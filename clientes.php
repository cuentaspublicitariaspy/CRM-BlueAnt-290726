<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once 'api/config.php';
$user_id = (int)$_SESSION['user_id'];
$is_admin = ($_SESSION['user_role'] ?? 'subscriber') === 'admin';

// Obtener todos los servicios del usuario para filtros y modal
$stmtSrv = $pdo->prepare("SELECT id, name, price FROM services ORDER BY name ASC");
$stmtSrv->execute();
$all_services = $stmtSrv->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - Ultra CRM</title>
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
            grid-template-columns: 40px 1fr auto;
            transition: all 0.1s ease;
            border-bottom: 1px solid #f1f5f9;
            background: white;
        }

        @media (min-width: 768px) {
            .list-row {
                grid-template-columns: 40px 1.5fr 1.2fr 1fr 1fr 1fr 120px;
                padding: 0 16px;
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

        /* ── ANIMACIONES PREMIUM ── */
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

        /* Status Badges */
        .status-active { background-color: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .status-inactive { background-color: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5; }
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

                            <a href="clientes.php" class="nav-link active flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

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

                    <a href="acciones.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                        <i data-lucide="list" class="w-5 h-5"></i>Acciones</a>

                    <a href="clientes.php" class="nav-link active flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

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
        <!-- Header -->
        <header class="h-16 border-b flex items-center justify-between px-6 lg:px-10 bg-white sticky top-0 z-40">
            <div class="flex items-center gap-4">
                <button onclick="toggleMenu()" class="lg:hidden p-2 text-slate-500"><i data-lucide="menu" class="w-6 h-6"></i></button>
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-bold text-slate-900 leading-none">Clientes</h2>
                    <span id="countBadge" class="bg-indigo-50 text-[#5c59f2] text-[10px] font-black px-2 py-0.5 rounded-full">0</span>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                <!-- Botón Eliminar Selección -->
                <button id="bulkDeleteBtn" onclick="askBulkDelete()" class="hidden bg-rose-50 text-rose-600 px-3 py-1.5 rounded-md text-xs font-bold shadow-sm transition-all active:scale-95 flex items-center gap-1.5 hover:bg-rose-500 hover:text-white">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    <span>Eliminar (<span id="selectedCount">0</span>)</span>
                </button>

                <button onclick="openModal()" class="bg-[#5c59f2] hover:bg-[#4d4ae0] text-white px-3 py-1.5 rounded-md text-xs font-bold shadow-sm transition-all active:scale-95 flex items-center gap-2">
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
                    <input type="text" id="searchInput" oninput="filterClients()" placeholder="Buscar..." class="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-2.5 text-slate-400"></i>
                </div>
            </div>

            <!-- Filtro Estado -->
            <div class="w-full md:w-40">
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5 tracking-wider">Estado</label>
                <select id="statusFilter" onchange="filterClients()" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all cursor-pointer appearance-none">
                    <option value="all">Todos los estados</option>
                    <option value="cliente_activo">Activo</option>
                    <option value="cliente_inactivo">Inactivo</option>
                </select>
            </div>

            <!-- Filtro Servicios -->
            <div class="w-full md:w-48">
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5 tracking-wider">Servicio contratado</label>
                <select id="serviceFilter" onchange="filterClients()" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all cursor-pointer appearance-none">
                    <option value="all">Todos los servicios</option>
                    <?php foreach ($all_services as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Rango Fechas -->
            <div class="flex gap-2 w-full md:w-auto">
                <div class="flex-1 md:w-36">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5 tracking-wider">Desde</label>
                    <input type="date" id="dateFrom" onchange="filterClients()" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                </div>
                <div class="flex-1 md:w-36">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5 tracking-wider">Hasta</label>
                    <input type="date" id="dateTo" onchange="filterClients()" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                </div>
            </div>

            <!-- Limpiar -->
            <button onclick="resetFilters()" class="p-2.5 text-slate-400 hover:text-[#5c59f2] hover:bg-blue-50 rounded-lg transition-all" title="Limpiar filtros">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
            </button>

            <!-- Exportar / Importar -->
            <div class="flex border-l pl-4 gap-1">
                <button onclick="exportCSV()" class="p-2.5 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-all" title="Exportar CSV">
                    <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                </button>
                <button onclick="exportPDF()" class="p-2.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-all" title="Exportar PDF">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                </button>
                <button onclick="openImportModal()" class="p-2.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all" title="Importar Clientes">
                    <i data-lucide="upload-cloud" class="w-4 h-4"></i>
                </button>
            </div>
        </section>

        <!-- Cabecera Tabla (Desktop) -->
        <div class="hidden md:grid list-row bg-slate-50 h-10 text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b">
            <div class="flex justify-center">
                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
            </div>
            <div>Cliente</div>
            <div>Servicios Contratados</div>
            <div>Idioma / Ubicación</div>
            <div>WhatsApp</div>
            <div>Estado</div>
            <div class="text-right">Acciones</div>
        </div>

        <div class="flex-1 overflow-y-auto">
            <div id="clients-list">
                <!-- Inyectado por JS -->
            </div>
            
            <div id="emptyState" class="hidden flex flex-col items-center justify-center p-20 text-slate-400">
                <i data-lucide="info" class="w-12 h-12 mb-4 opacity-20"></i>
                <p class="text-sm font-medium">No se encontraron clientes con esos filtros</p>
            </div>
        </div>
    </main>

    <!-- Footer Móvil -->
    <div class="fixed bottom-6 left-1/2 -translate-x-1/2 w-[90%] max-w-sm bg-white/90 backdrop-blur-xl border border-slate-200 rounded-3xl p-2 flex justify-around items-center shadow-xl z-50 lg:hidden">
        <a href="index.php" class="p-4 text-slate-400"><i data-lucide="layout-dashboard" class="w-6 h-6"></i></a>
        <a href="prospectos.php" class="p-4 text-slate-400"><i data-lucide="users" class="w-6 h-6"></i></a>
        <a href="clientes.php" class="p-4 text-[#5c59f2]"><i data-lucide="user-check" class="w-6 h-6"></i></a>
        <a href="acciones.php" class="p-4 text-slate-400"><i data-lucide="list" class="w-6 h-6"></i></a>
        <a href="marketing.php" class="p-4 text-slate-400"><i data-lucide="image" class="w-6 h-6"></i></a>
    </div>

    <!-- Modal Formulario Agregar/Editar Cliente -->
    <div id="clientModal" class="fixed inset-0 z-[110] hidden flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
        <div class="bg-white w-full max-w-2xl rounded-[2.5rem] p-8 md:p-10 shadow-2xl border border-slate-100 max-h-[90vh] overflow-y-auto">
            <h3 id="modalTitle" class="text-2xl font-black mb-6">Nuevo Cliente</h3>
            <form id="clientForm" class="space-y-6" onsubmit="saveClient(event)">
                <input type="hidden" name="id" id="edit-id">
                
                <!-- Datos Básicos -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label for="modal_name" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Nombre Completo</label>
                        <input type="text" name="name" id="modal_name" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                    </div>
                    <div>
                        <label for="modal_email" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Correo Electrónico</label>
                        <input type="email" name="email" id="modal_email" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1.5">WhatsApp / Teléfono</label>
                        <div id="client-phone-picker"></div>
                    </div>
                    <div>
                        <label for="modal_language" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Idioma</label>
                        <select name="language" id="modal_language" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                            <option value="es">Español</option>
                            <option value="en">Inglés</option>
                        </select>
                    </div>
                </div>

                <!-- Ubicación -->
                <div class="border-t border-slate-100 pt-5 space-y-4">
                    <h4 class="text-sm font-bold text-slate-800">Dirección y Ubicación</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div class="md:col-span-3">
                            <label for="modal_address" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Calle y Número</label>
                            <input type="text" name="address" id="modal_address" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                        </div>
                        <div>
                            <label for="modal_city" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Ciudad</label>
                            <input type="text" name="city" id="modal_city" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                        </div>
                        <div>
                            <label for="modal_state" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Estado</label>
                            <input type="text" name="state" id="modal_state" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                        </div>
                        <div>
                            <label for="modal_zip_code" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Código Postal</label>
                            <input type="text" name="zip_code" id="modal_zip_code" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                        </div>
                    </div>
                </div>

                <!-- Estado Inicial -->
                <div>
                    <label for="modal_status" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Estado</label>
                    <select name="status" id="modal_status" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                        <option value="cliente_activo">Activo</option>
                        <option value="cliente_inactivo">Inactivo</option>
                    </select>
                </div>

                <!-- Servicios -->
                <div class="border-t border-slate-100 pt-5">
                    <label class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-3">Servicios Contratados</label>
                    <?php if (empty($all_services)): ?>
                        <p class="text-sm text-slate-400 italic">No tienes servicios definidos. Créalos en la sección de Servicios.</p>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <?php foreach ($all_services as $s): ?>
                                <label class="flex items-center gap-3 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-100 transition-all select-none">
                                    <input type="checkbox" name="services[]" value="<?php echo $s['id']; ?>" class="modal-service-checkbox w-4 h-4 rounded text-blue-600 border-slate-300 focus:ring-blue-500">
                                    <div class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($s['name']); ?></div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Condicional: Negocio y Tarjeta -->
                <div class="border-t border-slate-100 pt-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-bold text-slate-800">?Tiene Negocio?</h4>
                            <p class="text-xs text-slate-400 mt-0.5">Habilita esta opción para guardar sus datos fiscales/comerciales y de pago.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="has_business" id="modal_has_business" onchange="toggleModalCardSection()" class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#5c59f2]"></div>
                        </label>
                    </div>

                    <!-- Datos Tarjeta -->
                    <div id="modalCardSection" class="hidden bg-slate-50 p-6 rounded-[2rem] border border-slate-100 space-y-4 transition-all">
                        <h5 class="text-xs font-bold uppercase tracking-widest text-slate-400">Información de Facturación / Pago</h5>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-3">
                                <label for="modal_card_number" class="block text-[11px] font-bold text-slate-500 mb-1">Número de Tarjeta</label>
                                <input type="text" name="card_number" id="modal_card_number" placeholder="4111 2222 3333 4444" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                            </div>
                            <div>
                                <label for="modal_card_expiry" class="block text-[11px] font-bold text-slate-500 mb-1">Vencimiento (MM/AA)</label>
                                <input type="text" name="card_expiry" id="modal_card_expiry" placeholder="12/28" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                            </div>
                            <div>
                                <label for="modal_card_cvv" class="block text-[11px] font-bold text-slate-500 mb-1">CVV</label>
                                <input type="text" name="card_cvv" id="modal_card_cvv" placeholder="123" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-blue-500 text-slate-900 transition-all">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="flex-1 bg-[#5c59f2] text-white py-4 rounded-2xl font-bold hover:bg-[#4d4ae0] transition-all">Guardar</button>
                    <button type="button" onclick="closeModal()" class="flex-1 bg-slate-100 text-slate-500 py-4 rounded-2xl font-bold hover:bg-slate-200 transition-all">Cerrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Eliminar -->
    <div id="deleteModal" class="fixed inset-0 z-[120] hidden items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
        <div class="bg-white w-full max-w-sm rounded-[2.5rem] p-10 text-center shadow-2xl">
            <h2 class="text-2xl font-bold mb-4">?Eliminar Cliente?</h2>
            <div class="flex flex-col gap-3">
                <button id="confirmDeleteBtn" class="w-full py-4 bg-red-600 text-white font-bold rounded-2xl hover:bg-red-700 transition-all">S?, eliminar</button>
                <button onclick="closeDeleteModal()" class="w-full py-4 bg-slate-100 text-slate-500 font-bold rounded-2xl hover:bg-slate-200 transition-all">Cancelar</button>
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
                    <h3 class="text-xl font-black">Importar Clientes</h3>
                    <p class="text-slate-400 text-xs font-semibold">Sube un archivo CSV con columnas: Nombre, Email, WhatsApp, Idioma, Dirección, Ciudad, Estado, Código Postal</p>
                </div>
            </div>
            
            <div id="importDropZone" class="border-2 border-dashed border-slate-200 rounded-[2rem] p-10 text-center hover:border-indigo-400 transition-all cursor-pointer group mb-6 bg-slate-50/50">
                <input type="file" id="importFile" class="hidden" accept=".csv,.txt">
                <div onclick="document.getElementById('importFile').click()">
                    <i data-lucide="file-up" class="w-10 h-10 mx-auto mb-4 text-slate-300 group-hover:text-[#5c59f2] transition-colors"></i>
                    <p class="text-sm font-bold text-slate-600">Haz clic para seleccionar archivo</p>
                    <p class="text-[10px] text-slate-400 mt-1 uppercase tracking-widest font-bold">Formato CSV o TXT</p>
                </div>
            </div>

            <div class="flex gap-4">
                <button type="button" onclick="closeImportModal()" class="flex-1 bg-slate-100 text-slate-500 py-4 rounded-2xl font-bold hover:bg-slate-200 transition-all">Cancelar</button>
            </div>
        </div>
    </div>

    <!-- Modal Personalizado (Notification/Glow Halo) -->
    <div id="customModal" class="fixed inset-0 z-[200] hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md">
        <div id="modal-container" class="bg-white w-full max-w-md rounded-[32px] p-8 md:p-10 text-center shadow-[0_25px_60px_-15px_rgba(0,0,0,0.5)] border border-slate-100 animate-fadeIn relative">
            <div class="relative mb-6 flex justify-center">
                <div id="customModalHalo" class="absolute inset-0 bg-indigo-100 rounded-full blur-xl opacity-70 animate-pulse-ring hidden"></div>
                <div id="customModalIcon" class="relative w-16 h-16 bg-blue-50 border border-blue-100 text-blue-600 rounded-2xl flex items-center justify-center shadow-inner animate-pulse-ring">
                    <i data-lucide="info" class="w-8 h-8"></i>
                </div>
            </div>

            <div class="space-y-3 mb-8">
                <h3 id="customModalTitle" class="text-2xl font-extrabold text-slate-900 tracking-tight">Título</h3>
                <div id="customModalMessage" class="text-[15px] text-slate-500 leading-relaxed px-2">Mensaje.</div>
            </div>
            
            <div id="customModalActions" class="w-full space-y-3"></div>

            <div id="processing-container" class="w-full hidden space-y-4 mt-6">
                <div class="flex justify-between items-center text-xs font-bold text-slate-400 px-1">
                    <span id="progress-label">Procesando base de datos...</span>
                    <span id="progress-percent">0%</span>
                </div>
                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                    <div id="progress-bar" class="bg-[#5c59f2] h-full w-0 transition-all duration-300 rounded-full shadow-[0_0_10px_rgba(92,89,242,0.4)]"></div>
                </div>
            </div>

            <div id="success-container" class="w-full hidden space-y-4 mt-6">
                <div class="bg-emerald-50 text-emerald-700 p-4 rounded-2xl border border-emerald-100 text-sm font-semibold flex items-center justify-center space-x-2">
                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                    <span>?Operación completada con éxito!</span>
                </div>
                <button onclick="closeAllModals()" class="text-xs text-indigo-600 hover:text-indigo-800 font-bold underline transition-colors">
                    Cerrar ventana
                </button>
            </div>
        </div>
    </div>

    <script>
        let allClients = [];
        let filteredClients = [];
        let selectedClients = new Set();
        let clientToDelete = null;
        let isBulkDelete = false;

        function toggleMenu() { document.getElementById('mobileMenu').classList.toggle('hidden'); }
        async function logout() { fetch('api/auth.php?action=logout').then(() => window.location.href = 'login.php'); }

        if (typeof PhonePicker !== 'undefined') {
            PhonePicker.render('client-phone-picker', 'whatsapp', { theme: 'crm', placeholder: 'Número local' });
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

        function openModal(client = null) {
            document.getElementById('modalTitle').innerText = client ? 'Editar Cliente' : 'Nuevo Cliente';
            document.getElementById('clientForm').reset();
            
            if (client) {
                document.getElementById('edit-id').value = client.id;
                document.getElementById('modal_name').value = client.name;
                document.getElementById('modal_email').value = client.email;
                document.getElementById('modal_language').value = client.language || 'es';
                document.getElementById('modal_address').value = client.address || '';
                document.getElementById('modal_city').value = client.city || '';
                document.getElementById('modal_state').value = client.state || '';
                document.getElementById('modal_zip_code').value = client.zip_code || '';
                document.getElementById('modal_status').value = client.status || 'cliente_activo';
                
                const hasBiz = parseInt(client.has_business) === 1;
                document.getElementById('modal_has_business').checked = hasBiz;
                document.getElementById('modal_card_number').value = client.card_number || '';
                document.getElementById('modal_card_expiry').value = client.card_expiry || '';
                document.getElementById('modal_card_cvv').value = client.card_cvv || '';
                
                // Phone Picker
                const pickerEl = document.getElementById('client-phone-picker');
                if (pickerEl && typeof pickerEl._pickerSetValue === 'function') {
                    pickerEl._pickerSetValue(client.whatsapp);
                }

                // Checkboxes
                const chks = document.querySelectorAll('.modal-service-checkbox');
                chks.forEach(chk => {
                    const val = parseInt(chk.value);
                    const hasSrv = client.services && client.services.some(s => s.id === val);
                    chk.checked = hasSrv;
                });
            } else {
                document.getElementById('edit-id').value = '';
                // Phone Picker clear
                const pickerEl = document.getElementById('client-phone-picker');
                if (pickerEl && typeof pickerEl._pickerSetValue === 'function') {
                    pickerEl._pickerSetValue('');
                }
                const chks = document.querySelectorAll('.modal-service-checkbox');
                chks.forEach(chk => chk.checked = false);
            }

            toggleModalCardSection();
            document.getElementById('clientModal').classList.remove('hidden');
            document.getElementById('clientModal').classList.add('flex');
        }

        function closeModal() { document.getElementById('clientModal').classList.add('hidden'); }
        function closeDeleteModal() { document.getElementById('deleteModal').classList.add('hidden'); }
        
        function closeAllModals() {
            document.getElementById('customModal').classList.add('hidden');
            document.getElementById('importModal').classList.add('hidden');
            document.getElementById('clientModal').classList.add('hidden');
            document.getElementById('deleteModal').classList.add('hidden');
        }

        function openImportModal() { 
            document.getElementById('importModal').classList.remove('hidden'); 
            document.getElementById('importModal').classList.add('flex'); 
            lucide.createIcons();
        }
        function closeImportModal() { document.getElementById('importModal').classList.add('hidden'); }

        async function fetchClients() {
            try {
                const res = await fetch('api/clients.php');
                allClients = await res.json();
                filterClients();
            } catch (err) { console.error('Error fetching clients:', err); }
        }

        function filterClients() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const status = document.getElementById('statusFilter').value;
            const service = document.getElementById('serviceFilter').value;
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;

            filteredClients = allClients.filter(c => {
                const matchesSearch = !search || (
                    (c.name || '').toLowerCase().includes(search) ||
                    (c.email || '').toLowerCase().includes(search) ||
                    (c.whatsapp || '').toLowerCase().includes(search) ||
                    (c.city || '').toLowerCase().includes(search) ||
                    (c.state || '').toLowerCase().includes(search) ||
                    (c.services || []).some(s => (s.name || '').toLowerCase().includes(search))
                );
                
                const matchesStatus = status === 'all' || c.status === status;
                
                const matchesService = service === 'all' || (c.services && c.services.some(s => s.id == service));
                
                const date = c.created_at ? c.created_at.split(' ')[0] : '';
                const matchesFrom = !from || date >= from;
                const matchesTo = !to || date <= to;
                
                return matchesSearch && matchesStatus && matchesService && matchesFrom && matchesTo;
            });

            renderClients(filteredClients);
        }

        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = 'all';
            document.getElementById('serviceFilter').value = 'all';
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            filterClients();
        }

        function renderClients(data) {
            const list = document.getElementById('clients-list');
            const countBadge = document.getElementById('countBadge');
            const emptyState = document.getElementById('emptyState');

            countBadge.textContent = data.length;
            
            if (data.length === 0) {
                list.innerHTML = '';
                emptyState.classList.remove('hidden');
                return;
            }

            emptyState.classList.add('hidden');
            list.innerHTML = data.map(c => {
                const date = c.created_at ? c.created_at.split(' ')[0] : '?';
                const isChecked = selectedClients.has(c.id.toString());
                const servicesText = c.services && c.services.length > 0
                    ? c.services.map(s => `<span class="bg-blue-50 text-blue-600 px-2 py-0.5 rounded-lg border border-blue-100 text-[10px] font-bold">${s.name}</span>`).join(' ')
                    : '<span class="text-slate-400 italic text-[10px]">Sin servicios</span>';
                
                const isAct = c.status === 'cliente_activo';
                const badgeClass = isAct ? 'status-active' : 'status-inactive';
                const statusName = isAct ? 'Activo' : 'Inactivo';
                const hasBiz = parseInt(c.has_business) === 1;

                return `
                <div class="list-row p-4 md:py-3 cursor-pointer group" onclick="if(!event.target.closest('button') && !event.target.closest('input')) window.location='prospecto.php?id=${c.id}'">
                    <div class="flex justify-center">
                        <input type="checkbox" value="${c.id}" ${isChecked ? 'checked' : ''} onchange="toggleClientSelection('${c.id}')" class="client-checkbox w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                    </div>
                    
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-black shrink-0 shadow-inner">
                            ${c.name.substr(0, 2).toUpperCase()}
                        </div>
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                <span class="text-truncate">${c.name}</span>
                                ${hasBiz ? '<span class="bg-indigo-50 text-indigo-600 text-[8px] font-bold px-1.5 py-0.5 rounded-full border border-indigo-100 uppercase tracking-wide">Biz</span>' : ''}
                            </div>
                            <div class="text-[10px] text-slate-400 text-truncate">${c.email}</div>
                        </div>
                    </div>

                    <div class="hidden md:flex flex-wrap items-center gap-1">
                        ${servicesText}
                    </div>

                    <div class="hidden md:flex flex-col justify-center text-[10px] font-medium text-slate-500">
                        <span class="font-bold uppercase tracking-wider text-[9px] text-slate-400">${c.language ? c.language.toUpperCase() : 'ES'}</span>
                        <span class="text-slate-600 text-truncate">${c.city || 'No especificada'}</span>
                    </div>

                    <div class="hidden md:flex items-center justify-start">
                        <span class="text-[10px] font-medium text-slate-600 flex items-center gap-2">
                            <i data-lucide="phone" class="w-3 h-3 text-slate-400"></i>
                            ${c.whatsapp}
                        </span>
                    </div>

                    <div class="hidden md:flex items-center justify-start gap-2">
                        <span id="badge-${c.id}" class="inline-block text-[9px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider ${badgeClass}">
                            ${statusName}
                        </span>
                        <!-- Switch interactivo -->
                        <button onclick="event.stopPropagation(); toggleClientStatus(${c.id}, '${c.status}')" class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none ${isAct ? 'bg-emerald-500' : 'bg-slate-200'}">
                            <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${isAct ? 'translate-x-4' : 'translate-x-0'}"></span>
                        </button>
                    </div>

                    <div class="flex items-center justify-end gap-1.5">
                        <button onclick="event.stopPropagation(); openModal(${JSON.stringify(c).replace(/"/g, '&quot;')})" class="w-8 h-8 bg-slate-50 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg flex items-center justify-center transition-all" title="Editar">
                            <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                        </button>
                        <button onclick="event.stopPropagation(); askDelete(${c.id})" class="w-8 h-8 bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white rounded-lg flex items-center justify-center transition-all" title="Eliminar">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </div>`;
            }).join('');
            lucide.createIcons();
            updateBulkUI();
        }

        function toggleClientSelection(id) {
            if (selectedClients.has(id)) selectedClients.delete(id);
            else selectedClients.add(id);
            updateBulkUI();
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            if (selectAll.checked) {
                filteredClients.forEach(c => selectedClients.add(c.id.toString()));
            } else {
                selectedClients.clear();
            }
            renderClients(filteredClients);
        }

        function updateBulkUI() {
            const btn = document.getElementById('bulkDeleteBtn');
            const count = document.getElementById('selectedCount');
            if (selectedClients.size > 0) {
                btn.classList.remove('hidden');
                btn.style.display = 'flex';
                count.textContent = selectedClients.size;
            } else {
                btn.classList.add('hidden');
                btn.style.display = 'none';
                const selectAll = document.getElementById('selectAll');
                if (selectAll) selectAll.checked = false;
            }
        }

        function askDelete(id) { 
            clientToDelete = id; 
            isBulkDelete = false;
            document.getElementById('deleteModal').classList.remove('hidden'); 
            document.getElementById('deleteModal').classList.add('flex'); 
        }

        function askBulkDelete() {
            if (selectedClients.size === 0) return;
            isBulkDelete = true;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
        }

        document.getElementById('confirmDeleteBtn').onclick = async () => {
            const body = isBulkDelete 
                ? { action: 'delete', ids: Array.from(selectedClients) }
                : { action: 'delete', id: clientToDelete };

            await fetch('api/prospects.php', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body) 
            });
            
            if (isBulkDelete) {
                selectedClients.clear();
            } else if (clientToDelete) {
                selectedClients.delete(clientToDelete.toString());
            }
            
            const selectAllCb = document.getElementById('selectAll');
            if (selectAllCb) selectAllCb.checked = false;
            updateBulkUI();
            
            closeDeleteModal(); 
            fetchClients();
        };

        // SAVE CLIENT
        async function saveClient(e) {
            e.preventDefault();
            const id = document.getElementById('edit-id').value;
            const name = document.getElementById('modal_name').value;
            const email = document.getElementById('modal_email').value;
            const whatsapp = document.getElementById('modal_whatsapp') ? document.getElementById('modal_whatsapp').value : '';
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

            // Services checkboxes
            const serviceCheckboxes = document.querySelectorAll('.modal-service-checkbox:checked');
            const services = Array.from(serviceCheckboxes).map(chk => parseInt(chk.value));

            const payload = {
                action: id ? 'update_client_info' : 'add_client',
                client_id: id ? parseInt(id) : null,
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
                services: services
            };

            const res = await fetch('api/clients.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                closeModal();
                fetchClients();
            } else {
                alert('Error al guardar el cliente: ' + (data.error || 'Desconocido'));
            }
        }

        // TOGGLE STATUS DIRECTLY IN SWITCH
        async function toggleClientStatus(clientId, currentStatus) {
            const newStatus = currentStatus === 'cliente_activo' ? 'cliente_inactivo' : 'cliente_activo';
            const res = await fetch('api/clients.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'toggle_status',
                    client_id: clientId,
                    status: newStatus
                })
            });
            const data = await res.json();
            if (data.success) {
                fetchClients();
            } else {
                alert('Error al actualizar estado: ' + (data.error || 'Desconocido'));
            }
        }

        // ── EXPORTAR ──
        function exportCSV() {
            if (filteredClients.length === 0) return alert('No hay datos para exportar');
            const headers = ['Nombre', 'Email', 'WhatsApp', 'Idioma', 'Dirección', 'Ciudad', 'Estado', 'Código Postal', 'Servicios', 'Estado Cliente', 'Fecha Ingreso'];
            const rows = filteredClients.map(c => {
                const srvStr = c.services ? c.services.map(s => s.name).join(' | ') : '';
                return [
                    `"${(c.name || '').replace(/"/g, '""')}"`,
                    `"${(c.email || '').replace(/"/g, '""')}"`,
                    `"${(c.whatsapp || '').replace(/"/g, '""')}"`,
                    `"${(c.language || 'es').toUpperCase()}"`,
                    `"${(c.address || '').replace(/"/g, '""')}"`,
                    `"${(c.city || '').replace(/"/g, '""')}"`,
                    `"${(c.state || '').replace(/"/g, '""')}"`,
                    `"${(c.zip_code || '').replace(/"/g, '""')}"`,
                    `"${srvStr.replace(/"/g, '""')}"`,
                    `"${c.status === 'cliente_activo' ? 'Activo' : 'Inactivo'}"`,
                    `"${c.created_at || ''}"`
                ];
            });
            
            const csvContent = "\uFEFF" + [headers, ...rows].map(e => e.join(",")).join("\n");
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            const url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", "clientes_filtrados.csv");
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function exportPDF() {
            if (filteredClients.length === 0) return alert('No hay datos para exportar');
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            doc.setFontSize(18);
            doc.setTextColor(30, 58, 138); // Royal Blue
            doc.text("Reporte de Clientes", 14, 22);
            doc.setFontSize(10);
            doc.setTextColor(100);
            doc.text(`Generado el: ${new Date().toLocaleString()} | Total: ${filteredClients.length} registros`, 14, 30);

            const tableData = filteredClients.map(c => [
                c.name, 
                c.email, 
                c.whatsapp, 
                c.services ? c.services.map(s => s.name).join(', ') : '',
                c.status === 'cliente_activo' ? 'Activo' : 'Inactivo',
                c.city || ''
            ]);

            doc.autoTable({
                startY: 35,
                head: [['Nombre', 'Email', 'WhatsApp', 'Servicios', 'Estado', 'Ciudad']],
                body: tableData,
                theme: 'striped',
                headStyles: { fillColor: [30, 58, 138], textColor: 255, fontStyle: 'bold' },
                alternateRowStyles: { fillColor: [248, 250, 252] },
                margin: { top: 35 }
            });

            doc.save("clientes_filtrados.pdf");
        }

        // ── SISTEMA DE MODALES PERSONALIZADOS ──
        function showNotification({ title, message, type = 'info', confirmText = 'Aceptar', cancelText = null, onConfirm = null }) {
            const modal = document.getElementById('customModal');
            const halo = document.getElementById('customModalHalo');
            const iconBox = document.getElementById('customModalIcon');
            const titleEl = document.getElementById('customModalTitle');
            const msgEl = document.getElementById('customModalMessage');
            const actions = document.getElementById('customModalActions');
            const proc = document.getElementById('processing-container');
            const success = document.getElementById('success-container');

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            titleEl.innerHTML = title;
            msgEl.innerHTML = message;
            actions.innerHTML = '';
            actions.classList.remove('hidden');
            proc.classList.add('hidden');
            success.classList.add('hidden');
            halo.classList.add('hidden');

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

            const btnConfirm = document.createElement('button');
            btnConfirm.className = `w-full py-4 px-6 font-bold rounded-2xl transition-all shadow-lg text-sm tracking-wide active:scale-[0.98] ${type === 'import' ? 'bg-[#5c59f2] hover:bg-[#4d4ae0] text-white' : (type === 'confirm' ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'bg-slate-900 text-white hover:bg-slate-800')}`;
            btnConfirm.innerHTML = `<span>${confirmText}</span>`;
            btnConfirm.onclick = () => {
                if (type === 'import' && onConfirm) {
                    onConfirm();
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

        // ── IMPORTAR CLIENTES ──
        document.getElementById('importFile').onchange = function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = async function(event) {
                const text = event.target.result;
                const lines = text.split(/\r?\n/).filter(l => l.trim() !== '');
                if (lines.length === 0) return showNotification({ title: 'Error', message: 'El archivo est? vacío.', type: 'error' });

                // Delimitador
                const firstLine = lines[0];
                const commaCount = (firstLine.match(/,/g) || []).length;
                const semiCount = (firstLine.match(/;/g) || []).length;
                const delimiter = semiCount > commaCount ? ';' : ',';
                
                const clientsToImport = [];
                lines.forEach((line, index) => {
                    const parts = line.split(delimiter).map(s => s.replace(/^["']|["']$/g, '').trim());
                    
                    if (parts.length >= 3) {
                        // Ignorar cabecera
                        if (index === 0 && (parts[0].toLowerCase().includes('nombre') || parts[0].toLowerCase().includes('name'))) return;
                        
                        const c = {
                            name: parts[0],
                            email: parts[1],
                            whatsapp: parts[2],
                            language: parts[3] || 'es',
                            address: parts[4] || '',
                            city: parts[5] || '',
                            state: parts[6] || '',
                            zip_code: parts[7] || '',
                            status: 'cliente_activo'
                        };

                        if (c.name && c.whatsapp) {
                            clientsToImport.push(c);
                        }
                    }
                });

                if (clientsToImport.length === 0) {
                    return showNotification({ 
                        title: 'Sin datos', 
                        message: 'No se encontraron registros válidos en el archivo. Asegúrate de tener al menos las columnas: Nombre, Email, WhatsApp.', 
                        type: 'error' 
                    });
                }

                showNotification({
                    title: 'Importación de Clientes',
                    message: `Se detectaron <span class="font-bold text-indigo-600 bg-indigo-50 px-2.5 py-0.5 rounded-full">${clientsToImport.length} clientes</span> listos para importar.<br><p class="text-sm font-semibold text-slate-400 pt-2">?Deseas importarlos ahora?</p>`,
                    type: 'import',
                    confirmText: 'S?, importar ahora',
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

                        let progress = 0;
                        const interval = setInterval(() => {
                            if (progress < 90) {
                                progress += 10;
                                bar.style.width = `${progress}%`;
                                percent.innerText = `${progress}%`;
                                if (progress === 40) label.innerText = "Registrando clientes...";
                                else if (progress === 80) label.innerText = "Asociando datos...";
                            }
                        }, 80);

                        try {
                            const res = await fetch('api/clients.php', { 
                                method: 'POST', 
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ 
                                    action: 'bulk_create', 
                                    clients: clientsToImport 
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
                                    fetchClients();
                                }, 400);
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
            e.target.value = '';
        };

        fetchClients();
        lucide.createIcons();
    </script>
</body>
</html>
