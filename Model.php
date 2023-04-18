<?php
/**
 * Created by PhpStorm.
 * User: smiglecz
 * Date: 4/26/18
 * Time: 4:58 PM
 */

include_once "JiraConnect.php";

abstract class Model {

    public static $idField = "id"; // on purpose public for simplicity
    protected static $fields = [];
    protected static $cache = [];

    public static function getCache(){
        return static::$cache;
    }

    public static function getFromCache($id) {
        return array_key_exists($id, static::$cache) ? static::$cache[$id] : false;
    }

    public static function addToCache(Model $obj) {
        static::$cache[$obj->getId()] = $obj;
    }

    public function __construct($obj) {
        $vars = static::$fields;
        foreach ($vars as $key) {
            $this->$key = $obj->$key;
        }
        static::addToCache($this);
    }

    public function __destruct() {
        Logger::dump("DESTRUCTING: " . $this);
    }

    public static function getById($id) {
        return static::getFromCache($id);
    }

    public function getId() {
        return $this->{static::$idField};
    }

    public function loadReferences(JiraReport $report) {
        Logger::dump("*** load references for ", $this->__toString());
    }

    public static function loadAllReferences(JiraReport $report) {
        $calledClass = get_called_class();
        Logger::dump("**** load all references: " . $calledClass . " (".count(static::$cache)." of ". join(", ", static::getCachedIds()) . ")");
        foreach (static::$cache as $object) {
            $object->loadReferences($report);
        }
    }

    // generic function to return something about content. to be overridden
    public function getLabel() {
    }

    public function __toString() {
        return $this->getLabel() . " (" . get_class($this) . "#" . $this->getId() . ")";
    }

    public static function getCachedIds() {
        $ret = [];
        foreach (static::$cache as $item) {
            $ret[] = $item->getId();
        }
        return $ret;
    }
}