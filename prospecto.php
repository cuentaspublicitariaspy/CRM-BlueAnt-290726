<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once 'api/db_config.php';
$user_id = (int)$_SESSION['user_id'];
$is_admin = ($_SESSION['user_role'] ?? 'subscriber') === 'admin';

$agent_lead_id = isset($_GET['agent_lead']) ? (int)$_GET['agent_lead'] : null;
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

$prospect = null;

if ($agent_lead_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT lp.*, a.name AS agent_name, cs.domain AS agent_domain
            FROM lead_profiles lp
            JOIN agents a ON a.id = lp.agent_id
            LEFT JOIN chat_sessions cs ON lp.session_id = cs.id
            WHERE lp.id = ?
        ");
        $stmt->execute([$agent_lead_id]);
        $lead = $stmt->fetch();

        if ($lead) {
            $prospect = [
                'id' => $lead['id'],
                'name' => $lead['name'] ?? 'Sin nombre',
                'email' => $lead['email'] ?? '',
                'whatsapp' => $lead['phone'] ?? '',
                'landing_id' => null,
                'landing_title' => 'Agente: ' . ($lead['agent_name'] ?? 'IA'),
                'landing_color' => '#6366f1',
                'domain' => $lead['agent_domain'] ?? '',
                'agent_domain' => $lead['agent_domain'] ?? '',
                'agent_id' => $lead['agent_id'] ?? null,
                'origin_type' => 'agent',
                'status' => $lead['lead_stage'] ?? 'prospecto',
                'language' => 'es',
                'address' => '',
                'city' => '',
                'state' => '',
                'zip_code' => '',
                'has_business' => 0,
                'card_number' => '',
                'card_expiry' => '',
                'card_cvv' => '',
                'created_at' => $lead['created_at'] ?? date('Y-m-d H:i:s'),
                'user_id' => $user_id,
            ];
        }
    } catch (\Throwable $e) {
        header('Location: prospectos.php');
        exit();
    }
} elseif ($id) {
    // Obtener datos del prospecto/cliente desde CRM
    if ($is_admin) {
        $stmt = $pdo->prepare("
            SELECT p.*, l.title AS landing_title, l.color AS landing_color 
            FROM prospects p
            LEFT JOIN landings l ON p.landing_id = l.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.*, l.title AS landing_title, l.color AS landing_color 
            FROM prospects p
            LEFT JOIN landings l ON p.landing_id = l.id
            WHERE p.id = ? AND p.user_id = ?
        ");
        $stmt->execute([$id, $user_id]);
    }
    $prospect = $stmt->fetch();
}

if (!$prospect) {
    header('Location: prospectos.php');
    exit();
}

$agent_name = 'IA';
if (($prospect['origin_type'] ?? '') === 'agent' && !empty($prospect['agent_id'])) {
    try {
        $stmtA = $pdo->prepare("SELECT name FROM agents WHERE id = ?");
        $stmtA->execute([$prospect['agent_id']]);
        $name = $stmtA->fetchColumn();
        if ($name) $agent_name = $name;
    } catch (\Throwable $e) {}
}

// Obtener todos los servicios del usuario para el modal
$stmtSrv = $pdo->prepare("SELECT * FROM services ORDER BY name ASC");
$stmtSrv->execute();
$all_services = $stmtSrv->fetchAll();


// Obtener servicios contratados por este prospecto (solo para prospectos CRM)
$my_services = [];
$my_service_ids = [];
if ($id) {
    $stmtMySrv = $pdo->prepare("
        SELECT s.* 
        FROM services s
        INNER JOIN prospect_services ps ON s.id = ps.service_id
        WHERE ps.prospect_id = ?
    ");
    $stmtMySrv->execute([$id]);
    $my_services = $stmtMySrv->fetchAll();
    $my_service_ids = array_column($my_services, 'id');
}

// ID unificado para uso en JS (prospecto CRM o lead de agente)
$display_id = $id ?: $agent_lead_id;

// ── Calcular Nivel de interés ──
function calcularInterestLevel($p) {
    $score = 0;
    if (!empty($p['has_business'])) $score++;
    if (!empty($p['annual_income']) && (float)$p['annual_income'] >= 100000) $score++;
    if (!empty($p['marital_status']) && $p['marital_status'] === 'married') $score++;
    if (!empty($p['spouse_income']) && (float)$p['spouse_income'] > 0) $score++;
    if (!empty($p['owns_house'])) $score++;
    if ($score <= 1) $level = 'bajo';
    elseif ($score <= 3) $level = 'medio';
    else $level = 'alto';
    return ['score' => $score, 'level' => $level];
}
$interestData = calcularInterestLevel($prospect);
?>
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($prospect['name']); ?> - Ultra CRM</title>
    <?php if(isset($crm_favicon) && $crm_favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($crm_favicon); ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ultrablue: #0d1e56;
            --brandpurple: #6366f1;
            --sidebar-bg: #0d1e56;
            --accent-blue: #2563eb;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; color: #0f172a; }
        .glass-card { background: white; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }

        /* ── SIDEBAR ── */
        .sidebar-desktop { background: var(--sidebar-bg) !important; border-right: none !important; }
        .sidebar-desktop .brand-logo { background: white; color: var(--ultrablue); }
        .sidebar-desktop .brand-name { color: white !important; }
        .sidebar-desktop .nav-link { color: #94a3b8; }
        .sidebar-desktop .nav-link:hover { background: rgba(255,255,255,.05) !important; color: white !important; }
        .sidebar-desktop .nav-link.active { background: #2563eb !important; color: white !important; box-shadow: 0 4px 15px rgba(37,99,235,.3); }
        .sidebar-desktop .logout-btn { color: #94a3b8 !important; border-top: 1px solid rgba(255,255,255,.05); padding-top: 1rem; }
        .sidebar-desktop .logout-btn:hover { background: rgba(239,68,68,.1) !important; color: #f87171 !important; }
        .sidebar-desktop nav::-webkit-scrollbar { width: 4px; }
        .sidebar-desktop nav::-webkit-scrollbar-track { background: transparent; }
        .sidebar-desktop nav::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 2px; }
        .sidebar-desktop nav::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.2); }

        /* ── MODALS ── */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,.6); backdrop-filter:blur(6px); z-index:200; align-items:center; justify-content:center; padding:1rem; }
        .modal-overlay.open { display:flex; }
        
        /* Badges */
        .badge-prospecto { background-color: #fef3c7; color: #d97706; border-color: #fde68a; }
        .badge-active { background-color: #d1fae5; color: #059669; border-color: #a7f3d0; }
        .badge-inactive { background-color: #fee2e2; color: #dc2626; border-color: #fca5a5; }
    </style>
</head>
<body class="flex min-h-screen">

    <!-- Mobile Menu Overlay -->
    <div id="mobileMenu" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[100] hidden lg:hidden">
        <div class="w-72 h-full sidebar-desktop p-8 flex flex-col">
            <div class="flex items-center justify-between mb-10">
                <div class="flex items-center gap-3">
                    <div class="brand-logo w-8 h-8 rounded-lg flex items-center justify-center font-bold text-lg overflow-hidden shrink-0 bg-white text-indigo-900 shadow">
                        <?php echo $crm_logo ? '<img src="'.htmlspecialchars($crm_logo).'" class="w-full h-full object-cover">' : htmlspecialchars(substr($crm_name, 0, 1)); ?>
                    </div>
                    <span class="brand-name font-bold text-xl tracking-tight leading-tight text-white"><?php echo htmlspecialchars($crm_name); ?></span>
                </div>
                <button onclick="toggleMenu()" class="text-white/60"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
                        <nav class="flex-1 space-y-2">

                            <a href="index.php" class="nav-link   flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

                                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>Dashboard</a>

                            <a href="prospectos.php" class="nav-link active flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

                                <i data-lucide="users" class="w-5 h-5"></i>Prospectos</a>

                            <a href="acciones.php" class="nav-link   flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

                                <i data-lucide="list" class="w-5 h-5"></i>Acciones</a>

                            <a href="clientes.php" class="nav-link   flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

                                <i data-lucide="user-check" class="w-5 h-5"></i>Clientes</a>

                            <a href="servicios.php" class="nav-link   flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

                                <i data-lucide="briefcase" class="w-5 h-5"></i>Servicios</a>

                            <a href="agentes.php" class="nav-link   flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

                                <i data-lucide="bot" class="w-5 h-5"></i>Agentes</a>

                            <a href="landings.php" class="nav-link   flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

                                <i data-lucide="rocket" class="w-5 h-5"></i>Landings</a>

                            <a href="marketing.php" class="nav-link   flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

                                <i data-lucide="image" class="w-5 h-5"></i>Material de Mkt</a>

                            <?php if ($is_admin): ?>

                            <a href="usuarios.php" class="nav-link   flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

                                <i data-lucide="users" class="w-5 h-5"></i>Usuarios</a>

                            <a href="configuracion.php" class="nav-link   flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

                                <i data-lucide="settings" class="w-5 h-5"></i>Configuración</a>

                            <?php endif; ?>

                            <a href="perfil.php" class="nav-link   flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

                                <i data-lucide="user" class="w-5 h-5"></i>Mi Perfil</a>

                        </nav>
            <div class="pt-4 mt-auto">
                <button onclick="logout()" class="logout-btn w-full flex items-center gap-4 px-4 py-3 rounded-xl transition-all font-bold">
                    <i data-lucide="log-out" class="w-5 h-5"></i>Cerrar Sesión
                </button>
            </div>
        </div>
    </div>

    <!-- Sidebar Desktop -->
    <aside class="w-64 hidden lg:flex flex-col sticky top-0 h-screen sidebar-desktop shrink-0">
        <div class="p-8 flex items-center gap-3 mb-4">
            <div class="brand-logo w-9 h-9 rounded-xl flex items-center justify-center font-bold text-lg shadow overflow-hidden shrink-0 bg-white text-indigo-900">
                <?php echo $crm_logo ? '<img src="'.htmlspecialchars($crm_logo).'" class="w-full h-full object-cover">' : htmlspecialchars(substr($crm_name, 0, 1)); ?>
            </div>
            <span class="brand-name font-bold text-xl tracking-tight leading-tight text-white"><?php echo htmlspecialchars($crm_name); ?></span>
        </div>
                <nav class="flex-1 px-4 space-y-1 overflow-y-auto">

                    <a href="index.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                        <i data-lucide="layout-dashboard" class="w-5 h-5"></i>Dashboard</a>

                    <a href="prospectos.php" class="nav-link active flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                        <i data-lucide="users" class="w-5 h-5"></i>Prospectos</a>

                    <a href="acciones.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                        <i data-lucide="list" class="w-5 h-5"></i>Acciones</a>

                    <a href="clientes.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                        <i data-lucide="user-check" class="w-5 h-5"></i>Clientes</a>

                    <a href="servicios.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                        <i data-lucide="briefcase" class="w-5 h-5"></i>Servicios</a>

                    <a href="agentes.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                        <i data-lucide="bot" class="w-5 h-5"></i>Agentes</a>

                    <a href="landings.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                        <i data-lucide="rocket" class="w-5 h-5"></i>Landings</a>

                    <a href="marketing.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                        <i data-lucide="image" class="w-5 h-5"></i>Material de Mkt</a>

                    <?php if ($is_admin): ?>

                    <a href="usuarios.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                        <i data-lucide="users" class="w-5 h-5"></i>Usuarios</a>

                    <a href="configuracion.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                        <i data-lucide="settings" class="w-5 h-5"></i>Configuración</a>

                    <?php endif; ?>

                    <a href="perfil.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                        <i data-lucide="user" class="w-5 h-5"></i>Mi Perfil</a>

                </nav>
        <div class="px-4 pb-8">
            <button onclick="logout()" class="logout-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all font-bold">
                <i data-lucide="log-out" class="w-5 h-5"></i>Cerrar Sesión
            </button>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0 bg-slate-50">
        <!-- Header -->
        <header class="h-16 border-b border-slate-200 flex items-center justify-between px-6 lg:px-10 bg-white sticky top-0 z-40">
            <div class="flex items-center gap-4">
                <button onclick="goBack()" class="p-2 text-slate-400 hover:text-slate-900 transition-colors">
                    <i data-lucide="chevron-left" class="w-6 h-6"></i>
                </button>
                <h1 class="text-xl font-bold text-slate-900">Perfil del Contacto</h1>
            </div>
            <div class="flex gap-2">
                <button onclick="openEditModal()" class="bg-blue-50 text-blue-600 p-2.5 rounded-xl hover:bg-blue-600 hover:text-white transition-all shadow-sm" title="Editar Contacto">
                    <i data-lucide="edit-3" class="w-5 h-5"></i>
                </button>
                <button onclick="askDelete()" class="bg-rose-50 text-rose-500 p-2.5 rounded-xl hover:bg-rose-500 hover:text-white transition-all shadow-sm" title="Eliminar Contacto">
                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                </button>
                <div class="w-px h-6 bg-slate-200 mx-1 self-center"></div>
                <a href="tel:<?php echo htmlspecialchars($prospect['whatsapp']); ?>" class="bg-slate-100 text-slate-600 p-2.5 rounded-xl hover:bg-slate-200 transition-all shadow-sm" title="Llamar">
                    <i data-lucide="phone" class="w-5 h-5"></i>
                </a>
                <a href="https://wa.me/<?php echo preg_replace('/[^0-9+]/', '', $prospect['whatsapp']); ?>" target="_blank" class="bg-emerald-50 text-emerald-600 p-2.5 rounded-xl hover:bg-emerald-600 hover:text-white border border-emerald-100 transition-all shadow-sm" title="WhatsApp">
                    <i data-lucide="message-circle" class="w-5 h-5"></i>
                </a>
            </div>
        </header>

        <!-- Main Content -->
        <div class="p-6 lg:p-10 max-w-5xl mx-auto w-full space-y-8">
            <!-- Profile Overview Card -->
            <div class="bg-white border border-slate-200 rounded-[2.5rem] p-8 md:p-10 flex flex-col md:flex-row items-center gap-8 shadow-sm">
                <!-- Avatar -->
                <div class="w-28 h-28 rounded-[2rem] flex items-center justify-center text-white text-4xl font-black shadow-lg" 
                     style="background-color: <?php echo $prospect['landing_color'] ?: '#2563eb'; ?>; box-shadow: 0 10px 20px <?php echo ($prospect['landing_color'] ?: '#2563eb').'33'; ?>;">
                    <?php echo htmlspecialchars(substr($prospect['name'], 0, 2)); ?>
                </div>

                <div class="text-center md:text-left flex-1 space-y-4">
                    <div>
                        <h2 class="text-3xl font-extrabold text-slate-900"><?php echo htmlspecialchars($prospect['name']); ?></h2>
                        <p class="text-slate-500 flex items-center justify-center md:justify-start gap-2 mt-1">
                            <i data-lucide="mail" class="w-4 h-4 text-slate-400"></i>
                            <span><?php echo htmlspecialchars($prospect['email']); ?></span>
                        </p>
                    </div>

                    <div class="flex flex-wrap justify-center md:justify-start gap-3">
                        <div class="bg-slate-50 px-4 py-2 rounded-xl border border-slate-100 text-xs font-semibold text-slate-600">
                            <span class="text-[9px] uppercase tracking-widest text-slate-400 block mb-0.5">WhatsApp / Teléfono</span>
                            <span><?php echo htmlspecialchars($prospect['whatsapp']); ?></span>
                        </div>
                        <!-- Origen Badge -->
                        <?php 
                        $origin_type = $prospect['origin_type'] ?? (empty($prospect['landing_id']) ? 'manual' : 'landing');
                        if ($origin_type === 'agent'): 
                        ?>
                            <div class="px-4 py-2 rounded-xl text-xs font-semibold bg-indigo-500 text-white flex items-center gap-1.5" title="Conversación con Agente de IA">
                                <i data-lucide="bot" class="w-4 h-4"></i>
                                <span>Agente: <?php echo htmlspecialchars($agent_name); ?></span>
                            </div>
                            <?php if (!empty($prospect['agent_domain'])): ?>
                                <div class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 border border-slate-200 text-slate-600 flex items-center gap-1.5" title="Dominio web de origen">
                                    <i data-lucide="globe" class="w-4 h-4"></i>
                                    <span><?php echo htmlspecialchars($prospect['agent_domain']); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($origin_type === 'landing' && !empty($prospect['landing_title'])): ?>
                            <div class="px-4 py-2 rounded-xl text-xs font-semibold text-white flex items-center gap-1.5" 
                                 style="background-color: <?php echo htmlspecialchars($prospect['landing_color'] ?: '#2563eb'); ?>;" title="Formulario de Landing Page">
                                <i data-lucide="rocket" class="w-4 h-4"></i>
                                <span>Landing: <?php echo htmlspecialchars($prospect['landing_title']); ?></span>
                            </div>
                        <?php elseif ($origin_type === 'import'): ?>
                            <div class="px-4 py-2 rounded-xl text-xs font-semibold bg-emerald-500 text-white flex items-center gap-1.5" title="Importado mediante archivo CSV">
                                <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                                <span>Importación CSV</span>
                            </div>
                        <?php else: // manual ?>
                            <div class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-500 text-white flex items-center gap-1.5" title="Registrado manualmente en el CRM">
                                <i data-lucide="user-plus" class="w-4 h-4"></i>
                                <span>Carga Manual</span>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Badge de Estado -->
                        <div class="px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider border flex items-center gap-1.5 
                            <?php 
                            if ($prospect['status'] === 'cliente_activo') echo 'badge-active';
                            elseif ($prospect['status'] === 'cliente_inactivo') echo 'badge-inactive';
                            else echo 'badge-prospecto';
                            ?>">
                            <i data-lucide="shield-check" class="w-4 h-4"></i>
                            <span>
                                <?php 
                                if ($prospect['status'] === 'cliente_activo') echo 'Cliente Activo';
                                elseif ($prospect['status'] === 'cliente_inactivo') echo 'Cliente Inactivo';
                                else echo 'Prospecto';
                                ?>
                            </span>
                        </div>

                        <!-- Badge de Nivel de interés -->
                        <div class="px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider border flex items-center gap-1.5 
                            <?php 
                            if ($interestData['level'] === 'alto') echo 'badge-active';
                            elseif ($interestData['level'] === 'medio') echo 'badge-prospecto';
                            else echo 'badge-inactive';
                            ?>">
                            <i data-lucide="trending-up" class="w-4 h-4"></i>
                            <span>
                                interés: <?php 
                                if ($interestData['level'] === 'alto') echo 'Alto';
                                elseif ($interestData['level'] === 'medio') echo 'Medio';
                                else echo 'Bajo';
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grid de Información Adicional -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Columna Izquierda: Información de Ubicación e Idioma -->
                <div class="bg-white border border-slate-200 rounded-[2.5rem] p-8 space-y-6">
                    <h3 class="text-lg font-bold text-slate-900 flex items-center gap-3 border-b border-slate-100 pb-4">
                        <i data-lucide="map-pin" class="text-blue-600"></i> Ubicación e Idioma
                    </h3>
                    
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Idioma de contacto</span>
                            <span class="text-sm font-semibold text-slate-800 uppercase"><?php echo htmlspecialchars($prospect['language'] ?: 'es'); ?></span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Código Postal</span>
                            <span class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($prospect['zip_code'] ?: 'No especificado'); ?></span>
                        </div>
                        <div class="col-span-2">
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Dirección</span>
                            <span class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($prospect['address'] ?: 'No especificada'); ?></span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Ciudad</span>
                            <span class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($prospect['city'] ?: 'No especificada'); ?></span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Estado</span>
                            <span class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($prospect['state'] ?: 'No especificado'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Servicios Contratados y Facturación -->
                <div class="bg-white border border-slate-200 rounded-[2.5rem] p-8 space-y-6">
                    <h3 class="text-lg font-bold text-slate-900 flex items-center gap-3 border-b border-slate-100 pb-4">
                        <i data-lucide="briefcase" class="text-blue-600"></i> Servicios y Negocio
                    </h3>

                    <!-- Servicios Contratados -->
                    <div class="space-y-3">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Servicios Asignados</span>
                        <?php if (empty($my_services)): ?>
                            <div class="bg-amber-50 border border-amber-200/50 rounded-2xl p-4 text-center">
                                <p class="text-xs font-semibold text-amber-800">Este contacto no tiene servicios asignados.</p>
                                <?php if ($prospect['status'] === 'prospecto'): ?>
                                    <p class="text-[10px] text-amber-600/80 mt-0.5">Asignar servicios lo convertirá automáticamente en Cliente Activo.</p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="flex flex-wrap gap-1.5">
                                <?php foreach ($my_services as $s): ?>
                                    <span class="text-xs font-bold bg-blue-50 text-blue-600 px-3 py-1.5 rounded-xl border border-blue-100">
                                        <?php echo htmlspecialchars($s['name']); ?> ($<?php echo number_format($s['price'], 2); ?>)
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Datos de Tarjeta/Negocio -->
                    <div class="space-y-3 pt-2">
                        <div class="flex items-center justify-between">
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">?Tiene Negocio?</span>
                            <span class="text-xs font-bold px-2.5 py-0.5 rounded-full <?php echo $prospect['has_business'] ? 'bg-indigo-50 text-indigo-600' : 'bg-slate-100 text-slate-500'; ?>">
                                <?php echo $prospect['has_business'] ? 'S? tiene' : 'No tiene'; ?>
                            </span>
                        </div>

                        <?php if ($prospect['has_business']): ?>
                            <div class="bg-slate-50 border border-slate-200/60 rounded-2xl p-4 space-y-2">
                                <div class="flex items-center gap-2 text-xs font-bold text-slate-400">
                                    <i data-lucide="credit-card" class="w-4 h-4 text-slate-400 shrink-0"></i>
                                    <span>DATOS DE FACTURación</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-sm font-semibold text-slate-800">
                                    <div class="col-span-2">
                                        <span class="text-[10px] text-slate-400 block font-normal">Número de tarjeta</span>
                                        <span>
                                            <?php 
                                            $card = $prospect['card_number'];
                                            if ($card) {
                                                echo '???? ???? ???? ' . substr($card, -4);
                                            } else {
                                                echo 'No registrada';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-[10px] text-slate-400 block font-normal">Vencimiento</span>
                                        <span><?php echo htmlspecialchars($prospect['card_expiry'] ?: 'N/A'); ?></span>
                                    </div>
                                    <div>
                                        <span class="text-[10px] text-slate-400 block font-normal">CVV</span>
                                        <span><?php echo $prospect['card_cvv'] ? '???' : 'N/A'; ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-xs text-slate-400 italic">No aplica información de facturación (No tiene negocio).</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Seguimiento y Chatbot (Tabs) -->
            <div class="bg-white border border-slate-200 rounded-[2.5rem] p-8 md:p-10 space-y-6 shadow-sm">
                <!-- Tab Bar -->
                <div class="flex items-center gap-2 border-b border-slate-100 pb-4">
                    <button onclick="switchTab('activities')" id="tab-activities-btn" class="tab-btn px-4 py-2 text-xs font-bold rounded-xl transition-all" style="background: #2563eb; color: white;">Historial de Seguimiento</button>
                    <button onclick="switchTab('chatbot')" id="tab-chatbot-btn" class="tab-btn px-4 py-2 text-xs font-bold rounded-xl transition-all hidden" style="background: transparent; color: #94a3b8;">Conversación Chatbot</button>
                </div>
                
                <!-- Tab: Activities -->
                <div id="tab-activities" class="tab-content space-y-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-900 flex items-center gap-3">
                            <i data-lucide="history" class="text-blue-600"></i> Historial de Seguimiento
                        </h3>
                        <button onclick="toggleActivitiesAccordion()" id="activitiesBtn" class="text-xs font-bold text-blue-600 hover:underline flex items-center gap-1">
                            <i data-lucide="list" class="w-4 h-4"></i> Ver Actividades
                        </button>
                    </div>

                    <div id="activities-accordion" class="hidden space-y-4">
                        <div id="activity-list" class="space-y-4 max-h-[300px] overflow-y-auto pr-2"></div>
                    </div>

<form id="actionForm" class="space-y-4 pt-2">
                        <div>
                            <label for="noteInput" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Registrar Nueva Acción de Seguimiento</label>
                            <div class="relative">
                                <textarea name="note" id="noteInput" required placeholder="Escrib? el detalle del seguimiento..." oninput="autoResizeNote(this); updateNoteCounter(this);" class="w-full bg-white border-2 border-slate-200 rounded-2xl p-5 pb-8 outline-none focus:border-indigo-400 focus:shadow-lg focus:shadow-indigo-100/50 text-slate-800 min-h-[120px] max-h-[320px] resize-none transition-all duration-200 placeholder-slate-300 text-sm leading-relaxed"></textarea>
                                <div class="absolute bottom-2 right-3 text-[10px] text-slate-300 font-mono select-none">
                                    <span id="noteCounter">0</span>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl font-bold shadow-lg shadow-indigo-500/25 transition-all active:scale-[0.98]">
                            <i data-lucide="save" class="w-5 h-5"></i>Guardar Nota de Seguimiento
                        </button>
                    </form>
                </div>
                
                <!-- Tab: Chatbot -->
                <div id="tab-chatbot" class="tab-content hidden space-y-6">
                    <div class="flex flex-col md:flex-row md:items-center justify-between pb-4 gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center shrink-0">
                                <i data-lucide="bot" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-900">Conversación con Chatbot</h3>
                                <p class="text-xs text-slate-400 font-medium">Historial completo de la sesión de chat</p>
                            </div>
                        </div>
                        <div id="chatbotMetadata" class="flex flex-wrap gap-2 text-xs"></div>
                    </div>

                    <div id="chatbotSummarySection" class="hidden grid grid-cols-1 md:grid-cols-2 gap-4 bg-slate-50 p-6 rounded-2xl border border-slate-100">
                        <div id="chatbotSummaryCol" class="hidden">
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Resumen de la Conversación</span>
                            <p id="chatbotSummaryText" class="text-xs text-slate-700 font-medium leading-relaxed"></p>
                        </div>
                        <div id="chatbotProblemCol" class="hidden">
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Problema Principal</span>
                            <p id="chatbotProblemText" class="text-xs text-slate-700 font-medium leading-relaxed"></p>
                        </div>
                    </div>

                    <div class="bg-slate-50 rounded-2xl border border-slate-100 p-6 text-center">
                        <p class="text-xs text-slate-400 font-medium">Revisa el resumen y los metadatos arriba para evaluar el interés del prospecto.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Editar Contacto Completo -->
    <div id="editContactModal" class="modal-overlay">
        <div class="bg-white w-full max-w-2xl rounded-[2.5rem] shadow-2xl p-8 md:p-10 relative space-y-6 max-h-[90vh] overflow-y-auto">
            <button onclick="closeEditModal()" class="absolute top-6 right-6 p-2 rounded-full hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-all">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>

            <div>
                <h3 class="text-2xl font-bold text-slate-900">Editar Información del Contacto</h3>
                <p class="text-sm text-slate-500 mt-1">Modifica los datos del contacto, ubicación, facturación y servicios.</p>
            </div>

            <form id="editContactForm" onsubmit="saveContact(event)" class="space-y-6">
                <!-- Datos Básicos -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label for="modal_name" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Nombre Completo</label>
                        <input type="text" name="name" id="modal_name" value="<?php echo htmlspecialchars($prospect['name']); ?>" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                    </div>
                    <div>
                        <label for="modal_email" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Correo Electrónico</label>
                        <input type="email" name="email" id="modal_email" value="<?php echo htmlspecialchars($prospect['email']); ?>" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                    </div>
                    <div>
                        <label for="modal_whatsapp" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">WhatsApp / Teléfono</label>
                        <input type="text" name="whatsapp" id="modal_whatsapp" value="<?php echo htmlspecialchars($prospect['whatsapp']); ?>" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                    </div>
                    <div>
                        <label for="modal_language" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Idioma</label>
                        <select name="language" id="modal_language" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                            <option value="es" <?php echo $prospect['language'] === 'es' ? 'selected' : ''; ?>>Español</option>
                            <option value="en" <?php echo $prospect['language'] === 'en' ? 'selected' : ''; ?>>Inglés</option>
                        </select>
                    </div>
                </div>

                <!-- Ubicación -->
                <div class="border-t border-slate-100 pt-5 space-y-4">
                    <h4 class="text-sm font-bold text-slate-800">Dirección y Ubicación</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div class="md:col-span-3">
                            <label for="modal_address" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Calle y Número</label>
                            <input type="text" name="address" id="modal_address" value="<?php echo htmlspecialchars($prospect['address'] ?: ''); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                        </div>
                        <div>
                            <label for="modal_city" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Ciudad</label>
                            <input type="text" name="city" id="modal_city" value="<?php echo htmlspecialchars($prospect['city'] ?: ''); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                        </div>
                        <div>
                            <label for="modal_state" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Estado</label>
                            <input type="text" name="state" id="modal_state" value="<?php echo htmlspecialchars($prospect['state'] ?: ''); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                        </div>
                        <div>
                            <label for="modal_zip_code" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Código Postal</label>
                            <input type="text" name="zip_code" id="modal_zip_code" value="<?php echo htmlspecialchars($prospect['zip_code'] ?: ''); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                        </div>
                    </div>
                </div>

                <!-- Estado del Contacto -->
                <div>
                    <label for="modal_status" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Estado del Contacto</label>
                    <select name="status" id="modal_status" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                        <option value="prospecto" <?php echo $prospect['status'] === 'prospecto' ? 'selected' : ''; ?>>Prospecto</option>
                        <option value="cliente_activo" <?php echo $prospect['status'] === 'cliente_activo' ? 'selected' : ''; ?>>Cliente Activo</option>
                        <option value="cliente_inactivo" <?php echo $prospect['status'] === 'cliente_inactivo' ? 'selected' : ''; ?>>Cliente Inactivo</option>
                    </select>
                </div>

                <!-- Servicios -->
                <div class="border-t border-slate-100 pt-5">
                    <label class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-3">Servicios Asignados (Seleccionar uno convertir? el contacto a Cliente Activo)</label>
                    <?php if (empty($all_services)): ?>
                        <p class="text-sm text-slate-400 italic">No tienes servicios definidos en el sistema. Créalos primero en la pestaña <a href="servicios.php" class="text-blue-500 underline">Servicios</a>.</p>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <?php foreach ($all_services as $s): ?>
                                <label class="flex items-center gap-3 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-100 transition-all select-none">
                                    <input type="checkbox" name="services[]" value="<?php echo $s['id']; ?>" 
                                        <?php echo in_array($s['id'], $my_service_ids) ? 'checked' : ''; ?>
                                        onchange="onServiceCheckboxChange()"
                                        class="service-checkbox w-4 h-4 rounded text-blue-600 border-slate-300 focus:ring-blue-500">
                                    <div class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($s['name']); ?></div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Evaluación de interés -->
                <div class="border-t border-slate-100 pt-5 space-y-4">
                    <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                        <i data-lucide="trending-up" class="w-4 h-4 text-blue-600"></i> Evaluación de interés
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label for="modal_annual_income" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Ingresos Anuales ($)</label>
                            <input type="number" name="annual_income" id="modal_annual_income" value="<?php echo htmlspecialchars($prospect['annual_income'] ?? ''); ?>" step="0.01" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                        </div>
                        <div>
                            <label for="modal_marital_status" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Estado Civil</label>
                            <select name="marital_status" id="modal_marital_status" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                                <option value="">Seleccionar...</option>
                                <option value="single" <?php echo ($prospect['marital_status'] ?? '') === 'single' ? 'selected' : ''; ?>>Soltero</option>
                                <option value="married" <?php echo ($prospect['marital_status'] ?? '') === 'married' ? 'selected' : ''; ?>>Casado</option>
                                <option value="divorced" <?php echo ($prospect['marital_status'] ?? '') === 'divorced' ? 'selected' : ''; ?>>Divorciado</option>
                                <option value="widowed" <?php echo ($prospect['marital_status'] ?? '') === 'widowed' ? 'selected' : ''; ?>>Viudo</option>
                            </select>
                        </div>
                        <div>
                            <label for="modal_spouse_income" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Ingresos Cónyuge ($)</label>
                            <input type="number" name="spouse_income" id="modal_spouse_income" value="<?php echo htmlspecialchars($prospect['spouse_income'] ?? ''); ?>" step="0.01" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                        </div>
                    </div>
                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="owns_house" id="modal_owns_house" value="1" <?php echo !empty($prospect['owns_house']) ? 'checked' : ''; ?> class="w-4 h-4 rounded text-blue-600 border-slate-300 focus:ring-blue-500">
                            <span class="text-sm font-semibold text-slate-800">Tiene Casa Propia</span>
                        </label>
                        <div class="text-xs text-slate-400 font-medium" id="interestScoreDisplay">
                            Puntaje: <?php echo $interestData['score']; ?>/5 ? 
                            Nivel: <span class="font-bold" style="color: <?php echo $interestData['level'] === 'alto' ? '#059669' : ($interestData['level'] === 'medio' ? '#d97706' : '#dc2626'); ?>">
                                <?php echo ucfirst($interestData['level']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Negocio / Facturación -->
                <div class="border-t border-slate-100 pt-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-bold text-slate-800">?Tiene Negocio?</h4>
                            <p class="text-xs text-slate-400 mt-0.5 font-medium">Habilita esta opción para guardar sus datos fiscales/comerciales y de pago.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="has_business" id="modal_has_business" 
                                <?php echo $prospect['has_business'] ? 'checked' : ''; ?>
                                onchange="toggleModalCardSection()" class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>

                    <!-- Datos Tarjeta (Despliegue Dinámico) -->
                    <div id="modalCardSection" class="<?php echo $prospect['has_business'] ? '' : 'hidden'; ?> bg-slate-50 p-6 rounded-[2.5rem] border border-slate-100 space-y-4 transition-all">
                        <h5 class="text-xs font-bold uppercase tracking-widest text-slate-400">Información de Facturación / Pago</h5>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-3">
                                <label for="modal_card_number" class="block text-[11px] font-bold text-slate-500 mb-1">Número de Tarjeta</label>
                                <input type="text" name="card_number" id="modal_card_number" value="<?php echo htmlspecialchars($prospect['card_number'] ?: ''); ?>" placeholder="4111 2222 3333 4444" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                            </div>
                            <div>
                                <label for="modal_card_expiry" class="block text-[11px] font-bold text-slate-500 mb-1">Vencimiento (MM/AA)</label>
                                <input type="text" name="card_expiry" id="modal_card_expiry" value="<?php echo htmlspecialchars($prospect['card_expiry'] ?: ''); ?>" placeholder="12/28" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                            </div>
                            <div>
                                <label for="modal_card_cvv" class="block text-[11px] font-bold text-slate-500 mb-1">CVV</label>
                                <input type="text" name="card_cvv" id="modal_card_cvv" value="<?php echo htmlspecialchars($prospect['card_cvv'] ?: ''); ?>" placeholder="123" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="closeEditModal()" class="px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-2xl font-bold transition-all">
                        Cancelar
                    </button>
                    <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl font-bold shadow-lg shadow-blue-500/25 transition-all">
                        Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        const prospectId = "<?php echo $display_id; ?>";
        const currentStatus = "<?php echo $prospect['status']; ?>";
        const isAgentLead = <?php echo $agent_lead_id ? 'true' : 'false'; ?>;
        const prospectEmail = <?php echo json_encode($prospect['email']); ?>;
        const prospectWhatsapp = <?php echo json_encode($prospect['whatsapp']); ?>;

        function toggleMenu() {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('hidden');
        }

        function goBack() {
            if (isAgentLead || currentStatus === 'prospecto') {
                window.location.href = 'prospectos.php';
            } else {
                window.location.href = 'clientes.php';
            }
        }

        function logout() {
            if (confirm('?Seguro que quieres cerrar sesión?')) {
                window.location.href = 'api/logout.php';
            }
        }

        // CONTROL CARD DISPLAY IN MODAL
        function toggleModalCardSection() {
            const hasBiz = document.getElementById('modal_has_business').checked;
            const cardSec = document.getElementById('modalCardSection');
            if (hasBiz) {
                cardSec.classList.remove('hidden');
            } else {
                cardSec.classList.add('hidden');
            }
        }

        // TRIGGER ON SERVICE CHECKBOX CHANGE
        function onServiceCheckboxChange() {
            const checkboxes = document.querySelectorAll('.service-checkbox:checked');
            const statusSelect = document.getElementById('modal_status');
            if (checkboxes.length > 0) {
                // Seleccionar autom?ticamente cliente_activo
                statusSelect.value = 'cliente_activo';
            }
        }

        // DELETE CONTACT
        async function askDelete() {
            if (confirm('?Seguro que quieres eliminar este contacto? Se borrarán sus actividades.')) {
                fetch('api/prospects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', id: prospectId })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        goBack();
                    } else {
                        alert('Error al eliminar: ' + (data.error || 'Desconocido'));
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error de red al intentar eliminar.');
                });
            }
        }

        // MODAL MANAGEMENT
        const editModal = document.getElementById('editContactModal');
        
        function openEditModal() {
            editModal.classList.add('open');
        }

        function closeEditModal() {
            editModal.classList.remove('open');
        }

        // SAVE CONTACT DETAILS
        function saveContact(e) {
            e.preventDefault();

            const name = document.getElementById('modal_name').value;
            const email = document.getElementById('modal_email').value;
            const whatsapp = document.getElementById('modal_whatsapp').value;
            const language = document.getElementById('modal_language').value;
            const address = document.getElementById('modal_address').value;
            const city = document.getElementById('modal_city').value;
            const state = document.getElementById('modal_state').value;
            const zip_code = document.getElementById('modal_zip_code').value;
            const status = document.getElementById('modal_status').value;
            
            const has_business = document.getElementById('modal_has_business').checked ? 1 : 0;
            const card_number = document.getElementById('modal_card_number').value;
            const card_expiry = document.getElementById('modal_card_expiry').value;
            const card_cvv = document.getElementById('modal_card_cvv').value;
            const annual_income = document.getElementById('modal_annual_income').value;
            const marital_status = document.getElementById('modal_marital_status').value;
            const spouse_income = document.getElementById('modal_spouse_income').value;
            const owns_house = document.getElementById('modal_owns_house').checked ? 1 : 0;

            // Obtener servicios
            const serviceCheckboxes = document.querySelectorAll('.service-checkbox:checked');
            const services = Array.from(serviceCheckboxes).map(chk => parseInt(chk.value));

            // Si pasa a 'cliente_activo' y tiene servicios pero no tiene dirección, o viceversa, el endpoint api/clients.php lo procesa
            const payload = {
                action: 'update_client_info',
                client_id: parseInt(prospectId),
                name: name,
                email: email,
                whatsapp: whatsapp,
                language: language,
                address: address,
                city: city,
                state: state,
                zip_code: zip_code,
                status: status,
                has_business: has_business,
                card_number: card_number,
                card_expiry: card_expiry,
                card_cvv: card_cvv,
                annual_income: annual_income,
                marital_status: marital_status,
                spouse_income: spouse_income,
                owns_house: owns_house,
                services: services
            };

            fetch('api/clients.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error al actualizar el contacto: ' + (data.error || 'Desconocido'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('Ocurri? un error al enviar la solicitud.');
            });
        }

        // ACTIVITIES LOG
        let activitiesLoaded = false;
        async function toggleActivitiesAccordion() {
            const acc = document.getElementById('activities-accordion');
            const btn = document.getElementById('activitiesBtn');
            
            if (!acc.classList.contains('hidden')) {
                acc.classList.add('hidden');
                btn.innerHTML = '<i data-lucide="list" class="w-4 h-4"></i> Ver Actividades';
                lucide.createIcons();
                return;
            }

            acc.classList.remove('hidden');
            btn.innerHTML = '<i data-lucide="eye-off" class="w-4 h-4"></i> Ocultar Actividades';
            lucide.createIcons();
            
            if (activitiesLoaded) return; // Evitar llamadas repetidas
            
            const list = document.getElementById('activity-list');
            list.innerHTML = `<div class="text-center py-6 text-slate-400 text-sm">Cargando actividades...</div>`;

            try {
                const res = await fetch(`api/activities.php?prospect_id=${prospectId}`);
                const data = await res.json();
                
                if (data.length === 0) {
                    list.innerHTML = `<div class="text-center py-6 text-slate-300 text-sm">No hay actividad registrada</div>`;
                } else {
                    list.innerHTML = data.map(a => `
                        <div class="flex gap-4 items-start p-3 bg-slate-50 rounded-xl border border-slate-100">
                            <div class="w-8 h-8 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center shrink-0">
                                <i data-lucide="message-square" class="w-4 h-4"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">${new Date(a.created_at).toLocaleString()}</span>
                                </div>
                                <p class="text-slate-700 text-xs leading-relaxed font-medium">${a.note}</p>
                            </div>
                        </div>
                    `).join('');
                    activitiesLoaded = true;
                }
                lucide.createIcons();
            } catch (err) { 
                console.error(err); 
                list.innerHTML = `<div class="text-center py-6 text-red-400 text-sm">Error al cargar historial</div>`;
            }
        }

        // SAVE NEW ACTIVITY ACTION
        document.getElementById('actionForm').onsubmit = async (e) => {
            e.preventDefault();
            const note = document.getElementById('noteInput').value;
            
            try {
                const res = await fetch('api/activities.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ prospect_id: prospectId, note: note })
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('noteInput').value = '';
                    activitiesLoaded = false;
                    const acc = document.getElementById('activities-accordion');
                    if (acc.classList.contains('hidden')) {
                        toggleActivitiesAccordion();
                    } else {
                        toggleActivitiesAccordion();
                        toggleActivitiesAccordion();
                    }
                    
                    // Alerta elegante temporal
                    const successToast = document.createElement('div');
                    successToast.className = "fixed bottom-5 right-5 bg-emerald-500 text-white font-bold py-3 px-6 rounded-2xl shadow-lg z-50 transition-all transform duration-300";
                    successToast.innerText = "?Acción registrada correctamente!";
                    document.body.appendChild(successToast);
                    setTimeout(() => {
                        successToast.style.opacity = '0';
                        setTimeout(() => successToast.remove(), 300);
                    }, 2500);
                } else {
                    alert('Error al guardar acción: ' + (data.error || 'Desconocido'));
                }
            } catch (err) {
                console.error(err);
                alert('Error al guardar nota.');
            }
        };

        // Auto-resize textarea
        function autoResizeNote(el) {
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 320) + 'px';
        }
        function updateNoteCounter(el) {
            document.getElementById('noteCounter').textContent = el.value.length;
        }
        // Inicializar contador al cargar
        document.addEventListener('DOMContentLoaded', function() {
            var ta = document.getElementById('noteInput');
            if (ta) { autoResizeNote(ta); updateNoteCounter(ta); }
        });

        // TAB SWITCHING
        function switchTab(tab) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.getElementById('tab-' + tab).classList.remove('hidden');
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.style.background = 'transparent';
                btn.style.color = '#94a3b8';
            });
            const activeBtn = document.getElementById('tab-' + tab + '-btn');
            activeBtn.style.background = '#2563eb';
            activeBtn.style.color = 'white';
        }

        // CHATBOT HISTORY LOADER
        function escapeHtml(text) {
            if (!text) return '';
            return text
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        async function loadChatbotHistory() {
            if (!prospectEmail && !prospectWhatsapp) return;
            
            try {
                const params = new URLSearchParams();
                if (prospectEmail) params.append('email', prospectEmail);
                if (prospectWhatsapp) params.append('whatsapp', prospectWhatsapp);
                
                const res = await fetch(`api/agent-report.php?${params.toString()}`);
                if (!res.ok) return;
                const data = await res.json();
                
                if (data.error || !data.messages || data.messages.length === 0) {
                    return; // No history or error
                }
                
                // Show Chatbot Tab Button
                const chatbotBtn = document.getElementById('tab-chatbot-btn');
                chatbotBtn.classList.remove('hidden');
                
                // Set metadata/badges
                const metaContainer = document.getElementById('chatbotMetadata');
                metaContainer.innerHTML = '';
                
                if (data.session) {
                    const session = data.session;
                    if (session.intent) {
                        metaContainer.innerHTML += `
                            <span class="px-2.5 py-1 rounded-lg font-bold bg-blue-50 border border-blue-100 text-blue-600 flex items-center gap-1">
                                <i data-lucide="compass" class="w-3.5 h-3.5"></i>
                                Intención: ${escapeHtml(session.intent)}
                            </span>
                        `;
                    }
                    if (session.topic) {
                        metaContainer.innerHTML += `
                            <span class="px-2.5 py-1 rounded-lg font-bold bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center gap-1">
                                <i data-lucide="tag" class="w-3.5 h-3.5"></i>
                                Tema: ${escapeHtml(session.topic)}
                            </span>
                        `;
                    }
                    if (session.next_action) {
                        metaContainer.innerHTML += `
                            <span class="px-2.5 py-1 rounded-lg font-bold bg-amber-50 border border-amber-100 text-amber-600 flex items-center gap-1" title="Siguiente acción sugerida">
                                <i data-lucide="arrow-right-circle" class="w-3.5 h-3.5"></i>
                                Siguiente Acción: ${escapeHtml(session.next_action)}
                            </span>
                        `;
                    }
                }
                
                if (data.profile) {
                    const profile = data.profile;
                    if (profile.lead_score) {
                        metaContainer.innerHTML += `
                            <span class="px-2.5 py-1 rounded-lg font-bold bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center gap-1">
                                <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                                Score: ${escapeHtml(profile.lead_score)}
                            </span>
                        `;
                    }
                    if (profile.urgency) {
                        metaContainer.innerHTML += `
                            <span class="px-2.5 py-1 rounded-lg font-bold bg-rose-50 border border-rose-100 text-rose-600 flex items-center gap-1">
                                <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                                Urgencia: ${escapeHtml(profile.urgency)}
                            </span>
                        `;
                    }
                    if (profile.service_interest) {
                        metaContainer.innerHTML += `
                            <span class="px-2.5 py-1 rounded-lg font-bold bg-purple-50 border border-purple-100 text-purple-600 flex items-center gap-1">
                                <i data-lucide="briefcase" class="w-3.5 h-3.5"></i>
                                interés: ${escapeHtml(profile.service_interest)}
                            </span>
                        `;
                    }
                    
                    // Summary and Main Problem
                    let showSummarySection = false;
                    if (profile.conversation_summary) {
                        document.getElementById('chatbotSummaryCol').classList.remove('hidden');
                        document.getElementById('chatbotSummaryText').innerText = profile.conversation_summary;
                        showSummarySection = true;
                    }
                    if (profile.main_problem) {
                        document.getElementById('chatbotProblemCol').classList.remove('hidden');
                        document.getElementById('chatbotProblemText').innerText = profile.main_problem;
                        showSummarySection = true;
                    }
                    if (showSummarySection) {
                        document.getElementById('chatbotSummarySection').classList.remove('hidden');
                    }
                }
                
                lucide.createIcons();
                
            } catch (err) {
                console.error('Error fetching chatbot history:', err);
            }
        }

        // Initialize loader on load
        loadChatbotHistory();
    </script>
</body>
</html>
