<?php
require_once 'config/db.php';

if (isset($_GET['id'])) {
    $tenant_id = $_GET['id'];
    
    // Get room_id before deleting
    $stmt = $pdo->prepare("SELECT room_id FROM tenants WHERE id = ?");
    $stmt->execute([$tenant_id]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($tenant) {
        $room_id = $tenant['room_id'];
        
        // Delete payments first
        $pdo->prepare("DELETE FROM payments WHERE tenant_id = ?")->execute([$tenant_id]);
        
        // Delete tenant
        $pdo->prepare("DELETE FROM tenants WHERE id = ?")->execute([$tenant_id]);
        
        // Check if room still has tenants
        $remaining = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE room_id = ?");
        $remaining->execute([$room_id]);
        $count = $remaining->fetchColumn();
        
        // If no tenants left, set room to vacant
        if ($count == 0) {
            $pdo->prepare("UPDATE rooms SET status = 'vacant' WHERE id = ?")->execute([$room_id]);
        }
    }
}

header('Location: index.php');
exit;
?>