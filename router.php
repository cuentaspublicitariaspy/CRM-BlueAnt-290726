<?php
/**
 * Router para desarrollo local
 * Uso: php -S localhost:8000 router.php
 */

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$crmRoot = __DIR__;
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

// Archivos estáticos
$staticExts = ['css','js','html','htm','png','jpg','jpeg','gif','svg','ico','json','woff','woff2'];
if (in_array($ext, $staticExts, true)) {
    $fp = $crmRoot . $path;
    if (file_exists($fp) && is_file($fp)) {
        $mimeTypes = [
            'css'=>'text/css','js'=>'application/javascript','html'=>'text/html',
            'htm'=>'text/html','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg',
            'gif'=>'image/gif','svg'=>'image/svg+xml','ico'=>'image/x-icon',
            'json'=>'application/json','woff'=>'font/woff','woff2'=>'font/woff2',
        ];
        if (isset($mimeTypes[$ext])) header('Content-Type: ' . $mimeTypes[$ext]);
        if (in_array($ext, ['css','js','html'], true)) header('Cache-Control: no-cache');
        readfile($fp);
        return true;
    }
}

// PHP files en la raíz del CRM
$crmFile = $crmRoot . $path;
if ($ext === 'php' && file_exists($crmFile) && is_file($crmFile)) {
    $dir = dirname($crmFile);
    $oldInc = set_include_path($dir . PATH_SEPARATOR . get_include_path());
    try { require $crmFile; }
    finally { set_include_path($oldInc); }
    return true;
}

// 404
http_response_code(404);
echo "404 Not Found";
return true;
