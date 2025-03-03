<?php
// This is a very handy script for troubleshooting.
// Replace whdc.php with this script, and every request getting in will dump
// full logs to the defined log file.

$headers = getallheaders();
$resp = print_r($headers, true);
$server = print_r($_SERVER, true);
$post = print_r($_POST, true);
$get = print_r($_GET, true);

// Create handle for log file.
$handle = fopen("/var/www/logs/whdc.log", "a");

$date =  date("Y-m-d H:i:s");


fwrite($handle, "\n*** $date ======================================================\n");
fwrite($handle, "==> Headers ===================================================\n{$resp} \n");
fwrite($handle, "==> GET =======================================================\n{$get} \n");
fwrite($handle, "==> POST ======================================================\n{$post} \n");
$json = file_get_contents('php://input');
fwrite($handle, "==> JSON ======================================================\n{$json} \n");
fwrite($handle, "==> SERVER ====================================================\n {$server} \n");
// Close log-file.
fclose($handle);


?>
