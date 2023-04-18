<?php
/**
 * Created by PhpStorm.
 * Date: 4/17/18
 * Time: 10:09 AM
 */

include_once "JiraConnect.php";
include_once "Logger.php";

class JiraReport {

    public static $conn;

    public function __construct($conn) {
        self::$conn = $conn;
        Logger::dump("JiraReport construct");
    }

    public function run($args = null) {
        // to be overridden by descendants
    }

    public function getAllTeams() {
        return self::$conn->get("rest/tempo-teams/1/team");
    }

    public function getTeam($teamId) {
        return self::$conn->get("rest/tempo-teams/1/team/{$teamId}");
    }

    public function getTeamMembers($teamId) {
        $members = self::$conn->get("rest/tempo-teams/2/team/{$teamId}/member");
        $ret = [];
        foreach ($members as $i => $member) {
            if (in_array($member->member->type, ["USER", "GROUP_USER"]) &&
                $member->member->activeInJira /* &&
                $member->membership->status == "active"*/) {
                $obj = new stdClass();
                $obj->key = $member->member->key;
                $obj->username = $member->member->name;
                $obj->displayName = $member->member->displayname;
                $obj->active = $member->member->activeInJira;
                $ret[] = $obj;
            }
        }
        return $ret;
    }

    public function getUser($username) {
        $result = self::$conn->get("rest/api/2/user/search?username={$username}");
        if (count($result) == 0) {
            $obj = new stdClass();
            $obj->key = "";
            $obj->username = $username;
            $obj->displayName = "Inactive user ({$username})";
            $obj->active = false;
            return $obj;
        }
        $rawObj = $result[0];
        $obj = new stdClass();
        $obj->key = $rawObj->key;
        $obj->username = $rawObj->name;
        $obj->displayName = $rawObj->displayName;
        $obj->active = isset($rawObj->active) ? $rawObj->active : true;
        return $obj;
    }

    public function checkDateFormat($date) {
        if (is_string($date) && preg_match("/^\\d{4}-\\d{2}-\\d{2}$/", $date))
            return true;
        throw new Exception("Expected date format: YYYY-MM-DD.");
    }

    public function checkNumber($number) {
        if (is_numeric($number))
            return true;
        throw new Exception("Numeric value expected.");
    }

    // returns stdObject[]
    public function getWorkloadSchemes() {
        return self::$conn->get("rest/tempo-core/1/workloadscheme");
    }

    // returns stdObject
    public function getWorkloadScheme($schemeId) {
        return self::$conn->get("rest/tempo-core/1/workloadscheme/{$schemeId}");
    }

    // returns stdObject[]
    public function getWorkloadSchemeUsers($schemeId) {
        return self::$conn->get("rest/tempo-core/1/workloadscheme/users/{$schemeId}");
    }

    // returns stdObject[]
    public function getHolidaySchemes() {
        return self::$conn->get("rest/tempo-core/1/holidayscheme");
    }

    // returns stdObject
    public function getHolidayScheme($schemeId) {
        return self::$conn->get("rest/tempo-core/1/holidayscheme/{$schemeId}");
    }

    // returns stdObject[]
    public function getHolidaySchemeMembers($schemeId) {
        return self::$conn->get("rest/tempo-core/1/holidayscheme/{$schemeId}/members");
    }

    // returns stdObject[]
    public function getHolidaySchemeFloatingDays($schemeId) {
        return self::$conn->get("rest/tempo-core/1/holidayscheme/{$schemeId}/days/floating");
    }

    // returns stdObject[]
    public function getHolidaySchemeFixedDays($schemeId) {
        return self::$conn->get("rest/tempo-core/1/holidayscheme/{$schemeId}/days/fixed");
    }

    public function __toString() {
        return "JiraReport";
    }

    public function getTeamWorklogs($dateFrom, $dateTo, $teamId) {
        $timeline = $this->getDateTimeline($dateFrom, $dateTo);
        $result = [];
        while (count($timeline) > 0) {
            $week = array_splice($timeline, 0, 7);
            $start = array_shift($week);
            $end = count($week) > 0 ? array_pop($week) : $start;
            $part = self::$conn->get("rest/tempo-timesheets/3/worklogs/?dateFrom={$start}&dateTo={$end}&teamId={$this->teamId}");
            $result = array_merge($result, $part);
        }
        return $result;
    }

    public function dateToTimestamp($date) {
        $this->checkDateFormat($date);
        list ($y, $m, $d) = preg_split("/-/", $date);
        return mktime(0, 0, 0, $m, $d, $y);
    }

    public function getDateTimeline($dateFrom, $dateTo) {
        $tsFrom = $this->dateToTimestamp($dateFrom);
        $tsTo = $this->dateToTimestamp($dateTo);
        if ($tsFrom > $tsTo) {
            $x = $tsFrom;
            $tsFrom = $tsTo;
            $tsTo = $x;
        }
        $ret = [];
        for ($ts = $tsFrom; $ts <= $tsTo; $ts += 86400) {
            $ret[] = date("Y-m-d", $ts);
        }
        return $ret;
    }

    public function shortenDate($date) {
        $this->checkDateFormat($date);
        list(, $m, $d) = preg_split("/-/", $date);
        return (integer) $m . "/" . (integer) $d;
    }
}