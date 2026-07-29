/**
 * PhonePicker – Selector de prefijo telefónico internacional
 * Uso: PhonePicker.render(containerId, inputName, options)
 * El valor final guardado en el hidden input es E.164: +595981234567
 */
const PhonePicker = (() => {

  const COUNTRIES = [
    // Latinoamérica primero
    { name: 'Argentina',           flag: '🇦🇷', code: '+54',  iso: 'AR', fmt: '11 1234-5678' },
    { name: 'Bolivia',             flag: '🇧🇴', code: '+591', iso: 'BO', fmt: '7 123 4567' },
    { name: 'Brasil',              flag: '🇧🇷', code: '+55',  iso: 'BR', fmt: '11 91234-5678' },
    { name: 'Chile',               flag: '🇨🇱', code: '+56',  iso: 'CL', fmt: '9 1234 5678' },
    { name: 'Colombia',            flag: '🇨🇴', code: '+57',  iso: 'CO', fmt: '310 123 4567' },
    { name: 'Costa Rica',          flag: '🇨🇷', code: '+506', iso: 'CR', fmt: '8312 3456' },
    { name: 'Cuba',                flag: '🇨🇺', code: '+53',  iso: 'CU', fmt: '5 1234567' },
    { name: 'Ecuador',             flag: '🇪🇨', code: '+593', iso: 'EC', fmt: '99 123 4567' },
    { name: 'El Salvador',         flag: '🇸🇻', code: '+503', iso: 'SV', fmt: '7012 3456' },
    { name: 'Guatemala',           flag: '🇬🇹', code: '+502', iso: 'GT', fmt: '5123 4567' },
    { name: 'Honduras',            flag: '🇭🇳', code: '+504', iso: 'HN', fmt: '9123 4567' },
    { name: 'México',              flag: '🇲🇽', code: '+52',  iso: 'MX', fmt: '55 1234 5678' },
    { name: 'Nicaragua',           flag: '🇳🇮', code: '+505', iso: 'NI', fmt: '8123 4567' },
    { name: 'Panamá',              flag: '🇵🇦', code: '+507', iso: 'PA', fmt: '6123-4567' },
    { name: 'Paraguay',            flag: '🇵🇾', code: '+595', iso: 'PY', fmt: '981 234 567' },
    { name: 'Perú',                flag: '🇵🇪', code: '+51',  iso: 'PE', fmt: '912 345 678' },
    { name: 'Puerto Rico',         flag: '🇵🇷', code: '+1787',iso: 'PR', fmt: '555-1234' },
    { name: 'Rep. Dominicana',     flag: '🇩🇴', code: '+1809',iso: 'DO', fmt: '809-555-1234' },
    { name: 'Uruguay',             flag: '🇺🇾', code: '+598', iso: 'UY', fmt: '91 234 567' },
    { name: 'Venezuela',           flag: '🇻🇪', code: '+58',  iso: 'VE', fmt: '412 123 4567' },
    // España y USA
    { name: 'España',              flag: '🇪🇸', code: '+34',  iso: 'ES', fmt: '612 345 678' },
    { name: 'Estados Unidos',      flag: '🇺🇸', code: '+1',   iso: 'US', fmt: '555 123-4567' },
    { name: 'Canadá',              flag: '🇨🇦', code: '+1',   iso: 'CA', fmt: '604 123-4567' },
    // Resto del mundo
    { name: 'Alemania',            flag: '🇩🇪', code: '+49',  iso: 'DE', fmt: '151 23456789' },
    { name: 'Francia',             flag: '🇫🇷', code: '+33',  iso: 'FR', fmt: '6 12 34 56 78' },
    { name: 'Italia',              flag: '🇮🇹', code: '+39',  iso: 'IT', fmt: '312 345 6789' },
    { name: 'Portugal',            flag: '🇵🇹', code: '+351', iso: 'PT', fmt: '912 345 678' },
    { name: 'Reino Unido',         flag: '🇬🇧', code: '+44',  iso: 'GB', fmt: '7911 123456' },
  ];

  const DEFAULT_ISO = 'PY'; // Paraguay por defecto

  /**
   * Normaliza el número quitando todo excepto dígitos
   */
  function digitsOnly(s) {
    return s.replace(/\D/g, '');
  }

  /**
   * Construye número E.164 combinando prefijo + número local
   */
  function buildE164(code, local) {
    const d = digitsOnly(local);
    if (!d) return '';
    // Quita el cero inicial si lo pusieron (ej: 0981... → 981...)
    const clean = d.replace(/^0+/, '');
    return code + clean;
  }

  /**
   * Valida longitud mínima de número (al menos 6 dígitos locales)
   */
  function isValid(e164) {
    const digits = digitsOnly(e164);
    return digits.length >= 7 && digits.length <= 15;
  }

  /**
   * Parsea un número existente (ej "+595981234567") al abrir un form de edición
   * Retorna { iso, local }
   */
  function parseExisting(fullNumber) {
    if (!fullNumber) return { iso: DEFAULT_ISO, local: '' };
    // Busca el prefijo más largo que coincida
    const num = fullNumber.startsWith('+') ? fullNumber : '+' + fullNumber;
    // Ordenar por longitud de código desc para priorizar códigos más largos
    const sorted = [...COUNTRIES].sort((a,b) => b.code.length - a.code.length);
    for (const c of sorted) {
      if (num.startsWith(c.code)) {
        return { iso: c.iso, local: num.slice(c.code.length) };
      }
    }
    return { iso: DEFAULT_ISO, local: digitsOnly(fullNumber) };
  }

  /**
   * Genera el HTML del componente e inicializa la lógica
   * @param {string} containerId  - ID del div contenedor
   * @param {string} inputName    - name del hidden input que almacena E.164
   * @param {object} opts         - { currentValue, placeholder, theme }
   *   theme: 'crm' (bg-slate-50 / Tailwind) | 'landing' (bg-gray-100 / Tailwind) | 'plain' (sin clases Tailwind)
   */
  function render(containerId, inputName, opts = {}) {
    const container = document.getElementById(containerId);
    if (!container) { console.error('PhonePicker: container not found:', containerId); return; }

    const theme = opts.theme || 'crm';
    const existing = parseExisting(opts.currentValue || '');
    let selectedIso = existing.iso;

    // Clases según tema
    const wrapClass = theme === 'landing'
      ? 'w-full bg-gray-100 border border-gray-200 rounded-lg overflow-hidden flex focus-within:ring-2 focus-within:ring-blue-900/20'
      : 'w-full bg-slate-50 border border-slate-200 rounded-2xl overflow-hidden flex focus-within:ring-4 focus-within:ring-indigo-500/10 focus-within:border-indigo-500 transition-all';

    const flagBtnClass = theme === 'landing'
      ? 'flex items-center gap-1.5 px-3 border-r border-gray-200 bg-gray-100 cursor-pointer hover:bg-gray-200 transition-colors text-sm whitespace-nowrap'
      : 'flex items-center gap-1.5 px-4 border-r border-slate-200 bg-slate-100 cursor-pointer hover:bg-slate-200 transition-colors text-sm whitespace-nowrap';

    const numberClass = theme === 'landing'
      ? 'flex-1 px-3 py-3 bg-transparent outline-none text-sm'
      : 'flex-1 px-4 py-4 bg-transparent outline-none text-sm';

    const dropdownClass = 'absolute z-[9999] bg-white border border-slate-200 rounded-2xl shadow-2xl mt-1 w-72 max-h-72 overflow-y-auto hidden';

    const uid = containerId; // único por instancia

    container.innerHTML = `
      <div class="relative">
        <div class="${wrapClass}">
          <button type="button" id="${uid}-flag-btn" class="${flagBtnClass}" aria-haspopup="listbox" aria-expanded="false">
            <span id="${uid}-flag" class="text-xl leading-none"></span>
            <span id="${uid}-code" class="font-mono font-bold text-slate-600 text-xs"></span>
            <svg class="w-3 h-3 text-slate-400 ml-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
          </button>
          <input
            type="tel"
            id="${uid}-local"
            class="${numberClass}"
            placeholder="${opts.placeholder || 'Número local'}"
            autocomplete="tel-national"
            inputmode="tel"
          >
        </div>

        <!-- Dropdown de países -->
        <div id="${uid}-dropdown" class="${dropdownClass}" role="listbox">
          <div class="p-2 sticky top-0 bg-white border-b border-slate-100">
            <input
              type="text"
              id="${uid}-search"
              placeholder="Buscar país..."
              class="w-full text-sm px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:border-indigo-400"
              autocomplete="off"
            >
          </div>
          <ul id="${uid}-list" class="py-1"></ul>
        </div>

        <!-- Hidden input con valor E.164 final -->
        <input type="hidden" name="${inputName}" id="${uid}-value">
      </div>
      <!-- Feedback de validación -->
      <p id="${uid}-hint" class="text-xs mt-1.5 hidden"></p>
    `;

    // Referencias
    const flagBtn   = document.getElementById(`${uid}-flag-btn`);
    const flagSpan  = document.getElementById(`${uid}-flag`);
    const codeSpan  = document.getElementById(`${uid}-code`);
    const dropdown  = document.getElementById(`${uid}-dropdown`);
    const searchInp = document.getElementById(`${uid}-search`);
    const listEl    = document.getElementById(`${uid}-list`);
    const localInp  = document.getElementById(`${uid}-local`);
    const hiddenInp = document.getElementById(`${uid}-value`);
    const hint      = document.getElementById(`${uid}-hint`);

    function getSelected() {
      return COUNTRIES.find(c => c.iso === selectedIso) || COUNTRIES.find(c => c.iso === DEFAULT_ISO);
    }

    function updateFlag() {
      const c = getSelected();
      flagSpan.textContent = c.flag;
      codeSpan.textContent = c.code;
      localInp.placeholder = c.fmt;
    }

    function renderList(filter = '') {
      const q = filter.toLowerCase();
      const filtered = COUNTRIES.filter(c =>
        c.name.toLowerCase().includes(q) || c.code.includes(q) || c.iso.toLowerCase().includes(q)
      );
      listEl.innerHTML = filtered.map(c => `
        <li
          data-iso="${c.iso}"
          role="option"
          class="flex items-center gap-3 px-4 py-2.5 cursor-pointer hover:bg-indigo-50 transition-colors text-sm ${c.iso === selectedIso ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700'}"
        >
          <span class="text-xl leading-none w-6 text-center">${c.flag}</span>
          <span class="flex-1 truncate">${c.name}</span>
          <span class="font-mono text-xs text-slate-400">${c.code}</span>
        </li>
      `).join('');

      // Scroll al seleccionado
      const active = listEl.querySelector(`[data-iso="${selectedIso}"]`);
      if (active) active.scrollIntoView({ block: 'nearest' });
    }

    function updateValue() {
      const local = localInp.value.trim();
      const c = getSelected();
      const e164 = local ? buildE164(c.code, local) : '';
      hiddenInp.value = e164;

      // Feedback visual
      if (local.length > 0) {
        if (isValid(e164)) {
          hint.textContent = '✅ ' + e164;
          hint.className = 'text-xs mt-1.5 text-emerald-600 font-mono';
          hint.classList.remove('hidden');
          localInp.style.color = '';
        } else {
          hint.textContent = '⚠️ Número muy corto. Ej: ' + c.fmt;
          hint.className = 'text-xs mt-1.5 text-amber-600';
          hint.classList.remove('hidden');
        }
      } else {
        hint.classList.add('hidden');
        hiddenInp.value = '';
      }
    }

    function openDropdown() {
      renderList('');
      dropdown.classList.remove('hidden');
      searchInp.value = '';
      searchInp.focus();
      flagBtn.setAttribute('aria-expanded', 'true');
    }

    function closeDropdown() {
      dropdown.classList.add('hidden');
      flagBtn.setAttribute('aria-expanded', 'false');
    }

    // Toggle dropdown
    flagBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      dropdown.classList.contains('hidden') ? openDropdown() : closeDropdown();
    });

    // Cerrar al hacer clic afuera
    document.addEventListener('click', (e) => {
      if (!container.contains(e.target)) closeDropdown();
    });

    // Filtro de búsqueda
    searchInp.addEventListener('input', () => renderList(searchInp.value));

    // Selección de país
    listEl.addEventListener('click', (e) => {
      const li = e.target.closest('[data-iso]');
      if (!li) return;
      selectedIso = li.dataset.iso;
      updateFlag();
      updateValue();
      closeDropdown();
      localInp.focus();
    });

    // Número local
    localInp.addEventListener('input', updateValue);
    localInp.addEventListener('blur', updateValue);

    // API pública: setear valor programáticamente (para edición de existentes)
    container._pickerSetValue = function(fullNumber) {
      const parsed = parseExisting(fullNumber);
      selectedIso = parsed.iso;
      localInp.value = parsed.local;
      updateFlag();
      updateValue();
    };

    // Inicializar con valor existente
    selectedIso = existing.iso;
    if (existing.local) localInp.value = existing.local;
    updateFlag();
    if (existing.local) updateValue();
  }

  return { render, parseExisting, COUNTRIES };
})();
