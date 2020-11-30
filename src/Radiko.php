<?php
/*
 * Copyright © 2020 kokarare1212 All right reserved.
 */

namespace kokarare1212;

use GuzzleHttp\Client;

class Radiko
{
  private $HttpClient;
  private $RadikoAppName = "aSmartPhone7o";
  function __construct(){
    $this->HttpClient = new Client();
  }
  
  /**
   * @param string $StationID
   * @return string
   */
  public function GetAreaID4StationID(string $StationID): string{
    $Stations = $this->GetStations();
    foreach($Stations as $Station){
      if($Station["id"] === $StationID){
        return $Station["area_id"];
      }
    }
    return "";
  }
  
  /**
   * @param string|null $AreaID
   * @return string
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function GetAuthToken(string $AreaID=null): string{
    $auth1 = $this->HttpClient->get("https://radiko.jp/v2/api/auth1", [
      "headers" => [
        "X-Radiko-App" => $this->RadikoAppName,
        "X-Radiko-App-Version" => "0.0.1",
        "X-Radiko-Device" => "PHP.Radiko",
        "X-Radiko-User" => "dummy_user",
      ],
    ]);
    $AuthToken = $auth1->getHeader("X-Radiko-AuthToken")[0];
    $PartialKeyOffset = (int)$auth1->getHeader("X-Radiko-KeyOffset")[0];
    $PartialKeyLength = (int)$auth1->getHeader("X-Radiko-KeyLength")[0];
    $PartialKey = base64_encode(
      file_get_contents(dirname(__FILE__)."/key/{$this->RadikoAppName}.bin",
      false, null, $PartialKeyOffset, $PartialKeyLength)
    );
    if($this->IsAvailableAreaID($AreaID)){
      $CoordinatesList = json_decode(
        file_get_contents(dirname(__FILE__)."/json/coordinates.json")
      );
      $Coordinate = "{$CoordinatesList->$AreaID[0]},{$CoordinatesList->$AreaID[1]},gps";
    } else {
      $Coordinate = "";
    }
    $auth2 = $this->HttpClient->get("https://radiko.jp/v2/api/auth2", [
      "headers" => [
        "X-Radiko-App" => $this->RadikoAppName,
        "X-Radiko-AuthToken" => $AuthToken,
        "X-Radiko-App-Version" => "0.0.1",
        "X-Radiko-Connection" => "wifi",
        "X-Radiko-Device" => "PHP.Radiko",
        "X-Radiko-Location" => $Coordinate,
        "X-Radiko-PartialKey" => $PartialKey,
        "X-Radiko-User" => "dummy_user",
      ]
    ]);
    return $AuthToken;
  }
  
  /**
   * @param string $StationID
   * @return string
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function GetAuthToken4StationID(string $StationID): string{
    if(!$this->IsAvailableStationID($StationID)){
      return "";
    }
    $AreaID = $this->GetAreaID4StationID($StationID);
    return $this->GetAuthToken($AreaID);
  }
  
  /**
   * @param string $StationID
   * @return array
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function GetLivePrograms(string $StationID): array{
    $MatchedPrograms = [];
    if(!$this->IsAvailableStationID($StationID)){
      return $MatchedPrograms;
    }
    $AreaID = $this->GetAreaID4StationID($StationID);
    $Response = $this->HttpClient->get("https://radiko.jp/v3/program/now/{$AreaID}.xml");
    $ResponseObject = simplexml_load_string($Response->getBody()->getContents());
    $i = 0;
    foreach($ResponseObject->stations->station as $Station){
      if((string)$Station->attributes()["id"] === $StationID){
        foreach($Station->progs->prog as $Program){
          $MatchedPrograms[$i] = [];
          $MatchedPrograms[$i]["id"] = (string)$Program->attributes()["id"];
          $MatchedPrograms[$i]["ft"] = (string)$Program->attributes()["ft"];
          $MatchedPrograms[$i]["to"] = (string)$Program->attributes()["to"];
          $MatchedPrograms[$i]["ftl"] = (string)$Program->attributes()["ftl"];
          $MatchedPrograms[$i]["tol"] = (string)$Program->attributes()["tol"];
          $MatchedPrograms[$i]["dur"] = (string)$Program->attributes()["dur"];
          $MatchedPrograms[$i]["title"] = (string)$Program->title;
          $MatchedPrograms[$i]["url"] = (string)$Program->url;
          $MatchedPrograms[$i]["failed_record"] = (bool)((string)$Program->failed_record);
          $MatchedPrograms[$i]["ts_in_ng"] = (bool)((string)$Program->ts_in_ng);
          $MatchedPrograms[$i]["ts_out_ng"] = (bool)((string)$Program->ts_out_ng);
          $MatchedPrograms[$i]["desc"] = (string)$Program->desc;
          $MatchedPrograms[$i]["info"] = (string)$Program->info;
          $MatchedPrograms[$i]["pfm"] = (string)$Program->pfm;
          $MatchedPrograms[$i]["img"] = (string)$Program->img;
          $MatchedPrograms[$i]["tag"] = (string)$Program->tag;
          $MatchedPrograms[$i]["genre"] = (string)$Program->genre;
          $MatchedPrograms[$i]["meta"] = [];
          $j = 0;
          foreach($Program->metas->meta as $Meta){
            $MatchedPrograms[$i]["meta"][$j]["name"] = (string)$Meta->name;
            $MatchedPrograms[$i]["meta"][$j]["value"] = (string)$Meta->value;
            $j++;
          }
          $i++;
        }
      }
    }
    return $MatchedPrograms;
  }
  
  /**
   * @param string $StationID
   * @return string
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function GetLiveStream4Hls(string $StationID): string{
    if(!$this->IsAvailableStationID($StationID)){
      return "";
    }
    $StreamInfo = $this->GetLiveStreamInfo($StationID);
    $Chunk = $this->HttpClient->get($StreamInfo["url"], [
      "headers" => [
        "X-Radiko-AuthToken" => $StreamInfo["token"],
      ]
    ]);
    $PlaylistUrls = [];
    foreach(explode("\n", $Chunk->getBody()->getContents()) as $ChunkLine){
      if($this->StartsWith("http://", $ChunkLine) || $this->StartsWith("https://", $ChunkLine)){
        $PlaylistUrls[] = $ChunkLine;
      }
    }
    if(empty($PlaylistUrls)){
      return "";
    }
    $Playlist = $this->HttpClient->get($PlaylistUrls[0]);
    return $Playlist->getBody()->getContents();
  }
  
  /**
   * @param string $StationID
   * @return array
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function GetLiveStreamInfo(string $StationID): array{
    $LiveStreamInfo = [];
    if(!$this->IsAvailableStationID($StationID)){
      return $LiveStreamInfo;
    }
    $AuthToken = $this->GetAuthToken4StationID($StationID);
    $BaseUrl = $this->GetStreamBaseUrls($StationID)[0];
    $UrlParam = http_build_query([
      "station_id" => $StationID,
      "l" => 15,
      "lsid" => "",
      "type" => "b",
    ]);
    $StreamUrl = $BaseUrl."?".$UrlParam;
    return [
      "url" => $StreamUrl,
      "token" => $AuthToken,
    ];
  }
  
  /**
   * @return array
   */
  public function GetStationIDs(): array{
    $AvailableStations = $this->GetStations();
    $AvailableStationIDs = [];
    foreach($AvailableStations as $Station){
      $AvailableStationIDs[] = $Station["id"];
    }
    return $AvailableStationIDs;
  }
  
  /**
   * @return array
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function GetStations(): array{
    $Response = $this->HttpClient->get("https://radiko.jp/v3/station/region/full.xml");
    $ResponseObject = simplexml_load_string($Response->getBody()->getContents());
    $AvailableStationIDs = [];
    $i = 0;
    foreach($ResponseObject as $Stations){
      foreach($Stations->station as $Station){
        $AvailableStationIDs[$i] = [];
        $AvailableStationIDs[$i]["id"] = (string)$Station->id;
        $AvailableStationIDs[$i]["name"] = (string)$Station->name;
        $AvailableStationIDs[$i]["ascii_name"] = (string)$Station->ascii_name;
        $AvailableStationIDs[$i]["ruby"] = (string)$Station->ruby;
        $AvailableStationIDs[$i]["areafree"] = (bool)((int)$Station->areafree);
        $AvailableStationIDs[$i]["timefree"] = (bool)((int)$Station->timefree);
        $AvailableStationIDs[$i]["logo"] = [];
        foreach($Station->logo as $Logo){
          $AvailableStationIDs[$i]["logo"][] = (string)$Logo;
        }
        $AvailableStationIDs[$i]["tf_max_delay"] = (string)$Station->tf_max_delay;
        $AvailableStationIDs[$i]["banner"] = (string)$Station->banner;
        $AvailableStationIDs[$i]["area_id"] = (string)$Station->area_id;
        $AvailableStationIDs[$i]["url"] = (string)$Station->href;
        $i++;
      }
    }
    return $AvailableStationIDs;
  }
  
  /**
   * @param string $StationID
   * @param bool $AreaFree
   * @param bool $TimeFree
   * @param bool $UseCache
   * @return array
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function GetStreamBaseUrls(string $StationID, bool $AreaFree = false, bool $TimeFree = false, bool $UseCache = true): array{
    $MatchedBaseUrls = [];
    if(!$this->IsAvailableStationID($StationID)){
      return $MatchedBaseUrls;
    }
    $Response = $this->HttpClient->get("https://radiko.jp/v3/station/stream/{$this->RadikoAppName}/{$StationID}.xml");
    $ResponseObject = simplexml_load_string($Response->getBody()->getContents());
    foreach($ResponseObject->url as $EntriedUrlElement){
      if((bool)((integer)$EntriedUrlElement->attributes()["areafree"]) === $AreaFree && (bool)((integer)$EntriedUrlElement->attributes()["timefree"]) === $TimeFree){
        $MatchedBaseUrls[] = (string)$EntriedUrlElement->playlist_create_url;
      }
    }
    return $MatchedBaseUrls;
  }
  
  /**
   * @param string $AreaID
   * @return string
   */
  public function IsAvailableAreaID(string $AreaID): string{
    return (bool)preg_match("/JP[1-47]/", $AreaID);
  }
  
  /**
   * @param string $StationID
   * @return string
   */
  public function IsAvailableStationID(string $StationID): string{
    $AvailableStationIDs = $this->GetStationIDs();
    return in_array($StationID, $AvailableStationIDs);
  }
  private function StartsWith($Target, $Source){
    return strpos($Source, $Target) === 0;
  }
}