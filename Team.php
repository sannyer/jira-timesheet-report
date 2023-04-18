<?php
/**
 * Created by PhpStorm.
 * Date: 5/17/18
 * Time: 4:04 PM
 */

include_once "Model.php";

// id, name, summary, lead
class Team extends Model {

    protected static $fields = ["id" ,"name", "summary", "lead"];
    protected static $cache = [];

    public $id; // on purpose public for simplicity
    public $name;
    public $summary;
    public $lead; // userkey

    protected $users;
    protected $leadUser;

    public function __construct($obj) {
        parent::__construct($obj);
    }

    public function loadReferences(JiraReport $report) {
        parent::loadReferences($report);
        $this->users = ModelFactory::makeMultiple("User", $report->getTeamMembers($this->getId()));
        $this->leadUser = ModelFactory::make("User", $report->getUser($this->lead));
        User::loadAllReferences($report);
    }

    public function getUsers() {
        return $this->users;
    }

    public function getLabel() {
        return $this->name;
    }

}