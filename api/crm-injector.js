/**
 * Ultra CRM - Injector Script
 * Tracks views and captures leads
 */
(function() {
    console.log('CRM Injector Active');

    // 1. RASTREO DE VISITA ÚNICA
    async function trackView() {
        const landingId = window.CRM_LANDING_ID;
        if (!landingId) return;

        // Verificar si ya contamos esta visita en esta sesión
        const sessionKey = 'crm_viewed_' + landingId;
        if (sessionStorage.getItem(sessionKey)) return;

        try {
            await fetch('../api/landings_stats.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ landing_id: landingId, action: 'view' })
            });
            sessionStorage.setItem(sessionKey, 'true');
        } catch (err) {
            console.error('CRM Stats Error:', err);
        }
    }

    // 2. CAPTURA DE PROSPECTO
    function setupFormCapture() {
        const btn = document.getElementById('crm-button');
        if (!btn) return;

        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            
            // Buscar campos comunes
            const name = document.querySelector('input[name*="name"], input[placeholder*="Nombre"]') ?.value;
            const email = document.querySelector('input[type="email"], input[name*="email"]') ?.value;
            const whatsapp = document.querySelector('input[type="tel"], input[name*="whatsapp"], input[name*="phone"]') ?.value;

            if (!name || !email || !whatsapp) {
                alert('Por favor, completa todos los campos (Nombre, Email y WhatsApp)');
                return;
            }

            btn.innerText = 'Enviando...';
            btn.disabled = true;

            try {
                const res = await fetch(window.CRM_API_URL || '../api/prospects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        name, email, whatsapp,
                        landing_id: window.CRM_LANDING_ID
                    })
                });

                const data = await res.json();
                if (data.success) {
                    btn.innerText = '¡Enviado!';
                    btn.style.backgroundColor = '#10b981';
                    // Opcional: redirección
                    // window.location.href = 'gracias.html';
                } else {
                    throw new Error(data.error);
                }
            } catch (err) {
                alert('Error al enviar: ' + err.message);
                btn.innerText = 'Reintentar';
                btn.disabled = false;
            }
        });
    }

    // Ejecutar
    if (document.readyState === 'complete') {
        trackView();
        setupFormCapture();
    } else {
        window.addEventListener('load', () => {
            trackView();
            setupFormCapture();
        });
    }
})();
