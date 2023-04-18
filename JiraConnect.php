<?php

include_once "Logger.php";

Class JiraConnect {

	protected $baseUrl;
	protected $user;
	protected $pass;
	public static $conn;

	protected static $curlCacheOn = true;
	protected static $curlCache = [];

	public function __construct($baseUrl, $user, $pass) {
	    $this->baseUrl = $baseUrl;
        $this->user = $user;
        $this->pass = $pass;
        date_default_timezone_set("America/New_York");
    }

    public function call($verb, $path_query = "", $json_payload = null) {
	    $uri = $this->baseUrl . $path_query;
        $json_data = json_encode($json_payload, JSON_UNESCAPED_SLASHES);

        if (array_key_exists($uri, self::$curlCache)) {
            Logger::dump("Curl cache reading: " . $uri);
            $result = self::$curlCache[$uri];
        } else {
            Logger::dump("Curl $verb: " . $uri);
            if ($json_payload) Logger::dump("body to send", $json_data);
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_USERPWD, $this->user.":".$this->pass);
            curl_setopt($curl, CURLOPT_URL, $uri);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curl, CURLOPT_VERBOSE, 0);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $verb);
            if (in_array($verb, ["PUT", "POST"])) {
                curl_setopt($curl, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($json_data)
                ]);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $json_data);
            }
            $result = curl_exec($curl);
            if (self::$curlCacheOn) self::$curlCache[$uri] = $result;
        }

        $ret = json_decode($result);
        Logger::dump("Result: " . strlen($result) . " bytes, type: ". gettype($ret) . ", count: " . count($ret));
        return $ret;
    }

    public function get($path_query, $json_payload = null) {
        return $this->call('GET', $path_query, $json_payload);
    }

    public function post($path_query, $json_payload = null) {
        return $this->call('POST', $path_query, $json_payload);
    }

    public function put($path_query, $json_payload = null) {
        return $this->call('PUT', $path_query, $json_payload);
    }

    public function options($path_query, $json_payload = null) {
        return $this->call('OPTIONS', $path_query, $json_payload);
    }

    public function __toString() {
	    return "JiraConnect to " . $this->baseUrl;
    }

    public static function curlCacheOff() {
        self::$curlCacheOn = false;
    }

    public static function curlCacheOn() {
        self::$curlCacheOn = true;
    }
}