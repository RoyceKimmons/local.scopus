<?php
require_once('db_conn.inc');

$reset = 4000;

$api_key = '7f59af901d2d86f78a1fd60c1bf9426a';
$issn = '1042-1629';
$url = 'https://api.elsevier.com/content/search/scopus';
$url .= '?query=issn(' . $issn . ')';
$url .= '&apiKey=' . $api_key;
$url .= '&count=25';

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
$body = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$json = json_decode($body);
//echo print_r($json);
curl_close($ch);
$results = $json->{'search-results'};
$articles = $results->entry;
foreach($articles as $article) {
  $scopus_id = explode(':',$article->{'dc:identifier'})[1];
  $title = substr(mb_convert_encoding($article->{'dc:title'},'UTF-8'),0,255);
  echo '<p>Saving ' . $title . '</p>';
  $author = isset($article->{'dc:creator'}) ? mb_convert_encoding($article->{'dc:creator'},'UTF-8') : null;
  $volume = (isset($article->{'prism:volume'})) ? $article->{'prism:volume'} : null;
  $issue = (isset($article->{'prism:issueIdentifier'})) ? $article->{'prism:issueIdentifier'} : null;
  $pages = $article->{'prism:pageRange'};
  $date = $article->{'prism:coverDate'};
  $cited_by_count = $article->{'citedby-count'};
  $subtype = $article->subtype;
  $open = $article->openaccess;
  $doi = $article->{'prism:doi'};
  $sql = 'REPLACE INTO `articles` VALUES(?,?,?,?,?,?,?,?,?,?,?)';
  $stmt = $mysqli->prepare($sql) or die($mysqli->error);
  $stmt->bind_param('issssssisis',$scopus_id,$title,$author,$volume,$issue,$pages,$date,$cited_by_count,$subtype,$open,$doi);
  $stmt->execute() or die($mysqli->error);
}
$new_start = $start+count($articles);
save_variable($mysqli, 'scopus_start',$new_start);
echo 'done';
echo '<script>setTimeout(function() {location.reload();},' . $reset . ');</script>';
 ?>
