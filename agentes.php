<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once 'api/db_config.php';
$is_admin = ($_SESSION['user_role'] ?? 'subscriber') === 'admin';
$crm_user_id = (int)($_SESSION['user_id'] ?? 0);

$can_create_agents = true;
$assigned_agent_ids = [];
try {
    if (!$is_admin && $crm_user_id) {
        try {
            $stmtP = $pdo->prepare("SELECT can_create_agents FROM users WHERE id = ?");
            $stmtP->execute([$crm_user_id]);
            $perm = $stmtP->fetchColumn();
            $can_create_agents = ($perm === false) ? true : (bool)(int)$perm;
        } catch (\Throwable $e) { $can_create_agents = true; }

        try {
            $stmtA = $pdo->prepare("SELECT agent_id FROM agent_subscriptions WHERE user_id = ?");
            $stmtA->execute([$crm_user_id]);
            $assigned_agent_ids = array_column($stmtA->fetchAll(PDO::FETCH_ASSOC), 'agent_id');
        } catch (\Throwable $e) { $assigned_agent_ids = []; }
    }
} catch (\Throwable $e) {}

// === API endpoint para inteligencia comercial ===
$action = $_GET['action'] ?? '';
if ($action === 'intel') {
    header('Content-Type: application/json');
    $agentId = $_GET['agent_id'] ?? '';
    if (!$agentId) { echo json_encode(['error' => 'agent_id required']); exit; }

    $stmt = $pdo->prepare("SELECT cs.id as session_id, cs.domain, cs.message_count, cs.created_at as session_created,
        lp.name, lp.email, lp.phone, lp.company, lp.lead_stage, lp.lead_score, lp.urgency,
        lp.estimated_budget, lp.service_interest, lp.main_problem, lp.conversation_summary
        FROM chat_sessions cs
        LEFT JOIN lead_profiles lp ON lp.session_id = cs.id AND lp.agent_id = ?
        WHERE cs.agent_id = ?
        ORDER BY cs.created_at DESC
        LIMIT 30");
    $stmt->execute([$agentId, $agentId]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $conversations = [];
    foreach ($sessions as $s) {
        $stmt = $pdo->prepare("SELECT role, content, created_at FROM chat_messages WHERE session_id = ? ORDER BY created_at ASC");
        $stmt->execute([$s['session_id']]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT intent, topic, lead_score_delta, next_action, full_metadata, created_at FROM chat_message_metadata WHERE session_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$s['session_id']]);
        $lastMeta = $stmt->fetch(PDO::FETCH_ASSOC);

        $conversations[] = [
            'session_id'         => $s['session_id'],
            'domain'             => $s['domain'],
            'message_count'      => $s['message_count'],
            'session_created'    => $s['session_created'],
            'name'               => $s['name'],
            'email'              => $s['email'],
            'phone'              => $s['phone'],
            'company'            => $s['company'],
            'lead_stage'         => $s['lead_stage'],
            'lead_score'         => $s['lead_score'],
            'urgency'            => $s['urgency'],
            'estimated_budget'   => $s['estimated_budget'],
            'service_interest'   => $s['service_interest'],
            'main_problem'       => $s['main_problem'],
            'conversation_summary' => $s['conversation_summary'],
            'last_intent'        => $lastMeta['intent'] ?? null,
            'last_topic'         => $lastMeta['topic'] ?? null,
            'last_score_delta'   => $lastMeta['lead_score_delta'] ?? 0,
            'last_action'        => $lastMeta['next_action'] ?? null,
            'messages'           => $messages,
        ];
    }

    $stmt = $pdo->prepare("SELECT lead_stage, COUNT(*) as count FROM lead_profiles WHERE agent_id = ? AND lead_stage IS NOT NULL GROUP BY lead_stage");
    $stmt->execute([$agentId]);
    $stageStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT intent, COUNT(*) as count FROM chat_message_metadata WHERE intent IS NOT NULL AND intent != '' AND session_id IN (SELECT id FROM chat_sessions WHERE agent_id = ?) GROUP BY intent ORDER BY count DESC");
    $stmt->execute([$agentId]);
    $intentStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'conversations' => $conversations,
        'stage_stats'   => $stageStats,
        'intent_stats'  => $intentStats,
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agentes Inteligentes - <?php echo htmlspecialchars($crm_name); ?></title>
    <?php if($crm_favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($crm_favicon); ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ultrablue: #0d1e56;
            --sidebar-bg: #0d1e56;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; color: #0f172a; }
        .sidebar-desktop { background: var(--sidebar-bg) !important; border-right: none !important; }
        .sidebar-desktop .brand-logo { background: white; color: var(--ultrablue); }
        .sidebar-desktop .brand-name { color: white !important; }
        .sidebar-desktop .nav-link { color: #94a3b8; }
        .sidebar-desktop .nav-link:hover { background: rgba(255,255,255,.05) !important; color: white !important; }
        .card-shadow { box-shadow: 0 30px 70px -15px rgba(15, 23, 42, 0.08); }
        .sidebar-desktop .nav-link.active { background: #2563eb !important; color: white !important; box-shadow: 0 4px 15px rgba(37,99,235,.3); }
        .sidebar-desktop .logout-btn { color: #94a3b8 !important; border-top: 1px solid rgba(255,255,255,.05); padding-top: 1rem; }
        .sidebar-desktop .logout-btn:hover { background: rgba(239,68,68,.1) !important; color: #f87171 !important; }
        .sidebar-desktop nav::-webkit-scrollbar { width: 4px; }
        .sidebar-desktop nav::-webkit-scrollbar-track { background: transparent; }
        .sidebar-desktop nav::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 2px; }
        .sidebar-desktop nav::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.2); }
        .agent-card { transition: all .2s; }
        .agent-card:hover { transform: translateY(-2px); box-shadow: 0 12px 25px -8px rgba(0,0,0,.08); }
        .tab-btn.active { color: #2563eb; border-bottom: 2px solid #2563eb; }
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #f1f5f9; color: #64748b; }
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

                        <a href="prospectos.php" class="nav-link   flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

                            <i data-lucide="users" class="w-5 h-5"></i>Prospectos</a>

                        <a href="acciones.php" class="nav-link   flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

                            <i data-lucide="list" class="w-5 h-5"></i>Acciones</a>

                        <a href="clientes.php" class="nav-link   flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

                            <i data-lucide="user-check" class="w-5 h-5"></i>Clientes</a>

                        <a href="servicios.php" class="nav-link   flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

                            <i data-lucide="briefcase" class="w-5 h-5"></i>Servicios</a>

                        <a href="agentes.php" class="nav-link active flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

                            <i data-lucide="bot" class="w-5 h-5"></i>Agentes</a>

                        <a href="landings.php" class="nav-link   flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

                            <i data-lucide="rocket" class="w-5 h-5"></i>Landings</a>

                        <a href="marketing.php" class="nav-link   flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

                            <i data-lucide="image" class="w-5 h-5"></i>Material de Mkt</a>
                        <a href="agenda.php" class="nav-link flex items-center gap-4 px-4 py-3 rounded-xl transition-all">
                            <i data-lucide="calendar-check" class="w-5 h-5"></i>Agenda</a>

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

                <a href="prospectos.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                    <i data-lucide="users" class="w-5 h-5"></i>Prospectos</a>

                <a href="acciones.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                    <i data-lucide="list" class="w-5 h-5"></i>Acciones</a>

                <a href="clientes.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                    <i data-lucide="user-check" class="w-5 h-5"></i>Clientes</a>

                <a href="servicios.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                    <i data-lucide="briefcase" class="w-5 h-5"></i>Servicios</a>

                <a href="agentes.php" class="nav-link active flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                    <i data-lucide="bot" class="w-5 h-5"></i>Agentes</a>

                <a href="landings.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                    <i data-lucide="rocket" class="w-5 h-5"></i>Landings</a>

                <a href="marketing.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                    <i data-lucide="image" class="w-5 h-5"></i>Material de Mkt</a>
                <a href="agenda.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl transition-all">
                    <i data-lucide="calendar-check" class="w-5 h-5"></i>Agenda</a>

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

<main class="flex-1 flex flex-col min-w-0 bg-white">
    <header class="h-16 border-b border-slate-100 flex items-center justify-between px-6 lg:px-10 bg-white sticky top-0 z-40">
        <div class="flex items-center gap-4">
            <button onclick="toggleMenu()" class="lg:hidden p-2 text-slate-500"><i data-lucide="menu" class="w-6 h-6"></i></button>
            <h1 class="text-xl font-bold text-slate-900" id="pageTitle">Agentes Inteligentes</h1>
            <button id="backBtn" onclick="showDashboard()" class="hidden text-sm text-blue-600 hover:underline ml-2">&larr; Volver</button>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-sm font-medium text-emerald-700 bg-emerald-50 px-3 py-1 rounded-full hidden sm:block"><?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></span>
        </div>
    </header>

    <!-- DASHBOARD VIEW -->
    <div id="dashboardView" class="flex-1 overflow-y-auto p-6 lg:p-10">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-slate-800">Tus Agentes</h2>
            <?php if ($can_create_agents): ?>
            <button onclick="openCreateModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-medium transition flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nuevo Agente
            </button>
            <?php else: ?>
            <span class="text-xs text-slate-400 bg-slate-100 border border-slate-200 px-3 py-2 rounded-lg flex items-center gap-2">
                <svg class="w-4 h-4 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                Creación de agentes deshabilitada
            </span>
            <?php endif; ?>
        </div>
        <div id="agentsLoading" class="text-center py-16">
            <div class="animate-spin w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full mx-auto mb-3"></div>
            <p class="text-slate-500">Cargando agentes...</p>
        </div>
        <div id="agentsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5"></div>
        <div id="agentsEmpty" class="hidden text-center py-16">
            <svg class="w-16 h-16 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            <h3 class="text-lg font-semibold text-slate-600 mb-2">No hay agentes todavia</h3>
            <p class="text-slate-400 mb-6">Crea tu primer agente conversacional</p>
            <button onclick="openCreateModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-medium transition">Crear Agente</button>
        </div>
    </div>

    <!-- DETAIL VIEW -->
    <div id="detailView" class="flex-1 overflow-y-auto p-6 lg:p-10 hidden">
        <div class="max-w-5xl mx-auto">
            <!-- Tabs -->
            <div class="flex space-x-6 border-b border-slate-200 mb-6">
                <button data-tab="config" class="tab-btn pb-3 font-semibold text-blue-600 border-b-2 border-blue-600">Configuracion</button>
                <button data-tab="knowledge" class="tab-btn pb-3 font-semibold text-slate-500 hover:text-slate-700 transition">Conocimiento</button>
                <button data-tab="domains" class="tab-btn pb-3 font-semibold text-slate-500 hover:text-slate-700 transition">Dominios</button>
                <button data-tab="embed" class="tab-btn pb-3 font-semibold text-slate-500 hover:text-slate-700 transition">Embed</button>
                <button data-tab="intel" class="tab-btn pb-3 font-semibold text-slate-500 hover:text-slate-700 transition">Inteligencia</button>
            </div>

            <!-- TAB: Configuracion -->
            <div id="tabConfig" class="tab-content">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-lg text-slate-800">Configuracion del Agente</h3>
                        <div class="flex items-center gap-3">
                            <span id="detailStatusBadge" class="px-3 py-1 rounded-full text-xs font-semibold">Activo</span>
                            <?php if ($is_admin): ?>
                                <button onclick="deleteCurrentAgent()" class="text-red-500 hover:text-red-700 text-sm font-medium">Eliminar</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Nombre</label>
                            <input type="text" id="cfgName" maxlength="100" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"></div>
                        <div id="modelFieldWrapper"><label class="block text-sm font-medium text-slate-700 mb-1">Modelo IA</label>
                            <select id="cfgModel" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                                <option value="gpt-4o-mini">GPT-4o Mini (OpenAI) ? Rápido y económico</option>
                                <option value="gpt-4o">GPT-4o (OpenAI) ? Máxima calidad</option>
                            </select></div>
                        <div id="elModelInfoPanel" class="hidden"><label class="block text-sm font-medium text-slate-700 mb-1">Modelo de Voz (ElevenLabs)</label>
                            <div class="w-full px-4 py-2.5 border border-blue-200 bg-blue-50 rounded-lg text-sm text-blue-800 flex items-center gap-2">
                                <span>?</span>
                                <span><strong>Gemini 2.5 Flash</strong> ? Gestionado por ElevenLabs</span>
                            </div></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Modo</label>
                            <select id="cfgMode" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                                <option value="preciso">Preciso (RAG TF-IDF)</option>
                                <option value="rapido">Rapido (Prompt completo)</option>
                            </select></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Widget</label>
                            <select id="cfgStyle" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                                <option value="bubble">Bubble (Flotante)</option>
                                <option value="panel">Panel (Lateral)</option>
                            </select></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Modo de Voz</label>
                            <select id="cfgVoice" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" onchange="toggleElevenLabsField()">
                                <option value="none">Sin voz (Solo texto)</option>
                                <option value="elevenlabs">ElevenLabs Conversational AI</option>
                            </select></div>
                        <div id="elevenLabsFieldWrapper" class="hidden">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 flex items-start gap-2">
                                <span style="font-size:18px;">?</span>
                                <div>
                                    <p class="text-sm font-semibold text-blue-800">Integración automática con ElevenLabs</p>
                                    <p class="text-xs text-blue-600 mt-0.5">Al guardar, el CRM crear&aacute; o actualizar&aacute; autom&aacute;ticamente el agente de voz en ElevenLabs usando tu API Key configurada en <a href="configuracion.php" class="underline font-bold">Configuraci&oacute;n</a>.</p>
                                </div>
                            </div>
                            <div id="elStatusBadge" class="hidden mt-2 text-xs font-semibold px-3 py-1.5 rounded-full inline-flex items-center gap-1"></div>
                        </div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Color</label>
                            <div class="flex items-center gap-3">
                                <input type="color" id="cfgColor" value="#2563eb" class="w-10 h-10 rounded cursor-pointer border">
                                <span id="cfgColorText" class="text-sm text-slate-500"></span>
                            </div></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Avatar</label>
                            <div class="flex items-center gap-3">
                                <div id="avatarPreview" class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center overflow-hidden text-sm font-semibold text-slate-500"></div>
                                <input type="file" id="cfgAvatar" accept="image/jpeg,image/png,image/gif,image/webp" class="text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                <button id="removeAvatarBtn" class="text-red-500 hover:text-red-700 text-sm hidden" type="button">Eliminar</button>
                            </div></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Max mensajes/sesion</label>
                            <input type="number" id="cfgMaxMessages" min="1" max="100" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Max tokens</label>
                            <input type="number" id="cfgMaxTokens" min="50" max="2000" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Contexto (mensajes)</label>
                            <input type="number" id="cfgContextMessages" min="5" max="200" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Limite diario</label>
                            <input type="number" id="cfgDailyLimit" min="10" max="10000" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Estado</label>
                            <select id="cfgActive" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select></div>
                        <div class="md:col-span-2"><label class="block text-sm font-medium text-slate-700 mb-1">Prompt del sistema</label>
                            <textarea id="cfgPrompt" rows="6" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none font-mono text-sm"></textarea></div>
                    </div>
                    <div class="mt-6 flex items-center justify-between">
                        <div id="configMsg" class="text-sm hidden"></div>
                        <button onclick="saveConfig()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-medium transition">Guardar Cambios</button>
                    </div>
                </div>
            </div>

            <!-- TAB: Conocimiento -->
            <div id="tabKnowledge" class="tab-content hidden">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                    <div id="kbSyncBanner" class="hidden mb-4 bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span style="font-size:22px;">?</span>
                            <div>
                                <p class="text-sm font-semibold text-blue-800">Base de Conocimiento ? ElevenLabs</p>
                                <p class="text-xs text-blue-600 mt-0.5">Los archivos subidos aquí se vinculan automáticamente al agente de voz de ElevenLabs.</p>
                            </div>
                        </div>
                        <button id="kbSyncBtn" onclick="syncKnowledgeBase()" class="flex-shrink-0 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Sincronizar con ElevenLabs
                        </button>
                    </div>
                    <div id="kbSyncStatus" class="hidden mb-4 text-sm px-4 py-2.5 rounded-lg"></div>
                    <div id="dropZone" class="border-2 border-dashed border-slate-300 rounded-xl p-10 text-center hover:border-blue-400 cursor-pointer transition">
                        <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        <p class="text-slate-500 font-semibold">Arrastra archivos o haz clic para subir</p>
                        <p class="text-xs text-slate-400 mt-1">PDF (max 5MB), TXT y MD (max 1MB)</p>
                        <input type="file" id="fileInput" accept=".txt,.md,.pdf" class="hidden" multiple>
                    </div>
                    <div id="uploadProgress" class="hidden mt-4"><div class="flex items-center gap-3 text-sm text-slate-600"><div class="animate-spin w-4 h-4 border-2 border-blue-600 border-t-transparent rounded-full"></div><span id="uploadStatus">Procesando...</span></div></div>
                    <div id="uploadError" class="hidden mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm"></div>
                    <div id="filesList" class="mt-6 space-y-3">
                        <h4 class="font-semibold text-slate-700 mb-3">Archivos Subidos</h4>
                        <div id="filesEmpty" class="text-center py-8 text-slate-400"><p>No hay archivos subidos</p></div>
                        <div id="filesItems" class="space-y-2 hidden"></div>
                    </div>
                </div>
            </div>

            <!-- TAB: Dominios -->
            <div id="tabDomains" class="tab-content hidden">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                    <p class="text-sm text-slate-600 mb-4">Dominios donde se permite usar este agente.</p>
                    <div class="flex gap-3 mb-6">
                        <input type="text" id="domainInput" placeholder="ej: midominio.com" class="flex-1 px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        <button onclick="addDomain()" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-medium transition">Agregar</button>
                    </div>
                    <div id="domainError" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm mb-4"></div>
                    <div id="domainsList" class="space-y-2">
                        <div id="domainsEmpty" class="text-center py-6 text-slate-400"><p>Sin dominios configurados</p></div>
                        <div id="domainsItems" class="space-y-2 hidden"></div>
                    </div>
                </div>
            </div>

            <!-- TAB: Embed -->
            <div id="tabEmbed" class="tab-content hidden">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                    <h3 class="font-bold text-lg text-slate-800 mb-2">Codigo de Embed</h3>
                    <p class="text-sm text-slate-600 mb-4">Pega esto antes de <code class="bg-slate-100 px-1 rounded">&lt;/body&gt;</code> en tu sitio.</p>
                    <div class="bg-slate-900 text-slate-300 p-5 rounded-xl font-mono text-sm relative">
                        <pre id="embedCode" class="whitespace-pre-wrap break-all"></pre>
                    </div>
                    <div class="mt-4 flex items-center gap-3">
                        <button onclick="copyEmbed()" class="bg-slate-800 hover:bg-slate-700 text-white px-5 py-2.5 rounded-lg font-medium transition flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            Copiar
                        </button>
                        <span id="copyMsg" class="text-sm text-green-600 hidden">Copiado!</span>
                    </div>
                </div>
            </div>

            <!-- TAB: Inteligencia Comercial -->
            <div id="tabIntel" class="tab-content hidden">
                <div id="intelLoading" class="text-center py-8"><div class="animate-spin w-6 h-6 border-2 border-blue-600 border-t-transparent rounded-full mx-auto mb-2"></div><p class="text-sm text-slate-500">Cargando inteligencia...</p></div>
                <div id="intelContent" class="hidden">
                    <div id="intelStats" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6"></div>
                    <h4 class="font-bold text-slate-700 mb-4">Conversaciones</h4>
                    <div id="intelConversations" class="space-y-3"></div>
                </div>
            </div>
        </div>
        <div id="globalError" class="hidden mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm"></div>
    </div>
</main>

<!-- Modal Crear Agente -->
<div id="createModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-slate-800">Nuevo Agente</h3>
            <button onclick="closeCreateModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="createAgentForm" class="space-y-4">
            <div><label class="block text-sm font-medium text-slate-700 mb-1">Nombre</label>
                <input type="text" id="newAgentName" required maxlength="100" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Ej: Soporte Ventas"></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-1">Prompt del sistema</label>
                <textarea id="newAgentPrompt" rows="4" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Eres un asistente experto en...">Eres un asistente util y amable. Respondes preguntas sobre productos y servicios de la empresa.</textarea></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Modelo</label>
                    <select id="newAgentModel" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="gpt-4o-mini" selected>GPT-4o Mini (OpenAI) ? Rápido y económico</option>
                        <option value="gpt-4o">GPT-4o (OpenAI) ? Máxima calidad</option>
                    </select></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Modo</label>
                    <select id="newAgentMode" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="preciso">Preciso (RAG TF-IDF)</option>
                        <option value="rapido">Rapido (Prompt completo)</option>
                    </select></div>
            </div>
            <div id="createError" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm"></div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-lg transition disabled:opacity-50">Crear Agente</button>
        </form>
    </div>
</div>

<!-- === CONFIRM MODAL (reutilizable) === -->
<div id="confirmModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-[60] hidden p-4">
  <div class="bg-white rounded-[2rem] w-full max-w-sm p-8 card-shadow relative overflow-hidden">
    <div class="flex flex-col items-center text-center">
      <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-rose-500 to-red-500 flex items-center justify-center shadow-lg mb-5">
        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
        </svg>
      </div>
      <h3 id="confirmTitle" class="text-xl font-extrabold text-slate-900 mb-2">?Eliminar Agente?</h3>
      <p id="confirmMessage" class="text-sm text-slate-500 mb-6">Esta accion no se puede deshacer.</p>
      <div class="flex gap-3 w-full">
        <button onclick="closeConfirmModal()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-3 px-5 rounded-xl transition-all">Cancelar</button>
        <button id="confirmDeleteBtn" onclick="executeConfirm()" class="flex-1 bg-gradient-to-tr from-rose-500 to-red-500 hover:from-rose-600 hover:to-red-600 text-white font-bold py-3 px-5 rounded-xl shadow-lg transition-all active:scale-[0.97]">Eliminar</button>
      </div>
    </div>
  </div>
</div>

<script>
const API_BASE = 'api/agents.php';
const IS_ADMIN = <?php echo $is_admin ? 'true' : 'false'; ?>;
const CAN_CREATE_AGENTS = <?php echo $can_create_agents ? 'true' : 'false'; ?>;
const CRM_USER_ID = <?php echo $crm_user_id; ?>;
const ASSIGNED_AGENT_IDS = <?php echo json_encode($assigned_agent_ids); ?>;

function api(method, path, body) {
    let url = API_BASE + '?method=' + method + '&path=' + encodeURIComponent(path);
    const opts = { method: 'POST' };
    if (body !== null) {
        if (body instanceof FormData) { opts.body = body; }
        else { opts.headers = { 'Content-Type': 'application/json' }; opts.body = JSON.stringify(body); }
    }
    return fetch(url, opts).then(r => r.json()).catch(e => ({error: 'Error de conexión: ' + e.message}));
}

let currentAgentId = null;
let currentAgentData = null;


// === DASHBOARD ===
function loadAgents() {
    document.getElementById('agentsLoading').classList.remove('hidden');
    document.getElementById('agentsGrid').classList.add('hidden');
    document.getElementById('agentsEmpty').classList.add('hidden');
    api('GET', '/admin/agents').then(data => {
        document.getElementById('agentsLoading').classList.add('hidden');
        if (data.agents && data.agents.length > 0) {
            let visibleAgents = data.agents;
            if (!IS_ADMIN) {
                // Filtrar: solo agentes asignados por admin + propios del usuario
                visibleAgents = data.agents.filter(a => {
                    const isOwn = parseInt(a.owner_crm_user_id) === CRM_USER_ID;
                    const isAssigned = ASSIGNED_AGENT_IDS.includes(a.id);
                    return isOwn || isAssigned;
                });
            }

            if (visibleAgents.length === 0) {
                document.getElementById('agentsEmpty').classList.remove('hidden');
                return;
            }

            const grid = document.getElementById('agentsGrid');
            grid.innerHTML = visibleAgents.map(a => {
                const active = a.is_active == 1;
                const isOwn  = !IS_ADMIN && parseInt(a.owner_crm_user_id) === CRM_USER_ID;
                const canDelete = IS_ADMIN; // Solo admin puede eliminar
                const ownerBadge = IS_ADMIN && a.owner_crm_user_id
                    ? `<span class="text-[10px] bg-violet-100 text-violet-600 px-2 py-0.5 rounded font-semibold">De: ${escapeHtml(a.owner_name || 'Usuario #' + a.owner_crm_user_id)}</span>`
                    : '';
                const deleteBtn = canDelete
                    ? `<button onclick="event.stopPropagation(); confirmDeleteAgent('${a.id}', '${escapeHtml(a.name)}')" class="w-8 h-8 bg-red-50 text-red-500 rounded-lg flex items-center justify-center hover:bg-red-600 hover:text-white transition-all" title="Eliminar agente"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>`
                    : '';
                return `<div class="agent-card bg-white rounded-xl shadow-sm border border-slate-200 p-6 cursor-pointer" onclick="showAgent('${a.id}')">
                    <div class="flex items-start justify-between mb-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-lg">${a.name.charAt(0).toUpperCase()}</div>
                        <div class="flex flex-col items-end gap-1">
                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold ${active ? 'status-active' : 'status-inactive'}">${active ? 'Activo' : 'Inactivo'}</span>
                            ${ownerBadge}
                        </div>
                    </div>
                    <h3 class="font-bold text-slate-800 mb-1">${escapeHtml(a.name)}</h3>
                    <p class="text-xs text-slate-500 mb-3">${a.model}</p>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4 text-xs text-slate-400">
                            <span>${a.files_count || 0} archivos</span>
                            <span>${a.widget_style === 'bubble' ? 'Bubble' : 'Panel'}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <button onclick="event.stopPropagation(); showAgent('${a.id}')" class="w-8 h-8 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all" title="Editar agente"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></button>
                            ${deleteBtn}
                        </div>
                    </div>
                </div>`;
            }).join('');
            grid.classList.remove('hidden');
            lucide.createIcons();
        } else {
            document.getElementById('agentsEmpty').classList.remove('hidden');
        }
    });
}

function escapeHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// === CREATE ===
function openCreateModal() { document.getElementById('createModal').classList.remove('hidden'); }
function closeCreateModal() { document.getElementById('createModal').classList.add('hidden'); }
document.getElementById('createAgentForm').onsubmit = async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const err = document.getElementById('createError');
    btn.disabled = true; err.classList.add('hidden');
    const data = await api('POST', '/admin/agents', {
        name: document.getElementById('newAgentName').value.trim(),
        personality_prompt: document.getElementById('newAgentPrompt').value.trim(),
        model: document.getElementById('newAgentModel').value,
        mode: document.getElementById('newAgentMode').value,
        widget_style: 'panel',
        voice_mode: 'none',
        primary_color: '#2563eb',
        owner_crm_user_id: CRM_USER_ID || null,
    });
    if (data.agent_id) {
        closeCreateModal();
        document.getElementById('createAgentForm').reset();
        loadAgents();
    } else {
        err.textContent = data.error || 'Error al crear';
        err.classList.remove('hidden');
    }
    btn.disabled = false;
};

// === DETAIL VIEW ===
function showAgent(id) {
    currentAgentId = id;
    document.getElementById('dashboardView').classList.add('hidden');
    document.getElementById('detailView').classList.remove('hidden');
    document.getElementById('pageTitle').textContent = 'Configurar Agente';
    document.getElementById('backBtn').classList.remove('hidden');
    loadAgent();
}

function showDashboard() {
    document.getElementById('dashboardView').classList.remove('hidden');
    document.getElementById('detailView').classList.add('hidden');
    document.getElementById('pageTitle').textContent = 'Agentes Inteligentes';
    document.getElementById('backBtn').classList.add('hidden');
    loadAgents();
}

async function loadAgent() {
    const data = await api('GET', '/admin/agents/' + currentAgentId);
    if (data.agent) fillConfig(data.agent);
    loadAgentFiles();
    loadAgentDomains();
    loadEmbed();
}

function fillConfig(a) {
    currentAgentData = a; // guardar para syncKnowledgeBase
    document.getElementById('cfgName').value = a.name || '';
    document.getElementById('cfgPrompt').value = a.personality_prompt || a.system_prompt || '';
    document.getElementById('cfgModel').value = a.model || 'gpt-4o-mini';
    document.getElementById('cfgMode').value = a.mode || 'preciso';
    document.getElementById('cfgStyle').value = a.widget_style || 'panel';
    document.getElementById('cfgVoice').value = a.voice_mode || 'none';
    // Mostrar badge de estado de sincronización
    var badge = document.getElementById('elStatusBadge');
    if (a.elevenlabs_agent_id) {
        badge.className = 'mt-2 text-xs font-semibold px-3 py-1.5 rounded-full inline-flex items-center gap-1 bg-green-100 text-green-700';
        badge.innerHTML = '? Sincronizado con ElevenLabs';
        badge.classList.remove('hidden');
    } else if ((a.voice_mode || 'none') === 'elevenlabs') {
        badge.className = 'mt-2 text-xs font-semibold px-3 py-1.5 rounded-full inline-flex items-center gap-1 bg-yellow-100 text-yellow-700';
        badge.innerHTML = '?? Sin sincronizar aún ? Guarda para activar';
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
    toggleElevenLabsField();
    // Mostrar/ocultar banner de KB-ElevenLabs
    const kbBanner = document.getElementById('kbSyncBanner');
    if (kbBanner) {
        if ((a.voice_mode || 'none') === 'elevenlabs' && a.elevenlabs_agent_id) {
            kbBanner.classList.remove('hidden');
        } else {
            kbBanner.classList.add('hidden');
        }
    }
    document.getElementById('cfgColor').value = a.primary_color || a.widget_color || '#2563eb';
    document.getElementById('cfgColorText').textContent = a.primary_color || a.widget_color || '#2563eb';
    document.getElementById('cfgMaxMessages').value = a.max_messages_per_session || a.max_messages || 30;
    document.getElementById('cfgMaxTokens').value = a.max_tokens_response || a.max_tokens || 500;
    document.getElementById('cfgContextMessages').value = a.context_messages || 50;
    document.getElementById('cfgDailyLimit').value = a.daily_message_limit || 500;
    document.getElementById('cfgActive').value = a.is_active ? '1' : '0';
    const statusBadge = document.getElementById('detailStatusBadge');
    if (a.is_active == 1) { statusBadge.textContent = 'Activo'; statusBadge.className = 'status-active px-3 py-1 rounded-full text-xs font-semibold'; }
    else { statusBadge.textContent = 'Inactivo'; statusBadge.className = 'status-inactive px-3 py-1 rounded-full text-xs font-semibold'; }
    updateAvatarPreview(a.avatar);
}

async function syncKnowledgeBase() {
    const btn = document.getElementById('kbSyncBtn');
    const statusEl = document.getElementById('kbSyncStatus');
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Sincronizando...';
    statusEl.className = 'hidden';
    try {
        const data = await api('POST', '/admin/agents/' + currentAgentId + '/kb-sync');
        if (data.success) {
            statusEl.textContent = '? Sincronización exitosa: ' + data.synced + ' archivo(s) subido(s) a ElevenLabs, ' + data.total_docs + ' doc(s) vinculados al agente.';
            statusEl.className = 'mb-4 text-sm px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-700';
        } else {
            statusEl.textContent = '? Error: ' + (data.error || 'Error desconocido');
            statusEl.className = 'mb-4 text-sm px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-700';
        }
    } catch(e) {
        statusEl.textContent = '? Error de conexión al sincronizar';
        statusEl.className = 'mb-4 text-sm px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-700';
    }
    btn.disabled = false;
    btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Sincronizar con ElevenLabs';
}

function updateAvatarPreview(avatarPath) {
    const preview = document.getElementById('avatarPreview');
    const removeBtn = document.getElementById('removeAvatarBtn');
    if (avatarPath) {
        preview.innerHTML = '<img src="' + avatarPath + '" class="w-full h-full object-cover">';
        removeBtn.classList.remove('hidden');
    } else {
        preview.innerHTML = '<span class="text-sm font-semibold text-slate-500">?</span>';
        removeBtn.classList.add('hidden');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const avatarInput = document.getElementById('cfgAvatar');
    if (avatarInput) {
        avatarInput.addEventListener('change', async function() {
            if (!this.files || !this.files[0]) return;
            const form = new FormData();
            form.append('avatar', this.files[0]);
            try {
                const data = await api('POST', '/admin/agents/' + currentAgentId + '/avatar', form);
                if (data.success) {
                    showMsg('configMsg', 'Avatar actualizado!', 'text-green-600');
                    updateAvatarPreview(data.avatar_url);
                } else {
                    showMsg('configMsg', data.error || 'Error al subir', 'text-red-600');
                }
            } catch(e) {
                showMsg('configMsg', 'Error de conexion', 'text-red-600');
            }
            this.value = '';
        });
    }

    const removeBtn = document.getElementById('removeAvatarBtn');
    if (removeBtn) {
        removeBtn.addEventListener('click', async function() {
            try {
                const data = await api('DELETE', '/admin/agents/' + currentAgentId + '/avatar');
                if (data.success) {
                    showMsg('configMsg', 'Avatar eliminado', 'text-green-600');
                    updateAvatarPreview(null);
                } else {
                    showMsg('configMsg', data.error || 'Error', 'text-red-600');
                }
            } catch(e) {
                showMsg('configMsg', 'Error de conexion', 'text-red-600');
            }
        });
    }
});

function showMsg(id, text, cls) {
    const el = document.getElementById(id);
    el.textContent = text;
    el.className = 'text-sm ' + cls;
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 3000);
}

function toggleElevenLabsField() {
    var v = document.getElementById('cfgVoice').value;
    var isEl = (v === 'elevenlabs');
    // Panel de info ElevenLabs (auto-sync)
    document.getElementById('elevenLabsFieldWrapper').classList.toggle('hidden', !isEl);
    // Campo Modelo IA: solo aplica para chat de texto (OpenAI), no para voz ElevenLabs
    document.getElementById('modelFieldWrapper').classList.toggle('hidden', isEl);
    document.getElementById('elModelInfoPanel').classList.toggle('hidden', !isEl);
}

async function saveConfig() {
    const data = await api('PUT', '/admin/agents/' + currentAgentId, {
        name: document.getElementById('cfgName').value.trim(),
        personality_prompt: document.getElementById('cfgPrompt').value.trim(),
        model: document.getElementById('cfgModel').value,
        mode: document.getElementById('cfgMode').value,
        widget_style: document.getElementById('cfgStyle').value,
        voice_mode: document.getElementById('cfgVoice').value,
        primary_color: document.getElementById('cfgColor').value,
        max_messages_per_session: parseInt(document.getElementById('cfgMaxMessages').value),
        max_tokens_response: parseInt(document.getElementById('cfgMaxTokens').value),
        context_messages: parseInt(document.getElementById('cfgContextMessages').value),
        daily_message_limit: parseInt(document.getElementById('cfgDailyLimit').value),
        is_active: document.getElementById('cfgActive').value === '1',
    });
    const msg = document.getElementById('configMsg');
    if (data.success) {
        if (data.warning) {
            msg.textContent = '?? Guardado ? pero ElevenLabs no pudo sincronizarse: ' + data.warning;
            msg.className = 'text-sm text-yellow-600';
        } else if (data.elevenlabs_synced) {
            msg.textContent = '? Guardado y sincronizado con ElevenLabs';
            msg.className = 'text-sm text-green-600';
        } else {
            msg.textContent = '\u2705 Configuraci\u00f3n guardada';
            msg.className = 'text-sm text-green-600';
        }
        msg.classList.remove('hidden');
        setTimeout(() => msg.classList.add('hidden'), 4000);
        // Recargar para actualizar el badge de estado
        await loadAgentDetail(currentAgentId);
    } else {
        msg.textContent = data.error || 'Error al guardar';
        msg.className = 'text-sm text-red-600';
        msg.classList.remove('hidden');
        setTimeout(() => msg.classList.add('hidden'), 4000);
    }
}

async function deleteCurrentAgent() {
    const ok = await showConfirm('?Eliminar Agente?', 'Este agente se eliminara permanentemente junto con todos sus archivos y sesiones.');
    if (!ok) return;
    await api('DELETE', '/admin/agents/' + currentAgentId);
    showDashboard();
}

// === CONFIRM MODAL (reutilizable) ===
let _confirmCallback = null;

function showConfirm(title, message) {
    return new Promise(resolve => {
        document.getElementById('confirmTitle').textContent = title;
        document.getElementById('confirmMessage').textContent = message;
        _confirmCallback = resolve;
        document.getElementById('confirmModal').classList.remove('hidden');
    });
}

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.add('hidden');
    if (_confirmCallback) { _confirmCallback(false); _confirmCallback = null; }
}

function executeConfirm() {
    document.getElementById('confirmModal').classList.add('hidden');
    if (_confirmCallback) { _confirmCallback(true); _confirmCallback = null; }
}

document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) closeConfirmModal();
});

async function confirmDeleteAgent(agentId, agentName) {
    const ok = await showConfirm('?Eliminar Agente?', 'El agente "' + agentName + '" se eliminara permanentemente. Esta accion no se puede deshacer.');
    if (!ok) return;
    await api('DELETE', '/admin/agents/' + agentId);
    loadAgents();
}

// === FILES ===
async function loadAgentFiles() {
    const data = await api('GET', '/admin/agents/' + currentAgentId + '/files');
    const empty = document.getElementById('filesEmpty');
    const items = document.getElementById('filesItems');
    if (data.files && data.files.length > 0) {
        empty.classList.add('hidden');
        items.classList.remove('hidden');
        
        const isElevenLabs = (currentAgentData && currentAgentData.voice_mode === 'elevenlabs');

        items.innerHTML = data.files.map(f => {
            let badgeHtml = '';
            if (isElevenLabs) {
                if (f.elevenlabs_doc_id) {
                    badgeHtml = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">? Sincronizado</span>`;
                } else {
                    badgeHtml = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800" title="Guarda el agente o haz clic en Sincronizar para subirlo a ElevenLabs">?? Pendiente</span>`;
                }
            }
            return `<div class="flex items-center justify-between bg-slate-50 px-4 py-3 rounded-lg">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    <span class="text-sm font-medium text-slate-700">${f.original_filename}</span>
                    <span class="text-xs text-slate-400">${(f.filesize/1024).toFixed(1)} KB</span>
                    ${badgeHtml}
                </div>
                <button onclick="deleteFile('${f.id}')" class="text-red-500 hover:text-red-700 text-sm">Eliminar</button>
            </div>`;
        }).join('');
    } else {
        empty.classList.remove('hidden');
        items.classList.add('hidden');
    }
}

async function deleteFile(id) {
    await api('DELETE', '/admin/agents/' + currentAgentId + '/files/' + id);
    loadAgentFiles();
}

const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
dropZone.onclick = () => fileInput.click();
dropZone.ondragover = (e) => { e.preventDefault(); dropZone.classList.add('border-blue-500'); };
dropZone.ondragleave = () => dropZone.classList.remove('border-blue-500');
dropZone.ondrop = (e) => { e.preventDefault(); dropZone.classList.remove('border-blue-500'); if (e.dataTransfer.files.length) uploadFiles(e.dataTransfer.files); };
fileInput.onchange = () => { if (fileInput.files.length) uploadFiles(fileInput.files); };

async function uploadFiles(files) {
    const progress = document.getElementById('uploadProgress');
    const status = document.getElementById('uploadStatus');
    const error = document.getElementById('uploadError');
    progress.classList.remove('hidden'); error.classList.add('hidden');
    for (const file of files) {
        status.textContent = 'Subiendo ' + file.name + '...';
        const fd = new FormData();
        fd.append('file', file);
        const data = await api('POST', '/admin/agents/' + currentAgentId + '/upload', fd);
        if (data.file) { status.textContent = file.name + ' listo'; }
        else { error.textContent = data.error || 'Error en ' + file.name; error.classList.remove('hidden'); }
    }
    progress.classList.add('hidden');
    loadAgentFiles();
}

// === DOMAINS ===
async function loadAgentDomains() {
    const data = await api('GET', '/admin/agents/' + currentAgentId + '/domains');
    const empty = document.getElementById('domainsEmpty');
    const items = document.getElementById('domainsItems');
    if (data.domains && data.domains.length > 0) {
        empty.classList.add('hidden'); items.classList.remove('hidden');
        items.innerHTML = data.domains.map(d => `<div class="flex items-center justify-between bg-slate-50 px-4 py-3 rounded-lg">
            <span class="text-sm text-slate-700">${d.domain}</span>
            <button onclick="deleteDomain('${d.id}')" class="text-red-500 hover:text-red-700 text-sm">Eliminar</button></div>`).join('');
    } else { empty.classList.remove('hidden'); items.classList.add('hidden'); }
}

async function addDomain() {
    const input = document.getElementById('domainInput');
    const err = document.getElementById('domainError');
    err.classList.add('hidden');
    if (!input.value.trim()) return;
    const data = await api('POST', '/admin/agents/' + currentAgentId + '/domains', { domain: input.value.trim() });
    if (data.success) { input.value = ''; loadAgentDomains(); }
    else { err.textContent = data.error || 'Error'; err.classList.remove('hidden'); }
}

async function deleteDomain(id) {
    await api('DELETE', '/admin/agents/' + currentAgentId + '/domains/' + id);
    loadAgentDomains();
}

// === EMBED ===
async function loadEmbed() {
    const data = await api('GET', '/admin/agents/' + currentAgentId + '/embed');
    if (data.script) document.getElementById('embedCode').textContent = data.script;
}

function copyEmbed() {
    const code = document.getElementById('embedCode').textContent;
    navigator.clipboard.writeText(code);
    const msg = document.getElementById('copyMsg');
    msg.classList.remove('hidden');
    setTimeout(() => msg.classList.add('hidden'), 2000);
}

// === TABS ===
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(b => { b.classList.remove('text-blue-600', 'border-blue-600'); b.classList.add('text-slate-500', 'border-transparent'); });
        this.classList.add('text-blue-600', 'border-blue-600');
        this.classList.remove('text-slate-500', 'border-transparent');
        document.querySelectorAll('.tab-content').forEach(t => t.classList.add('hidden'));
        var tabId = 'tab' + this.dataset.tab.charAt(0).toUpperCase() + this.dataset.tab.slice(1);
        document.getElementById(tabId).classList.remove('hidden');
        if (this.dataset.tab === 'intel') loadIntel();
    });
});

// === INTELIGENCIA ===
function loadIntel() {
    document.getElementById('intelLoading').classList.remove('hidden');
    document.getElementById('intelContent').classList.add('hidden');
    fetch('agentes.php?action=intel&agent_id=' + currentAgentId)
      .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(data => {
          document.getElementById('intelLoading').classList.add('hidden');
          document.getElementById('intelContent').classList.remove('hidden');
          renderIntelStats(data);
          renderIntelConversations(data.conversations);
      })
      .catch(function(err) {
          document.getElementById('intelLoading').classList.add('hidden');
          document.getElementById('intelStats').innerHTML = '<div class="col-span-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">Error: ' + err.message + '</div>';
          document.getElementById('intelConversations').innerHTML = '';
      });
}

function renderIntelStats(data) {
    var stageLabels = { 'new':'Nuevo', 'cold':'Frio', 'warm':'Tibio', 'hot':'Caliente', 'qualified':'Calificado', 'closed':'Cerrado' };
    var intentLabels = {
        'unknown':'Sin clasificar', 'pricing_question':'Consulta de precios', 'lead_capture':'Captura de lead',
        'service_interest':'Interes en servicio', 'general_question':'Consulta general', 'greeting':'Saludo',
        'goodbye':'Despedida', 'complaint':'Queja', 'human_request':'Solicita humano',
        'spam_or_abuse':'Spam/Abuso', 'other':'Otro'
    };
    var html = '';
    var stats = [
        { label: 'Total Sesiones', value: data.conversations ? data.conversations.length : 0, color: 'text-blue-600', bg: 'bg-blue-50' },
        { label: 'Con Clasificacion', value: data.conversations ? data.conversations.filter(function(c) { return c.last_intent && c.last_intent !== 'unknown'; }).length : 0, color: 'text-purple-600', bg: 'bg-purple-50' },
        { label: 'Lead Stages', value: data.stage_stats ? data.stage_stats.map(function(s) { return (stageLabels[s.lead_stage] || s.lead_stage) + '=' + s.count; }).join(', ') : '-', color: 'text-emerald-600', bg: 'bg-emerald-50' },
        { label: 'Intenciones', value: data.intent_stats ? data.intent_stats.map(function(s) { return (intentLabels[s.intent] || s.intent) + '=' + s.count; }).join(', ') : '-', color: 'text-amber-600', bg: 'bg-amber-50' },
    ];
    stats.forEach(function(s) {
        html += '<div class="' + s.bg + ' rounded-xl p-4"><p class="text-xs font-semibold uppercase tracking-wide ' + s.color + '">' + s.label + '</p><p class="text-lg font-bold text-slate-800 mt-1">' + s.value + '</p></div>';
    });
    document.getElementById('intelStats').innerHTML = html;
}

function renderIntelConversations(conversations) {
    var stageLabels = { 'new':'Nuevo', 'cold':'Frio', 'warm':'Tibio', 'hot':'Caliente', 'qualified':'Calificado', 'closed':'Cerrado' };
    var intentLabels = {
        'unknown':'Sin clasificar', 'pricing_question':'Consulta de precios', 'lead_capture':'Captura de lead',
        'service_interest':'Interes en servicio', 'general_question':'Consulta general', 'greeting':'Saludo',
        'goodbye':'Despedida', 'complaint':'Queja', 'human_request':'Solicita humano',
        'spam_or_abuse':'Spam/Abuso', 'other':'Otro'
    };
    var container = document.getElementById('intelConversations');
    if (!conversations || conversations.length === 0) {
        container.innerHTML = '<div class="text-center py-8 text-slate-400"><p>Sin conversaciones aun</p></div>';
        return;
    }
    container.innerHTML = conversations.map(function(c) {
        var contact = [c.name, c.email, c.phone].filter(Boolean).join(', ') || 'Anonimo';
        var stage = c.lead_stage || '-';
        var score = c.lead_score || 0;
        var summary = c.conversation_summary || c.main_problem || 'Sin conclusion';
        var intentColor = c.last_intent && c.last_intent !== 'unknown' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500';
        var messagesHtml = '';
        if (c.messages && c.messages.length > 0) {
            messagesHtml = c.messages.map(function(m) {
                var align = m.role === 'user' ? 'ml-8' : 'mr-8';
                var bg = m.role === 'user' ? 'bg-blue-50 border-blue-100' : 'bg-slate-50 border-slate-200';
                var label = m.role === 'user' ? 'Usuario' : 'Agente';
                return '<div class="' + align + ' mb-2"><div class="' + bg + ' border rounded-lg px-3 py-2"><p class="text-[10px] font-semibold text-slate-400 uppercase mb-1">' + label + '</p><p class="text-xs text-slate-700 whitespace-pre-wrap">' + escapeHtml(m.content) + '</p></div></div>';
            }).join('');
        } else {
            messagesHtml = '<p class="text-xs text-slate-400 text-center py-2">Sin mensajes</p>';
        }
        var sid = 'chat-' + c.session_id;
        return '<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">' +
            '<div onclick="document.getElementById(\'' + sid + '\').classList.toggle(\'hidden\'); this.querySelector(\'.chevron\').classList.toggle(\'rotate-180\')" class="p-4 cursor-pointer hover:bg-slate-50 flex items-start gap-3 transition">' +
                '<div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center shrink-0 text-xs font-bold text-slate-500">#' + c.session_id + '</div>' +
                '<div class="flex-1 min-w-0">' +
                    '<div class="flex items-center gap-2 flex-wrap">' +
                        '<span class="text-sm font-bold text-slate-800">' + escapeHtml(contact) + '</span>' +
                        '<span class="text-[10px] px-2 py-0.5 rounded-full font-medium ' + stageBadge(stage) + '">' + (stageLabels[stage] || stage) + '</span>' +
                        '<span class="text-[10px] font-medium text-slate-400">Score: ' + score + '</span>' +
                    '</div>' +
                    '<div class="mt-1 flex items-center gap-2 text-[11px] text-slate-400">' +
                        '<span class="' + intentColor + ' px-1.5 py-0.5 rounded text-[10px] font-medium">' + (intentLabels[c.last_intent] || c.last_intent || 'Sin clasificar') + '</span>' +
                        (c.domain ? '<span>' + escapeHtml(c.domain) + '</span>' : '') +
                        '<span>' + (c.session_created || '').split(' ')[0] + '</span>' +
                    '</div>' +
                    '<p class="mt-2 text-xs text-slate-600 line-clamp-2">' + escapeHtml(summary) + '</p>' +
                '</div>' +
                '<svg class="chevron w-5 h-5 text-slate-400 shrink-0 mt-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>' +
            '</div>' +
            '<div id="' + sid + '" class="hidden border-t border-slate-100 p-4 bg-slate-50/50">' +
                messagesHtml +
            '</div>' +
        '</div>';
    }).join('');
}

function stageBadge(stage) {
    var m = { 'hot':'bg-orange-100 text-orange-700', 'warm':'bg-amber-100 text-amber-700', 'qualified':'bg-green-100 text-green-700', 'cold':'bg-blue-100 text-blue-700', 'new':'bg-slate-100 text-slate-500', 'closed':'bg-gray-100 text-gray-500' };
    return m[stage] || 'bg-slate-100 text-slate-500';
}

// === UI ===
function toggleMenu() { document.getElementById('mobileMenu').classList.toggle('hidden'); }
async function logout() { fetch('api/auth.php?action=logout').then(() => window.location.href = 'login.php'); }
lucide.createIcons();

// Init
loadAgents();
</script>
</body>
</html>
