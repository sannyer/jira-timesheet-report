<?php
/**
 * Created by PhpStorm.
 * Date: 5/4/18
 * Time: 3:34 PM
 */

include_once "User.php";
include_once "Team.php";
include_once "WorkloadScheme.php";
include_once "HolidayScheme.php";

class ModelFactory {

    public static function make($className, $object) {
        if (!is_object($object)) {
            throw new Exception("Not object to instantiate");
        }
        if (get_class($object) == $className) {
            return $object;
        }
        $idField = $className::$idField;
        $id = $object->$idField;
        $instance = $className::getById($id);
        if (!$instance) {
            $instance = new $className($object);
        }
        return $instance;
    }

    public static function makeMultiple($className, $objects) {
        $instances = array();
        foreach ($objects as $object) {
            $instances[] = static::make($className, $object);
        }
        return $instances;
    }
}