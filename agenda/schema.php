<?php
/**
 * agenda/schema.php
 * Migraciones idempotentes del módulo Agenda/Turnos/Reservas.
 * Se incluye desde api/config.php justo después de que $pdo existe, dentro
 * del mismo try/catch de migraciones del resto del CRM — sigue exactamente
 * el mismo patrón (CREATE TABLE IF NOT EXISTS + ALTER TABLE en try/catch).
 */

// 1. Sucursales
$pdo->exec("CREATE TABLE IF NOT EXISTS agenda_branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'America/Asuncion',
    phone VARCHAR(50) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_agenda_branches_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 2. Recursos reservables (agendas)
$pdo->exec("CREATE TABLE IF NOT EXISTS agenda_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    branch_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    capacity INT NOT NULL DEFAULT 1,
    color VARCHAR(7) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_agenda_resources_user (user_id),
    FOREIGN KEY (branch_id) REFERENCES agenda_branches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Buffers: el recurso (no el servicio) define cuánto tiempo necesita antes/
// después de cualquier turno, porque es una propiedad física del recurso
// (tiempo de limpieza/prep), no del servicio abstracto que se ofrezca.
try { $pdo->exec("ALTER TABLE agenda_resources ADD COLUMN buffer_before_min INT NOT NULL DEFAULT 0 AFTER capacity"); } catch (\Exception $e) {}
try { $pdo->exec("ALTER TABLE agenda_resources ADD COLUMN buffer_after_min INT NOT NULL DEFAULT 0 AFTER buffer_before_min"); } catch (\Exception $e) {}

// Foto del recurso (profesional/sala): igual que users.avatar, se guarda
// solo el nombre de archivo en uploads/ y se arma la URL absoluta al leer.
try { $pdo->exec("ALTER TABLE agenda_resources ADD COLUMN photo VARCHAR(255) NULL AFTER color"); } catch (\Exception $e) {}

// 3. Catálogo de servicios reservables (distinto de `services`, que es el
//    catálogo de servicios contratados por prospects)
$pdo->exec("CREATE TABLE IF NOT EXISTS agenda_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    duration_min INT NOT NULL DEFAULT 30,
    buffer_before_min INT NOT NULL DEFAULT 0,
    buffer_after_min INT NOT NULL DEFAULT 0,
    price DECIMAL(10,2) NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'PYG',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_agenda_services_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 4. Puente recurso <-> servicio
$pdo->exec("CREATE TABLE IF NOT EXISTS agenda_resource_services (
    resource_id INT NOT NULL,
    service_id INT NOT NULL,
    PRIMARY KEY (resource_id, service_id),
    FOREIGN KEY (resource_id) REFERENCES agenda_resources(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES agenda_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 5. Horarios recurrentes
$pdo->exec("CREATE TABLE IF NOT EXISTS agenda_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_id INT NOT NULL,
    weekday TINYINT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    FOREIGN KEY (resource_id) REFERENCES agenda_resources(id) ON DELETE CASCADE,
    INDEX idx_agenda_schedules_resource (resource_id, weekday)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 6. Bloqueos puntuales
$pdo->exec("CREATE TABLE IF NOT EXISTS agenda_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_id INT NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    reason VARCHAR(255) NULL,
    FOREIGN KEY (resource_id) REFERENCES agenda_resources(id) ON DELETE CASCADE,
    INDEX idx_agenda_blocks_resource (resource_id, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 7. Enlaces de reserva pública
$pdo->exec("CREATE TABLE IF NOT EXISTS agenda_booking_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    branch_id INT NULL,
    resource_id INT NULL,
    service_id INT NULL,
    source_channel VARCHAR(50) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_agenda_booking_links_token (token),
    INDEX idx_agenda_booking_links_user (user_id),
    FOREIGN KEY (branch_id) REFERENCES agenda_branches(id) ON DELETE SET NULL,
    FOREIGN KEY (resource_id) REFERENCES agenda_resources(id) ON DELETE SET NULL,
    FOREIGN KEY (service_id) REFERENCES agenda_services(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 8. Reservas
$pdo->exec("CREATE TABLE IF NOT EXISTS agenda_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    branch_id INT NOT NULL,
    resource_id INT NOT NULL,
    service_id INT NOT NULL,
    contact_id INT NULL,
    contact_name VARCHAR(255) NULL,
    contact_phone VARCHAR(50) NULL,
    contact_email VARCHAR(255) NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'held',
    hold_expires_at DATETIME NULL,
    manage_token VARCHAR(64) NOT NULL,
    attendance_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_by VARCHAR(20) NOT NULL DEFAULT 'public',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_agenda_bookings_manage_token (manage_token),
    INDEX idx_agenda_bookings_user (user_id),
    INDEX idx_agenda_bookings_resource_range (resource_id, starts_at, ends_at),
    INDEX idx_agenda_bookings_status (status),
    FOREIGN KEY (branch_id) REFERENCES agenda_branches(id) ON DELETE CASCADE,
    FOREIGN KEY (resource_id) REFERENCES agenda_resources(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES agenda_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// google_event_id: columna de extensión para Google Calendar (fase 2), se
// agrega ya la columna (nullable, sin uso todavía) para no requerir otra
// migración cuando se implemente.
try { $pdo->exec("ALTER TABLE agenda_bookings ADD COLUMN google_event_id VARCHAR(255) NULL AFTER notes"); } catch (\Exception $e) {}

// 9. Auditoría + dedup de recordatorios/notificaciones
$pdo->exec("CREATE TABLE IF NOT EXISTS agenda_booking_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    actor VARCHAR(20) NOT NULL DEFAULT 'system',
    channel VARCHAR(20) NULL,
    meta TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES agenda_bookings(id) ON DELETE CASCADE,
    INDEX idx_agenda_booking_events_booking_type (booking_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 10. Configuración general, 1 fila por negocio
$pdo->exec("CREATE TABLE IF NOT EXISTS agenda_settings (
    user_id INT NOT NULL PRIMARY KEY,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    hold_minutes INT NOT NULL DEFAULT 5,
    min_lead_minutes INT NOT NULL DEFAULT 60,
    reminder_hours_before VARCHAR(100) NOT NULL DEFAULT '24,2',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 11. Catálogo de agentes externos (referentes sin cuenta en el sistema)
$pdo->exec("CREATE TABLE IF NOT EXISTS agenda_external_agents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(255) NULL,
    notes TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_agenda_external_agents_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 12. Reglas de notificación (owner/client/external_agent -> canal)
$pdo->exec("CREATE TABLE IF NOT EXISTS agenda_notification_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    recipient_type VARCHAR(20) NOT NULL,
    channel VARCHAR(20) NOT NULL DEFAULT 'email',
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uk_agenda_notification_rules (user_id, recipient_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 13. Credenciales de Twilio (SMS), encriptadas, 1 fila por negocio
$pdo->exec("CREATE TABLE IF NOT EXISTS agenda_twilio_config (
    user_id INT NOT NULL PRIMARY KEY,
    account_sid VARCHAR(255) NOT NULL,
    auth_token_encrypted TEXT NOT NULL,
    from_number VARCHAR(30) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 14. Credenciales SMTP (email), encriptadas, 1 fila por negocio
$pdo->exec("CREATE TABLE IF NOT EXISTS agenda_smtp_config (
    user_id INT NOT NULL PRIMARY KEY,
    host VARCHAR(255) NOT NULL,
    port INT NOT NULL DEFAULT 587,
    username VARCHAR(255) NOT NULL,
    password_encrypted TEXT NOT NULL,
    from_email VARCHAR(255) NOT NULL,
    from_name VARCHAR(255) NULL,
    encryption VARCHAR(10) NOT NULL DEFAULT 'tls',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 15. Split-case: a qué agente externo pertenece un contacto/lead existente
try { $pdo->exec("ALTER TABLE prospects ADD COLUMN external_agent_id INT NULL DEFAULT NULL AFTER agent_domain"); } catch (\Exception $e) {}
try { $pdo->exec("ALTER TABLE prospects ADD CONSTRAINT fk_prospects_external_agent FOREIGN KEY (external_agent_id) REFERENCES agenda_external_agents(id) ON DELETE SET NULL"); } catch (\Exception $e) {}

// 16. Migración única: los buffers pasaron de agenda_services a
// agenda_resources (son una propiedad física del recurso, no del servicio
// abstracto). Si un recurso sigue en 0/0 y alguno de sus servicios ya tenía
// buffers cargados del modelo anterior, se copian acá. Autolimitada: una
// vez migrado deja de estar en 0/0 (salvo que el valor real fuera 0), así
// no vuelve a pisar un ajuste manual hecho después.
try {
    $pdo->exec("
        UPDATE agenda_resources r
        JOIN (
            SELECT rs.resource_id, MAX(s.buffer_before_min) AS bb, MAX(s.buffer_after_min) AS ba
            FROM agenda_resource_services rs
            JOIN agenda_services s ON s.id = rs.service_id
            GROUP BY rs.resource_id
        ) x ON x.resource_id = r.id
        SET r.buffer_before_min = x.bb, r.buffer_after_min = x.ba
        WHERE r.buffer_before_min = 0 AND r.buffer_after_min = 0 AND (x.bb > 0 OR x.ba > 0)
    ");
} catch (\Exception $e) {}

// 17. Servicio virtual (videollamada) — si está marcado y hay una cuenta
// Zoom configurada, cada reserva de este servicio genera automáticamente
// una reunión (ver agenda/Services/ZoomService.php).
try { $pdo->exec("ALTER TABLE agenda_services ADD COLUMN is_virtual TINYINT(1) NOT NULL DEFAULT 0 AFTER duration_min"); } catch (\Exception $e) {}

// 18. Datos de la reunión Zoom generada para una reserva puntual.
// start_url incluye un token de autenticación para arrancar la reunión como
// host — nunca se expone en los endpoints públicos de reserva, solo en el
// panel de administración.
try { $pdo->exec("ALTER TABLE agenda_bookings ADD COLUMN zoom_meeting_id VARCHAR(50) NULL AFTER google_event_id"); } catch (\Exception $e) {}
try { $pdo->exec("ALTER TABLE agenda_bookings ADD COLUMN zoom_join_url VARCHAR(500) NULL AFTER zoom_meeting_id"); } catch (\Exception $e) {}
try { $pdo->exec("ALTER TABLE agenda_bookings ADD COLUMN zoom_start_url VARCHAR(500) NULL AFTER zoom_join_url"); } catch (\Exception $e) {}

// 19. Conexión de Google Calendar — una por recurso (cada profesional/sala
// conecta su propio calendario). refresh_token encriptado con Crypto, igual
// patrón que las credenciales de Twilio/SMTP.
$pdo->exec("CREATE TABLE IF NOT EXISTS agenda_google_calendar_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_id INT NOT NULL,
    user_id INT NOT NULL,
    calendar_id VARCHAR(255) NOT NULL DEFAULT 'primary',
    google_email VARCHAR(255) NULL,
    access_token_encrypted TEXT NULL,
    refresh_token_encrypted TEXT NOT NULL,
    token_expires_at DATETIME NULL,
    connected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_agenda_google_calendar_resource (resource_id),
    FOREIGN KEY (resource_id) REFERENCES agenda_resources(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 20. Credenciales Zoom (Server-to-Server OAuth), una cuenta por negocio —
// toda reunión de cualquier recurso/servicio virtual sale de esta cuenta.
// host_user_id: email o ID del usuario Zoom (normalmente el dueño de la
// cuenta) bajo el cual se crean las reuniones — una app Server-to-Server
// es a nivel de cuenta, no tiene noción de "usuario actual" ("me" no es
// válido acá como sí lo es en apps OAuth de usuario), así que hace falta
// indicar explícitamente qué usuario Zoom oficia de host.
$pdo->exec("CREATE TABLE IF NOT EXISTS agenda_zoom_config (
    user_id INT NOT NULL PRIMARY KEY,
    account_id VARCHAR(255) NOT NULL,
    client_id VARCHAR(255) NOT NULL,
    client_secret_encrypted TEXT NOT NULL,
    host_user_id VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 21. Overhaul de reglas de notificación: de "una fila por destinatario,
// aplicada igual a cualquier evento y cualquier recurso" a granularidad por
// recurso (resource_id = 0 significa "regla por defecto del negocio, para
// cualquier recurso sin override propio") + tipo de evento (trigger_type),
// con plantilla de mensaje editable (subject_template/body_template, con
// variables {{cliente}} {{servicio}} {{agenda}} {{sucursal}} {{negocio}}
// {{fecha}} {{link}} {{zoom_link}} {{horas}}). Sin plantilla propia, se usa
// la plantilla por defecto del sistema (ver NotificationService::defaultTemplate).
try { $pdo->exec("ALTER TABLE agenda_notification_rules ADD COLUMN resource_id INT NOT NULL DEFAULT 0 AFTER user_id"); } catch (\Exception $e) {}
try { $pdo->exec("ALTER TABLE agenda_notification_rules ADD COLUMN trigger_type VARCHAR(20) NOT NULL DEFAULT 'confirmed' AFTER resource_id"); } catch (\Exception $e) {}
try { $pdo->exec("ALTER TABLE agenda_notification_rules ADD COLUMN subject_template VARCHAR(255) NULL AFTER channel"); } catch (\Exception $e) {}
try { $pdo->exec("ALTER TABLE agenda_notification_rules ADD COLUMN body_template TEXT NULL AFTER subject_template"); } catch (\Exception $e) {}

// La vieja unique key (user_id, recipient_type) no contempla trigger_type
// todavía — hay que sacarla ANTES del backfill de abajo, o los INSERT de
// los 3 triggers nuevos chocan contra ella (mismo user_id+recipient_type
// que la fila 'confirmed' ya existente, aunque el trigger sea distinto).
try { $pdo->exec("ALTER TABLE agenda_notification_rules DROP INDEX uk_agenda_notification_rules"); } catch (\Exception $e) {}

// Backfill autolimitado: las reglas viejas (de antes de esta migración, sin
// distinción de evento) se clonan a los otros 3 triggers para no cambiar el
// comportamiento ya configurado por el negocio — antes una sola regla por
// destinatario aplicaba a confirmed/rescheduled/cancelled/reminder por
// igual. Se autolimita chequeando si ya existe algún trigger distinto de
// 'confirmed' (si existe, esta migración ya corrió antes).
try {
    $hasDefaults = (int)$pdo->query("SELECT COUNT(*) FROM agenda_notification_rules WHERE resource_id = 0 AND trigger_type = 'confirmed'")->fetchColumn() > 0;
    $alreadyExpanded = (int)$pdo->query("SELECT COUNT(*) FROM agenda_notification_rules WHERE trigger_type IN ('rescheduled','cancelled','reminder')")->fetchColumn() > 0;
    if ($hasDefaults && !$alreadyExpanded) {
        $rows = $pdo->query("SELECT user_id, recipient_type, channel, enabled FROM agenda_notification_rules WHERE resource_id = 0 AND trigger_type = 'confirmed'")->fetchAll();
        $ins = $pdo->prepare("INSERT INTO agenda_notification_rules (user_id, resource_id, trigger_type, recipient_type, channel, enabled) VALUES (?, 0, ?, ?, ?, ?)");
        foreach ($rows as $row) {
            foreach (['rescheduled', 'cancelled', 'reminder'] as $trigger) {
                $ins->execute([$row['user_id'], $trigger, $row['recipient_type'], $row['channel'], $row['enabled']]);
            }
        }
    }
} catch (\Exception $e) {}

try { $pdo->exec("ALTER TABLE agenda_notification_rules ADD UNIQUE KEY uk_agenda_notification_rules_v2 (user_id, resource_id, trigger_type, recipient_type)"); } catch (\Exception $e) {}
