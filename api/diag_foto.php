<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
require_once 'config.php';

$uploadDir = dirname(__DIR__) . '/uploads/';
$result = null;

// Si viene un POST con archivo, procesarlo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $f = $_FILES['avatar'];
    if ($f['error'] === 0) {
        $fn   = 'test_' . time() . '_' . basename($f['name']);
        $dest = $uploadDir . $fn;
        $moved = move_uploaded_file($f['tmp_name'], $dest);
        $result = $moved 
            ? "<div style='color:green;font-weight:bold'>✅ ARCHIVO GUARDADO: $fn</div>"
            : "<div style='color:red;font-weight:bold'>❌ FALLÓ move_uploaded_file</div>";
    } else {
        $result = "<div style='color:red'>❌ Error en archivo: código {$f['error']}</div>";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = "<div style='color:red'>❌ No se recibió ningún archivo en el POST</div>";
}
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Test Subida Foto</title></head>
<body style="font-family:sans-serif;padding:2rem;max-width:600px">
<h2>🔬 Test de Subida de Foto</h2>
<?php if ($result) echo "<div style='padding:1rem;background:#f5f5f5;margin-bottom:1rem'>$result</div>"; ?>

<h3>Test 1: Formulario HTML Nativo</h3>
<form action="" method="post" enctype="multipart/form-data">
    <input type="file" name="avatar" accept="image/*">
    <button type="submit" style="padding:.5rem 1rem;background:#6366f1;color:white;border:none;border-radius:.5rem;cursor:pointer;margin-left:.5rem">Subir con form nativo</button>
</form>

<h3 style="margin-top:2rem">Test 2: Fetch con FormData (como hace perfil.php)</h3>
<input type="file" id="testFile" accept="image/*">
<button onclick="testFetch()" style="padding:.5rem 1rem;background:#10b981;color:white;border:none;border-radius:.5rem;cursor:pointer;margin-left:.5rem">Subir con Fetch</button>
<div id="fetchResult" style="margin-top:1rem;padding:1rem;background:#f5f5f5;display:none"></div>

<script>
async function testFetch() {
    const file = document.getElementById('testFile').files[0];
    if (!file) { alert('Selecciona un archivo primero'); return; }
    
    const fd = new FormData();
    fd.append('avatar', file);
    
    const res  = await fetch('diag_foto.php', { method: 'POST', body: fd });
    const text = await res.text();
    
    const div = document.getElementById('fetchResult');
    div.style.display = 'block';
    div.innerHTML = '<strong>Respuesta del servidor:</strong><br>' + text;
}
</script>
</body>
</html>
