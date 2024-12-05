<?php
require_once('settings.inc');
$mysqli = mysqli_connect("localhost", "scopus", $db_pw, "scopus");
if ($mysqli->connect_error) {
    die('Connect Error: ' . $mysqli->connect_error);
}

