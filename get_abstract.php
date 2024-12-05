<?php

require_once('db_conn.inc');
$headers = array(
    "Content-type: text/json;charset=\"utf-8\"",
    "Accept: application/json",
    "Cache-Control: no-cache",
    "Pragma: no-cache",
    "View: META",
    "X-ELS-Insttoken: " . $insttoken,
    "X-ELS-APIKey: " . $api_key
);


$reset = 2000;
$sql = 'SELECT `scopus_id` FROM `articles` WHERE `year` = 2023 AND `scopus_id` NOT IN (SELECT `scopus_id` FROM `abstracts`) ORDER BY `scopus_id` ASC';
$stmt = $mysqli->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$url = 'https://api.elsevier.com/content/abstract/scopus_id/';
while($obj = $result->fetch_object()) {
  $scopus_id = $obj->scopus_id;

  if(!isset($scopus_id)) continue;

  echo 'Looking for ' . $scopus_id . '
  ';
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url . $scopus_id);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  $body = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  switch($httpCode) {
    case 200:
      $json = json_decode($body);
      $abstract = mb_convert_encoding($json->{'abstracts-retrieval-response'}->item->bibrecord->head->abstracts,'UTF-8');
      echo $scopus_id . '
      ';
      echo $abstract . '
      ';
      curl_close($ch);
      $sql = 'REPLACE INTO `abstracts` VALUES(?,?)';
      $stmt = $mysqli->prepare($sql);
      $stmt->bind_param('is',$scopus_id,$abstract);
      $stmt->execute();
      //echo '<p>Refreshing in ' . $reset . '</p>';
      //echo '<script>setTimeout(function() {location.reload();},' . $reset . ');</script>';
    break;
    case 404:
      $sql = 'REPLACE INTO `abstracts` VALUES(?,null)';
      $stmt = $mysqli->prepare($sql);
      $stmt->bind_param('i',$scopus_id);
      $stmt->execute();
      //echo '<script>setTimeout(function() {location.reload();},' . $reset . ');</script>';
      echo 'Error ' . $httpCode . '
      ';
    break;
    default:
      //echo '<script>setTimeout(function() {location.reload();},' . $reset*100 . ');</script>';
      echo 'Error ' . $httpCode . '
      ';
    break;
  }
  sleep(1);
}
echo 'Done!';
      
 ?>
