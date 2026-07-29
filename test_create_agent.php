<?php
// Diagnostic: test creating an agent directly
session_start();
require_once __DIR__ . '/api/db_config.php';

header('Content-Type: text/plain');

// Check session
echo "SESSION:\n";
print_r($_SESSION);
echo "\n\n";

// Check DB connection
try {
    $pdo->query("SELECT 1");
    echo "DB connection: OK\n";
} catch (Exception $e) {
    echo "DB connection: FAILED - " . $e->getMessage() . "\n";
    exit;
}

// Check table exists
$stmt = $pdo->query("SHOW TABLES LIKE 'agents'");
if ($stmt->rowCount() > 0) {
    echo "agents table: EXISTS\n";
} else {
    echo "agents table: MISSING\n";
    exit;
}

// Count existing agents
$count = $pdo->query("SELECT COUNT(*) FROM agents")->fetchColumn();
echo "Current agents count: $count\n";

// Try to create one
try {
    $agentId = 'ag_' . bin2hex(random_bytes(14));
    $stmt = $pdo->prepare("INSERT INTO agents (id, name, personality_prompt, model, mode, widget_style, voice_mode, primary_color, owner_crm_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $agentId,
        'Diagnostic Agent',
        'Eres un asistente util y amable.',
        'gpt-4o-mini',
        'preciso',
        'bubble',
        'none',
        '#2563eb',
        null
    ]);
    echo "Inserted agent: $agentId\n";
    
    // Verify it exists
    $stmt2 = $pdo->prepare("SELECT * FROM agents WHERE id = ?");
    $stmt2->execute([$agentId]);
    $agent = $stmt2->fetch();
    if ($agent) {
        echo "Verification: FOUND - " . $agent['name'] . " (id: " . $agent['id'] . ")\n";
    } else {
        echo "Verification: NOT FOUND (insert succeeded but select returned nothing?)\n";
    }
    
    // Clean up: delete the test agent
    $pdo->prepare("DELETE FROM agents WHERE id = ?")->execute([$agentId]);
    echo "Cleaned up test agent.\n";
    
} catch (Exception $e) {
    echo "INSERT FAILED: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
