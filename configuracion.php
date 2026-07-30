<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
include 'api/config.php';
$is_admin = ($_SESSION['user_role'] ?? 'subscriber') === 'admin';
if (!$is_admin) { header('Location: index.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración Global - Ultra CRM</title>
    <?php if(isset($crm_favicon) && $crm_favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($crm_favicon); ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ultrablue: #0d1e56;
            --brandpurple: #6366f1;
            --sidebar-bg: #0d1e56;
            --accent-blue: #2563eb;
            --card-bg: #ffffff;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; color: #0f172a; }
        .glass-card { background: white; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(30,41,59,.05); }

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

                            <a href="agentes.php" class="nav-link   flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

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

                            <a href="configuracion.php" class="nav-link active flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

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

                    <a href="agentes.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

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

                    <a href="configuracion.php" class="nav-link active flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

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

    <main class="flex-1 flex flex-col min-w-0">
        <header class="h-16 border-b border-slate-200 flex items-center justify-between px-6 lg:px-10 bg-white sticky top-0 z-40">
            <h1 class="text-xl font-bold text-slate-900">Configuración Global del Sistema</h1>
        </header>

        <div class="p-6 lg:p-10 max-w-2xl mx-auto w-full space-y-8">
            <div class="glass-card rounded-[2.5rem] p-8 md:p-12">
                <div class="flex items-center gap-4 mb-10">
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center">
                        <i data-lucide="palette" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-slate-900">Interfaz Visual</h2>
                        <p class="text-slate-500 text-sm">Personaliza la apariencia general del CRM</p>
                    </div>
                </div>

                <div class="space-y-8">
                    <!-- Configuración Nombre CRM -->
                    <div class="space-y-4">
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Nombre de la Plataforma</label>
                        <div class="bg-slate-50 p-6 rounded-3xl border border-slate-100">
                            <input type="text" id="cfg-crm-name" placeholder="Ultra CRM" value="<?php echo htmlspecialchars($crm_name); ?>" class="w-full bg-white border border-slate-200 rounded-2xl px-5 py-3 text-sm font-bold text-slate-800 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all">
                            <p class="text-[11px] text-slate-400 font-medium mt-2">Este nombre aparecer? en la barra lateral superior de todos los usuarios.</p>
                        </div>
                    </div>

                    <!-- Configuración Logo CRM -->
                    <div class="space-y-4">
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Logo de la Plataforma</label>
                        <div class="flex items-center gap-6 bg-slate-50 p-6 rounded-3xl border border-slate-100">
                            <div class="w-16 h-16 rounded-2xl bg-white border border-slate-200 flex items-center justify-center font-bold text-2xl text-indigo-600 shadow-sm overflow-hidden shrink-0" id="logo-preview-box">
                                <?php echo $crm_logo ? '<img src="'.htmlspecialchars($crm_logo).'" class="w-full h-full object-cover">' : htmlspecialchars(substr($crm_name, 0, 1)); ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <input type="file" id="cfg-crm-logo" accept="image/*" onchange="previewLogo(this)" class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100 file:cursor-pointer cursor-pointer">
                                <p class="text-[11px] text-slate-400 font-medium mt-1">Formatos recomendados: PNG, JPG, SVG o WEBP (Formato cuadrado 1:1).</p>
                            </div>
                        </div>
                    </div>

                    <!-- Configuración Favicon CRM -->
                    <div class="space-y-4">
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Favicon (Icono de pestaña)</label>
                        <div class="flex items-center gap-6 bg-slate-50 p-6 rounded-3xl border border-slate-100">
                            <div class="w-12 h-12 rounded-xl bg-white border border-slate-200 flex items-center justify-center font-bold text-xl text-indigo-600 shadow-sm overflow-hidden shrink-0" id="favicon-preview-box">
                                <?php echo isset($crm_favicon) && $crm_favicon ? '<img src="'.htmlspecialchars($crm_favicon).'" class="w-full h-full object-cover">' : '<i data-lucide="globe" class="w-6 h-6"></i>'; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <input type="file" id="cfg-crm-favicon" accept="image/*,.ico" onchange="previewFavicon(this)" class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100 file:cursor-pointer cursor-pointer">
                                <p class="text-[11px] text-slate-400 font-medium mt-1">Formatos recomendados: ICO, PNG o SVG (Tamaño pequeño y cuadrado).</p>
                            </div>
                        </div>
                    </div>

                    <!-- Configuración Fondo Tarjetas -->
                    <div class="space-y-4">
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Color de Fondo de Tarjetas (Prospectos)</label>
                        <div class="flex items-center gap-6 bg-slate-50 p-6 rounded-3xl border border-slate-100">
                            <input type="color" id="cfg-card-bg" value="#1e293b" class="w-16 h-16 rounded-2xl border-none cursor-pointer bg-transparent">
                            <div class="flex-1">
                                <input type="text" id="cfg-card-bg-hex" placeholder="#1E293B" class="w-full bg-transparent text-lg font-mono font-bold uppercase outline-none text-slate-700">
                                <p class="text-[11px] text-slate-400 font-medium mt-1">Este color se aplicar? a todas las tarjetas en la lista de prospectos</p>
                            </div>
                        </div>
                    </div>

                    <div class="h-px bg-slate-100"></div>

                    <button onclick="saveGlobalSettings()" id="saveGlobalBtn" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-5 rounded-2xl font-bold shadow-xl shadow-indigo-100 transition-all active:scale-95 flex items-center justify-center gap-3">
                        <i data-lucide="save" class="w-5 h-5"></i> Guardar Cambios Globales
                    </button>
                </div>
            </div>
        </div>

        <!-- ElevenLabs API Key -->
        <div class="glass-card rounded-[2.5rem] p-8 md:p-12">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-12 h-12 bg-violet-50 text-violet-600 rounded-2xl flex items-center justify-center text-2xl">
                    🎤
                </div>
                <div>
                    <h2 class="text-2xl font-black text-slate-900">ElevenLabs Conversational AI</h2>
                    <p class="text-slate-500 text-sm">API Key para crear agentes de voz automáticamente</p>
                </div>
            </div>

            <div class="space-y-5">
                <div class="space-y-2">
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">API Key de ElevenLabs</label>
                    <div class="bg-slate-50 p-5 rounded-3xl border border-slate-100 flex flex-col gap-3">
                        <div class="flex gap-2">
                            <input type="password" id="cfg-el-api-key" placeholder="sk-..." autocomplete="off" class="flex-1 bg-white border border-slate-200 rounded-2xl px-5 py-3 text-sm font-mono text-slate-800 outline-none focus:ring-4 focus:ring-violet-500/10 focus:border-violet-500 transition-all">
                            <button onclick="toggleApiKeyVisibility()" class="px-4 py-2 rounded-xl bg-slate-200 text-slate-600 hover:bg-slate-300 transition text-sm font-bold" title="Mostrar/Ocultar">👁️</button>
                        </div>
                        <div id="el-verify-result" class="hidden text-xs font-semibold px-3 py-1.5 rounded-full"></div>
                        <p class="text-[11px] text-slate-400 font-medium">Obtén tu API Key en <a href="https://elevenlabs.io/app/settings/api-keys" target="_blank" class="text-violet-500 underline font-bold">ElevenLabs → Settings → API Keys</a>. Esta clave se almacena de forma segura en el servidor y nunca se expone al navegador de tus clientes.</p>
                    </div>
                </div>

                <button onclick="saveElevenLabsKey()" id="saveElBtn" class="w-full bg-violet-600 hover:bg-violet-700 text-white py-4 rounded-2xl font-bold shadow-xl shadow-violet-100 transition-all active:scale-95 flex items-center justify-center gap-3">
                    <i data-lucide="save" class="w-5 h-5"></i> Guardar API Key de ElevenLabs
                </button>
            </div>
        </div>

        <!-- Notificaciones de Agenda: SMTP y SMS -->
        <div class="glass-card rounded-[2.5rem] p-8 md:p-12">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center">
                    <i data-lucide="calendar-check" class="w-6 h-6"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-black text-slate-900">Notificaciones de Agenda</h2>
                    <p class="text-slate-500 text-sm">Credenciales de envío para los recordatorios y avisos automáticos del módulo Agenda</p>
                </div>
            </div>

            <div class="space-y-10">
                <!-- SMTP -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Email (SMTP)</label>
                        <span id="agendaSmtpBadge" class="text-xs font-bold"></span>
                    </div>
                    <div class="bg-slate-50 p-6 rounded-3xl border border-slate-100 grid md:grid-cols-2 gap-4">
                        <div><label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Host</label><input id="agenda-smtp-host" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500"></div>
                        <div><label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Puerto</label><input id="agenda-smtp-port" type="number" value="587" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500"></div>
                        <div><label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Usuario</label><input id="agenda-smtp-username" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500"></div>
                        <div><label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Contraseña (vacío = no cambiarla)</label><input id="agenda-smtp-password" type="password" placeholder="••••••••" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500"></div>
                        <div><label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Email remitente</label><input id="agenda-smtp-from-email" type="email" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500"></div>
                        <div><label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Nombre remitente</label><input id="agenda-smtp-from-name" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500"></div>
                        <div class="md:col-span-2">
                            <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Encriptación</label>
                            <select id="agenda-smtp-encryption" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500">
                                <option value="tls">TLS</option><option value="ssl">SSL</option><option value="none">Ninguna</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <button onclick="saveAgendaSmtp()" id="saveAgendaSmtpBtn" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3.5 rounded-2xl font-bold shadow-lg shadow-blue-100 transition-all active:scale-95">Guardar SMTP</button>
                        </div>
                    </div>
                </div>

                <!-- SMS (Twilio) -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">SMS (Twilio)</label>
                        <span id="agendaSmsBadge" class="text-xs font-bold"></span>
                    </div>
                    <div class="bg-slate-50 p-6 rounded-3xl border border-slate-100 grid md:grid-cols-2 gap-4">
                        <div><label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Account SID</label><input id="agenda-sms-sid" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500"></div>
                        <div><label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Número de origen</label><input id="agenda-sms-from" placeholder="+595981000000" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500"></div>
                        <div class="md:col-span-2"><label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Auth Token (vacío = no cambiarlo)</label><input id="agenda-sms-token" type="password" placeholder="••••••••" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500"></div>
                        <div class="md:col-span-2">
                            <button onclick="saveAgendaSms()" id="saveAgendaSmsBtn" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3.5 rounded-2xl font-bold shadow-lg shadow-blue-100 transition-all active:scale-95">Guardar SMS</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

    <script>
        function toggleMenu() { document.getElementById('mobileMenu').classList.toggle('hidden'); }
        async function logout() { await fetch('api/auth.php?action=logout'); window.location.href = 'login.php'; }

        function previewLogo(input) {
            const box = document.getElementById('logo-preview-box');
            if (input.files && input.files[0]) {
                const r = new FileReader();
                r.onload = e => box.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
                r.readAsDataURL(input.files[0]);
            }
        }

        function previewFavicon(input) {
            const box = document.getElementById('favicon-preview-box');
            if (input.files && input.files[0]) {
                const r = new FileReader();
                r.onload = e => box.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
                r.readAsDataURL(input.files[0]);
            }
        }

        async function fetchSettings() {
            try {
                const res = await fetch('api/settings.php');
                const data = await res.json();
                if (data.card_bg) {
                    document.getElementById('cfg-card-bg').value = data.card_bg;
                    document.getElementById('cfg-card-bg-hex').value = data.card_bg;
                }
                if (data.crm_name) {
                    document.getElementById('cfg-crm-name').value = data.crm_name;
                }
                // Mostrar placeholder enmascarado si hay una API key guardada
                if (data.elevenlabs_api_key_set) {
                    document.getElementById('cfg-el-api-key').placeholder = '•••••••• (configurada — ingresa una nueva para reemplazar)';
                }
            } catch (err) { console.error(err); }
        }

        async function saveGlobalSettings() {
            const btn = document.getElementById('saveGlobalBtn');
            const color = document.getElementById('cfg-card-bg').value;
            const crmName = document.getElementById('cfg-crm-name').value.trim();
            const logoFile = document.getElementById('cfg-crm-logo').files[0];
            const faviconFile = document.getElementById('cfg-crm-favicon').files[0];

            btn.disabled = true;
            btn.innerHTML = 'Guardando...';

            const formData = new FormData();
            formData.append('card_bg', color);
            formData.append('crm_name', crmName);
            if (logoFile) {
                formData.append('crm_logo', logoFile);
            }
            if (faviconFile) {
                formData.append('crm_favicon', faviconFile);
            }

            try {
                const res = await fetch('api/settings.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    showToast('✅ Configuración guardada correctamente');
                    btn.innerHTML = '✅ Guardado';
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                }
            } catch (err) { 
                showToast('❌ Error al guardar', true);
                btn.disabled = false;
                btn.innerHTML = 'Guardar Cambios Globales';
            }
        }

        const cfgInp = document.getElementById('cfg-card-bg');
        const cfgHex = document.getElementById('cfg-card-bg-hex');
        cfgInp.addEventListener('input', (e) => cfgHex.value = e.target.value);
        cfgHex.addEventListener('input', (e) => { if(/^#[0-9A-F]{6}$/i.test(e.target.value)) cfgInp.value = e.target.value; });

        function showToast(msg, isError) {
            const t = document.createElement('div');
            t.textContent = msg;
            t.style.cssText = `position:fixed;bottom:40px;left:50%;transform:translateX(-50%);background:${isError?'#ef4444':'#0f172a'};color:#fff;padding:.75rem 1.5rem;border-radius:9999px;font-size:.9rem;font-weight:700;z-index:9999;box-shadow:0 10px 20px rgba(0,0,0,0.1);`;
            document.body.appendChild(t);
            setTimeout(() => t.remove(), 3000);
        }

        function toggleApiKeyVisibility() {
            const inp = document.getElementById('cfg-el-api-key');
            inp.type = inp.type === 'password' ? 'text' : 'password';
        }

        async function saveElevenLabsKey() {
            const btn = document.getElementById('saveElBtn');
            const apiKey = document.getElementById('cfg-el-api-key').value.trim();
            if (!apiKey) { showToast('❌ Ingresa una API Key', true); return; }

            btn.disabled = true;
            btn.innerHTML = 'Verificando y guardando...';

            // Verificar la key primero
            const verifyRes = await fetch('api/elevenlabs.php?action=verify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ api_key: apiKey })
            });
            const verifyData = await verifyRes.json();
            const resultEl = document.getElementById('el-verify-result');

            if (!verifyData.valid) {
                const errMsg = verifyData.error ? ` (${verifyData.error})` : ' — verifica que sea correcta en elevenlabs.io';
                resultEl.textContent = '❌ API Key inválida' + errMsg;
                resultEl.className = 'text-xs font-semibold px-3 py-1.5 rounded-full bg-red-100 text-red-700';
                resultEl.classList.remove('hidden');
                btn.disabled = false;
                btn.innerHTML = '💾 Guardar API Key de ElevenLabs';
                return;
            }

            // Guardar en settings
            const saveRes = await fetch('api/settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ elevenlabs_api_key: apiKey })
            });
            const saveData = await saveRes.json();

            if (saveData.success) {
                resultEl.textContent = '✅ API Key verificada y guardada correctamente';
                resultEl.className = 'text-xs font-semibold px-3 py-1.5 rounded-full bg-green-100 text-green-700';
                resultEl.classList.remove('hidden');
                document.getElementById('cfg-el-api-key').value = '';
                document.getElementById('cfg-el-api-key').placeholder = '•••••••• (configurada — ingresa una nueva para reemplazar)';
                showToast('✅ API Key de ElevenLabs guardada');
            } else {
                showToast('❌ Error al guardar la API Key', true);
            }
            btn.disabled = false;
            btn.innerHTML = '💾 Guardar API Key de ElevenLabs';
        }

        // ── Agenda: SMTP y SMS (comparten patrón simple fetch/guardar) ──
        async function fetchAgendaSmtp() {
            try {
                const res = await fetch('api/agenda-smtp-config.php');
                const cfg = await res.json();
                document.getElementById('agendaSmtpBadge').innerHTML = cfg.configured
                    ? '<span class="text-emerald-600">✓ Configurado</span>' : '<span class="text-slate-400">Sin configurar</span>';
                document.getElementById('agenda-smtp-host').value = cfg.host || '';
                document.getElementById('agenda-smtp-port').value = cfg.port || 587;
                document.getElementById('agenda-smtp-username').value = cfg.username || '';
                document.getElementById('agenda-smtp-from-email').value = cfg.from_email || '';
                document.getElementById('agenda-smtp-from-name').value = cfg.from_name || '';
                document.getElementById('agenda-smtp-encryption').value = cfg.encryption || 'tls';
            } catch (err) { console.error(err); }
        }
        async function saveAgendaSmtp() {
            const btn = document.getElementById('saveAgendaSmtpBtn');
            const data = {
                host: document.getElementById('agenda-smtp-host').value,
                port: document.getElementById('agenda-smtp-port').value,
                username: document.getElementById('agenda-smtp-username').value,
                password: document.getElementById('agenda-smtp-password').value,
                from_email: document.getElementById('agenda-smtp-from-email').value,
                from_name: document.getElementById('agenda-smtp-from-name').value,
                encryption: document.getElementById('agenda-smtp-encryption').value,
            };
            btn.disabled = true; btn.textContent = 'Guardando...';
            try {
                const res = await fetch('api/agenda-smtp-config.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
                const result = await res.json();
                if (result.success) {
                    showToast('✅ SMTP guardado');
                    document.getElementById('agenda-smtp-password').value = '';
                    fetchAgendaSmtp();
                } else { showToast('❌ ' + (result.error || 'Error al guardar'), true); }
            } catch (err) { showToast('❌ Error al guardar', true); }
            btn.disabled = false; btn.textContent = 'Guardar SMTP';
        }

        async function fetchAgendaSms() {
            try {
                const res = await fetch('api/agenda-sms-config.php');
                const cfg = await res.json();
                document.getElementById('agendaSmsBadge').innerHTML = cfg.configured
                    ? '<span class="text-emerald-600">✓ Configurado</span>' : '<span class="text-slate-400">Sin configurar</span>';
                document.getElementById('agenda-sms-sid').value = cfg.account_sid || '';
                document.getElementById('agenda-sms-from').value = cfg.from_number || '';
            } catch (err) { console.error(err); }
        }
        async function saveAgendaSms() {
            const btn = document.getElementById('saveAgendaSmsBtn');
            const data = {
                account_sid: document.getElementById('agenda-sms-sid').value,
                from_number: document.getElementById('agenda-sms-from').value,
                auth_token: document.getElementById('agenda-sms-token').value,
            };
            btn.disabled = true; btn.textContent = 'Guardando...';
            try {
                const res = await fetch('api/agenda-sms-config.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
                const result = await res.json();
                if (result.success) {
                    showToast('✅ SMS guardado');
                    document.getElementById('agenda-sms-token').value = '';
                    fetchAgendaSms();
                } else { showToast('❌ ' + (result.error || 'Error al guardar'), true); }
            } catch (err) { showToast('❌ Error al guardar', true); }
            btn.disabled = false; btn.textContent = 'Guardar SMS';
        }

        fetchSettings();
        fetchAgendaSmtp();
        fetchAgendaSms();
        lucide.createIcons();
    </script>
</body>
</html>
