<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error' => 'No autorizado']); exit; }
$user_id = (int)$_SESSION['user_id'];

// === RUTAS ABSOLUTAS CONFIRMADAS ===
$UPLOAD_DIR = dirname(dirname(__FILE__)) . '/landings_gen/';

if (!is_dir($UPLOAD_DIR)) @mkdir($UPLOAD_DIR, 0755, true);

// Asegurar tabla landings
// Asegurar tabla y columnas
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS landings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

// Migraciones: agregar columnas si no existen
foreach ([
    "ALTER TABLE landings ADD COLUMN user_id INT NOT NULL DEFAULT 1 AFTER id",
    "ALTER TABLE landings ADD COLUMN filename VARCHAR(255)",
    "ALTER TABLE landings ADD COLUMN views INT DEFAULT 0",
    "ALTER TABLE landings ADD COLUMN color VARCHAR(50) DEFAULT '#3b82f6'",
] as $sql) { try { $pdo->exec($sql); } catch (Exception $e) {} }

// ==============================
//   ACCION: ACTUALIZAR DATOS (Título, Desc, Color)
// ==============================
if (isset($_GET['action']) && $_GET['action'] === 'update') {
    $id    = (int)($_GET['id'] ?? 0);
    $title = trim($_GET['title'] ?? '');
    $desc  = trim($_GET['description'] ?? '');
    $color = trim($_GET['color'] ?? '');
    
    if ($id > 0 && $title) {
        $pdo->prepare("UPDATE landings SET title = ?, description = ?, color = ? WHERE id = ? AND user_id = ?")
            ->execute([$title, $desc, $color, $id, $user_id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Datos incompletos']);
    }
    exit;
}

// ==============================
//   ACCION: ACTUALIZAR COLOR (Mantener por retrocompatibilidad o quitar si ya se usa update)
// ==============================
if (isset($_GET['action']) && $_GET['action'] === 'update_color') {
    $id = (int)($_GET['id'] ?? 0);
    $color = trim($_GET['color'] ?? '');
    if ($id > 0 && $color) {
        $pdo->prepare("UPDATE landings SET color = ? WHERE id = ? AND user_id = ?")->execute([$color, $id, $user_id]);
        echo json_encode(['success' => true]);
    }
    exit;
}

// ==============================
//   BORRAR  GET ?action=delete&id=X
// ==============================
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT filename FROM landings WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        $fn = $stmt->fetchColumn();
        if ($fn) {
            $filepath = $UPLOAD_DIR . $fn;
            if (file_exists($filepath)) @unlink($filepath);
        }
        $pdo->prepare("DELETE FROM landings WHERE id = ? AND user_id = ?")->execute([$id, $user_id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'ID inválido']);
    }
    exit;
}

// ==============================
//   LISTAR  GET
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Crear tabla de suscripciones si no existe
        $pdo->exec("CREATE TABLE IF NOT EXISTS landing_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            landing_id INT NOT NULL,
            user_id INT NOT NULL,
            token VARCHAR(32) UNIQUE NOT NULL,
            views INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_lu (landing_id, user_id)
        )");

        // Migración para redirecciones
        foreach ([
            "ALTER TABLE landing_subscriptions ADD COLUMN redirect_type ENUM('default', 'url', 'whatsapp') DEFAULT 'default'",
            "ALTER TABLE landing_subscriptions ADD COLUMN redirect_url VARCHAR(255) NULL",
            "ALTER TABLE landing_subscriptions ADD COLUMN whatsapp_number VARCHAR(50) NULL",
            "ALTER TABLE landing_subscriptions ADD COLUMN whatsapp_message TEXT NULL",
        ] as $sql) { try { $pdo->exec($sql); } catch (Exception $e) {} }

        $user_role = $_SESSION['user_role'] ?? 'subscriber';

        if ($user_role === 'admin') {
            // ADMIN: ve TODAS las landings con stats globales
            // Auto-sync carpeta
            $files = @scandir($UPLOAD_DIR);
            if ($files) {
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;
                    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'html') continue;
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM landings WHERE filename = ?");
                    $chk->execute([$file]);
                    if ((int)$chk->fetchColumn() === 0) {
                        $name = preg_replace('/^\d+_?/', '', pathinfo($file, PATHINFO_FILENAME));
                        $name = str_replace(['_','-'], ' ', $name);
                        $pdo->prepare("INSERT IGNORE INTO landings (user_id, title, description, filename) VALUES (?, ?, 'Importado automáticamente', ?)")
                            ->execute([$user_id, ucwords($name), $file]);
                    }
                }
            }

            $stmt = $pdo->query("SELECT * FROM landings ORDER BY id DESC");
            $landings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($landings as &$l) {
                // Obtener slug del admin (propietario de la landing)
                $adminStmt = $pdo->prepare("SELECT slug, name FROM users WHERE id = ?");
                $adminStmt->execute([$l['user_id']]);
                $adminRow = $adminStmt->fetch(PDO::FETCH_ASSOC);
                $adminSlug = ($adminRow && $adminRow['slug']) ? $adminRow['slug'] : 'usuario-' . $l['user_id'];
                $adminName = $adminRow ? $adminRow['name'] : 'Admin';

                // URL pública: siempre vía landings_gen.php (nunca el archivo estático directo,
                // que puede no existir en el servidor si no fue sincronizado con el deploy)
                $ownerUrl = CRM_URL . '/landings_gen/' . urlencode($adminSlug) . '?lp=' . $l['id'];
                $l['url']         = $ownerUrl;
                $l['url_display'] = str_replace(['http://', 'https://'], '', $ownerUrl);
                $l['can_delete']  = true;
                $l['is_admin']    = true;

                // Stats globales (todos los suscriptores sumados)
                $sv = $pdo->prepare("SELECT COALESCE(SUM(views),0) FROM landing_subscriptions WHERE landing_id = ?");
                $sv->execute([$l['id']]);
                $l['views'] = (int)$sv->fetchColumn();

                $sp = $pdo->prepare("SELECT COUNT(*) FROM prospects WHERE landing_id = ?");
                $sp->execute([$l['id']]);
                $l['prospect_count'] = (int)$sp->fetchColumn();

                $v = $l['views'];
                $p = $l['prospect_count'];
                $l['conversion_rate'] = ($v > 0) ? round(($p / $v) * 100, 1) : 0;

                // Número de suscriptores activos
                $sc = $pdo->prepare("SELECT COUNT(*) FROM landing_subscriptions WHERE landing_id = ?");
                $sc->execute([$l['id']]);
                $l['subscriber_count'] = (int)$sc->fetchColumn();

                // URLs de cada suscriptor para esta landing
                $subStmt = $pdo->prepare("
                    SELECT u.name, u.slug, u.id AS uid
                    FROM landing_subscriptions ls
                    JOIN users u ON ls.user_id = u.id
                    WHERE ls.landing_id = ?
                ");
                $subStmt->execute([$l['id']]);
                $subs = [];
                while ($s = $subStmt->fetch(PDO::FETCH_ASSOC)) {
                    $slug = $s['slug'] ?: 'usuario-' . $s['uid'];
                    $subUrl = CRM_URL . '/landings_gen/' . urlencode($slug) . '?lp=' . $l['id'];
                    $subs[] = [
                        'name'        => $s['name'],
                        'slug'        => $slug,
                        'url'         => $subUrl,
                        'url_display' => str_replace(['http://', 'https://'], '', $subUrl),
                    ];
                }
                // Si no hay suscriptores, mostrar la URL del admin como preview
                if (empty($subs)) {
                    $subs[] = [
                        'name'        => $adminName . ' (tú)',
                        'slug'        => $adminSlug,
                        'url'         => $ownerUrl,
                        'url_display' => $l['url_display'],
                    ];
                }
                $l['subscriber_urls'] = $subs;
            }
        } else {
            // SUSCRIPTOR: ve SOLO las landings que el admin le asignó explícitamente
            $stmt = $pdo->prepare("
                SELECT l.*, ls.token, ls.views AS sub_views, ls.id AS sub_id
                FROM landings l
                INNER JOIN landing_subscriptions ls ON l.id = ls.landing_id AND ls.user_id = ?
                ORDER BY l.id DESC
            ");
            $stmt->execute([$user_id]);
            $landings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Obtener slug del suscriptor para la URL pública
            $stmtSlug = $pdo->prepare("SELECT slug FROM users WHERE id = ?");
            $stmtSlug->execute([$user_id]);
            $userSlug = $stmtSlug->fetchColumn() ?: 'usuario-' . $user_id;

            foreach ($landings as &$l) {
                $l['can_delete'] = false;
                $l['is_admin']   = false;

                // URL pública del suscriptor: landings_gen/{slug}?lp={landing_id}
                $l['url']         = CRM_URL . '/landings_gen/' . urlencode($userSlug) . '?lp=' . $l['id'];
                $l['url_display'] = str_replace(['http://', 'https://'], '', CRM_URL) . '/landings_gen/' . urlencode($userSlug) . '?lp=' . $l['id'];

                // Stats PERSONALES del suscriptor (desde landing_subscriptions)
                $l['views'] = (int)($l['sub_views'] ?? 0);

                $sp = $pdo->prepare("SELECT COUNT(*) FROM prospects WHERE landing_id = ? AND user_id = ?");
                $sp->execute([$l['id'], $user_id]);
                $l['prospect_count'] = (int)$sp->fetchColumn();

                $v = $l['views'];
                $p = $l['prospect_count'];
                $l['conversion_rate'] = ($v > 0) ? round(($p / $v) * 100, 1) : 0;
            }
        }

        echo json_encode($landings);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

/**
 * Analiza los <form> del HTML original (antes de inyectar nada) con la
 * MISMA heurística que corre en el navegador (ver $formBridgeJs más abajo,
 * función extractLeadFields): busca en name/id/placeholder/type de cada
 * campo qué probablemente sea nombre/email/whatsapp. Sirve para devolverle
 * un reporte al admin en el momento de subir la landing, así sabe si el
 * mapeo automático va a funcionar o si el formulario usa nombres de campo
 * que no se pueden reconocer (y en ese caso ajustarlo antes de publicar).
 *
 * Las listas de keywords deben mantenerse iguales a las de $formBridgeJs.
 */
function analyzeLandingForms(string $html): array {
    $emailKeywords = ['email', 'correo', 'mail'];
    $phoneKeywords = ['whatsapp', 'wsp', 'telefono', 'teléfono', 'celular', 'movil', 'móvil', 'phone', 'tel'];
    $nameKeywords = ['name', 'nombre'];

    $matchesKeyword = function (string $haystack, array $keywords): bool {
        foreach ($keywords as $kw) {
            if (mb_stripos($haystack, $kw) !== false) return true;
        }
        return false;
    };

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    // Prefijo XML declarando UTF-8: sin esto, DOMDocument asume Latin-1 al
    // parsear HTML arbitrario que no trae su propio <meta charset>, y las
    // tildes/ñ de placeholders en español quedan corruptas.
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();

    $forms = $dom->getElementsByTagName('form');
    $report = [];

    foreach ($forms as $form) {
        $nameField = null; $emailField = null; $phoneField = null; $firstTextField = null;

        foreach (['input', 'textarea'] as $tag) {
            foreach ($form->getElementsByTagName($tag) as $input) {
                $type = strtolower($input->getAttribute('type') ?: 'text');
                if (in_array($type, ['submit', 'button', 'hidden', 'checkbox', 'radio', 'file'], true)) continue;

                $fieldName = $input->getAttribute('name');
                $fieldId = $input->getAttribute('id');
                $placeholder = $input->getAttribute('placeholder');
                $haystack = $fieldName . ' ' . $fieldId . ' ' . $placeholder;
                $label = $fieldName ? "{$tag}[name=\"{$fieldName}\"]" : ($fieldId ? "{$tag}#{$fieldId}" : ($placeholder ? "{$tag}[placeholder=\"{$placeholder}\"]" : "{$tag} sin nombre/id"));

                if (!$emailField && ($type === 'email' || $matchesKeyword($haystack, $emailKeywords))) { $emailField = $label; continue; }
                if (!$phoneField && ($type === 'tel' || $matchesKeyword($haystack, $phoneKeywords))) { $phoneField = $label; continue; }
                if (!$nameField && $matchesKeyword($haystack, $nameKeywords)) { $nameField = $label; continue; }
                if (!$firstTextField && $type === 'text') { $firstTextField = $label; }
            }
        }
        if (!$nameField) { $nameField = $firstTextField; }

        $report[] = [
            'name_found' => $nameField !== null,
            'name_field' => $nameField,
            'email_found' => $emailField !== null,
            'email_field' => $emailField,
            'whatsapp_found' => $phoneField !== null,
            'whatsapp_field' => $phoneField,
        ];
    }

    return $report;
}

// ==============================
//   SUBIR  POST multipart
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['landing_file'])) {
    // Solo admins pueden subir landings
    if (($_SESSION['user_role'] ?? 'subscriber') !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'No tenés permisos para subir landings']);
        exit;
    }
    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $color = trim($_POST['color'] ?? '#3b82f6');
    $file  = $_FILES['landing_file'];

    if (empty($title)) { echo json_encode(['error' => 'El título es obligatorio']); exit; }
    if ($file['error'] !== UPLOAD_ERR_OK) { echo json_encode(['error' => 'Error en la subida: código ' . $file['error']]); exit; }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'html') { echo json_encode(['error' => 'Solo se aceptan archivos .html']); exit; }

    $fn   = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
    $path = $UPLOAD_DIR . $fn;

    if (!move_uploaded_file($file['tmp_name'], $path)) {
        echo json_encode(['error' => 'No se pudo guardar el archivo en: ' . $UPLOAD_DIR]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO landings (user_id, title, description, filename, color) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $title, $desc, $fn, $color]);
    $landing_id = (int)$pdo->lastInsertId();

    $hasExistingForm = false;
    $formReport = [];

    // Inyectar modal de registro + tracker en el HTML
    $html = file_get_contents($path);
    if ($html !== false) {
        $crmUrl = CRM_URL;

        // Si la landing ya trae su propio <form>, ESE es la puerta de entrada
        // al CRM: no se tocan los botones de la página (nada de abrir el
        // modal al hacer click en cualquier link/botón). Si NO tiene form,
        // se mantiene el comportamiento de siempre (cualquier botón/enlace
        // abre el modal de captura).
        $hasExistingForm = (stripos($html, '<form') !== false);
        $formReport = $hasExistingForm ? analyzeLandingForms($html) : [];

        if ($hasExistingForm) {
            $buttonHijackJs = '';
            $formBridgeJs = <<<'JS'

  // La landing ya trae su propio formulario: se usa ESE como puerta de
  // entrada al CRM en vez del modal — no se tocan botones ni el resto de
  // la página. Heurística sobre name/id/placeholder/type de cada campo
  // para mapear a name/email/whatsapp (best-effort: un formulario de
  // terceros no sigue ninguna convención fija de nombres de campo).
  (function(){
    function fieldHay(el){ return ((el.name||'')+' '+(el.id||'')+' '+(el.placeholder||'')).toLowerCase(); }
    function fieldMatches(el, keywords){
      var hay = fieldHay(el);
      for(var i=0;i<keywords.length;i++){ if(hay.indexOf(keywords[i])>-1) return true; }
      return false;
    }
    function extractLeadFields(form){
      var name='', email='', phone='', firstText='';
      Array.prototype.forEach.call(form.elements, function(el){
        var tag = el.tagName;
        if(tag!=='INPUT'&&tag!=='TEXTAREA') return;
        var type = (el.type||'text').toLowerCase();
        if(type==='submit'||type==='button'||type==='hidden'||type==='checkbox'||type==='radio'||type==='file') return;
        var val = (el.value||'').trim();
        if(!val) return;
        if(!email && (type==='email' || fieldMatches(el, ['email','correo','mail']))){ email = val; return; }
        if(!phone && (type==='tel' || fieldMatches(el, ['whatsapp','wsp','telefono','teléfono','celular','movil','móvil','phone','tel']))){ phone = val; return; }
        if(!name && fieldMatches(el, ['name','nombre'])){ name = val; return; }
        if(!firstText && type==='text') firstText = val;
      });
      if(!name) name = firstText;
      return {name:name, email:email, whatsapp:phone};
    }
    Array.prototype.forEach.call(document.querySelectorAll('form'), function(form){
      form.addEventListener('submit', async function(e){
        var fields = extractLeadFields(form);
        if(!fields.name || !fields.email) return; // no se pudo mapear los campos: se deja que el form haga lo suyo
        e.preventDefault();
        var token = window.CRM_TOKEN || '';
        var finalApi = token ? base + '/api/landing_track.php' : API;
        var payload = token
          ? {action:'lead', token:token, name:fields.name, email:fields.email, whatsapp:fields.whatsapp}
          : {name:fields.name, email:fields.email, whatsapp:fields.whatsapp, landing_id:LID};
        var submitBtn = form.querySelector('[type="submit"],button:not([type])');
        var originalText = submitBtn ? submitBtn.textContent : '';
        if(submitBtn){ submitBtn.disabled = true; }
        try{
          var r = await fetch(finalApi, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
          var d = await r.json();
          if(d.id || d.success){
            if(d.redirect_url){ window.location.href = d.redirect_url; }
            else { form.innerHTML = '<div style="text-align:center;padding:2rem 1rem;"><h3 style="color:#10b981;font-size:1.2rem;font-weight:900;margin-bottom:.4rem;">✅ ¡Recibido!</h3><p style="color:#64748b;font-family:inherit;margin:0;">Te contactamos muy pronto. ¡Gracias!</p></div>'; }
          } else {
            alert('Error: '+(d.error||'Intenta de nuevo'));
            if(submitBtn){ submitBtn.disabled=false; submitBtn.textContent=originalText; }
          }
        }catch(err){
          alert('Error de red. Intenta de nuevo.');
          if(submitBtn){ submitBtn.disabled=false; submitBtn.textContent=originalText; }
        }
      });
    });
  })();
JS;
        } else {
            $buttonHijackJs = <<<'JS'
  // Event delegation: botones/enlaces externos abren el modal
  document.addEventListener('click', function(e){
    var el = e.target;
    for(var i=0;i<3;i++){
      if(!el || el===document.body) break;
      var id = el.id || '';
      if(id==='crm-send'||id==='crm-cancel'||id==='crm-fab'||id==='crm-ov'||id==='crm-box'||id==='crm-flag-btn'||id==='crm-num-inp'||id==='crm-country-search'||id==='crm-ok-close') return;
      if(drop.contains(el)||document.getElementById('crm-phone-wrap').contains(el)) return;
      if(el.tagName==='BUTTON'||el.tagName==='A'){
        e.preventDefault();
        openModal();
        return;
      }
      el = el.parentElement;
    }
  });
JS;
            $formBridgeJs = '';
        }
        $inject = <<<HTML

<!-- ===== CRM ULTRA INJECTED ===== -->
<style>
/* Ocultar permanentemente el botón flotante morado (!Quiero información!) en todas las landings */
#crm-fab { display: none !important; }
/* Modal overlay */
#crm-ov{display:none;position:fixed;inset:0;background:rgba(15,23,42,.65);backdrop-filter:blur(8px);z-index:99999;align-items:center;justify-content:center;padding:1rem}
#crm-ov.crm-open{display:flex}
/* Modal box */
#crm-box{background:#fff;border-radius:1.75rem;padding:2.25rem;width:100%;max-width:430px;box-shadow:0 30px 60px rgba(0,0,0,.3);animation:crm-in .25s ease}
@keyframes crm-in{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
#crm-box h2{font-size:1.4rem;font-weight:900;color:#0f172a;margin:0 0 .35rem}
#crm-box p{color:#64748b;font-size:.85rem;margin:0 0 1.25rem}
.crm-f{width:100%;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:.875rem;padding:.8rem 1.1rem;font-size:.875rem;outline:none;margin-bottom:.6rem;box-sizing:border-box;font-family:inherit;color:#0f172a !important}
.crm-f::placeholder{color:#94a3b8 !important;opacity:1 !important}
.crm-f:focus{border-color:#6366f1;background:#fff}
#crm-send{width:100%;background:#6366f1;color:#fff;border:none;border-radius:.875rem;padding:.9rem;font-weight:800;font-size:.95rem;cursor:pointer;transition:.2s;font-family:inherit}
#crm-send:hover{background:#4f46e5}
#crm-cancel{width:100%;background:#f1f5f9;color:#475569;border:none;border-radius:.875rem;padding:.7rem;font-weight:700;cursor:pointer;margin-top:.4rem;font-family:inherit}
#crm-ok{display:none;text-align:center;padding:.5rem}
#crm-ok h3{color:#10b981;font-size:1.2rem;font-weight:900;margin-bottom:.4rem}
/* Phone picker dentro del modal CRM */
#crm-phone-wrap{display:flex;width:100%;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:.875rem;overflow:hidden;margin-bottom:.6rem;box-sizing:border-box;transition:border-color .2s}
#crm-phone-wrap:focus-within{border-color:#6366f1;background:#fff}
#crm-flag-btn{display:flex;align-items:center;gap:.4rem;padding:.55rem .85rem;border:none;border-right:1.5px solid #e2e8f0;background:#f1f5f9;cursor:pointer;font-size:.85rem;white-space:nowrap;font-family:inherit;transition:background .15s}
#crm-flag-btn:hover{background:#e2e8f0}
#crm-flag-btn svg{width:12px;height:12px;color:#94a3b8}
#crm-num-inp{flex:1;border:none;background:transparent;padding:.8rem .9rem;font-size:.875rem;outline:none;font-family:inherit;color:#0f172a !important}
#crm-num-inp::placeholder{color:#94a3b8 !important;opacity:1 !important}
#crm-country-drop{display:none;position:absolute;z-index:99999;background:#fff;border:1px solid #e2e8f0;border-radius:1rem;box-shadow:0 20px 50px rgba(0,0,0,.15);width:280px;max-height:260px;overflow-y:auto;margin-top:2px}
#crm-country-drop.open{display:block}
#crm-country-search{width:100%;border:none;border-bottom:1px solid #f1f5f9;padding:.6rem 1rem;font-size:.8rem;outline:none;font-family:inherit;box-sizing:border-box;position:sticky;top:0;background:#fff}
.crm-country-item{display:flex;align-items:center;gap:.6rem;padding:.5rem 1rem;cursor:pointer;font-size:.82rem;transition:background .1s}
.crm-country-item:hover,.crm-country-item.active{background:#eef2ff;color:#4f46e5}
#crm-wa-hint{font-size:.72rem;color:#64748b;margin-bottom:.5rem;display:none}
</style>

<!-- Modal -->
<div id="crm-ov">
  <div id="crm-box">
    <div id="crm-form">
      <h2>¡Quiero más información!</h2>
      <p>Completá tus datos y nos contactamos a la brevedad.</p>
      <input class="crm-f" id="crm-n" type="text"  placeholder="Nombre completo *">
      <input class="crm-f" id="crm-e" type="email" placeholder="Email *">
      <!-- WhatsApp picker -->
      <label style="display:block;font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:.35rem;">WhatsApp *</label>
      <div style="position:relative;">
        <div id="crm-phone-wrap">
          <button type="button" id="crm-flag-btn" aria-haspopup="listbox">
            <span id="crm-flag-icon" style="font-size:1.2rem;line-height:1;"></span>
            <span id="crm-flag-code" style="font-family:monospace;font-weight:700;font-size:.8rem;color:#475569;"></span>
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
          </button>
          <input type="tel" id="crm-num-inp" placeholder="Tu número..." inputmode="tel" autocomplete="tel-national">
        </div>
        <div id="crm-country-drop">
          <input type="text" id="crm-country-search" placeholder="Buscar país...">
          <div id="crm-country-list"></div>
        </div>
      </div>
      <p id="crm-wa-hint"></p>
      <!-- Hidden con valor E.164 -->
      <input type="hidden" id="crm-w">
      <br>
      <button id="crm-send">Enviar mis datos →</button>
      <button id="crm-cancel">Cancelar</button>
    </div>
    <div id="crm-ok">
      <h3>✅ ¡Recibido!</h3>
      <p style="color:#64748b">Te contactamos muy pronto. ¡Gracias!</p>
      <button id="crm-ok-close" onclick="closeCRMModal()" style="width:100%;background:#f1f5f9;color:#475569;border:none;border-radius:.875rem;padding:.7rem;font-weight:700;cursor:pointer;margin-top:1rem;font-family:inherit">Cerrar</button>
    </div>
  </div>
</div>


<script>
(function(){
  var LID = $landing_id;
  var base = window.location.origin + window.location.pathname.split('/landings_gen/')[0].split('/l.php')[0];
  var API = base + '/api/prospects.php';
  var STATS = base + '/api/landings_stats.php';

  // ── Country data ──────────────────────────────────────────────────────────
  var COUNTRIES = [
    {name:'Argentina',flag:'🇦🇷',code:'+54',fmt:'11 1234-5678'},
    {name:'Bolivia',flag:'🇧🇴',code:'+591',fmt:'7 123 4567'},
    {name:'Brasil',flag:'🇧🇷',code:'+55',fmt:'11 91234-5678'},
    {name:'Chile',flag:'🇨🇱',code:'+56',fmt:'9 1234 5678'},
    {name:'Colombia',flag:'🇨🇴',code:'+57',fmt:'310 123 4567'},
    {name:'Costa Rica',flag:'🇨🇷',code:'+506',fmt:'8312 3456'},
    {name:'Ecuador',flag:'🇪🇨',code:'+593',fmt:'99 123 4567'},
    {name:'El Salvador',flag:'🇸🇻',code:'+503',fmt:'7012 3456'},
    {name:'Guatemala',flag:'🇬🇹',code:'+502',fmt:'5123 4567'},
    {name:'Honduras',flag:'🇭🇳',code:'+504',fmt:'9123 4567'},
    {name:'México',flag:'🇲🇽',code:'+52',fmt:'55 1234 5678'},
    {name:'Nicaragua',flag:'🇳🇮',code:'+505',fmt:'8123 4567'},
    {name:'Panamá',flag:'🇵🇦',code:'+507',fmt:'6123-4567'},
    {name:'Paraguay',flag:'🇵🇾',code:'+595',fmt:'981 234 567'},
    {name:'Perú',flag:'🇵🇪',code:'+51',fmt:'912 345 678'},
    {name:'Puerto Rico',flag:'🇵🇷',code:'+1787',fmt:'555-1234'},
    {name:'Rep. Dominicana',flag:'🇩🇴',code:'+1809',fmt:'809-555-1234'},
    {name:'Uruguay',flag:'🇺🇾',code:'+598',fmt:'91 234 567'},
    {name:'Venezuela',flag:'🇻🇪',code:'+58',fmt:'412 123 4567'},
    {name:'España',flag:'🇪🇸',code:'+34',fmt:'612 345 678'},
    {name:'Estados Unidos',flag:'🇺🇸',code:'+1',fmt:'555 123-4567'},
    {name:'Canadá',flag:'🇨🇦',code:'+1',fmt:'604 123-4567'},
    {name:'Alemania',flag:'🇩🇪',code:'+49',fmt:'151 23456789'},
    {name:'Francia',flag:'🇫🇷',code:'+33',fmt:'6 12 34 56 78'},
    {name:'Italia',flag:'🇮🇹',code:'+39',fmt:'312 345 6789'},
    {name:'Portugal',flag:'🇵🇹',code:'+351',fmt:'912 345 678'},
    {name:'Reino Unido',flag:'🇬🇧',code:'+44',fmt:'7911 123456'}
  ];
  var selIdx = 13; // Paraguay por defecto

  function digitsOnly(s){ return s.replace(/\D/g,''); }
  function buildE164(code,local){ var d=digitsOnly(local).replace(/^0+/,''); return d ? code+d : ''; }
  function isValid(e164){ var d=digitsOnly(e164); return d.length>=7&&d.length<=15; }

  var flagIcon   = document.getElementById('crm-flag-icon');
  var flagCode   = document.getElementById('crm-flag-code');
  var numInp     = document.getElementById('crm-num-inp');
  var hiddenW    = document.getElementById('crm-w');
  var drop       = document.getElementById('crm-country-drop');
  var searchInp  = document.getElementById('crm-country-search');
  var listEl     = document.getElementById('crm-country-list');
  var hint       = document.getElementById('crm-wa-hint');
  var flagBtn    = document.getElementById('crm-flag-btn');

  function setCountry(idx){
    selIdx=idx;
    var c=COUNTRIES[idx];
    flagIcon.textContent=c.flag;
    flagCode.textContent=c.code;
    numInp.placeholder=c.fmt;
    renderList('');
    updateValue();
  }

  function renderList(q){
    var filtered=COUNTRIES.filter(function(c,i){
      return c.name.toLowerCase().indexOf(q.toLowerCase())>-1||c.code.indexOf(q)>-1;
    });
    listEl.innerHTML=filtered.map(function(c,i){
      var real=COUNTRIES.indexOf(c);
      return '<div class="crm-country-item'+(real===selIdx?' active':'')+'" data-idx="'+real+'">' +
        '<span style="font-size:1.2rem;width:1.4rem;text-align:center;">'+c.flag+'</span>' +
        '<span style="flex:1;">'+c.name+'</span>' +
        '<span style="font-family:monospace;font-size:.75rem;color:#94a3b8;">'+c.code+'</span></div>';
    }).join('');
  }

  function updateValue(){
    var local=numInp.value.trim();
    var c=COUNTRIES[selIdx];
    var e164=local?buildE164(c.code,local):'';
    hiddenW.value=e164;
    if(local.length>0){
      if(isValid(e164)){
        hint.textContent='✅ '+e164;
        hint.style.color='#10b981';
        hint.style.display='block';
      } else {
        hint.textContent='⚠️ Ej: '+c.fmt;
        hint.style.color='#f59e0b';
        hint.style.display='block';
      }
    } else {
      hint.style.display='none';
      hiddenW.value='';
    }
  }

  flagBtn.addEventListener('click',function(e){
    e.stopPropagation();
    drop.classList.toggle('open');
    if(drop.classList.contains('open')){
      searchInp.value=''; renderList(''); searchInp.focus();
    }
  });

  searchInp.addEventListener('input',function(){ renderList(this.value); });

  listEl.addEventListener('click',function(e){
    var el=e.target.closest('[data-idx]');
    if(!el) return;
    setCountry(parseInt(el.dataset.idx));
    drop.classList.remove('open');
    numInp.focus();
  });

  document.addEventListener('click',function(e){
    if(!document.getElementById('crm-phone-wrap').contains(e.target)&&!drop.contains(e.target)) drop.classList.remove('open');
  });

  numInp.addEventListener('input',updateValue);
  numInp.addEventListener('blur',updateValue);

  // Inicializar
  setCountry(selIdx);

  // ── Modal ──────────────────────────────────────────────────────────────────
  function openModal(){ var ov=document.getElementById('crm-ov'); if(ov){ov.classList.add('crm-open'); ov.style.display='flex';} }
  function closeModal(){ var ov=document.getElementById('crm-ov'); if(ov){ov.classList.remove('crm-open'); ov.style.display='none';} }
  window.closeCRMModal = closeModal;

  document.getElementById('crm-cancel').addEventListener('click', closeModal);
  document.getElementById('crm-ov').addEventListener('click', function(e){ if(e.target===this) closeModal(); });

{$buttonHijackJs}

  // Enviar formulario
  document.getElementById('crm-send').addEventListener('click', async function(){
    var n=document.getElementById('crm-n').value.trim();
    var em=document.getElementById('crm-e').value.trim();
    var w=hiddenW.value.trim();
    if(!n||!em){ alert('Por favor completá nombre y email.'); return; }
    if(!w||!isValid(w)){ alert('Por favor ingresá un número de WhatsApp válido con prefijo de país.'); numInp.focus(); return; }
    var token = window.CRM_TOKEN || '';
    var finalApi = token ? base + '/api/landing_track.php' : API;
    var payload  = token ? {action: 'lead', token: token, name: n, email: em, whatsapp: w} : {name: n, email: em, whatsapp: w, landing_id: LID};

    this.textContent = 'Enviando...'; this.disabled = true;
    try{
      var r = await fetch(finalApi, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
      });
      var d = await r.json();
      if(d.id || d.success){
        if (d.redirect_url) {
          window.location.href = d.redirect_url;
        } else {
          document.getElementById('crm-form').style.display='none';
          document.getElementById('crm-ok').style.display='block';
          var fab = document.getElementById('crm-fab');
          if(fab) fab.style.display='none';
        }
      } else {
        alert('Error: '+(d.error||'Intenta de nuevo'));
        this.textContent='Enviar mis datos →'; this.disabled=false;
      }
    }catch(e){ alert('Error de red. Intenta de nuevo.'); this.textContent='Enviar mis datos →'; this.disabled=false; }
  });
{$formBridgeJs}

  // Track visita única por sesión
  var k='crm_v_'+LID;
  if(!sessionStorage.getItem(k)){
    fetch(STATS,{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({landing_id:LID,action:'view'})});
    sessionStorage.setItem(k,'1');
  }
})();
</script>
<!-- ===== /CRM ULTRA ===== -->
HTML;
        $html_out = (stripos($html, '</body>') !== false)
            ? str_ireplace('</body>', $inject . "\n</body>", $html)
            : $html . $inject;
        file_put_contents($path, $html_out);
    }

    echo json_encode([
        'success' => true,
        'id' => $landing_id,
        'form_detected' => $hasExistingForm,
        'form_report' => $formReport,
    ]);
    exit;
}

echo json_encode(['error' => 'Método no permitido']);
?>
