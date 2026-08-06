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
    <title>Reservar turno<?php echo isset($crm_name) ? ' - ' . htmlspecialchars($crm_name) : ''; ?></title>
    <?php if(isset($crm_favicon) && $crm_favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($crm_favicon); ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        .card { background: white; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 8px 24px -8px rgba(0,0,0,.08); }
        .slot-pill { transition: all .15s; }
        .slot-pill:hover { background: #eff6ff; }
        .cal-day:not(:disabled):hover { background: #eff6ff; }
    </style>
</head>
<body class="min-h-screen p-4 py-10">
    <div class="max-w-4xl mx-auto">
        <div id="app" class="card rounded-2xl overflow-hidden">
            <div class="text-center py-16 text-slate-400">
                <i data-lucide="loader-2" class="w-8 h-8 mx-auto animate-spin mb-3"></i>
                Cargando...
            </div>
        </div>
    </div>

<script>
const TOKEN = <?php echo json_encode($token); ?>;
const CRM_NAME = <?php echo json_encode(isset($crm_name) ? $crm_name : 'este negocio'); ?>;
const app = document.getElementById('app');
let state = {
    config: null, serviceId: null, resourceId: null,
    visibleMonth: null, selectedDate: null, slotsByDate: {},
    slot: null, hold: null, booking: null, holdTimer: null,
};

function icons() { lucide.createIcons(); }
function pad2(n) { return String(n).padStart(2, '0'); }
function ymd(y, mIdx, day) { return `${y}-${pad2(mIdx + 1)}-${pad2(day)}`; }
function currentService() { return state.config.services.find(s => s.id === state.serviceId); }
function currentResource() { return state.config.resources.find(r => r.id === state.resourceId); }
function currentBranch() {
    const r = currentResource();
    return r ? state.config.branches.find(b => b.id == r.branch_id) : null;
}

function errorScreen(msg) {
    app.innerHTML = `
        <div class="text-center py-14 px-6">
            <div class="w-14 h-14 rounded-2xl bg-red-50 text-red-500 flex items-center justify-center mx-auto mb-4"><i data-lucide="alert-triangle" class="w-7 h-7"></i></div>
            <h2 class="text-lg font-bold text-slate-900 mb-2">No se pudo continuar</h2>
            <p class="text-slate-500 text-sm">${escapeHtml(msg)}</p>
        </div>`;
    icons();
}

async function api(action, opts = {}) {
    const method = opts.method || 'GET';
    let url = `api/agenda-public.php?action=${action}`;
    let fetchOpts = { method };
    if (method === 'GET') {
        const params = new URLSearchParams(opts.params || {});
        url += '&' + params.toString();
    } else {
        fetchOpts.headers = { 'Content-Type': 'application/json' };
        fetchOpts.body = JSON.stringify(opts.body || {});
    }
    const res = await fetch(url, fetchOpts);
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Error inesperado');
    return data;
}

async function init() {
    if (!TOKEN) { errorScreen('Falta el enlace de reserva.'); return; }
    try {
        state.config = await api('link-config', { params: { token: TOKEN } });
        state.serviceId = state.config.preselected.service_id;
        state.resourceId = state.config.preselected.resource_id;
        step();
    } catch (e) { errorScreen(e.message); }
}

function step() {
    if (!state.serviceId) return renderServiceStep();
    if (!state.resourceId) return renderResourceStep();
    if (!state.booking) {
        if (!state.slot) return renderBookingStep();
        return renderContactStep();
    }
}

// ── Paso 0/1: elegir servicio / recurso (solo cuando el enlace es general) ──
function renderServiceStep() {
    const services = state.config.services;
    app.innerHTML = `
        <div class="p-8 md:p-10">
            <h2 class="text-xl font-bold text-slate-900 mb-1">¿Qué servicio necesitás?</h2>
            <p class="text-slate-400 text-sm mb-6">Elegí una opción para ver los horarios disponibles.</p>
            <div class="space-y-3">
                ${services.map(s => `
                    <button data-id="${s.id}" class="svc-btn w-full text-left bg-slate-50 hover:bg-blue-50 border border-slate-200 hover:border-blue-300 rounded-2xl p-5 transition-all">
                        <div class="font-bold text-slate-900">${escapeHtml(s.name)}</div>
                        <div class="text-xs text-slate-400 mt-1">${s.duration_min} min${s.price ? ' · ' + s.price + ' ' + (s.currency||'') : ''}</div>
                    </button>`).join('')}
            </div>
        </div>`;
    app.querySelectorAll('.svc-btn').forEach(btn => btn.onclick = () => { state.serviceId = parseInt(btn.dataset.id); step(); });
    icons();
}

function renderResourceStep() {
    const resources = state.config.resources.filter(r => r.service_ids.includes(state.serviceId));
    if (resources.length === 1) { state.resourceId = resources[0].id; return step(); }
    app.innerHTML = `
        <div class="p-8 md:p-10">
            <button id="backBtn" class="text-slate-400 hover:text-slate-600 text-sm font-bold mb-4 flex items-center gap-1"><i data-lucide="arrow-left" class="w-4 h-4"></i>Volver</button>
            <h2 class="text-xl font-bold text-slate-900 mb-1">¿Con quién preferís?</h2>
            <p class="text-slate-400 text-sm mb-6">Elegí un recurso disponible.</p>
            <div class="space-y-3">
                ${resources.map(r => `
                    <button data-id="${r.id}" class="res-btn w-full text-left bg-slate-50 hover:bg-blue-50 border border-slate-200 hover:border-blue-300 rounded-2xl p-5 transition-all">
                        <div class="font-bold text-slate-900">${escapeHtml(r.name)}</div>
                        ${r.description ? `<div class="text-xs text-slate-400 mt-1">${escapeHtml(r.description)}</div>` : ''}
                    </button>`).join('')}
            </div>
        </div>`;
    document.getElementById('backBtn').onclick = () => { state.serviceId = null; step(); };
    app.querySelectorAll('.res-btn').forEach(btn => btn.onclick = () => { state.resourceId = parseInt(btn.dataset.id); step(); });
    icons();
}

// ── Sidebar de perfil (persiste durante calendario y datos de contacto) ──
function sidebarHtml() {
    const svc = currentService(); const res = currentResource(); const branch = currentBranch();
    const accent = (res && res.color) || '#2563eb';
    const initials = ((res && res.name) || '?').trim().charAt(0).toUpperCase();
    const hasPrice = svc && svc.price && parseFloat(svc.price) > 0;
    const priceLabel = hasPrice ? `${svc.price} ${svc.currency || ''}`.trim() : 'Sin costo';
    const avatarHtml = res && res.photo_url
        ? `<img src="${res.photo_url}" class="w-16 h-16 rounded-full object-cover mb-4">`
        : `<div class="w-16 h-16 rounded-full flex items-center justify-center text-xl font-black text-white mb-4" style="background:${accent}">${escapeHtml(initials)}</div>`;
    return `
        <div class="p-8">
            ${avatarHtml}
            <div class="text-xs font-bold text-slate-400 mb-1">${escapeHtml((res && res.name) || '')}</div>
            <h2 class="text-xl font-black text-slate-900 mb-4 leading-tight">${escapeHtml((svc && svc.name) || '')}</h2>
            <div class="space-y-2.5 text-sm text-slate-600">
                <div class="flex items-center gap-2"><i data-lucide="clock" class="w-4 h-4 text-slate-400 shrink-0"></i>${(svc && svc.duration_min) || ''} min</div>
                <div class="flex items-center gap-2"><i data-lucide="map-pin" class="w-4 h-4 text-slate-400 shrink-0"></i>${escapeHtml((branch && branch.name) || '')}${branch && branch.city ? ' · ' + escapeHtml(branch.city) : ''}</div>
                <div class="flex items-center gap-2"><i data-lucide="banknote" class="w-4 h-4 text-slate-400 shrink-0"></i>${escapeHtml(priceLabel)}</div>
            </div>
            ${res && res.description ? `<p class="text-sm text-slate-500 mt-5 pt-5 border-t border-slate-100">${escapeHtml(res.description)}</p>` : ''}
        </div>`;
}

// ── Paso 2: calendario mensual + horarios del día elegido ──
async function renderBookingStep() {
    if (!state.visibleMonth) { state.visibleMonth = new Date(); state.visibleMonth.setDate(1); }
    app.innerHTML = `<div class="grid lg:grid-cols-[280px_1fr]"><div class="border-b lg:border-b-0 lg:border-r border-slate-100">${sidebarHtml()}</div><div class="p-8 md:p-10 text-center text-slate-400"><i data-lucide="loader-2" class="w-7 h-7 mx-auto animate-spin mb-3"></i>Buscando horarios...</div></div>`;
    icons();
    try {
        await loadAvailabilityForVisibleMonth();
        renderBookingLayout();
    } catch (e) { errorScreen(e.message); }
}

async function loadAvailabilityForVisibleMonth() {
    const y = state.visibleMonth.getFullYear(), m = state.visibleMonth.getMonth();
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    const today = new Date();
    const todayStr = ymd(today.getFullYear(), today.getMonth(), today.getDate());
    const monthStartStr = ymd(y, m, 1);
    const monthEndStr = ymd(y, m, daysInMonth);
    const from = monthStartStr < todayStr ? todayStr : monthStartStr;
    const { slots } = await api('availability', { params: { token: TOKEN, resource_id: state.resourceId, service_id: state.serviceId, from, to: monthEndStr } });
    state.slotsByDate = {};
    slots.forEach(s => {
        const d = s.starts_at.slice(0, 10);
        (state.slotsByDate[d] = state.slotsByDate[d] || []).push(s);
    });
}

function renderBookingLayout() {
    const y = state.visibleMonth.getFullYear(), m = state.visibleMonth.getMonth();
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    const firstWeekday = new Date(y, m, 1).getDay();
    const monthLabel = state.visibleMonth.toLocaleDateString('es-ES', { month: 'long', year: 'numeric' });
    const today = new Date();
    const isCurrentOrPastMonth = (y * 12 + m) <= (today.getFullYear() * 12 + today.getMonth());
    const anyAvailable = Object.values(state.slotsByDate).some(arr => arr.length);

    const cells = [];
    for (let i = 0; i < firstWeekday; i++) cells.push('<div></div>');
    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = ymd(y, m, d);
        const daySlots = state.slotsByDate[dateStr] || [];
        const hasSlots = daySlots.length > 0;
        const isSelected = state.selectedDate === dateStr;
        let cls = 'w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold mx-auto';
        let style = '';
        if (!hasSlots) cls += ' text-slate-300';
        else if (isSelected) { cls += ' text-white'; style = ' style="background:#2563eb"'; }
        else cls += ' text-slate-700 cal-day cursor-pointer';
        cells.push(`<button ${hasSlots ? `data-date="${dateStr}"` : 'disabled'} class="${cls}"${style}>${d}</button>`);
    }

    const backBtn = !state.config.preselected.resource_id
        ? `<button id="backBtn" class="text-slate-400 hover:text-slate-600 text-xs font-bold mb-3 flex items-center gap-1"><i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Volver</button>`
        : '';

    const slotsColumnHtml = state.selectedDate ? `
        <div class="lg:w-56 lg:border-l border-slate-100 lg:pl-6 mt-6 lg:mt-0">
            <div class="text-sm font-black text-slate-900 mb-3">${formatDateShort(state.selectedDate)}</div>
            <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                ${(state.slotsByDate[state.selectedDate] || []).map(s => `
                    <button data-start="${s.starts_at}" class="slot-pill w-full text-center border-2 border-blue-200 text-blue-700 font-bold text-sm rounded-xl py-2 px-3">${s.starts_at.slice(11,16)}</button>
                `).join('') || '<p class="text-xs text-slate-400">Sin horarios este día.</p>'}
            </div>
        </div>` : '';

    app.innerHTML = `
        <div class="grid lg:grid-cols-[280px_1fr]">
            <div class="border-b lg:border-b-0 lg:border-r border-slate-100">${sidebarHtml()}</div>
            <div class="p-8 md:p-10">
                ${backBtn}
                <div class="flex items-center justify-between mb-6">
                    <h3 class="font-black text-slate-900">Elegí fecha y horario</h3>
                    <span class="text-xs text-slate-400">${escapeHtml((currentBranch() && currentBranch().timezone) || '')}</span>
                </div>
                <div class="flex lg:flex-row flex-col gap-0 lg:gap-6">
                    <div class="lg:w-72 shrink-0">
                        <div class="flex items-center justify-between mb-4">
                            <button id="prevMonthBtn" ${isCurrentOrPastMonth ? 'disabled' : ''} class="w-7 h-7 rounded-lg flex items-center justify-center ${isCurrentOrPastMonth ? 'text-slate-200' : 'text-slate-500 hover:bg-slate-100'}"><i data-lucide="chevron-left" class="w-4 h-4"></i></button>
                            <span class="font-black text-sm text-slate-900 capitalize">${monthLabel}</span>
                            <button id="nextMonthBtn" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-500 hover:bg-slate-100"><i data-lucide="chevron-right" class="w-4 h-4"></i></button>
                        </div>
                        <div class="grid grid-cols-7 gap-y-2 text-center text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">
                            <div>Dom</div><div>Lun</div><div>Mar</div><div>Mié</div><div>Jue</div><div>Vie</div><div>Sáb</div>
                        </div>
                        <div class="grid grid-cols-7 gap-y-2">${cells.join('')}</div>
                        ${!anyAvailable ? '<p class="text-xs text-slate-400 mt-4">No hay horarios disponibles este mes — probá el mes siguiente.</p>' : ''}
                        ${!state.selectedDate && anyAvailable ? '<p class="text-xs text-slate-400 mt-4">Elegí una fecha disponible para ver los horarios.</p>' : ''}
                    </div>
                    ${slotsColumnHtml}
                </div>
                <div class="border-t border-slate-100 mt-8 pt-4 text-center">
                    <span class="text-[11px] text-slate-300">Powered by ${escapeHtml(CRM_NAME)}</span>
                </div>
            </div>
        </div>`;

    if (backBtn) document.getElementById('backBtn').onclick = () => { state.resourceId = null; state.selectedDate = null; state.visibleMonth = null; step(); };
    document.getElementById('prevMonthBtn').onclick = () => shiftMonth(-1);
    document.getElementById('nextMonthBtn').onclick = () => shiftMonth(1);
    app.querySelectorAll('[data-date]').forEach(btn => btn.onclick = () => { state.selectedDate = btn.dataset.date; renderBookingLayout(); });
    app.querySelectorAll('.slot-pill').forEach(btn => btn.onclick = () => selectSlot(btn.dataset.start));
    icons();
}

async function shiftMonth(delta) {
    state.visibleMonth.setMonth(state.visibleMonth.getMonth() + delta);
    state.selectedDate = null;
    await renderBookingStep();
}

async function selectSlot(startsAt) {
    app.innerHTML = `<div class="text-center py-14 text-slate-400"><i data-lucide="loader-2" class="w-8 h-8 mx-auto animate-spin mb-3"></i>Reteniendo horario...</div>`;
    icons();
    try {
        const { booking } = await api('hold', { method: 'POST', body: { token: TOKEN, resource_id: state.resourceId, service_id: state.serviceId, starts_at: startsAt } });
        state.slot = startsAt;
        state.hold = booking;
        step();
    } catch (e) {
        app.innerHTML = `<div class="text-center py-10 px-6"><p class="text-red-500 font-bold mb-4">${escapeHtml(e.message)}</p></div>`;
        setTimeout(() => renderBookingLayout(), 1500);
    }
}

// ── Paso 3: datos de contacto ──
function renderContactStep() {
    // Segundos restantes calculados por el servidor (con la timezone correcta
    // de la sucursal) — nunca parsear hold_expires_at como fecha en el
    // navegador, porque su hora local puede no coincidir con la de la
    // sucursal y el conteo regresivo terminaría siempre "vencido" al instante.
    let remainingSeconds = Number.isFinite(state.hold.hold_seconds) ? state.hold.hold_seconds : 300;
    const svc = currentService();
    app.innerHTML = `
        <div class="grid lg:grid-cols-[280px_1fr]">
            <div class="border-b lg:border-b-0 lg:border-r border-slate-100">${sidebarHtml()}</div>
            <div class="p-8 md:p-10">
                <button id="backToCalBtn" class="text-blue-600 hover:text-blue-700 text-xs font-bold mb-4 flex items-center gap-1"><i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Volver al calendario</button>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-black text-slate-900">Confirmá tu turno</h3>
                    <span id="countdown" class="text-xs font-bold text-amber-600 bg-amber-50 px-3 py-1 rounded-full"></span>
                </div>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 mb-6 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-slate-400 shrink-0"><i data-lucide="calendar" class="w-4 h-4"></i></div>
                    <div>
                        <div class="font-bold text-slate-800 text-sm capitalize">${formatDate(state.slot.slice(0,10))}</div>
                        <div class="text-blue-600 font-bold text-xs">${state.slot.slice(11,16)} · ${(svc && svc.duration_min) || ''} min</div>
                    </div>
                </div>
                <form id="contactForm" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nombre completo *</label>
                        <input id="cf-fullname" required name="fullname" placeholder="Juan Pérez" class="w-full bg-white border border-slate-200 rounded-xl py-2.5 px-4 outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Teléfono *</label>
                        <input id="cf-phone" required name="phone" placeholder="(555) 123-4567" class="w-full bg-white border border-slate-200 rounded-xl py-2.5 px-4 outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Email</label>
                        <input name="email" type="email" placeholder="juan@email.com" class="w-full bg-white border border-slate-200 rounded-xl py-2.5 px-4 outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500">
                    </div>
                    <button type="submit" id="confirmSubmitBtn" disabled class="w-full bg-slate-100 text-slate-400 py-3.5 rounded-xl font-bold transition-all">Confirmar turno</button>
                    <p class="text-[11px] text-slate-400 text-center">Al reservar, aceptás ser contactado/a para coordinar este turno.</p>
                </form>
            </div>
        </div>`;
    icons();

    document.getElementById('backToCalBtn').onclick = () => {
        clearInterval(state.holdTimer);
        state.slot = null; state.hold = null;
        renderBookingLayout();
    };

    const form = document.getElementById('contactForm');
    const submitBtn = document.getElementById('confirmSubmitBtn');
    const fullnameInput = document.getElementById('cf-fullname');
    const phoneInput = document.getElementById('cf-phone');
    function updateSubmitState() {
        const valid = fullnameInput.value.trim() !== '' && phoneInput.value.trim() !== '';
        submitBtn.disabled = !valid;
        submitBtn.className = valid
            ? 'w-full bg-blue-600 hover:bg-blue-700 text-white py-3.5 rounded-xl font-bold shadow-lg shadow-blue-100 transition-all active:scale-[0.98]'
            : 'w-full bg-slate-100 text-slate-400 py-3.5 rounded-xl font-bold transition-all';
    }
    fullnameInput.oninput = updateSubmitState;
    phoneInput.oninput = updateSubmitState;

    function tick() {
        const el = document.getElementById('countdown');
        if (!el) { clearInterval(state.holdTimer); return; }
        if (remainingSeconds <= 0) {
            clearInterval(state.holdTimer);
            state.slot = null; state.hold = null;
            app.innerHTML = `<div class="text-center py-10 px-6"><p class="text-amber-600 font-bold mb-4">El tiempo para confirmar venció. Elegí un horario nuevamente.</p></div>`;
            setTimeout(() => renderBookingLayout(), 1200);
            return;
        }
        el.textContent = `Expira en ${Math.floor(remainingSeconds/60)}:${String(remainingSeconds%60).padStart(2,'0')}`;
        remainingSeconds--;
    }
    clearInterval(state.holdTimer);
    state.holdTimer = setInterval(tick, 1000);
    tick();

    form.onsubmit = async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const body = { manage_token: state.hold.manage_token, name: fd.get('fullname'), phone: fd.get('phone'), email: fd.get('email') };
        try {
            const { booking } = await api('confirm', { method: 'POST', body });
            clearInterval(state.holdTimer);
            state.booking = booking;
            renderDoneStep();
        } catch (err) {
            showFormError(err.message);
        }
    };
}

function showFormError(msg) {
    let el = document.getElementById('contactFormError');
    if (!el) {
        el = document.createElement('p');
        el.id = 'contactFormError';
        el.className = 'text-red-500 text-xs font-bold text-center mt-3';
        document.getElementById('contactForm').appendChild(el);
    }
    el.textContent = msg;
}

// ── Paso 4: confirmación (reemplaza todo el layout, sin sidebar) ──
function renderDoneStep() {
    const svc = currentService(); const res = currentResource();
    const accent = (res && res.color) || '#2563eb';
    const initials = ((res && res.name) || '?').trim().charAt(0).toUpperCase();
    const avatarHtml = res && res.photo_url
        ? `<img src="${res.photo_url}" class="w-9 h-9 rounded-full object-cover shrink-0">`
        : `<div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-black text-white shrink-0" style="background:${accent}">${escapeHtml(initials)}</div>`;
    const manageUrl = `${location.origin}${location.pathname.replace('reservar.php','reservar-gestionar.php')}?token=${state.booking.manage_token}`;
    app.innerHTML = `
        <div class="text-center py-12 px-8 max-w-md mx-auto">
            <div class="w-14 h-14 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center mx-auto mb-5"><i data-lucide="check" class="w-7 h-7"></i></div>
            <h2 class="text-xl font-black text-slate-900 mb-2">¡Turno confirmado!</h2>
            <p class="text-slate-500 text-sm mb-6">Tu ${escapeHtml((svc && svc.name) || 'turno')}${svc ? ` (${svc.duration_min} min)` : ''} con ${escapeHtml((res && res.name) || '')} quedó confirmado.</p>
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 mb-6">
                <div class="font-bold text-slate-800 text-sm capitalize">${formatDate(state.booking.starts_at.slice(0,10))}</div>
                <div class="text-blue-600 font-bold text-sm">${state.booking.starts_at.slice(11,16)}</div>
            </div>
            <div class="flex items-center justify-center gap-3 mb-6">
                ${avatarHtml}
                <span class="font-bold text-sm text-slate-700">${escapeHtml((res && res.name) || '')}</span>
            </div>
            ${state.booking.zoom_join_url ? `
            <a href="${escapeHtml(state.booking.zoom_join_url)}" target="_blank" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl font-bold transition-all flex items-center justify-center gap-2 mb-6 no-underline">
                <i data-lucide="video" class="w-4 h-4"></i>Unirme por Zoom
            </a>` : ''}
            <div class="border-t border-slate-100 pt-5 text-left">
                <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Guardá este enlace para reprogramar o cancelar</div>
                <a href="${manageUrl}" class="text-blue-600 text-sm font-bold break-all">${manageUrl}</a>
            </div>
        </div>`;
    icons();
}

function formatDate(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long' });
}
function formatDateShort(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('es-ES', { weekday: 'short', day: 'numeric', month: 'short' });
}
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

init();
</script>
</body>
</html>
