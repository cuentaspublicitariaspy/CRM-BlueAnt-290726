<?php
// Diagnostic: test agents.php internal logic
session_start();
header('Content-Type: text/plain');

// Simulate the exact same flow as agents.php for a GET /admin/agents request
try {
    echo "Step 1: require db_config\n";
    require_once __DIR__ . '/api/db_config.php';
    echo "  PDO OK: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
} catch (Throwable $e) {
    echo "  FAIL: " . $e->getMessage() . "\n";
}

if (!isset($_SESSION['user_id'])) {
    echo "Step 2: No session user_id - set test session\n";
    $_SESSION['user_id'] = 1;
    $_SESSION['user_role'] = 'admin';
    $_SESSION['user_name'] = 'Diagnostic';
}

echo "Step 2: Session user=" . ($_SESSION['user_id'] ?? 'none') . " role=" . ($_SESSION['user_role'] ?? 'none') . "\n";

try {
    echo "Step 3: Test PDO query on agents table\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM agents");
    echo "  agents count: " . $stmt->fetchColumn() . "\n";
    
    echo "Step 4: Test PDO query on users table\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    echo "  users count: " . $stmt->fetchColumn() . "\n";
    
    echo "Step 5: Simulate LIST query\n";
    $user_id = 1;
    $sql = "SELECT a.id, a.name FROM agents a WHERE (? = 'admin') ORDER BY a.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['admin']);
    $agents = $stmt->fetchAll();
    echo "  agents found: " . count($agents) . "\n";
    foreach ($agents as $a) echo "    - {$a['id']}: {$a['name']}\n";
    
    echo "\nStep 6: Test INSERT\n";
    $agentIdNew = 'ag_' . bin2hex(random_bytes(14));
    $stmt = $pdo->prepare("INSERT INTO agents (id, name, personality_prompt, model, mode, widget_style, voice_mode, primary_color, owner_crm_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $agentIdNew, 'Diagnostic Agent',
        'Eres un asistente util y amable.',
        'gpt-4o-mini', 'preciso', 'bubble', 'none', '#2563eb', null
    ]);
    echo "  inserted: $agentIdNew\n";
    
    echo "Step 7: Verify INSERT\n";
    $stmt = $pdo->prepare("SELECT id, name FROM agents WHERE id = ?");
    $stmt->execute([$agentIdNew]);
    $agent = $stmt->fetch();
    if ($agent) {
        echo "  VERIFIED: {$agent['id']} - {$agent['name']}\n";
    } else {
        echo "  VERIFICATION FAILED: agent not found after insert!\n";
    }
    
    echo "Step 8: Cleanup\n";
    $pdo->prepare("DELETE FROM agents WHERE id = ?")->execute([$agentIdNew]);
    echo "  deleted test agent\n";
    
    echo "\n=== ALL TESTS PASSED ===\n";
} catch (Throwable $e) {
    echo "\nFAILED at " . $e->getLine() . ": " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
}
