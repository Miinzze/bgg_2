<?php
session_start();

// Bug-Admin Session beenden
unset($_SESSION['bug_admin_id']);
unset($_SESSION['bug_admin_username']);
unset($_SESSION['bug_admin_email']);

session_destroy();

header('Location: login.php');
exit;