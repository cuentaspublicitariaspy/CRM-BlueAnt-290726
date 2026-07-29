<?php
require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/lib/ProspeccionEngine.php';

use CRM\Lib\ProspeccionEngine;

// En un entorno real, aquí se verificaría la sesión del distribuidor
// session_start();
// if (!isset($_SESSION['user_id'])) { die("Acceso denegado"); }

$distribuidorId = $_GET['d_id'] ?? null;
$landingId = $_GET['l_id'] ?? null;
$templateId = $_GET['t_id'] ?? null;

if (!$distribuidorId || !$landingId || !$templateId) {
    http_response_code(400);
    die("Parámetros faltantes: d_id, l_id, t_id requeridos.");
}

try {
    $engine = new ProspeccionEngine($pdo);
    $engine->generarAsset((int)$distribuidorId, (int)$landingId, (int)$templateId);
} catch (Exception $e) {
    http_response_code(500);
    echo "Error al generar el asset: " . htmlspecialchars($e->getMessage());
}
