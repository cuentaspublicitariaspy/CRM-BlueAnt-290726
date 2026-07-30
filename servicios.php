<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once 'api/config.php';
$user_id = (int)$_SESSION['user_id'];
$is_admin = ($_SESSION['user_role'] ?? 'subscriber') === 'admin';

// Obtener servicios del agente
$stmt = $pdo->prepare("SELECT * FROM services ORDER BY name ASC");
$stmt->execute();
$services = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servicios - Ultra CRM</title>
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

        /* ── CARDS ── */
        .service-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 1.5rem;
            padding: 1.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            transition: all 0.2s ease-in-out;
            display: flex;
            flex-direction: column;
        }
        .service-card:hover { transform: translateY(-4px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
        .card-icon { width:48px; height:48px; background:rgba(37, 99, 235, 0.1); border-radius:14px; display:flex; align-items:center; justify-content:center; color:#2563eb; font-size:1.25rem; flex-shrink:0; }
        
        .price-badge { background:#eff6ff; color:#1d4ed8; font-size:.85rem; font-weight:700; padding:6px 12px; border-radius:10px; }
        .card-shadow { box-shadow: 0 30px 70px -15px rgba(15, 23, 42, 0.08); }
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

                            <a href="servicios.php" class="nav-link active flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

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

                    <a href="clientes.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                        <i data-lucide="user-check" class="w-5 h-5"></i>Clientes</a>

                    <a href="servicios.php" class="nav-link active flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

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
        <header class="h-16 border-b border-slate-100 flex items-center justify-between px-6 lg:px-10 bg-white sticky top-0 z-40">
            <div class="flex items-center gap-4">
                <button onclick="toggleMenu()" class="lg:hidden p-2 text-slate-500"><i data-lucide="menu" class="w-6 h-6"></i></button>
                <h1 class="text-xl font-bold text-slate-900">Definición de Servicios</h1>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-xs font-medium text-slate-500 hidden sm:block">?Hola, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>
            </div>
        </header>

        <!-- Main Content -->
        <div class="p-6 lg:p-10 max-w-7xl mx-auto w-full space-y-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-3xl font-bold text-slate-900 tracking-tight">Catálogo de Servicios</h2>
                    <p class="text-slate-500 mt-1"><?php echo $is_admin ? 'Crea y define los servicios y tarifas que ofreces a tus clientes.' : 'Visualiza los servicios y tarifas asignados a tu cuenta.'; ?></p>
                </div>
                <?php if ($is_admin): ?>
                <button onclick="openModal()" class="inline-flex items-center justify-center gap-2 px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl font-bold shadow-lg shadow-blue-500/20 transition-all duration-200 shrink-0">
                    <i data-lucide="plus" class="w-5 h-5"></i>Nuevo Servicio
                </button>
                <?php endif; ?>
            </div>

            <!-- Grid de Servicios -->
            <?php if (empty($services)): ?>
                <div class="bg-slate-50 rounded-[2rem] p-12 text-center border-2 border-dashed border-slate-200">
                    <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="briefcase" class="w-8 h-8"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800">No hay servicios definidos</h3>
                    <p class="text-slate-500 mt-1 max-w-md mx-auto"><?php echo $is_admin ? 'Comienza agregando los servicios que ofreces (por ejemplo: Bookkeeping, Tax Planning, Tax Resolution) para poder asignarlos a tus prospectos.' : 'El administrador aún no ha definido ningún servicio para su cuenta.'; ?></p>
                    <?php if ($is_admin): ?>
                    <button onclick="openModal()" class="mt-6 inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold transition-all">
                        Crear mi primer servicio
                    </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($services as $s): ?>
                        <div class="service-card" id="service-card-<?php echo $s['id']; ?>">
                            <div class="flex items-start justify-between gap-4 mb-4">
                                <div class="card-icon">
                                    <i data-lucide="award" class="w-6 h-6"></i>
                                </div>
                                <div class="price-badge shrink-0">
                                    $<?php echo number_format($s['price'], 2); ?>
                                </div>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900 mb-2"><?php echo htmlspecialchars($s['name']); ?></h3>
                            <p class="text-slate-500 text-sm flex-1 leading-relaxed"><?php echo htmlspecialchars($s['description'] ?: 'Sin Descripción.'); ?></p>
                            
                            <?php if ($is_admin): ?>
                            <div class="flex items-center justify-end gap-2 mt-6 pt-4 border-t border-slate-100">
                                <button onclick="openModal(<?php echo htmlspecialchars(json_encode($s)); ?>)" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all" title="Editar">
                                    <i data-lucide="edit-3" class="w-4 h-4"></i>
                                </button>
                                <button onclick="deleteService(<?php echo $s['id']; ?>)" class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all" title="Eliminar">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Formulario -->
    <div id="serviceModal" class="modal-overlay">
        <div class="bg-white w-full max-w-lg rounded-[2.5rem] shadow-2xl p-8 md:p-10 relative space-y-6 max-h-[90vh] overflow-y-auto">
            <button onclick="closeModal()" class="absolute top-6 right-6 p-2 rounded-full hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-all">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>

            <div>
                <h3 id="modalTitle" class="text-2xl font-bold text-slate-900">Nuevo Servicio</h3>
                <p class="text-sm text-slate-500 mt-1">Completa los datos del servicio ofrecido.</p>
            </div>

            <form id="serviceForm" onsubmit="saveService(event)" class="space-y-5">
                <input type="hidden" name="id" id="serviceId">
                
                <div>
                    <label for="name" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Nombre del Servicio</label>
                    <input type="text" name="name" id="name" required placeholder="Ej. Tax Planning, Bookkeeping..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 focus:bg-white text-slate-900 placeholder-slate-400 transition-all">
                </div>

                <div>
                    <label for="description" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Descripción</label>
                    <textarea name="description" id="description" rows="3" placeholder="Describe brevemente en qu? consiste el servicio..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 focus:bg-white text-slate-900 placeholder-slate-400 transition-all"></textarea>
                </div>

                <div>
                    <label for="price" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Precio Sugerido ($)</label>
                    <input type="number" step="0.01" name="price" id="price" required placeholder="0.00" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 focus:bg-white text-slate-900 placeholder-slate-400 transition-all">
                </div>

                <div class="flex items-center justify-end gap-3 pt-4">
                    <button type="button" onclick="closeModal()" class="px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-2xl font-bold transition-all">
                        Cancelar
                    </button>
                    <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl font-bold shadow-lg shadow-blue-500/25 transition-all">
                        Guardar Servicio
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function toggleMenu() {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('hidden');
        }

        function logout() {
            if (confirm('?Seguro que quieres cerrar sesión?')) {
                window.location.href = 'api/logout.php';
            }
        }

        // MODAL ACTIONS
        const modal = document.getElementById('serviceModal');
        const serviceForm = document.getElementById('serviceForm');

        function openModal(service = null) {
            if (service) {
                document.getElementById('modalTitle').textContent = 'Editar Servicio';
                document.getElementById('serviceId').value = service.id;
                document.getElementById('name').value = service.name;
                document.getElementById('description').value = service.description;
                document.getElementById('price').value = service.price;
            } else {
                document.getElementById('modalTitle').textContent = 'Nuevo Servicio';
                serviceForm.reset();
                document.getElementById('serviceId').value = '';
            }
            modal.classList.add('open');
        }

        function closeModal() {
            modal.classList.remove('open');
        }

        // SAVE SERVICE
        function saveService(e) {
            e.preventDefault();
            
            const id = document.getElementById('serviceId').value;
            const name = document.getElementById('name').value;
            const description = document.getElementById('description').value;
            const price = document.getElementById('price').value;

            const payload = {
                id: id ? parseInt(id) : null,
                name: name,
                description: description,
                price: parseFloat(price)
            };

            fetch('api/services.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error al guardar el servicio: ' + (data.error || 'Desconocido'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('Ocurri? un error al enviar la solicitud.');
            });
        }

        // DELETE SERVICE
        async function deleteService(id) {
            const ok = await showConfirm('?Eliminar Servicio?', 'Este servicio se eliminara permanentemente junto con todas sus asociaciones con clientes.');
            if (!ok) return;

            fetch('api/services.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete',
                    id: id
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const card = document.getElementById('service-card-' + id);
                    if (card) {
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.9)';
                        setTimeout(() => {
                            window.location.reload();
                        }, 250);
                    } else {
                        window.location.reload();
                    }
                } else {
                    alert('Error al eliminar el servicio: ' + (data.error || 'Desconocido'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('Ocurri? un error al eliminar el servicio.');
            });
        }
    </script>

<!-- === CONFIRM MODAL === -->
<div id="confirmModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-[60] hidden p-4">
  <div class="bg-white rounded-[2rem] w-full max-w-sm p-8 card-shadow relative overflow-hidden">
    <div class="flex flex-col items-center text-center">
      <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-rose-500 to-red-500 flex items-center justify-center shadow-lg mb-5">
        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
        </svg>
      </div>
      <h3 id="confirmTitle" class="text-xl font-extrabold text-slate-900 mb-2">?Eliminar?</h3>
      <p id="confirmMessage" class="text-sm text-slate-500 mb-6">Esta accion no se puede deshacer.</p>
      <div class="flex gap-3 w-full">
        <button onclick="closeConfirmModal()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-3 px-5 rounded-xl transition-all">Cancelar</button>
        <button id="confirmDeleteBtn" onclick="executeConfirm()" class="flex-1 bg-gradient-to-tr from-rose-500 to-red-500 hover:from-rose-600 hover:to-red-600 text-white font-bold py-3 px-5 rounded-xl shadow-lg transition-all active:scale-[0.97]">Eliminar</button>
      </div>
    </div>
  </div>
</div>

<script>
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
</script>
</body>
</html>
