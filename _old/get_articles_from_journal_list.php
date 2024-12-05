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

$reset = 5000;
$year_min = 2020;
$year_max = 2020;
//$api_key = '7f59af901d2d86f78a1fd60c1bf9426a';
$sql = 'SELECT * FROM `journals` WHERE `status` = 0 AND `issn` IS NOT NULL';
$stmt = $mysqli->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
while($obj = $result->fetch_object()) {
  $issn = $obj->issn;
  $journal_id = $obj->journal_id;
}
if(!isset($issn)) die('No ISSN found for retrieval.');
$url = 'https://api.elsevier.com/content/search/scopus';
$url .= '?query=issn(' . $issn . ')';
$url .= '&apiKey=' . $api_key;
$url .= '&count=25';
$url .= '&date=' . $year_min;
if($year_max!=$year_min) $url .= '-' . $year_max;
echo $url;
function save_variable($mysqli, $name, $val) {
  $sql = 'REPLACE INTO variables VALUES(?,?)';
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('ss',$name,$val);
  $stmt->execute();
}
function get_variable($mysqli, $name) {
  $sql = 'SELECT value FROM variables WHERE name = ? LIMIT 1';
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('s',$name);
  $stmt->execute();
  $result = $stmt->get_result();
  while($obj = $result->fetch_object()) {
    return $obj->value;
  }
  return null;
}
$start = get_variable($mysqli, 'scopus_start');
if(!$start) $start = 0;
$url .= '&start=' . $start;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$body = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo $httpCode;
if($httpCode != 200) die('There was an error.');
$json = json_decode($body);
//echo print_r($json);
curl_close($ch);
$results = $json->{'search-results'};
$articles = $results->entry;
if(isset($articles[0]) && isset($articles[0]->error)) $articles = array();
foreach($articles as $article) {
  $scopus_id = explode(':',$article->{'dc:identifier'})[1];
  $title = substr(mb_convert_encoding($article->{'dc:title'},'UTF-8'),0,255);
  echo '<p>Saving ' . $title . '</p>';
  $author = isset($article->{'dc:creator'}) ? mb_convert_encoding($article->{'dc:creator'},'UTF-8') : null;
  $volume = (isset($article->{'prism:volume'})) ? substr($article->{'prism:volume'},0,255) : null;
  $issue = (isset($article->{'prism:issueIdentifier'})) ? substr($article->{'prism:issueIdentifier'},0,255) : null;
  $pages = (isset($article->{'prism:pageRange'})) ? $article->{'prism:pageRange'} : null;
  $date = (isset($article->{'prism:coverDate'})) ? $article->{'prism:coverDate'} : null;
  $cited_by_count = (isset($article->{'citedby-count'})) ? $article->{'citedby-count'} : null;
  $subtype = (isset($article->subtype)) ? $article->subtype : null;
  $open = (isset($article->openaccess)) ? $article->openaccess : null;
  $year = null;
  $datediff = null;
  $doi = isset($article->{'prism:doi'}) ? $article->{'prism:doi'} : '';
  $sql = 'REPLACE INTO `articles` VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
  $stmt = $mysqli->prepare($sql) or die($mysqli->error);
  $stmt->bind_param('iissssssiisisi',$journal_id,$scopus_id,$title,$author,$volume,$issue,$pages,$date,$cited_by_count,$datediff,$subtype,$open,$doi,$year);
  $stmt->execute() or die($mysqli->error);
}
$sql = 'UPDATE `articles` SET `year` = YEAR(cover_date) WHERE `year` is null';
$mysqli->query($sql);
$sql = 'UPDATE `articles` SET `date_difference_in_days` = DATEDIFF(now(),cover_date) WHERE `date_difference_in_days` is null';
$mysqli->query($sql);
$new_start = $start+count($articles);
if(count($articles)>0) {
  save_variable($mysqli, 'scopus_start',$new_start);
} else {
  save_variable($mysqli, 'scopus_start',0);
  $sql = 'UPDATE `journals` SET `status`=1 WHERE `journal_id` = ' . $journal_id . ' LIMIT 1';
  $mysqli->prepare($sql)->execute();
}
echo 'done';
echo '<p>Refreshing in ' . $reset . '</p>';
echo '<script>setTimeout(function() {location.reload();},' . $reset . ');</script>';
 ?>
