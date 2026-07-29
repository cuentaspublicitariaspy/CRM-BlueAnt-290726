<?php
header('Content-Type: text/html; charset=utf-8');
echo "<h1>Prueba de Codificación</h1>";
echo "<p>Texto con acento: Configuración</p>";
echo "<p>Texto con eñe: Contraseña</p>";
echo "<p>Codificación PHP default_charset: " . ini_get('default_charset') . "</p>";
?>
