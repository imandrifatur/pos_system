<?php
session_start();
require_once __DIR__ . '/config/database.php';
session_destroy();
header('Location: ' . APP_URL . '/index.php');
exit;
