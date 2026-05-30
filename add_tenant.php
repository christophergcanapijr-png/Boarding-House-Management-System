<?php
require_once 'config/db.php';

$room_id = $_GET['room_id'] ?? '';
$success = '';
$error = '';

// Get all rooms
$rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_number")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $contact = $_POST['contact'];
    $email = $_POST['email'];
    $room_id = $_POST['room_id'];
    $bed_number = $_POST['bed_number'] ?? null;
    $move_in_date = $_POST['move_in_date'];

    try {
        // Add tenant
        $stmt = $pdo->prepare("INSERT INTO tenants (room_id, bed_number, name, contact, email, move_in_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$room_id, $bed_number, $name, $contact, $email, $move_in_date]);

        // Update room status to occupied
        $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?")->execute([$room_id]);

        $success = "Tenant added successfully!";
    } catch(Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get selected room type
$selected_room = null;
if ($room_id) {
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->execute([$room_id]);
    $selected_room = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Tenant</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f0; color: #1a1a1a; }
        .header { background: #fff; border-bottom: 1px solid #eee; padding: 1rem 2rem; display: flex; align-items: center; gap: 1rem; }
        .back-btn { display: flex; align-items: center; gap: 6px; text-decoration: none; color: #888; font-size: 14px; }
        .header h1 { font-size: 20px; font-weight: 600; }
        .container { padding: 2rem; max-width: 600px; margin: 0 auto; }
        .card { background: #fff; border-radius: 16px; padding: 2rem; border: 1px solid #eee; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: 13px; font-weight: 500; color: #555; margin-bottom: 6px; }
        input, select { width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; outline: none; }
        input:focus, select:focus { border-color: #c45e1a; }
        .btn { width: 100%; padding: 12px; background: #c45e1a; color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: 500; cursor: pointer; margin-top: 0.5rem; }
        .btn:hover { background: #a84d15; }
        .success { background: #dcfce7; color: #16a34a; padding: 12px; border-radius: 8px; margin-bottom: 1rem; font-size: 14px; }
        .error { background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 1rem; font-size: 14px; }
        .bed-group { display: none; }
    </style>
</head>
<body>

<div class="header">
    <a href="index.php" class="back-btn">← Back</a>
    <h1>Add Tenant</h1>
</div>

<div class="container">
    <div class="card">
        <?php if($success): ?>
            <div class="success">✅ <?= $success ?> <a href="index.php">Go back to dashboard</a></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="error">❌ <?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Select Room</label>
                <select name="room_id" id="room_select" onchange="checkBedspacer(this)" required>
                    <option value="">-- Select Room --</option>
                    <?php foreach($rooms as $room): ?>
                    <option value="<?= $room['id'] ?>" 
                        data-type="<?= $room['room_type'] ?>"
                        <?= $room_id == $room['id'] ? 'selected' : '' ?>>
                        Room <?= $room['room_number'] ?> — <?= $room['room_type'] ?> (₱<?= number_format($room['price'], 2) ?>)
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

            <button type="submit" class="btn">Add Tenant</button>
        </form>
    </div>
</div>

<script>
lucide.createIcons();

function checkBedspacer(select) {
    const type = select.options[select.selectedIndex].dataset.type;
    document.getElementById('bed_group').style.display = type === 'Bedspacer' ? 'block' : 'none';
}

// Check on load
window.onload = function() {
    const select = document.getElementById('room_select');
    if (select.value) checkBedspacer(select);
}
</script>
</body>
</html>