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
    <title>Dashboard - Ultra CRM</title>
    <?php if(isset($crm_favicon) && $crm_favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($crm_favicon); ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
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
        .filter-btn-active { background: var(--accent-blue) !important; color: white !important; box-shadow: 0 10px 15px -3px rgba(37,99,235,.3) !important; transform: scale(1.05); }

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
                <a href="index.php" class="nav-link active flex items-center gap-4 px-4 py-3 rounded-xl transition-all">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i>Dashboard</a>
                <a href="prospectos.php" class="nav-link  flex items-center gap-4 px-4 py-3 rounded-xl transition-all">
                    <i data-lucide="users" class="w-5 h-5"></i>Prospectos</a>
                <a href="acciones.php" class="nav-link  flex items-center gap-4 px-4 py-3 rounded-xl transition-all">
                    <i data-lucide="list" class="w-5 h-5"></i>Acciones</a>
                <a href="clientes.php" class="nav-link  flex items-center gap-4 px-4 py-3 rounded-xl transition-all">
                    <i data-lucide="user-check" class="w-5 h-5"></i>Clientes</a>
                <a href="servicios.php" class="nav-link  flex items-center gap-4 px-4 py-3 rounded-xl transition-all">
                    <i data-lucide="briefcase" class="w-5 h-5"></i>Servicios</a>
                <a href="agentes.php" class="nav-link  flex items-center gap-4 px-4 py-3 rounded-xl transition-all">
                    <i data-lucide="bot" class="w-5 h-5"></i>Agentes</a>
                <a href="landings.php" class="nav-link  flex items-center gap-4 px-4 py-3 rounded-xl transition-all">
                    <i data-lucide="rocket" class="w-5 h-5"></i>Landings</a>
                <a href="marketing.php" class="nav-link  flex items-center gap-4 px-4 py-3 rounded-xl transition-all">
                    <i data-lucide="image" class="w-5 h-5"></i>Material de Mkt</a>
                <?php if ($is_admin): ?>
                <a href="usuarios.php" class="nav-link  flex items-center gap-4 px-4 py-3 rounded-xl transition-all">
                    <i data-lucide="users" class="w-5 h-5"></i>Usuarios</a>
                <a href="configuracion.php" class="nav-link  flex items-center gap-4 px-4 py-3 rounded-xl transition-all">
                    <i data-lucide="settings" class="w-5 h-5"></i>Configuración</a>
                <?php endif; ?>
                <a href="perfil.php" class="nav-link  flex items-center gap-4 px-4 py-3 rounded-xl transition-all">
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
            <a href="index.php" class="nav-link active flex items-center gap-3 px-4 py-3 rounded-xl transition-all">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>Dashboard</a>
            <a href="prospectos.php" class="nav-link  flex items-center gap-3 px-4 py-3 rounded-xl transition-all">
                <i data-lucide="users" class="w-5 h-5"></i>Prospectos</a>
            <a href="acciones.php" class="nav-link  flex items-center gap-3 px-4 py-3 rounded-xl transition-all">
                <i data-lucide="list" class="w-5 h-5"></i>Acciones</a>
            <a href="clientes.php" class="nav-link  flex items-center gap-3 px-4 py-3 rounded-xl transition-all">
                <i data-lucide="user-check" class="w-5 h-5"></i>Clientes</a>
            <a href="servicios.php" class="nav-link  flex items-center gap-3 px-4 py-3 rounded-xl transition-all">
                <i data-lucide="briefcase" class="w-5 h-5"></i>Servicios</a>
            <a href="agentes.php" class="nav-link  flex items-center gap-3 px-4 py-3 rounded-xl transition-all">
                <i data-lucide="bot" class="w-5 h-5"></i>Agentes</a>
            <a href="landings.php" class="nav-link  flex items-center gap-3 px-4 py-3 rounded-xl transition-all">
                <i data-lucide="rocket" class="w-5 h-5"></i>Landings</a>
            <a href="marketing.php" class="nav-link  flex items-center gap-3 px-4 py-3 rounded-xl transition-all">
                <i data-lucide="image" class="w-5 h-5"></i>Material de Mkt</a>
            <?php if ($is_admin): ?>
            <a href="usuarios.php" class="nav-link  flex items-center gap-3 px-4 py-3 rounded-xl transition-all">
                <i data-lucide="users" class="w-5 h-5"></i>Usuarios</a>
            <a href="configuracion.php" class="nav-link  flex items-center gap-3 px-4 py-3 rounded-xl transition-all">
                <i data-lucide="settings" class="w-5 h-5"></i>Configuración</a>
            <?php endif; ?>
            <a href="perfil.php" class="nav-link  flex items-center gap-3 px-4 py-3 rounded-xl transition-all">
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
                <h1 class="text-xl font-bold text-slate-900">Dashboard</h1>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2 px-3 py-1 bg-emerald-50 rounded-full border border-emerald-100 hidden sm:flex">
                    <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span></span>
                    <span class="text-[10px] font-black uppercase tracking-widest text-emerald-600">En Vivo</span>
                </div>
                <span class="text-xs font-medium text-slate-500 hidden sm:block">?Hola, <?php echo $_SESSION['user_name']; ?>!</span>
            </div>
        </header>

        <div class="p-6 lg:p-10 max-w-7xl mx-auto w-full space-y-8">
            <!-- KPIs Mejorados: 3 Columnas -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="glass-card p-8 rounded-[2rem]">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400" style="color:var(--card-muted)"><?php echo $is_admin ? 'Usuarios' : 'Prospectos'; ?></span>
                        <div class="w-10 h-10 bg-indigo-50/10 rounded-xl flex items-center justify-center text-blue-400"><i data-lucide="users" class="w-5 h-5"></i></div>
                    </div>
                    <div class="text-4xl font-black text-white" id="kpi-prospects">0</div>
                </div>
                <div class="glass-card p-8 rounded-[2rem]">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400" style="color:var(--card-muted)">Actividad</span>
                        <div class="w-10 h-10 bg-purple-50/10 rounded-xl flex items-center justify-center text-purple-400"><i data-lucide="activity" class="w-5 h-5"></i></div>
                    </div>
                    <div class="text-4xl font-black text-white" id="kpi-actions">0</div>
                </div>
                <div class="glass-card p-8 rounded-[2rem] text-white border-none shadow-xl" style="background:linear-gradient(135deg, #3b82f6, #2563eb)">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-[10px] font-black uppercase tracking-widest text-white/80">Conversión Global</span>
                        <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center text-white"><i data-lucide="zap" class="w-5 h-5"></i></div>
                    </div>
                    <div class="text-4xl font-black" id="kpi-conversion">0%</div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="flex flex-col gap-6 items-center">
                <div class="flex flex-wrap justify-center gap-2" id="filterButtonGroup">
                    <button onclick="setActiveFilter(this, 'all')" class="px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all glass-card border-none" style="color:var(--card-muted)">Todos</button>
                    <button onclick="setActiveFilter(this, 'week')" class="px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all glass-card border-none filter-btn-active">Semana</button>
                    <button onclick="setActiveFilter(this, 'month')" class="px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all glass-card border-none" style="color:var(--card-muted)">Mes</button>
                    <button onclick="setActiveFilter(this, 'year')" class="px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all glass-card border-none" style="color:var(--card-muted)">Año</button>
                    <button onclick="toggleDatePicker()" class="px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all glass-card border-none" style="color:var(--card-muted)">Personalizado</button>
                </div>

                <div id="customDatePicker" class="hidden glass-card p-6 rounded-3xl flex flex-wrap gap-4 items-end animate-in fade-in slide-in-from-top-4 duration-300">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest" style="color:var(--card-muted)">Desde</label>
                        <input type="date" id="startDate" class="bg-slate-900/50 border border-slate-700 rounded-xl p-3 text-sm outline-none text-white">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest" style="color:var(--card-muted)">Hasta</label>
                        <input type="date" id="endDate" class="bg-slate-900/50 border border-slate-700 rounded-xl p-3 text-sm outline-none text-white">
                    </div>
                    <div class="flex gap-2">
                        <button onclick="applyCustomFilter()" style="background:var(--accent-blue)" class="text-white px-6 py-3 rounded-xl font-bold text-xs uppercase tracking-widest shadow-lg shadow-blue-500/20 hover:opacity-90">Filtrar</button>
                        <button onclick="toggleDatePicker()" class="bg-slate-700 text-slate-300 px-6 py-3 rounded-xl font-bold text-xs uppercase tracking-widest hover:bg-slate-600">Cancelar</button>
                    </div>
                </div>
            </div>

            <!-- Gráfico Interactivo -->
            <div class="glass-card rounded-[2.5rem] p-10 min-h-[400px] w-full">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-white">Rendimiento de Leads</h3>
                </div>
                <div id="leadsChart" class="w-full h-[350px]"></div>
            </div>

            <?php if ($is_admin): ?>
            <!-- ── Widgets Admin ── -->
            <div id="adminWidgets" class="space-y-6">
                <!-- Tabla: Top Usuarios -->
                <div class="glass-card rounded-[2.5rem] p-8">
                    <h3 class="text-lg font-bold text-white mb-6 flex items-center gap-3">
                        <i data-lucide="trophy" class="w-5 h-5 text-amber-400"></i>
                        Top Usuarios por Prospectos
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-[10px] font-bold uppercase tracking-widest text-slate-500 border-b border-slate-700/50">
                                    <th class="text-left py-3 pl-2">Usuario</th>
                                    <th class="text-center py-3">Prospectos</th>
                                    <th class="text-center py-3">Actividades</th>
                                    <th class="text-center py-3">Permiso Agentes</th>
                                    <th class="text-right py-3 pr-2">última Actividad</th>
                                </tr>
                            </thead>
                            <tbody id="usersSummaryBody">
                                <tr><td colspan="5" class="text-center py-10 text-slate-500 text-sm">Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Gráfico: Prospectos por Usuario + Actividad -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="glass-card rounded-[2.5rem] p-8">
                        <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                            <i data-lucide="bar-chart-3" class="w-4 h-4 text-blue-400"></i>
                            Prospectos por Usuario
                        </h3>
                        <div id="prospectsPerUserChart" class="w-full h-[250px]"></div>
                    </div>
                    <div class="glass-card rounded-[2.5rem] p-8">
                        <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                            <i data-lucide="activity" class="w-4 h-4 text-purple-400"></i>
                            Actividad por Usuario
                        </h3>
                        <div id="activitiesPerUserChart" class="w-full h-[250px]"></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        let currentPeriod = 'week';
        let chart = null;
        let prospectsUserChart = null;
        let activitiesUserChart = null;

        function toggleMenu() { document.getElementById('mobileMenu').classList.toggle('hidden'); }
        function toggleDatePicker() { document.getElementById('customDatePicker').classList.toggle('hidden'); }
        async function logout() { fetch('api/auth.php?action=logout').then(() => window.location.href = 'login.php'); }

        async function updateStats(period, start = '', end = '') {
            try {
                const res = await fetch(`api/stats.php?period=${period}&start_date=${start}&end_date=${end}`);
                const data = await res.json();
                document.getElementById('kpi-prospects').innerText = data.prospects || 0;
                document.getElementById('kpi-actions').innerText = data.actions || data.activities || 0;
                document.getElementById('kpi-conversion').innerText = (data.conversion_rate || 0) + '%';
                
                // Update chart
                if (data.chart_data && data.chart_data.length > 0) {
                    const categories = data.chart_data.map(d => {
                        const dateParts = d.date.split('-');
                        const date = new Date(dateParts[0], dateParts[1] - 1, dateParts[2]);
                        return date.toLocaleDateString('es-ES', { month: 'short', day: 'numeric' });
                    });
                    const seriesData = data.chart_data.map(d => d.count);
                    
                    if (!chart) {
                        initChart(categories, seriesData);
                    } else {
                        chart.updateOptions({ xaxis: { categories } });
                        chart.updateSeries([{ data: seriesData }]);
                    }
                } else if (chart) {
                    chart.updateSeries([{ data: [] }]);
                }

                // Admin widgets
                if (data.users_summary && data.users_summary.length > 0) {
                    renderUsersTable(data.users_summary);
                    renderProspectsPerUserChart(data.prospects_per_user);
                    renderActivitiesPerUserChart(data.activities_per_user);
                }
            } catch (err) { console.error(err); }
        }

        function renderUsersTable(users) {
            var tbody = document.getElementById('usersSummaryBody');
            if (!tbody) return;
            tbody.innerHTML = users.map(function(u) {
                var lastAct = u.last_activity ? new Date(u.last_activity).toLocaleDateString('es-ES', { day:'numeric', month:'short', hour:'2-digit', minute:'2-digit' }) : '?';
                var agentBadge = u.can_create_agents
                    ? '<span class="text-[9px] bg-emerald-500/20 text-emerald-400 px-2 py-0.5 rounded-full font-bold">S?</span>'
                    : '<span class="text-[9px] bg-slate-600/30 text-slate-400 px-2 py-0.5 rounded-full font-bold">No</span>';
                var statusBadge = u.active
                    ? '<span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>'
                    : '<span class="w-1.5 h-1.5 rounded-full bg-slate-500 inline-block"></span>';
                return '<tr class="border-b border-slate-700/30 hover:bg-slate-800/30 transition">' +
                    '<td class="py-3 pl-2"><div class="flex items-center gap-2"><span class="text-white text-sm font-semibold">' + esc(u.name) + '</span><span class="text-slate-400 text-[10px] hidden md:inline">' + esc(u.email) + '</span></div></td>' +
                    '<td class="text-center py-3"><span class="text-white font-bold">' + u.prospects + '</span></td>' +
                    '<td class="text-center py-3"><span class="text-slate-300 font-medium">' + u.activities + '</span></td>' +
                    '<td class="text-center py-3">' + agentBadge + '</td>' +
                    '<td class="text-right py-3 pr-2 text-[11px] text-slate-400">' + lastAct + '</td>' +
                '</tr>';
            }).join('');
        }

        function renderProspectsPerUserChart(data) {
            if (!data || !data.labels || data.labels.length === 0) return;
            if (prospectsUserChart) { prospectsUserChart.destroy(); prospectsUserChart = null; }
            var el = document.getElementById('prospectsPerUserChart');
            if (!el) return;
            var opts = {
                series: [{ name: 'Prospectos', data: data.data }],
                chart: { type: 'bar', height: 250, toolbar: { show: false }, fontFamily: 'Inter, sans-serif', background: 'transparent' },
                colors: ['#3b82f6'],
                plotOptions: { bar: { borderRadius: 4, horizontal: true, distributed: false, barHeight: '70%' } },
                dataLabels: { enabled: true, style: { colors: ['#fff'], fontSize: '10px' } },
                xaxis: { categories: data.labels, labels: { style: { colors: '#94a3b8', fontSize: '10px' } } },
                yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '10px' } } },
                grid: { borderColor: 'rgba(255,255,255,0.05)', strokeDashArray: 4 },
                theme: { mode: 'dark' },
                tooltip: { theme: 'dark' }
            };
            prospectsUserChart = new ApexCharts(el, opts);
            prospectsUserChart.render();
        }

        function renderActivitiesPerUserChart(data) {
            if (!data || !data.labels || data.labels.length === 0) return;
            if (activitiesUserChart) { activitiesUserChart.destroy(); activitiesUserChart = null; }
            var el = document.getElementById('activitiesPerUserChart');
            if (!el) return;
            var opts = {
                series: [{ name: 'Actividades', data: data.data }],
                chart: { type: 'bar', height: 250, toolbar: { show: false }, fontFamily: 'Inter, sans-serif', background: 'transparent' },
                colors: ['#a855f7'],
                plotOptions: { bar: { borderRadius: 4, horizontal: true, distributed: false, barHeight: '70%' } },
                dataLabels: { enabled: true, style: { colors: ['#fff'], fontSize: '10px' } },
                xaxis: { categories: data.labels, labels: { style: { colors: '#94a3b8', fontSize: '10px' } } },
                yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '10px' } } },
                grid: { borderColor: 'rgba(255,255,255,0.05)', strokeDashArray: 4 },
                theme: { mode: 'dark' },
                tooltip: { theme: 'dark' }
            };
            activitiesUserChart = new ApexCharts(el, opts);
            activitiesUserChart.render();
        }

        function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

        function initChart(categories, data) {
            const options = {
                series: [{ name: 'Nuevos Prospectos', data: data }],
                chart: {
                    type: 'area',
                    height: 350,
                    toolbar: { show: false },
                    fontFamily: 'Inter, sans-serif',
                    background: 'transparent'
                },
                colors: ['#3b82f6'],
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] }
                },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 3 },
                xaxis: {
                    categories: categories,
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: { style: { colors: '#94a3b8' } }
                },
                yaxis: {
                    labels: { style: { colors: '#94a3b8' } }
                },
                grid: {
                    borderColor: 'rgba(255,255,255,0.05)',
                    strokeDashArray: 4,
                },
                theme: { mode: 'dark' },
                tooltip: { theme: 'dark' }
            };
            chart = new ApexCharts(document.querySelector("#leadsChart"), options);
            chart.render();
        }

        function setActiveFilter(btn, period) {
            document.querySelectorAll('#filterButtonGroup button').forEach(b => {
                b.classList.remove('filter-btn-active');
                b.classList.add('text-slate-500');
            });
            btn.classList.add('filter-btn-active');
            btn.classList.remove('text-slate-500');
            currentPeriod = period;
            document.getElementById('customDatePicker').classList.add('hidden');
            updateStats(period);
        }

        function applyCustomFilter() {
            const start = document.getElementById('startDate').value;
            const end = document.getElementById('endDate').value;
            if(start && end) updateStats('custom', start, end);
        }

        updateStats('week');
        setInterval(() => updateStats(currentPeriod), 10000);
        lucide.createIcons();
    </script>
</body>
</html>
