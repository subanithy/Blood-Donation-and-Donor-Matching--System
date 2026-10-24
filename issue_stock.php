<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: admin_login.php");
    exit();
}

$message = '';
$alert_class = 'alert-danger';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['issue_stock'])) {
    $unit_id = trim($_POST['unit_id']);
    $remarks = trim($_POST['remarks'] ?? '');

    $check_stmt = $conn->prepare("SELECT id, blood_group, status FROM inventory_units WHERE unit_id = ?");
    $check_stmt->bind_param("s", $unit_id);
    $check_stmt->execute();
    $res = $check_stmt->get_result();

    if ($res->num_rows === 1) {
        $unit = $res->fetch_assoc();

        if ($unit['status'] === 'Available') {
            $update_stmt = $conn->prepare("UPDATE inventory_units SET status = 'Used', remarks = ? WHERE unit_id = ?");
            $update_stmt->bind_param("ss", $remarks, $unit_id);

            if ($update_stmt->execute()) {
                $message = "Unit " . htmlspecialchars($unit_id) . " (" . htmlspecialchars($unit['blood_group']) . ") successfully issued/deducted!";
                $alert_class = 'alert-success';
            } else {
                $message = "Database error updating status.";
            }
        } else {
            $message = "Unit " . htmlspecialchars($unit_id) . " is already marked as " . htmlspecialchars($unit['status']) . "!";
        }
    } else {
        $message = "Unit ID not found!";
    }
}

$available_units = $conn->query("SELECT unit_id, blood_group, component_type, storage_location FROM inventory_units WHERE status = 'Available' ORDER BY expiry_date ASC")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Issue / Deduct Blood Unit</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 0; }
        .navbar { background-color: #2599e1; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 600px; margin: 30px auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { background: #dc3545; color: #fff; padding: 12px; border: none; border-radius: 4px; width: 100%; font-size: 16px; cursor: pointer; font-weight: bold; }
        .alert { padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .alert-success { background-color: #d4edda; color: #155724; }
        .alert-danger { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<div class="navbar">
    <h2>Blood Inventory Management</h2>
    <a href="inventory_dashboard.php" style="color: white; text-decoration: none; font-weight: bold;">View Dashboard</a>
</div>

<div class="container">
    <h2>Issue / Deduct Blood Unit</h2>

    <?php if ($message): ?>
        <div class="alert <?= $alert_class ?>"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST" action="issue_stock.php">
        <div class="form-group">
            <label>Select Available Unit to Issue:</label>
            <select name="unit_id" required>
                <option value="">-- Choose Unit --</option>
                <?php foreach ($available_units as $u): ?>
                    <option value="<?= htmlspecialchars($u['unit_id']) ?>">
                        <?= htmlspecialchars($u['unit_id']) ?> | Blood Group: <?= htmlspecialchars($u['blood_group']) ?> | <?= htmlspecialchars($u['component_type']) ?> (Location: <?= htmlspecialchars($u['storage_location']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Remarks / Recipient Details (Optional):</label>
            <textarea name="remarks" rows="2" placeholder="e.g., Donated to Ward 3 patient"></textarea>
        </div>

        <button type="submit" name="issue_stock" class="btn">Issue Unit & Deduct Stock</button>
    </form>
</div>

</body>
</html>