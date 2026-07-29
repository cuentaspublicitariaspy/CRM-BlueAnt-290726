<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo "No autorizado. Inicia sesión primero.";
    exit;
}
$user_id = (int)$_SESSION['user_id'];

if (!$user_id) {
    echo "No users found in database to attach demo data to.\n";
    exit(1);
}

// Clear existing demo data (optional, maybe don't clear, just add)
// Let's just add new data spanning the last 30 days.

$names = ['Juan Perez', 'Maria Garcia', 'Carlos Lopez', 'Ana Martinez', 'Luis Rodriguez', 'Laura Fernandez', 'Pedro Sanchez', 'Sofia Gomez', 'Diego Diaz', 'Carmen Torres'];
$domains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'empresa.com'];

$current_time = time();

$inserted_prospects = 0;
$inserted_activities = 0;

for ($i = 0; $i < 50; $i++) {
    // Random day in the last 30 days
    $days_ago = rand(0, 30);
    $created_at = date('Y-m-d H:i:s', $current_time - ($days_ago * 86400) - rand(0, 86400));
    
    $name = $names[array_rand($names)] . ' ' . rand(1, 100);
    $email = strtolower(str_replace(' ', '.', $name)) . rand(1,1000) . '@' . $domains[array_rand($domains)];
    $whatsapp = '+1' . rand(1000000000, 9999999999);
    
    // Insert prospect
    $stmt = $pdo->prepare("INSERT INTO prospects (user_id, name, email, whatsapp, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
    try {
        $stmt->execute([$user_id, $name, $email, $whatsapp, $created_at, $created_at]);
        $prospect_id = $pdo->lastInsertId();
        $inserted_prospects++;
        
        // Insert 1 to 3 activities for this prospect
        $num_activities = rand(1, 3);
        $activity_types = ['note', 'call', 'email', 'meeting'];
        
        for ($j = 0; $j < $num_activities; $j++) {
            $act_time = date('Y-m-d H:i:s', strtotime($created_at) + rand(3600, 86400 * 2)); // Activity happens after creation
            if (strtotime($act_time) > time()) {
                $act_time = date('Y-m-d H:i:s'); // Cap at now
            }
            $act_type = $activity_types[array_rand($activity_types)];
            
            $stmtAct = $pdo->prepare("INSERT INTO activities (prospect_id, description, activity_type, created_at) VALUES (?, ?, ?, ?)");
            $stmtAct->execute([$prospect_id, "Demo activity $j for $name", $act_type, $act_time]);
            $inserted_activities++;
        }
        
    } catch (Exception $e) {
        // Ignore duplicate emails if they happen
    }
}

echo "Inserted $inserted_prospects prospects and $inserted_activities activities for user_id $user_id.\n";
?>
