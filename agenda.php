<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
include 'api/config.php';
$is_admin = ($_SESSION['user_role'] ?? 'subscriber') === 'admin';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda - <?php echo htmlspecialchars($crm_name); ?></title>
    <?php if(isset($crm_favicon) && $crm_favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($crm_favicon); ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --ultrablue: #0d1e56; --sidebar-bg: #0d1e56; --accent-blue: #2563eb; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; color: #0f172a; }
        .sidebar-desktop { background: var(--sidebar-bg) !important; border-right: none !important; }
        .sidebar-desktop .brand-logo { background: white; color: var(--ultrablue); }
        .sidebar-desktop .brand-name { color: white !important; }
        .sidebar-desktop .nav-link { color: #94a3b8; }
        .sidebar-desktop .nav-link:hover { background: rgba(255,255,255,.05) !important; color: white !important; }
        .sidebar-desktop .nav-link.active { background: #2563eb !important; color: white !important; box-shadow: 0 4px 15px rgba(37,99,235,.3); }
        .sidebar-desktop .logout-btn { color: #94a3b8 !important; border-top: 1px solid rgba(255,255,255,.05); padding-top: 1rem; }
        .sidebar-desktop .logout-btn:hover { background: rgba(239,68,68,.1) !important; color: #f87171 !important; }
        .top-tab { padding: .6rem 1.1rem; border-radius: .75rem; font-weight: 700; font-size: .78rem; color: #64748b; transition: all .15s; }
        .top-tab.active { background: #2563eb; color: white; box-shadow: 0 4px 12px rgba(37,99,235,.25); }
        .cfg-pill { padding: .45rem .9rem; border-radius: 9999px; font-weight: 700; font-size: .7rem; color: #475569; background: #f1f5f9; white-space: nowrap; transition: all .15s; }
        .cfg-pill.active { background: #2563eb; color: white; box-shadow: 0 4px 12px rgba(37,99,235,.25); }
        .card { background: white; border: 1px solid #e2e8f0; border-radius: 1.5rem; box-shadow: 0 1px 2px rgba(15,23,42,.04); }
        .card-accent { border-left: 4px solid var(--accent, #2563eb); }
        table.simple th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; font-weight: 800; padding: .6rem .75rem; border-bottom: 1px solid #e2e8f0; }
        table.simple td { padding: .65rem .75rem; font-size: .8rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        table.simple tbody tr:last-child td { border-bottom: none; }
        .field-label { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; margin-bottom: .35rem; display: block; }
        .field-input { width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: .85rem; padding: .6rem .9rem; font-size: .82rem; outline: none; transition: all .15s; }
        .field-input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); background: white; }
        .btn-primary { background: #2563eb; color: white; font-weight: 700; border-radius: .85rem; padding: .6rem 1.2rem; font-size: .78rem; transition: all .15s; box-shadow: 0 2px 8px rgba(37,99,235,.2); }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: #f1f5f9; color: #475569; font-weight: 700; border-radius: .85rem; padding: .6rem 1.2rem; font-size: .78rem; transition: all .15s; }
        .btn-secondary:hover { background: #e2e8f0; }
        .btn-danger { background: #fef2f2; color: #dc2626; font-weight: 700; border-radius: .6rem; padding: .35rem .7rem; font-size: .72rem; transition: all .15s; }
        .btn-danger:hover { background: #fee2e2; }
        .slot-btn.selected { background: #2563eb !important; color: white !important; border-color: #2563eb !important; }
        .modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,.4); backdrop-filter: blur(2px); z-index: 200; display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .breadcrumb button { font-weight: 700; transition: color .15s; }
        .badge { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .6rem; border-radius: 9999px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; }
        .badge-ok { background: #ecfdf5; color: #059669; }
        .badge-off { background: #f1f5f9; color: #94a3b8; }
        .badge-warn { background: #fffbeb; color: #d97706; }
        .day-chip { border: 1px solid #e2e8f0; border-radius: .85rem; padding: .55rem .7rem; background: white; }
        .day-chip.day-off { background: #f8fafc; opacity: .65; }
        .day-chip .day-name { font-weight: 700; font-size: .72rem; color: #1e293b; }
    </style>
</head>
<body class="flex min-h-screen">

<!-- Mobile Menu -->
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
            <a href="index.php" class="nav-link flex items-center gap-4 px-4 py-3 rounded-xl transition-all"><i data-lucide="layout-dashboard" class="w-5 h-5"></i>Dashboard</a>
            <a href="prospectos.php" class="nav-link flex items-center gap-4 px-4 py-3 rounded-xl transition-all"><i data-lucide="users" class="w-5 h-5"></i>Prospectos</a>
            <a href="acciones.php" class="nav-link flex items-center gap-4 px-4 py-3 rounded-xl transition-all"><i data-lucide="list" class="w-5 h-5"></i>Acciones</a>
            <a href="clientes.php" class="nav-link flex items-center gap-4 px-4 py-3 rounded-xl transition-all"><i data-lucide="user-check" class="w-5 h-5"></i>Clientes</a>
            <a href="servicios.php" class="nav-link flex items-center gap-4 px-4 py-3 rounded-xl transition-all"><i data-lucide="briefcase" class="w-5 h-5"></i>Servicios</a>
            <a href="agentes.php" class="nav-link flex items-center gap-4 px-4 py-3 rounded-xl transition-all"><i data-lucide="bot" class="w-5 h-5"></i>Agentes</a>
            <a href="landings.php" class="nav-link flex items-center gap-4 px-4 py-3 rounded-xl transition-all"><i data-lucide="rocket" class="w-5 h-5"></i>Landings</a>
            <a href="marketing.php" class="nav-link flex items-center gap-4 px-4 py-3 rounded-xl transition-all"><i data-lucide="image" class="w-5 h-5"></i>Material de Mkt</a>
            <a href="agenda.php" class="nav-link active flex items-center gap-4 px-4 py-3 rounded-xl transition-all"><i data-lucide="calendar-check" class="w-5 h-5"></i>Agenda</a>
            <?php if ($is_admin): ?>
            <a href="usuarios.php" class="nav-link flex items-center gap-4 px-4 py-3 rounded-xl transition-all"><i data-lucide="users" class="w-5 h-5"></i>Usuarios</a>
            <a href="configuracion.php" class="nav-link flex items-center gap-4 px-4 py-3 rounded-xl transition-all"><i data-lucide="settings" class="w-5 h-5"></i>Configuración</a>
            <?php endif; ?>
            <a href="perfil.php" class="nav-link flex items-center gap-4 px-4 py-3 rounded-xl transition-all"><i data-lucide="user" class="w-5 h-5"></i>Mi Perfil</a>
        </nav>
        <div class="pt-4 mt-auto">
            <button onclick="logout()" class="logout-btn w-full flex items-center gap-4 px-4 py-3 rounded-xl transition-all font-bold"><i data-lucide="log-out" class="w-5 h-5"></i>Cerrar Sesión</button>
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
        <a href="index.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl transition-all"><i data-lucide="layout-dashboard" class="w-5 h-5"></i>Dashboard</a>
        <a href="prospectos.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl transition-all"><i data-lucide="users" class="w-5 h-5"></i>Prospectos</a>
        <a href="acciones.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl transition-all"><i data-lucide="list" class="w-5 h-5"></i>Acciones</a>
        <a href="clientes.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl transition-all"><i data-lucide="user-check" class="w-5 h-5"></i>Clientes</a>
        <a href="servicios.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl transition-all"><i data-lucide="briefcase" class="w-5 h-5"></i>Servicios</a>
        <a href="agentes.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl transition-all"><i data-lucide="bot" class="w-5 h-5"></i>Agentes</a>
        <a href="landings.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl transition-all"><i data-lucide="rocket" class="w-5 h-5"></i>Landings</a>
        <a href="marketing.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl transition-all"><i data-lucide="image" class="w-5 h-5"></i>Material de Mkt</a>
        <a href="agenda.php" class="nav-link active flex items-center gap-3 px-4 py-3 rounded-xl transition-all"><i data-lucide="calendar-check" class="w-5 h-5"></i>Agenda</a>
        <?php if ($is_admin): ?>
        <a href="usuarios.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl transition-all"><i data-lucide="users" class="w-5 h-5"></i>Usuarios</a>
        <a href="configuracion.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl transition-all"><i data-lucide="settings" class="w-5 h-5"></i>Configuración</a>
        <?php endif; ?>
        <a href="perfil.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl transition-all"><i data-lucide="user" class="w-5 h-5"></i>Mi Perfil</a>
    </nav>
    <div class="px-4 pb-8">
        <button onclick="logout()" class="logout-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all font-bold"><i data-lucide="log-out" class="w-5 h-5"></i>Cerrar Sesión</button>
    </div>
</aside>

<main class="flex-1 flex flex-col min-w-0 bg-white">
    <header class="h-16 border-b border-slate-100 flex items-center justify-between px-6 lg:px-10 bg-white sticky top-0 z-40">
        <div class="flex items-center gap-4">
            <button onclick="toggleMenu()" class="lg:hidden p-2 text-slate-500"><i data-lucide="menu" class="w-6 h-6"></i></button>
            <h1 class="text-xl font-bold text-slate-900">Agenda</h1>
        </div>
        <div class="flex gap-2 overflow-x-auto">
            <button id="tabBtn-resumen" class="top-tab active" onclick="showTab('resumen')">Resumen</button>
            <button id="tabBtn-turnos" class="top-tab" onclick="showTab('turnos')">Turnos</button>
            <button id="tabBtn-consolidada" class="top-tab" onclick="showTab('consolidada')">Agenda consolidada</button>
            <button id="tabBtn-external-agents" class="top-tab" onclick="showTab('external-agents')">Agentes externos</button>
            <?php if ($is_admin): ?>
            <button id="tabBtn-config" class="top-tab" onclick="showTab('config')">Configuración</button>
            <?php endif; ?>
        </div>
    </header>

    <div class="p-6 lg:p-10 max-w-6xl mx-auto w-full space-y-6">

        <!-- ============ RESUMEN GENERAL ============ -->
        <div id="panel-resumen" class="space-y-6"></div>

        <!-- ============ TURNOS ============ -->
        <div id="panel-turnos" class="hidden">
            <div class="rounded-[1.75rem] p-6 mb-6 text-white shadow-xl" style="background:linear-gradient(120deg,#0f172a,#1e3a8a 55%,#2563eb)">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/10 border border-white/10 mb-2">
                            <span class="relative flex h-1.5 w-1.5"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-400"></span></span>
                            Agenda en vivo
                        </div>
                        <h2 class="text-xl font-black">Turnos y reservas</h2>
                        <p class="text-blue-200 text-xs mt-0.5">Vista consolidada de todos tus recursos y sucursales.</p>
                    </div>
                    <div class="flex gap-3" id="turnosStats"></div>
                </div>
            </div>
            <div class="card p-6 mb-6">
                <div class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label class="field-label">Recurso</label>
                        <select id="filterResource" class="field-input" onchange="loadBookings()"><option value="">Todos</option></select>
                    </div>
                    <div>
                        <label class="field-label">Estado</label>
                        <select id="filterStatus" class="field-input" onchange="loadBookings()">
                            <option value="">Todos</option>
                            <option value="held">Pendiente (held)</option>
                            <option value="confirmed">Confirmada</option>
                            <option value="rescheduled">Reprogramada</option>
                            <option value="cancelled">Cancelada</option>
                            <option value="completed">Completada</option>
                            <option value="no_show">No asistió</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Desde</label>
                        <input type="date" id="filterFrom" class="field-input" onchange="loadBookings()">
                    </div>
                    <div>
                        <label class="field-label">Hasta</label>
                        <input type="date" id="filterTo" class="field-input" onchange="loadBookings()">
                    </div>
                    <button class="btn-primary ml-auto flex items-center gap-2" onclick="openManualBookingModal()"><i data-lucide="plus" class="w-4 h-4"></i>Nueva reserva</button>
                </div>
            </div>
            <div class="card overflow-x-auto">
                <table class="simple w-full">
                    <thead><tr><th>Fecha / Hora</th><th>Recurso</th><th>Servicio</th><th>Contacto</th><th>Estado</th><th>Asistencia</th><th></th></tr></thead>
                    <tbody id="bookingsBody"><tr><td colspan="7" class="text-center py-10 text-slate-400">Cargando...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- ============ CONFIGURACIÓN ============ -->
        <div id="panel-config" class="hidden space-y-6">
            <div class="flex flex-wrap gap-2" id="cfgPills"></div>

            <!-- Sucursales → Recursos → Detalle (navegación jerárquica) -->
            <div id="cfg-branches" class="cfg-panel space-y-6">
                <div class="breadcrumb flex items-center gap-2 text-sm text-slate-400" id="drillBreadcrumb"></div>
                <div id="drillBody" class="space-y-6"></div>
            </div>

            <!-- Enlaces de reserva -->
            <div id="cfg-links" class="cfg-panel hidden space-y-6">
                <div id="generalLinkCard"></div>
                <div class="card p-6">
                    <h3 class="font-black text-slate-900 mb-1">Todos los enlaces de reserva</h3>
                    <p class="text-xs text-slate-500 mb-4">El general del negocio y el dedicado de cada recurso se generan solos. Acá también podés crear enlaces adicionales (ej. con un canal de origen distinto).</p>
                    <form id="linkForm" class="grid md:grid-cols-4 gap-4">
                        <div><label class="field-label">Sucursal (opcional)</label><select class="field-input" name="branch_id" id="linkBranchSelect"><option value="">Cualquiera</option></select></div>
                        <div><label class="field-label">Recurso (opcional)</label><select class="field-input" name="resource_id" id="linkResourceSelect"><option value="">Cualquiera</option></select></div>
                        <div><label class="field-label">Servicio (opcional)</label><select class="field-input" name="service_id" id="linkServiceSelect"><option value="">Cualquiera</option></select></div>
                        <div><label class="field-label">Canal de origen</label><input class="field-input" name="source_channel" placeholder="ej. whatsapp, instagram"></div>
                        <div class="md:col-span-4"><button type="submit" class="btn-primary">Generar enlace adicional</button></div>
                    </form>
                </div>
                <div class="card overflow-x-auto">
                    <table class="simple w-full"><thead><tr><th>Enlace</th><th>Contexto</th><th>Estado</th><th></th></tr></thead><tbody id="linksBody"></tbody></table>
                </div>
            </div>

            <!-- Notificaciones -->
            <div id="cfg-notifications" class="cfg-panel hidden space-y-6">
                <div class="card p-4 flex items-center gap-3 bg-blue-50/50 border-blue-100">
                    <i data-lucide="info" class="w-4 h-4 text-blue-500 shrink-0"></i>
                    <p class="text-xs text-slate-600">Las credenciales de envío (SMTP y SMS/Twilio) se cargan desde <a href="configuracion.php" class="text-blue-600 font-bold underline">Configuración → Notificaciones de Agenda</a>. Acá definís a quién avisar, por qué canal, y con qué texto — todo por recurso y por tipo de evento.</p>
                </div>
                <div class="card p-6">
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-1">
                        <h3 class="font-black text-slate-900">Reglas de notificación</h3>
                        <div class="flex items-center gap-2">
                            <label class="field-label mb-0">Recurso</label>
                            <select id="notifResourceSelect" class="field-input" style="width:auto" onchange="loadNotificationRules()">
                                <option value="0">Regla por defecto (todos los recursos)</option>
                            </select>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 mb-6">A quién avisar, por qué canal y con qué texto para cada tipo de evento. Un recurso sin reglas propias usa la regla por defecto del negocio. El agente externo solo se notifica si el contacto tiene uno asignado.</p>
                    <div id="notificationRulesBody" class="space-y-6"></div>
                    <button class="btn-primary mt-6" onclick="saveNotificationRules()">Guardar reglas</button>
                </div>
            </div>

            <!-- Integraciones -->
            <div id="cfg-integrations" class="cfg-panel hidden space-y-6">
                <div class="card p-6">
                    <h3 class="font-black text-slate-900 mb-1">Zoom</h3>
                    <p class="text-xs text-slate-400 mb-6">Una sola cuenta Zoom para todo el negocio (Server-to-Server OAuth). Los servicios marcados como "virtuales" generan automáticamente una reunión desde esta cuenta al confirmarse la reserva — el enlace se comparte con el cliente y se guarda en la reserva.</p>
                    <form id="zoomConfigForm" class="grid md:grid-cols-2 gap-4">
                        <div><label class="field-label">Account ID</label><input class="field-input" name="account_id" required></div>
                        <div><label class="field-label">Client ID</label><input class="field-input" name="client_id" required></div>
                        <div><label class="field-label">Client Secret</label><input class="field-input" type="password" name="client_secret" placeholder="•••••••• (dejar vacío para no cambiar)"></div>
                        <div><label class="field-label">Usuario host (email de Zoom)</label><input class="field-input" name="host_user_id" placeholder="ej. dueño@negocio.com" required></div>
                        <div class="md:col-span-2 flex items-center gap-3">
                            <button type="submit" class="btn-primary">Guardar</button>
                            <span id="zoomConfigStatus" class="text-xs font-bold text-slate-400"></span>
                        </div>
                    </form>
                    <p class="text-[11px] text-slate-400 mt-4">¿No tenés las credenciales todavía? Pedíselas al administrador — hace falta crear una app "Server-to-Server OAuth" en el Marketplace de Zoom.</p>
                </div>
                <div class="card p-6">
                    <h3 class="font-black text-slate-900 mb-1">Google Calendar</h3>
                    <p class="text-xs text-slate-400">Se conecta por recurso, no acá — entrá a Sucursales y Recursos → el recurso que quieras sincronizar, vas a ver el botón "Conectar Google Calendar" en su detalle.</p>
                </div>
            </div>

            <!-- Ajustes generales -->
            <div id="cfg-settings" class="cfg-panel hidden space-y-6">
                <div class="card p-6">
                    <h3 class="font-black text-slate-900 mb-1">Ajustes generales</h3>
                    <p class="text-xs text-slate-400 mb-6">No configuran un recurso puntual, sino otros puntos del camino de la reserva: cuánto dura el hold antes de liberarse, cuánta anticipación mínima exige el sistema, y cuándo se disparan los recordatorios automáticos.</p>
                    <form id="settingsForm" class="grid md:grid-cols-2 gap-4">
                        <div class="flex items-end"><label class="flex items-center gap-2 text-xs font-bold text-slate-500"><input type="checkbox" name="enabled" checked class="w-4 h-4"> Agenda habilitada</label></div>
                        <div><label class="field-label">Minutos de hold</label><input class="field-input" type="number" min="1" name="hold_minutes" value="5" required></div>
                        <div><label class="field-label">Anticipación mínima (minutos)</label><input class="field-input" type="number" min="0" name="min_lead_minutes" value="60" required></div>
                        <div><label class="field-label">Horas de recordatorio (separadas por coma)</label><input class="field-input" name="reminder_hours_before" value="24,2" required></div>
                        <div class="md:col-span-2"><button type="submit" class="btn-primary">Guardar</button></div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ============ AGENDA CONSOLIDADA ============ -->
        <div id="panel-consolidada" class="hidden space-y-6"></div>

        <!-- ============ AGENTES EXTERNOS ============ -->
        <div id="panel-external-agents" class="hidden space-y-6">
            <div class="card p-6">
                <h3 class="font-black text-slate-900 mb-4">Nuevo agente externo</h3>
                <p class="text-xs text-slate-400 mb-4 -mt-2">Referentes que comparten leads pero no tienen usuario en el sistema — solo se guardan sus datos de contacto para notificarlos.</p>
                <form id="externalAgentForm" class="grid md:grid-cols-3 gap-4">
                    <input type="hidden" name="id">
                    <div><label class="field-label">Nombre</label><input class="field-input" name="name" required></div>
                    <div><label class="field-label">Teléfono</label><input class="field-input" name="phone" required></div>
                    <div><label class="field-label">Email</label><input class="field-input" type="email" name="email"></div>
                    <div class="md:col-span-3"><label class="field-label">Notas</label><input class="field-input" name="notes"></div>
                    <div class="flex items-end"><label class="flex items-center gap-2 text-xs font-bold text-slate-500"><input type="checkbox" name="active" checked class="w-4 h-4"> Activo</label></div>
                    <div class="md:col-span-3 flex gap-3"><button type="submit" class="btn-primary">Guardar</button><button type="button" class="btn-secondary" onclick="resetForm('externalAgentForm')">Cancelar edición</button></div>
                </form>
            </div>
            <div class="card overflow-x-auto">
                <table class="simple w-full"><thead><tr><th>Nombre</th><th>Teléfono</th><th>Email</th><th>Activo</th><th></th></tr></thead><tbody id="externalAgentsBody"></tbody></table>
            </div>
        </div>
    </div>
</main>

<!-- Modal: Nueva reserva manual -->
<div id="manualBookingModal" class="modal-overlay hidden">
    <div class="bg-white w-full max-w-lg rounded-[2rem] p-8 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-black">Nueva reserva</h3>
            <button onclick="closeManualBookingModal()" class="w-8 h-8 bg-slate-100 rounded-full flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <div class="space-y-4">
            <div>
                <label class="field-label">Recurso</label>
                <select id="mb-resource" class="field-input" onchange="onManualResourceChange()"></select>
            </div>
            <div>
                <label class="field-label">Servicio</label>
                <select id="mb-service" class="field-input" onchange="loadManualAvailability()"></select>
            </div>
            <div>
                <label class="field-label">Fecha</label>
                <input type="date" id="mb-date" class="field-input" onchange="loadManualAvailability()">
            </div>
            <div>
                <label class="field-label">Horario</label>
                <div id="mb-slots" class="flex flex-wrap gap-2 max-h-40 overflow-y-auto"></div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div><label class="field-label">Nombre</label><input class="field-input" id="mb-name"></div>
                <div><label class="field-label">Teléfono</label><input class="field-input" id="mb-phone"></div>
            </div>
            <div><label class="field-label">Email</label><input class="field-input" id="mb-email" type="email"></div>
            <div>
                <label class="field-label">Agente externo (opcional)</label>
                <div class="flex gap-2">
                    <select id="mb-external-agent" class="field-input flex-1">
                        <option value="">Sin agente externo</option>
                    </select>
                    <button type="button" class="btn-secondary shrink-0" onclick="document.getElementById('mb-ea-quick').classList.toggle('hidden')" title="Crear agente externo nuevo">+ Nuevo</button>
                </div>
                <div id="mb-ea-quick" class="hidden mt-2 p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                    <input id="mb-ea-quick-name" class="field-input" placeholder="Nombre del agente externo">
                    <input id="mb-ea-quick-phone" class="field-input" placeholder="Teléfono">
                    <div class="flex gap-2">
                        <button type="button" class="btn-primary flex-1" onclick="quickCreateExternalAgent()">Crear</button>
                        <button type="button" class="btn-secondary flex-1" onclick="document.getElementById('mb-ea-quick').classList.add('hidden')">Cancelar</button>
                    </div>
                </div>
            </div>
            <div><label class="field-label">Notas</label><input class="field-input" id="mb-notes"></div>
            <button class="btn-primary w-full" onclick="submitManualBooking()">Crear reserva</button>
        </div>
    </div>
</div>

<!-- Modal: Nueva sucursal -->
<div id="branchModal" class="modal-overlay hidden">
    <div class="bg-white w-full max-w-lg rounded-[2rem] p-8 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-black" id="branchModalTitle">Nueva sucursal</h3>
            <button onclick="closeBranchModal()" class="w-8 h-8 bg-slate-100 rounded-full flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <form id="branchForm" class="grid md:grid-cols-2 gap-4">
            <input type="hidden" name="id">
            <div><label class="field-label">Nombre</label><input class="field-input" name="name" required></div>
            <div><label class="field-label">Ciudad</label><input class="field-input" name="city"></div>
            <div><label class="field-label">Dirección</label><input class="field-input" name="address"></div>
            <div><label class="field-label">Teléfono</label><input class="field-input" name="phone"></div>
            <div><label class="field-label">Zona horaria</label><select class="field-input" name="timezone" id="branchTimezoneSelect" required></select></div>
            <div class="flex items-end gap-2"><label class="flex items-center gap-2 text-xs font-bold text-slate-500"><input type="checkbox" name="active" checked class="w-4 h-4"> Activa</label></div>
            <div class="md:col-span-2 flex gap-3"><button type="submit" class="btn-primary">Guardar</button><button type="button" class="btn-secondary" onclick="closeBranchModal()">Cancelar</button></div>
        </form>
    </div>
</div>

<!-- Modal: Nuevo recurso -->
<div id="resourceModal" class="modal-overlay hidden">
    <div class="bg-white w-full max-w-lg rounded-[2rem] p-8 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-black">Nuevo recurso en esta sucursal</h3>
            <button onclick="closeResourceModal()" class="w-8 h-8 bg-slate-100 rounded-full flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <p class="text-xs text-slate-500 mb-4">Un profesional, una cancha, un salón — cualquier cosa que se reserve por horario. Después de crearlo definís su disponibilidad y su(s) servicio(s).</p>
        <form id="quickResourceForm" class="grid gap-4">
            <div><label class="field-label">Nombre</label><input class="field-input" name="name" required placeholder="Ej. Cancha 1, Juan Pérez"></div>
            <div><label class="field-label">Capacidad simultánea</label><input class="field-input" type="number" min="1" value="1" name="capacity" required></div>
            <div class="flex gap-3"><button type="submit" class="btn-primary flex-1">Crear y configurar →</button><button type="button" class="btn-secondary" onclick="closeResourceModal()">Cancelar</button></div>
        </form>
    </div>
</div>

<!-- Modal: confirmación genérica (reemplaza confirm() nativo, bloqueado en navegadores embebidos) -->
<div id="confirmModal" class="modal-overlay hidden">
    <div class="bg-white w-full max-w-sm rounded-[2rem] p-8 shadow-2xl text-center">
        <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center mx-auto mb-4"><i data-lucide="alert-triangle" class="w-6 h-6"></i></div>
        <p id="confirmMessage" class="text-slate-700 font-medium mb-6"></p>
        <div class="flex gap-3">
            <button id="confirmCancelBtn" class="btn-secondary flex-1">Cancelar</button>
            <button id="confirmOkBtn" class="btn-primary flex-1" style="background:#dc2626;box-shadow:0 2px 8px rgba(220,38,38,.25)">Confirmar</button>
        </div>
    </div>
</div>

<!-- Toast (reemplaza alert() nativo) -->
<div id="toast" class="hidden fixed bottom-6 right-6 z-[300] px-4 py-3 rounded-xl font-medium text-xs shadow-2xl max-w-sm bg-slate-900 text-white items-center gap-2.5"></div>

<script>
const IS_ADMIN = <?php echo $is_admin ? 'true' : 'false'; ?>;
lucide.createIcons();
function toggleMenu() { document.getElementById('mobileMenu').classList.toggle('hidden'); }
async function logout() { fetch('api/auth.php?action=logout').then(() => window.location.href = 'login.php'); }
function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
function icons() { lucide.createIcons(); }

// Reemplazan alert()/confirm() nativos: en navegadores embebidos (ej. el
// panel integrado de VS Code) esos diálogos están bloqueados y el botón
// que los dispara parece "no hacer nada".
let toastTimer = null;
function showToast(message, type = 'error') {
    const el = document.getElementById('toast');
    const icon = type === 'error' ? 'circle-alert' : 'circle-check';
    const iconColor = type === 'error' ? 'text-red-400' : 'text-emerald-400';
    el.innerHTML = `<i data-lucide="${icon}" class="w-4 h-4 shrink-0 ${iconColor}"></i><span>${escapeHtml(message)}</span>`;
    el.className = 'fixed bottom-6 right-6 z-[300] px-4 py-3 rounded-xl font-medium text-xs shadow-2xl max-w-sm bg-slate-900 text-white flex items-center gap-2.5';
    icons();
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.add('hidden'), 3500);
}
function showConfirm(message) {
    return new Promise(resolve => {
        document.getElementById('confirmMessage').textContent = message;
        const modal = document.getElementById('confirmModal');
        modal.classList.remove('hidden');
        const cleanup = (result) => { modal.classList.add('hidden'); resolve(result); };
        document.getElementById('confirmOkBtn').onclick = () => cleanup(true);
        document.getElementById('confirmCancelBtn').onclick = () => cleanup(false);
    });
}

async function api(url, opts = {}) {
    const res = await fetch(url, opts);
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || 'Error inesperado');
    return data;
}
async function apiPost(url, body) {
    return api(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
}
function resetForm(id) { const f = document.getElementById(id); f.reset(); const idInput = f.querySelector('[name="id"]'); if (idInput) idInput.value = ''; }

let cache = { branches: [], resources: [], services: [], externalAgents: [] };
const WEEKDAYS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
const TIMEZONE_OPTIONS = `
    <optgroup label="Estados Unidos">
        <option value="America/New_York">Este (New York)</option>
        <option value="America/Chicago">Central (Chicago)</option>
        <option value="America/Denver">Montaña (Denver)</option>
        <option value="America/Phoenix">Montaña sin horario de verano (Arizona)</option>
        <option value="America/Los_Angeles">Pacífico (Los Ángeles)</option>
        <option value="America/Anchorage">Alaska</option>
        <option value="Pacific/Honolulu">Hawái</option>
    </optgroup>
    <optgroup label="Otras zonas horarias">
        <option value="America/Asuncion">Paraguay (Asunción)</option>
        <option value="America/Mexico_City">México (Ciudad de México)</option>
        <option value="America/Bogota">Colombia (Bogotá)</option>
        <option value="America/Sao_Paulo">Brasil (São Paulo)</option>
        <option value="Europe/Madrid">España (Madrid)</option>
        <option value="UTC">UTC</option>
    </optgroup>`;

// ── TABS ──
const TABS = ['resumen', 'turnos', 'consolidada', 'external-agents', 'config'];
function showTab(tab) {
    TABS.forEach(t => {
        const panel = document.getElementById('panel-' + t);
        const btn = document.getElementById('tabBtn-' + t);
        if (panel) panel.classList.toggle('hidden', t !== tab);
        if (btn) btn.classList.toggle('active', t === tab);
    });
    if (tab === 'resumen') loadResumen();
    else if (tab === 'turnos') loadBookings();
    else if (tab === 'consolidada') loadConsolidada();
    else if (tab === 'external-agents') loadExternalAgents();
    else if (tab === 'config' && document.getElementById('tabBtn-config')) showConfigTab(currentConfigTab || 'branches');
}

const CFG_TABS = [
    ['branches', 'Sucursales y Recursos'], ['links', 'Enlaces de reserva'],
    ['notifications', 'Notificaciones'], ['integrations', 'Integraciones'], ['settings', 'Ajustes generales'],
];
let currentConfigTab = 'branches';
document.getElementById('cfgPills').innerHTML = CFG_TABS.map(([id, label]) =>
    `<button class="cfg-pill" id="pill-${id}" onclick="showConfigTab('${id}')">${label}</button>`).join('');

const CFG_LOADERS = {
    branches: loadDrillDown, links: loadLinks,
    notifications: loadNotificationRulesTab, integrations: loadIntegrationsTab, settings: loadSettings,
};
function showConfigTab(tab) {
    currentConfigTab = tab;
    document.querySelectorAll('.cfg-panel').forEach(el => el.classList.add('hidden'));
    document.getElementById('cfg-' + tab).classList.remove('hidden');
    document.querySelectorAll('.cfg-pill').forEach(el => el.classList.remove('active'));
    document.getElementById('pill-' + tab).classList.add('active');
    CFG_LOADERS[tab] && CFG_LOADERS[tab]();
}

// ── CATALOGO BASE (branches/resources/services) usado en varios lados ──
async function refreshCatalog() {
    [cache.branches, cache.resources, cache.services] = await Promise.all([
        api('api/agenda-branches.php'), api('api/agenda-resources.php'), api('api/agenda-services.php'),
    ]);
}

// ══════════════════════════════════════════════════════════════════════
// SUCURSALES → RECURSOS → DETALLE — navegación jerárquica.
// Un recurso (persona, cancha, salón) vive DENTRO de una sucursal; su
// disponibilidad, buffers, servicios y enlace de reserva se configuran
// todos DENTRO de ESE recurso, sin cambiar de pestaña.
// ══════════════════════════════════════════════════════════════════════
let drill = { level: 'branches', branchId: null, resourceId: null };

async function loadDrillDown() {
    await refreshCatalog();
    if (drill.level === 'resources') await renderDrillResources();
    else if (drill.level === 'resource-detail') await renderDrillResourceDetail();
    else await renderDrillBranches();
}
function drillGoBranches() { drill = { level: 'branches', branchId: null, resourceId: null }; loadDrillDown(); }
function drillGoResources(branchId) { drill = { level: 'resources', branchId, resourceId: null }; loadDrillDown(); }
function drillGoResourceDetail(resourceId) {
    const res = cache.resources.find(x => x.id == resourceId);
    drill.level = 'resource-detail';
    drill.resourceId = resourceId;
    if (res) drill.branchId = res.branch_id;
    loadDrillDown();
}

function renderBreadcrumb() {
    const parts = [`<button class="hover:text-blue-600" onclick="drillGoBranches()">Sucursales</button>`];
    if (drill.branchId) {
        const b = cache.branches.find(x => x.id == drill.branchId);
        parts.push('<span class="text-slate-300">/</span>');
        parts.push(drill.level === 'resource-detail'
            ? `<button class="hover:text-blue-600" onclick="drillGoResources(${drill.branchId})">${escapeHtml(b?.name || '')}</button>`
            : `<span class="text-slate-800 font-bold">${escapeHtml(b?.name || '')}</span>`);
    }
    if (drill.resourceId && drill.level === 'resource-detail') {
        const r = cache.resources.find(x => x.id == drill.resourceId);
        parts.push('<span class="text-slate-300">/</span>');
        parts.push(`<span class="text-slate-800 font-bold">${escapeHtml(r?.name || '')}</span>`);
    }
    document.getElementById('drillBreadcrumb').innerHTML = parts.join(' ');
}

function rdCopyLink(url) { navigator.clipboard.writeText(url); showToast('Copiado', 'success'); }

// ── Nivel 1: Sucursales ──
async function renderDrillBranches() {
    renderBreadcrumb();
    document.getElementById('drillBody').innerHTML = `
        <div class="flex justify-end">
            <button class="btn-primary" onclick="openBranchModal()"><i data-lucide="plus" class="w-4 h-4 inline -mt-1 mr-1"></i>Nueva sucursal</button>
        </div>
        <div class="grid gap-4">
            ${cache.branches.map(b => {
                const resCount = cache.resources.filter(r => r.branch_id == b.id).length;
                return `<div class="card p-5 flex items-center gap-4 cursor-pointer hover:border-blue-300 hover:shadow-md transition-all" onclick="drillGoResources(${b.id})">
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg font-black shrink-0"><i data-lucide="building-2" class="w-5 h-5"></i></div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h4 class="font-black text-slate-900">${escapeHtml(b.name)}</h4>
                            ${parseInt(b.active) ? '<span class="badge badge-ok">Activa</span>' : '<span class="badge badge-off">Inactiva</span>'}
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">${escapeHtml(b.city || 'Sin ciudad')} · ${escapeHtml(b.timezone)}</p>
                    </div>
                    <div class="hidden sm:block text-center px-3 shrink-0">
                        <div class="text-lg font-black text-slate-800">${resCount}</div>
                        <div class="text-[9px] uppercase tracking-widest text-slate-400 font-bold">Recursos</div>
                    </div>
                    <div class="flex items-center gap-1 shrink-0" onclick="event.stopPropagation()">
                        <button class="w-8 h-8 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-400 hover:text-blue-600" onclick='editBranch(${JSON.stringify(b)})' title="Editar"><i data-lucide="pencil" class="w-4 h-4"></i></button>
                        <button class="w-8 h-8 rounded-lg hover:bg-red-50 flex items-center justify-center text-slate-400 hover:text-red-600" onclick="deleteBranch(${b.id})" title="Eliminar"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                    </div>
                    <i data-lucide="chevron-right" class="hidden sm:block w-5 h-5 text-slate-300 shrink-0"></i>
                </div>`;
            }).join('') || '<div class="card p-10 text-center text-slate-400">Sin sucursales todavía — creá la primera arriba</div>'}
        </div>`;
    icons();
}
function openBranchModal() {
    resetForm('branchForm');
    document.getElementById('branchModalTitle').textContent = 'Nueva sucursal';
    document.getElementById('branchModal').classList.remove('hidden');
}
function closeBranchModal() { document.getElementById('branchModal').classList.add('hidden'); }
function editBranch(b) {
    const f = document.getElementById('branchForm');
    f.id.value = b.id; f.name.value = b.name; f.city.value = b.city || ''; f.address.value = b.address || '';
    f.phone.value = b.phone || ''; f.timezone.value = b.timezone; f.active.checked = !!parseInt(b.active);
    document.getElementById('branchModalTitle').textContent = 'Editar sucursal';
    document.getElementById('branchModal').classList.remove('hidden');
}
async function deleteBranch(id) {
    if (!(await showConfirm('¿Eliminar sucursal? Se eliminan también sus recursos.'))) return;
    await apiPost('api/agenda-branches.php', { action: 'delete', id });
    await refreshCatalog();
    renderDrillBranches();
}

// ── Nivel 2: Recursos de una sucursal ──
async function renderDrillResources() {
    renderBreadcrumb();
    const branchResources = cache.resources.filter(r => r.branch_id == drill.branchId);
    document.getElementById('drillBody').innerHTML = `
        <div class="flex justify-end">
            <button class="btn-primary" onclick="openResourceModal()"><i data-lucide="plus" class="w-4 h-4 inline -mt-1 mr-1"></i>Nuevo recurso</button>
        </div>
        <div class="grid gap-4">
            ${branchResources.map(r => {
                const svcNames = cache.services.filter(s => r.service_ids.includes(s.id)).map(s => s.name).join(', ');
                const warnings = [];
                if (!r.service_ids.length) warnings.push('sin servicios');
                if (!parseInt(r.schedule_count)) warnings.push('sin horarios');
                const accent = r.color || '#2563eb';
                return `<div class="card overflow-hidden cursor-pointer hover:shadow-md transition-all relative" onclick="drillGoResourceDetail(${r.id})">
                    <div class="absolute top-0 left-0 w-1.5 h-full" style="background:${accent}"></div>
                    <div class="p-5 pl-7 flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-lg font-black shrink-0 overflow-hidden" style="background:${accent}22;color:${accent}">${r.photo_url ? `<img src="${r.photo_url}" class="w-full h-full object-cover">` : '<i data-lucide="calendar-clock" class="w-5 h-5"></i>'}</div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h4 class="font-black text-slate-900">${escapeHtml(r.name)}</h4>
                                ${warnings.length ? `<span class="badge badge-warn">${warnings.join(', ')}</span>` : '<span class="badge badge-ok">Listo para reservar</span>'}
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">${svcNames || 'Sin servicios asignados'} · Capacidad ${r.capacity}</p>
                        </div>
                        <div class="flex items-center gap-1 shrink-0" onclick="event.stopPropagation()">
                            <button class="w-8 h-8 rounded-lg hover:bg-red-50 flex items-center justify-center text-slate-400 hover:text-red-600" onclick="deleteResourceFromDrill(${r.id})" title="Eliminar"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                        </div>
                        <i data-lucide="chevron-right" class="hidden sm:block w-5 h-5 text-slate-300 shrink-0"></i>
                    </div>
                </div>`;
            }).join('') || '<div class="card p-10 text-center text-slate-400">Sin recursos todavía — creá el primero arriba</div>'}
        </div>`;
    icons();
}
function openResourceModal() {
    document.getElementById('quickResourceForm').reset();
    document.getElementById('resourceModal').classList.remove('hidden');
}
function closeResourceModal() { document.getElementById('resourceModal').classList.add('hidden'); }
async function deleteResourceFromDrill(id) {
    if (!(await showConfirm('¿Eliminar recurso?'))) return;
    await apiPost('api/agenda-resources.php', { action: 'delete', id });
    await refreshCatalog();
    renderDrillResources();
}

document.getElementById('branchTimezoneSelect').innerHTML = TIMEZONE_OPTIONS;
document.getElementById('branchForm').onsubmit = async (e) => {
    e.preventDefault();
    const f = e.target;
    const data = { id: f.id.value || null, name: f.name.value, city: f.city.value, address: f.address.value, phone: f.phone.value, timezone: f.timezone.value, active: f.active.checked ? 1 : 0 };
    try { await apiPost('api/agenda-branches.php', data); await refreshCatalog(); closeBranchModal(); renderDrillBranches(); showToast('Sucursal guardada', 'success'); } catch (err) { showToast(err.message); }
};
document.getElementById('quickResourceForm').onsubmit = async (e) => {
    e.preventDefault();
    const f = e.target;
    try {
        const res = await apiPost('api/agenda-resources.php', { name: f.name.value, branch_id: drill.branchId, capacity: f.capacity.value });
        await refreshCatalog();
        closeResourceModal();
        drillGoResourceDetail(res.id);
        showToast('Recurso creado — ahora definí su disponibilidad y servicio', 'success');
    } catch (err) { showToast(err.message); }
};

// ── Nivel 3: Detalle de un recurso (disponibilidad + buffers + servicios + enlace) ──
async function renderDrillResourceDetail() {
    renderBreadcrumb();
    const r = cache.resources.find(x => x.id == drill.resourceId);
    if (!r) { drillGoBranches(); return; }

    const [schedules, blocks, links, googleConn] = await Promise.all([
        api('api/agenda-schedules.php?resource_id=' + r.id),
        api('api/agenda-blocks.php?resource_id=' + r.id),
        api('api/agenda-booking-links.php'),
        api('api/agenda-google-disconnect.php?resource_id=' + r.id).catch(() => ({ connected: false })),
    ]);

    // El enlace dedicado se autogenera recién cuando el recurso ya tiene al
    // menos un servicio asignado — así queda bien armado desde el vamos
    // (con el servicio precargado si hay uno solo) en vez de crear un
    // enlace "vacío" apenas se entra al recurso por primera vez.
    let dedicatedLink = links.find(l => l.resource_id == r.id && l.status !== 'revoked');
    if (!dedicatedLink && r.service_ids.length > 0) {
        try {
            dedicatedLink = await apiPost('api/agenda-booking-links.php', {
                branch_id: r.branch_id, resource_id: r.id,
                service_id: r.service_ids.length === 1 ? r.service_ids[0] : null,
            });
        } catch (err) { dedicatedLink = null; }
    }

    const schedulesByDay = {};
    schedules.forEach(s => { (schedulesByDay[s.weekday] = schedulesByDay[s.weekday] || []).push(s); });
    const DAY_ORDER = [1, 2, 3, 4, 5, 6, 0];

    const accent = r.color || '#2563eb';
    document.getElementById('drillBody').innerHTML = `
        <div class="rounded-[1.75rem] p-6 text-white shadow-lg flex items-center gap-4 flex-wrap" style="background:linear-gradient(120deg,${accent}dd,${accent})">
            <div class="w-14 h-14 rounded-2xl bg-white/15 flex items-center justify-center text-2xl font-black shrink-0 border border-white/20 overflow-hidden">${r.photo_url ? `<img src="${r.photo_url}" class="w-full h-full object-cover">` : '<i data-lucide="calendar-clock" class="w-6 h-6"></i>'}</div>
            <div class="flex-1 min-w-0">
                <h2 class="text-xl font-black leading-tight">${escapeHtml(r.name)}</h2>
                <p class="text-white/70 text-xs mt-0.5">${r.service_ids.length} servicio(s) · ${schedules.length} bloque(s) de horario · Capacidad ${r.capacity}</p>
            </div>
            ${parseInt(r.active) ? '<span class="badge" style="background:rgba(255,255,255,.18);color:white">Activo</span>' : '<span class="badge" style="background:rgba(0,0,0,.2);color:#e2e8f0">Inactivo</span>'}
        </div>
        <div class="card card-accent p-6" style="--accent:${accent}">
            <h3 class="font-black text-slate-900 mb-4">Datos del recurso</h3>
            <div class="flex items-center gap-4 mb-5">
                <div id="resourcePhotoPreview" class="w-16 h-16 rounded-2xl flex items-center justify-center text-xl font-black text-white shrink-0 overflow-hidden" style="background:${accent}">${r.photo_url ? `<img src="${r.photo_url}" class="w-full h-full object-cover">` : escapeHtml(r.name.trim().charAt(0).toUpperCase())}</div>
                <div>
                    <input type="file" id="resourcePhotoInput" accept=".jpg,.jpeg,.png,.gif,.webp" class="hidden">
                    <button type="button" id="resourcePhotoBtn" class="btn-secondary">Cambiar foto</button>
                    <p class="text-[11px] text-slate-400 mt-1">JPG, PNG, GIF o WEBP.</p>
                </div>
            </div>
            <form id="resourceDetailForm" class="grid md:grid-cols-3 gap-4">
                <div><label class="field-label">Nombre</label><input class="field-input" name="name" value="${escapeHtml(r.name)}" required></div>
                <div><label class="field-label">Capacidad simultánea</label><input class="field-input" type="number" min="1" name="capacity" value="${r.capacity}" required></div>
                <div><label class="field-label">Color</label><input class="field-input" type="color" name="color" value="${r.color || '#2563eb'}"></div>
                <div class="flex items-end"><label class="flex items-center gap-2 text-xs font-bold text-slate-500"><input type="checkbox" name="active" ${parseInt(r.active) ? 'checked' : ''} class="w-4 h-4"> Activo</label></div>
                <div class="md:col-span-3"><button type="submit" class="btn-primary">Guardar datos</button></div>
            </form>
        </div>

        <div class="card p-6">
            <h3 class="font-black text-slate-900 mb-1">Google Calendar</h3>
            <p class="text-xs text-slate-500 mb-4">Cada reserva confirmada de este recurso se crea (y actualiza/borra) como evento en el calendario conectado. Es sincronización de ida — nunca se lee lo que haya en el calendario.</p>
            ${googleConn.connected ? `
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2 text-emerald-700 text-sm font-bold"><i data-lucide="check-circle-2" class="w-4 h-4"></i>Conectado${googleConn.google_email ? ` (${escapeHtml(googleConn.google_email)})` : ''}</div>
                <button class="btn-danger" onclick="disconnectGoogleCalendar(${r.id})">Desconectar</button>
            </div>` : `
            <a href="api/agenda-google-connect.php?resource_id=${r.id}" class="btn-primary inline-flex items-center gap-2"><i data-lucide="calendar-plus" class="w-4 h-4"></i>Conectar Google Calendar</a>`}
        </div>

        <div class="card p-6">
            <h3 class="font-black text-slate-900 mb-1">Disponibilidad</h3>
            <p class="text-xs text-slate-500 mb-4">Horarios semanales, bloqueos puntuales, y los buffers de preparación/limpieza que necesita este recurso entre turnos.</p>
            <form id="bufferForm" class="grid md:grid-cols-3 gap-4 mb-6">
                <div><label class="field-label">Buffer antes (min)</label><input class="field-input" type="number" min="0" name="buffer_before_min" value="${r.buffer_before_min}"></div>
                <div><label class="field-label">Buffer después (min)</label><input class="field-input" type="number" min="0" name="buffer_after_min" value="${r.buffer_after_min}"></div>
                <div class="flex items-end"><button type="submit" class="btn-secondary w-full">Guardar buffers</button></div>
            </form>
            <div class="mb-6">
                <h4 class="font-bold text-sm text-slate-700 mb-2">Horarios recurrentes</h4>
                <form id="rdScheduleForm" class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-4 max-w-xl">
                    <select name="weekday" class="field-input" required>
                        <option value="1">Lunes</option><option value="2">Martes</option><option value="3">Miércoles</option>
                        <option value="4">Jueves</option><option value="5">Viernes</option><option value="6">Sábado</option><option value="0">Domingo</option>
                    </select>
                    <input type="time" name="start_time" class="field-input" value="09:00" required>
                    <input type="time" name="end_time" class="field-input" value="18:00" required>
                    <button type="submit" class="btn-primary col-span-3">+ Agregar</button>
                </form>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                    ${DAY_ORDER.map(wd => {
                        const ranges = schedulesByDay[wd] || [];
                        const hasRanges = ranges.length > 0;
                        return `<div class="day-chip ${hasRanges ? '' : 'day-off'}">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="day-name">${WEEKDAYS[wd]}</span>
                                <span class="badge ${hasRanges ? 'badge-ok' : 'badge-off'}" style="font-size:8px;padding:.15rem .4rem">${hasRanges ? 'Disponible' : 'Cerrado'}</span>
                            </div>
                            ${hasRanges ? ranges.map(s => `<div class="flex items-center justify-between text-[11px] text-slate-600 font-mono">
                                <span>${s.start_time.slice(0,5)}-${s.end_time.slice(0,5)}</span>
                                <button onclick="rdDeleteSchedule(${s.id})" class="text-slate-300 hover:text-red-500 shrink-0 ml-1"><i data-lucide="x" class="w-3 h-3"></i></button>
                            </div>`).join('') : '<p class="text-[11px] text-slate-400">Sin atención</p>'}
                        </div>`;
                    }).join('')}
                </div>
            </div>
            <div>
                <h4 class="font-bold text-sm text-slate-700 mb-2">Bloqueos puntuales</h4>
                <form id="rdBlockForm" class="grid md:grid-cols-5 gap-2 mb-4">
                    <div class="md:col-span-2"><label class="field-label">Desde</label><input type="datetime-local" name="starts_at" class="field-input" required></div>
                    <div class="md:col-span-2"><label class="field-label">Hasta</label><input type="datetime-local" name="ends_at" class="field-input" required></div>
                    <input type="text" name="reason" placeholder="Motivo (opcional)" class="field-input md:col-span-4">
                    <button type="submit" class="btn-primary">+ Agregar</button>
                </form>
                <table class="simple w-full"><thead><tr><th>Desde</th><th>Hasta</th><th>Motivo</th><th></th></tr></thead>
                    <tbody>${blocks.map(b => `<tr><td class="text-xs">${b.starts_at}</td><td class="text-xs">${b.ends_at}</td><td class="text-xs">${escapeHtml(b.reason || '')}</td><td class="text-right"><button class="btn-danger" onclick="rdDeleteBlock(${b.id})">Borrar</button></td></tr>`).join('') || '<tr><td colspan="4" class="text-center py-4 text-slate-400 text-xs">Sin bloqueos cargados</td></tr>'}</tbody>
                </table>
            </div>
        </div>

        <div class="card p-6">
            <h3 class="font-black text-slate-900 mb-1">Servicios que ofrece</h3>
            <p class="text-xs text-slate-500 mb-4">Al menos uno. Si es un recurso tipo cancha o salón, cargá un único servicio genérico (ej. "Uso General"). Todos comparten la disponibilidad de arriba.</p>
            <div id="rd-services-list" class="flex flex-wrap gap-3 bg-slate-50 border border-slate-200 rounded-xl p-3 mb-3">
                ${cache.services.map(s => `<label class="flex items-center gap-1.5 text-xs font-bold text-slate-600"><input type="checkbox" value="${s.id}" class="rd-svc-chk w-4 h-4" ${r.service_ids.includes(s.id) ? 'checked' : ''}> ${escapeHtml(s.name)} (${s.duration_min} min)</label>`).join('') || '<span class="text-xs text-slate-400">Sin servicios todavía</span>'}
            </div>
            ${cache.services.length ? `
            <div class="mb-3">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Servicio virtual (genera reunión de Zoom automáticamente)</p>
                <div class="flex flex-wrap gap-3 bg-slate-50 border border-slate-200 rounded-xl p-3">
                    ${cache.services.map(s => `<label class="flex items-center gap-1.5 text-xs font-bold text-slate-600"><input type="checkbox" data-service-id="${s.id}" class="rd-svc-virtual w-4 h-4" ${parseInt(s.is_virtual) ? 'checked' : ''} onchange="rdToggleServiceVirtual(${s.id}, this.checked)"> ${escapeHtml(s.name)}</label>`).join('')}
                </div>
            </div>` : ''}
            <button type="button" class="btn-secondary" onclick="document.getElementById('rd-service-quick').classList.toggle('hidden')">+ Nuevo servicio</button>
            <div id="rd-service-quick" class="hidden mt-2 p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                <input id="rd-service-quick-name" class="field-input" placeholder='Nombre (ej. "Uso General", "Sesión Estratégica")'>
                <div class="grid grid-cols-2 gap-2">
                    <input id="rd-service-quick-duration" type="number" min="5" value="30" class="field-input" placeholder="Duración (min)">
                    <input id="rd-service-quick-price" type="number" step="0.01" class="field-input" placeholder="Precio (opcional)">
                </div>
                <label class="flex items-center gap-2 text-xs font-bold text-slate-500"><input type="checkbox" id="rd-service-quick-virtual" class="w-4 h-4"> Servicio virtual (genera reunión de Zoom)</label>
                <div class="flex gap-2">
                    <button type="button" class="btn-primary flex-1" onclick="rdQuickCreateService()">Crear y asignar</button>
                    <button type="button" class="btn-secondary flex-1" onclick="document.getElementById('rd-service-quick').classList.add('hidden')">Cancelar</button>
                </div>
            </div>
            <button class="btn-primary w-full mt-4" onclick="rdSaveServices()">Guardar servicios</button>
        </div>

        <div class="card p-6">
            <h3 class="font-black text-slate-900 mb-1">Enlace de reserva de este recurso</h3>
            <p class="text-xs text-slate-500 mb-4">Compartilo con tus clientes para que reserven directo con este recurso.</p>
            ${dedicatedLink ? `
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 flex items-center justify-between gap-3">
                <a href="${dedicatedLink.url}" target="_blank" class="text-blue-600 font-bold text-sm break-all">${dedicatedLink.url}</a>
                <button class="btn-secondary shrink-0" onclick="rdCopyLink('${dedicatedLink.url}')">Copiar</button>
            </div>` : `<p class="text-xs text-amber-600">No se pudo generar el enlace todavía — asigná al menos un servicio primero.</p>`}
        </div>
    `;

    document.getElementById('resourcePhotoBtn').onclick = () => document.getElementById('resourcePhotoInput').click();
    document.getElementById('resourcePhotoInput').onchange = async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        const fd = new FormData();
        fd.append('id', r.id);
        fd.append('photo', file);
        try {
            const res = await fetch('api/agenda-resources.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Error al subir la foto');
            await refreshCatalog();
            renderDrillResourceDetail();
            showToast('Foto actualizada', 'success');
        } catch (err) { showToast(err.message); }
    };
    document.getElementById('resourceDetailForm').onsubmit = async (e) => {
        e.preventDefault();
        const f = e.target;
        try {
            await apiPost('api/agenda-resources.php', { id: r.id, name: f.name.value, capacity: f.capacity.value, color: f.color.value, active: f.active.checked ? 1 : 0 });
            await refreshCatalog();
            renderDrillResourceDetail();
            showToast('Datos guardados', 'success');
        } catch (err) { showToast(err.message); }
    };
    document.getElementById('bufferForm').onsubmit = async (e) => {
        e.preventDefault();
        const f = e.target;
        try {
            await apiPost('api/agenda-resources.php', { id: r.id, buffer_before_min: f.buffer_before_min.value, buffer_after_min: f.buffer_after_min.value });
            await refreshCatalog();
            renderDrillResourceDetail();
            showToast('Buffers guardados', 'success');
        } catch (err) { showToast(err.message); }
    };
    document.getElementById('rdScheduleForm').onsubmit = async (e) => {
        e.preventDefault();
        const f = e.target;
        try { await apiPost('api/agenda-schedules.php', { resource_id: r.id, weekday: f.weekday.value, start_time: f.start_time.value, end_time: f.end_time.value }); renderDrillResourceDetail(); }
        catch (err) { showToast(err.message); }
    };
    document.getElementById('rdBlockForm').onsubmit = async (e) => {
        e.preventDefault();
        const f = e.target;
        const toSql = (v) => v.replace('T', ' ') + ':00';
        try { await apiPost('api/agenda-blocks.php', { resource_id: r.id, starts_at: toSql(f.starts_at.value), ends_at: toSql(f.ends_at.value), reason: f.reason.value }); renderDrillResourceDetail(); }
        catch (err) { showToast(err.message); }
    };
    icons();
}
async function rdDeleteSchedule(id) { await apiPost('api/agenda-schedules.php', { action: 'delete', id }); renderDrillResourceDetail(); }
async function rdDeleteBlock(id) { await apiPost('api/agenda-blocks.php', { action: 'delete', id }); renderDrillResourceDetail(); }

async function rdQuickCreateService() {
    const name = document.getElementById('rd-service-quick-name').value.trim();
    const duration = parseInt(document.getElementById('rd-service-quick-duration').value || '30', 10);
    const price = document.getElementById('rd-service-quick-price').value;
    const isVirtual = document.getElementById('rd-service-quick-virtual').checked ? 1 : 0;
    if (!name) return showToast('Ponele un nombre al servicio');
    if (!duration || duration <= 0) return showToast('Duración inválida');
    try {
        const svc = await apiPost('api/agenda-services.php', { name, duration_min: duration, price, is_virtual: isVirtual });
        const currentIds = Array.from(document.querySelectorAll('.rd-svc-chk:checked')).map(c => parseInt(c.value));
        const newIds = [...new Set([...currentIds, svc.id])];
        await apiPost('api/agenda-resources.php', { id: drill.resourceId, service_ids: newIds });
        await refreshCatalog();
        renderDrillResourceDetail();
        showToast('Servicio creado y asignado', 'success');
    } catch (err) { showToast(err.message); }
}
async function rdToggleServiceVirtual(serviceId, isVirtual) {
    const svc = cache.services.find(s => s.id === serviceId);
    if (!svc) return;
    try {
        await apiPost('api/agenda-services.php', { id: serviceId, name: svc.name, duration_min: svc.duration_min, price: svc.price, currency: svc.currency, active: svc.active, is_virtual: isVirtual ? 1 : 0 });
        await refreshCatalog();
        showToast('Servicio actualizado', 'success');
    } catch (err) { showToast(err.message); renderDrillResourceDetail(); }
}
async function disconnectGoogleCalendar(resourceId) {
    if (!(await showConfirm('¿Desconectar Google Calendar de este recurso? Los eventos ya creados no se van a borrar del calendario, pero dejarán de sincronizarse.'))) return;
    try {
        await apiPost('api/agenda-google-disconnect.php', { resource_id: resourceId });
        renderDrillResourceDetail();
        showToast('Google Calendar desconectado', 'success');
    } catch (err) { showToast(err.message); }
}
async function rdSaveServices() {
    const serviceIds = Array.from(document.querySelectorAll('.rd-svc-chk:checked')).map(c => parseInt(c.value));
    if (!serviceIds.length && !(await showConfirm('No elegiste ningún servicio — el recurso no va a estar disponible para reservar. ¿Guardar igual?'))) return;
    try {
        await apiPost('api/agenda-resources.php', { id: drill.resourceId, service_ids: serviceIds });
        await refreshCatalog();
        renderDrillResourceDetail();
        showToast('Servicios guardados', 'success');
    } catch (err) { showToast(err.message); }
}

// ── ENLACES DE RESERVA (listado global) ──
function branchOptions(selected) {
    return cache.branches.map(b => `<option value="${b.id}" ${b.id == selected ? 'selected' : ''}>${escapeHtml(b.name)}</option>`).join('');
}
async function loadLinks() {
    await refreshCatalog();
    document.getElementById('linkBranchSelect').innerHTML = '<option value="">Cualquiera</option>' + branchOptions();
    document.getElementById('linkResourceSelect').innerHTML = '<option value="">Cualquiera</option>' + cache.resources.map(r => `<option value="${r.id}">${escapeHtml(r.name)}</option>`).join('');
    document.getElementById('linkServiceSelect').innerHTML = '<option value="">Cualquiera</option>' + cache.services.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');

    const links = await api('api/agenda-booking-links.php');
    let generalLink = links.find(l => !l.branch_id && !l.resource_id && !l.service_id && l.status !== 'revoked');
    if (!generalLink) {
        try { generalLink = await apiPost('api/agenda-booking-links.php', {}); links.unshift(generalLink); } catch (err) {}
    }
    document.getElementById('generalLinkCard').innerHTML = generalLink ? `
        <div class="card p-6 flex items-center justify-between gap-4" style="background:linear-gradient(135deg,#eef2ff,#ffffff)">
            <div class="min-w-0">
                <div class="text-[10px] font-black uppercase tracking-widest text-blue-500 mb-1">Enlace general del negocio</div>
                <a href="${generalLink.url}" target="_blank" class="text-blue-600 font-bold text-sm break-all">${generalLink.url}</a>
                <p class="text-xs text-slate-500 mt-1">El cliente elige servicio y con quién reservar. Cada recurso además tiene su propio enlace dedicado (aparece al entrar a ese recurso).</p>
            </div>
            <button class="btn-secondary shrink-0" onclick="rdCopyLink('${generalLink.url}')">Copiar</button>
        </div>` : '';
    document.getElementById('linksBody').innerHTML = links.map(l => `
        <tr>
            <td class="text-xs"><a href="${l.url}" target="_blank" class="text-blue-600 font-bold break-all">${l.url}</a>
                <button class="text-slate-400 hover:text-slate-700 ml-1" onclick="rdCopyLink('${l.url}')" title="Copiar"><i data-lucide="copy" class="w-3 h-3 inline"></i></button></td>
            <td class="text-xs">${[l.branch_name, l.resource_name, l.service_name].filter(Boolean).join(' · ') || 'General'}</td>
            <td><span class="text-xs font-bold ${l.status === 'active' ? 'text-emerald-600' : l.status === 'used' ? 'text-amber-600' : 'text-slate-400'}">${l.status}</span></td>
            <td class="text-right">${l.status !== 'revoked' ? `<button class="btn-danger" onclick="revokeLink(${l.id})">Revocar</button>` : ''}</td>
        </tr>`).join('') || '<tr><td colspan="4" class="text-center py-8 text-slate-400">Sin enlaces generados</td></tr>';
    icons();
}
async function revokeLink(id) { if (!(await showConfirm('¿Revocar este enlace?'))) return; await apiPost('api/agenda-booking-links.php', { action: 'revoke', id }); loadLinks(); }
document.getElementById('linkForm').onsubmit = async (e) => {
    e.preventDefault();
    const f = e.target;
    try { await apiPost('api/agenda-booking-links.php', { branch_id: f.branch_id.value || null, resource_id: f.resource_id.value || null, service_id: f.service_id.value || null, source_channel: f.source_channel.value }); f.reset(); loadLinks(); showToast('Enlace generado', 'success'); }
    catch (err) { showToast(err.message); }
};

// ── AGENTES EXTERNOS ──
async function loadExternalAgents() {
    cache.externalAgents = await api('api/agenda-external-agents.php');
    document.getElementById('externalAgentsBody').innerHTML = cache.externalAgents.map(a => `
        <tr>
            <td class="font-bold">${escapeHtml(a.name)}</td><td>${escapeHtml(a.phone)}</td><td>${escapeHtml(a.email || '')}</td>
            <td>${parseInt(a.active) ? '<span class="badge badge-ok">Activo</span>' : '<span class="badge badge-off">Inactivo</span>'}</td>
            <td class="text-right"><button class="text-blue-600 text-xs font-bold mr-3" onclick='editExternalAgent(${JSON.stringify(a)})'>Editar</button><button class="btn-danger" onclick="deleteExternalAgent(${a.id})">Eliminar</button></td>
        </tr>`).join('') || '<tr><td colspan="5" class="text-center py-8 text-slate-400">Sin agentes externos cargados</td></tr>';
}
function editExternalAgent(a) {
    const f = document.getElementById('externalAgentForm');
    f.id.value = a.id; f.name.value = a.name; f.phone.value = a.phone; f.email.value = a.email || ''; f.notes.value = a.notes || ''; f.active.checked = !!parseInt(a.active);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
async function deleteExternalAgent(id) { if (!(await showConfirm('¿Eliminar agente externo?'))) return; await apiPost('api/agenda-external-agents.php', { action: 'delete', id }); loadExternalAgents(); }
document.getElementById('externalAgentForm').onsubmit = async (e) => {
    e.preventDefault();
    const f = e.target;
    const data = { id: f.id.value || null, name: f.name.value, phone: f.phone.value, email: f.email.value, notes: f.notes.value, active: f.active.checked ? 1 : 0 };
    try { await apiPost('api/agenda-external-agents.php', data); resetForm('externalAgentForm'); loadExternalAgents(); showToast('Agente externo guardado', 'success'); } catch (err) { showToast(err.message); }
};

// ── NOTIFICACIONES (por recurso + tipo de evento, con plantilla editable) ──
const RECIPIENT_LABELS = { owner: 'Dueño del negocio', client: 'Cliente', external_agent: 'Agente externo (si tiene uno asignado)' };
const TRIGGER_LABELS = { confirmed: 'Al confirmar', rescheduled: 'Al reprogramar', cancelled: 'Al cancelar', reminder: 'Recordatorio' };

async function loadNotificationRulesTab() {
    await refreshCatalog();
    const sel = document.getElementById('notifResourceSelect');
    const current = sel.value || '0';
    sel.innerHTML = '<option value="0">Regla por defecto (todos los recursos)</option>' +
        cache.resources.map(r => `<option value="${r.id}">${escapeHtml(r.name)}</option>`).join('');
    sel.value = current;
    loadNotificationRules();
}

async function loadNotificationRules() {
    const resourceId = document.getElementById('notifResourceSelect').value || '0';
    const { rules } = await api('api/agenda-notification-rules.php?resource_id=' + resourceId);
    const byTrigger = {};
    rules.forEach(r => { (byTrigger[r.trigger_type] = byTrigger[r.trigger_type] || []).push(r); });

    document.getElementById('notificationRulesBody').innerHTML = Object.keys(TRIGGER_LABELS).map(trigger => `
        <div class="border border-slate-200 rounded-2xl p-4">
            <h4 class="font-black text-sm text-slate-800 mb-3">${TRIGGER_LABELS[trigger]}</h4>
            <div class="space-y-3">
                ${(byTrigger[trigger] || []).map(r => `
                    <details class="bg-slate-50 border border-slate-200 rounded-xl p-3" data-trigger="${r.trigger_type}" data-recipient="${r.recipient_type}">
                        <summary class="flex items-center justify-between gap-4 cursor-pointer list-none flex-wrap">
                            <span class="font-bold text-xs text-slate-700">${RECIPIENT_LABELS[r.recipient_type]}${r.has_override ? ' <span class="text-[9px] font-black uppercase tracking-widest text-blue-500">· Personalizado</span>' : ''}</span>
                            <span class="flex items-center gap-3 shrink-0" onclick="event.stopPropagation()">
                                <select class="field-input rule-channel" style="width:auto">
                                    <option value="email" ${r.channel === 'email' ? 'selected' : ''}>Email</option>
                                    <option value="sms" ${r.channel === 'sms' ? 'selected' : ''}>SMS</option>
                                </select>
                                <label class="flex items-center gap-1.5 text-xs font-bold text-slate-500"><input type="checkbox" class="rule-enabled w-4 h-4" ${parseInt(r.enabled) ? 'checked' : ''}> Habilitado</label>
                            </span>
                        </summary>
                        <div class="mt-3 pt-3 border-t border-slate-200 space-y-2">
                            <div><label class="field-label">Asunto</label><input class="field-input rule-subject"></div>
                            <div><label class="field-label">Mensaje</label><textarea class="field-input rule-body" rows="4"></textarea></div>
                            <p class="text-[10px] text-slate-400">Variables: {{cliente}} {{servicio}} {{agenda}} {{sucursal}} {{negocio}} {{fecha}} {{link}} {{zoom_link}} {{horas}}. Dejar vacío y guardar para volver a usar el texto por defecto.</p>
                        </div>
                    </details>
                `).join('')}
            </div>
        </div>
    `).join('');

    // Se llenan por JS (no interpolados en el template de arriba) para evitar
    // problemas de escapado de comillas/saltos de línea dentro de atributos HTML.
    document.querySelectorAll('#notificationRulesBody details').forEach(el => {
        const r = rules.find(x => x.trigger_type === el.dataset.trigger && x.recipient_type === el.dataset.recipient);
        if (!r) return;
        el.querySelector('.rule-subject').value = r.subject_template || r.default_subject;
        el.querySelector('.rule-body').value = r.body_template || r.default_body;
    });
    icons();
}

async function saveNotificationRules() {
    const resourceId = document.getElementById('notifResourceSelect').value || '0';
    const items = document.querySelectorAll('#notificationRulesBody details');
    const rules = Array.from(items).map(el => ({
        trigger_type: el.dataset.trigger,
        recipient_type: el.dataset.recipient,
        channel: el.querySelector('.rule-channel').value,
        enabled: el.querySelector('.rule-enabled').checked ? 1 : 0,
        subject_template: el.querySelector('.rule-subject').value,
        body_template: el.querySelector('.rule-body').value,
    }));
    try {
        await apiPost('api/agenda-notification-rules.php', { resource_id: resourceId, rules });
        showToast('Reglas guardadas', 'success');
        loadNotificationRules();
    } catch (err) { showToast(err.message); }
}

// ── INTEGRACIONES (Zoom del negocio) ──
async function loadIntegrationsTab() {
    try {
        const cfg = await api('api/agenda-zoom-config.php');
        const f = document.getElementById('zoomConfigForm');
        f.account_id.value = cfg.account_id || '';
        f.client_id.value = cfg.client_id || '';
        f.host_user_id.value = cfg.host_user_id || '';
        document.getElementById('zoomConfigStatus').textContent = cfg.configured ? 'Configurado' : 'Sin configurar';
        document.getElementById('zoomConfigStatus').className = 'text-xs font-bold ' + (cfg.configured ? 'text-emerald-600' : 'text-slate-400');
    } catch (err) { showToast(err.message); }
}
document.getElementById('zoomConfigForm').onsubmit = async (e) => {
    e.preventDefault();
    const f = e.target;
    try {
        await apiPost('api/agenda-zoom-config.php', {
            account_id: f.account_id.value, client_id: f.client_id.value,
            client_secret: f.client_secret.value, host_user_id: f.host_user_id.value,
        });
        f.client_secret.value = '';
        showToast('Zoom configurado', 'success');
        loadIntegrationsTab();
    } catch (err) { showToast(err.message); }
};


// ── AJUSTES GENERALES ──
async function loadSettings() {
    const s = await api('api/agenda-settings.php');
    const f = document.getElementById('settingsForm');
    f.enabled.checked = !!parseInt(s.enabled); f.hold_minutes.value = s.hold_minutes; f.min_lead_minutes.value = s.min_lead_minutes; f.reminder_hours_before.value = s.reminder_hours_before;
}
document.getElementById('settingsForm').onsubmit = async (e) => {
    e.preventDefault();
    const f = e.target;
    try { await apiPost('api/agenda-settings.php', { enabled: f.enabled.checked ? 1 : 0, hold_minutes: f.hold_minutes.value, min_lead_minutes: f.min_lead_minutes.value, reminder_hours_before: f.reminder_hours_before.value }); showToast('Guardado', 'success'); }
    catch (err) { showToast(err.message); }
};

// ── RESUMEN GENERAL ──
async function loadResumen() {
    await refreshCatalog();
    const today = new Date().toISOString().slice(0, 10);
    let bookingsToday = [];
    try { bookingsToday = await api('api/agenda-bookings.php?from=' + today + '&to=' + today); } catch (err) {}

    const activeResources = cache.resources.filter(r => parseInt(r.active));
    const panel = document.getElementById('panel-resumen');
    panel.innerHTML = `
        <div class="rounded-[1.75rem] p-8 text-white shadow-xl" style="background:linear-gradient(120deg,#0f172a,#1e3a8a 55%,#2563eb)">
            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/10 border border-white/10 mb-3">
                <span class="relative flex h-1.5 w-1.5"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-400"></span></span>
                Negocio activo
            </div>
            <h2 class="text-2xl font-black">Gestión de recursos y turnos</h2>
            <p class="text-blue-200 text-sm mt-1">Supervisión centralizada de tus sucursales, recursos y agendas.</p>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="card p-5 flex items-center justify-between">
                <div><p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Recursos activos</p><h3 class="text-2xl font-black text-slate-800 mt-1">${activeResources.length}</h3></div>
                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center"><i data-lucide="users" class="w-5 h-5"></i></div>
            </div>
            <div class="card p-5 flex items-center justify-between">
                <div><p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Turnos hoy</p><h3 class="text-2xl font-black text-slate-800 mt-1">${bookingsToday.length}</h3></div>
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center"><i data-lucide="calendar-check" class="w-5 h-5"></i></div>
            </div>
            <div class="card p-5 flex items-center justify-between">
                <div><p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Servicios</p><h3 class="text-2xl font-black text-slate-800 mt-1">${cache.services.length}</h3></div>
                <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center"><i data-lucide="tag" class="w-5 h-5"></i></div>
            </div>
            <div class="card p-5 flex items-center justify-between">
                <div><p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Sucursales</p><h3 class="text-2xl font-black text-slate-800 mt-1">${cache.branches.length}</h3></div>
                <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center"><i data-lucide="building-2" class="w-5 h-5"></i></div>
            </div>
        </div>
        <div class="grid md:grid-cols-2 gap-6">
            ${activeResources.slice(0, 4).map(r => {
                const svcNames = cache.services.filter(s => r.service_ids.includes(s.id)).map(s => s.name).join(', ');
                const branch = cache.branches.find(b => b.id == r.branch_id);
                const accent = r.color || '#2563eb';
                return `<div class="card p-5 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1.5 h-full" style="background:${accent}"></div>
                    <div class="pl-2 flex items-center justify-between">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 overflow-hidden" style="background:${accent}22;color:${accent}">${r.photo_url ? `<img src="${r.photo_url}" class="w-full h-full object-cover">` : '<i data-lucide="calendar-clock" class="w-5 h-5"></i>'}</div>
                            <div class="min-w-0">
                                <h4 class="font-black text-slate-900 truncate">${escapeHtml(r.name)}</h4>
                                <p class="text-xs text-slate-500 truncate">${escapeHtml(branch?.name || '')}</p>
                            </div>
                        </div>
                        <button class="text-slate-400 hover:text-blue-600 p-2 rounded-lg hover:bg-slate-100 shrink-0" onclick="goToResourceFromResumen(${r.id})" title="Ver recurso"><i data-lucide="arrow-right" class="w-4 h-4"></i></button>
                    </div>
                    <p class="text-xs text-slate-500 mt-3 pl-2 truncate">${svcNames || 'Sin servicios asignados'}</p>
                </div>`;
            }).join('') || '<div class="card p-10 text-center text-slate-400 md:col-span-2">Todavía no tenés recursos activos — armá el primero desde Configuración.</div>'}
        </div>`;
    icons();
}
function goToResourceFromResumen(resourceId) {
    showTab('config');
    showConfigTab('branches');
    drillGoResourceDetail(resourceId);
}

// ── AGENDA CONSOLIDADA ──
let consolidadaDate = new Date().toISOString().slice(0, 10);
async function loadConsolidada() {
    await refreshCatalog();
    let bookings = [];
    try { bookings = await api('api/agenda-bookings.php?from=' + consolidadaDate + '&to=' + consolidadaDate); } catch (err) {}
    const activeResources = cache.resources.filter(r => parseInt(r.active));
    const dateObj = new Date(consolidadaDate + 'T00:00:00');
    const dateLabel = dateObj.toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long' });
    const isToday = consolidadaDate === new Date().toISOString().slice(0, 10);

    document.getElementById('panel-consolidada').innerHTML = `
        <div class="card p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-xl font-black text-slate-900">Agenda consolidada</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Todos los recursos activos, un mismo día, de un vistazo.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center" onclick="consolidadaShiftDay(-1)"><i data-lucide="chevron-left" class="w-4 h-4"></i></button>
                    <span class="text-xs font-black text-slate-700 px-2 capitalize">${isToday ? 'Hoy, ' : ''}${dateLabel}</span>
                    <button class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center" onclick="consolidadaShiftDay(1)"><i data-lucide="chevron-right" class="w-4 h-4"></i></button>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                ${activeResources.map(r => {
                    const resBookings = bookings
                        .filter(b => b.resource_id == r.id && ['confirmed', 'rescheduled', 'held'].includes(b.status))
                        .sort((a, b) => a.starts_at.localeCompare(b.starts_at));
                    const accent = r.color || '#2563eb';
                    return `<div class="border border-slate-200 rounded-2xl overflow-hidden">
                        <div class="p-3 bg-slate-50 border-b border-slate-200 font-bold text-xs text-slate-700 flex items-center gap-2 truncate">
                            <span class="w-2 h-2 rounded-full shrink-0" style="background:${accent}"></span>${escapeHtml(r.name)}
                        </div>
                        <div class="p-2 space-y-2 min-h-[110px]">
                            ${resBookings.length ? resBookings.map(b => `
                                <div class="p-2.5 rounded-lg text-xs" style="background:${accent}14;border-left:3px solid ${accent}">
                                    <p class="font-bold text-slate-800">${b.starts_at.slice(11,16)}–${b.ends_at.slice(11,16)}</p>
                                    <p class="text-slate-500 truncate">${escapeHtml(b.contact_name || b.service_name)}${b.status === 'held' ? ' · pendiente' : ''}</p>
                                </div>`).join('') : '<p class="text-[11px] text-slate-400 text-center py-6">Sin turnos</p>'}
                        </div>
                    </div>`;
                }).join('') || '<div class="text-center text-slate-400 py-10 col-span-full">Todavía no tenés recursos activos</div>'}
            </div>
        </div>`;
    icons();
}
function consolidadaShiftDay(delta) {
    const d = new Date(consolidadaDate + 'T00:00:00');
    d.setDate(d.getDate() + delta);
    consolidadaDate = d.toISOString().slice(0, 10);
    loadConsolidada();
}

// ── TURNOS (listado) ──
async function loadBookings() {
    await refreshCatalog();
    const sel = document.getElementById('filterResource');
    if (!sel.dataset.loaded) {
        sel.innerHTML = '<option value="">Todos</option>' + cache.resources.map(r => `<option value="${r.id}">${escapeHtml(r.name)}</option>`).join('');
        sel.dataset.loaded = '1';
    }
    const params = new URLSearchParams();
    if (document.getElementById('filterResource').value) params.set('resource_id', document.getElementById('filterResource').value);
    if (document.getElementById('filterStatus').value) params.set('status', document.getElementById('filterStatus').value);
    if (document.getElementById('filterFrom').value) params.set('from', document.getElementById('filterFrom').value);
    if (document.getElementById('filterTo').value) params.set('to', document.getElementById('filterTo').value);

    const bookings = await api('api/agenda-bookings.php?' + params.toString());

    const confirmedCount = bookings.filter(b => ['confirmed', 'rescheduled'].includes(b.status)).length;
    const heldCount = bookings.filter(b => b.status === 'held').length;
    document.getElementById('turnosStats').innerHTML = `
        <div class="text-center px-4 py-2 rounded-2xl bg-white/10 border border-white/10">
            <div class="text-lg font-black leading-none">${bookings.length}</div>
            <div class="text-[9px] uppercase tracking-widest text-blue-200 font-bold mt-1">Mostrando</div>
        </div>
        <div class="text-center px-4 py-2 rounded-2xl bg-white/10 border border-white/10">
            <div class="text-lg font-black leading-none text-emerald-300">${confirmedCount}</div>
            <div class="text-[9px] uppercase tracking-widest text-blue-200 font-bold mt-1">Confirmadas</div>
        </div>
        <div class="text-center px-4 py-2 rounded-2xl bg-white/10 border border-white/10">
            <div class="text-lg font-black leading-none text-amber-300">${heldCount}</div>
            <div class="text-[9px] uppercase tracking-widest text-blue-200 font-bold mt-1">Pendientes</div>
        </div>
        <div class="text-center px-4 py-2 rounded-2xl bg-white/10 border border-white/10">
            <div class="text-lg font-black leading-none">${cache.resources.length}</div>
            <div class="text-[9px] uppercase tracking-widest text-blue-200 font-bold mt-1">Recursos</div>
        </div>`;

    const STATUS_BADGE = { held: 'bg-amber-50 text-amber-600', confirmed: 'bg-emerald-50 text-emerald-600', rescheduled: 'bg-blue-50 text-blue-600', cancelled: 'bg-slate-100 text-slate-400', completed: 'bg-slate-100 text-slate-500', no_show: 'bg-red-50 text-red-500' };
    document.getElementById('bookingsBody').innerHTML = bookings.map(b => `
        <tr>
            <td class="font-bold">${b.starts_at.slice(0,16).replace('T',' ')}</td>
            <td>${escapeHtml(b.resource_name)}</td>
            <td>${escapeHtml(b.service_name)}</td>
            <td>${escapeHtml(b.contact_name || '—')}${b.contact_phone ? '<br><span class="text-slate-400 text-[11px]">' + escapeHtml(b.contact_phone) + '</span>' : ''}</td>
            <td><span class="text-[10px] font-black uppercase px-2 py-1 rounded-full ${STATUS_BADGE[b.status] || ''}">${b.status}</span>
                ${b.zoom_join_url ? `<a href="${b.zoom_join_url}" target="_blank" title="Unirse por Zoom" class="text-blue-500 ml-1"><i data-lucide="video" class="w-3.5 h-3.5 inline"></i></a>` : ''}
                ${b.google_event_id ? `<i data-lucide="calendar-check-2" class="w-3.5 h-3.5 inline text-emerald-500 ml-1" title="Sincronizado con Google Calendar"></i>` : ''}</td>
            <td>${parseInt(b.attendance_confirmed) ? '<i data-lucide="check" class="w-4 h-4 text-emerald-500"></i>' : '—'}</td>
            <td class="text-right">${['confirmed','rescheduled','held'].includes(b.status) ? `<button class="btn-danger" onclick="cancelBooking(${b.id})">Cancelar</button>` : ''}</td>
        </tr>`).join('') || '<tr><td colspan="7" class="text-center py-10 text-slate-400">Sin reservas con estos filtros</td></tr>';
    icons();
}
async function cancelBooking(id) { if (!(await showConfirm('¿Cancelar esta reserva?'))) return; await apiPost('api/agenda-bookings.php', { action: 'cancel', id }); loadBookings(); }

// ── MODAL: NUEVA RESERVA MANUAL ──
function manualBookingSetupIssue() {
    if (!cache.branches.length) return { message: 'Todavía no hay ninguna sucursal creada.', resourceId: null };
    const activeResources = cache.resources.filter(r => parseInt(r.active));
    if (!activeResources.length) return { message: 'Todavía no hay ningún recurso creado.', resourceId: null };
    const ready = activeResources.filter(r => r.service_ids.length > 0 && parseInt(r.schedule_count) > 0);
    if (ready.length) return null;
    const withoutServices = activeResources.find(r => !r.service_ids.length);
    if (withoutServices) return { message: `El recurso "${withoutServices.name}" todavía no tiene ningún servicio asignado.`, resourceId: withoutServices.id };
    const withoutSchedule = activeResources.find(r => !parseInt(r.schedule_count));
    return { message: `El recurso "${withoutSchedule.name}" todavía no tiene horarios cargados.`, resourceId: withoutSchedule.id };
}

async function openManualBookingModal() {
    await refreshCatalog();
    const issue = manualBookingSetupIssue();
    if (issue) {
        if (IS_ADMIN) {
            showToast(issue.message + ' Te llevamos a completarlo.');
            showTab('config');
            showConfigTab('branches');
            if (issue.resourceId) drillGoResourceDetail(issue.resourceId);
            else drillGoBranches();
        } else {
            showToast(issue.message + ' Pedile al administrador que lo complete.');
        }
        return;
    }
    const readyResources = cache.resources.filter(r => parseInt(r.active) && r.service_ids.length > 0 && parseInt(r.schedule_count) > 0);
    document.getElementById('mb-resource').innerHTML = readyResources.map(r => `<option value="${r.id}">${escapeHtml(r.name)}</option>`).join('');
    document.getElementById('mb-date').value = new Date().toISOString().slice(0, 10);
    document.getElementById('mb-name').value = ''; document.getElementById('mb-phone').value = ''; document.getElementById('mb-email').value = ''; document.getElementById('mb-notes').value = '';
    await loadManualExternalAgents();
    onManualResourceChange();
    document.getElementById('manualBookingModal').classList.remove('hidden');
    icons();
}
async function loadManualExternalAgents(selectId = '') {
    const sel = document.getElementById('mb-external-agent');
    const agents = await api('api/agenda-external-agents.php?active_only=1');
    sel.innerHTML = '<option value="">Sin agente externo</option>' +
        agents.map(a => `<option value="${a.id}">${escapeHtml(a.name)} (${escapeHtml(a.phone)})</option>`).join('');
    if (selectId) sel.value = selectId;
}
async function quickCreateExternalAgent() {
    const name = document.getElementById('mb-ea-quick-name').value.trim();
    const phone = document.getElementById('mb-ea-quick-phone').value.trim();
    if (!name || !phone) return showToast('Completá nombre y teléfono del agente externo');
    try {
        const res = await apiPost('api/agenda-external-agents.php', { name, phone });
        await loadManualExternalAgents(res.id);
        document.getElementById('mb-ea-quick-name').value = '';
        document.getElementById('mb-ea-quick-phone').value = '';
        document.getElementById('mb-ea-quick').classList.add('hidden');
        showToast('Agente externo creado', 'success');
    } catch (err) { showToast(err.message); }
}
function closeManualBookingModal() { document.getElementById('manualBookingModal').classList.add('hidden'); }
function onManualResourceChange() {
    const resourceId = parseInt(document.getElementById('mb-resource').value);
    const resource = cache.resources.find(r => r.id === resourceId);
    const services = cache.services.filter(s => resource && resource.service_ids.includes(s.id));
    document.getElementById('mb-service').innerHTML = services.map(s => `<option value="${s.id}">${escapeHtml(s.name)} (${s.duration_min} min)</option>`).join('') || '<option value="">Este recurso no ofrece servicios</option>';
    loadManualAvailability();
}
let manualSelectedSlot = null;
async function loadManualAvailability() {
    manualSelectedSlot = null;
    const resourceId = document.getElementById('mb-resource').value;
    const serviceId = document.getElementById('mb-service').value;
    const date = document.getElementById('mb-date').value;
    const box = document.getElementById('mb-slots');
    if (!resourceId || !serviceId || !date) { box.innerHTML = ''; return; }
    box.innerHTML = '<span class="text-xs text-slate-400">Buscando...</span>';
    try {
        const to = new Date(new Date(date).getTime() + 6 * 86400000).toISOString().slice(0, 10);
        const { slots } = await api(`api/agenda-bookings.php?action=availability&resource_id=${resourceId}&service_id=${serviceId}&from=${date}&to=${to}`);
        const dayOnly = slots.filter(s => s.starts_at.slice(0, 10) === date);
        const list = dayOnly.length ? dayOnly : slots;
        box.innerHTML = list.slice(0, 40).map(s => `<button type="button" data-start="${s.starts_at}" class="slot-btn bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-xs font-bold" onclick="selectManualSlot(this)">${s.starts_at.slice(0,10)} ${s.starts_at.slice(11,16)}</button>`).join('') || '<span class="text-xs text-slate-400">Sin horarios disponibles</span>';
    } catch (err) { box.innerHTML = `<span class="text-xs text-red-500">${escapeHtml(err.message)}</span>`; }
}
function selectManualSlot(btn) {
    document.querySelectorAll('#mb-slots .slot-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    manualSelectedSlot = btn.dataset.start;
}
async function submitManualBooking() {
    if (!manualSelectedSlot) return showToast('Elegí un horario');
    const data = {
        action: 'create_manual',
        resource_id: document.getElementById('mb-resource').value,
        service_id: document.getElementById('mb-service').value,
        starts_at: manualSelectedSlot,
        contact_name: document.getElementById('mb-name').value,
        contact_phone: document.getElementById('mb-phone').value,
        contact_email: document.getElementById('mb-email').value,
        external_agent_id: document.getElementById('mb-external-agent').value,
        notes: document.getElementById('mb-notes').value,
    };
    try { await apiPost('api/agenda-bookings.php', data); closeManualBookingModal(); loadBookings(); showToast('Reserva creada', 'success'); }
    catch (err) { showToast(err.message); }
}

// Init
showConfigTab('branches');
loadResumen();

// Vuelta del callback de Google Calendar OAuth (redirect de página completa,
// no fetch) — agenda-google-callback.php manda de vuelta acá con estos
// query params para mostrar el resultado y reabrir el recurso conectado.
(function handleGoogleCallbackRedirect() {
    const params = new URLSearchParams(location.search);
    const google = params.get('google');
    if (!google) return;
    const resourceId = parseInt(params.get('resource_id') || '0', 10);
    if (google === 'connected') showToast('Google Calendar conectado', 'success');
    else showToast(params.get('msg') || 'No se pudo conectar Google Calendar');
    history.replaceState({}, '', location.pathname);
    if (resourceId) {
        showTab('config');
        showConfigTab('branches');
        setTimeout(() => drillGoResourceDetail(resourceId), 300);
    }
})();
</script>
</body>
</html>
