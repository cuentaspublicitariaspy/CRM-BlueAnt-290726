<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
if (($_SESSION['user_role'] ?? '') !== 'admin') { header('Location: index.php'); exit(); }
include 'api/config.php';
$is_admin = ($_SESSION['user_role'] ?? 'subscriber') === 'admin';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - Ultra CRM</title>
    <?php if(isset($crm_favicon) && $crm_favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($crm_favicon); ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --ultrablue: #0d1e56;
            --brandpurple: #6366f1;
            --sidebar-bg: #0d1e56;
            --accent-blue: #2563eb;
            --card-bg: #1e293b;
            --card-muted: #94a3b8;
        }
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        
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

        .list-row {
            display: grid;
            align-items: center;
            grid-template-columns: 1fr auto;
            transition: all 0.1s ease;
            border-bottom: 1px solid #f1f5f9;
            background: white;
        }

        @media (min-width: 768px) {
            .list-row {
                grid-template-columns: 1.5fr 1fr 1fr 1fr 1fr 180px;
                padding: 0 20px;
            }
        }

        .list-row:hover { background-color: #f8fafc; }
        
        .modal-overlay { display:none !important; position:fixed; inset:0; background:rgba(15,23,42,.6); backdrop-filter:blur(6px); z-index:9999; align-items:center; justify-content:center; padding:1rem; }
        .modal-overlay.open { display:flex !important; }
        .modal-inner { transition: transform 0.2s ease, opacity 0.2s ease; transform: scale(0.95); opacity: 0; }
        .modal-overlay.open .modal-inner { transform: scale(1); opacity: 1; }
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

                            <a href="usuarios.php" class="nav-link active flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

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

                    <a href="agentes.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                        <i data-lucide="bot" class="w-5 h-5"></i>Agentes</a>

                    <a href="landings.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                        <i data-lucide="rocket" class="w-5 h-5"></i>Landings</a>

                    <a href="marketing.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                        <i data-lucide="image" class="w-5 h-5"></i>Material de Mkt</a>
                    <a href="agenda.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl transition-all">
                        <i data-lucide="calendar-check" class="w-5 h-5"></i>Agenda</a>

                    <?php if ($is_admin): ?>

                    <a href="usuarios.php" class="nav-link active flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

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

    <!-- --- ?REA DE CONTENIDO PRINCIPAL --- -->
    <main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
        
        <div id="tab-usuarios" class="flex-1 flex flex-col min-h-0 animate-fadeIn">
            <!-- Header Integrado -->
            <div class="p-4 md:p-8 pb-3 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 shrink-0">
                <div class="flex items-center space-x-3">
                    <button onclick="toggleMenu()" class="lg:hidden p-2 -ml-2 text-slate-500"><i data-lucide="menu" class="w-6 h-6"></i></button>
                    <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Gestión de Usuarios</h2>
                    <span id="user-count-badge" class="bg-blue-100 text-blue-700 text-xs font-bold px-2.5 py-1 rounded-full">0</span>
                </div>
                <button onclick="openCreateModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-md flex items-center space-x-2 transition-all">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Nueva Cuenta</span>
                </button>
            </div>

            <!-- Barra de Filtros -->
            <div class="px-4 md:px-8 pb-4 shrink-0">
                <form autocomplete="off">
                <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                    <!-- Buscador -->
                    <div class="md:col-span-6 space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Nombre / Email</label>
                        <div class="relative">
                            <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 w-4 h-4"></i>
                            <input id="userSearch" type="text" placeholder="Buscar usuario..." oninput="renderUsers()" autocomplete="off" class="w-full bg-[#f8fafc] border border-slate-200 rounded-xl pl-10 pr-4 py-2 text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </div>
                    </div>

                    <!-- Filtro Rol -->
                    <div class="md:col-span-3 space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Rol de Cuenta</label>
                        <select id="roleFilter" onchange="renderUsers()" class="w-full bg-[#f8fafc] border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-blue-500 cursor-pointer">
                            <option value="all">Todos los Roles</option>
                            <option value="admin">Administrador</option>
                            <option value="subscriber">Suscriptor</option>
                        </select>
                    </div>

                    <!-- Filtro Estado -->
                    <div class="md:col-span-3 space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Estado de Acceso</label>
                        <select id="statusFilter" onchange="renderUsers()" class="w-full bg-[#f8fafc] border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-blue-500 cursor-pointer">
                            <option value="all">Todos los Estados</option>
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                </div>
                </form>
            </div>

            <!-- Cabecera de la Tabla -->
            <div class="hidden md:grid list-row bg-slate-100/60 h-10 text-[10px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200 shrink-0 mx-4 md:mx-8 rounded-t-xl" style="grid-template-columns:1.5fr 1fr 1fr 1fr 1fr 180px;">
                <div class="pl-5">Usuario (Nombre / Email)</div>
                <div>Landings Asignadas</div>
                <div>Agentes Asignados</div>
                <div>Rol de Sistema</div>
                <div>Prospectos Totales</div>
                <div class="text-right pr-5">Acciones</div>
            </div>

            <!-- Contenedor Desplazable de Usuarios -->
            <div class="flex-1 overflow-y-auto px-4 md:px-8 pb-8">
                <div id="userList" class="bg-white rounded-b-xl shadow-sm border-x border-b border-slate-100 divide-y divide-slate-100">
                    <!-- Inyección dinámica de filas de usuarios -->
                </div>
                
                <div id="loadingState" class="hidden flex flex-col items-center justify-center p-20 text-slate-400 bg-white rounded-b-xl border border-slate-100">
                    <div class="w-8 h-8 border-4 border-slate-200 border-t-blue-600 rounded-full animate-spin mb-4"></div>
                    <p class="text-xs font-medium">Cargando usuarios...</p>
                </div>

                <div id="emptyState" class="hidden flex flex-col items-center justify-center p-20 text-slate-400 bg-white rounded-b-xl border border-slate-100">
                    <i data-lucide="frown" class="w-12 h-12 mb-2 opacity-20"></i>
                    <p class="text-xs font-medium">No se encontraron usuarios con esos filtros</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Bottom Nav Mobile -->
    <div class="fixed bottom-6 left-1/2 -translate-x-1/2 w-[90%] max-w-sm bg-white/90 backdrop-blur-xl border border-slate-200 rounded-3xl p-2 flex justify-around items-center shadow-xl z-50 lg:hidden">
        <a href="index.php" class="p-4 text-slate-400"><i data-lucide="layout-dashboard" class="w-6 h-6"></i></a>
        <a href="usuarios.php" class="p-4 text-indigo-600"><i data-lucide="users" class="w-6 h-6"></i></a>
        <a href="landings.php" class="p-4 text-slate-400"><i data-lucide="rocket" class="w-6 h-6"></i></a>
        <a href="marketing.php" class="p-4 text-slate-400"><i data-lucide="image" class="w-6 h-6"></i></a>
        <a href="perfil.php" class="p-4 text-slate-400"><i data-lucide="user" class="w-6 h-6"></i></a>
    </div>

    <!-- ══ MODAL: Crear Usuario ══ -->
    <div id="createModal" class="modal-overlay">
        <div class="bg-white rounded-3xl p-8 w-full max-w-md shadow-2xl">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-black text-slate-900">Nueva Cuenta</h2>
                <button onclick="closeCreateModal()" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form id="createForm" class="space-y-4" onsubmit="submitCreateUser(event)">
                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1 block">Nombre Completo</label>
                    <input type="text" id="newName" required placeholder="Juan Pérez" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-3 px-5 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all">
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1 block">Email</label>
                    <input type="email" id="newEmail" required placeholder="juan@ejemplo.com" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-3 px-5 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all">
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1 block">Contraseña</label>
                    <input type="password" id="newPassword" required minlength="6" placeholder="Mínimo 6 caracteres" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-3 px-5 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all">
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1 block">Rol</label>
                    <select id="newRole" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-3 px-5 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all">
                        <option value="subscriber">Suscriptor</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1 block">Slug <span class="text-slate-300 font-normal normal-case tracking-normal">(para URL pública)</span></label>
                    <input type="text" id="newSlug" placeholder="juan-perez" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-3 px-5 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all">
                </div>
                <div id="createError" class="hidden text-sm text-red-500 font-medium bg-red-50 px-4 py-3 rounded-xl"></div>
                <button type="submit" id="createBtn" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-4 rounded-2xl font-bold transition-all active:scale-95 flex items-center justify-center gap-2">
                    <i data-lucide="user-plus" class="w-4 h-4"></i> Crear Cuenta
                </button>
            </form>
        </div>
    </div>

    <!-- ══ MODAL: Configurar Suscriptor ══ -->
    <div id="configModal" class="modal-overlay">
        <div class="modal-inner bg-white w-full max-w-[520px] rounded-[32px] shadow-2xl border border-slate-100 flex flex-col overflow-hidden max-h-[90vh]">
            
            <!-- Modal Header -->
            <div class="p-8 pb-5 flex justify-between items-start shrink-0">
                <div>
                    <h3 class="text-[26px] font-bold text-slate-900 tracking-tight leading-tight">Configurar</h3>
                    <p class="text-sm font-medium text-slate-400 mt-1 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                        Usuario: <strong id="configModalUser" class="text-slate-600 font-semibold">Cargando...</strong>
                    </p>
                </div>
                <button onclick="closeConfigModal(false)" class="w-10 h-10 rounded-full bg-slate-50 hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition flex items-center justify-center border border-slate-100/80 shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- TAB BAR SELECTION -->
            <div class="px-8 border-b border-slate-100 shrink-0">
                <div class="flex gap-6 text-sm font-semibold">
                    <button onclick="switchConfigTab('landings')" id="tab-btn-landings" class="pb-3 text-blue-600 border-b-2 border-blue-600 relative transition-all duration-200">
                        Landings
                        <span id="badge-count" class="ml-1.5 px-1.5 py-0.5 text-[10px] rounded-full bg-slate-100 text-slate-500 font-bold">0</span>
                    </button>
                    <button onclick="switchConfigTab('agentes')" id="tab-btn-agentes" class="pb-3 text-slate-400 hover:text-slate-600 border-b-2 border-transparent transition-all duration-200 flex items-center gap-1.5">
                        Agentes
                        <span id="agents-badge-count" class="ml-1.5 px-1.5 py-0.5 text-[10px] rounded-full bg-slate-100 text-slate-500 font-bold">0</span>
                    </button>
                    <button onclick="switchConfigTab('seguridad')" id="tab-btn-seguridad" class="pb-3 text-slate-400 hover:text-slate-600 border-b-2 border-transparent transition-all duration-200 flex items-center gap-1.5">
                        Contraseña
                    </button>
                </div>
            </div>

            <!-- Modal Scrollable Content Section -->
            <div class="p-8 pt-6 overflow-y-auto flex-1 space-y-5">
                
                <!-- TAB 1: LANDINGS CONTENT -->
                <div id="tab-content-landings" class="space-y-4">
                    <!-- Informative Alert Box -->
                    <div class="bg-amber-50/90 border border-amber-200/60 p-4 rounded-2xl flex items-start space-x-3 shadow-sm shadow-amber-50/20">
                        <span class="text-amber-500 text-lg leading-none mt-0.5">✅</span>
                        <p class="text-xs font-semibold text-amber-800 leading-relaxed">
                            Activa las landings que este suscriptor puede usar. Cada una genera su propia URL de tracking para capturar leads.
                        </p>
                    </div>

                    <!-- Landings Items Container -->
                    <div class="space-y-3" id="config-landings-list">
                        <!-- Items rendered dynamically via Javascript -->
                    </div>
                </div>

                <!-- TAB 2: AGENTES CONTENT -->
                <div id="tab-content-agentes" class="space-y-5 hidden">
                    <!-- Subtitle descriptive helper modified for AI agent context -->
                    <div class="bg-amber-50/90 border border-amber-200/60 p-4 rounded-2xl flex items-start space-x-3 shadow-sm shadow-amber-50/20">
                        <span class="text-amber-500 text-lg leading-none mt-0.5">🤖</span>
                        <p class="text-xs font-semibold text-amber-800 leading-relaxed">
                            Tilda los agentes conversacionales que quieres asignar a este usuario para que interactúe automáticamente con <strong>sus propios prospectos</strong>. Puedes elegir uno, varios o todos.
                        </p>
                    </div>

                    <!-- Agents Items List -->
                    <div class="space-y-3" id="config-agents-list">
                        <!-- Items rendered dynamically via Javascript -->
                    </div>

                    <!-- Permissions Config Card: Permitir Crear Agente -->
                    <div class="space-y-2 pt-2 border-t border-slate-100">
                        <label class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Permisos de Creación</label>
                        <div onclick="toggleAllowCreateAgent()" class="group border-2 border-slate-100 hover:border-blue-100 bg-slate-50/40 hover:bg-blue-50/10 p-4 rounded-2xl flex items-center justify-between cursor-pointer transition duration-200">
                            <div class="flex items-center space-x-4 flex-1 pr-4">
                                <!-- Checkbox box -->
                                <div id="allow-agent-checkbox-box" class="w-[22px] h-[22px] rounded-lg border-2 border-slate-300 flex items-center justify-center transition duration-200 bg-white">
                                    <svg class="w-3.5 h-3.5 text-white hidden" id="allow-agent-check-icon" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-slate-800 group-hover:text-blue-900 transition">Permitir Crear Agente</div>
                                    <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">Habilita a este usuario para diseñar, configurar y entrenar sus propios agentes conversacionales.</p>
                                </div>
                            </div>
                            <span id="allow-agent-status-badge" class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider rounded-full bg-slate-100 text-slate-500 border border-slate-200 transition">Desactivado</span>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: CONtraseña CONTENT -->
                <div id="tab-content-seguridad" class="space-y-5 hidden">
                    <div class="bg-blue-50/90 border border-blue-200/60 p-4 rounded-2xl flex items-start space-x-3 shadow-sm shadow-blue-50/20">
                        <span class="text-blue-500 text-lg leading-none mt-0.5">🔒</span>
                        <p class="text-xs font-semibold text-blue-800 leading-relaxed">
                            Cambia la contraseña de acceso de este usuario. Debe tener al menos 6 caracteres.
                        </p>
                    </div>

                    <div class="space-y-4 pt-2">
                        <div>
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 block">Nueva Contraseña</label>
                            <input type="password" id="change-pwd-input" placeholder="Ingresa la nueva contraseña..." class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-3.5 px-5 outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all text-sm font-semibold">
                        </div>

                        <button onclick="changeUserPassword()" class="w-full py-4 px-6 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl font-bold text-sm shadow-lg shadow-blue-500/10 transition flex items-center justify-center gap-2">
                            <span>Actualizar Contraseña</span>
                        </button>
                    </div>
                </div>

            </div>

            <!-- Modal Footer -->
            <div class="p-8 pt-4 bg-slate-50/50 border-t border-slate-100 flex flex-col sm:flex-row gap-3 shrink-0">
                <button onclick="closeConfigModal(true)" class="w-full py-4 px-6 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl font-bold text-sm shadow-lg shadow-blue-500/10 transition flex items-center justify-center gap-2 order-1 sm:order-2">
                    <span>Guardar Cambios</span>
                </button>
                <button onclick="closeConfigModal(false)" class="w-full py-4 px-6 bg-white hover:bg-slate-100 text-slate-600 hover:text-slate-800 border border-slate-200 rounded-2xl font-bold text-sm transition flex items-center justify-center order-2 sm:order-1">
                    <span>Cerrar</span>
                </button>
            </div>

        </div>
    </div>

    <!-- ══ MODAL: Editar Usuario ══ -->
    <div id="editModal" class="modal-overlay">
        <div class="bg-white rounded-3xl p-8 w-full max-w-md shadow-2xl">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-black text-slate-900">Editar Usuario</h2>
                <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form id="editForm" class="space-y-4" onsubmit="submitEditUser(event)">
                <input type="hidden" id="editUserId" value="">
                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1 block">Nombre Completo</label>
                    <input type="text" id="editName" required placeholder="Juan Pérez" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-3 px-5 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all">
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1 block">Email</label>
                    <input type="email" id="editEmail" required placeholder="juan@ejemplo.com" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-3 px-5 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all">
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1 block">Rol</label>
                    <select id="editRole" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-3 px-5 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all">
                        <option value="subscriber">Suscriptor</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1 block">Slug <span class="text-slate-300 font-normal normal-case tracking-normal">(para URL pública)</span></label>
                    <input type="text" id="editSlug" placeholder="juan-perez" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-3 px-5 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all">
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1 block">Nueva Contraseña <span class="text-slate-300 font-normal normal-case tracking-normal">(dejar vacío para mantener)</span></label>
                    <input type="password" id="editPassword" minlength="6" placeholder="Mínimo 6 caracteres" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-3 px-5 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all">
                </div>
                <div id="editError" class="hidden text-sm text-red-500 font-medium bg-red-50 px-4 py-3 rounded-xl"></div>
                <button type="submit" id="editBtn" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-4 rounded-2xl font-bold transition-all active:scale-95 flex items-center justify-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i> Guardar Cambios
                </button>
            </form>
        </div>
    </div>

    <!-- ══ MODAL: Confirmación Visual (Eliminar) ══ -->
    <div id="confirmModal" class="modal-overlay">
        <div class="modal-inner bg-white w-full max-w-sm rounded-[32px] shadow-2xl border border-slate-100 p-8 text-center">
            <div class="w-16 h-16 mx-auto mb-4 bg-gradient-to-br from-rose-100 to-red-100 rounded-full flex items-center justify-center shadow-md shadow-rose-200/50">
                <i data-lucide="alert-triangle" class="w-8 h-8 text-rose-600"></i>
            </div>
            <h3 id="confirmTitle" class="text-lg font-black text-slate-900 mb-2">?Eliminar?</h3>
            <p id="confirmMessage" class="text-sm text-slate-500 font-medium leading-relaxed mb-6">Esta accion no se puede deshacer.</p>
            <div class="flex gap-3">
                <button onclick="closeConfirm()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-3 px-5 rounded-xl transition-all active:scale-[0.97]">Cancelar</button>
                <button id="confirmDeleteBtn" onclick="executeConfirm()" class="flex-1 bg-gradient-to-tr from-rose-500 to-red-500 hover:from-rose-600 hover:to-red-600 text-white font-bold py-3 px-5 rounded-xl shadow-lg transition-all active:scale-[0.97]">Eliminar</button>
            </div>
        </div>
    </div>

    <!-- TOAST NOTIFICATION POPUPS (No-Alert System) -->
    <div id="toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col space-y-3 pointer-events-none"></div>

<script>
const loggedInUserId = <?php echo (int)$_SESSION['user_id']; ?>;
function toggleMenu() { document.getElementById('mobileMenu').classList.toggle('hidden'); }
async function logout() { await fetch('api/auth.php?action=logout'); window.location.href = 'login.php'; }
function esc(str) { return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
// Safe escape for use inside onclick="..." single-quoted JS string args
function escQ(str) { return String(str||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'"); }
function suggestSlug(email) {
    return String(email||'').split('@')[0].toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
}

let allUsersData = [];
let expandedUsers = [];

function toggleUserExpand(id) {
    const userId = id.toString();
    const idx = expandedUsers.indexOf(userId);
    if (idx > -1) expandedUsers.splice(idx, 1);
    else expandedUsers.push(userId);
    renderUsers();
}

// ── Modal Crear Usuario ──
function openCreateModal() {
    document.getElementById('createForm').reset();
    document.getElementById('createError').classList.add('hidden');
    document.getElementById('createModal').classList.add('open');
    lucide.createIcons();
}
function closeCreateModal() { document.getElementById('createModal').classList.remove('open'); }

async function submitCreateUser(e) {
    e.preventDefault();
    const btn = document.getElementById('createBtn');
    const err = document.getElementById('createError');
    btn.disabled = true;
    btn.textContent = 'Creando...';
    err.classList.add('hidden');

    const slug = document.getElementById('newSlug').value.trim() || suggestSlug(document.getElementById('newEmail').value.trim());
    const payload = {
        action:   'create_user',
        name:     document.getElementById('newName').value.trim(),
        email:    document.getElementById('newEmail').value.trim(),
        password: document.getElementById('newPassword').value,
        role:     document.getElementById('newRole').value,
        slug:     slug,
    };

    try {
        const res  = await fetch('api/users.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
        const data = await res.json();
        if (data.success) {
            closeCreateModal();
            loadUsers();
        } else {
            err.textContent = data.error || 'Error al crear el usuario';
            err.classList.remove('hidden');
        }
    } catch(ex) {
        err.textContent = 'Error de red: ' + ex.message;
        err.classList.remove('hidden');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="user-plus" class="w-4 h-4"></i> Crear Cuenta';
        lucide.createIcons();
    }
}

// ── Modal Asignar Landings ──
let currentAssignUserId = null;

// ── Unified Configuration Modal Logic ──
let currentUserId = null;
let currentTab = 'landings'; // 'landings' or 'agentes'
let userLandings = [];
let userAgents = [];
let userCanCreateAgents = false;

// Open configuration modal
async function openConfigModal(userId, userName, tab) {
    tab = tab || 'landings';
    currentUserId = userId;

    var nameEl = document.getElementById('configModalUser');
    if (nameEl) nameEl.textContent = userName;

    var pwdInput = document.getElementById('change-pwd-input');
    if (pwdInput) pwdInput.value = '';

    switchConfigTab(tab);

    var modal = document.getElementById('configModal');
    if (!modal) { console.error('[CRM] configModal not found!'); return; }
    modal.classList.add('open');

    await loadConfigData(userId);
}

// Close configuration modal
function closeConfigModal(showSaveToast = false) {
    const modal = document.getElementById('configModal');
    if (!modal) return;
    
    modal.classList.remove('open');
    
    setTimeout(() => {
        currentUserId = null;
        if (showSaveToast) {
            showToast('?Configuración guardada exitosamente!', 'success');
            loadUsers();
        } else {
            showToast('Se cerr? el panel de configuración.', 'info');
        }
    }, 200);
}

async function loadConfigData(userId) {
    const landingsList = document.getElementById('config-landings-list');
    landingsList.innerHTML = '<div class="text-center py-8 text-slate-400 text-sm">Cargando landings...</div>';
    
    const agentsList = document.getElementById('config-agents-list');
    agentsList.innerHTML = '<div class="text-center py-8 text-slate-400 text-sm">Cargando agentes...</div>';

    try {
        // Fetch landings
        const resL = await fetch('api/users.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'get_user_landings', user_id: userId })
        });
        userLandings = await resL.json();
        
        // Fetch agents
        const resA = await fetch('api/users.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'get_user_agents', user_id: userId })
        });
        userAgents = await resA.json();

        // Get can_create_agents flag from allUsersData
        const user = allUsersData.find(u => u.id == userId);
        userCanCreateAgents = user ? parseInt(user.can_create_agents) === 1 : false;

        renderLandingsList();
        renderAgentsList();
        updateModalStats();
        updateAllowCreateAgentUI();
    } catch (e) {
        landingsList.innerHTML = `<p class="text-red-500 text-sm text-center">Error al cargar datos: ${e.message}</p>`;
        agentsList.innerHTML = `<p class="text-red-500 text-sm text-center">Error al cargar datos: ${e.message}</p>`;
    }
}

function switchConfigTab(tabId) {
    currentTab = tabId;
    const landingsBtn = document.getElementById('tab-btn-landings');
    const agentesBtn = document.getElementById('tab-btn-agentes');
    const seguridadBtn = document.getElementById('tab-btn-seguridad');
    
    const landingsContent = document.getElementById('tab-content-landings');
    const agentesContent = document.getElementById('tab-content-agentes');
    const seguridadContent = document.getElementById('tab-content-seguridad');

    if (tabId === 'landings') {
        landingsBtn.className = "pb-3 text-blue-600 border-b-2 border-blue-600 relative transition-all duration-200";
        agentesBtn.className = "pb-3 text-slate-400 hover:text-slate-600 border-b-2 border-transparent transition-all duration-200 flex items-center gap-1.5";
        seguridadBtn.className = "pb-3 text-slate-400 hover:text-slate-600 border-b-2 border-transparent transition-all duration-200 flex items-center gap-1.5";
        landingsContent.classList.remove('hidden');
        agentesContent.classList.add('hidden');
        seguridadContent.classList.add('hidden');
    } else if (tabId === 'agentes') {
        agentesBtn.className = "pb-3 text-blue-600 border-b-2 border-blue-600 relative transition-all duration-200 flex items-center gap-1.5";
        landingsBtn.className = "pb-3 text-slate-400 hover:text-slate-600 border-b-2 border-transparent transition-all duration-200";
        seguridadBtn.className = "pb-3 text-slate-400 hover:text-slate-600 border-b-2 border-transparent transition-all duration-200 flex items-center gap-1.5";
        landingsContent.classList.add('hidden');
        agentesContent.classList.remove('hidden');
        seguridadContent.classList.add('hidden');
    } else {
        seguridadBtn.className = "pb-3 text-blue-600 border-b-2 border-blue-600 relative transition-all duration-200 flex items-center gap-1.5";
        landingsBtn.className = "pb-3 text-slate-400 hover:text-slate-600 border-b-2 border-transparent transition-all duration-200";
        agentesBtn.className = "pb-3 text-slate-400 hover:text-slate-600 border-b-2 border-transparent transition-all duration-200 flex items-center gap-1.5";
        landingsContent.classList.add('hidden');
        agentesContent.classList.add('hidden');
        seguridadContent.classList.remove('hidden');
    }
}

async function changeUserPassword() {
    const pwdInput = document.getElementById('change-pwd-input');
    if (!pwdInput) return;
    
    const password = pwdInput.value.trim();
    if (!password) {
        showToast('Por favor, ingresa una contraseña.', 'info');
        return;
    }
    if (password.length < 6) {
        showToast('La contraseña debe tener al menos 6 caracteres.', 'info');
        return;
    }
    
    try {
        const res = await fetch('api/users.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'change_password', user_id: currentUserId, password })
        });
        const data = await res.json();
        if (data.success) {
            pwdInput.value = '';
            showToast('?Contraseña actualizada exitosamente!', 'success');
            closeConfigModal(true);
        } else {
            showToast('Error al actualizar contraseña: ' + (data.error || 'Desconocido'), 'info');
        }
    } catch (e) {
        showToast('Error de red al actualizar contraseña', 'info');
    }
}

function renderLandingsList() {
    const container = document.getElementById('config-landings-list');
    if (!container) return;

    if (!Array.isArray(userLandings) || userLandings.length === 0) {
        container.innerHTML = '<p class="text-slate-400 text-sm text-center py-8">No hay landings disponibles.</p>';
        return;
    }

    container.innerHTML = userLandings.map(l => {
        const isChecked = parseInt(l.assigned) === 1;
        const cardClass = isChecked 
            ? 'border-blue-200 bg-blue-50/10 hover:border-blue-300 shadow-sm' 
            : 'border-slate-100 bg-white hover:border-slate-200';
        
        const badgeClass = isChecked
            ? 'bg-emerald-50 text-emerald-700 border-emerald-200/60'
            : 'bg-slate-100 text-slate-400 border-slate-200/50';

        const badgeText = isChecked ? 'ASIGNADO' : 'SIN ASIGNAR';

        return `
            <div onclick="toggleConfigLanding(${l.id}, ${!isChecked})" class="group border-2 ${cardClass} p-4 rounded-2xl flex items-center justify-between cursor-pointer transition duration-150">
                <div class="flex items-center space-x-4">
                    <div class="w-[22px] h-[22px] rounded-lg border-2 flex items-center justify-center transition-all ${isChecked ? 'bg-blue-600 border-blue-600' : 'bg-white border-slate-300 group-hover:border-slate-400'}">
                        <svg class="w-3.5 h-3.5 text-white ${isChecked ? 'block' : 'hidden'}" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-slate-800 transition group-hover:text-blue-900">${esc(l.title)}</h4>
                        <span class="text-xs text-slate-400 font-medium">${esc(l.description || 'Sin descripción')}</span>
                    </div>
                </div>
                <span class="px-3 py-1 text-[10px] font-bold tracking-wider rounded-full border ${badgeClass} transition">
                    ${badgeText}
                </span>
            </div>
        `;
    }).join('');
}

async function toggleConfigLanding(landingId, assign) {
    const landing = userLandings.find(l => l.id === landingId);
    if (landing) {
        landing.assigned = assign ? 1 : 0;
        renderLandingsList();
        updateModalStats();
        
        const actionText = assign ? 'asignada con éxito' : 'retirada de la lista';
        showToast(`Landing "${landing.title}" ${actionText}.`, assign ? 'success' : 'info');
    }

    try {
        const res = await fetch('api/users.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'assign_landing', user_id: currentUserId, landing_id: landingId, assign })
        });
        const data = await res.json();
        if (!data.success) {
            if (landing) {
                landing.assigned = assign ? 0 : 1;
                renderLandingsList();
                updateModalStats();
            }
            showToast('Error al actualizar landing: ' + (data.error || 'Desconocido'), 'info');
        }
    } catch (e) {
        if (landing) {
            landing.assigned = assign ? 0 : 1;
            renderLandingsList();
            updateModalStats();
        }
        showToast('Error de red al conectar con el servidor', 'info');
    }
}

function renderAgentsList() {
    const container = document.getElementById('config-agents-list');
    if (!container) return;

    if (!Array.isArray(userAgents) || userAgents.length === 0) {
        container.innerHTML = '<p class="text-slate-400 text-sm text-center py-8">No hay agentes disponibles en el sistema.</p>';
        return;
    }

    container.innerHTML = userAgents.map(a => {
        const isChecked = parseInt(a.assigned) === 1;
        const isOwn = parseInt(a.is_own) === 1;
        const isActive = parseInt(a.is_active) === 1;
        
        const cardClass = isChecked 
            ? 'border-blue-200 bg-blue-50/10 hover:border-blue-300 shadow-sm' 
            : 'border-slate-100 bg-white hover:border-slate-200';
        
        const badgeClass = isChecked
            ? 'bg-emerald-50 text-emerald-700 border-emerald-200/60'
            : 'bg-slate-100 text-slate-400 border-slate-200/50';

        let badgeText = isChecked ? 'ASIGNADO' : 'SIN ASIGNAR';
        if (isOwn) badgeText = 'PROPIO';

        const disabledAttr = isOwn ? 'style="pointer-events: none; opacity: 0.6;" title="Agente propio, siempre asignado"' : '';

        const ownerTag = a.owner_name ? `<span class="text-[9px] bg-violet-100 text-violet-600 px-1.5 py-0.5 rounded font-bold ml-1.5">De: ${esc(a.owner_name)}</span>` : `<span class="text-[9px] bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded font-bold ml-1.5">Admin</span>`;

        return `
            <div ${disabledAttr} onclick="toggleConfigAgent('${a.id}', ${!isChecked})" class="group border-2 ${cardClass} p-4 rounded-2xl flex items-center justify-between cursor-pointer transition duration-150">
                <div class="flex items-center space-x-4">
                    <div class="w-[22px] h-[22px] rounded-lg border-2 flex items-center justify-center transition-all ${isChecked ? 'bg-blue-600 border-blue-600' : 'bg-white border-slate-300 group-hover:border-slate-400'}">
                        <svg class="w-3.5 h-3.5 text-white ${isChecked ? 'block' : 'hidden'}" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center">
                            <h4 class="text-sm font-bold text-slate-800 transition group-hover:text-blue-900">${esc(a.name)}</h4>
                            ${ownerTag}
                            ${!isActive ? '<span class="text-[9px] bg-rose-100 text-rose-600 px-1.5 py-0.5 rounded font-bold ml-1.5">Desactivado</span>' : ''}
                        </div>
                        <span class="text-xs text-slate-400 font-medium">${esc(a.model)} ? ${a.widget_style === 'bubble' ? 'Bubble' : 'Panel'}</span>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    ${!isOwn ? `<button onclick="event.stopPropagation(); toggleConfigAgentActive('${a.id}')" class="text-[9px] font-bold uppercase px-2 py-1 rounded-full border transition-all ${isActive ? 'border-slate-200 text-slate-400 hover:border-rose-300 hover:text-rose-500 bg-white' : 'border-emerald-250 text-emerald-600 hover:bg-emerald-50 bg-white'}" id="cfg-aactive-${a.id}">${isActive ? 'Desactivar' : 'Activar'}</button>` : ''}
                    <span class="px-3 py-1 text-[10px] font-bold tracking-wider rounded-full border ${badgeClass} transition">
                        ${badgeText}
                    </span>
                </div>
            </div>
        `;
    }).join('');
}

async function toggleConfigAgent(agentId, assign) {
    const agent = userAgents.find(a => a.id === agentId);
    if (agent) {
        agent.assigned = assign ? 1 : 0;
        renderAgentsList();
        updateModalStats();
        
        const actionText = assign ? 'asignado con éxito' : 'removido de la lista';
        showToast(`Agente "${agent.name}" ${actionText}.`, assign ? 'success' : 'info');
    }

    try {
        const res = await fetch('api/users.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'assign_agent', user_id: currentUserId, agent_id: agentId, assign })
        });
        const data = await res.json();
        if (!data.success) {
            if (agent) {
                agent.assigned = assign ? 0 : 1;
                renderAgentsList();
                updateModalStats();
            }
            showToast('Error al actualizar agente: ' + (data.error || 'Desconocido'), 'info');
        }
    } catch (e) {
        if (agent) {
            agent.assigned = assign ? 0 : 1;
            renderAgentsList();
            updateModalStats();
        }
        showToast('Error de red al conectar con el servidor', 'info');
    }
}

async function toggleConfigAgentActive(agentId) {
    const agent = userAgents.find(a => a.id === agentId);
    if (!agent) return;
    
    const isCurrentlyActive = parseInt(agent.is_active) === 1;
    const newActiveState = isCurrentlyActive ? 0 : 1;
    
    agent.is_active = newActiveState;
    renderAgentsList();

    const actionText = newActiveState ? 'activado' : 'desactivado';
    showToast(`Agente "${agent.name}" ${actionText} con éxito.`, newActiveState ? 'success' : 'info');

    try {
        const res = await fetch('api/users.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'toggle_agent_active', agent_id: agentId })
        });
        const data = await res.json();
        if (!data.success) {
            agent.is_active = isCurrentlyActive;
            renderAgentsList();
            showToast('Error al cambiar estado del agente: ' + (data.error || 'Desconocido'), 'info');
        }
    } catch (e) {
        agent.is_active = isCurrentlyActive;
        renderAgentsList();
        showToast('Error de red al cambiar estado del agente', 'info');
    }
}

async function toggleAllowCreateAgent() {
    userCanCreateAgents = !userCanCreateAgents;
    updateAllowCreateAgentUI();

    const actionText = userCanCreateAgents ? 'Permiso de creación habilitado' : 'Permiso deshabilitado';
    showToast(`${actionText} para agentes conversacionales autónomos.`, userCanCreateAgents ? 'success' : 'info');

    try {
        const res = await fetch('api/users.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'toggle_can_create_agents', user_id: currentUserId })
        });
        const data = await res.json();
        if (data.success) {
            const user = allUsersData.find(u => u.id == currentUserId);
            if (user) user.can_create_agents = data.can_create_agents;
        } else {
            userCanCreateAgents = !userCanCreateAgents;
            updateAllowCreateAgentUI();
            showToast('Error al cambiar permiso: ' + (data.error || 'Desconocido'), 'info');
        }
    } catch (e) {
        userCanCreateAgents = !userCanCreateAgents;
        updateAllowCreateAgentUI();
        showToast('Error de red al cambiar permiso', 'info');
    }
}

function updateAllowCreateAgentUI() {
    const cbBox = document.getElementById('allow-agent-checkbox-box');
    const cbCheck = document.getElementById('allow-agent-check-icon');
    const cbBadge = document.getElementById('allow-agent-status-badge');

    if (cbBox && cbCheck && cbBadge) {
        if (userCanCreateAgents) {
            cbBox.className = "w-[22px] h-[22px] rounded-lg border-2 border-blue-600 bg-blue-600 flex items-center justify-center transition duration-200";
            cbCheck.classList.remove('hidden');
            cbBadge.className = "px-3 py-1 text-[10px] font-bold uppercase tracking-wider rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 transition";
            cbBadge.innerText = 'Habilitado';
        } else {
            cbBox.className = "w-[22px] h-[22px] rounded-lg border-2 border-slate-300 bg-white flex items-center justify-center transition duration-200";
            cbCheck.classList.add('hidden');
            cbBadge.className = "px-3 py-1 text-[10px] font-bold uppercase tracking-wider rounded-full bg-slate-100 text-slate-500 border border-slate-200 transition";
            cbBadge.innerText = 'Desactivado';
        }
    }
}

function updateModalStats() {
    const assignedLandingsCount = Array.isArray(userLandings) ? userLandings.filter(l => parseInt(l.assigned) === 1).length : 0;
    const badgeCount = document.getElementById('badge-count');

    if (badgeCount) {
        badgeCount.innerText = assignedLandingsCount;
        if (assignedLandingsCount > 0) {
            badgeCount.className = "ml-1.5 px-1.5 py-0.5 text-[10px] rounded-full bg-blue-600 text-white font-bold transition-all";
        } else {
            badgeCount.className = "ml-1.5 px-1.5 py-0.5 text-[10px] rounded-full bg-slate-100 text-slate-500 font-bold transition-all";
        }
    }

    const assignedAgentsCount = Array.isArray(userAgents) ? userAgents.filter(a => parseInt(a.assigned) === 1).length : 0;
    const agentsBadgeCount = document.getElementById('agents-badge-count');

    if (agentsBadgeCount) {
        agentsBadgeCount.innerText = assignedAgentsCount;
        if (assignedAgentsCount > 0) {
            agentsBadgeCount.className = "ml-1.5 px-1.5 py-0.5 text-[10px] rounded-full bg-blue-600 text-white font-bold transition-all";
        } else {
            agentsBadgeCount.className = "ml-1.5 px-1.5 py-0.5 text-[10px] rounded-full bg-slate-100 text-slate-500 font-bold transition-all";
        }
    }
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const id = 'toast-' + Math.random().toString(36).substr(2, 9);
    let bgClass = 'bg-slate-900 text-white';
    let icon = '⚙️';

    if (type === 'success') {
        bgClass = 'bg-blue-600 text-white';
        icon = '✓';
    } else if (type === 'info') {
        bgClass = 'bg-slate-800 text-white';
        icon = 'ℹ';
    }

    const html = `
        <div id="${id}" class="transform translate-y-4 opacity-0 transition-all duration-300 ease-out flex items-center p-4 rounded-xl shadow-lg ${bgClass} text-xs font-semibold max-w-sm pointer-events-auto">
            <span class="mr-2 text-sm bg-white/20 w-5 h-5 rounded-full flex items-center justify-center">${icon}</span>
            <span class="flex-1">${message}</span>
            <button onclick="dismissToast('${id}')" class="ml-3 text-white/60 hover:text-white transition">✕</button>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);

    const element = document.getElementById(id);
    setTimeout(() => {
        element.classList.remove('translate-y-4', 'opacity-0');
    }, 50);

    setTimeout(() => {
        dismissToast(id);
    }, 3000);
}

function dismissToast(id) {
    const element = document.getElementById(id);
    if (element) {
        element.classList.add('translate-y-4', 'opacity-0');
        setTimeout(() => {
            element.remove();
        }, 300);
    }
}

// ── Cargar usuarios ──
async function loadUsers() {
    document.getElementById('loadingState').classList.remove('hidden');
    document.getElementById('userList').classList.add('hidden');

    try {
        // Cargar estadísticas globales para el sidebar y badges
        const sRes = await fetch('api/stats.php');
        const stats = await sRes.json();
        if (stats.success) {
            const upCount = document.getElementById('sidebar-users-count');
            const ppCount = document.getElementById('sidebar-prospects-count');
            if (upCount) upCount.textContent = stats.total_users || 0;
            if (ppCount) ppCount.textContent = stats.total_prospects || 0;
        }

        const res  = await fetch('api/users.php');
        const data = await res.json();
        allUsersData = data;
        document.getElementById('loadingState').classList.add('hidden');

        if (!Array.isArray(data)) {
            document.getElementById('userList').innerHTML = `<div class="p-6 text-red-500 text-sm">Error: ${esc(data.error || 'Respuesta inválida')}</div>`;
            document.getElementById('userList').classList.remove('hidden');
            return;
        }

        renderUsers();
    } catch(e) {
        document.getElementById('loadingState').innerHTML = `<p class="text-red-500 text-sm p-6">Error de red: ${e.message}</p>`;
    }
}

function renderUsers() {
    const list = document.getElementById('userList');
    const search = document.getElementById('userSearch').value.toLowerCase();
    const role = document.getElementById('roleFilter').value;
    const status = document.getElementById('statusFilter').value;

    const filtered = allUsersData.filter(u => {
        const isActive = parseInt(u.active) === 1;
        const matchesSearch = !search || (
            (u.name || '').toLowerCase().includes(search) ||
            (u.email || '').toLowerCase().includes(search) ||
            (u.phone || '').toLowerCase().includes(search) ||
            (u.role || '').toLowerCase().includes(search) ||
            (u.whatsapp || '').toLowerCase().includes(search) ||
            (isActive ? 'activo' : 'inactivo').includes(search) ||
            (u.prospect_count || 0).toString().includes(search) ||
            (u.landings || []).some(l => parseInt(l.assigned) === 1 && (l.title || '').toLowerCase().includes(search)) ||
            (u.agents || []).some(a => parseInt(a.assigned) === 1 && (a.name || '').toLowerCase().includes(search))
        );
        const matchesRole = role === 'all' || u.role === role;
        const matchesStatus = status === 'all' || u.active.toString() === status;
        return matchesSearch && matchesRole && matchesStatus;
    });

    const subscribers = filtered.filter(u => u.role !== 'admin');
    const admins      = filtered.filter(u => u.role === 'admin');
    const badge = document.getElementById('user-count-badge');
    if (badge) badge.textContent = filtered.length;

    if (filtered.length === 0) {
        list.innerHTML = '';
        document.getElementById('emptyState').classList.remove('hidden');
        list.classList.add('hidden');
        return;
    }

    document.getElementById('emptyState').classList.add('hidden');
    list.classList.remove('hidden');

    let html = '';
    for (const u of filtered) {
        const isActive  = parseInt(u.active) === 1;
        const isAdmin   = u.role === 'admin';
        const isExpanded = expandedUsers.includes(u.id.toString());
        
        // Color lateral de la fila
        let stripeColor = '#818cf8'; // Morado por defecto
        if (isAdmin) stripeColor = '#3b82f6'; // Azul admin
        if (!isActive) stripeColor = '#f43f5e'; // Rojo inactivo

        const avatarInitials = (u.name || 'U').split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
        
        let finalAvatarUrl = u.avatar;
        if (u.avatar && !u.avatar.startsWith('http')) {
            finalAvatarUrl = 'uploads/' + u.avatar;
        }
        
        const avatarHtml = finalAvatarUrl 
            ? `<img src="${finalAvatarUrl}" alt="Avatar" class="w-8 h-8 rounded-full object-cover shrink-0 border border-slate-200" onerror="this.outerHTML='<div class=\\'w-8 h-8 bg-blue-600/10 text-blue-600 rounded-full flex items-center justify-center font-bold text-xs shrink-0\\'>${avatarInitials}</div>'">`
            : `<div class="w-8 h-8 bg-blue-600/10 text-blue-600 rounded-full flex items-center justify-center font-bold text-xs shrink-0">${avatarInitials}</div>`;

        html += `
        <div class="flex flex-col border-b border-slate-100 last:border-0">
            <div class="list-row p-4 md:py-3.5 cursor-pointer select-none" onclick="if(!event.target.closest('button')) toggleUserExpand('${u.id}')">
                <!-- Nombre e Info -->
                <div class="flex items-center gap-3.5 min-w-0 pl-1">
                    <div class="w-1.5 h-6 rounded-full shrink-0" style="background-color: ${stripeColor}"></div>
                    ${avatarHtml}
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-slate-800 truncate">${esc(u.name || 'Sin nombre')}</div>
                        <div class="text-[10px] text-slate-400 font-semibold md:hidden mt-0.5">${u.role.toUpperCase()} ? ${esc(u.email)}</div>
                    </div>
                </div>

                <!-- Landings Asignadas -->
                <div class="hidden md:block">
                    ${!isAdmin ? `
                    <div class="text-xs text-slate-500 font-semibold" id="landings-count-text-${u.id}">
                        Cargando...
                    </div>
                    ` : '<span class="text-xs text-slate-400 italic font-medium">?</span>'}
                </div>

                <!-- Agentes Asignados -->
                <div class="hidden md:block">
                    ${!isAdmin ? `
                    <div class="text-xs text-slate-500 font-semibold" id="agents-count-text-${u.id}">
                        Cargando...
                    </div>
                    ` : '<span class="text-xs text-slate-400 italic font-medium">?</span>'}
                </div>

                <!-- Rol -->
                <div class="hidden md:block">
                    <span class="text-[11px] font-bold px-2.5 py-1 rounded-full ${isAdmin ? 'bg-blue-50 text-blue-600 border border-blue-200' : 'bg-slate-50 text-slate-600 border border-slate-200'}">
                        ${u.role.toUpperCase()}
                    </span>
                </div>

                <!-- Prospectos / Estado -->
                <div class="hidden md:block">
                    <div class="flex items-center gap-3">
                        <span class="bg-indigo-50 text-indigo-700 text-xs font-bold px-2.5 py-1 rounded-full border border-indigo-200">
                            ${u.prospect_count || 0}
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider ${isActive ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200'}">
                            <span class="w-1.5 h-1.5 rounded-full ${isActive ? 'bg-emerald-500' : 'bg-rose-500'}"></span>
                            ${isActive ? 'Activo' : 'Inactivo'}
                        </span>
                    </div>
                </div>

                <!-- Acciones -->
                <div class="flex items-center justify-end gap-1.5 pr-1">
                    <button onclick="openEditModal(${u.id}, '${escQ(u.name || u.email)}', '${escQ(u.email)}', '${u.role}', '${escQ(u.slug || '')}')" class="w-8 h-8 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center hover:bg-amber-600 hover:text-white border border-amber-200 hover:border-amber-600 transition-all" title="Editar Usuario">
                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                    </button>
                    ${!isAdmin ? `
                    <button onclick="openConfigModal(${u.id}, '${escQ(u.name || u.email)}', 'landings')" class="w-8 h-8 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center hover:bg-blue-600 hover:text-white border border-blue-200 hover:border-blue-600 transition-all" title="Configurar Landings y Agentes">
                        <i data-lucide="settings" class="w-3.5 h-3.5"></i>
                    </button>
                    ` : ''}
                    <button onclick="toggleUserStatus(${u.id})" class="w-8 h-8 border rounded-xl flex items-center justify-center transition-all ${isActive ? 'border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white hover:border-rose-600' : 'border-emerald-200 bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white hover:border-emerald-600'}" title="${isActive ? 'Desactivar Cuenta' : 'Activar Cuenta'}">
                        <i data-lucide="${isActive ? 'user-x' : 'user-check'}" class="w-3.5 h-3.5"></i>
                    </button>
                    ${parseInt(u.id) !== loggedInUserId ? `
                    <button onclick="deleteUser(${u.id}, '${escQ(u.name || u.email)}')" class="w-8 h-8 border border-rose-200 bg-rose-50 text-rose-500 rounded-xl flex items-center justify-center hover:bg-rose-600 hover:text-white hover:border-rose-600 transition-all" title="Eliminar Usuario">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                    ` : ''}
                </div>
            </div>
            <div id="landings-row-${u.id}" class="hidden animate-fadeIn"></div>
        </div>`;
    }
    list.innerHTML = html;
    lucide.createIcons();

    // Actualizar conteos de landings y agentes + cargar expandidos
    filtered.forEach(async u => {
        if (u.role !== 'admin') {
            // Conteo de landings
            const countText = document.getElementById(`landings-count-text-${u.id}`);
            try {
                const res = await fetch('api/users.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action: 'get_user_landings', user_id: u.id }) });
                const landings = await res.json();
                const uObj = allUsersData.find(x => x.id === u.id);
                if (uObj && Array.isArray(landings)) uObj.landings = landings;
                if (countText) {
                    if (!Array.isArray(landings)) {
                        countText.textContent = 'Error';
                    } else {
                        const total = landings.filter(l => l.id > 0).length;
                        const assigned = landings.filter(l => l.id > 0 && parseInt(l.assigned) === 1).length;
                        countText.textContent = `${assigned} de ${total} landings`;
                    }
                }
            } catch(e) { if(countText) countText.textContent = 'Error'; }

            // Conteo de agentes
            const agCountText = document.getElementById(`agents-count-text-${u.id}`);
            try {
                const res2 = await fetch('api/users.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action: 'get_user_agents', user_id: u.id }) });
                const agents = await res2.json();
                const uObj = allUsersData.find(x => x.id === u.id);
                if (uObj && Array.isArray(agents)) uObj.agents = agents;
                if (agCountText) {
                    if (!Array.isArray(agents)) {
                        agCountText.textContent = 'N/A';
                    } else {
                        const activeAgents = agents.filter(a => parseInt(a.assigned) === 1);
                        if (activeAgents.length === 0) {
                            agCountText.innerHTML = `<span class="text-slate-400 italic">Sin asignar</span>`;
                        } else if (activeAgents.length === 1) {
                            const activeAgent = activeAgents[0];
                            let emoji = '🤖';
                            const name = activeAgent.name || '';
                            if (name.includes('Leads')) emoji = '💬';
                            else if (name.includes('Inmobiliario')) emoji = '🏠';
                            else if (name.includes('Soporte') || name.includes('Ventas')) emoji = '🚀';
                            else if (name.includes('Agendador')) emoji = '📅';
                            
                            agCountText.innerHTML = `
                                <div class="flex items-center gap-1.5 font-medium text-slate-700">
                                    <span class="text-sm">${emoji}</span>
                                    <span class="font-bold text-blue-600 truncate max-w-[150px]" title="${esc(name)}">${esc(name)}</span>
                                </div>
                            `;
                        } else {
                            agCountText.innerHTML = `
                                <div class="flex items-center gap-1.5 font-medium text-slate-700">
                                    <span class="text-sm">🤖</span>
                                    <span class="font-bold text-blue-600">${activeAgents.length} agentes asignados</span>
                                </div>
                            `;
                        }
                    }
                }
            } catch(e) { if(agCountText) agCountText.textContent = 'Error'; }
        }
        
        if (expandedUsers.includes(u.id.toString()) && u.role !== 'admin') {
            loadUserLandings(u.id);
        }
    });
}

async function loadUserLandings(userId) {
    const row = document.getElementById(`landings-row-${userId}`);
    if (!row) return;
    row.classList.remove('hidden');
    row.innerHTML = `<div class="bg-slate-900 text-white px-10 py-8 text-xs italic flex items-center gap-3">
        <div class="w-5 h-5 border-2 border-slate-700 border-t-indigo-500 rounded-full animate-spin"></div>
        Cargando campañas activas...
    </div>`;

    try {
        const res = await fetch('api/users.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'get_user_landings', user_id: userId })
        });
        const landings = await res.json();
        const assigned = landings.filter(l => parseInt(l.assigned));

        if (assigned.length > 0) {
            let cardsHtml = assigned.map(l => {
                const landLeads = l.views || 0;
                const convRate = (10 + (landLeads % 7)).toFixed(1);
                const color = l.color || '#3b82f6';

                return `
                <div class="bg-[#1e293b] p-4 rounded-xl border border-slate-700/60 shadow-md flex flex-col justify-between hover:border-slate-500 transition-all text-white">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-3 h-3 rounded-full shrink-0 shadow-lg" style="background-color: ${color}"></span>
                            <span class="text-xs font-bold text-slate-100 truncate" title="${esc(l.title)}">${esc(l.title)}</span>
                        </div>
                    </div>
                    
                    <div class="flex items-end justify-between mt-4 border-t border-slate-800 pt-3">
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block">Prospectos</span>
                            <div class="text-xl font-extrabold text-white mt-0.5">${landLeads}</div>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block">Tasa Conversión</span>
                            <div class="text-sm font-extrabold text-indigo-400 mt-0.5">${convRate}%</div>
                        </div>
                    </div>
                </div>`;
            }).join('');

            row.innerHTML = `
            <div class="bg-slate-900 text-white border-t border-slate-800 px-8 py-6 animate-fadeIn space-y-5 shadow-inner">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest flex items-center gap-2">
                    <i data-lucide="layers" class="w-3.5 h-3.5 text-indigo-400"></i>
                    <span>campañas Activas para el Usuario (${assigned.length})</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    ${cardsHtml}
                </div>
            </div>`;
            lucide.createIcons();
        } else {
            row.innerHTML = `<div class="bg-slate-900 px-10 py-8 text-xs text-slate-400 italic flex items-center gap-2">
                <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-500"></i>
                Este usuario no tiene landings asignadas actualmente.
            </div>`;
            lucide.createIcons();
        }
    } catch(e) {
        row.innerHTML = `<div class="bg-slate-900 text-rose-400 px-10 py-8 text-xs italic">Error al conectar con el servidor.</div>`;
    }
}

async function toggleUserStatus(id) {
    try {
        const res  = await fetch('api/users.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'toggle_active', id}) });
        const data = await res.json();
        if (data.success) { loadUsers(); }
        else { alert('Error: ' + (data.error || 'Intenta de nuevo')); }
    } catch(e) { alert('Error de red'); }
}

// ── Modal Editar Usuario ──
function openEditModal(id, name, email, role, slug) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editEmail').value = email;
    document.getElementById('editRole').value = role;
    document.getElementById('editSlug').value = slug || suggestSlug(email);
    document.getElementById('editPassword').value = '';
    document.getElementById('editError').classList.add('hidden');
    document.getElementById('editModal').classList.add('open');
    lucide.createIcons();
}
function closeEditModal() { document.getElementById('editModal').classList.remove('open'); }

async function submitEditUser(event) {
    event.preventDefault();
    const id       = document.getElementById('editUserId').value;
    const name     = document.getElementById('editName').value.trim();
    const email    = document.getElementById('editEmail').value.trim();
    const role     = document.getElementById('editRole').value;
    const password = document.getElementById('editPassword').value;
    const btn      = document.getElementById('editBtn');

    btn.disabled = true;
    btn.innerHTML = '<div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div> Guardando...';

    const slug = document.getElementById('editSlug').value.trim() || suggestSlug(email);

    try {
        const res = await fetch('api/users.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'update_user', id: parseInt(id), name, email, role, slug, password})
        });
        const data = await res.json();

        if (data.success) {
            closeEditModal();
            loadUsers();
            showToast('Usuario actualizado correctamente', 'success');
        } else {
            const errEl = document.getElementById('editError');
            errEl.textContent = data.error || 'Error al actualizar';
            errEl.classList.remove('hidden');
        }
    } catch(e) {
        document.getElementById('editError').textContent = 'Error de red';
        document.getElementById('editError').classList.remove('hidden');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Guardar Cambios';
        lucide.createIcons();
    }
}

// ── Confirmación visual (reutilizable) ──
let _confirmResolve = null;
function showConfirm(title, message) {
    return new Promise((resolve) => {
        _confirmResolve = resolve;
        document.getElementById('confirmTitle').textContent = title;
        document.getElementById('confirmMessage').textContent = message;
        document.getElementById('confirmModal').classList.add('open');
    });
}
function closeConfirm() {
    document.getElementById('confirmModal').classList.remove('open');
    if (_confirmResolve) _confirmResolve(false);
    _confirmResolve = null;
}
function executeConfirm() {
    document.getElementById('confirmModal').classList.remove('open');
    if (_confirmResolve) _confirmResolve(true);
    _confirmResolve = null;
}

// ── Eliminar Usuario ──
async function deleteUser(id, name) {
    const ok = await showConfirm('?Eliminar Usuario?', 'El usuario "' + name + '" se eliminara permanentemente junto con sus asignaciones. Esta accion no se puede deshacer.');
    if (!ok) return;

    try {
        const res = await fetch('api/users.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'delete_user', id})
        });
        const data = await res.json();

        if (data.success) {
            loadUsers();
            showToast('Usuario eliminado correctamente', 'success');
        } else {
            showToast(data.error || 'Error al eliminar usuario', 'error');
        }
    } catch(e) {
        showToast('Error de red al eliminar', 'error');
    }
}

// Limpiar filtros al inicializar la página (evita persistencia del browser y autocomplete)
const searchEl = document.getElementById('userSearch');
const roleEl = document.getElementById('roleFilter');
const statusEl = document.getElementById('statusFilter');
if (searchEl) { searchEl.value = ''; searchEl.setAttribute('autocomplete', 'off'); }
if (roleEl) roleEl.value = 'all';
if (statusEl) statusEl.value = 'all';
// Fallback para navegadores que ignoran autocomplete="off" y rellenan después de cargar
setTimeout(() => { if (searchEl) searchEl.value = ''; }, 100);

loadUsers();
lucide.createIcons();
</script>
</body>
</html>
