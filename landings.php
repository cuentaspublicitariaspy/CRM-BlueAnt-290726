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
    <title>Landings - Ultra CRM</title>
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
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: #f8fafc; 
            color: #0f172a; 
        }

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
        #fileLabel { cursor:pointer; }
        #fileLabel:hover { border-color: var(--accent-blue); }

        /* ── LANDING CARD ROWS (LIGHT/ELEGANT) ── */
        .landing-row-card {
            display: grid;
            align-items: center;
            grid-template-columns: 1fr;
            background: white;
            border: 1px solid #f1f5f9;
            border-radius: 1.25rem;
            padding: 1.25rem;
            margin-bottom: 0.75rem;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            gap: 1rem;
        }

        @media (min-width: 768px) {
            .landing-row-card {
                grid-template-columns: 2.2fr 1.8fr 1fr 1fr 1fr 150px;
                padding: 1rem 1.5rem;
                gap: 0.5rem;
            }
        }

        .landing-row-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px 0 rgba(0, 0, 0, 0.05);
            border-color: #e2e8f0;
        }

        .text-truncate {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ── ANIMACIONES ── */
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .animate-fadeIn {
            animation: fadeIn 0.3s ease-out forwards;
        }
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

                            <a href="landings.php" class="nav-link active flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

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

                    <a href="landings.php" class="nav-link active flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

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

    <main class="flex-1 flex flex-col min-w-0 bg-white">
        <!-- Header -->
        <header class="h-16 border-b border-slate-100 flex items-center justify-between px-6 lg:px-10 bg-white sticky top-0 z-40">
            <div class="flex items-center gap-4">
                <button onclick="toggleMenu()" class="lg:hidden p-2 text-slate-500"><i data-lucide="menu" class="w-6 h-6"></i></button>
                <div class="flex items-center gap-3">
                    <h1 class="text-lg font-bold text-slate-900 leading-none">Gestión de Landings</h1>
                    <span id="countBadge" class="bg-indigo-50 text-[#5c59f2] text-[10px] font-black px-2 py-0.5 rounded-full">0</span>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <?php if ($is_admin): ?>
                <button onclick="openModal()" class="bg-[#5c59f2] hover:bg-[#4d4ae0] text-white px-3 py-1.5 rounded-md text-xs font-bold shadow-sm transition-all active:scale-95 flex items-center gap-2">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Nueva Landing
                </button>
                <?php else: ?>
                <span class="bg-amber-50 border border-amber-200 rounded-full px-2.5 py-1 text-[10px] font-bold text-amber-600 uppercase tracking-wide">Vista Suscriptor</span>
                <?php endif; ?>
            </div>
        </header>

        <!-- Filtros -->
        <section class="p-4 border-b bg-slate-50/50 flex flex-wrap gap-4 items-end">
            <!-- Buscador -->
            <div class="flex-1 min-w-[200px]">
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5 tracking-wider">Buscar campaña / URL</label>
                <div class="relative">
                    <input type="text" id="searchInput" oninput="filterLandings()" placeholder="Buscar landing..." class="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-2.5 text-slate-400"></i>
                </div>
            </div>

            <!-- Ordenar Por -->
            <div class="w-full md:w-48">
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5 tracking-wider">Ordenar por</label>
                <select id="sortFilter" onchange="filterLandings()" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all cursor-pointer appearance-none">
                    <option value="name_asc">Nombre (A-Z)</option>
                    <option value="name_desc">Nombre (Z-A)</option>
                    <option value="views_desc">Más visitas</option>
                    <option value="leads_desc">Más leads</option>
                    <option value="conv_desc">Mayor conversión</option>
                </select>
            </div>

            <!-- Limpiar -->
            <button onclick="resetFilters()" class="p-2.5 text-slate-400 hover:text-[#5c59f2] hover:bg-blue-50 rounded-lg transition-all" title="Limpiar filtros">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
            </button>
        </section>

        <!-- Content -->
        <div class="flex-1 p-6 lg:p-8 overflow-y-auto">
            <!-- Loading state -->
            <div id="loadingState" class="text-center py-20 text-slate-400">
                <i data-lucide="loader" class="w-8 h-8 mx-auto mb-3 animate-spin"></i>
                <p class="text-sm">Cargando landings...</p>
            </div>

            <!-- Empty state -->
            <div id="emptyState" class="hidden text-center py-20">
                <div class="w-16 h-16 bg-indigo-50 rounded-3xl flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="rocket" class="w-8 h-8 text-indigo-400"></i>
                </div>
                <?php if ($is_admin): ?>
                <h3 class="text-lg font-bold text-slate-900 mb-2">Sin landings todavía</h3>
                <p class="text-slate-500 text-sm mb-6">Sub? tu primer archivo HTML para comenzar a trackear.</p>
                <button onclick="openModal()" class="bg-[#5c59f2] text-white px-6 py-3 rounded-xl font-bold hover:bg-[#4d4ae0] transition-all">Subir primera landing</button>
                <?php else: ?>
                <h3 class="text-lg font-bold text-slate-900 mb-2">No tenés landings asignadas</h3>
                <p class="text-slate-500 text-sm">El administrador aún no te asign? ninguna landing. Contactalo para que te habilite el acceso.</p>
                <?php endif; ?>
            </div>

            <!-- Cabecera Tabla (Desktop) -->
            <div id="tableHeader" class="hidden md:grid landing-row-card bg-transparent border-none shadow-none h-8 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 pointer-events-none">
                <div class="pl-14">campaña</div>
                <div>Enlace / URL</div>
                <div class="text-center">Visitas</div>
                <div class="text-center">Leads</div>
                <div class="text-center">Conversión</div>
                <div class="text-right">Acciones</div>
            </div>

            <!-- List rows container -->
            <div id="landingsList" class="hidden flex flex-col pb-32"></div>
        </div>
    </main>

    <!-- Bottom Nav Mobile -->
    <div class="fixed bottom-6 left-1/2 -translate-x-1/2 w-[90%] max-w-sm bg-white/90 backdrop-blur-xl border border-slate-200 rounded-3xl p-2 flex justify-around items-center shadow-xl z-50 lg:hidden">
        <a href="index.php" class="p-4 text-slate-400"><i data-lucide="layout-dashboard" class="w-6 h-6"></i></a>
        <a href="prospectos.php" class="p-4 text-slate-400"><i data-lucide="users" class="w-6 h-6"></i></a>
        <a href="acciones.php" class="p-4 text-slate-400"><i data-lucide="list" class="w-6 h-6"></i></a>
        <a href="landings.php" class="p-4 text-indigo-600"><i data-lucide="rocket" class="w-6 h-6"></i></a>
        <a href="marketing.php" class="p-4 text-slate-400"><i data-lucide="image" class="w-6 h-6"></i></a>
    </div>

    <!-- ===== MODAL NUEVA LANDING ===== -->
    <div id="uploadModal" class="modal-overlay">
        <div class="bg-white w-full max-w-lg rounded-3xl p-8 shadow-2xl">
            <div class="flex items-center justify-between mb-6">
                <h2 id="modal-title" class="text-2xl font-black text-slate-900">Nueva Landing</h2>
                <button onclick="closeUploadModal()" class="w-9 h-9 flex items-center justify-center rounded-xl bg-slate-100 text-slate-500 hover:bg-slate-200"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div id="modal-info" class="bg-indigo-50 border border-indigo-100 rounded-2xl p-4 mb-6 text-xs text-indigo-700 font-medium">
                ✨ El sistema inyectará automáticamente el <strong>formulario de registro</strong> y el tracking de visitas en tu HTML.
            </div>
            <form id="uploadForm" class="space-y-4">
                <input type="hidden" name="id" id="inp-id">
                <input type="text" name="title" id="inp-title" required placeholder="Nombre de la campaña *" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-3.5 px-5 text-sm outline-none focus:border-indigo-400 transition-colors">
                <textarea name="description" id="inp-desc" rows="2" placeholder="Descripción (opcional)" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-3.5 px-5 text-sm outline-none focus:border-indigo-400 transition-colors resize-none"></textarea>
                
                <div id="file-selector-area">
                    <label id="fileLabel" class="block w-full bg-slate-50 border-2 border-dashed border-slate-200 rounded-2xl py-8 text-center hover:border-indigo-400 transition-colors">
                        <i data-lucide="file-code" class="w-8 h-8 text-slate-300 mx-auto mb-2"></i>
                        <span id="fileLabelText" class="text-sm text-slate-400 block">Seleccionar archivo .html</span>
                        <span class="text-xs text-slate-300 mt-1 block">Solo archivos HTML</span>
                        <input type="file" name="landing_file" id="inp-file" accept=".html" class="hidden">
                    </label>
                </div>

                <div class="space-y-3">
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Color de la Tarjeta</label>
                    <div class="flex items-center gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                        <input type="color" id="inp-color" name="color" value="#3b82f6" class="w-12 h-12 rounded-xl border-none cursor-pointer bg-transparent">
                        <div class="flex-1">
                            <input type="text" id="inp-color-hex" placeholder="#000000" class="w-full bg-transparent text-sm font-mono font-bold uppercase outline-none text-slate-600">
                            <p class="text-[10px] text-slate-400 font-medium">Click en el cuadro para elegir color</p>
                        </div>
                    </div>
                </div>

                <div id="uploadMsg" class="hidden text-sm text-center py-2 rounded-xl font-medium"></div>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeUploadModal()" class="flex-1 py-3.5 bg-slate-100 text-slate-600 font-bold rounded-2xl hover:bg-slate-200 transition-all">Cancelar</button>
                    <button type="submit" id="uploadBtn" class="flex-1 py-3.5 bg-indigo-600 text-white font-bold rounded-2xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100 flex items-center justify-center gap-2">
                        <i data-lucide="upload-cloud" class="w-4 h-4"></i> <span id="btn-text">Procesar e Inyectar</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== MODAL CONFIRMAR BORRADO ===== -->
    <div id="deleteModal" class="modal-overlay">
        <div class="bg-white w-full max-w-sm rounded-3xl p-8 shadow-2xl text-center">
            <div class="w-14 h-14 bg-red-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <i data-lucide="trash-2" class="w-7 h-7 text-red-500"></i>
            </div>
            <h2 class="text-xl font-black text-slate-900 mb-2">?Eliminar landing?</h2>
            <p class="text-slate-500 text-sm mb-6">Se eliminar? el registro y el archivo. Esta acción no se puede deshacer.</p>
            <div class="flex flex-col gap-3">
                <button id="confirmDeleteBtn" class="w-full py-3.5 bg-red-600 text-white font-bold rounded-2xl hover:bg-red-700 transition-all">S?, eliminar</button>
                <button onclick="closeDeleteModal()" class="w-full py-3.5 bg-slate-100 text-slate-500 font-bold rounded-2xl hover:bg-slate-200 transition-all">Cancelar</button>
            </div>
        </div>
    </div>

    <!-- ===== MODAL CONFIGURACION REDIRECCION (SUSCRIPTORES) ===== -->
    <div id="configModal" class="modal-overlay">
        <div class="bg-white w-full max-w-lg rounded-3xl p-8 shadow-2xl">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-black text-slate-900 flex items-center gap-2">
                    <i data-lucide="settings-2" class="w-6 h-6 text-indigo-600"></i> Acción post-registro
                </h2>
                <button onclick="closeConfigModal()" class="w-9 h-9 flex items-center justify-center rounded-xl bg-slate-100 text-slate-500 hover:bg-slate-200"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <p class="text-slate-500 text-sm mb-6">?Qu? sucede cuando un prospecto llena el formulario en esta landing?</p>
            
            <form id="configForm" class="space-y-6">
                <input type="hidden" id="cfg-landing-id">
                
                <div class="space-y-3">
                    <label class="flex items-center gap-3 p-4 border border-slate-200 rounded-2xl cursor-pointer hover:bg-slate-50 transition-colors" onclick="toggleConfigFields('default')">
                        <input type="radio" name="redirect_type" value="default" class="w-5 h-5 text-indigo-600" checked>
                        <div>
                            <span class="block font-bold text-slate-800">Mensaje de éxito normal</span>
                            <span class="block text-xs text-slate-500">Muestra el tilde verde predeterminado.</span>
                        </div>
                    </label>
                    
                    <label class="flex items-center gap-3 p-4 border border-slate-200 rounded-2xl cursor-pointer hover:bg-slate-50 transition-colors" onclick="toggleConfigFields('url')">
                        <input type="radio" name="redirect_type" value="url" class="w-5 h-5 text-indigo-600">
                        <div>
                            <span class="block font-bold text-slate-800">Redirigir a un Link</span>
                            <span class="block text-xs text-slate-500">Enviarlo a tu Linktree, Catálogo, Web, etc.</span>
                        </div>
                    </label>
                    <div id="cfg-url-wrap" class="hidden pl-11 pr-4">
                        <input type="url" id="cfg-url" placeholder="https://ejemplo.com" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-sm outline-none focus:border-indigo-400">
                    </div>

                    <label class="flex items-center gap-3 p-4 border border-slate-200 rounded-2xl cursor-pointer hover:bg-slate-50 transition-colors" onclick="toggleConfigFields('whatsapp')">
                        <input type="radio" name="redirect_type" value="whatsapp" class="w-5 h-5 text-indigo-600">
                        <div>
                            <span class="block font-bold text-slate-800">Abrir WhatsApp (Recomendado)</span>
                            <span class="block text-xs text-slate-500">Abre un chat con un mensaje pre-escrito.</span>
                        </div>
                    </label>
                    <div id="cfg-wa-wrap" class="hidden pl-11 pr-4 space-y-3">
                        <input type="text" id="cfg-wa-num" placeholder="Tu número completo (Ej: 549112345678)" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-sm outline-none focus:border-indigo-400">
                        <textarea id="cfg-wa-msg" rows="2" placeholder="Mensaje pre-escrito (Ej: Hola, acabo de registrarme...)" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-sm outline-none focus:border-indigo-400 resize-none"></textarea>
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeConfigModal()" class="flex-1 py-3.5 bg-slate-100 text-slate-600 font-bold rounded-2xl hover:bg-slate-200 transition-all">Cancelar</button>
                    <button type="submit" id="saveConfigBtn" class="flex-1 py-3.5 bg-indigo-600 text-white font-bold rounded-2xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100 flex items-center justify-center gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i> Guardar Acción
                    </button>
                </div>
            </form>
        </div>
    </div>

<script>
let allLandings = [];
let filteredLandings = [];
let landingToDelete = null;
const is_admin = <?php echo $is_admin ? 'true' : 'false'; ?>;

function toggleMenu() { document.getElementById('mobileMenu').classList.toggle('hidden'); }
async function logout() { await fetch('api/auth.php?action=logout'); window.location.href = 'login.php'; }

function openModal() { 
    document.getElementById('modal-title').textContent = 'Nueva Landing';
    document.getElementById('modal-info').classList.remove('hidden');
    document.getElementById('file-selector-area').classList.remove('hidden');
    document.getElementById('btn-text').textContent = 'Procesar e Inyectar';
    document.getElementById('inp-id').value = '';
    document.getElementById('inp-file').required = true;
    document.getElementById('uploadModal').classList.add('open'); 
    lucide.createIcons(); 
}
function closeUploadModal() { 
    document.getElementById('uploadModal').classList.remove('open'); 
    document.getElementById('uploadForm').reset(); 
    document.getElementById('fileLabelText').textContent = 'Seleccionar archivo .html'; 
    document.getElementById('uploadMsg').classList.add('hidden'); 
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('open'); landingToDelete = null; }

function editLanding(l) {
    document.getElementById('modal-title').textContent = 'Editar Landing';
    document.getElementById('modal-info').classList.add('hidden');
    document.getElementById('file-selector-area').classList.add('hidden');
    document.getElementById('btn-text').textContent = 'Guardar Cambios';
    document.getElementById('inp-id').value = l.id;
    document.getElementById('inp-title').value = l.title;
    document.getElementById('inp-desc').value = l.description || '';
    document.getElementById('inp-color').value = l.color || '#3b82f6';
    document.getElementById('inp-color-hex').value = l.color || '#3b82f6';
    document.getElementById('inp-file').required = false;
    document.getElementById('uploadModal').classList.add('open');
    lucide.createIcons();
}

// Sincronizar color picker con texto
const colorInp = document.getElementById('inp-color');
const colorHex = document.getElementById('inp-color-hex');
colorInp.addEventListener('input', (e) => colorHex.value = e.target.value);
colorHex.addEventListener('input', (e) => { if(/^#[0-9A-F]{6}$/i.test(e.target.value)) colorInp.value = e.target.value; });

// Mostrar nombre de archivo seleccionado
document.getElementById('inp-file').addEventListener('change', function() {
    const name = this.files[0]?.name || 'Seleccionar archivo .html';
    document.getElementById('fileLabelText').textContent = name;
});

// ===== LISTAR LANDINGS =====
async function fetchLandings() {
    try {
        const res = await fetch('api/landings.php');
        const text = await res.text();
        let data;
        try { data = JSON.parse(text); } catch(e) {
            document.getElementById('loadingState').classList.add('hidden');
            document.getElementById('emptyState').classList.add('hidden');
            const list = document.getElementById('landingsList');
            list.classList.remove('hidden');
            list.innerHTML = `<div class="bg-red-50 border border-red-100 rounded-2xl p-6 text-red-700 text-sm"><strong>Error al leer respuesta de la API:</strong><br><code class="text-xs break-all">${text.substring(0,500)}</code></div>`;
            return;
        }

        document.getElementById('loadingState').classList.add('hidden');

        if (!Array.isArray(data)) {
            const list = document.getElementById('landingsList');
            list.classList.remove('hidden');
            list.innerHTML = `<div class="bg-red-50 border border-red-100 rounded-2xl p-6 text-red-700 text-sm"><strong>Error API:</strong> ${data.error || JSON.stringify(data)}</div>`;
            return;
        }
        allLandings = data;
        filterLandings();
    } catch(e) {
        document.getElementById('loadingState').classList.add('hidden');
        console.error('Fetch error:', e);
    }
}

function filterLandings() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const sortBy = document.getElementById('sortFilter').value;

    filteredLandings = allLandings.filter(l => {
        const title = (l.title || '').toLowerCase();
        const desc = (l.description || '').toLowerCase();
        const url = (l.url || '').toLowerCase();
        return title.includes(search) || desc.includes(search) || url.includes(search);
    });

    // Sorting
    filteredLandings.sort((a, b) => {
        if (sortBy === 'name_asc') return (a.title || '').localeCompare(b.title || '');
        if (sortBy === 'name_desc') return (b.title || '').localeCompare(a.title || '');
        if (sortBy === 'views_desc') return (b.views || 0) - (a.views || 0);
        if (sortBy === 'leads_desc') return (b.prospect_count || 0) - (a.prospect_count || 0);
        if (sortBy === 'conv_desc') return parseFloat(b.conversion_rate || 0) - parseFloat(a.conversion_rate || 0);
        return 0;
    });

    renderLandings(filteredLandings);
}

function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('sortFilter').value = 'name_asc';
    filterLandings();
}

function renderLandings(data) {
    const list = document.getElementById('landingsList');
    const empty = document.getElementById('emptyState');
    const badge = document.getElementById('countBadge');
    const tableHeader = document.getElementById('tableHeader');

    badge.textContent = data.length;

    if (data.length === 0) {
        empty.classList.remove('hidden');
        list.classList.add('hidden');
        tableHeader.classList.add('hidden');
        return;
    }

    empty.classList.add('hidden');
    list.classList.remove('hidden');
    tableHeader.classList.remove('hidden');

    list.innerHTML = data.map(l => {
        const color = l.color || '#3b82f6';
        return `
        <div class="landing-row-card" style="border-left: 4px solid ${color};">
            <!-- campaña -->
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background: ${color}18; color: ${color};">
                    <i data-lucide="rocket" class="w-5 h-5"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-bold text-slate-800 text-truncate">${escHtml(l.title)}</div>
                    <div class="text-[10px] text-slate-400 text-truncate">${escHtml(l.description || 'Sin Descripción')}</div>
                </div>
            </div>

            <!-- URL / Enlace -->
            <div class="flex flex-col min-w-0">
                ${l.is_admin && l.subscriber_urls && l.subscriber_urls.length > 0 ? `
                    <div class="space-y-1">
                        ${l.subscriber_urls.map(su => `
                            <div class="flex items-center gap-1.5">
                                <span class="text-[9px] font-semibold text-slate-400 w-16 truncate" title="${escHtml(su.name)}">${escHtml(su.name)}</span>
                                <span class="text-[10px] text-slate-500 font-mono truncate max-w-[140px] md:max-w-[200px]">${escHtml(su.url_display)}</span>
                                <button class="text-slate-400 hover:text-indigo-600 transition-colors shrink-0" onclick="copyUrl('${escHtml(su.url)}')" title="Copiar URL de ${escHtml(su.name)}">
                                    <i data-lucide="copy" class="w-3 h-3"></i>
                                </button>
                            </div>
                        `).join('')}
                    </div>
                ` : l.is_admin ? `
                    <span class="text-[10px] text-slate-400 italic">Sin suscriptores asignados</span>
                ` : `
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] text-slate-500 font-mono truncate max-w-[180px] md:max-w-none">${escHtml(l.url_display || l.url)}</span>
                        <button class="text-slate-400 hover:text-slate-600 transition-colors ml-1 cursor-pointer" onclick="copyUrl('${escHtml(l.url)}')" title="Copiar URL">
                            <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                `}
            </div>

            <!-- Visitas -->
            <div class="flex md:flex-col md:items-center justify-between md:justify-center border-t md:border-t-0 pt-2 md:pt-0">
                <span class="text-[9px] font-bold text-slate-400 uppercase md:hidden">Visitas</span>
                <span class="text-xs font-bold text-slate-700">${l.views}</span>
            </div>

            <!-- Leads -->
            <div class="flex md:flex-col md:items-center justify-between md:justify-center border-t md:border-t-0 pt-2 md:pt-0">
                <span class="text-[9px] font-bold text-slate-400 uppercase md:hidden">Leads</span>
                <div class="flex items-center gap-1.5">
                    <span class="text-xs font-bold text-slate-700">${l.prospect_count}</span>
                    ${l.is_admin ? `<span class="bg-slate-100 text-slate-500 text-[8px] font-bold px-1.5 py-0.5 rounded-full">${l.subscriber_count ?? 0} subs</span>` : ''}
                </div>
            </div>

            <!-- Conversión -->
            <div class="flex md:flex-col md:items-center justify-between md:justify-center border-t md:border-t-0 pt-2 md:pt-0 pb-2 md:pb-0">
                <span class="text-[9px] font-bold text-slate-400 uppercase md:hidden">Conversión</span>
                <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full" style="background: ${color}18; color: ${color};">
                    ${l.conversion_rate}%
                </span>
            </div>

            <!-- Acciones -->
            <div class="flex items-center justify-end gap-1.5 border-t md:border-t-0 pt-3 md:pt-0">
                <a href="${l.url}" target="_blank" class="w-8 h-8 bg-slate-50 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg flex items-center justify-center transition-all" title="Ver landing externa">
                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                </a>
                ${l.can_delete ? `
                    <button onclick='editLanding(${JSON.stringify(l).replace(/'/g, "&#39;")})' class="w-8 h-8 bg-slate-50 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg flex items-center justify-center transition-all" title="Editar">
                        <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                    </button>
                    <button onclick="deleteLanding(${l.id})" class="w-8 h-8 bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white rounded-lg flex items-center justify-center transition-all" title="Eliminar">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                ` : `
                    <button onclick="openConfigModal(${l.id})" class="w-8 h-8 bg-indigo-50 text-[#5c59f2] hover:bg-[#5c59f2] hover:text-white rounded-lg flex items-center justify-center transition-all" title="Configurar Acción Post-Registro">
                        <i data-lucide="settings-2" class="w-3.5 h-3.5"></i>
                    </button>
                `}
            </div>
        </div>`;
    }).join('');
    lucide.createIcons();
}

// ===== SUBIR =====
document.getElementById('uploadForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('uploadBtn');
    const msg = document.getElementById('uploadMsg');

    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg> Procesando...';
    msg.classList.add('hidden');

    const formData = new FormData(this);
    const isEdit = !!document.getElementById('inp-id').value;
    const url = isEdit ? `api/landings.php?action=update&id=${document.getElementById('inp-id').value}&title=${encodeURIComponent(formData.get('title'))}&description=${encodeURIComponent(formData.get('description'))}&color=${encodeURIComponent(formData.get('color'))}` : 'api/landings.php';
    const method = isEdit ? 'GET' : 'POST';
    const body = isEdit ? null : formData;

    try {
        const res = await fetch(url, { method, body });
        const data = await res.json();

        if (data.success) {
            msg.textContent = isEdit ? '✅ Cambios guardados' : '✅ Landing subida e inyectada con éxito';
            msg.className = 'text-sm text-center py-2 rounded-xl font-medium bg-emerald-50 text-emerald-700';
            msg.classList.remove('hidden');
            setTimeout(() => { closeUploadModal(); fetchLandings(); }, 1500);
        } else {
            msg.textContent = '❌ ' + (data.error || 'Error desconocido');
            msg.className = 'text-sm text-center py-2 rounded-xl font-medium bg-red-50 text-red-600';
            msg.classList.remove('hidden');
        }
    } catch(err) {
        msg.textContent = '❌ Error de red: ' + err.message;
        msg.className = 'text-sm text-center py-2 rounded-xl font-medium bg-red-50 text-red-600';
        msg.classList.remove('hidden');
    }

    btn.disabled = false;
    btn.innerHTML = '<i data-lucide="upload-cloud" class="w-4 h-4"></i> Procesar e Inyectar';
    lucide.createIcons();
});

// ===== BORRAR =====
function deleteLanding(id) {
    landingToDelete = id;
    document.getElementById('deleteModal').classList.add('open');
    lucide.createIcons();
}

document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
    if (!landingToDelete) return;
    this.textContent = 'Eliminando...';
    this.disabled = true;

    try {
        const res = await fetch(`api/landings.php?action=delete&id=${landingToDelete}`);
        const data = await res.json();
        if (data.success) {
            closeDeleteModal();
            fetchLandings();
        } else {
            alert('Error al eliminar: ' + (data.error || 'Error desconocido'));
        }
    } catch(e) {
        alert('Error de red al eliminar');
    }

    this.textContent = 'S?, eliminar';
    this.disabled = false;
});

// ===== CONFIG REDIRECCION =====
function toggleConfigFields(type) {
    document.getElementById('cfg-url-wrap').classList.add('hidden');
    document.getElementById('cfg-wa-wrap').classList.add('hidden');
    document.querySelector(`input[name="redirect_type"][value="${type}"]`).checked = true;
    if (type === 'url') document.getElementById('cfg-url-wrap').classList.remove('hidden');
    if (type === 'whatsapp') document.getElementById('cfg-wa-wrap').classList.remove('hidden');
}

async function openConfigModal(id) {
    document.getElementById('cfg-landing-id').value = id;
    toggleConfigFields('default'); // reset visual
    document.getElementById('configForm').reset();
    
    // Cargar config actual
    try {
        const r = await fetch(`api/landing_config.php?landing_id=${id}`);
        const d = await r.json();
        if (d.success && d.config) {
            const type = d.config.redirect_type || 'default';
            toggleConfigFields(type);
            document.getElementById('cfg-url').value = d.config.redirect_url || '';
            document.getElementById('cfg-wa-num').value = d.config.whatsapp_number || '';
            document.getElementById('cfg-wa-msg').value = d.config.whatsapp_message || '';
        }
    } catch(e) { console.error(e); }

    document.getElementById('configModal').classList.add('open');
    lucide.createIcons();
}

function closeConfigModal() {
    document.getElementById('configModal').classList.remove('open');
}

document.getElementById('configForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('saveConfigBtn');
    btn.disabled = true;
    btn.innerHTML = 'Guardando...';

    const payload = {
        landing_id: document.getElementById('cfg-landing-id').value,
        redirect_type: document.querySelector('input[name="redirect_type"]:checked').value,
        redirect_url: document.getElementById('cfg-url').value,
        whatsapp_number: document.getElementById('cfg-wa-num').value,
        whatsapp_message: document.getElementById('cfg-wa-msg').value
    };

    try {
        const r = await fetch('api/landing_config.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const d = await r.json();
        if (d.success) {
            const toast = document.createElement('div');
            toast.textContent = '✅ Acción guardada';
            toast.style.cssText = 'position:fixed;bottom:100px;left:50%;transform:translateX(-50%);background:#10b981;color:#fff;padding:.5rem 1.25rem;border-radius:9999px;font-size:.8rem;font-weight:700;z-index:9999;';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2000);
            closeConfigModal();
        } else {
            alert(d.error || 'Error al guardar');
        }
    } catch(err) { alert('Error de red'); }

    btn.disabled = false;
    btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Guardar Acción';
    lucide.createIcons();
});

// ===== UTILIDADES =====
function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function copyUrl(url) {
    navigator.clipboard.writeText(url).then(() => {
        const toast = document.createElement('div');
        toast.textContent = '✅ URL copiada';
        toast.style.cssText = 'position:fixed;bottom:100px;left:50%;transform:translateX(-50%);background:#0f172a;color:#fff;padding:.5rem 1.25rem;border-radius:9999px;font-size:.8rem;font-weight:700;z-index:9999;';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2000);
    });
}

// Init
fetchLandings();
lucide.createIcons();
</script>
</body>
</html>
