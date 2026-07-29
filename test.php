<?php
echo "PHP Version: " . phpversion() . "\n\n";
echo "PDO: " . (class_exists('PDO') ? 'OK' : 'NO') . "\n";
echo "PDO_MySQL: " . (in_array('mysql', PDO::getAvailableDrivers()) ? 'OK' : 'NO') . "\n";
echo "mbstring: " . (function_exists('mb_strlen') ? 'OK' : 'NO') . "\n";
echo "curl: " . (function_exists('curl_version') ? 'OK' : 'NO') . "\n";
echo "json: " . (function_exists('json_encode') ? 'OK' : 'NO') . "\n\n";

// Test DB connection
require_once __DIR__ . '/api/config.php';
echo "DB: CONECTADO\n";
echo "CRM_URL: " . CRM_URL . "\n";
