<?php
/**
 * Created by PhpStorm.
 * Date: 4/26/18
 * Time: 6:10 PM
 */

include_once "JiraReport.php";
include_once "Model.php";
include_once "User.php";

class WorkloadScheme extends Model {

    protected static $fields = ["id", "name" ,"memberCount", "days"];
    protected static $cache = [];

    public $id; // on purpose public for simplicity
    public $name; // on purpose public for simplicity
    public $memberCount; // on purpose public for simplicity
    public $days; // on purpose public for simplicity

    protected $users;

    public function loadReferences(JiraReport $report) {
        parent::loadReferences($report);
        $this->getUsers($report);
        $this->assignToUsers($report);
        Logger::dump($this . "====>" . join(", ", $this->users));
    }

    public static function getSchemeForUserId(JiraReport $report, $username) {
        foreach (static::$cache as $scheme) {
            $users = $scheme->getUsers($report);
            foreach ($users as $user) {
                if ($user->getId() == $username) {
                    return $scheme;
                }
            }
        }
        return false;
    }

    public function getUsers(JiraReport $report) {
        if ($this->users) {
            return $this->users;
        }
        return $this->users = ModelFactory::makeMultiple("User", $report->getWorkloadSchemeUsers($this->getId()));
    }

    public function assignToUsers(JiraReport $report) {
        $users = $this->getUsers($report);
        foreach ($users as $user) {
            $user->setWorkloadScheme($this);
        }
    }

    public function getLabel() {
        return $this->name;
    }

    public function getRequiredHoursOnDate($date) { // military date expected yyyy-mm-dd
        list($year, $month, $day) = preg_split("/-/", substr($date, 0, 10));
        $dow = 1 * date("N", mktime(0, 0, 0, $month, $day, $year)); // N is day of week 1..7 = Monday..Sunday
        if ($dow < 1 || $dow > 7) {
            throw new Exception("Some date mistery happened #1");
        }
        foreach ($this->days as $dayObject) {
            if ($dayObject->day == $dow) {
                return ($dayObject->seconds / 3600);
            }
        }
        throw new Exception("Some date mistery happened #2");
    }

}
