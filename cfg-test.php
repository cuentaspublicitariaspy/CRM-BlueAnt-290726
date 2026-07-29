<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/api/config.php';
    echo "CONFIG OK - Migraciones ejecutadas";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "LINE: " . $e->getLine() . "\n";
    echo "FILE: " . $e->getFile();
}
