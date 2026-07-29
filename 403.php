<?php
// Forzar código de estado 403 para evitar que navegadores o proxies bloqueen la vista por errores 429/500
http_response_code(403);

// Capturar IP real considerando proxies inversos (Cloudflare, Load Balancers de Hostinger, etc.)
$user_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'IP Desconocida';
if (strpos($user_ip, ',') !== false) {
    $ips = explode(',', $user_ip);
    $user_ip = trim($ips[0]);
}

$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Navegador Desconocido';
$request_time = date('Y-m-d H:i:s');
// En Apache ErrorDocument, la URL original solicitada está en REDIRECT_URL
$request_uri = $_SERVER['REDIRECT_URL'] ?? $_SERVER['REQUEST_URI'] ?? '/crm/.env';

// Detección de País mediante Cloudflare (sin peticiones externas en PHP para evitar HTTP 429)
$country_code = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '';

// Mapeo básico de códigos comunes de habla hispana y otros
$countries = [
    'AR' => 'Argentina 🇦🇷',
    'ES' => 'España 🇪🇸',
    'MX' => 'México 🇲🇽',
    'CO' => 'Colombia 🇨🇴',
    'CL' => 'Chile 🇨🇱',
    'PE' => 'Perú 🇵🇪',
    'VE' => 'Venezuela 🇻🇪',
    'EC' => 'Ecuador 🇪🇨',
    'GT' => 'Guatemala 🇬🇹',
    'CU' => 'Cuba 🇨🇺',
    'BO' => 'Bolivia 🇧🇴',
    'DO' => 'República Dominicana 🇩🇴',
    'HN' => 'Honduras 🇭🇳',
    'PY' => 'Paraguay 🇵🇾',
    'SV' => 'El Salvador 🇸🇻',
    'NI' => 'Nicaragua 🇳🇮',
    'CR' => 'Costa Rica 🇨🇷',
    'PA' => 'Panamá 🇵🇦',
    'UY' => 'Uruguay 🇺🇾',
    'US' => 'Estados Unidos 🇺🇸',
    'BR' => 'Brasil 🇧🇷',
    'CA' => 'Canadá 🇨🇦',
];

if (isset($countries[$country_code])) {
    $country = $countries[$country_code];
} elseif ($country_code) {
    $country = $country_code;
} else {
    $country = "Buscando..."; // Se resolverá de forma limpia por JS en el cliente
}

// Detección avanzada de Sistema Operativo en PHP
$os = "Desconocido";
if (preg_match('/windows nt 10/i', $user_agent)) $os = "Windows 10 / 11";
elseif (preg_match('/windows nt 6\.3/i', $user_agent)) $os = "Windows 8.1";
elseif (preg_match('/windows nt 6\.2/i', $user_agent)) $os = "Windows 8";
elseif (preg_match('/windows nt 6\.1/i', $user_agent)) $os = "Windows 7";
elseif (preg_match('/windows/i', $user_agent)) $os = "Windows";
elseif (preg_match('/iphone/i', $user_agent)) $os = "iOS (iPhone)";
elseif (preg_match('/ipad/i', $user_agent)) $os = "iOS (iPad)";
elseif (preg_match('/macintosh|mac os x/i', $user_agent)) $os = "macOS";
elseif (preg_match('/android/i', $user_agent)) $os = "Android";
elseif (preg_match('/linux/i', $user_agent)) $os = "Linux";
elseif (preg_match('/cros/i', $user_agent)) $os = "ChromeOS";

// Detección de Navegador principal
$browser = "Desconocido";
if (preg_match('/edg/i', $user_agent)) $browser = "Microsoft Edge";
elseif (preg_match('/chrome|crios/i', $user_agent)) $browser = "Google Chrome";
elseif (preg_match('/firefox|fxios/i', $user_agent)) $browser = "Mozilla Firefox";
elseif (preg_match('/safari/i', $user_agent) && !preg_match('/chrome|crios/i', $user_agent)) $browser = "Apple Safari";
elseif (preg_match('/opr|opera/i', $user_agent)) $browser = "Opera";
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Restringido - 403</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                        mono: ['Fira Code', 'monospace'],
                    }
                }
            }
        }
    </script>

    <style>
        /* Fondo con malla de puntos sutil */
        .bg-grid-pattern {
            background-size: 30px 30px;
            background-image: radial-gradient(circle, rgba(255,255,255,0.03) 1px, transparent 1px);
        }
        
        /* Animación suave para luces de fondo */
        @keyframes pulse-slow {
            0%, 100% { transform: scale(1) translate(0, 0); opacity: 0.4; }
            50% { transform: scale(1.1) translate(10px, -10px); opacity: 0.6; }
        }
        .animate-pulse-slow {
            animation: pulse-slow 8s infinite ease-in-out;
        }

        /* Cursor parpadeante */
        @keyframes blink { 50% { opacity: 0; } }
        .cursor-blink { animation: blink 1s step-end infinite; }
    </style>
</head>
<body class="bg-[#070913] text-slate-100 min-h-screen flex flex-col items-center justify-center py-16 px-4 md:px-8 pb-40 bg-grid-pattern relative overflow-y-auto selection:bg-purple-500/30 selection:text-purple-200">

    <!-- Efecto de iluminación de fondo (Glows sofisticados) -->
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-purple-600/10 rounded-full blur-[120px] pointer-events-none animate-pulse-slow"></div>
    <div class="absolute top-1/3 left-1/4 w-[300px] h-[300px] bg-blue-600/10 rounded-full blur-[100px] pointer-events-none"></div>

    <div class="max-w-2xl w-full z-10 flex flex-col my-auto">
        
        <!-- Encabezado con Icono Minimalista -->
        <div class="flex flex-col items-center text-center mb-10">
            <div class="relative mb-6 group">
                <!-- Brillo dinámico detrás del icono -->
                <div class="absolute inset-0 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl blur-xl opacity-45 group-hover:opacity-70 transition-opacity duration-300"></div>
                
                <div class="relative bg-slate-950 p-5 rounded-2xl border border-slate-800 text-purple-400 shadow-xl">
                    <!-- SVG candado moderno -->
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                </div>
            </div>
            
            <h1 class="text-4xl font-extrabold tracking-tight bg-gradient-to-r from-white via-slate-100 to-slate-400 bg-clip-text text-transparent">
                Buen intento, pero no.
            </h1>
            <p class="mt-3 text-slate-400 text-base md:text-lg max-w-md font-medium">
                Tu curiosidad es admirable, pero los secretos de este CRM están bajo llave. 🔓
            </p>
        </div>

        <!-- Consola de Diagnóstico de Seguridad -->
        <div class="bg-[#05070f]/90 backdrop-blur-xl border border-slate-800/80 rounded-3xl p-8 shadow-2xl relative overflow-hidden group">
            
            <!-- Pequeña etiqueta de estado en la consola -->
            <div class="absolute top-5 right-5 flex items-center space-x-2 bg-purple-950/40 border border-purple-500/20 px-3 py-1.5 rounded-full">
                <span class="w-2 h-2 rounded-full bg-purple-50 animate-ping"></span>
                <span class="text-[10px] font-mono font-bold text-purple-300 uppercase tracking-wider">Interceptado</span>
            </div>

            <!-- Texto de la consola -->
            <div class="font-mono text-xs md:text-sm text-slate-300 space-y-5 pt-2">
                
                <!-- 1. Contexto de la Intercepción -->
                <div>
                    <p class="text-slate-500 mb-3 text-[11px] uppercase tracking-widest font-semibold">// 1. Contexto de la Intercepción</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pl-4 border-l-2 border-purple-500/40 bg-purple-950/10 py-3 rounded-r-2xl">
                        <p><span class="text-purple-400 font-semibold">EVENTO:</span> <br><span class="text-slate-100 font-medium text-sm md:text-base">403_ACCESO_DENEGADO</span></p>
                        <p><span class="text-purple-400 font-semibold">HORA:</span> <br><span class="text-yellow-400 font-semibold text-sm md:text-base"><?php echo htmlspecialchars($request_time); ?></span></p>
                        <p class="col-span-1 sm:col-span-2 truncate"><span class="text-purple-400 font-semibold">RECURSO:</span> <br><span class="text-red-400 font-medium text-sm md:text-base"><?php echo htmlspecialchars($request_uri); ?></span></p>
                    </div>
                </div>

                <div class="border-t border-slate-800/60 my-5"></div>

                <!-- 2. Origen de la Solicitud -->
                <div>
                    <p class="text-slate-500 mb-3 text-[11px] uppercase tracking-widest font-semibold">// 2. Origen de la Solicitud</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pl-4 border-l-2 border-blue-500/40 bg-blue-950/10 py-3 rounded-r-2xl">
                        <p><span class="text-slate-500">DIRECCIÓN_IP:</span> <br><span class="text-blue-400 font-semibold text-sm md:text-base"><?php echo htmlspecialchars($user_ip); ?></span></p>
                        <p><span class="text-slate-500">PAÍS:</span> <br><span id="display-country" class="text-blue-300 font-semibold text-sm md:text-base"><?php echo htmlspecialchars($country); ?></span></p>
                    </div>
                </div>

                <div class="border-t border-slate-800/60 my-5"></div>

                <!-- 3. Huella del Dispositivo -->
                <div>
                    <p class="text-slate-500 mb-3 text-[11px] uppercase tracking-widest font-semibold">// 3. Huella del Dispositivo</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pl-4 border-l-2 border-emerald-500/40 bg-emerald-950/10 py-3 rounded-r-2xl">
                        <p><span class="text-slate-500">SISTEMA_OPERATIVO:</span> <br><span class="text-emerald-400 font-semibold text-sm md:text-base"><?php echo htmlspecialchars($os); ?></span></p>
                        <p><span class="text-slate-500">NAVEGADOR:</span> <br><span class="text-emerald-300 font-semibold text-sm md:text-base"><?php echo htmlspecialchars($browser); ?></span></p>
                        <p class="col-span-1 sm:col-span-2 text-xs text-slate-500 truncate" title="<?php echo htmlspecialchars($user_agent); ?>"><span class="text-slate-600">USER_AGENT:</span> <?php echo htmlspecialchars($user_agent); ?></p>
                    </div>
                </div>
                
                <div class="border-t border-slate-800/60 my-5"></div>
                
                <p class="text-purple-400/80 font-semibold italic text-xs md:text-sm">
                    > Redirección automática disponible en el botón de abajo...<span class="cursor-blink">_</span>
                </p>
            </div>
        </div>

        <!-- Botón de acción con efecto de luz lateral -->
        <div class="mt-10 flex justify-center">
            <a href="/crm/index.php" class="relative group overflow-hidden w-full sm:w-auto px-10 py-4 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-semibold rounded-2xl text-center shadow-lg shadow-purple-950/20 active:scale-95 transition-all duration-150 flex items-center justify-center gap-3 text-base md:text-lg">
                <!-- Brillo interno en hover -->
                <span class="absolute right-0 w-8 h-32 -mt-12 transition-all duration-1000 transform translate-x-12 bg-white opacity-10 rotate-12 group-hover:-translate-x-40 ease"></span>
                
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                </svg>
                Volver a Zona Segura (Dashboard)
            </a>
        </div>

        <!-- Footer minimalista integrado -->
        <div class="mt-8 text-center text-xs text-slate-600 font-medium">
            La seguridad de tu sistema es primordial. Esta consulta ha sido clasificada y registrada por tu firewall.
        </div>

    </div>

    <!-- Script de geolocalización limpia del lado del cliente (evita error HTTP 429 en servidor) -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const countryEl = document.getElementById("display-country");
            if (countryEl && countryEl.textContent.trim() === "Buscando...") {
                fetch("https://get.geojs.io/v1/ip/geo.json")
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.country) {
                            countryEl.textContent = data.country + (data.country === "Argentina" ? " 🇦🇷" : "");
                        } else {
                            countryEl.textContent = "Desconocido";
                        }
                    })
                    .catch(() => { countryEl.textContent = "Desconocido"; });
            }
        });
    </script>
</body>
</html>