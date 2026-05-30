<?php
require_once 'config/db.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenant_id = $_POST['tenant_id'];
    $amount = $_POST['amount'];
    $date_paid = $_POST['date_paid'];
    $month_covered = $_POST['month_covered'];

    try {
        // Check if payment already exists for this month
        $check = $pdo->prepare("SELECT id FROM payments WHERE tenant_id = ? AND month_covered = ?");
        $check->execute([$tenant_id, $month_covered]);
        
        if ($check->fetch()) {
            // Update existing payment
            $stmt = $pdo->prepare("UPDATE payments SET amount = ?, date_paid = ?, status = 'paid' WHERE tenant_id = ? AND month_covered = ?");
            $stmt->execute([$amount, $date_paid, $tenant_id, $month_covered]);
        } else {
            // Insert new payment
            $stmt = $pdo->prepare("INSERT INTO payments (tenant_id, amount, date_paid, month_covered, status) VALUES (?, ?, ?, ?, 'paid')");
            $stmt->execute([$tenant_id, $amount, $date_paid, $month_covered]);
        }
        $success = "Payment recorded successfully!";
    } catch(Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all tenants with room info and payment status
$tenants = $pdo->query("
    SELECT t.*, r.room_number, r.room_type, r.price,
    COALESCE(p.status, 'unpaid') as payment_status,
    p.date_paid, p.amount as paid_amount,
    TIMESTAMPDIFF(MONTH, t.move_in_date, NOW()) as months_stayed
    FROM tenants t
    JOIN rooms r ON t.room_id = r.id
    LEFT JOIN payments p ON p.tenant_id = t.id 
        AND p.month_covered = DATE_FORMAT(NOW(), '%Y-%m')
    ORDER BY r.room_number
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f0; color: #1a1a1a; }
        .header { background: #fff; border-bottom: 1px solid #eee; padding: 1rem 2rem; display: flex; align-items: center; gap: 1rem; }
        .back-btn { text-decoration: none; color: #888; font-size: 14px; }
        .header h1 { font-size: 20px; font-weight: 600; }
        .container { padding: 2rem; max-width: 1000px; margin: 0 auto; }
        .success { background: #dcfce7; color: #16a34a; padding: 12px; border-radius: 8px; margin-bottom: 1rem; font-size: 14px; }
        .error { background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 1rem; font-size: 14px; }
        table { width: 100%; background: #fff; border-radius: 16px; border-collapse: collapse; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.05); }
        th { background: #f9f9f9; padding: 12px 16px; text-align: left; font-size: 13px; color: #888; font-weight: 500; border-bottom: 1px solid #eee; }
        td { padding: 14px 16px; font-size: 14px; border-bottom: 1px solid #f5f5f5; }
        tr:last-child td { border-bottom: none; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .badge-paid { background: #dcfce7; color: #16a34a; }
        .badge-unpaid { background: #fee2e2; color: #dc2626; }
        .btn-pay { background: #c45e1a; color: white; border: none; padding: 7px 16px; border-radius: 8px; font-size: 13px; cursor: pointer; }
        .btn-pay:hover { background: #a84d15; }
        .section-title { font-size: 16px; font-weight: 600; margin-bottom: 1rem; }

        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: #fff; border-radius: 16px; padding: 2rem; width: 420px; max-width: 90%; }
        .modal h2 { font-size: 18px; font-weight: 600; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-size: 13px; font-weight: 500; color: #555; margin-bottom: 6px; }
        input, select { width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; outline: none; }
        input:focus { border-color: #c45e1a; }
        .modal-actions { display: flex; gap: 8px; margin-top: 1.5rem; }
        .btn-submit { flex: 1; padding: 11px; background: #c45e1a; color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; }
        .btn-cancel { flex: 1; padding: 11px; background: transparent; border: 1px solid #ccc; border-radius: 8px; font-size: 14px; cursor: pointer; }
    </style>
</head>
<body>

<div class="header">
    <a href="index.php" class="back-btn">← Back</a>
    <h1>💰 Payment Tracking</h1>
</div>

<div class="container">
    <?php if($success): ?>
        <div class="success">✅ <?= $success ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="error">❌ <?= $error ?></div>
    <?php endif; ?>

    <div class="section-title">
        <?= date('F Y') ?> Payments
    </div>

    <table>
        <thead>
            <tr>
                <th>Room</th>
                <th>Tenant</th>
                <th>Monthly Rent</th>
                <th>Duration</th>
                <th>Status</th>
                <th>Date Paid</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($tenants as $tenant): ?>
            <tr>
                <td>Room <?= $tenant['room_number'] ?> — <?= $tenant['room_type'] ?></td>
                <td><?= $tenant['name'] ?><br><small style="color:#888"><?= $tenant['contact'] ?></small></td>
                <td>₱<?= number_format($tenant['price'], 2) ?></td>
                <td><?= $tenant['months_stayed'] ?> month(s)</td>
                <td><span class="badge badge-<?= $tenant['payment_status'] ?>"><?= ucfirst($tenant['payment_status']) ?></span></td>
                <td><?= $tenant['date_paid'] ?? '—' ?></td>
                <td>
                    <?php if($tenant['payment_status'] === 'unpaid'): ?>
                    <button class="btn-pay" onclick="openPayment(<?= $tenant['id'] ?>, '<?= $tenant['name'] ?>', <?= $tenant['price'] ?>)">Mark Paid</button>
                    <?php else: ?>
                    <span style="color:#16a34a;font-size:13px;">✓ Paid</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Payment Modal -->
<div class="modal-overlay" id="payModal">
    <div class="modal">
        <h2>Record Payment</h2>
        <form method="POST">
            <input type="hidden" name="tenant_id" id="pay_tenant_id" />
            <div class="form-group">
                <label>Tenant</label>
                <input type="text" id="pay_tenant_name" readonly style="background:#f9f9f9" />
            </div>
            <div class="form-group">
                <label>Amount</label>
                <input type="number" name="amount" id="pay_amount" required />
            </div>
            <div class="form-group">
                <label>Date Paid</label>
                <input type="date" name="date_paid" value="<?= date('Y-m-d') ?>" required />
            </div>
            <div class="form-group">
                <label>Month Covered</label>
                <input type="month" name="month_covered" value="<?= date('Y-m') ?>" required />
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closePayModal()">Cancel</button>
                <button type="submit" class="btn-submit">Record Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
lucide.createIcons();

function openPayment(id, name, amount) {
    document.getElementById('pay_tenant_id').value = id;
    document.getElementById('pay_tenant_name').value = name;
    document.getElementById('pay_amount').value = amount;
    document.getElementById('payModal').classList.add('active');
}

function closePayModal() {
    document.getElementById('payModal').classList.remove('active');
}
</script>
</body>
</html>