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
        $filename = $landing['filename'];
        // El dueño no tiene fila propia en landing_subscriptions (esa tabla
        // es para asignaciones a OTROS usuarios) — se la creamos on demand
        // para que la acción post-registro que configuró (ver
        // api/landing_config.php) funcione también en su propio enlace de
        // vista previa/uso directo. Las vistas del dueño siguen sin sumar
        // al contador (ver más abajo), solo cambia que ahora el token es
        // real en vez de sintético, así landing_track.php puede resolver
        // la suscripción al recibir un lead.
        $ownStmt = $pdo->prepare("SELECT token FROM landing_subscriptions WHERE landing_id = ? AND user_id = ?");
        $ownStmt->execute([$landing['id'], $user['id']]);
        $token = $ownStmt->fetchColumn();
        if (!$token) {
            $token = bin2hex(random_bytes(12));
            try {
                $pdo->prepare("INSERT INTO landing_subscriptions (landing_id, user_id, token) VALUES (?, ?, ?)")
                    ->execute([$landing['id'], $user['id'], $token]);
            } catch (Exception $e) {
                // Carrera con otra request creando la misma fila a la vez: releer.
                $ownStmt->execute([$landing['id'], $user['id']]);
                $token = $ownStmt->fetchColumn() ?: $token;
            }
        }
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

    // 4. Inyectar script de tracking. Antes esto se saltaba entero para el
    // dueño ("vista previa, sin tracking"), lo que también dejaba sin
    // efecto la acción post-registro que hubiera configurado — ahora la
    // única diferencia real es que sus propias visitas no suman al
    // contador de vistas; el token (real, ver arriba) y el manejo de la
    // reserva/lead son los mismos para dueño y suscriptor.
    $crmUrl = CRM_URL;
    $viewTrackingJs = $isOwner ? '' : <<<'JS'
  var k = 'crm_sv_' + TOKEN;
  if (!sessionStorage.getItem(k)) {
    fetch(API, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'view', token:TOKEN}) });
    sessionStorage.setItem(k, '1');
  }
JS;

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

{$viewTrackingJs}

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
