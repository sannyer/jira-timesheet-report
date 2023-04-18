<?php
/**
 * Created by PhpStorm.
 * User: smiglecz
 * Date: 4/17/18
 * Time: 10:44 AM
 */

include_once "JiraConnect.php";
include_once "JiraReport_TimesheetViolation.php";
include_once "Team.php";
include_once "HolidayScheme_Day.php";

$config_json = file_get_contents("jiraconfig.json");
$config = json_decode($config_json);
Logger::debugOff();

function errorHandler($errno, $errstr, $errfile, $errline, $errcontext) {
    Logger::dump("ERRNO", $errno);
    Logger::dump("ERRSTR", $errstr);
    Logger::dump("ERRFILE", $errfile);
    Logger::dump("ERRLINE", $errline);
    Logger::dump("ERRCONTEXT", $errcontext);
    Logger::dumpCallStack();
}
set_error_handler("errorHandler", E_ALL | E_STRICT);

$conn = new JiraConnect($config->baseUrl, $config->user, $config->pass);

$report = new JiraReport_TimesheetViolation($conn, 40, "2018-06-01", "2018-06-06");
$result = $report->run();
Logger::dump("TIMESHEET VIOLATION", $result);
$formatted = $report->formatResult();
echo $formatted['body'];


