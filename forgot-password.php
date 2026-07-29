<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Clave - Ultra CRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: radial-gradient(circle at top left, #f1f5f9 0%, #ffffff 100%); }
        .auth-card { background: white; border: 1px solid #e2e8f0; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="auth-card w-full max-w-md rounded-[2.5rem] p-10 md:p-14">
        <div class="flex items-center gap-3 mb-10 justify-center">
            <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center font-bold text-xl text-white shadow-lg shadow-indigo-200">U</div>
            <span class="font-bold text-2xl tracking-tight text-slate-900">Ultra CRM</span>
        </div>
        
        <h1 class="text-3xl font-black text-slate-900 mb-2 text-center">Recuperar Clave</h1>
        <p class="text-slate-400 text-center mb-10 text-sm font-medium">Te enviaremos un correo para restaurar tu acceso</p>

        <form id="forgotForm" class="space-y-6">
            <div class="space-y-2">
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-500 ml-1">Email Registrado</label>
                <div class="relative">
                    <i data-lucide="mail" class="absolute left-5 top-1/2 -translate-y-1/2 text-slate-300 w-5 h-5"></i>
                    <input type="email" name="email" required placeholder="tu@email.com" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-4 pl-14 pr-6 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all text-slate-900">
                </div>
            </div>
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-4 rounded-2xl font-bold shadow-xl shadow-indigo-100 transition-all active:scale-[0.98] mt-6">Enviar Enlace</button>
        </form>

        <p class="text-center mt-10 text-slate-500 text-sm">
            ¿Recordaste tu clave? <a href="login.php" class="font-bold text-indigo-600 hover:text-indigo-700">Inicia Sesión</a>
        </p>
    </div>

    <script>
        document.getElementById('forgotForm').onsubmit = async (e) => {
            e.preventDefault();
            alert('Se ha enviado un enlace a tu correo (Simulado)');
            window.location.href = 'login.php';
        };
        lucide.createIcons();
    </script>
</body>
</html>
