<?php
/**********************************************************
 * Radiko                                                 *
 * Copyright © 2020 kokarare1212 All rights reserved.     *
 *                                                        *
 * This software is released under the Apache License 2.0 *
 * see http://www.apache.org/licenses/LICENSE-2.0         *
 **********************************************************/

require("Config.php");
require("Exception.php");

class Radiko {
    private $authToken = null;
    private $region = null;
    function __construct($regionId = null){
        if($regionId == null){
            $this->init();
        } else {
            $this->initWithRegionId($regionId);
        }
    }
    private function init(){
        $auth1Headers = array(
            "X-Radiko-App: pc_html5",
            "X-Radiko-App-Version: 0.0.1",
            "X-Radiko-User: dummy_user",
            "X-Radiko-Device: pc"
        );
        $auth1Curl = curl_init(AUTH1_URL);
        curl_setopt($auth1Curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($auth1Curl, CURLOPT_HTTPHEADER, $auth1Headers);
        curl_setopt($auth1Curl, CURLOPT_HEADER, true);
        curl_setopt($auth1Curl, CURLOPT_NOBODY, true);
        $auth1Response = curl_exec($auth1Curl);
        $auth1Info = curl_getinfo($auth1Curl);
        if($auth1Info["http_code"] != 200){
            throw new RadikoException("Failed to authenticate auth1");
        }
        $auth1ResponseHeader = $this->header2array($auth1Response);
        $authToken = isset($auth1ResponseHeader["X-Radiko-AuthToken"]) ? $auth1ResponseHeader["X-Radiko-AuthToken"] : $auth1ResponseHeader["X-RADIKO-AUTHTOKEN"];
        $keyLength = (int)$auth1ResponseHeader["X-Radiko-KeyLength"];
        $keyOffset = (int)$auth1ResponseHeader["X-Radiko-KeyOffset"];
        $partialKey = base64_encode(substr(PC_AUTHKEY, $keyOffset, $keyLength));
        $auth2Headers = array(
            "X-Radiko-AuthToken: {$authToken}",
            "X-Radiko-PartialKey: {$partialKey}",
            "X-Radiko-User: dummy_user",
            "X-Radiko-Device: pc"
        );
        $auth2Curl = curl_init(AUTH2_URL);
        curl_setopt($auth2Curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($auth2Curl, CURLOPT_HTTPHEADER, $auth2Headers);
        curl_setopt($auth2Curl, CURLOPT_HEADER, true);
        $auth2Response = curl_exec($auth2Curl);
        $auth2Info = curl_getinfo($auth2Curl);
        $auth2ResponseBody = substr($auth2Response, $auth2Info["header_size"]);
        if($auth2Info["http_code"] != 200){
            throw new RadikoException("Failed to authenticate auth2");
        }
        preg_match('/(\w+),/', $auth2ResponseBody, $regionArray);
        $region = $regionArray[1];
        $this->authToken = $authToken;
        $this->region = $region;
    }
    private function initWithRegionId($regionId){
        if(!in_array($regionId, REGION_LIST)){
            throw new RadikoException("The region id isn't found");
        }
        $info = $this->getRandomInfo();
        $auth1Headers = array(
            "User-Agent: {$info["userAgent"]}",
            "X-Radiko-App: aSmartPhone7a",
            "X-Radiko-App-Version: {$info["appVersion"]}",
            "X-Radiko-User: {$info["userId"]}",
            "X-Radiko-Device: {$info["device"]}"
        );
        $auth1Curl = curl_init(AUTH1_URL);
        curl_setopt($auth1Curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($auth1Curl, CURLOPT_HTTPHEADER, $auth1Headers);
        curl_setopt($auth1Curl, CURLOPT_HEADER, true);
        curl_setopt($auth1Curl, CURLOPT_NOBODY, true);
        $auth1Response = curl_exec($auth1Curl);
        $auth1Info = curl_getinfo($auth1Curl);
        if($auth1Info["http_code"] != 200){
            var_dump($auth1Response);
            var_dump($info);
            throw new RadikoException("Failed to authenticate auth1");
        }
        $auth1ResponseHeader = $this->header2array($auth1Response);
        $authToken = isset($auth1ResponseHeader["X-Radiko-AuthToken"]) ? $auth1ResponseHeader["X-Radiko-AuthToken"] : $auth1ResponseHeader["X-RADIKO-AUTHTOKEN"];
        $keyLength = (int)$auth1ResponseHeader["X-Radiko-KeyLength"];
        $keyOffset = (int)$auth1ResponseHeader["X-Radiko-KeyOffset"];
        $partialKey = base64_encode(substr(base64_decode(APP_AUTHKEY), $keyOffset, $keyLength));
        $auth2Headers = array(
            "User-Agent: {$info["userAgent"]}",
            "X-Radiko-App: aSmartPhone7a",
            "X-Radiko-App-Version: {$info["appVersion"]}",
            "X-Radiko-User: {$info["userId"]}",
            "X-Radiko-Device: {$info["device"]}",
            "X-Radiko-AuthToken: {$authToken}",
            "X-Radiko-PartialKey: {$partialKey}",
            "X-Radiko-Connection: wifi",
            "X-Radiko-Location: ".$this->getLocation($regionId)
        );
        $auth2Curl = curl_init(AUTH2_URL);
        curl_setopt($auth2Curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($auth2Curl, CURLOPT_HTTPHEADER, $auth2Headers);
        curl_setopt($auth2Curl, CURLOPT_HEADER, true);
        $auth2Response = curl_exec($auth2Curl);
        $auth2Info = curl_getinfo($auth2Curl);
        $auth2ResponseBody = substr($auth2Response, $auth2Info["header_size"]);
        if($auth2Info["http_code"] != 200){
            throw new RadikoException("Failed to authenticate auth2");
        }
        preg_match('/(\w+),/', $auth2ResponseBody, $regionArray);
        $region = $regionArray[1];
        $this->authToken = $authToken;
        $this->region = $region;
    }
    public function changeRegion($regionId){
        if(!in_array($regionId, REGION_LIST)){
            throw new RadikoException("The region id isn't found");
        }
        $this->initWithRegionId($regionId);
    }
    public function getStations(){
        $stationsCurl = curl_init(STATION_BASE_URL.$this->region.".xml");
        curl_setopt($stationsCurl, CURLOPT_RETURNTRANSFER, true);
        $stationsResponse = curl_exec($stationsCurl);
        $stationsInfo = curl_getinfo($stationsCurl);
        if($stationsInfo["http_code"] != 200){
            throw new RadikoException("Failed to get stations");
        }
        return new SimpleXMLElement($stationsResponse);
    }
    public function getPrograms($station, $date = null){
        if($date == null){
            $date = date("Ymd");
        }
        if(!preg_match("/\d{8}/", $date)){
            throw new RadikoException("Invalied aragments");
        }
        $programsCurl = curl_init(PROGRAM_BASE_URL.$date."/".$station.".xml");
        curl_setopt($programsCurl, CURLOPT_RETURNTRANSFER, true);
        $programsResponse = curl_exec($programsCurl);
        $programsInfo = curl_getinfo($programsCurl);
        if($programsInfo["http_code"] != 200){
            throw new RadikoException("Failed to get programs");
        }
        return new SimpleXMLElement($programsResponse);
    }
    private function header2array($headers){
        $result = array();
        $headers = str_replace(array("\r\n", "\r", "\n"), "\n", $headers);
        foreach(explode("\n", $headers) as $header){
            if(strpos($header,":") !== false){
                preg_match("/(.*):\s(.*)/", $header, $headerResult);
                $result[$headerResult[1]] = $headerResult[2];
            }
        }
        return $result;
    }
    private function getRandomInfo(){
        $appVersion = APP_VERSION_LIST[array_rand(APP_VERSION_LIST)];
        $userId = "";
        for($i = 0;$i < 32; $i++){
            $userId .= USERID_BASE_LIST[array_rand(USERID_BASE_LIST)];
        }
        $ver = array_keys(VERSIONMAP)[array_rand(array_keys(VERSIONMAP))];
        $sdk = VERSIONMAP[$ver]["sdk"];
        $build = VERSIONMAP[$ver]["builds"][array_rand(VERSIONMAP[$ver]["builds"])];
        $model = MODEL_LIST[array_rand(MODEL_LIST)];
        $device = $sdk.".".$model;
        $userAgent = "Dalvik/2.1.0 (Linux; U; Android {$ver}; {$model}/{$build})";
        return ["appVersion"=>$appVersion, "userId"=>$userId, "userAgent"=>$userAgent, "device"=>$device];
    }
    private function getLocation($regionId){
        if(!in_array($regionId, REGION_LIST)){
            throw new RadikoException("The region id isn't found");
        }
        $lat = LOCATION_LIST[$regionId][0];
        $lon = LOCATION_LIST[$regionId][1];
        $lat += $this->random() / 40.0 * (($this->random() > 0.5) ? 1 : -1);
        $lon += $this->random() / 40.0 * (($this->random() > 0.5) ? 1 : -1);
        $lat = round($lat, 6);
        $lon = round($lon, 6);
        return "{$lat},{$lon},gps";
    }
    public function getAuthToken(){
        return $this->authToken;
    }
    public function getRegionId(){
        return $this->region;
    }
    private function random(){
        return mt_rand() / mt_getrandmax();
    }
}
$r = new Radiko();
$r->getStations();