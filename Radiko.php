<?php
class Radiko{
  private $RadikoAppName = "aSmartPhone7o";
  private $Cache = [];
  public function GetAreaID4StationID(string $StationID): string{
    $Stations = $this->GetStations();
    foreach($Stations as $Station){
      if($Station["id"] === $StationID){
        return $Station["area_id"];
      }
    }
    return "";
  }
  public function GetAuthToken(string $AreaID = ""): string{
    $Auth1Curl = curl_init();
    curl_setopt_array($Auth1Curl, [
      CURLOPT_HEADER => true,
      CURLOPT_HTTPHEADER => [
        "X-Radiko-App: {$this->RadikoAppName}",
        "X-Radiko-App-Version: 0.0.1",
        "X-Radiko-Device: PHP.Radiko",
        "X-Radiko-User: dummy_user",
      ],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_URL => "https://radiko.jp/v2/api/auth1",
    ]);
    $Auth1Response = curl_exec($Auth1Curl);
    $Auth1ResponseInfo = curl_getinfo($Auth1Curl);
    curl_close($Auth1Curl);
    $Auth1ResponseHeader = substr($Auth1Response, 0, (int)$Auth1ResponseInfo["header_size"]);
    $Auth1ResponseHeaderArray = [];
    if($Auth1ResponseInfo["http_code"] !== 200){
      return "";
    }
    foreach(explode("\r\n", $Auth1ResponseHeader) as $Auth1ResponseHeaderLine){
      $Header = explode(": ", $Auth1ResponseHeaderLine);
      if(count($Header) !== 2){
        continue;
      }
      if($Header[0] === "X-RADIKO-AUTHTOKEN"){
        $Header[0] = "X-Radiko-AuthToken";
      }
      $Auth1ResponseHeaderArray[$Header[0]] = $Header[1];
    }
    $AuthToken = $Auth1ResponseHeaderArray["X-Radiko-AuthToken"];
    $PartialKeyOffset = $Auth1ResponseHeaderArray["X-Radiko-KeyOffset"];
    $PartialKeyLength = $Auth1ResponseHeaderArray["X-Radiko-KeyLength"];
    $PartialKeyRaw = file_get_contents(dirname(__FILE__)."/{$this->RadikoAppName}.bin", false, null, $PartialKeyOffset, $PartialKeyLength);
    $PartialKey = base64_encode($PartialKeyRaw);
    $Auth2Curl = curl_init();
    if($this->IsAvailableAreaID($AreaID)){
      $AreaCoordinates = json_decode(file_get_contents(dirname(__FILE__)."/coordinates.json"));
      curl_setopt_array($Auth2Curl, [
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => [
          "X-Radiko-AuthToken: {$AuthToken}",
          "X-Radiko-App: {$this->RadikoAppName}",
          "X-Radiko-App-Version: 0.0.1",
          "X-Radiko-Connection: wifi",
          "X-Radiko-Device: PHP.Radiko",
          "X-Radiko-Location: {$AreaCoordinates->$AreaID[0]},{$AreaCoordinates->$AreaID[1]},gps",
          "X-Radiko-PartialKey: {$PartialKey}",
          "X-Radiko-User: dummy_user",
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_URL => "https://radiko.jp/v2/api/auth2",
      ]);
    } else {
      curl_setopt_array($Auth2Curl, [
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => [
          "X-Radiko-AuthToken: {$AuthToken}",
          "X-Radiko-App: {$this->RadikoAppName}",
          "X-Radiko-App-Version: 0.0.1",
          "X-Radiko-Device: PHP.Radiko",
          "X-Radiko-PartialKey: {$PartialKey}",
          "X-Radiko-User: dummy_user",
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_URL => "https://radiko.jp/v2/api/auth2",
      ]);
    }
    curl_exec($Auth2Curl);
    $Auth2ResponseInfo = curl_getinfo($Auth2Curl);
    curl_close($Auth2Curl);
    if($Auth2ResponseInfo["http_code"] === 200){
      return $AuthToken;
    } else {
      return "";
    }
  }
  public function GetAuthToken4StationID(string $StationID): string{
    if(!$this->IsAvailableStationID($StationID)){
      return "";
    }
    $AreaID = $this->GetAreaID4StationID($StationID);
    return $this->GetAuthToken($AreaID);
  }
  public function GetLivePrograms(string $StationID): array{
    $MatchedPrograms = [];
    if(!$this->IsAvailableStationID($StationID)){
      return $MatchedPrograms;
    }
    $AreaID = $this->GetAreaID4StationID($StationID);
    $Curl = curl_init();
    curl_setopt_array($Curl, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_URL => "https://radiko.jp/v3/program/now/{$AreaID}.xml",
    ]);
    $Response = curl_exec($Curl);
    curl_close($Curl);
    $ResponseObject = simplexml_load_string($Response);
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
  public function GetLiveStream4Hls(string $StationID): string{
    if(!$this->IsAvailableStationID($StationID)){
      return "";
    }
    $StreamInfo = $this->GetLiveStreamInfo($StationID);
    $ChunkCurl = curl_init();
    curl_setopt_array($ChunkCurl, [
      CURLOPT_HTTPHEADER => [
        "X-Radiko-AuthToken: {$StreamInfo["token"]}",
      ],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_URL => $StreamInfo["url"],
    ]);
    $ChunkResponse = curl_exec($ChunkCurl);
    curl_close($ChunkCurl);
    $PlaylistUrls = [];
    foreach(explode("\n", $ChunkResponse) as $ChunkLine){
      if($this->StartsWith("http://", $ChunkLine) || $this->StartsWith("https://", $ChunkLine)){
        $PlaylistUrls[] = $ChunkLine;
      }
    }
    if(empty($PlaylistUrls)){
      return "";
    }
    $PlaylistCurl = curl_init();
    curl_setopt_array($PlaylistCurl, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_URL => $PlaylistUrls[0],
    ]);
    $PlaylistResponse = curl_exec($PlaylistCurl);
    return $PlaylistResponse;
  }
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
  public function GetStationIDs(): array{
    $AvailableStations = $this->GetStations();
    $AvailableStationIDs = [];
    foreach($AvailableStations as $Station){
      $AvailableStationIDs[] = $Station["id"];
    }
    return $AvailableStationIDs;
  }
  public function GetStations(bool $UseCache = true): array{
    if(isset($this->Cache["v3_station_region_full_xml"]) && $UseCache){
      $Response = $this->Cache["v3_station_region_full_xml"];
    } else {
      $Curl = curl_init();
      curl_setopt_array($Curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_URL => "https://radiko.jp/v3/station/region/full.xml",
      ]);
      $Response = curl_exec($Curl);
      curl_close($Curl);
      $this->Cache["v3_station_region_full_xml"] = $Response;
    }
    $ResponseObject = simplexml_load_string($Response);
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
  public function GetStreamBaseUrls(string $StationID, bool $AreaFree = false, bool $TimeFree = false, bool $UseCache = true): array{
    $MatchedBaseUrls = [];
    if(!$this->IsAvailableStationID($StationID)){
      return $MatchedBaseUrls;
    }
    if(isset($this->Cache["v3_station_stream_{$this->RadikoAppName}_{$StationID}_xml"]) && $UseCache){
      $Response = $this->Cache["v3_station_stream_{$this->RadikoAppName}_{$StationID}_xml"];
    } else {
      $Curl = curl_init();
      curl_setopt_array($Curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_URL => "https://radiko.jp/v3/station/stream/{$this->RadikoAppName}/{$StationID}.xml",
      ]);
      $Response = curl_exec($Curl);
      curl_close($Curl);
      $this->Cache["v3_station_stream_{$this->RadikoAppName}_{$StationID}_xml"] = $Response;
    }
    $ResponseObject = simplexml_load_string($Response);
    foreach($ResponseObject->url as $EntriedUrlElement){
      if((bool)((integer)$EntriedUrlElement->attributes()["areafree"]) === $AreaFree && (bool)((integer)$EntriedUrlElement->attributes()["timefree"]) === $TimeFree){
        $MatchedBaseUrls[] = (string)$EntriedUrlElement->playlist_create_url;
      }
    }
    return $MatchedBaseUrls;
  }
  public function IsAvailableAreaID(string $AreaID): bool{
    return (bool)preg_match("/JP[1-47]/", $AreaID);
  }
  public function IsAvailableStationID(string $StationID): bool{
    $AvailableStationIDs = $this->GetStationIDs();
    return in_array($StationID, $AvailableStationIDs);
  }
  private function StartsWith($Target, $Source){
    return strpos($Source, $Target) === 0;
  }
}