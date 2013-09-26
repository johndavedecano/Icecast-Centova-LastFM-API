<?php 
header("content-type:text/json");
require_once('libraries/Icecast.php');
$icecast = new Icecast;
$response = $icecast->getInfo();
echo json_encode($response);
?>