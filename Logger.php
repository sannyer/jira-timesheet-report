<?php
/**
 * Created by PhpStorm.
 * User: smiglecz
 * Date: 4/17/18
 * Time: 4:27 PM
 */

class Logger {

    protected static $debugging = false;

    public static function debugOn() {
        self::$debugging = true;
    }

    public static function debugOff() {
        self::$debugging = false;
    }

    public static function dump() {
        if (!self::$debugging)
            return;
        foreach (func_get_args() as $arg) {
            echo "[" . date("Y-m-d h:m:s") . "/" . round(memory_get_usage(false)/1024/1024, 1), "MB/", round(memory_get_usage(true)/1024/1024, 1), "MB] ";
            if (is_scalar($arg)) {
                echo $arg, "\n";
            } else {
                print_r($arg);
                echo "-----------------------------------------------------------------------------------------------\n";
            }
        }
    }

    public static function dumpCallStack($minLevel = 0) {
        if (!self::$debugging)
            return;
        Logger::dump("CALL STACK", debug_backtrace());
    }


}