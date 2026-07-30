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
    <title>Acciones - Ultra CRM</title>
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
            --card-bg: #1e293b;
            --card-muted: #94a3b8;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; color: #0f172a; }
        .glass-card { background: var(--card-bg); border: none; box-shadow: 0 10px 25px rgba(30,41,59,.18); color: white; }

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

        /* OVERRIDES ACCIONES OSCURO */
        .glass-card .text-slate-900 { color: white !important; }
        .glass-card .text-slate-700 { color: #e2e8f0 !important; }
        .glass-card .text-slate-400 { color: var(--card-muted) !important; }
        .glass-card .bg-indigo-50 { background: rgba(59,130,246,0.1) !important; color: #60a5fa !important; }
        .glass-card .text-indigo-600 { color: #60a5fa !important; }
        .glass-card .hover\:border-indigo-200:hover { border-color: rgba(59,130,246,0.5) !important; }
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

                            <a href="acciones.php" class="nav-link active flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

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

                    <a href="acciones.php" class="nav-link active flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

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
                <h1 class="text-xl font-bold text-slate-900">Historial de Acciones</h1>
            </div>
            <div class="flex items-center gap-2 px-3 py-1 bg-emerald-50 rounded-full border border-emerald-100 hidden sm:flex">
                <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span></span>
                <span class="text-[10px] font-black uppercase tracking-widest text-emerald-600">En Vivo</span>
            </div>
        </header>

        <div class="p-4 lg:p-6 border-b border-slate-100 bg-slate-50/50">
            <div class="relative max-w-md">
                <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                <input id="actSearch" type="text" placeholder="Buscar por prospecto o contenido..." oninput="filterActivities()" class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
            </div>
        </div>

        <div class="p-6 lg:p-10 max-w-5xl mx-auto w-full">
            <div id="activity-list" class="space-y-6 pb-32">
                <!-- Acciones -->
            </div>
        </div>
    </main>

    <!-- Footer Móvil -->
    <div class="fixed bottom-6 left-1/2 -translate-x-1/2 w-[90%] max-w-sm bg-white/90 backdrop-blur-xl border border-slate-200 rounded-3xl p-2 flex justify-around items-center shadow-xl z-50 lg:hidden">
        <a href="index.php" class="p-4 text-slate-400"><i data-lucide="layout-dashboard" class="w-6 h-6"></i></a>
        <a href="prospectos.php" class="p-4 text-slate-400"><i data-lucide="users" class="w-6 h-6"></i></a>
        <a href="acciones.php" class="p-4 text-indigo-600"><i data-lucide="list" class="w-6 h-6"></i></a>
        <a href="landings.php" class="p-4 text-slate-400"><i data-lucide="rocket" class="w-6 h-6"></i></a>
        <a href="marketing.php" class="p-4 text-slate-400"><i data-lucide="image" class="w-6 h-6"></i></a>
    </div>

    <script>
        function toggleMenu() { document.getElementById('mobileMenu').classList.toggle('hidden'); }
        async function logout() { fetch('api/auth.php?action=logout').then(() => window.location.href = 'login.php'); }
        let allActivities = [];
        async function fetchActivities() {
            try {
                const urlParams = new URLSearchParams(window.location.search);
                const pId = urlParams.get('prospect_id');
                const url = pId ? `api/activities.php?prospect_id=${pId}` : `api/activities.php`;
                
                const res = await fetch(url);
                const data = await res.json();
                allActivities = Array.isArray(data) ? data : [];
                filterActivities();
            } catch (err) { console.error(err); }
        }

        function filterActivities() {
            const search = (document.getElementById('actSearch')?.value || '').toLowerCase();
            const filtered = search
                ? allActivities.filter(a =>
                    (a.prospect_name || '').toLowerCase().includes(search) ||
                    (a.note || '').toLowerCase().includes(search)
                  )
                : allActivities;
            renderActivities(filtered);
        }

        function renderActivities(data) {
            const list = document.getElementById('activity-list');
            if (data.length === 0) {
                list.innerHTML = `<div class="glass-card p-20 rounded-[2.5rem] text-center text-slate-300"><i data-lucide="clipboard-list" class="w-12 h-12 mx-auto mb-4 opacity-20"></i><p>No hay actividad registrada</p></div>`;
                lucide.createIcons(); return;
            }
            list.innerHTML = data.map(a => `
                <div class="glass-card p-8 rounded-3xl flex gap-6 items-start animate-in fade-in slide-in-from-bottom-4 duration-300 cursor-pointer hover:border-indigo-200 transition-all" onclick="window.location='prospecto.php?id=${a.prospect_id}'">
                    <div class="w-12 h-12 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-600 shrink-0"><i data-lucide="message-square" class="w-5 h-5"></i></div>
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-[10px] font-black uppercase tracking-widest text-indigo-500">${a.prospect_name || 'Prospecto'}</span>
                            <span class="text-[10px] text-slate-400 font-medium">${new Date(a.created_at).toLocaleString()}</span>
                        </div>
                        <p class="text-slate-700 text-sm leading-relaxed">${a.note}</p>
                    </div>
                </div>
            `).join('');
            lucide.createIcons();
        }

        fetchActivities();
        setInterval(fetchActivities, 10000);
        lucide.createIcons();
    </script>
</body>
</html>
