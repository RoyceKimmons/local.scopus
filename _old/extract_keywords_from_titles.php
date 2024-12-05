<?php

require_once('db_conn.inc');

$sql = 'SELECT * FROM `stopwords`';
$stmt = $mysqli->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$stopwords = array();
while($obj = $result->fetch_object()) {
  $stopwords[] = $obj->stopword;
}


$sql = 'SELECT * FROM `articles`';
$stmt = $mysqli->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
while($obj = $result->fetch_object()) {
  $obj->title = strtolower($obj->title);
  $obj->title = str_replace('-','',$obj->title);
  $obj->title = str_replace('—','',$obj->title);
  $obj->title = preg_replace('/[^a-z0-9]/',' ', $obj->title);
  $words = explode(' ',$obj->title);
  $new_words[$obj->scopus_id] = array();
  foreach($words as $key=>$word) {
    $word = substr(trim($word),0,255);
    if(strlen($word)>0 && !in_array($word, $stopwords)) {
      $new_words[$obj->scopus_id][] = $word;
    }
  }
}
$stmt->close();

$mysqli->query('TRUNCATE TABLE `title_keywords`');
foreach($new_words as $scopus_id=>$words) {
  $prev_word = '';
  $prev_stem = '';
  $prev_truncated = '';
  foreach($words as $position=>$word) {
    $stem = $word;
    if (substr($stem,-3,3)=='ies') $stem = substr($stem,0,-3);
    if (substr($stem,-1,1)=='s') $stem = substr($stem,0,-1);
    if (substr($stem,-1,1)=='y') $stem = substr($stem,0,-1);
    if (substr($stem,-3,3)=='ing') $stem = substr($stem,0,-3);
    $truncated = substr($stem,0,5);
    $sql = 'REPLACE INTO `title_keywords` VALUES(?,?,?,?,?,0)';
    $stmt = $mysqli->prepare($sql) or die($mysqli->error);
    $stmt->bind_param('isssi',$scopus_id,$word,$stem,$truncated,$position) or die($mysqli->error);
    $stmt->execute() or die($mysqli->error);
    if(strlen($prev_word)>0 && strlen($prev_stem)>0 && strlen($prev_truncated)>0) {
      $group_word = $prev_word . ' ' . $word;
      $group_stem = $prev_stem . ' ' . $stem;
      $prev_position = $position-1;
      $group_truncated = $prev_truncated . ' ' . $truncated;
      $sql = 'REPLACE INTO `title_keywords` VALUES(?,?,?,?,?,1)';
      $stmt = $mysqli->prepare($sql) or die($mysqli->error);
      $stmt->bind_param('isssi',$scopus_id,$group_word,$group_stem,$group_truncated,$prev_position) or die($mysqli->error);
      $stmt->execute() or die($mysqli->error);
    }
    $prev_word = $word;
    $prev_stem = $stem;
    $prev_truncated = $truncated;
  }
}
$stmt->close();
