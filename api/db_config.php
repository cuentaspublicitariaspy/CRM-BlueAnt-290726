<?php
if (!defined('DB_SERVER')) {
    require_once __DIR__ . '/config.php';
}

function getCrmPdo() {
    global $pdo;
    return $pdo;
}


