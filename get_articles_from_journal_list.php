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
$year_min = 2023;
$year_max = 2023;
$category_id = 2;
$limit = 25;



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

$sql = 'SELECT * FROM `journals` j INNER JOIN `journal_categories` jc ON jc.`journal_id` = j.`journal_id` WHERE `issn` IS NOT NULL AND `category_id` = ?';
$stmt = $mysqli->prepare($sql) or die($mysqli->error);
$stmt->bind_param('i',$category_id);
$stmt->execute();
$result = $stmt->get_result();
while($obj = $result->fetch_object()) {
  $start = 0;
  $proceed = true;
  echo 'Beginning ' . $obj->title . ' ...
  ';
  $issn = $obj->issn;
  $journal_id = $obj->journal_id;

  $url = 'https://api.elsevier.com/content/search/scopus';
  $url .= '?query=issn(' . $issn . ')';
  $url .= '&apiKey=' . $api_key;
  $url .= '&count=' . $limit;
  $url .= '&date=' . $year_min;
  if($year_max!=$year_min) $url .= '-' . $year_max;
  while($proceed) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '&start=' . $start);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if($httpCode != 200) die('There was an error.');
    $json = json_decode($body);
    //echo print_r($json);
    curl_close($ch);
    $results = $json->{'search-results'};
    if(!isset($results->entry)) {
      $start = 0;
      $proceed = false;
      break;
    }
    $articles = $results->entry;
    if(isset($articles[0]) && isset($articles[0]->error)) $articles = array();
    foreach($articles as $article) {
        $scopus_id = explode(':',$article->{'dc:identifier'})[1];
        $title = substr(mb_convert_encoding($article->{'dc:title'},'UTF-8'),0,255);
        echo '' . $title . '
        ';
        $author = isset($article->{'dc:creator'}) ? mb_convert_encoding($article->{'dc:creator'},'UTF-8') : null;
        $volume = (isset($article->{'prism:volume'})) ? substr($article->{'prism:volume'},0,255) : null;
        $issue = (isset($article->{'prism:issueIdentifier'})) ? substr($article->{'prism:issueIdentifier'},0,255) : null;
        $pages = (isset($article->{'prism:pageRange'})) ? $article->{'prism:pageRange'} : null;
        $date = (isset($article->{'prism:coverDate'})) ? $article->{'prism:coverDate'} : null;
        $cited_by_count = (isset($article->{'citedby-count'})) ? $article->{'citedby-count'} : null;
        $subtype = (isset($article->subtype)) ? $article->subtype : null;
        $open = (isset($article->openaccess)) ? $article->openaccess : null;
        $year = intval(substr($date,0,4));
        $datediff = null;
        $doi = isset($article->{'prism:doi'}) ? $article->{'prism:doi'} : '';
        $sql = 'REPLACE INTO `articles` VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        $stmt2 = $mysqli->prepare($sql) or die($mysqli->error);
        $stmt2->bind_param('iissssssiisisi',$journal_id,$scopus_id,$title,$author,$volume,$issue,$pages,$date,$cited_by_count,$datediff,$subtype,$open,$doi,$year);
        $stmt2->execute() or die($mysqli->error);
    }
    $start = $start+count($articles);
    if(count($articles)==0) $proceed = false;
    echo 'Sleeping...
    ';
    sleep(1);
  }
  echo 'Sleeping...
  ';
  sleep(1);
}
$sql = 'UPDATE `articles` SET `date_difference_in_days` = DATEDIFF(now(),cover_date) WHERE `date_difference_in_days` is null';
$mysqli->query($sql);

echo 'Done!';
 ?>
