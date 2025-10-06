<?php
// Sicherheitstoken
$secret = 'dfgshfgj4656486513';

if (!isset($_GET['token']) || $_GET['token'] !== $secret) {
    die('Unauthorized');
}

include 'cron_maintenance.php';
?>