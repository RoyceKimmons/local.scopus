<?php
require_once('settings.inc');
$mysqli = mysqli_connect("localhost", "scopus", "N1RQ3RFhEyrbZIsl", "scopus");
if ($mysqli->connect_error) {
    die('Connect Error: ' . $mysqli->connect_error);
}

