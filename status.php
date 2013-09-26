<?php
header("content-type:text/json");
include_once('libraries/CentoLast.php');
$server = new CentoLast;
$server->setCentova('http://78.129.181.183:2199','xxxxx','xxxxxxxxx');
echo json_encode($server->getServerStatus());
?>