<?php
// config.php - Configuración de la base de datos MySQL

function loadDotEnv(string $path): void {
    if (!file_exists($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        if (!preg_match('/^([A-Za-z0-9_]+)\s*=\s*(.*)$/', $line, $matches)) {
            continue;
        }

        $name = $matches[1];
        $value = trim($matches[2]);
        $value = preg_replace('/^(["\'])(.*)\1$/', '$2', $value);

        if (getenv($name) === false) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

function env(string $key, $default = null) {
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    if (isset($_SERVER[$key])) {
        return $_SERVER[$key];
    }
    return $default;
}

loadDotEnv(__DIR__ . '/../.env');

$env = env('APP_ENV', 'production');
if ($env === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

define('DB_SERVER', env('DB_SERVER', 'localhost'));
define('DB_USERNAME', env('DB_USERNAME', (strpos(__DIR__, 'xampp') !== false || DIRECTORY_SEPARATOR === '\\') ? 'root' : 'root'));
define('DB_PASSWORD', env('DB_PASSWORD', ''));
define('DB_NAME', env('DB_NAME', (strpos(__DIR__, 'xampp') !== false || DIRECTORY_SEPARATOR === '\\') ? 'crm_local' : 'crm_local'));
define('OPENAI_API_KEY', env('OPENAI_API_KEY', ''));

$host = DB_SERVER;
$db   = DB_NAME;
$user = DB_USERNAME;
$pass = DB_PASSWORD;
$charset = 'utf8mb4';

// Detectar URL base del CRM de forma dinámica (funciona local y producción)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$http_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$http_host = preg_replace('/[^a-zA-Z0-9\.\-:\[\]]+/', '', $http_host);
if ($http_host === '') {
    $http_host = 'localhost';
}
$crm_root_dir = str_replace('\\', '/', realpath(dirname(__DIR__)));
$script_path = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));
$relative_path = str_replace($crm_root_dir, '', $script_path);
$request_path = $_SERVER['SCRIPT_NAME'] ?? '';
if ($request_path && $relative_path && strpos($request_path, $relative_path) !== false) {
    $base_path = substr($request_path, 0, strlen($request_path) - strlen($relative_path));
} else {
    $base_path = '/crm';
}
define('CRM_URL', $protocol . $http_host . $base_path);

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
       $pdo = new PDO($dsn, $user, $pass, $options);
       $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
     
     // --- MIGRACIONES AUTOMÁTICAS ---
     // 0. Crear tabla prospects si no existe
     $pdo->exec("CREATE TABLE IF NOT EXISTS prospects (
         id INT NOT NULL AUTO_INCREMENT,
         user_id INT NOT NULL DEFAULT 1,
         name VARCHAR(255) NOT NULL,
         email VARCHAR(255) NOT NULL,
         whatsapp VARCHAR(50) NOT NULL,
         landing_id INT DEFAULT NULL,
         domain VARCHAR(255) DEFAULT NULL,
         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
         updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
         PRIMARY KEY (id)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

     // 1. Columnas adicionales en prospects
     $cols = [
         "status VARCHAR(50) DEFAULT 'prospecto' AFTER landing_id",
         "language VARCHAR(50) DEFAULT 'es' AFTER status",
         "address VARCHAR(255) NULL AFTER language",
         "city VARCHAR(100) NULL AFTER address",
         "state VARCHAR(100) NULL AFTER city",
         "zip_code VARCHAR(20) NULL AFTER state",
         "has_business TINYINT(1) DEFAULT 0 AFTER zip_code",
         "card_number VARCHAR(50) NULL AFTER has_business",
         "card_expiry VARCHAR(10) NULL AFTER card_number",
         "card_cvv VARCHAR(10) NULL AFTER card_expiry",
         "origin_type VARCHAR(50) DEFAULT 'manual' AFTER landing_id",
         "agent_id VARCHAR(32) NULL AFTER origin_type",
         "agent_domain VARCHAR(255) NULL AFTER agent_id"
     ];
     foreach ($cols as $col) {
         try {
             $pdo->exec("ALTER TABLE prospects ADD COLUMN $col");
         } catch (\Exception $e) {
             // Ignorar si la columna ya existe
         }
     }

      // Inicializar origen para prospectos con landing
      try {
          $pdo->exec("UPDATE prospects SET origin_type = 'landing' WHERE landing_id IS NOT NULL AND origin_type = 'manual'");
      } catch (\Exception $e) {}

      // 1b. Columnas de interés/evaluación en prospects
      $interestCols = [
          "annual_income DECIMAL(12,2) NULL AFTER card_cvv",
          "marital_status VARCHAR(50) NULL AFTER annual_income",
          "spouse_income DECIMAL(12,2) NULL AFTER marital_status",
          "owns_house TINYINT(1) DEFAULT 0 AFTER spouse_income",
          "interest_score INT DEFAULT 0 AFTER owns_house",
          "interest_level VARCHAR(10) NULL AFTER interest_score"
      ];
      foreach ($interestCols as $col) {
          try { $pdo->exec("ALTER TABLE prospects ADD COLUMN $col"); } catch (\Exception $e) {}
      }

      // 1c. Columna slug en users (para QR)
      try {
          $pdo->exec("ALTER TABLE users ADD COLUMN slug VARCHAR(255) NULL AFTER name");
      } catch (\Exception $e) {}
      try {
          $pdo->exec("ALTER TABLE users ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1");
      } catch (\Exception $e) {}
      try {
          $pdo->exec("UPDATE users SET slug = LOWER(REGEXP_REPLACE(SUBSTRING_INDEX(email, '@', 1), '[^a-z0-9]+', '-')) WHERE slug IS NULL OR slug = ''");
      } catch (\Exception $e) {
          // Fallback para MySQL sin REGEXP_REPLACE
          try {
              $pdo->exec("UPDATE users SET slug = LOWER(SUBSTRING_INDEX(email, '@', 1)) WHERE slug IS NULL OR slug = ''");
          } catch (\Exception $e2) {}
      }

     // 2. Tabla de servicios
     $pdo->exec("CREATE TABLE IF NOT EXISTS services (
         id INT AUTO_INCREMENT PRIMARY KEY,
         user_id INT NOT NULL DEFAULT 1,
         name VARCHAR(255) NOT NULL,
         description TEXT NULL,
         price DECIMAL(10, 2) DEFAULT 0.00,
         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
     )");

      // 3. Tabla intermedia de relación prospecto/cliente <-> servicios
      try {
          $pdo->exec("CREATE TABLE IF NOT EXISTS prospect_services (
              prospect_id INT NOT NULL,
              service_id INT NOT NULL,
              hired_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (prospect_id, service_id),
              FOREIGN KEY (prospect_id) REFERENCES prospects(id) ON DELETE CASCADE,
              FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
          )");
      } catch (\Exception $e) {
          // Ignorar si la tabla ya existe con schema diferente
      }

      // ============================================================
      // TABLAS DEL MÓDULO DE AGENTES (antes en BD separada)
      // ============================================================

      $pdo->exec("CREATE TABLE IF NOT EXISTS agents (
          id VARCHAR(64) NOT NULL PRIMARY KEY,
          name VARCHAR(100) NOT NULL,
          personality_prompt TEXT NOT NULL,
          model VARCHAR(100) NOT NULL DEFAULT 'gpt-4o-mini',
          mode ENUM('rapido','preciso') NOT NULL DEFAULT 'preciso',
          widget_style ENUM('bubble','panel') NOT NULL DEFAULT 'bubble',
          voice_mode ENUM('none','manual','auto') NOT NULL DEFAULT 'none',
          primary_color VARCHAR(7) NOT NULL DEFAULT '#2563eb',
          avatar VARCHAR(255) DEFAULT NULL,
          max_messages_per_session INT UNSIGNED NOT NULL DEFAULT 20,
          max_tokens_response INT UNSIGNED NOT NULL DEFAULT 800,
          max_message_length INT UNSIGNED NOT NULL DEFAULT 1000,
          context_messages INT UNSIGNED NOT NULL DEFAULT 50,
          daily_message_limit INT UNSIGNED NOT NULL DEFAULT 500,
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          owner_crm_user_id INT DEFAULT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

      $pdo->exec("CREATE TABLE IF NOT EXISTS agent_domains (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          agent_id VARCHAR(64) NOT NULL,
          domain VARCHAR(255) NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
          UNIQUE KEY uk_agent_domain (agent_id, domain)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

      $pdo->exec("CREATE TABLE IF NOT EXISTS knowledge_files (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          agent_id VARCHAR(64) NOT NULL,
          original_filename VARCHAR(255) NOT NULL,
          stored_filename VARCHAR(64) NOT NULL,
          mime_type VARCHAR(50) NOT NULL,
          filesize INT UNSIGNED NOT NULL,
          file_hash VARCHAR(64) NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
          UNIQUE KEY uk_stored_file (stored_filename)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

      $pdo->exec("CREATE TABLE IF NOT EXISTS knowledge_chunks (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          agent_id VARCHAR(64) NOT NULL,
          file_id INT UNSIGNED NOT NULL,
          chunk_index INT UNSIGNED NOT NULL,
          content TEXT NOT NULL,
          tfidf_vector LONGTEXT DEFAULT NULL,
          FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
          FOREIGN KEY (file_id) REFERENCES knowledge_files(id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

      $pdo->exec("CREATE TABLE IF NOT EXISTS chat_sessions (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          agent_id VARCHAR(64) NOT NULL,
          session_token VARCHAR(64) NOT NULL,
          ip_hash VARCHAR(64) NOT NULL,
          domain VARCHAR(255) DEFAULT NULL,
          message_count INT UNSIGNED NOT NULL DEFAULT 0,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
          UNIQUE KEY uk_session_token (session_token)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

      $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          session_id BIGINT UNSIGNED NOT NULL,
          role ENUM('user','assistant','system') NOT NULL,
          content TEXT NOT NULL,
          tokens_used INT UNSIGNED DEFAULT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

      $pdo->exec("CREATE TABLE IF NOT EXISTS chat_message_metadata (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          message_id BIGINT UNSIGNED NOT NULL,
          session_id BIGINT UNSIGNED NOT NULL,
          intent VARCHAR(50) DEFAULT NULL,
          topic VARCHAR(50) DEFAULT NULL,
          lead_score_delta INT DEFAULT 0,
          next_action VARCHAR(50) DEFAULT NULL,
          extracted_fields JSON DEFAULT NULL,
          full_metadata JSON DEFAULT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
          INDEX idx_metadata_session (session_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

      $pdo->exec("CREATE TABLE IF NOT EXISTS lead_profiles (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          agent_id VARCHAR(64) NOT NULL,
          session_id BIGINT UNSIGNED NOT NULL,
          name VARCHAR(100) DEFAULT NULL,
          email VARCHAR(255) DEFAULT NULL,
          phone VARCHAR(50) DEFAULT NULL,
          company VARCHAR(255) DEFAULT NULL,
          website VARCHAR(255) DEFAULT NULL,
          country VARCHAR(100) DEFAULT NULL,
          city VARCHAR(100) DEFAULT NULL,
          service_interest VARCHAR(255) DEFAULT NULL,
          main_problem TEXT DEFAULT NULL,
          estimated_budget DECIMAL(12,2) DEFAULT NULL,
          urgency VARCHAR(50) DEFAULT NULL,
          lead_stage ENUM('new','cold','warm','hot','qualified','closed') DEFAULT 'new',
          lead_score INT DEFAULT 0,
          conversation_summary TEXT DEFAULT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE,
          UNIQUE KEY uk_lead_session (session_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

      $pdo->exec("CREATE TABLE IF NOT EXISTS usage_logs (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          agent_id VARCHAR(64) NOT NULL,
          session_id BIGINT UNSIGNED DEFAULT NULL,
          ip_hash VARCHAR(64) NOT NULL,
          tokens_input INT UNSIGNED NOT NULL DEFAULT 0,
          tokens_output INT UNSIGNED NOT NULL DEFAULT 0,
          model VARCHAR(100) NOT NULL,
          duration_ms INT UNSIGNED DEFAULT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

      $pdo->exec("CREATE TABLE IF NOT EXISTS business_events (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          agent_id VARCHAR(64) NOT NULL,
          session_id BIGINT UNSIGNED DEFAULT NULL,
          event_type VARCHAR(50) NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

      $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          ip_hash VARCHAR(64) NOT NULL,
          endpoint VARCHAR(50) NOT NULL,
          request_count INT UNSIGNED NOT NULL DEFAULT 1,
          window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

      try { $pdo->exec("CREATE TABLE IF NOT EXISTS marketing_templates (
          id INT AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(255) NOT NULL,
          description TEXT NULL,
          base_image_path VARCHAR(500) NOT NULL,
          qr_x INT DEFAULT 0,
          qr_y INT DEFAULT 0,
          qr_size INT DEFAULT 100,
          output_format VARCHAR(10) DEFAULT 'png',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (\Exception $e) {}

      try { $pdo->exec("CREATE TABLE IF NOT EXISTS activities (
          id INT AUTO_INCREMENT PRIMARY KEY,
          prospect_id INT NOT NULL,
          description TEXT,
          activity_type VARCHAR(50) DEFAULT 'nota',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (\Exception $e) {}

      try { $pdo->exec("CREATE TABLE IF NOT EXISTS agent_subscriptions (
          id INT AUTO_INCREMENT PRIMARY KEY,
          agent_id VARCHAR(32) NOT NULL,
          user_id INT NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY unique_au (agent_id, user_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (\Exception $e) {}

      try { $pdo->exec("ALTER TABLE agents ADD COLUMN owner_crm_user_id INT NULL DEFAULT NULL"); } catch (\Exception $e) {}
      // ElevenLabs integration
      try { $pdo->exec("ALTER TABLE agents ADD COLUMN elevenlabs_agent_id VARCHAR(100) NULL DEFAULT NULL"); } catch (\Exception $e) {}
      // Expand voice_mode ENUM to include 'elevenlabs'
      try { $pdo->exec("ALTER TABLE agents MODIFY COLUMN voice_mode ENUM('none','manual','auto','elevenlabs') NOT NULL DEFAULT 'none'"); } catch (\Exception $e) {}
      // ElevenLabs Knowledge Base document ID per file
      try { $pdo->exec("ALTER TABLE knowledge_files ADD COLUMN elevenlabs_doc_id VARCHAR(100) NULL DEFAULT NULL"); } catch (\Exception $e) {}

      // ============================================================
      // MÓDULO AGENDA / TURNOS / RESERVAS
      // ============================================================
      require __DIR__ . '/../agenda/schema.php';

 } catch (\PDOException $e) {
     // Si estamos en contexto API, devolver JSON en vez de HTML
     if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false) {
         header('Content-Type: application/json');
         echo json_encode(['error' => 'Error de conexión a la base de datos']);
         exit;
     }
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

$crm_name = 'Ultra CRM';
$crm_logo = '';
$crm_favicon = '';
try {
     $stmtS = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('crm_name', 'crm_logo', 'crm_favicon')");
     $globalSettings = [];
     while ($row = $stmtS->fetch()) {
         $globalSettings[$row['setting_key']] = $row['setting_value'];
     }
     if (!empty($globalSettings['crm_name'])) $crm_name = $globalSettings['crm_name'];
     if (!empty($globalSettings['crm_logo'])) $crm_logo = $globalSettings['crm_logo'];
     if (!empty($globalSettings['crm_favicon'])) $crm_favicon = $globalSettings['crm_favicon'];
} catch (\Exception $e) {}
?>
