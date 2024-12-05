<?php

require_once('db_conn.inc');
$sql = 'SELECT * FROM `abstracts` ab WHERE `scopus_id` NOT IN (SELECT `scopus_id` FROM `abstracts_clean`)';
$stmt = $mysqli->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$ai_url = 'http://localhost:11434/api/generate';
$model = 'data-cleaner';
while($obj = $result->fetch_object()) {
    if($obj->abstract==null) continue;
    $curl = curl_init($ai_url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $data = json_encode(array(
        'prompt' => str_replace('.','. ',$obj->abstract),
        'model' => $model,
        'stream' => false
    ));
    echo print_r($data);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    $response = curl_exec($curl);

    if (curl_errno($curl)) {
    curl_close($curl);
        die('Curl error: ' . curl_error($curl));
    } else {
    curl_close($curl);
        $decodedResponse = json_decode($response, true);
        //echo print_r($decodedResponse);
        $startPos = strpos($decodedResponse['response'], '<file>');
        $endPos = strrpos($decodedResponse['response'], '</file>');
        $result2 = substr($decodedResponse['response'], $startPos + 6, $endPos - $startPos - 6);
        echo $result2;
        if ($decodedResponse === null) {
            echo 'Error decoding JSON';
        } else if($result2==null) {
            echo 'No result';
        } else {
            $text = trim($result2);
            echo $text;
            $sql2 = 'REPLACE INTO `abstracts_clean` VALUES(?,?)';
            $stmt2 = $mysqli->prepare($sql2);
            $stmt2->bind_param('is',$obj->scopus_id,$text);
            $stmt2->execute();
        }
    }
}