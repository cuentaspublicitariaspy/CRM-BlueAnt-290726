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
    <title>Prospectos - Ultra CRM</title>
    <?php if(isset($crm_favicon) && $crm_favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($crm_favicon); ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="lib/phone-picker.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <style>
        :root {
            --ultrablue: #0d1e56;
            --brandpurple: #6366f1;
            --sidebar-bg: #0d1e56;
            --accent-blue: #2563eb;
            --card-bg: #ffffff;
        }
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: #f8fafc; 
            color: #0f172a; 
        }

        /* â”€â”€ SIDEBAR â”€â”€ */
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
            grid-template-columns: 40px 1fr auto;
            transition: all 0.1s ease;
            border-bottom: 1px solid #f1f5f9;
            background: white;
        }

        @media (min-width: 768px) {
            .list-row {
                grid-template-columns: 40px 1.2fr 0.9fr 0.7fr 0.8fr 0.7fr 0.7fr 100px;
                padding: 0 16px;
            }
            .list-row.admin-grid {
                grid-template-columns: 40px 0.9fr 1.2fr 0.9fr 0.7fr 0.8fr 0.7fr 0.7fr 100px;
            }
        }

        .list-row:hover {
            background-color: #f1f5f9;
        }

        .text-truncate {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(0.5);
        }


        /* â”€â”€ ANIMACIONES PREMIUM â”€â”€ */
        @keyframes pulse-ring {
            0% { transform: scale(0.95); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
            100% { transform: scale(0.95); opacity: 0.5; }
        }
        .animate-pulse-ring {
            animation: pulse-ring 3s infinite ease-in-out;
        }
        .animate-fadeIn {
            animation: fadeIn 0.3s ease-out forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
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

                                <i data-lucide="settings" class="w-5 h-5"></i>Configuracin</a>

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

                        <i data-lucide="settings" class="w-5 h-5"></i>Configuracin</a>

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
        <!-- Header Compacto -->
        <header class="h-14 border-b px-6 flex items-center justify-between bg-white shrink-0 sticky top-0 z-40">
            <div class="flex items-center gap-3">
                <button onclick="toggleMenu()" class="lg:hidden p-2 text-slate-500"><i data-lucide="menu" class="w-5 h-5"></i></button>
                <h1 class="text-sm font-bold text-slate-800">Prospectos</h1>
            </div>
            <div class="flex items-center gap-3">
                <span id="countBadge" class="bg-slate-100 text-slate-500 text-[10px] px-2 py-0.5 rounded-full font-bold">0</span>
                
                <!-- Botón de Borrado Masivo (Oculto por defecto) -->
                <button id="bulkDeleteBtn" onclick="askBulkDelete()" class="hidden bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white px-3 py-1.5 rounded-md text-xs font-bold shadow-sm transition-all flex items-center gap-2 border border-rose-200">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> 
                    <span>Eliminar (<span id="selectedCount">0</span>)</span>
                </button>

                <button onclick="openModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-md text-xs font-bold shadow-sm transition-all active:scale-95 flex items-center gap-2">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Añadir
                </button>
            </div>
        </header>

        <!-- Tool Bar / Filtros -->
        <section class="p-4 border-b bg-slate-50/50 flex flex-wrap gap-4 items-end">
            <!-- Buscador -->
            <div class="flex-1 min-w-[200px]">
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5 tracking-wider">Nombre / WhatsApp</label>
                <div class="relative">
                    <input type="text" id="searchInput" oninput="filterProspects()" placeholder="Buscar..." class="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-2.5 text-slate-400"></i>
                </div>
            </div>

            <!-- Filtro Origen -->
            <div class="w-full md:w-56">
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5 tracking-wider">Origen</label>
                <select id="landingFilter" onchange="filterProspects()" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all cursor-pointer appearance-none">
                    <option value="">Todos los orígenes</option>
                </select>
            </div>

            <?php if ($is_admin): ?>
            <!-- Filtro Usuario (admin) -->
            <div class="w-full md:w-56">
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5 tracking-wider">Usuario</label>
                <select id="userFilterSelect" onchange="userFilter=this.value; filterProspects();" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all cursor-pointer appearance-none">
                    <option value="">Todos los usuarios</option>
                </select>
            </div>
            <?php endif; ?>

            <!-- Rango Fechas -->
            <div class="flex gap-2 w-full md:w-auto">
                <div class="flex-1 md:w-40">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5 tracking-wider">Desde</label>
                    <input type="date" id="dateFrom" onchange="filterProspects()" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                </div>
                <div class="flex-1 md:w-40">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5 tracking-wider">Hasta</label>
                    <input type="date" id="dateTo" onchange="filterProspects()" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                </div>
            </div>

            <!-- Limpiar -->
            <button onclick="resetFilters()" class="p-2.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all" title="Limpiar filtros">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
            </button>

            <!-- Exportar -->
            <div class="flex border-l pl-4 gap-1">
                <button onclick="exportCSV()" class="p-2.5 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-all" title="Exportar CSV">
                    <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                </button>
                <button onclick="exportPDF()" class="p-2.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-all" title="Exportar PDF">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                </button>
                <button onclick="openImportModal()" class="p-2.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all" title="Importar Prospectos">
                    <i data-lucide="upload-cloud" class="w-4 h-4"></i>
                </button>
            </div>
        </section>

        <!-- Cabecera Tabla (Desktop) -->
        <div class="hidden md:grid list-row bg-slate-50 h-10 text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b<?php echo $is_admin ? ' admin-grid' : ''; ?>">
            <div class="flex justify-center">
                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
            </div>
            <?php if ($is_admin): ?><div>Usuario</div><?php endif; ?>
            <div>Nombre</div>
            <div>Email</div>
            <div>Teléfono</div>
            <div>Dominio</div>
            <div>Origen</div>
            <div>Fecha</div>
            <div class="text-right">Acciones</div>
        </div>

        <div class="flex-1 overflow-y-auto">
            <div id="prospects-list">
                <!-- Inyectado por JS -->
            </div>
            
            <div id="emptyState" class="hidden flex flex-col items-center justify-center p-20 text-slate-400">
                <i data-lucide="info" class="w-12 h-12 mb-4 opacity-20"></i>
                <p class="text-sm font-medium">No se encontraron prospectos con esos filtros</p>
            </div>
        </div>
    </main>

    <!-- Footer Móvil -->
    <div class="fixed bottom-6 left-1/2 -translate-x-1/2 w-[90%] max-w-sm bg-white/90 backdrop-blur-xl border border-slate-200 rounded-3xl p-2 flex justify-around items-center shadow-xl z-50 lg:hidden">
        <a href="index.php" class="p-4 text-slate-400"><i data-lucide="layout-dashboard" class="w-6 h-6"></i></a>
        <a href="prospectos.php" class="p-4 text-indigo-600"><i data-lucide="users" class="w-6 h-6"></i></a>
        <a href="acciones.php" class="p-4 text-slate-400"><i data-lucide="list" class="w-6 h-6"></i></a>
        <a href="landings.php" class="p-4 text-slate-400"><i data-lucide="rocket" class="w-6 h-6"></i></a>
        <a href="marketing.php" class="p-4 text-slate-400"><i data-lucide="image" class="w-6 h-6"></i></a>
    </div>

    <!-- Modales -->
    <div id="prospectModal" class="fixed inset-0 z-[110] hidden flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
        <div class="bg-white w-full max-w-lg rounded-[2.5rem] p-10 shadow-2xl border border-slate-100">
            <h3 id="modalTitle" class="text-2xl font-black mb-8">Nuevo Prospecto</h3>
            <form id="prospectForm" class="space-y-6">
                <input type="hidden" name="id" id="edit-id">
                <input type="text" name="name" required placeholder="Nombre completo" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-4 px-6 outline-none focus:border-indigo-500">
                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1 block mb-1.5">WhatsApp</label>
                    <div id="prospect-phone-picker"></div>
                </div>
                <input type="email" name="email" required placeholder="Email" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-4 px-6 outline-none focus:border-indigo-500">
                <div class="flex gap-4">
                    <button type="submit" class="flex-1 bg-indigo-600 text-white py-4 rounded-2xl font-bold">Guardar</button>
                    <button type="button" onclick="closeModal()" class="flex-1 bg-slate-100 text-slate-500 py-4 rounded-2xl font-bold">Cerrar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="fixed inset-0 z-[120] hidden items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
        <div class="bg-white w-full max-w-sm rounded-[2.5rem] p-10 text-center shadow-2xl">
            <h2 class="text-2xl font-bold mb-4">¿Eliminar Prospecto?</h2>
            <div class="flex flex-col gap-3">
                <button id="confirmDeleteBtn" class="w-full py-4 bg-red-600 text-white font-bold rounded-2xl">Si, eliminar</button>
                <button onclick="closeDeleteModal()" class="w-full py-4 bg-slate-100 text-slate-500 font-bold rounded-2xl">Cancelar</button>
            </div>
        </div>
    </div>

    <!-- Modal Reporte de Agente -->
    <div id="reportModal" class="fixed inset-0 z-[120] hidden items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
        <div class="bg-white w-full max-w-2xl max-h-[90vh] rounded-[2.5rem] shadow-2xl border border-slate-100 overflow-hidden flex flex-col">
            <div class="p-6 border-b border-slate-100 flex items-center justify-between shrink-0">
                <div>
                    <h3 id="reportModalTitle" class="text-lg font-black">Reporte del Agente</h3>
                    <p id="reportModalSub" class="text-xs text-slate-400 mt-0.5"></p>
                </div>
                <button onclick="closeReportModal()" class="w-8 h-8 bg-slate-100 rounded-full flex items-center justify-center hover:bg-slate-200 transition-all">
                    <i data-lucide="x" class="w-4 h-4 text-slate-500"></i>
                </button>
            </div>
            <div id="reportModalBody" class="p-6 overflow-y-auto">
                <div class="text-center py-8 text-slate-400">
                    <div class="animate-spin w-6 h-6 border-2 border-purple-600 border-t-transparent rounded-full mx-auto mb-2"></div>
                    <p class="text-sm">Cargando reporte...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Importar -->
    <div id="importModal" class="fixed inset-0 z-[110] hidden flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
        <div class="bg-white w-full max-w-lg rounded-[2.5rem] p-10 shadow-2xl border border-slate-100">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center">
                    <i data-lucide="upload-cloud" class="w-6 h-6"></i>
                </div>
                <div>
                    <h3 class="text-xl font-black">Importar Prospectos</h3>
                    <p class="text-slate-400 text-xs font-semibold">Sube un archivo CSV con columnas: Nombre, Email, WhatsApp</p>
                </div>
            </div>
            
            <div id="importDropZone" class="border-2 border-dashed border-slate-200 rounded-[2rem] p-10 text-center hover:border-indigo-400 transition-all cursor-pointer group mb-6 bg-slate-50/50">
                <input type="file" id="importFile" class="hidden" accept=".csv,.txt">
                <div onclick="document.getElementById('importFile').click()">
                    <i data-lucide="file-up" class="w-10 h-10 mx-auto mb-4 text-slate-300 group-hover:text-indigo-500 transition-colors"></i>
                    <p class="text-sm font-bold text-slate-600">Haz clic para seleccionar archivo</p>
                    <p class="text-[10px] text-slate-400 mt-1 uppercase tracking-widest font-bold">Formato CSV o TXT</p>
                </div>
            </div>

            <div class="flex gap-4">
                <button type="button" onclick="closeImportModal()" class="flex-1 bg-slate-100 text-slate-500 py-4 rounded-2xl font-bold hover:bg-slate-200 transition-all">Cancelar</button>
            </div>
        </div>
    </div>

    <!-- â•â• MODAL: Lead de Agente â•â• -->
    <div id="agentLeadModal" class="fixed inset-0 z-[110] hidden flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl w-full max-w-lg p-6 shadow-2xl max-h-[80vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-slate-800">Lead de Agente</h3>
                <button onclick="closeAgentLeadModal()" class="text-slate-400 hover:text-slate-600"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div id="agentLeadContent" class="space-y-3"></div>
        </div>
    </div>

    <!-- â•â• MODAL: Notificación / Confirmación Personalizada (REDiseño PREMIUM) â•â• -->
    <div id="customModal" class="fixed inset-0 z-[200] hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md">
        <div id="modal-container" class="bg-white w-full max-w-md rounded-[32px] p-8 md:p-10 text-center shadow-[0_25px_60px_-15px_rgba(0,0,0,0.5)] border border-slate-100 animate-fadeIn relative">
            
            <!-- ICONO CON EFECTO DE HALO GLOWING -->
            <div class="relative mb-6 flex justify-center">
                <div id="customModalHalo" class="absolute inset-0 bg-indigo-100 rounded-full blur-xl opacity-70 animate-pulse-ring hidden"></div>
                <div id="customModalIcon" class="relative w-16 h-16 bg-blue-50 border border-blue-100 text-blue-600 rounded-2xl flex items-center justify-center shadow-inner">
                    <i data-lucide="info" class="w-8 h-8"></i>
                </div>
            </div>

            <!-- TEXTOS -->
            <div class="space-y-3 mb-8">
                <h3 id="customModalTitle" class="text-2xl font-extrabold text-slate-900 tracking-tight">Título</h3>
                <div id="customModalMessage" class="text-[15px] text-slate-500 leading-relaxed px-2">Mensaje descriptivo.</div>
            </div>
            
            <!-- ACCIONES PRINCIPALES -->
            <div id="customModalActions" class="w-full space-y-3">
                <!-- Se inyectan botones dinámicamente -->
            </div>

            <!-- ESTADO DE PROCESAMIENTO (Específico para Importación) -->
            <div id="processing-container" class="w-full hidden space-y-4 mt-6">
                <div class="flex justify-between items-center text-xs font-bold text-slate-400 px-1">
                    <span id="progress-label">Procesando base de datos...</span>
                    <span id="progress-percent">0%</span>
                </div>
                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                    <div id="progress-bar" class="bg-[#5c59f2] h-full w-0 transition-all duration-300 rounded-full shadow-[0_0_10px_rgba(92,89,242,0.4)]"></div>
                </div>
            </div>

            <!-- ESTADO DE ‰XITO FINAL -->
            <div id="success-container" class="w-full hidden space-y-4 mt-6">
                <div class="bg-emerald-50 text-emerald-700 p-4 rounded-2xl border border-emerald-100 text-sm font-semibold flex items-center justify-center space-x-2">
                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                    <span>¡Operación completada con éxito!</span>
                </div>
                <button onclick="closeAllModals()" class="text-xs text-indigo-600 hover:text-indigo-800 font-bold underline transition-colors">
                    Cerrar ventana
                </button>
            </div>
        </div>
    </div>

    <script>
        const IS_ADMIN = <?php echo $is_admin ? 'true' : 'false'; ?>;
        let allProspects = [];
        let filteredProspects = [];
        let selectedProspects = new Set();
        let prospectToDelete = null;
        let isBulkDelete = false;
        let globalSettings = { card_bg: '#ffffff' };
        let userFilter = '';

        function escapeHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
        function toggleMenu() { document.getElementById('mobileMenu').classList.toggle('hidden'); }
        async function logout() { fetch('api/auth.php?action=logout').then(() => window.location.href = 'login.php'); }
        
        if (typeof PhonePicker !== 'undefined') {
            PhonePicker.render('prospect-phone-picker', 'whatsapp', { theme: 'crm', placeholder: 'Número local' });
        }

        function openModal(isEdit = false) { 
            document.getElementById('modalTitle').innerText = isEdit ? 'Editar Prospecto' : 'Nuevo Prospecto';
            document.getElementById('prospectModal').classList.remove('hidden'); document.getElementById('prospectModal').classList.add('flex');
        }
        function closeModal() { document.getElementById('prospectModal').classList.add('hidden'); document.getElementById('prospectForm').reset(); }
        function closeDeleteModal() { document.getElementById('deleteModal').classList.add('hidden'); }

        async function openReportModal(prospectId) {
            const p = allProspects.find(x => x.id == prospectId);
            if (!p) return;
            document.getElementById('reportModalTitle').textContent = 'Reporte: ' + (p.name || 'Sin nombre');
            document.getElementById('reportModalSub').textContent = (p.email || '') + (p.email && p.whatsapp ? ' · ' : '') + (p.whatsapp ? 'Tel: ' + p.whatsapp : '') || '';
            document.getElementById('reportModalBody').innerHTML = '<div class="text-center py-8 text-slate-400"><div class="animate-spin w-6 h-6 border-2 border-purple-600 border-t-transparent rounded-full mx-auto mb-2"></div><p class="text-sm">Cargando reporte...</p></div>';
            document.getElementById('reportModal').classList.remove('hidden');
            document.getElementById('reportModal').classList.add('flex');
            lucide.createIcons();

            try {
                const params = new URLSearchParams();
                if (p.email) params.set('email', p.email);
                else if (p.whatsapp) params.set('whatsapp', p.whatsapp);
                const res = await fetch('api/agent-report.php?' + params.toString());
                const data = await res.json();
                if (data.error) {
                    document.getElementById('reportModalBody').innerHTML = '<div class="text-center py-8 text-slate-400"><p class="text-sm">' + data.error + '</p></div>';
                    return;
                }
                var stageLabels = { 'new':'Nuevo', 'cold':'Frio', 'warm':'Tibio', 'hot':'Caliente', 'qualified':'Calificado', 'closed':'Cerrado' };
                var intentLabels = {
                    'unknown':'Sin clasificar', 'pricing_question':'Consulta de precios', 'lead_capture':'Captura de lead',
                    'service_interest':'Interes en servicio', 'general_question':'Consulta general', 'greeting':'Saludo',
                    'goodbye':'Despedida', 'complaint':'Queja', 'human_request':'Solicita humano',
                    'spam_or_abuse':'Spam/Abuso', 'other':'Otro'
                };
                var html = '';
                if (data.session) {
                    html += '<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">' +
                        '<div class="bg-slate-50 rounded-xl p-3"><p class="text-[10px] font-semibold text-slate-400 uppercase">Intencion</p><p class="text-sm font-bold text-slate-700 mt-1">' + (intentLabels[data.session.intent] || data.session.intent || '-') + '</p></div>' +
                        '<div class="bg-slate-50 rounded-xl p-3"><p class="text-[10px] font-semibold text-slate-400 uppercase">Tema</p><p class="text-sm font-bold text-slate-700 mt-1">' + (data.session.topic || '-') + '</p></div>' +
                        '<div class="bg-slate-50 rounded-xl p-3"><p class="text-[10px] font-semibold text-slate-400 uppercase">Lead Stage</p><p class="text-sm font-bold text-slate-700 mt-1">' + (stageLabels[data.session.lead_stage] || data.session.lead_stage || '-') + '</p></div>' +
                        '<div class="bg-slate-50 rounded-xl p-3"><p class="text-[10px] font-semibold text-slate-400 uppercase">Score Delta</p><p class="text-sm font-bold text-slate-700 mt-1">' + (data.session.lead_score_delta || '0') + '</p></div>' +
                    '</div>';
                }
                if (data.messages && data.messages.length > 0) {
                    html += '<h4 class="font-bold text-slate-700 mb-3 text-sm">Historial de Chat</h4>';
                    data.messages.forEach(function(m) {
                        var align = m.role === 'user' ? 'ml-8' : 'mr-8';
                        var bg = m.role === 'user' ? 'bg-blue-50 border-blue-100' : 'bg-slate-50 border-slate-200';
                        var label = m.role === 'user' ? 'Usuario' : 'Agente';
                        html += '<div class="' + align + ' mb-2"><div class="' + bg + ' border rounded-lg px-3 py-2"><p class="text-[10px] font-semibold text-slate-400 uppercase mb-1">' + label + '</p><p class="text-xs text-slate-700 whitespace-pre-wrap">' + escapeHtml(m.content) + '</p></div></div>';
                    });
                } else {
                    html = '<div class="text-center py-8 text-slate-400"><p class="text-sm">No se encontraron datos del agente para este prospecto</p></div>';
                }
                document.getElementById('reportModalBody').innerHTML = html;
            } catch(e) {
                document.getElementById('reportModalBody').innerHTML = '<div class="text-center py-8 text-red-500"><p class="text-sm">Error al cargar reporte: ' + e.message + '</p></div>';
            }
            lucide.createIcons();
        }

        function closeReportModal() { document.getElementById('reportModal').classList.add('hidden'); }
        
        function closeAllModals() {
            document.getElementById('customModal').classList.add('hidden');
            document.getElementById('importModal').classList.add('hidden');
            closeDeleteModal();
            closeReportModal();
        }

        function openImportModal() { 
            document.getElementById('importModal').classList.remove('hidden'); 
            document.getElementById('importModal').classList.add('flex'); 
            lucide.createIcons();
        }
        function closeImportModal() { document.getElementById('importModal').classList.add('hidden'); }

        function openAgentLead(id) {
            const p = allProspects.find(x => x.id == id);
            if (!p) return;
            var stageLabels = { 'new':'Nuevo', 'cold':'Frio', 'warm':'Tibio', 'hot':'Caliente', 'qualified':'Calificado', 'closed':'Cerrado' };
            var intentLabels = {
                'unknown':'Sin clasificar', 'pricing_question':'Consulta de precios', 'lead_capture':'Captura de lead',
                'service_interest':'Interes en servicio', 'general_question':'Consulta general', 'greeting':'Saludo',
                'goodbye':'Despedida', 'complaint':'Queja', 'human_request':'Solicita humano',
                'spam_or_abuse':'Spam/Abuso', 'other':'Otro'
            };
            const stageColor = { 'new':'bg-slate-100 text-slate-500', 'cold':'bg-blue-100 text-blue-700', 'warm':'bg-amber-100 text-amber-700', 'hot':'bg-orange-100 text-orange-700', 'qualified':'bg-green-100 text-green-700', 'closed':'bg-gray-100 text-gray-500' };
            const sc = stageColor[p.status] || 'bg-slate-100 text-slate-500';
            var html = '<div class="grid grid-cols-2 gap-3 text-sm">' +
                '<div class="bg-slate-50 p-3 rounded-lg"><span class="text-xs text-slate-400 block">Nombre</span><span class="font-medium">' + escapeHtml(p.name || '-') + '</span></div>' +
                '<div class="bg-slate-50 p-3 rounded-lg"><span class="text-xs text-slate-400 block">Email</span><span class="font-medium">' + escapeHtml(p.email || '-') + '</span></div>' +
                '<div class="bg-slate-50 p-3 rounded-lg"><span class="text-xs text-slate-400 block">WhatsApp</span><span class="font-medium">' + escapeHtml(p.whatsapp || '-') + '</span></div>' +
                '<div class="bg-slate-50 p-3 rounded-lg"><span class="text-xs text-slate-400 block">Score</span><span class="font-bold text-blue-600">' + (p.lead_score || 0) + '</span></div>' +
                '<div class="bg-slate-50 p-3 rounded-lg"><span class="text-xs text-slate-400 block">Estado</span><span class="px-2 py-0.5 rounded-full text-xs font-medium ' + sc + '">' + (stageLabels[p.status] || p.status || 'new') + '</span></div>' +
                '<div class="bg-slate-50 p-3 rounded-lg"><span class="text-xs text-slate-400 block">Interes</span><span class="font-medium">' + escapeHtml(p.service_interest || '-') + '</span></div>' +
                '<div class="bg-slate-50 p-3 rounded-lg"><span class="text-xs text-slate-400 block">Empresa</span><span class="font-medium">' + escapeHtml(p.company || '-') + '</span></div>' +
                '<div class="bg-slate-50 p-3 rounded-lg"><span class="text-xs text-slate-400 block">Presupuesto</span><span class="font-medium">' + (p.estimated_budget ? '$' + p.estimated_budget : '-') + '</span></div>' +
                '<div class="bg-slate-50 p-3 rounded-lg"><span class="text-xs text-slate-400 block">Urgencia</span><span class="font-medium">' + escapeHtml(p.urgency || '-') + '</span></div>' +
                '<div class="bg-slate-50 p-3 rounded-lg"><span class="text-xs text-slate-400 block">Problema</span><span class="font-medium">' + escapeHtml(p.main_problem || '-') + '</span></div>' +
                '</div>';
            if (p.conversation_summary) html += '<div class="bg-indigo-50 p-3 rounded-lg mt-3"><span class="text-xs text-indigo-400 block mb-1">Resumen</span><span class="text-sm text-slate-700">' + escapeHtml(p.conversation_summary) + '</span></div>';
            html += '<div class="flex gap-2 mt-4"><button onclick="convertAgentLeadToProspect(' + p.id + ')" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-lg transition">Convertir a Prospecto CRM</button><button onclick="deleteAgentLead(' + p.id + ')" class="px-4 bg-red-50 hover:bg-red-100 text-red-600 font-semibold py-2.5 rounded-lg transition">Eliminar</button></div>';
            document.getElementById('agentLeadContent').innerHTML = html;
            document.getElementById('agentLeadModal').classList.remove('hidden');
            document.getElementById('agentLeadModal').classList.add('flex');
        }
        function closeAgentLeadModal() { document.getElementById('agentLeadModal').classList.add('hidden'); }

        async function deleteAgentLead(id) {
            if (!confirm('Eliminar este lead de agente?')) return;
            await fetch('api/prospects.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id: id, agent_lead: true })
            });
            closeAgentLeadModal();
            fetchProspects();
        }

        async function convertAgentLeadToProspect(id) {
            const p = allProspects.find(x => x.id == id);
            if (!p) return;
            const res = await fetch('api/prospects.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    name: p.name || 'Lead ' + p.whatsapp, 
                    email: p.email || '', 
                    whatsapp: p.whatsapp || '',
                    origin_type: 'agent',
                    agent_id: p.agent_id,
                    agent_domain: p.agent_domain || p.domain
                })
            });
            const data = await res.json();
            if (data.success) { alert('Prospecto creado en CRM'); closeAgentLeadModal(); fetchProspects(); }
            else { alert('Error: ' + (data.error || 'desconocido')); }
        }

        async function fetchSettings() {
            try {
                const res = await fetch('api/landings.php');
                const landings = await res.json();
                const filter = document.getElementById('landingFilter');
                filter.innerHTML = '<option value="">Todos los orígenes</option>';
                
                const optManual = document.createElement('option');
                optManual.value = 'manual';
                optManual.textContent = 'Carga Manual';
                filter.appendChild(optManual);

                const optImport = document.createElement('option');
                optImport.value = 'import';
                optImport.textContent = 'Importación CSV';
                filter.appendChild(optImport);

                const optAgent = document.createElement('option');
                optAgent.value = 'agent';
                optAgent.textContent = 'Agentes IA';
                filter.appendChild(optAgent);

                if (Array.isArray(landings)) {
                    landings.forEach(l => {
                        const opt = document.createElement('option');
                        opt.value = 'landing_' + l.id;
                        opt.textContent = 'Landing: ' + l.title;
                        filter.appendChild(opt);
                    });
                }
            } catch(e) { console.error('Error fetching landings:', e); }

            try {
                const res = await fetch('api/settings.php');
                const data = await res.json();
                if (data.card_bg) {
                    globalSettings.card_bg = data.card_bg;
                    document.documentElement.style.setProperty('--card-bg', data.card_bg);
                }
                fetchProspects();
            } catch (err) { fetchProspects(); }
        }

        async function fetchProspects() {
            const res = await fetch('api/prospects.php');
            allProspects = await res.json();
            // Poblar filtro de usuarios (admin)
            if (IS_ADMIN) {
                var sel = document.getElementById('userFilterSelect');
                var users = {};
                allProspects.forEach(function(p) { if (p.user_name) users[p.user_name] = true; });
                var names = Object.keys(users).sort();
                sel.innerHTML = '<option value="">Todos los usuarios</option>' +
                    names.map(function(n) { return '<option value="' + escapeHtml(n) + '">' + escapeHtml(n) + '</option>'; }).join('');
            }
            filterProspects();
        }

        function filterProspects() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const originFilter = document.getElementById('landingFilter').value;
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;

            filteredProspects = allProspects.filter(p => {
                const safeName = p.name ? p.name.toLowerCase() : '';
                const safeWhatsapp = p.whatsapp ? p.whatsapp.toLowerCase() : '';
                const matchesSearch = safeName.includes(search) || safeWhatsapp.includes(search);
                
                const p_origin = p.origin_type || (p.landing_id ? 'landing' : 'manual');
                let matchesOrigin = true;
                if (originFilter) {
                    if (originFilter === 'manual') {
                        matchesOrigin = (p_origin === 'manual');
                    } else if (originFilter === 'import') {
                        matchesOrigin = (p_origin === 'import');
                    } else if (originFilter === 'agent') {
                        matchesOrigin = (p_origin === 'agent');
                    } else if (originFilter.startsWith('landing_')) {
                        const targetLandingId = originFilter.replace('landing_', '');
                        matchesOrigin = (p_origin === 'landing' && p.landing_id == targetLandingId);
                    }
                }
                
                const date = p.created_at ? p.created_at.split(' ')[0] : '';
                const matchesFrom = !from || date >= from;
                const matchesTo = !to || date <= to;
                
                var matchesUser = !userFilter || (p.user_name && p.user_name === userFilter);
                return matchesSearch && matchesOrigin && matchesFrom && matchesTo && matchesUser;
            });

            renderProspects(filteredProspects);
        }

        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('landingFilter').value = '';
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            filterProspects();
        }

        function renderProspects(data) {
            const list = document.getElementById('prospects-list');
            const countBadge = document.getElementById('countBadge');
            const emptyState = document.getElementById('emptyState');

            countBadge.textContent = data.length;
            
            if (data.length === 0) {
                list.innerHTML = '';
                emptyState.classList.remove('hidden');
                return;
            }

            emptyState.classList.add('hidden');

            if (IS_ADMIN && !userFilter) {
                // Agrupar por usuario
                var groups = {};
                data.forEach(function(p) {
                    var key = p.user_name || 'Sin asignar';
                    if (!groups[key]) groups[key] = [];
                    groups[key].push(p);
                });
                var keys = Object.keys(groups).sort();
                var html = '';
                keys.forEach(function(user) {
                    var items = groups[user];
                    html += '<div class="border-b border-slate-200 last:border-b-0">';
                    html += '<div class="sticky top-0 z-10 bg-slate-100 px-4 py-2 flex items-center gap-2 text-xs font-bold text-slate-600 uppercase tracking-wider">';
                    html += '<i data-lucide="user" class="w-3.5 h-3.5"></i>';
                    html += escapeHtml(user) + ' <span class="text-slate-400 font-normal normal-case">(' + items.length + ' prospectos)</span>';
                    html += '</div>';
                    html += items.map(function(p) { return renderProspectCard(p); }).join('');
                    html += '</div>';
                });
                list.innerHTML = html;
                lucide.createIcons();
                updateBulkUI();
                return;
            }

            list.innerHTML = data.map(p => renderProspectCard(p)).join('');
            lucide.createIcons();
            updateBulkUI();
        }

        function renderProspectCard(p) {
                const date = p.created_at ? p.created_at.split(' ')[0] : 'â€”';
                const isChecked = selectedProspects.has(p.id.toString());
                
                let landingName = 'Carga Manual';
                let badgeStyle = 'background-color: rgba(100, 116, 139, 0.1); color: rgb(100, 116, 139);'; // Gris por defecto (manual)

                if (p.origin_type === 'landing' || (!p.origin_type && p.landing_id)) {
                    landingName = p.landing_title ? 'Landing: ' + p.landing_title : 'Landing';
                    const color = p.landing_color || '#3b82f6';
                    badgeStyle = `background-color: ${color}15; color: ${color};`;
                } else if (p.origin_type === 'agent') {
                    landingName = p.landing_title || 'Agente IA';
                    badgeStyle = `background-color: rgba(99, 102, 241, 0.1); color: rgb(99, 102, 241);`; // Indigo
                } else if (p.origin_type === 'import') {
                    landingName = 'Importación CSV';
                    badgeStyle = `background-color: rgba(16, 185, 129, 0.1); color: rgb(16, 185, 129);`; // Emerald
                } else if (p.landing_title) {
                    landingName = 'Landing: ' + p.landing_title;
                    const color = p.landing_color || '#3b82f6';
                    badgeStyle = `background-color: ${color}15; color: ${color};`;
                }
                
                const hasAgentData = p.agent_lead === true || p.agent_lead === 'true' || p.agent_lead === 1;
                return `
                <div class="list-row p-4 md:py-3 cursor-pointer group${IS_ADMIN ? ' admin-grid' : ''}" onclick="if(!event.target.closest('button') && !event.target.closest('input')) { window.location.href='prospecto.php?${hasAgentData ? 'agent_lead' : 'id'}=${p.id}'; }">
                    <div class="flex justify-center">
                        <input type="checkbox" value="${p.id}" ${isChecked ? 'checked' : ''} onchange="toggleProspectSelection('${p.id}')" class="prospect-checkbox w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                    </div>
                    
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-1.5 h-7 rounded-full shrink-0" style="background-color: #6366f1"></div>
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-slate-800 text-truncate">${p.name || 'Sin nombre'}</div>
                            <div class="text-[10px] text-slate-400 md:hidden">${landingName}${p.domain ? ' · ' + p.domain : ''} | ${date}</div>
                        </div>
                    </div>

                    <?php if ($is_admin): ?><div class="hidden md:flex items-center justify-start text-[10px] font-medium text-slate-500 text-truncate">
                        ${p.user_name || '-'}
                    </div><?php endif; ?>
                    <div class="hidden md:flex items-center justify-start text-[10px] font-medium text-slate-500 text-truncate">
                        ${p.email || '-'}
                    </div>

                    <div class="hidden md:flex items-center justify-start text-[10px] font-medium text-slate-600">
                        ${p.whatsapp || '-'}
                    </div>

                    <div class="hidden md:flex items-center justify-start text-[10px] font-medium text-slate-600">
                        ${p.domain || '-'}
                    </div>

                    <div class="hidden md:flex items-center justify-start">
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" style="${badgeStyle}">
                            ${landingName}
                        </span>
                    </div>

                    <div class="hidden md:flex items-center justify-start text-[10px] font-medium text-slate-500">
                        ${date}
                    </div>

                    <div class="flex items-center justify-end gap-1">
                        <button onclick="event.stopPropagation(); window.location='tel:${p.whatsapp}'" class="w-8 h-8 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all" title="Llamar">
                            <i data-lucide="phone" class="w-3.5 h-3.5"></i>
                        </button>
                        <button onclick="event.stopPropagation(); window.open('https://wa.me/${p.whatsapp}', '_blank')" class="w-8 h-8 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center hover:bg-emerald-600 hover:text-white transition-all" title="WhatsApp">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        </button>
                        <button onclick="event.stopPropagation(); window.location.href='prospecto.php?${hasAgentData ? 'agent_lead' : 'id'}=${p.id}'" class="w-8 h-8 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center hover:bg-purple-600 hover:text-white transition-all" title="Ver detalle">
                            <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                        </button>
                        <button onclick="event.stopPropagation(); askDelete(${p.id})" class="w-8 h-8 bg-red-50 text-red-500 rounded-lg flex items-center justify-center hover:bg-red-600 hover:text-white transition-all" title="Eliminar">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </div>`;
        }

        function toggleProspectSelection(id) {
            if (selectedProspects.has(id)) selectedProspects.delete(id);
            else selectedProspects.add(id);
            updateBulkUI();
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            if (selectAll.checked) {
                filteredProspects.forEach(p => selectedProspects.add(p.id.toString()));
            } else {
                selectedProspects.clear();
            }
            renderProspects(filteredProspects);
        }

        function updateBulkUI() {
            const btn = document.getElementById('bulkDeleteBtn');
            const count = document.getElementById('selectedCount');
            if (selectedProspects.size > 0) {
                btn.classList.remove('hidden');
                btn.style.display = 'flex';
                count.textContent = selectedProspects.size;
            } else {
                btn.classList.add('hidden');
                btn.style.display = 'none';
                const selectAll = document.getElementById('selectAll');
                if (selectAll) selectAll.checked = false;
            }
        }

        function editProspect(p) {
            document.getElementById('edit-id').value = p.id;
            document.querySelector('[name="name"]').value = p.name;
            document.querySelector('[name="email"]').value = p.email;
            const pickerEl = document.getElementById('prospect-phone-picker');
            if (pickerEl && typeof pickerEl._pickerSetValue === 'function') {
                pickerEl._pickerSetValue(p.whatsapp);
            } else {
                const plain = document.getElementById('prospect-wa-plain');
                if (plain) plain.value = p.whatsapp;
            }
            openModal(true);
        }

        function askDelete(id) { 
            prospectToDelete = id; 
            isBulkDelete = false;
            const prospect = allProspects.find(p => p.id == id);
            document.getElementById('deleteModal').classList.remove('hidden'); 
            document.getElementById('deleteModal').classList.add('flex'); 
            lucide.createIcons(); 
        }

        function askBulkDelete() {
            if (selectedProspects.size === 0) return;
            isBulkDelete = true;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
            lucide.createIcons();
        }

        document.getElementById('confirmDeleteBtn').onclick = async () => {
            if (isBulkDelete) {
                const ids = Array.from(selectedProspects);
                const agentIds = ids.filter(id => {
                    const p = allProspects.find(x => x.id == id);
                    return p && (p.agent_lead === true || p.agent_lead === 'true' || p.agent_lead === 1);
                });
                const crmIds = ids.filter(id => !agentIds.includes(id));
                await Promise.all([
                    agentIds.length ? fetch('api/prospects.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'delete', ids: agentIds, agent_lead: true }) }) : null,
                    crmIds.length ? fetch('api/prospects.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'delete', ids: crmIds }) }) : null,
                ]);
            } else {
                const prosp = allProspects.find(p => p.id == prospectToDelete);
                const isAgentLead = prosp && (prosp.agent_lead === true || prosp.agent_lead === 'true' || prosp.agent_lead === 1);
                await fetch('api/prospects.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'delete', id: prospectToDelete, agent_lead: isAgentLead }) });
            }
            
            // Limpiar selección después de borrar (ya sea individual o masivo)
            if (isBulkDelete) {
                selectedProspects.clear();
            } else if (prospectToDelete) {
                selectedProspects.delete(prospectToDelete.toString());
            }
            
            // Resetear UI de selección
            const selectAllCb = document.getElementById('selectAll');
            if (selectAllCb) selectAllCb.checked = false;
            updateBulkUI();
            
            closeDeleteModal(); 
            fetchProspects();
        };

        document.getElementById('prospectForm').onsubmit = async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target).entries());
            await fetch('api/prospects.php', { method: 'POST', body: JSON.stringify(data) });
            closeModal(); fetchProspects();
        };

        // â”€â”€ EXPORTAR â”€â”€
        function exportCSV() {
            if (filteredProspects.length === 0) return alert('No hay datos para exportar');
            const headers = ['Nombre', 'Email', 'WhatsApp', 'Landing', 'Fecha'];
            const rows = filteredProspects.map(p => [
                `"${(p.name || '').replace(/"/g, '""')}"`,
                `"${(p.email || '').replace(/"/g, '""')}"`,
                `"${(p.whatsapp || '').replace(/"/g, '""')}"`,
                `"${(p.landing_title || 'Importación Directa').replace(/"/g, '""')}"`,
                `"${(p.created_at || '').replace(/"/g, '""')}"`
            ]);
            
            const csvContent = "\uFEFF" + [headers, ...rows].map(e => e.join(",")).join("\n");
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            const url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", "prospectos_filtrados.csv");
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function exportPDF() {
            if (filteredProspects.length === 0) return alert('No hay datos para exportar');
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            doc.setFontSize(18);
            doc.setTextColor(30, 58, 138); // Royal Blue
            doc.text("Reporte de Prospectos", 14, 22);
            doc.setFontSize(10);
            doc.setTextColor(100);
            doc.text(`Generado el: ${new Date().toLocaleString()} | Total: ${filteredProspects.length} registros`, 14, 30);

            const tableData = filteredProspects.map(p => [
                p.name, p.email, p.whatsapp, p.landing_title || 'Importación Directa', (p.created_at || '').split(' ')[0]
            ]);

            doc.autoTable({
                startY: 35,
                head: [['Nombre', 'Email', 'WhatsApp', 'Landing', 'Fecha']],
                body: tableData,
                theme: 'striped',
                headStyles: { fillColor: [30, 58, 138], textColor: 255, fontStyle: 'bold' },
                alternateRowStyles: { fillColor: [248, 250, 252] },
                margin: { top: 35 }
            });

            doc.save("prospectos_filtrados.pdf");
        }

        // â”€â”€ SISTEMA DE MODALES PERSONALIZADOS â”€â”€
        function showNotification({ title, message, type = 'info', confirmText = 'Aceptar', cancelText = null, onConfirm = null }) {
            const modal = document.getElementById('customModal');
            const halo = document.getElementById('customModalHalo');
            const iconBox = document.getElementById('customModalIcon');
            const titleEl = document.getElementById('customModalTitle');
            const msgEl = document.getElementById('customModalMessage');
            const actions = document.getElementById('customModalActions');
            const proc = document.getElementById('processing-container');
            const success = document.getElementById('success-container');

            // Reset UI
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            titleEl.innerHTML = title;
            msgEl.innerHTML = message;
            actions.innerHTML = '';
            actions.classList.remove('hidden');
            proc.classList.add('hidden');
            success.classList.add('hidden');
            halo.classList.add('hidden');

            // Icon & Colors
            iconBox.className = "relative w-16 h-16 border rounded-2xl flex items-center justify-center shadow-inner ";
            if (type === 'success') {
                iconBox.classList.add('bg-emerald-50', 'text-emerald-600', 'border-emerald-100');
                iconBox.innerHTML = '<i data-lucide="check-circle" class="w-8 h-8"></i>';
            } else if (type === 'error') {
                iconBox.classList.add('bg-rose-50', 'text-rose-600', 'border-rose-100');
                iconBox.innerHTML = '<i data-lucide="x-circle" class="w-8 h-8"></i>';
            } else if (type === 'confirm' || type === 'import') {
                iconBox.classList.add('bg-indigo-50', 'text-indigo-600', 'border-indigo-100');
                iconBox.innerHTML = `<i data-lucide="${type === 'import' ? 'file-check-2' : 'help-circle'}" class="w-8 h-8"></i>`;
                if (type === 'import') halo.classList.remove('hidden');
            } else {
                iconBox.classList.add('bg-blue-50', 'text-blue-600', 'border-blue-100');
                iconBox.innerHTML = '<i data-lucide="info" class="w-8 h-8"></i>';
            }

            // Buttons
            const btnConfirm = document.createElement('button');
            btnConfirm.className = `w-full py-4 px-6 font-bold rounded-2xl transition-all shadow-lg text-sm tracking-wide active:scale-[0.98] ${type === 'import' ? 'bg-[#5c59f2] hover:bg-[#4d4ae0] text-white' : (type === 'confirm' ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'bg-slate-900 text-white hover:bg-slate-800')}`;
            btnConfirm.innerHTML = `<span>${confirmText}</span>`;
            btnConfirm.onclick = () => {
                if (type === 'import' && onConfirm) {
                    onConfirm(); // La función onConfirm se encargará de mostrar la barra de progreso
                } else {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    if (onConfirm) onConfirm();
                }
            };
            actions.appendChild(btnConfirm);

            if (cancelText) {
                const btnCancel = document.createElement('button');
                btnCancel.className = "w-full py-3.5 px-6 bg-[#f8fafc] hover:bg-slate-100 text-slate-500 hover:text-slate-800 font-bold rounded-2xl text-sm tracking-wide transition-all";
                btnCancel.textContent = cancelText;
                btnCancel.onclick = () => {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                };
                actions.appendChild(btnCancel);
            }

            lucide.createIcons();
        }

        // â”€â”€ IMPORTAR (MEJORADO) â”€â”€
        document.getElementById('importFile').onchange = function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = async function(event) {
                const text = event.target.result;
                const lines = text.split(/\r?\n/).filter(l => l.trim() !== '');
                if (lines.length === 0) return showNotification({ title: 'Error', message: 'El archivo está vacío.', type: 'error' });

                // Detectar delimitador (coma o punto y coma)
                const firstLine = lines[0];
                const commaCount = (firstLine.match(/,/g) || []).length;
                const semiCount = (firstLine.match(/;/g) || []).length;
                const delimiter = semiCount > commaCount ? ';' : ',';
                
                const prospectsToImport = [];
                lines.forEach((line, index) => {
                    const parts = line.split(delimiter).map(s => s.replace(/^["']|["']$/g, '').trim());
                    
                    if (parts.length >= 2) {
                        // Ignorar headers
                        if (index === 0 && (parts[0].toLowerCase().includes('nombre') || parts[0].toLowerCase().includes('name'))) return;
                        
                        const p = {
                            name: parts[0],
                            email: '',
                            whatsapp: '',
                            action: 'create'
                        };

                        // Analizar el resto de columnas para ver qué son
                        parts.slice(1).forEach(col => {
                            if (col.includes('@')) {
                                p.email = col;
                            } else {
                                // Si parece un número (tiene dígitos)
                                const clean = col.replace(/[^0-9+]/g, '');
                                if (clean.length >= 6) {
                                    p.whatsapp = clean;
                                }
                            }
                        });

                        if (p.name && p.whatsapp) {
                            prospectsToImport.push(p);
                        }
                    }
                });

                if (prospectsToImport.length === 0) {
                    return showNotification({ 
                        title: 'Sin datos', 
                        message: 'No se encontraron prospectos válidos en el archivo. Asegúrate de que las columnas sean: Nombre, Email, WhatsApp.', 
                        type: 'error' 
                    });
                }

                showNotification({
                    title: 'Confirmación de Importación',
                    message: `Se detectaron <span class="font-bold text-indigo-600 bg-indigo-50 px-2.5 py-0.5 rounded-full">${prospectsToImport.length} prospectos</span> en el archivo listos para procesar.<br><p class="text-sm font-semibold text-slate-400 pt-2">¿Deseas importarlos ahora al sistema?</p>`,
                    type: 'import',
                    confirmText: 'Sí, importar ahora',
                    cancelText: 'Cancelar',
                    onConfirm: async () => {
                        const actions = document.getElementById('customModalActions');
                        const proc = document.getElementById('processing-container');
                        const bar = document.getElementById('progress-bar');
                        const percent = document.getElementById('progress-percent');
                        const label = document.getElementById('progress-label');
                        const success = document.getElementById('success-container');

                        actions.classList.add('hidden');
                        proc.classList.remove('hidden');

                        // Simular barra de progreso mientras se hace la petición real
                        let progress = 0;
                        const interval = setInterval(() => {
                            if (progress < 90) {
                                progress += 5;
                                bar.style.width = `${progress}%`;
                                percent.innerText = `${progress}%`;
                                if (progress === 40) label.innerText = "Limpiando duplicados...";
                                else if (progress === 80) label.innerText = "Inyectando leads...";
                            }
                        }, 100);

                        try {
                            const res = await fetch('api/prospects.php', { 
                                method: 'POST', 
                                body: JSON.stringify({ 
                                    action: 'bulk_create', 
                                    prospects: prospectsToImport 
                                }) 
                            });
                            const result = await res.json();
                            
                            clearInterval(interval);
                            bar.style.width = `100%`;
                            percent.innerText = `100%`;
                            
                            if (result.success) {
                                setTimeout(() => {
                                    proc.classList.add('hidden');
                                    success.classList.remove('hidden');
                                    fetchProspects();
                                }, 500);
                            } else {
                                throw new Error(result.error || 'Error desconocido');
                            }
                        } catch (err) { 
                            clearInterval(interval);
                            showNotification({ title: 'Error', message: 'No se pudo completar la importación: ' + err.message, type: 'error' });
                        }
                    }
                });
            };
            reader.readAsText(file);
            e.target.value = ''; // Reset file input
        };

        fetchSettings();
        lucide.createIcons();
    </script>
</body>
</html>
