<?php
/**
 * Created by PhpStorm.
 * User: smiglecz
 * Date: 5/18/18
 * Time: 10:38 AM
 */

class HolidayScheme_Day extends Model {

    protected static $fields = ["id" ,"name", "description", "duration", "type", "date"];
    protected static $cache = [];

    public $id;
    public $schemaId;
    public $name;
    public $description;
    public $duration;
    public $type;
    public $date;

    protected $day;
    protected $month;
    protected $year;
    protected static $parseFixedFormat = "dd/mmm";
    protected static $parseFloatingFormat = "dd/mmm/yy";

    protected $scheme;

    private static $months = ['jan'=>1, 'feb'=>2, 'mar'=>3, 'apr'=>4, 'may'=>5, 'jun'=>6, 'jul'=>7, 'aug'=>8, 'sep'=>9, 'oct'=>10, 'nov'=>11, 'dec'=>12];

    public function __construct($obj) {
        parent::__construct($obj);
        list($this->day, $this->month, $this->year) = $this->parse($this->date);
    }

    public function parse($date) {
        $day = $month = $year = null;
        if ($this->type == "fixed") {
            switch (self::$parseFixedFormat) {
                case "dd/mmm":
                    list($day, $month) = preg_split("/\\//", $date);
                    break;
                default:
                    throw new Exception("Unexpected date format: " . self::$parseFixedFormat);
            }
        } elseif ($this->type == "floating") {
            switch (self::$parseFloatingFormat) {
                case "dd/mmm/yy":
                    list($day, $month, $year) = preg_split("/\\//", $date);
                    break;
                default:
                    throw new Exception("Unexpected date format: " . self::$parseFloatingFormat);
            }
        } else {
            throw new Exception("Unexpected date type: " . $this->type);
        }
        $month = self::$months[strtolower($month)];
        return [$day, $month, $year];
    }

    public function match($date) {
        list($day, $month, $year) = $this->parse($date);
        return ($day == $this->day && $month == $this->month && ($this->year ? ($this->year == $year) : true));
    }

    public function matchDate($date) { // military date expected yyyy-mm-dd
        list($year, $month, $day) = preg_split("/-/", substr($date, 0, 10));
        return ($day == $this->day && $month == $this->month && ($this->year ? (($this->year + ($this->year < 100 ? 2000 : 0)) == $year) : true));
    }

    public function setParseFormat($fixedFormat, $floatingFormat) {
        self::$parseFixedFormat = $fixedFormat;
        self::$parseFloatingFormat = $floatingFormat;
    }

    public function setScheme(HolidayScheme $scheme) {
        $this->scheme = $scheme;
    }

    public function loadReferences(JiraReport $report) {
        parent::loadReferences($report);
    }

    public function getLabel() {
        return $this->name;
    }

}