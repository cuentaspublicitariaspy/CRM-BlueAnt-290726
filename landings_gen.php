<?php
/**
 * Landing pública por slug de usuario
 * URL: /crm/landings_gen/{slug}?lp={landing_id}
 */
require_once __DIR__ . '/api/config.php';

$slug = trim($_GET['slug'] ?? '');
$lp   = (int)($_GET['lp'] ?? 0);

if (!$slug || !$lp) { http_response_code(404); echo "Parámetros inválidos."; exit; }

try {
    // 1. Buscar usuario por slug
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE slug = ? AND active = 1");
    $stmt->execute([$slug]);
    $user = $stmt->fetch();

    if (!$user) { http_response_code(404); echo "Usuario no encontrado."; exit; }

    // 2. Buscar suscripción del usuario a la landing
    //    Si el usuario es el dueño de la landing, acceso directo sin suscripción
    $stmt = $pdo->prepare("SELECT id, filename, title, user_id FROM landings WHERE id = ?");
    $stmt->execute([$lp]);
    $landing = $stmt->fetch();

    if (!$landing) { http_response_code(404); echo "Landing no encontrada."; exit; }

    $isOwner = ($landing['user_id'] == $user['id']);
    $sub = null;

    if ($isOwner) {
        // El dueño ve la landing sin registrar vista ni tracking
        $filename = $landing['filename'];
        $token    = 'owner-' . $landing['id'];
    } else {
        $stmtSub = $pdo->prepare("
            SELECT ls.token, ls.id AS sub_id
            FROM landing_subscriptions ls
            WHERE ls.user_id = ? AND ls.landing_id = ?
        ");
        $stmtSub->execute([$user['id'], $lp]);
        $sub = $stmtSub->fetch();

        if (!$sub) { http_response_code(404); echo "Landing no encontrada para este usuario."; exit; }

        // Incrementar vista
        $pdo->prepare("UPDATE landing_subscriptions SET views = views + 1 WHERE id = ?")->execute([$sub['sub_id']]);

        $filename = $landing['filename'];
        $token    = $sub['token'];
    }

    // 3. Leer HTML de la landing
    $htmlPath = __DIR__ . '/landings_gen/' . $filename;
    if (!file_exists($htmlPath)) { http_response_code(404); echo "Archivo no encontrado."; exit; }

    $html = file_get_contents($htmlPath);

    // 4. Inyectar script de tracking (solo si no es el dueño)
    $crmUrl = CRM_URL;
    if ($isOwner) {
        $trackScript = '<!-- Vista previa del propietario, sin tracking -->';
    } else {
    $trackScript = <<<JS
<style>
  #crm-fab { display: none !important; }
  .crm-f, #crm-num-inp { color: #0f172a !important; }
  .crm-f::placeholder, #crm-num-inp::placeholder { color: #94a3b8 !important; opacity: 1 !important; }
</style>
<script>
(function(){
  var TOKEN = '{$token}';
  var API   = '{$crmUrl}/api/landing_track.php';

  window.CRM_TOKEN = TOKEN;
  window.CRM_TRACK_API = API;

  window.closeCRMModal = function(e) {
    var ev = e || window.event;
    if(ev) { if(ev.stopPropagation) ev.stopPropagation(); ev.cancelBubble = true; }
    var ov = document.getElementById('crm-ov');
    if(ov) { ov.classList.remove('crm-open'); ov.style.setProperty('display', 'none', 'important'); }
  };

  var k = 'crm_sv_' + TOKEN;
  if (!sessionStorage.getItem(k)) {
    fetch(API, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'view', token:TOKEN}) });
    sessionStorage.setItem(k, '1');
  }

  document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('crm-send');
    if (!btn) return;
    var newBtn = btn.cloneNode(true);
    btn.parentNode.replaceChild(newBtn, btn);
    newBtn.addEventListener('click', async function() {
      var n  = document.getElementById('crm-n').value.trim();
      var em = document.getElementById('crm-e').value.trim();
      var w  = document.getElementById('crm-w').value.trim();
      if (!n || !em) { alert('Por favor completá nombre y email.'); return; }
      if (!w) { alert('Por favor ingresá un número de WhatsApp válido.'); return; }
      this.textContent = 'Enviando...'; this.disabled = true;
      try {
        var r = await fetch(API, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'lead', token:TOKEN, name:n, email:em, whatsapp:w}) });
        var d = await r.json();
        if (d.success) {
          if (d.redirect_url) { window.location.href = d.redirect_url; }
          else {
            document.getElementById('crm-form').style.display = 'none';
            var okDiv = document.getElementById('crm-ok');
            okDiv.style.display = 'block';
            var closeBtn = okDiv.querySelector('button');
            if (!closeBtn) {
              closeBtn = document.createElement('button');
              closeBtn.textContent = 'Cerrar';
              closeBtn.style.cssText = 'width:100%;background:#f1f5f9;color:#475569;border:none;border-radius:.875rem;padding:.7rem;font-weight:700;cursor:pointer;margin-top:1rem;font-family:inherit';
              closeBtn.onclick = window.closeCRMModal;
              okDiv.appendChild(closeBtn);
            } else { closeBtn.onclick = window.closeCRMModal; }
            var fab = document.getElementById('crm-fab');
            if (fab) fab.style.display = 'none';
          }
        } else { alert('Error: ' + (d.error || 'Intenta de nuevo')); this.textContent = 'Enviar mis datos \u2192'; this.disabled = false; }
      } catch(e) { alert('Error de red. Intenta de nuevo.'); this.textContent = 'Enviar mis datos \u2192'; this.disabled = false; }
    });
  });
})();
</script>
JS;
    }

    $html = (stripos($html, '</body>') !== false)
        ? str_ireplace('</body>', $trackScript . "\n</body>", $html)
        : $html . $trackScript;

    header('Content-Type: text/html; charset=utf-8');
    echo $html;

} catch (Exception $e) {
    error_log("Error en landings_gen.php: " . $e->getMessage());
    http_response_code(500);
    echo "Error del servidor.";
}
