<?php
include_once('libraries/CentoLast.php');
$server = new CentoLast;
$server->setLastFM('xxxxxxxxxxxxxxxxxxxx','xxxxxxxxxxxxxx');
$server->setCentova('http://78.129.181.183:2199','xxxxx','xxxxxxxxx');
$server->cache();
?>