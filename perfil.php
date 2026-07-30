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
    <title>Mi Perfil - Ultra CRM</title>
    <?php if(isset($crm_favicon) && $crm_favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($crm_favicon); ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="lib/phone-picker.js"></script>
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

        /* OVERRIDES FORMULARIOS OSCUROS */
        .glass-card input[type="text"], .glass-card input[type="email"], .glass-card select { background: rgba(15,23,42,0.5) !important; border-color: #334155 !important; color: white !important; }
        .glass-card select option { background: #1e293b; color: white; }
        .glass-card .text-slate-400 { color: var(--card-muted) !important; }

        /* OVERRIDES PHONE PICKER OSCURO */
        .glass-card .bg-slate-50 { background: rgba(15,23,42,0.5) !important; border-color: #334155 !important; color: white !important; }
        .glass-card .bg-slate-100 { background: rgba(30,41,59,0.8) !important; border-color: #334155 !important; color: white !important; }
        .glass-card .text-slate-600 { color: #cbd5e1 !important; }
        .glass-card .text-slate-700 { color: #e2e8f0 !important; }
        .glass-card .bg-white { background: var(--card-bg) !important; border-color: #334155 !important; }
        .glass-card .hover\:bg-slate-200:hover { background: rgba(51,65,85,1) !important; }
        .glass-card .hover\:bg-indigo-50:hover { background: rgba(59,130,246,0.1) !important; }
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

                            <a href="configuracion.php" class="nav-link   flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

                                <i data-lucide="settings" class="w-5 h-5"></i>Configuración</a>

                            <?php endif; ?>

                            <a href="perfil.php" class="nav-link active flex items-center gap-4 px-4 py-3 rounded-xl transition-all">

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

                    <a href="configuracion.php" class="nav-link   flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

                        <i data-lucide="settings" class="w-5 h-5"></i>Configuración</a>

                    <?php endif; ?>

                    <a href="perfil.php" class="nav-link active flex items-center gap-3 px-4 py-3 rounded-xl transition-all">

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
                <h1 class="text-xl font-bold text-slate-900">Configuración de Perfil</h1>
            </div>
        </header>

        <div class="p-6 lg:p-10 max-w-2xl mx-auto w-full pb-32">
            <div class="glass-card rounded-[2.5rem] p-8 md:p-12">
                <!-- Foto de Perfil -->
                <div class="relative w-32 h-32 mx-auto mb-10">
                    <img id="profileImage" src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name']); ?>&background=6366f1&color=fff" class="w-32 h-32 rounded-[2.5rem] object-cover shadow-2xl border-4 border-white">
                    <button type="button" onclick="document.getElementById('fileInput').click()" class="absolute -bottom-2 -right-2 bg-indigo-600 text-white p-3 rounded-2xl shadow-lg hover:bg-indigo-700 transition-all active:scale-90"><i data-lucide="camera" class="w-5 h-5"></i></button>
                </div>

                <form id="profileForm" class="space-y-6">
                    <!-- Input de foto DENTRO del form para que FormData lo capture -->
                    <input type="file" name="avatar" id="fileInput" class="hidden" accept="image/*">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Nombre Completo</label>
                        <input type="text" name="name" id="userName" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-4 px-6 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all">
                    </div>

                    <div class="space-y-2">
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">WhatsApp de Contacto</label>
                        <div id="profile-phone-picker"></div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Email (No editable)</label>
                        <input type="email" id="userEmail" disabled class="w-full bg-slate-100 border border-slate-200 rounded-2xl py-4 px-6 text-slate-400 cursor-not-allowed">
                    </div>

                    <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Nivel de Usuario</label>
                        <select name="role" id="userRole" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-4 px-6 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all appearance-none cursor-pointer">
                            <option value="subscriber">Suscriptor</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    <?php else: ?>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Nivel de Usuario</label>
                        <div class="w-full bg-slate-100 border border-slate-200 rounded-2xl py-4 px-6 text-slate-500 text-sm font-bold flex items-center gap-2">
                            <i data-lucide="shield" class="w-4 h-4 text-indigo-400"></i> Suscriptor
                        </div>
                    </div>
                    <?php endif; ?>

                    <button type="submit" id="saveBtn" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-5 rounded-2xl font-bold shadow-xl shadow-indigo-100 transition-all active:scale-95 flex items-center justify-center gap-2 mt-8">
                        <i data-lucide="save" class="w-5 h-5"></i> Guardar Cambios
                    </button>
                </form>

                <!-- Cambio de Contraseña -->
                <div class="mt-12 pt-10 border-t border-slate-100 space-y-8">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-rose-50 text-rose-500 rounded-xl flex items-center justify-center">
                            <i data-lucide="shield-check" class="w-5 h-5"></i>
                        </div>
                        <h3 class="text-xl font-bold text-white">Seguridad</h3>
                    </div>

                    <form id="passwordForm" class="space-y-4">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Contraseña Actual</label>
                            <input type="password" id="currentPass" required placeholder="????????" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-4 px-6 outline-none focus:ring-4 focus:ring-rose-500/10 focus:border-rose-500 transition-all">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Nueva Contraseña</label>
                                <input type="password" id="newPass" required placeholder="Mín. 6 caracteres" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-4 px-6 outline-none focus:ring-4 focus:ring-rose-500/10 focus:border-rose-500 transition-all">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Confirmar Nueva</label>
                                <input type="password" id="confirmPass" required placeholder="Repetir contraseña" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-4 px-6 outline-none focus:ring-4 focus:ring-rose-500/10 focus:border-rose-500 transition-all">
                            </div>
                        </div>
                        <button type="submit" id="passBtn" class="w-full bg-slate-900 hover:bg-black text-white py-4 rounded-2xl font-bold shadow-xl transition-all active:scale-95 flex items-center justify-center gap-2 mt-2">
                            <i data-lucide="refresh-cw" class="w-5 h-5"></i> Actualizar Contraseña
                        </button>
                    </form>
                </div>

                <div class="mt-8 pt-8 border-t border-slate-100">
                    <button onclick="logout()" class="w-full py-4 text-red-500 font-bold hover:bg-red-50 rounded-2xl transition-all flex items-center justify-center gap-2">
                        <i data-lucide="log-out" class="w-5 h-5"></i> Cerrar Sesión
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script>
        function toggleMenu() { document.getElementById('mobileMenu').classList.toggle('hidden'); }
        async function logout() { await fetch('api/auth.php?action=logout'); window.location.href = 'login.php'; }

        // Cargar perfil
        async function fetchProfile() {
            try {
                const res  = await fetch('api/profile.php');
                const data = await res.json();
                document.getElementById('userName').value  = data.name  || '';
                document.getElementById('userEmail').value  = data.email || '';
                // Cargar WhatsApp existente en el picker (si est? disponible) o en el input plano
                const pickerEl = document.getElementById('profile-phone-picker');
                if (pickerEl && typeof pickerEl._pickerSetValue === 'function') {
                    pickerEl._pickerSetValue(data.whatsapp || '');
                } else {
                    const plain = document.getElementById('userWhatsapp');
                    if (plain) plain.value = data.whatsapp || '';
                }
                <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                document.getElementById('userRole').value = data.role || 'subscriber';
                <?php endif; ?>
                const img = document.getElementById('profileImage');
                if (data.avatar_url) {
                    img.src = data.avatar_url + '?v=' + Date.now();
                } else {
                    img.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(data.name || 'U') + '&background=6366f1&color=fff&size=128&bold=true';
                }
            } catch (err) { console.error('fetchProfile error:', err); }
        }

        // ── FOTO: sube con XHR al instante de seleccionar ──
        document.getElementById('fileInput').addEventListener('change', function() {
            if (!this.files || !this.files[0]) return;
            const file = this.files[0];

            // Preview inmediato
            const reader = new FileReader();
            reader.onload = e => { document.getElementById('profileImage').src = e.target.result; };
            reader.readAsDataURL(file);

            // Indicador de carga en la imagen
            const img = document.getElementById('profileImage');
            img.style.opacity = '0.5';

            // XHR ? siempre funciona con multipart en cualquier hosting
            const xhr = new XMLHttpRequest();
            const fd  = new FormData();
            fd.append('avatar', file);

            xhr.open('POST', 'api/profile.php', true);

            xhr.onload = function() {
                img.style.opacity = '1';
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (data.success && data.avatar_url) {
                        img.src = data.avatar_url + '?v=' + Date.now();
                        showToast('✅ Foto actualizada');
                    } else {
                        showToast('❌ ' + (data.error || 'Error al subir foto'), true);
                    }
                } catch(e) {
                    showToast('❌ Respuesta inesperada del servidor', true);
                    console.error('XHR response:', xhr.responseText);
                }
            };
            xhr.onerror = function() {
                img.style.opacity = '1';
                showToast('❌ Error de red al subir foto', true);
            };
            xhr.send(fd);
        });

        // ── DATOS DE TEXTO: guarda con fetch JSON (sin archivo) ──
        document.getElementById('profileForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('saveBtn');
            btn.innerHTML = '<svg class="animate-spin w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Guardando...';
            btn.disabled = true;

            const payload = {
                name:     document.getElementById('userName').value.trim(),
                // Leer WhatsApp del picker (E.164) o del input plano de fallback
                whatsapp: (document.getElementById('profile-phone-picker-value')?.value?.trim()
                          || document.getElementById('userWhatsapp')?.value?.trim()
                          || ''),
                <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                role: document.getElementById('userRole').value,
                <?php endif; ?>
            };

            try {
                const res    = await fetch('api/profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await res.json();

                if (result.success) {
                    btn.innerHTML = '✅ ?Guardado!';
                    btn.classList.replace('bg-indigo-600', 'bg-emerald-500');
                    setTimeout(() => {
                        btn.innerHTML = '<i data-lucide="save" class="w-5 h-5"></i> Guardar Cambios';
                        btn.classList.replace('bg-emerald-500', 'bg-indigo-600');
                        btn.disabled = false;
                        lucide.createIcons();
                    }, 2000);
                } else {
                    btn.innerHTML = '❌ ' + (result.error || 'Error');
                    btn.classList.replace('bg-indigo-600', 'bg-red-500');
                    btn.disabled = false;
                    setTimeout(() => {
                        btn.innerHTML = '<i data-lucide="save" class="w-5 h-5"></i> Guardar Cambios';
                        btn.classList.replace('bg-red-500', 'bg-indigo-600');
                        lucide.createIcons();
                    }, 3000);
                }
            } catch (err) {
                btn.innerHTML = '❌ Error de red';
                btn.disabled = false;
            }
        });

        // ── CAMBIO DE CONtraseña ──
        document.getElementById('passwordForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('passBtn');
            const current = document.getElementById('currentPass').value;
            const newP = document.getElementById('newPass').value;
            const confirm = document.getElementById('confirmPass').value;

            if (newP !== confirm) { showToast('❌ Las contraseñas no coinciden', true); return; }
            if (newP.length < 6) { showToast('❌ Mínimo 6 caracteres', true); return; }

            btn.disabled = true;
            btn.innerHTML = 'Procesando...';

            try {
                const res = await fetch('api/profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'change_password', current_password: current, new_password: newP })
                });
                const result = await res.json();
                if (result.success) {
                    showToast('✅ Contraseña actualizada correctamente');
                    document.getElementById('passwordForm').reset();
                } else {
                    showToast('❌ ' + (result.error || 'Error'), true);
                }
            } catch (err) { showToast('❌ Error de red', true); }
            
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="refresh-cw" class="w-5 h-5"></i> Actualizar Contraseña';
            lucide.createIcons();
        });

        function showToast(msg, isError) {
            const t = document.createElement('div');
            t.textContent = msg;
            t.style.cssText = `position:fixed;bottom:120px;left:50%;transform:translateX(-50%);background:${isError?'#ef4444':'#0f172a'};color:#fff;padding:.5rem 1.25rem;border-radius:9999px;font-size:.8rem;font-weight:700;z-index:9999;`;
            document.body.appendChild(t);
            setTimeout(() => t.remove(), 3000);
        }

        // Inicializar PhonePicker (si est? disponible en el servidor)
        if (typeof PhonePicker !== 'undefined') {
            PhonePicker.render('profile-phone-picker', 'whatsapp', { theme: 'crm', placeholder: 'Número local' });
        } else {
            // Fallback: input plano mientras se despliega phone-picker.js
            document.getElementById('profile-phone-picker').innerHTML =
                '<input type="text" name="whatsapp" id="userWhatsapp" placeholder="+595..." ' +
                'class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-4 px-6 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all">';
        }

        fetchProfile();
        lucide.createIcons();
    </script>

</body>
</html>
