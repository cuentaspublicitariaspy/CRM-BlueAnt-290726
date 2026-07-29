<?php
// Configuración opcional para capturar datos de la landing
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan Fiscal Blindado: Ahorra Miles con Liz Aranda</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <script src="lib/phone-picker.js"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            dark: '#0f172a', 
                            primary: '#1e40af', 
                            gold: '#d97706', 
                            light: '#f8fafc',
                        }
                    },
                    fontFamily: {
                        sans: ['"Open Sans"', 'sans-serif'],
                        heading: ['"Montserrat"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style type="text/css">
        .gold-gradient {
            background: linear-gradient(90deg, #d97706 0%, #fbbf24 50%, #d97706 100%);
        }
        .hero-pattern {
            background-color: #0f172a;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%231e293b' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .modal-overlay { background-color: rgba(15, 23, 42, 0.9); backdrop-filter: blur(4px); }
        @keyframes modalIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .modal-content { animation: modalIn 0.3s ease-out forwards; }
        .vsl-overlay { background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(2px); cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: all 0.3s ease; }
        .unmute-badge { background: #d97706; animation: pulse-gold 2s infinite; }
        @keyframes pulse-gold { 0% { box-shadow: 0 0 0 0 rgba(217, 119, 6, 0.7); } 70% { box-shadow: 0 0 0 15px rgba(217, 119, 6, 0); } 100% { box-shadow: 0 0 0 0 rgba(217, 119, 6, 0); } }
    </style>
</head>
<body class="font-sans text-gray-800 antialiased bg-gray-50">

    <!-- Top Bar -->
    <div class="bg-brand-dark text-white text-center py-2 px-4 text-xs md:text-sm font-semibold border-b border-brand-gold">
        <i class="fas fa-shield-alt text-brand-gold mr-2"></i> ESTRATEGIAS LEGALES RESPALDADAS POR EL IRS
    </div>

    <!-- Hero Section -->
    <section class="hero-pattern text-white pt-12 pb-24 border-b-4 border-brand-gold relative">
        <div class="container mx-auto px-4 max-w-5xl text-center">
            <div class="flex justify-center mb-8">
                <span class="text-xl font-heading font-bold tracking-widest uppercase text-brand-gold">Tax Planning Masters</span>
            </div>
            <h1 class="text-2xl md:text-4xl lg:text-5xl font-heading font-extrabold leading-tight mb-6 px-4">
                ¿Qué tal si pudieras ahorrar <span class="text-brand-gold">legalmente miles de dólares en impuestos cada año</span>?
            </h1>
            <p class="text-lg md:text-xl text-gray-300 font-light max-w-3xl mx-auto mb-12">
                Diseñamos estrategias fiscales avanzadas para ayudarte a recuperar la <span class="text-brand-gold font-bold">tranquilidad</span>.
            </p>

            <!-- Video Container -->
            <div class="max-w-4xl mx-auto rounded-2xl overflow-hidden shadow-2xl border border-gray-700 bg-brand-dark p-2 mb-10 relative">
                <div class="aspect-video bg-black flex items-center justify-center relative rounded-xl overflow-hidden">
                    <video id="vslVideo" class="w-full h-full object-cover" playsinline autoplay muted loop>
                        <source src="https://taxplanningmasters.com/videos/VSLTaxPlanningClients.mp4" type="video/mp4">
                    </video>
                    <div id="unmuteOverlay" onclick="handleVSLClick()" class="vsl-overlay absolute inset-0 z-20">
                        <div class="unmute-badge flex items-center gap-3 px-6 py-3 rounded-full text-white font-bold text-lg shadow-2xl border-2 border-white/20 transition hover:scale-105">
                            <i class="fas fa-volume-up text-2xl"></i>
                            <span>Toca para activar el sonido</span>
                        </div>
                    </div>
                </div>
            </div>

            <button onclick="toggleModal()" class="gold-gradient text-white font-heading font-extrabold py-5 px-12 rounded-full shadow-2xl hover:scale-105 transition transform text-xl uppercase tracking-widest inline-flex items-center">
                QUIERO MI ANÁLISIS DE AHORRO <i class="fas fa-chevron-right ml-3 animate-pulse"></i>
            </button>
        </div>
    </section>

    <!-- MODAL FORM INTEGRADO CON CRM -->
    <div id="formModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div onclick="toggleModal()" class="fixed inset-0 transition-opacity modal-overlay"></div>
            <div class="inline-block bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-lg sm:w-full modal-content z-50 p-8">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-2xl font-heading font-bold text-brand-dark">Tu Análisis de Ahorro</h3>
                    <button onclick="toggleModal()" class="text-gray-400 hover:text-brand-gold"><i class="fas fa-times text-2xl"></i></button>
                </div>
                
                <!-- Formulario conectado al CRM -->
                <form id="crmForm" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nombre Completo</label>
                        <input type="text" name="name" required class="w-full bg-gray-100 border border-gray-200 rounded-lg p-3 outline-none focus:ring-2 focus:ring-brand-primary/20">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Email Corporativo</label>
                        <input type="email" name="email" required class="w-full bg-gray-100 border border-gray-200 rounded-lg p-3 outline-none focus:ring-2 focus:ring-brand-primary/20">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">WhatsApp</label>
                        <div id="landing-phone-picker"></div>
                    </div>
                    <button type="submit" id="submitBtn" class="gold-gradient w-full text-white font-bold py-4 rounded-lg shadow-lg hover:opacity-90 transition">
                        ENVIAR SOLICITUD
                    </button>
                </form>
                <div id="successMsg" class="hidden text-center py-4">
                    <i class="fas fa-check-circle text-green-500 text-5xl mb-4"></i>
                    <h4 class="text-xl font-bold">¡Solicitud Enviada!</h4>
                    <p class="text-gray-600 mt-2">Un especialista te contactará pronto.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('formModal');
        const video = document.getElementById('vslVideo');
        const unmuteOverlay = document.getElementById('unmuteOverlay');
        
        function toggleModal() {
            modal.classList.toggle('hidden');
        }

        function handleVSLClick() {
            video.muted = false;
            video.currentTime = 0;
            video.controls = true;
            video.play();
            unmuteOverlay.style.display = 'none';
        }

        // Lógica de envío al CRM
        document.getElementById('crmForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerText = 'Enviando...';

            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());
            // Validar que el número esté completo
            if (!data.whatsapp || data.whatsapp.replace(/\D/g,'').length < 7) {
                alert('Por favor ingresá un número de WhatsApp válido con prefijo de país.');
                btn.disabled = false;
                btn.innerText = 'ENVIAR SOLICITUD';
                return;
            }

            try {
                const response = await fetch('api/prospects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                if (response.ok) {
                    document.getElementById('crmForm').classList.add('hidden');
                    document.getElementById('successMsg').classList.remove('hidden');
                    setTimeout(() => { toggleModal(); }, 3000);
                } else {
                    alert('Error al enviar. Inténtalo de nuevo.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Hubo un problema con la conexión.');
            } finally {
                btn.disabled = false;
                btn.innerText = 'ENVIAR SOLICITUD';
            }
        });

        // Inicializar PhonePicker en la landing (si está disponible)
        if (typeof PhonePicker !== 'undefined') {
            PhonePicker.render('landing-phone-picker', 'whatsapp', { theme: 'landing', placeholder: 'Número local' });
        } else {
            document.getElementById('landing-phone-picker').innerHTML =
                '<input type="text" name="whatsapp" required placeholder="WhatsApp con código de país (Ej: +595981234567)" ' +
                'class="w-full bg-gray-100 border border-gray-200 rounded-lg p-3 outline-none focus:ring-2 focus:ring-blue-900/20">';
        }
    </script>
</body>
</html>
