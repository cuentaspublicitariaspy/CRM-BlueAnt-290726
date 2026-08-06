<?php
require_once 'api/db_config.php';
header('Content-Type: text/html; charset=utf-8');
$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar reserva<?php echo isset($crm_name) ? ' - ' . htmlspecialchars($crm_name) : ''; ?></title>
    <?php if(isset($crm_favicon) && $crm_favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($crm_favicon); ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: radial-gradient(circle at top left, #f1f5f9 0%, #ffffff 100%); }
        .card { background: white; border: 1px solid #e2e8f0; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1); }
        .slot-btn.selected { background: #2563eb !important; color: white !important; border-color: #2563eb !important; }
    </style>
</head>
<body class="min-h-screen p-4 py-10">
    <div class="max-w-2xl mx-auto">
        <div class="flex items-center gap-3 mb-8 justify-center">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-blue-200">
                <i data-lucide="calendar-check" class="w-5 h-5"></i>
            </div>
            <span class="font-bold text-2xl tracking-tight text-slate-900"><?php echo isset($crm_name) ? htmlspecialchars($crm_name) : 'Mi reserva'; ?></span>
        </div>
        <div id="app" class="card rounded-[2rem] p-8 md:p-10">
            <div class="text-center py-10 text-slate-400"><i data-lucide="loader-2" class="w-8 h-8 mx-auto animate-spin mb-3"></i>Cargando...</div>
        </div>
    </div>

<script>
const TOKEN = <?php echo json_encode($token); ?>;
const app = document.getElementById('app');
let booking = null;
let mode = 'detail';

function icons() { lucide.createIcons(); }
function escapeHtml(str) { const d = document.createElement('div'); d.textContent = str ?? ''; return d.innerHTML; }
function formatDate(dateStr) { const d = new Date(dateStr + 'T00:00:00'); return d.toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long' }); }

async function api(action, opts = {}) {
    const method = opts.method || 'GET';
    let url = `api/agenda-public.php?action=${action}`;
    let fetchOpts = { method };
    if (method === 'GET') {
        url += '&' + new URLSearchParams(opts.params || {}).toString();
    } else {
        fetchOpts.headers = { 'Content-Type': 'application/json' };
        fetchOpts.body = JSON.stringify(opts.body || {});
    }
    const res = await fetch(url, fetchOpts);
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Error inesperado');
    return data;
}

function errorScreen(msg) {
    app.innerHTML = `
        <div class="text-center py-8">
            <div class="w-14 h-14 rounded-2xl bg-red-50 text-red-500 flex items-center justify-center mx-auto mb-4"><i data-lucide="alert-triangle" class="w-7 h-7"></i></div>
            <h2 class="text-lg font-bold text-slate-900 mb-2">No se pudo continuar</h2>
            <p class="text-slate-500 text-sm">${escapeHtml(msg)}</p>
        </div>`;
    icons();
}

const STATUS_LABELS = {
    held: 'Pendiente de confirmación', confirmed: 'Confirmada', rescheduled: 'Reprogramada',
    cancelled: 'Cancelada', completed: 'Completada', no_show: 'No asistió',
};

async function load() {
    if (!TOKEN) { errorScreen('Falta el enlace de gestión.'); return; }
    try {
        booking = await api('detail', { params: { manage_token: TOKEN } });
        renderDetail();
    } catch (e) { errorScreen(e.message); }
}

function renderDetail() {
    const active = ['confirmed', 'rescheduled'].includes(booking.status);
    app.innerHTML = `
        <div class="mb-6">
            <span class="text-xs font-black uppercase tracking-widest px-3 py-1 rounded-full ${active ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500'}">${STATUS_LABELS[booking.status] || booking.status}</span>
        </div>
        <h2 class="text-xl font-bold text-slate-900 mb-1">${escapeHtml(booking.service_name)}</h2>
        <p class="text-slate-500 text-sm mb-6">${escapeHtml(booking.resource_name)} · ${escapeHtml(booking.branch_name)}</p>
        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 mb-6 space-y-1">
            <div class="text-xs font-black uppercase tracking-widest text-slate-400">Fecha y hora</div>
            <div class="font-bold text-slate-900">${formatDate(booking.starts_at.slice(0,10))} · ${booking.starts_at.slice(11,16)}</div>
        </div>
        ${active && booking.zoom_join_url ? `
        <a href="${escapeHtml(booking.zoom_join_url)}" target="_blank" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-2xl font-bold text-sm flex items-center justify-center gap-2 mb-6 no-underline">
            <i data-lucide="video" class="w-4 h-4"></i>Unirme por Zoom
        </a>` : ''}
        ${active ? `
        <div class="flex items-center gap-2 mb-6">
            <input type="checkbox" id="attendChk" ${parseInt(booking.attendance_confirmed) ? 'checked disabled' : ''} class="w-5 h-5 rounded accent-blue-600">
            <label for="attendChk" class="text-sm text-slate-600 font-medium">${parseInt(booking.attendance_confirmed) ? 'Ya confirmaste tu asistencia' : 'Confirmar que voy a asistir'}</label>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <button id="rescheduleBtn" class="bg-slate-100 hover:bg-slate-200 text-slate-700 py-3 rounded-2xl font-bold text-sm">Reprogramar</button>
            <button id="cancelBtn" class="bg-red-50 hover:bg-red-100 text-red-600 py-3 rounded-2xl font-bold text-sm">Cancelar</button>
        </div>` : `<p class="text-slate-400 text-sm text-center">Esta reserva ya no admite cambios.</p>`}
    `;
    icons();
    if (active) {
        if (!parseInt(booking.attendance_confirmed)) {
            document.getElementById('attendChk').onchange = async (e) => {
                if (!e.target.checked) return;
                try { booking = await api('confirm-attendance', { method: 'POST', body: { manage_token: TOKEN } }).then(r => r.booking); renderDetail(); }
                catch (err) { alert(err.message); }
            };
        }
        document.getElementById('rescheduleBtn').onclick = renderRescheduleSlots;
        document.getElementById('cancelBtn').onclick = renderCancelConfirm;
    }
}

async function renderRescheduleSlots() {
    app.innerHTML = `<div class="text-center py-10 text-slate-400"><i data-lucide="loader-2" class="w-8 h-8 mx-auto animate-spin mb-3"></i>Buscando horarios...</div>`;
    icons();
    try {
        const from = new Date().toISOString().slice(0, 10);
        const to = new Date(Date.now() + 21 * 86400000).toISOString().slice(0, 10);
        const { slots } = await api('reschedule-availability', { params: { manage_token: TOKEN, from, to } });
        if (!slots.length) {
            app.innerHTML = `<p class="text-center text-slate-500 py-10">No hay horarios disponibles para reprogramar.</p>
                <button id="backBtn" class="w-full bg-slate-100 py-3 rounded-2xl font-bold text-sm text-slate-700">Volver</button>`;
            document.getElementById('backBtn').onclick = renderDetail;
            icons();
            return;
        }
        const byDate = {};
        slots.forEach(s => (byDate[s.starts_at.slice(0,10)] = byDate[s.starts_at.slice(0,10)] || []).push(s));
        app.innerHTML = `
            <button id="backBtn" class="text-slate-400 hover:text-slate-600 text-sm font-bold mb-4 flex items-center gap-1"><i data-lucide="arrow-left" class="w-4 h-4"></i>Volver</button>
            <h2 class="text-lg font-bold text-slate-900 mb-4">Elegí un nuevo horario</h2>
            <div class="space-y-6 max-h-[380px] overflow-y-auto pr-1">
                ${Object.keys(byDate).map(date => `
                    <div>
                        <div class="text-xs font-black uppercase tracking-widest text-slate-400 mb-2">${formatDate(date)}</div>
                        <div class="flex flex-wrap gap-2">
                            ${byDate[date].map(s => `<button data-start="${s.starts_at}" class="slot-btn bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm font-bold text-slate-700">${s.starts_at.slice(11,16)}</button>`).join('')}
                        </div>
                    </div>`).join('')}
            </div>`;
        document.getElementById('backBtn').onclick = renderDetail;
        app.querySelectorAll('.slot-btn').forEach(btn => btn.onclick = () => doReschedule(btn.dataset.start));
        icons();
    } catch (e) { errorScreen(e.message); }
}

async function doReschedule(startsAt) {
    try {
        booking = await api('reschedule', { method: 'POST', body: { manage_token: TOKEN, starts_at: startsAt } }).then(r => r.booking);
        renderDetail();
    } catch (e) { alert(e.message); }
}

function renderCancelConfirm() {
    app.innerHTML = `
        <h2 class="text-lg font-bold text-slate-900 mb-2">¿Cancelar esta reserva?</h2>
        <p class="text-slate-500 text-sm mb-6">Esta acción no se puede deshacer.</p>
        <textarea id="reasonInput" placeholder="Motivo (opcional)" class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 mb-4 text-sm outline-none focus:ring-4 focus:ring-red-500/10 focus:border-red-400" rows="3"></textarea>
        <div class="grid grid-cols-2 gap-3">
            <button id="backBtn" class="bg-slate-100 hover:bg-slate-200 text-slate-700 py-3 rounded-2xl font-bold text-sm">Volver</button>
            <button id="confirmCancelBtn" class="bg-red-500 hover:bg-red-600 text-white py-3 rounded-2xl font-bold text-sm">Sí, cancelar</button>
        </div>`;
    icons();
    document.getElementById('backBtn').onclick = renderDetail;
    document.getElementById('confirmCancelBtn').onclick = async () => {
        try {
            booking = await api('cancel', { method: 'POST', body: { manage_token: TOKEN, reason: document.getElementById('reasonInput').value } }).then(r => r.booking);
            renderDetail();
        } catch (e) { alert(e.message); }
    };
}

load();
</script>
</body>
</html>
