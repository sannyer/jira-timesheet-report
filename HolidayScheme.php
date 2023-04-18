<?php
/**
 * Created by PhpStorm.
 * Date: 5/17/18
 * Time: 4:39 PM
 */

include_once "JiraReport.php";
include_once "Model.php";
include_once "User.php";

class HolidayScheme extends Model {

    protected static $fields = ["id" ,"name", "count", "defaultScheme"];
    protected static $cache = [];

    public $id; // on purpose public for simplicity
    public $name; // on purpose public for simplicity
    public $count; // on purpose public for simplicity
    public $defaultScheme; // on purpose public for simplicity

    protected $users;
    protected $fixedDays;
    protected $floatingDays;

    protected $report;

    public function loadReferences(JiraReport $report) {
        parent::loadReferences($report);
        $this->report = $report;
        $this->getUsers($report);
        $this->assignToUsers($report);
        Logger::dump($this . "====>" . join(", ", $this->users));
    }

    public function loadDays(JiraReport $report) {
        $this->fixedDays = ModelFactory::makeMultiple("HolidayScheme_Day", $report->getHolidaySchemeFixedDays($this->getId()));
        $this->floatingDays = ModelFactory::makeMultiple("HolidayScheme_Day", $report->getHolidaySchemeFloatingDays($this->getId()));
        foreach ($this->fixedDays as $day) {
            $day->setScheme($this);
        }
        foreach ($this->floatingDays as $day) {
            $day->setScheme($this);
        }
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
        return $this->users = ModelFactory::makeMultiple("User", $report->getHolidaySchemeMembers($this->getId()));
    }

    public function assignToUsers(JiraReport $report) {
        $users = $this->getUsers($report);
        foreach ($users as $user) {
            $user->setHolidayScheme($this);
        }
    }

    public function getLabel() {
        return $this->name;
    }

    public function isHoliday($date) {
        if (!$this->fixedDays || !$this->floatingDays) {
            $this->loadDays($this->report);
        }
        foreach ($this->fixedDays as $day) {
            if ($day->matchDate($date)) {
                return true;
            }
        }
        foreach ($this->floatingDays as $day) {
            if ($day->matchDate($date)) {
                return true;
            }
        }
        return false;
    }

}