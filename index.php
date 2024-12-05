<?php

$year = 2023;

require_once('db_conn.inc');
function td($t) {
    return '<td>' . $t . '</td>';
}
$sql = 'SELECT a.*, ab.*, j.`title` AS `journal_title` FROM `articles` a INNER JOIN `abstracts` ab ON a.`scopus_id` = ab.`scopus_id` INNER JOIN `journals` j ON j.`journal_id` = a.`journal_id` WHERE `year` = ? ORDER BY ab.`abstract` ASC';
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $year);
$stmt->execute();
$result = $stmt->get_result();
echo '<table>';
while($row = $result->fetch_object()) {
    echo '<tr>';
    echo td($row->title);
    echo td($row->abstract);
    echo td($row->journal_title);
    echo '</tr>';
}
echo '</table>';