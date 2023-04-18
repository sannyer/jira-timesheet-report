<?php
/**
 * Created by PhpStorm.
 * User: smiglecz
 * Date: 4/26/18
 * Time: 6:15 PM
 */

include_once "Model.php";
include_once "WorkloadScheme.php";
include_once "HolidayScheme.php";

class User extends Model {

    public static $idField = "username";
    protected static $fields = ["key" ,"username", "displayName"];
    protected static $cache = [];

    public $key; // on purpose public for simplicity
    public $username; // on purpose public for simplicity
    public $displayName; // on purpose public for simplicity
    public $active;

    protected $workloadScheme;
    protected $holidayScheme;

    public function __construct($obj) {
        parent::__construct($obj);
    }

    public function loadReferences(JiraReport $report) {
        parent::loadReferences($report);
        $this->getWorkLoadScheme($report);
        $this->getHolidayScheme($report);
    }

    public function getWorkLoadScheme(JiraReport $report) {
        if ($this->workloadScheme) {
            return $this->workloadScheme;
        }
        $this->workloadScheme = WorkloadScheme::getSchemeForUserId($report, $this->getId());
        if ($this->workloadScheme) {
            return $this->workloadScheme;
        }
        throw new Exception("No workload scheme loaded that lists user: " . $this->username);
    }

    public function getHolidayScheme(JiraReport $report) {
        if ($this->holidayScheme) {
            return $this->holidayScheme;
        }
        $this->holidayScheme = HolidayScheme::getSchemeForUserId($report, $this->getId());
        if ($this->holidayScheme) {
            return $this->holidayScheme;
        }
        throw new Exception("No holiday scheme loaded that lists user: " . $this->username);
    }

    public function setWorkloadScheme(WorkloadScheme $scheme) {
        $this->workloadScheme = $scheme;
    }

    public function setHolidayScheme(HolidayScheme $scheme) {
        $this->holidayScheme = $scheme;
    }

    public function getLabel() {
        return $this->displayName;
    }

    public function getRequiredHoursOnDate($date) { // military date expected yyyy-mm-dd
        Logger::dump("USER: " . $this, "WL: " . $this->workloadScheme, "HD: " . $this->holidayScheme);
        if ($this->holidayScheme->isHoliday($date)) {
            return 0;
        }
        return $this->workloadScheme->getRequiredHoursOnDate($date);
    }

}