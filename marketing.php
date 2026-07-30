<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once 'api/config.php';

$is_admin = ($_SESSION['user_role'] ?? 'subscriber') === 'admin';
$user_id = $_SESSION['user_id'];

// Obtener datos
if ($is_admin) {
    $stmt = $pdo->query("SELECT * FROM marketing_templates ORDER BY id DESC");
    $templates = $stmt->fetchAll();
    
    // Fetch all landings for admin so they can use the generator tool
    $stmt = $pdo->query("SELECT id, title FROM landings ORDER BY title ASC");
    $landings = $stmt->fetchAll();
} else {
    $stmt = $pdo->query("SELECT * FROM marketing_templates ORDER BY name ASC");
    $templates = $stmt->fetchAll();
    
    // FETCH LANDINGS SUBSCRIBED TO THE USER
    $stmt = $pdo->prepare("SELECT l.id, l.title FROM landings l INNER JOIN landing_subscriptions ls ON l.id = ls.landing_id WHERE ls.user_id = ?");
    $stmt->execute([$user_id]);
    $landings = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Material de Marketing - Ultra CRM</title>
    <?php if(isset($crm_favicon) && $crm_favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($crm_favicon); ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ultrablue: #0d1e56;
            --sidebar-bg: #0d1e56;
            --card-bg: #1e293b;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; color: #0f172a; }
        .glass-card { background: white; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .sidebar-desktop { background: var(--sidebar-bg) !important; }
        .sidebar-desktop .nav-link { color: #94a3b8; }
        .sidebar-desktop .nav-link:hover { background: rgba(255,255,255,.05) !important; color: white !important; }
        .sidebar-desktop .nav-link.active { background: #2563eb !important; color: white !important; box-shadow: 0 4px 15px rgba(37,99,235,.3); }
        .sidebar-desktop .logout-btn { color: #94a3b8 !important; }
    </style>
</head>
<body class="flex min-h-screen">

    <!-- Mobile Menu Overlay -->
    <div id="mobileMenu" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[100] hidden lg:hidden">
        <div class="w-72 h-full sidebar-desktop p-8 flex flex-col">
            <div class="flex items-center justify-between mb-10">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-lg overflow-hidden shrink-0 bg-white text-indigo-900 shadow">
                        <?php echo $crm_logo ? '<img src="'.htmlspecialchars($crm_logo).'" class="w-full h-full object-cover">' : htmlspecialchars(substr($crm_name, 0, 1)); ?>
                    </div>
                    <span class="font-bold text-xl tracking-tight leading-tight text-white"><?php echo htmlspecialchars($crm_name); ?></span>
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

                            <a href="marketing.php" class="nav-link active flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

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
            <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-lg shadow overflow-hidden shrink-0 bg-white text-indigo-900">
                <?php echo $crm_logo ? '<img src="'.htmlspecialchars($crm_logo).'" class="w-full h-full object-cover">' : htmlspecialchars(substr($crm_name, 0, 1)); ?>
            </div>
            <span class="font-bold text-xl tracking-tight leading-tight text-white"><?php echo htmlspecialchars($crm_name); ?></span>
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

                    <a href="marketing.php" class="nav-link active flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

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

    <main class="flex-1 flex flex-col min-w-0 bg-slate-50/50">
        <header class="h-16 border-b border-slate-200 flex items-center px-6 lg:px-10 bg-white sticky top-0 z-40">
            <button onclick="toggleMenu()" class="lg:hidden p-2 text-slate-500 mr-3"><i data-lucide="menu" class="w-6 h-6"></i></button>
            <h1 class="text-xl font-bold text-slate-900">Material de Marketing</h1>
        </header>

        <div class="p-6 lg:p-10 max-w-7xl mx-auto w-full space-y-8">

            <?php if ($is_admin): ?>
            <!-- ADMIN VIEW -->
            <div class="glass-card rounded-[2rem] p-8">
                <h2 class="text-xl font-bold mb-6 flex items-center gap-2 text-slate-800">
                    <i data-lucide="plus-circle" class="w-5 h-5 text-indigo-600"></i> Nueva Plantilla Interactiva
                </h2>
                
                <form id="templateForm" onsubmit="saveTemplate(event)" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Nombre del Asset</label>
                            <input type="text" name="name" required placeholder="Ej: Volante Promocional" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 outline-none focus:border-indigo-500 transition-colors">
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Formato Salida Final</label>
                            <select name="output_format" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 outline-none">
                                <option value="jpg">Imagen (JPG) - Ideal para Whatsapp</option>
                                <option value="pdf">Documento (PDF) - Ideal para Imprimir</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wide text-indigo-600 flex items-center gap-2">
                            <i data-lucide="upload-cloud" class="w-4 h-4"></i> Sube el diseño base (se abrirá el editor visual)
                        </label>
                        <input type="file" name="base_image" accept="image/png, image/jpeg, application/pdf" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 outline-none" onchange="previewImage(event)">
                    </div>

                    <!-- Interactive Image/PDF Preview Area -->
                    <div id="previewContainer" class="hidden bg-slate-100 border-2 border-dashed border-indigo-300 rounded-2xl p-4 overflow-hidden flex justify-center items-center shadow-inner group">
                        <!-- Wrapper ajustado exactamente al tamaño de la imagen/PDF -->
                        <div id="imageWrapper" class="relative inline-block" style="max-width: 100%;">
                            <img id="imagePreview" class="block max-h-[600px] max-w-full object-contain pointer-events-none select-none" />
                            <canvas id="pdfCanvas" class="hidden block max-h-[600px] max-w-full object-contain pointer-events-none select-none"></canvas>
                            
                            <div id="qrBox" class="absolute border-2 border-indigo-500 bg-indigo-500/40 cursor-move shadow-[0_0_15px_rgba(99,102,241,0.5)] flex items-center justify-center backdrop-blur-[1px]" style="width: 100px; height: 100px; top: 0; left: 0;">
                                <i data-lucide="qr-code" class="w-8 h-8 text-white/80 pointer-events-none"></i>
                                <div class="absolute w-4 h-4 bg-white border-2 border-indigo-600 -bottom-2 -right-2 cursor-se-resize rounded-full shadow" id="resizeHandle"></div>
                            </div>
                            
                            <div class="absolute top-4 right-4 bg-black/60 text-white text-[10px] font-bold uppercase px-3 py-1 rounded-full backdrop-blur pointer-events-none">
                                Arrastra y ajusta el tamaño
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Pos X Final (px)</label>
                            <input type="number" id="qr_x" name="qr_x" required readonly class="w-full bg-slate-100 text-slate-500 border border-slate-200 rounded-xl p-3 outline-none cursor-not-allowed font-mono text-sm">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Pos Y Final (px)</label>
                            <input type="number" id="qr_y" name="qr_y" required readonly class="w-full bg-slate-100 text-slate-500 border border-slate-200 rounded-xl p-3 outline-none cursor-not-allowed font-mono text-sm">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Tamaño QR (px)</label>
                            <input type="number" id="qr_size" name="qr_size" required readonly class="w-full bg-slate-100 text-slate-500 border border-slate-200 rounded-xl p-3 outline-none cursor-not-allowed font-mono text-sm">
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="w-full md:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-xl transition-all shadow-lg shadow-indigo-200">
                            Guardar Plantilla
                        </button>
                    </div>
                </form>
            </div>

            <h3 class="text-lg font-bold text-slate-800 mb-4">Plantillas Guardadas (Gestión)</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach($templates as $tpl): ?>
                <?php $is_pdf_base = strtolower(pathinfo($tpl['base_image_path'], PATHINFO_EXTENSION)) === 'pdf'; ?>
                <div class="glass-card rounded-[1.5rem] overflow-hidden flex flex-col group">
                    <div class="h-40 bg-slate-100 relative overflow-hidden flex items-center justify-center cursor-pointer" onclick="openPreviewModal(<?= $tpl['id'] ?>, '<?= htmlspecialchars(addslashes($tpl['name'])) ?>', <?= $is_pdf_base ? 'true' : 'false' ?>)">
                        <?php if ($is_pdf_base): ?>
                            <div class="w-full h-full bg-slate-100 flex flex-col items-center justify-center gap-2 select-none">
                                <i data-lucide="file-text" class="w-12 h-12 text-indigo-500"></i>
                                <span class="text-xs font-semibold text-slate-500">Plantilla PDF</span>
                            </div>
                        <?php else: ?>
                            <img src="api/ver_asset.php?id=<?= $tpl['id'] ?>" onerror="this.onerror=null; this.src='https://placehold.co/600x400?text=No+Disponible';" class="w-full h-full object-cover opacity-80 group-hover:scale-105 transition-transform duration-500">
                        <?php endif; ?>
                    </div>
                    <div class="p-6">
                        <h3 class="font-bold text-slate-800 text-lg mb-1"><?= htmlspecialchars($tpl['name']) ?></h3>
                        <p class="text-[10px] uppercase font-bold text-slate-400 mb-4 flex items-center gap-1 tracking-wider">
                            <i data-lucide="crosshair" class="w-3 h-3"></i> <?= $tpl['qr_x'] ?>x, <?= $tpl['qr_y'] ?>y (<?= $tpl['qr_size'] ?>px) &bull; <?= strtoupper($tpl['output_format']) ?>
                        </p>
                        <button onclick="deleteTemplate(<?= $tpl['id'] ?>)" class="text-red-500 text-sm font-semibold hover:text-red-700 transition-colors">Eliminar del sistema</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php endif; ?>

            <!-- GENERATOR VIEW (BOTH ADMIN & SUBSCRIBER) -->
            <div class="glass-card rounded-[2rem] p-8 mb-8 border-l-4 border-indigo-500">
                <h2 class="text-xl font-bold mb-2 text-slate-800">Generador de Material Físico</h2>
                <p class="text-slate-500 text-sm mb-6">Selecciona una de tus landing pages activas. El sistema unir? tu landing a un código QR dinámico y lo estampar? en los volantes profesionales listos para enviar a la imprenta.</p>
                
                <div class="max-w-md space-y-2">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest text-indigo-600">Paso 1: Elige qu? promocionar</label>
                    <select id="landing_select" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 outline-none focus:border-indigo-500 font-medium text-slate-800 shadow-sm">
                        <?php if(empty($landings)): ?>
                            <option value="">No tienes suscripciones de landings activas aún</option>
                        <?php else: ?>
                            <?php foreach($landings as $l): ?>
                                <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['title']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <h3 class="text-lg font-bold text-slate-800 mb-4">Paso 2: Elige el diseño y descarga</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach($templates as $tpl): ?>
                <?php $is_pdf_base = strtolower(pathinfo($tpl['base_image_path'], PATHINFO_EXTENSION)) === 'pdf'; ?>
                <div class="glass-card rounded-[1.5rem] overflow-hidden flex flex-col hover:shadow-xl transition-all border-transparent hover:border-indigo-100 cursor-pointer group" onclick="openPreviewModal(<?= $tpl['id'] ?>, '<?= htmlspecialchars(addslashes($tpl['name'])) ?>', <?= $is_pdf_base ? 'true' : 'false' ?>)">
                    <div class="h-64 bg-slate-100 relative overflow-hidden">
                        <?php if ($is_pdf_base): ?>
                            <div class="w-full h-full bg-slate-100 flex flex-col items-center justify-center gap-2 select-none">
                                <i data-lucide="file-text" class="w-16 h-16 text-indigo-500"></i>
                                <span class="text-xs font-semibold text-slate-500">Plantilla PDF</span>
                            </div>
                        <?php else: ?>
                            <img src="api/ver_asset.php?id=<?= $tpl['id'] ?>" onerror="this.onerror=null; this.src='https://placehold.co/600x400?text=No+Disponible';" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 via-slate-900/10 to-transparent opacity-80"></div>
                        <div class="absolute bottom-4 left-4 text-white">
                            <span class="bg-indigo-600 text-[10px] font-black px-2 py-1 rounded-md uppercase tracking-widest shadow-lg inline-block"><?= strtoupper($tpl['output_format']) ?> LISTO</span>
                        </div>
                    </div>
                    <div class="p-6 flex items-center justify-between bg-white relative">
                        <h3 class="font-bold text-slate-800 text-lg"><?= htmlspecialchars($tpl['name']) ?></h3>
                        <div class="w-12 h-12 bg-indigo-50 rounded-full flex items-center justify-center text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition-colors absolute -top-6 right-6 shadow-xl border-4 border-white">
                            <i data-lucide="download" class="w-5 h-5"></i>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
        
        <!-- Modal Preview Global -->
        <div id="previewModal" class="fixed inset-0 z-[100] hidden">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm transition-opacity" onclick="closePreviewModal()"></div>
            <!-- Modal Content -->
            <div class="absolute inset-0 flex items-center justify-center p-4 sm:p-6 pointer-events-none">
                <div class="bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col pointer-events-auto transform transition-all scale-95 opacity-0 duration-200" id="previewModalContent">
                    
                    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="text-xl font-bold text-slate-800" id="previewModalTitle">Vista Previa</h3>
                        <button onclick="closePreviewModal()" class="w-10 h-10 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-full flex items-center justify-center transition-colors">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>

                    <div class="flex-1 overflow-auto p-6 bg-slate-50 flex items-center justify-center relative">
                        <img id="previewModalImage" src="" class="max-h-[60vh] max-w-full rounded-xl shadow-md object-contain">
                        <canvas id="previewModalCanvas" class="hidden max-h-[60vh] max-w-full rounded-xl shadow-md object-contain"></canvas>
                    </div>

                    <div class="p-6 border-t border-slate-100 bg-white rounded-b-3xl flex justify-end gap-4">
                        <button onclick="closePreviewModal()" class="px-6 py-3 font-semibold text-slate-600 hover:text-slate-800 transition-colors">
                            Cerrar
                        </button>
                        <button id="previewModalDownloadBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-xl transition-all shadow-lg shadow-indigo-200 flex items-center gap-2">
                            <i data-lucide="download" class="w-5 h-5"></i> Generar y Descargar
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <script>
        lucide.createIcons();
        function toggleMenu() { document.getElementById('mobileMenu').classList.toggle('hidden'); }
        async function logout() { fetch('api/auth.php?action=logout').then(() => window.location.href = 'login.php'); }

        // --- FUNCIONES GLOBALES DEL MODAL ---
        let currentPreviewId = null;

        function openPreviewModal(templateId, title, isPdf) {
            currentPreviewId = templateId;
            document.getElementById('previewModalTitle').innerText = title;
            
            const modal = document.getElementById('previewModal');
            const content = document.getElementById('previewModalContent');
            const imgPreviewModal = document.getElementById('previewModalImage');
            const canvasPreviewModal = document.getElementById('previewModalCanvas');
            
            if (isPdf) {
                imgPreviewModal.classList.add('hidden');
                canvasPreviewModal.classList.remove('hidden');
                
                // Initialize pdf.js rendering for preview
                pdfjsLib.getDocument('api/ver_asset.php?id=' + templateId).promise.then(async function(pdf) {
                    const page = await pdf.getPage(1);
                    const viewport = page.getViewport({ scale: 1.2 });
                    const context = canvasPreviewModal.getContext('2d');
                    canvasPreviewModal.width = viewport.width;
                    canvasPreviewModal.height = viewport.height;
                    
                    const renderContext = {
                        canvasContext: context,
                        viewport: viewport
                    };
                    await page.render(renderContext).promise;
                }).catch(function(err) {
                    console.error('Error rendering preview PDF:', err);
                });
            } else {
                canvasPreviewModal.classList.add('hidden');
                imgPreviewModal.classList.remove('hidden');
                imgPreviewModal.src = 'api/ver_asset.php?id=' + templateId;
            }
            
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            }, 10);

            const downloadBtn = document.getElementById('previewModalDownloadBtn');
            if (downloadBtn) {
                downloadBtn.onclick = function() {
                    const landingId = document.getElementById('landing_select').value;
                    if(!landingId) {
                        alert('Por favor, selecciona una landing promocional en el paso 1.');
                        closePreviewModal();
                        return;
                    }
                    const userId = <?= json_encode($user_id) ?>;
                    window.location.href = `api/descargar_asset.php?d_id=${userId}&l_id=${landingId}&t_id=${templateId}`;
                    closePreviewModal();
                };
            }
        }

        function closePreviewModal() {
            const modal = document.getElementById('previewModal');
            const content = document.getElementById('previewModalContent');
            
            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                document.getElementById('previewModalImage').src = '';
            }, 200);
        }
        // --- FIN LOGICA MODAL ---

        <?php if ($is_admin): ?>
        // --- LOGICA DE SELECTOR VISUAL INTERACTIVO ---
        let isDragging = false;
        let isResizing = false;
        let startX, startY, startLeft, startTop, startWidth;
        let imgNaturalWidth, imgNaturalHeight;
        
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';

        const qrBox = document.getElementById('qrBox');
        const imgPreview = document.getElementById('imagePreview');
        const pdfCanvas = document.getElementById('pdfCanvas');
        const container = document.getElementById('previewContainer');
        const imageWrapper = document.getElementById('imageWrapper');
        const resizeHandle = document.getElementById('resizeHandle');

        function previewImage(event) {
            const file = event.target.files[0];
            if (!file) return;

            const ext = file.name.split('.').pop().toLowerCase();

            if (ext === 'pdf') {
                imgPreview.classList.add('hidden');
                pdfCanvas.classList.remove('hidden');

                const reader = new FileReader();
                reader.onload = async function(e) {
                    try {
                        const typedarray = new Uint8Array(e.target.result);
                        const pdf = await pdfjsLib.getDocument(typedarray).promise;
                        const page = await pdf.getPage(1);
                        
                        const viewport = page.getViewport({ scale: 1.5 });
                        const context = pdfCanvas.getContext('2d');
                        pdfCanvas.width = viewport.width;
                        pdfCanvas.height = viewport.height;
                        
                        const renderContext = {
                            canvasContext: context,
                            viewport: viewport
                        };
                        await page.render(renderContext).promise;
                        
                        imgNaturalWidth = page.view[2];
                        imgNaturalHeight = page.view[3];
                        
                        container.classList.remove('hidden');
                        
                        qrBox.style.left = '10px';
                        qrBox.style.top = '10px';
                        
                        let initialSize = Math.max(80, Math.floor(pdfCanvas.clientWidth * 0.2));
                        qrBox.style.width = initialSize + 'px';
                        qrBox.style.height = initialSize + 'px';

                        updateCoordinates();
                    } catch (err) {
                        alert('Error al renderizar el archivo PDF: ' + err.message);
                    }
                };
                reader.readAsArrayBuffer(file);
            } else {
                pdfCanvas.classList.add('hidden');
                imgPreview.classList.remove('hidden');

                const reader = new FileReader();
                reader.onload = function(e) {
                    imgPreview.src = e.target.result;
                    container.classList.remove('hidden');
                    
                    imgPreview.onload = function() {
                        imgNaturalWidth = imgPreview.naturalWidth;
                        imgNaturalHeight = imgPreview.naturalHeight;
                        
                        qrBox.style.left = '10px';
                        qrBox.style.top = '10px';
                        
                        let initialSize = Math.max(80, Math.floor(imgPreview.clientWidth * 0.2));
                        qrBox.style.width = initialSize + 'px';
                        qrBox.style.height = initialSize + 'px';

                        updateCoordinates();
                    }
                }
                reader.readAsDataURL(file);
            }
        }

        qrBox.addEventListener('mousedown', function(e) {
            if (e.target === resizeHandle) return; 
            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            startLeft = parseInt(window.getComputedStyle(qrBox).left, 10) || 0;
            startTop = parseInt(window.getComputedStyle(qrBox).top, 10) || 0;
            e.preventDefault();
        });

        resizeHandle.addEventListener('mousedown', function(e) {
            isResizing = true;
            startX = e.clientX;
            startWidth = parseInt(window.getComputedStyle(qrBox).width, 10) || 0;
            e.stopPropagation();
            e.preventDefault();
        });

        document.addEventListener('mousemove', function(e) {
            const activeEl = document.getElementById('pdfCanvas').classList.contains('hidden') 
                ? document.getElementById('imagePreview') 
                : document.getElementById('pdfCanvas');
                
            if (!activeEl || !activeEl.clientWidth) return;

            if (isDragging) {
                let dx = e.clientX - startX;
                let dy = e.clientY - startY;
                let newLeft = startLeft + dx;
                let newTop = startTop + dy;

                newLeft = Math.max(0, Math.min(newLeft, activeEl.clientWidth - qrBox.offsetWidth));
                newTop = Math.max(0, Math.min(newTop, activeEl.clientHeight - qrBox.offsetHeight));

                qrBox.style.left = newLeft + 'px';
                qrBox.style.top = newTop + 'px';
                updateCoordinates();
            } else if (isResizing) {
                let dx = e.clientX - startX;
                let newSize = startWidth + dx;

                newSize = Math.max(50, newSize);
                const currentLeft = parseInt(qrBox.style.left, 10) || 0;
                const currentTop = parseInt(qrBox.style.top, 10) || 0;
                newSize = Math.min(newSize, activeEl.clientWidth - currentLeft);
                newSize = Math.min(newSize, activeEl.clientHeight - currentTop);

                qrBox.style.width = newSize + 'px';
                qrBox.style.height = newSize + 'px'; 
                updateCoordinates();
            }
        });

        document.addEventListener('mouseup', function() {
            isDragging = false;
            isResizing = false;
        });

        function updateCoordinates() {
            const activeEl = document.getElementById('pdfCanvas').classList.contains('hidden') 
                ? document.getElementById('imagePreview') 
                : document.getElementById('pdfCanvas');
                
            if (!activeEl || !activeEl.clientWidth) return;
            
            const ratio = imgNaturalWidth / activeEl.clientWidth;
            
            const boxLeft = parseInt(qrBox.style.left, 10) || 0;
            const boxTop = parseInt(qrBox.style.top, 10) || 0;
            const boxSize = parseInt(qrBox.style.width, 10) || 0;

            const realX = Math.round(boxLeft * ratio);
            const realY = Math.round(boxTop * ratio);
            const realSize = Math.round(boxSize * ratio);

            document.getElementById('qr_x').value = realX;
            document.getElementById('qr_y').value = realY;
            document.getElementById('qr_size').value = realSize;
        }

        // --- FIN LOGICA DE SELECTOR VISUAL ---

        async function saveTemplate(e) {
            e.preventDefault();
            const form = e.target;
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerText = 'Guardando...';

            const formData = new FormData(form);
            formData.append('action', 'save');

            try {
                const res = await fetch('api/marketing_admin.php', { method: 'POST', body: formData });
                const data = await res.json();
                if(data.success) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Error al guardar');
                }
            } catch (err) {
                alert('Error de conexión');
            }
            btn.disabled = false;
            btn.innerText = 'Guardar Plantilla';
        }

        async function deleteTemplate(id) {
            if(!confirm('?Eliminar esta plantilla? Se borrar? la imagen y nadie podr? usarla más.')) return;
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);
            try {
                const res = await fetch('api/marketing_admin.php', { method: 'POST', body: formData });
                const data = await res.json();
                if(data.success) window.location.reload();
            } catch (err) {}
        }
        <?php else: ?>
        // Funciones exclusivas de suscriptor aqu? si son necesarias
        <?php endif; ?>
    </script>
</body>
</html>
