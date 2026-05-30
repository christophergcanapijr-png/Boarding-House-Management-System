<?php
require_once 'config/db.php';

$room_id = $_GET['id'] ?? 0;

$room = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
$room->execute([$room_id]);
$room = $room->fetch(PDO::FETCH_ASSOC);

$tenants = $pdo->prepare("
    SELECT t.*, 
    r.price as amount_due,
    TIMESTAMPDIFF(MONTH, t.move_in_date, NOW()) as months_stayed,
    TIMESTAMPDIFF(DAY, t.move_in_date, NOW()) as days_stayed,
    COALESCE(p.status, 'unpaid') as payment_status,
    CASE WHEN COALESCE(p.status, 'unpaid') = 'unpaid' THEN r.price ELSE 0 END as balance
    FROM tenants t
    JOIN rooms r ON t.room_id = r.id
    LEFT JOIN payments p ON p.tenant_id = t.id 
        AND p.month_covered = DATE_FORMAT(NOW(), '%Y-%m')
    WHERE t.room_id = ?
");
$tenants->execute([$room_id]);
$tenants = $tenants->fetchAll(PDO::FETCH_ASSOC);

// Format duration
foreach($tenants as &$tenant) {
    $months = $tenant['months_stayed'];
    $days = $tenant['days_stayed'] % 30;
    $tenant['duration'] = $months > 0 ? "$months month(s) $days day(s)" : "$days day(s)";
}

echo json_encode(['room' => $room, 'tenants' => $tenants]);
?>