<?php require_once 'config/db.php'; ?>
<?php
$success = '';
$error = '';

if(isset($_GET['success'])) $success = "Tenant added successfully!";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tenant'])) {
    $name = $_POST['name'];
    $contact = $_POST['contact'];
    $email = $_POST['email'];
    $room_id = $_POST['room_id'];
    $bed_number = $_POST['bed_number'] ?? null;
    $move_in_date = $_POST['move_in_date'];

    try {
        $stmt = $pdo->prepare("INSERT INTO tenants (room_id, bed_number, name, contact, email, move_in_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$room_id, $bed_number, $name, $contact, $email, $move_in_date]);
        $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?")->execute([$room_id]);
        header('Location: index.php?success=1');
        exit;
    } catch(Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$rooms = $pdo->query("
    SELECT r.*, 
    COUNT(t.id) as tenant_count,
    GROUP_CONCAT(t.name SEPARATOR ', ') as tenant_names
    FROM rooms r
    LEFT JOIN tenants t ON r.id = t.room_id
    GROUP BY r.id
    ORDER BY r.room_number
")->fetchAll(PDO::FETCH_ASSOC);

$all_rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_number")->fetchAll(PDO::FETCH_ASSOC);

$total = count($rooms);
$occupied = count(array_filter($rooms, fn($r) => $r['status'] === 'occupied'));
$vacant = $total - $occupied;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boarding House Admin</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f0; color: #1a1a1a; }
        .header { background: #fff; border-bottom: 1px solid #eee; padding: 1rem 2rem; display: flex; align-items: center; justify-content: space-between; }
        .header h1 { font-size: 20px; font-weight: 600; }
        .header p { font-size: 13px; color: #888; }
        .header-btns { display: flex; gap: 8px; }
        .container { padding: 2rem; max-width: 1100px; margin: 0 auto; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: #fff; border-radius: 12px; padding: 1.25rem; border: 1px solid #eee; }
        .stat-card .label { font-size: 12px; color: #888; margin-bottom: 6px; }
        .stat-card .value { font-size: 24px; font-weight: 600; color: #1a1a1a; }
        .section-title { font-size: 16px; font-weight: 600; margin-bottom: 1rem; }
        .floor-plan { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
        .room-card { background: #fff; border-radius: 12px; padding: 1.5rem; border: 2px solid #eee; cursor: pointer; transition: all 0.2s; position: relative; }
        .room-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .room-card.occupied { border-color: #c45e1a; background: #fdf6f0; }
        .room-card.vacant { border-color: #22c55e; background: #f0fdf4; }
        .room-number { font-size: 13px; color: #888; margin-bottom: 4px; }
        .room-type { font-size: 18px; font-weight: 600; margin-bottom: 4px; }
        .room-price { font-size: 14px; color: #c45e1a; font-weight: 500; margin-bottom: 8px; }
        .room-status { display: inline-block; font-size: 11px; padding: 4px 10px; border-radius: 20px; font-weight: 500; }
        .status-occupied { background: #fde8d8; color: #c45e1a; }
        .status-vacant { background: #dcfce7; color: #16a34a; }
        .room-tenant { font-size: 13px; color: #555; margin-top: 8px; }
        .room-icon { position: absolute; top: 1rem; right: 1rem; color: #ccc; }
        .success { background: #dcfce7; color: #16a34a; padding: 12px; border-radius: 8px; margin-bottom: 1rem; font-size: 14px; }
        .error { background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 1rem; font-size: 14px; }

        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: #fff; border-radius: 16px; padding: 2rem; width: 500px; max-width: 90%; max-height: 85vh; overflow-y: auto; }
        .modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
        .modal-header h2 { font-size: 18px; font-weight: 600; }
        .close-btn { background: none; border: none; cursor: pointer; font-size: 20px; color: #888; }
        .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .detail-label { color: #888; }
        .detail-value { font-weight: 500; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .badge-paid { background: #dcfce7; color: #16a34a; }
        .badge-unpaid { background: #fee2e2; color: #dc2626; }
        .vacant-msg { text-align: center; padding: 2rem; color: #888; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: 13px; font-weight: 500; color: #555; margin-bottom: 6px; }
        input, select { width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; outline: none; }
        input:focus, select:focus { border-color: #c45e1a; }
        .bed-group { display: none; }
        .btn { padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; border: none; }
        .btn-primary { background: #c45e1a; color: white; }
        .btn-outline { background: transparent; border: 1px solid #ccc; color: #1a1a1a; }
        .btn-danger { background: #fee2e2; color: #dc2626; border: none; padding: 8px 16px; border-radius: 8px; font-size: 13px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 10px; }
        .btn-full { width: 100%; margin-top: 0.5rem; }

        /* Pay modal */
        .btn-pay { background: #c45e1a; color: white; border: none; padding: 7px 16px; border-radius: 8px; font-size: 13px; cursor: pointer; margin-top: 10px; }
    </style>
</head>
<body>

<div class="header">
    <div>
        <h1>🏠 Boarding House Admin</h1>
        <p>Manage your rooms and tenants</p>
    </div>
    <div class="header-btns">
        <button class="btn btn-outline" onclick="openModal('allPaymentsModal')">💰 Payments</button>
        <button class="btn btn-primary" onclick="openModal('addTenantModal')">+ Add Tenant</button>
    </div>
</div>

<div class="container">
    <?php if($success): ?>
        <div class="success">✅ <?= $success ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="error">❌ <?= $error ?></div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat-card">
            <div class="label">Total Rooms</div>
            <div class="value"><?= $total ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Occupied</div>
            <div class="value" style="color:#c45e1a"><?= $occupied ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Vacant</div>
            <div class="value" style="color:#16a34a"><?= $vacant ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Occupancy Rate</div>
            <div class="value"><?= $total > 0 ? round(($occupied/$total)*100) : 0 ?>%</div>
        </div>
    </div>

    <div class="section-title">Floor Plan</div>
    <div class="floor-plan">
        <?php foreach($rooms as $room): ?>
        <div class="room-card <?= $room['status'] ?>" onclick="openRoom(<?= $room['id'] ?>)">
            <div class="room-icon"><i data-lucide="door-open"></i></div>
            <div class="room-number">Room <?= $room['room_number'] ?></div>
            <div class="room-type"><?= $room['room_type'] ?></div>
            <div class="room-price">₱<?= number_format($room['price'], 2) ?><?= $room['room_type'] === 'Bedspacer' ? '/bed' : ($room['room_type'] === 'Transient' ? '/day' : '/month') ?></div>
            <span class="room-status status-<?= $room['status'] ?>"><?= ucfirst($room['status']) ?></span>
            <?php if($room['tenant_names']): ?>
            <div class="room-tenant">👤 <?= $room['tenant_names'] ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Room Detail Modal -->
<div class="modal-overlay" id="roomModal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="modalTitle">Room Details</h2>
            <button class="close-btn" onclick="closeModal('roomModal')">✕</button>
        </div>
        <div id="modalContent"></div>
    </div>
</div>

<!-- Add Tenant Modal -->
<div class="modal-overlay" id="addTenantModal">
    <div class="modal">
        <div class="modal-header">
            <h2>+ Add Tenant</h2>
            <button class="close-btn" onclick="closeModal('addTenantModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="add_tenant" value="1" />
            <div class="form-group">
                <label>Select Room</label>
                <select name="room_id" id="room_select" onchange="checkBedspacer(this)" required>
                   <?php foreach($all_rooms as $room): ?>
<option value="<?= $room['id'] ?>" 
    data-type="<?= $room['room_type'] ?>"
    <?= $room['status'] === 'occupied' && $room['room_type'] !== 'Bedspacer' ? 'disabled' : '' ?>>
    Room <?= $room['room_number'] ?> — <?= $room['room_type'] ?> (₱<?= number_format($room['price'], 2) ?>)
    <?= $room['status'] === 'occupied' && $room['room_type'] !== 'Bedspacer' ? '— Occupied' : '' ?>
</option>
<?php endforeach; ?>
                </select>
            </div>
            <div class="form-group bed-group" id="bed_group">
                <label>Bed Number</label>
                <select name="bed_number">
                    <option value="1">Bed 1</option>
                    <option value="2">Bed 2</option>
                    <option value="3">Bed 3</option>
                    <option value="4">Bed 4</option>
                </select>
            </div>
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" placeholder="Enter tenant name" required />
            </div>
            <div class="form-group">
                <label>Contact Number</label>
                <input type="text" name="contact" placeholder="09XXXXXXXXX" required />
            </div>
            <div class="form-group">
                <label>Email (optional)</label>
                <input type="email" name="email" placeholder="email@example.com" />
            </div>
            <div class="form-group">
                <label>Move-in Date</label>
                <input type="date" name="move_in_date" required />
            </div>
            <button type="submit" class="btn btn-primary btn-full">Add Tenant</button>
        </form>
    </div>
</div>

<!-- Pay Modal -->
<div class="modal-overlay" id="payModal">
    <div class="modal">
        <div class="modal-header">
            <h2>Record Payment</h2>
            <button class="close-btn" onclick="closeModal('payModal')">✕</button>
        </div>
        <form method="POST" action="payments.php">
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
            <button type="submit" class="btn btn-primary btn-full">Record Payment</button>
        </form>
    </div>
</div>

<script>
lucide.createIcons();

function openRoom(roomId) {
    fetch('get_room.php?id=' + roomId)
        .then(res => res.json())
        .then(data => {
            document.getElementById('modalTitle').textContent = 'Room ' + data.room.room_number + ' — ' + data.room.room_type;
            let html = '';
            if (data.tenants.length === 0) {
                html = `<div class="vacant-msg"><p>This room is vacant</p><br><button class="btn btn-primary" onclick="closeModal('roomModal'); openModal('addTenantModal');" style="margin-top:1rem;">+ Add Tenant</button></div>`;
            } else {
                data.tenants.forEach(t => {
                    html += `
                    <div style="background:#f9f9f9;border-radius:10px;padding:1rem;margin-bottom:1rem;">
                        <div class="detail-row"><span class="detail-label">Name</span><span class="detail-value">${t.name}</span></div>
                        <div class="detail-row"><span class="detail-label">Contact</span><span class="detail-value">${t.contact}</span></div>
                        <div class="detail-row"><span class="detail-label">Move-in Date</span><span class="detail-value">${t.move_in_date}</span></div>
                        <div class="detail-row"><span class="detail-label">Duration</span><span class="detail-value">${t.duration}</span></div>
                        <div class="detail-row"><span class="detail-label">Monthly Rent</span><span class="detail-value">₱${parseFloat(t.amount_due).toLocaleString()}</span></div>
                        <div class="detail-row"><span class="detail-label">Payment Status</span><span class="detail-value"><span class="badge badge-${t.payment_status}">${t.payment_status}</span></span></div>
                        <div class="detail-row"><span class="detail-label">Balance</span><span class="detail-value" style="color:#dc2626;">₱${parseFloat(t.balance).toLocaleString()}</span></div>
                        <div style="display:flex;gap:8px;margin-top:10px;">
                            <button class="btn-pay" onclick="openPayModal(${t.id}, '${t.name}', ${t.amount_due})">💰 Mark Paid</button>
                            <a href="remove_tenant.php?id=${t.id}" onclick="return confirm('Remove this tenant?')" class="btn-danger">🗑 Remove</a>
                        </div>
                    </div>`;
                });
            }
            document.getElementById('modalContent').innerHTML = html;
            openModal('roomModal');
        });
}

function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function openPayModal(id, name, amount) {
    document.getElementById('pay_tenant_id').value = id;
    document.getElementById('pay_tenant_name').value = name;
    document.getElementById('pay_amount').value = amount;
    closeModal('roomModal');
    openModal('payModal');
}

function checkBedspacer(select) {
    const type = select.options[select.selectedIndex].dataset.type;
    document.getElementById('bed_group').style.display = type === 'Bedspacer' ? 'block' : 'none';
}

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});
</script>
</body>
</html>