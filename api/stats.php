<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error' => 'No autorizado']); exit; }
$user_id = (int)$_SESSION['user_id'];

$start_date = $_GET['start_date'] ?? null;
$end_date   = $_GET['end_date']   ?? null;
$period     = $_GET['period']     ?? 'all';

if ($period !== 'all' && !$start_date) {
    $end_date = date('Y-m-d 23:59:59');
    if ($period === 'week')  $start_date = date('Y-m-d 00:00:00', strtotime('-7 days'));
    elseif ($period === 'month') $start_date = date('Y-m-d 00:00:00', strtotime('-30 days'));
    elseif ($period === 'year')  $start_date = date('Y-m-d 00:00:00', strtotime('-1 year'));
}

try {
    $dateWhere  = '';
    $dateParams = [];
    if ($period !== 'all' && $start_date && $end_date) {
        $dateWhere  = " AND created_at BETWEEN ? AND ?";
        $dateParams = [$start_date, $end_date];
    }

    $is_admin = ($_SESSION['user_role'] ?? 'subscriber') === 'admin';

    // 1. Prospectos del usuario (o total usuarios si es admin)
    if ($is_admin) {
        $stmtU = $pdo->query("SELECT COUNT(*) FROM users");
        $prospects = (int)$stmtU->fetchColumn();
    } else {
        $stmtP = $pdo->prepare("SELECT COUNT(*) FROM prospects WHERE user_id = ?" . $dateWhere);
        $stmtP->execute(array_merge([$user_id], $dateParams));
        $prospects = (int)$stmtP->fetchColumn();
    }

    // 2. Actividades (todas si es admin)
    if ($is_admin) {
        $sqlAct = "SELECT COUNT(*) FROM activities a JOIN prospects p ON a.prospect_id = p.id";
        if ($period !== 'all' && $start_date && $end_date) {
            $sqlAct .= " WHERE a.created_at BETWEEN ? AND ?";
        }
        $stmtA = $pdo->prepare($sqlAct);
        $stmtA->execute($dateParams);
    } else {
        $stmtA = $pdo->prepare("SELECT COUNT(*) FROM activities a 
                                 JOIN prospects p ON a.prospect_id = p.id 
                                 WHERE p.user_id = ?" . str_replace('created_at', 'a.created_at', $dateWhere));
        $stmtA->execute(array_merge([$user_id], $dateParams));
    }
    $activities = (int)$stmtA->fetchColumn();

    // 3. Conversión global (solo para subscriber; admin ve datos globales)
    if ($is_admin) {
        $totalViews = 0;
        $conversionRate = 0;
    } else {
        $stmtV = $pdo->prepare("
            SELECT COALESCE(SUM(ls.views), 0)
            FROM landing_subscriptions ls
            INNER JOIN landings l ON ls.landing_id = l.id
            WHERE ls.user_id = ?
        ");
        $stmtV->execute([$user_id]);
        $totalViews = (int)$stmtV->fetchColumn();

        if ($totalViews > 0) {
            $stmtLP = $pdo->prepare(
                "SELECT COUNT(*) FROM prospects WHERE user_id = ? AND landing_id IS NOT NULL" . $dateWhere
            );
            $stmtLP->execute(array_merge([$user_id], $dateParams));
            $landingProspects = (int)$stmtLP->fetchColumn();
            $conversionRate = round(($landingProspects / $totalViews) * 100, 1);
        } elseif ($prospects > 0) {
            $stmtAct = $pdo->prepare("
                SELECT COUNT(DISTINCT a.prospect_id)
                FROM activities a
                JOIN prospects p ON a.prospect_id = p.id
                WHERE p.user_id = ?" . str_replace('created_at', 'p.created_at', $dateWhere));
            $stmtAct->execute(array_merge([$user_id], $dateParams));
            $activeProspects = (int)$stmtAct->fetchColumn();
            $conversionRate = round(($activeProspects / $prospects) * 100, 1);
        } else {
            $conversionRate = 0;
        }
    }

    // 4. Chart Data: Prospects per day (todas si es admin)
    if ($is_admin) {
        $sqlChart = "SELECT DATE(created_at) as date, COUNT(*) as count FROM prospects";
        if ($period !== 'all' && $start_date && $end_date) {
            $sqlChart .= " WHERE created_at BETWEEN ? AND ?";
        }
        $sqlChart .= " GROUP BY DATE(created_at) ORDER BY date ASC";
        $stmtChart = $pdo->prepare($sqlChart);
        $stmtChart->execute($dateParams);
    } else {
        $stmtChart = $pdo->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM prospects 
            WHERE user_id = ?" . $dateWhere . "
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmtChart->execute(array_merge([$user_id], $dateParams));
    }
    $chartDataRaw = $stmtChart->fetchAll();

    // Fill missing dates
    $chartData = [];
    if (!empty($chartDataRaw)) {
        // If 'all' period, use first and last date from data
        $first_date = $start_date ? date('Y-m-d', strtotime($start_date)) : $chartDataRaw[0]['date'];
        $last_date = $end_date ? date('Y-m-d', strtotime($end_date)) : date('Y-m-d');
        
        $current = strtotime($first_date);
        $end = strtotime($last_date);
        
        $rawDataMap = [];
        foreach ($chartDataRaw as $row) {
            $rawDataMap[$row['date']] = (int)$row['count'];
        }

        while ($current <= $end) {
            $d = date('Y-m-d', $current);
            $chartData[] = [
                'date' => $d,
                'count' => $rawDataMap[$d] ?? 0
            ];
            $current = strtotime('+1 day', $current);
        }
    }

    // ── Datos adicionales para admin ──
    $usersSummary = [];
    $prospectsPerUser = ['labels' => [], 'data' => []];
    $activitiesPerUser = ['labels' => [], 'data' => []];

    if ($is_admin) {
        $stmtU = $pdo->query("
            SELECT u.id, u.name, u.email, u.can_create_agents, u.active,
                (SELECT COUNT(*) FROM prospects p WHERE p.user_id = u.id) as prospect_count,
                (SELECT COUNT(*) FROM activities a JOIN prospects p ON a.prospect_id = p.id WHERE p.user_id = u.id) as activity_count,
                (SELECT MAX(a.created_at) FROM activities a JOIN prospects p ON a.prospect_id = p.id WHERE p.user_id = u.id) as last_activity
            FROM users u
            ORDER BY prospect_count DESC
        ");
        while ($u = $stmtU->fetch(PDO::FETCH_ASSOC)) {
            $usersSummary[] = [
                'id'              => (int)$u['id'],
                'name'            => $u['name'],
                'email'           => $u['email'],
                'prospects'       => (int)$u['prospect_count'],
                'activities'      => (int)$u['activity_count'],
                'last_activity'   => $u['last_activity'],
                'can_create_agents' => (int)$u['can_create_agents'],
                'active'          => (int)$u['active'],
            ];
            $prospectsPerUser['labels'][] = $u['name'];
            $prospectsPerUser['data'][]   = (int)$u['prospect_count'];
            $activitiesPerUser['labels'][] = $u['name'];
            $activitiesPerUser['data'][]   = (int)$u['activity_count'];
        }
    }

    echo json_encode([
        'prospects'           => $prospects,
        'activities'          => $activities,
        'actions'             => $activities,
        'conversion_rate'     => $conversionRate,
        'total_views'         => $totalViews,
        'chart_data'          => $chartData,
        'users_summary'       => $usersSummary,
        'prospects_per_user'  => $prospectsPerUser,
        'activities_per_user' => $activitiesPerUser,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
