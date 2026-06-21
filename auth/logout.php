<?php
require_once '../includes/db_config.php';

// Destroy the session
session_destroy();

// Redirect to login page
redirect('../auth/manager_login.php');
?>