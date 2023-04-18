<?php
/**
 * Created by PhpStorm.
 * User: smiglecz
 * Date: 4/17/18
 * Time: 10:13 AM
 */

include_once "JiraReport.php";
include_once "WorkloadScheme.php";
include_once "HolidayScheme.php";
include_once "User.php";
include_once "Team.php";
include_once "ModelFactory.php";

class JiraReport_TimesheetViolation extends JiraReport {

    public $teamId;
    public $dateFrom;
    public $dateTo;

    protected $team;

    protected $requiredVsLoggedHours;
    protected $result;

    protected $defaultArgs = [
        'dailyThreshold' => 0.125, // 0.125 = 12.5% = 1/8. Max allowed shortage of logged vs required hours
        'totalThreshold' => 0.125, // 0.125 = 12.5% = 1/8. Max allowed shortage of logged vs required hours
        'ignoreWorkAhead' => false, // if ignore work ahead is true, then a 10h+6h logging in 2-day interval the 2nd day will be a violation. If not ignored, the work ahead will compensate the 2nd day.
    ];
    protected $args;

    public function __construct($conn, $teamId, $dateFrom, $dateTo) {
        $this->checkNumber($teamId);
        $this->checkDateFormat($dateFrom);
        $this->checkDateFormat($dateTo);
        parent::__construct($conn);
        $this->teamId = $teamId;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->cacheUp();
    }

    /**
     * @param $args array of key-val pairs
     * @throws Exception
     */
    public function run($args = null) {
        extract($this->defaultArgs);
        if (is_array($args)) {
            $this->args = $args;
            extract($args);
        }
        $violations = $this->getRequiredVsLoggedHours();
        foreach ($violations as $i => $data) {
            $isShort = false;
            if ($data['logged'] < $data['required'] * (1 - $totalThreshold)) {
                $violations[$i]['isShort'] = true;
                $isShort = true;
            }
            $cumulative = 0;
            foreach ($data['detailed'] as $date => $hours) {
                $cumulative += $hours['logged'] - $hours['required'];
                if ($hours['logged'] + ($ignoreWorkAhead ? 0 : ($cumulative > 0 ? $cumulative : 0)) < $hours['required'] * (1 - $dailyThreshold)) {
                    $violations[$i]['detailed'][$date]['isShort'] = true;
                }
            }
            if (!$isShort) {
                //unset($violations[$i]);
            }
        }
        Logger::dump("VIOLATIONS", $violations);
        return $this->result = $violations;
    }

    // returns array[username]{required:number, logged:number}
    public function getRequiredVsLoggedHours() {
        $loggedHours = $this->getLoggedHours();
        $detailedHours = [];
        $totalUserHours = [];
        foreach ($this->team->getUsers() as $user) {
            $username = $user->username;
            if (!isset($detailedHours[$username])) {
                $detailedHours[$username] = [];
            }
            $userTotalRequired = 0;
            $userTotalLogged = 0;
            foreach ($this->getDateTimeline($this->dateFrom, $this->dateTo) as $date) {
                $required = $user->getRequiredHoursOnDate($date);
                $logged = 0;
                if (isset($loggedHours[$date]) && isset($loggedHours[$date][$username])) {
                    $logged = $loggedHours[$date][$username];
                }
                $detailedHours[$username][$date] = ['required' => $required, 'logged' => $logged];
                $userTotalRequired += $required;
                $userTotalLogged += $logged;
            }
            $totalUserHours[] = ['user' => $username, 'required' => $userTotalRequired, 'logged' => $userTotalLogged, 'detailed' => $detailedHours[$username]];
        }

        usort($totalUserHours, "JiraReport_TimesheetViolation::compareRVL");
        $this->requiredVsLoggedHours = $totalUserHours;
        Logger::dump("Required vs Logged", $totalUserHours);
        return $totalUserHours;
    }

    protected function compareRVL($a, $b) {
        if ($a['logged'] - $a['required'] < $b['logged'] - $b['required']) return -1;
        if ($a['logged'] - $a['required'] > $b['logged'] - $b['required']) return 1;
        return 0;
    }

    // returns array[yyyy-mm-dd][username] = hours
    public function getLoggedHours() {
        JiraReport::$conn->curlCacheOff();
        $data = parent::getTeamWorklogs($this->dateFrom, $this->dateTo, $this->teamId);
        $sums = [];
        foreach ($data as $i => $k) {
            $user = $k->author->name;
            $hours = $k->timeSpentSeconds / 60 / 60;
            $date = substr($k->dateStarted,0,10);
            if (!isset($sums[$date])) {
                $sums[$date] = [];
            }
            if (!isset($sums[$date][$user])) {
                $sums[$date][$user] = 0;
            }
            $sums[$date][$user] += $hours;
        }
        Logger::dump("SUMS", $sums);

        return $sums;
    }

    // load into memory and instantiate all records that we'll work with such as
    //     - workload schemes
    //     - vacation schemes
    //     - users
    //     - teams
    public function cacheUp() {
        $this->loadWorkloadSchemes();
        WorkloadScheme::loadAllReferences($this);
        $this->loadHolidaySchemes();
        WorkloadScheme::loadAllReferences($this);
        HolidayScheme::loadAllReferences($this);
        $this->team = $this->getTeam($this->teamId);
        Team::loadAllReferences($this);
        WorkLoadScheme::loadAllReferences($this);
        HolidayScheme::loadAllReferences($this);
        $this->optimizeCache();
    }

    public function loadWorkloadSchemes() {
        ModelFactory::makeMultiple("WorkloadScheme", $this->getWorkloadSchemes());
    }

    public function loadHolidaySchemes() {
        ModelFactory::makeMultiple("HolidayScheme", $this->getHolidaySchemes());
    }

    public function loadAllTeams() {
        ModelFactory::makeMultiple("Team", $this->getAllTeams());
    }

    // this will keep only the affected holiday- and workload schemes in cache, the rest will be freed
    public function optimizeCache() {
        $all = array_merge(Team::getCache(), User::getCache(), WorkloadScheme::getCache(), HolidayScheme::getCache());
        Logger::dump("ALL CACHE", join("\n", $all));
    }

    /**
     * The purpose is to obtain an object representation during the reporting thru this function. This will return the object
     * from cache if used before. If not present, attempts to load by rest api then return its typed object representation.
     * @param $teamId
     * @return Team
     */
    public function getTeam($teamId) {
        $team = Team::getFromCache($teamId);
        if ($team !== false) return $team;
        return ModelFactory::make("Team", parent::getTeam($teamId));
    }

    /**
     * The purpose is to obtain an object representation during the reporting thru this function. This will return the object
     * from cache if used before. If not present, attempts to load by rest api then return its typed object representation.
     * @param $schemeId
     * @return WorkloadScheme
     */
    public function getWorkloadScheme($schemeId) {
        $scheme = WorkloadScheme::getFromCache($schemeId);
        return $scheme !== false ? $scheme : ModelFactory::make("WorkloadScheme", parent::getWorkloadScheme($schemeId));
    }

    /**
     * The purpose is to obtain an object representation during the reporting thru this function. This will return the object
     * from cache if used before. If not present, attempts to load by rest api then return its typed object representation.
     * @param $schemeId
     * @return HolidayScheme
     */
    public function getHolidayScheme($schemeId) {
        $scheme = HolidayScheme::getFromCache($schemeId);
        return $scheme !== false ? $scheme : ModelFactory::make("HolidayScheme", parent::getHolidayScheme($schemeId));
    }

    /**
     * The purpose is to obtain an object representation during the reporting thru this function. This will return the object
     * from cache if used before. If not present, attempts to load by rest api then return its typed object representation.
     * @param $userkey
     * @return array
     */
    public function getUser($username) {
        $user = User::getFromCache($username);
        return $user !== false ? $user : ModelFactory::make("User", parent::getUser($username));
    }

    public function __toString() {
        return "JiraReport_TimesheetViolation for team: " . $this->team->getLabel();
    }

    public function getArg($argname) {
        if (array_key_exists($argname, $this->args))
            return $this->args[$argname];
        if (array_key_exists($argname, $this->defaultArgs))
            return $this->defaultArgs[$argname];
        return null;
    }

    public function formatResult() {
        extract($this->defaultArgs);
        if (is_array($this->args)) extract($this->args);
        $violations = $this->result;

        $shortFrom = $this->shortenDate($this->dateFrom);
        $shortTo = $this->shortenDate($this->dateTo);
        $subject = "JIRA Report: Timesheet Violation " . $shortFrom . " – " . $shortTo;
        $body = "<html lang='en'>";
        $body .= "<head>";
        $body .= "<title>" . $subject . "</title>";
        $body .= $this->getHtmlStyle();
        $body .= "</head>";
        $body .= "<body>";
        $body .= "<p><b>Dear PMs,</b><p>\n";
        $body .= "<p>Please find below the members of <b>" . $this->team->name . "</b> team who have incomplete or missing timesheets for the month.</p>\n";
        $body .= "<ul>\n";
        $body .= "<li>Date interval: {$this->dateFrom} – {$this->dateTo}</li>\n";
        $body .= "<li>Daily maximum shortage allowed: " . round($dailyThreshold * 100, 2) . "% (" . round($dailyThreshold * 8, 2) . "h in a 8-hour day)</li>\n";
        $body .= "<li>Total maximum shortage allowed: " . round($totalThreshold * 100, 2) . "% (" . round($dailyThreshold * 176, 2) . "h in a 176-hour month)</li>\n";
        if ($ignoreWorkAhead) {
            $body .= "<li>Extra hours worked ahead are ignored determining if a subsequent day is short or not</li>\n";
        } else {
            $body .= "<li>Extra hours worked ahead can compensate shorter subsequent days</li>\n";
        }
        $body .= "</ul>\n";
        $body .= "<table border='1' class='timesheetviolation'>\n";
        $body .= "<tr>\n";
        $body .= "<th>Employee</th>\n";
        $body .= "<th><table width='100%' border='0' cellpadding='0' cellspacing='0'><tr><th align='left'>{$shortFrom}</th><th align='center'>-</th><th align='right'>{$shortTo}</th></tr></table></th>\n";
        $body .= "<th>Logged Hours</th>\n";
        $body .= "</tr>\n";
        foreach ($violations as $details) {
            $username = $details['user'];
            $user = User::getById($username);
            $displayName = $user->displayName;
            $wlScheme = $user->getWorkloadScheme($this);
            $hdScheme = $user->getHolidayScheme($this);
            $body .= "<tr>\n";
            $body .= "<td><b>{$displayName}</b><br/><small>" . $wlScheme->getLabel() . "</small><br/><small>" . $hdScheme->getLabel() . "</small></td>\n";
            $daysList = [];
            $body .= "<td>\n";
            foreach ($details['detailed'] as $date => $hours) {
                $body .= $this->getHtmlDayBar($this->shortenDate($date), $hours['required'], $hours['logged'], $hours['isShort']);
            }
            $body .= "</td>\n";
            $diff = $details['required'] - $details['logged'];
            $body .= "<td>" . $this->getHtmlSummaryBar($details['required'], $details['logged'], $details['isShort']) . "</td>\n";
            $body .= "</tr>\n";
        }
        $body .= "</table>\n";
        $body .= "</body>\n";
        $body .= "</html>\n";

        return [
            'subject' => $subject,
            'body' => $body
        ];
    }

    protected function getHtmlDayBar($date, $required, $logged, $isShort) {
        $rh = round(20 * $required / 8);
        $wh = round(20 * $logged / 8);
        $short = $isShort ? "short" : "";
        $l = ($required != 0 || $logged != 0) ? round($logged, 1) : "&nbsp;";
        $html = "<span class='bar' title='{$date}'>" .
            "<span class='req' style='height:{$rh}px;'></span>" .
            "<span class='wrk {$short}' style='height:{$wh}px;'></span>" .
            "<span class='txt'>{$l}</span>" .
            "</span>";
        return $html;
    }

    protected function getHtmlSummaryBar($required, $logged, $isShort) {
        $rw = round(2 * $required);
        $ww = round(2 * $logged);
        $short = $isShort ? "short" : "";
        $l = ($required != 0 || $logged != 0) ? round($logged, 1) : "&nbsp;";
        $html = "<div class='bar'>" .
            "<div class='req' style='width:{$rw}px;'></div>" .
            "<div class='wrk {$short}' style='width:{$ww}px;'></div>" .
            "<div class='txt'>" . round($logged,1) . "h of " . round($required,1) . "h</div>" .
            "</div>";
        return $html;
    }

    protected function getHtmlStyle() {
        $style = <<<STYLE
<style type="text/css">
    table.timesheetviolation * {
        white-space: nowrap;
    }
    table.timesheetviolation td, table.timesheetviolation th {
        font-family: Arial;
        font-size: 12px;
    }
    table.timesheetviolation > tr > th {
        text-align: left;
    }
    table.timesheetviolation tr.data {
        height:30px;
    }
    table.timesheetviolation span.bar {
        padding:0; margin:0;
        display:inline-block;
        position:relative; width:20px; height: 30px;
        text-align: center;
        z-index:12;
        overflow:hidden;
    }
    table.timesheetviolation span.req {
        position:absolute;
        bottom:0; left:0;
        width: 20px;
        background-color: rgba(200,200,200,0.3);
        z-index:11;
    }
    table.timesheetviolation span.wrk {
        position:absolute;
        bottom:0; left:0;
        width: 20px;
        background-color: lightgreen;
        z-index: 10;
    }
    table.timesheetviolation span.txt {
        position:absolute;
        bottom:0; left:0;
        width: 20px;
        z-index: 12;
        font-family: "Arial Narrow", Arial;
        font-size: 10px;
        color: black;
    }
    table.timesheetviolation span.short, table.timesheetviolation div.short {
        background-color: lightpink !important;
    }
    table.timesheetviolation div.bar {
        padding:0; margin:0;
        position:relative; height: 30px;
        z-index:12;
        overflow:hidden;
    }
    table.timesheetviolation div.req {
        position:absolute;
        top:0; left:0;
        height: 30px;
        background-color: rgba(200,200,200,0.3);
        z-index:11;
    }
    table.timesheetviolation div.wrk {
        position:absolute;
        top:0; left:0;
        height: 30px;
        background-color: lightgreen;
        z-index: 10;
    }
    table.timesheetviolation div.txt {
        position:absolute;
        top:5px; left:0;
        z-index: 12;
        font-family: "Arial Narrow";
        font-size: 12px;
        color: black;
    }
</style>
STYLE;
        return $style;
    }

}
