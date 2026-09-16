<?php
session_start();
include 'db.php';

// 1. Automated Expiry Sweep: Set past-expiry units to 'Expired'
$today = date('Y-m-d');
$conn->query("UPDATE inventory_units SET status = 'Expired' WHERE expiry_date < '$today' AND status = 'Available'");

// 2. Query Near-Expiry Sweep (Units expiring within next 72 hours)
$near_expiry_limit = date('Y-m-d', strtotime('+3 days'));
$expiring_units = $conn->query("
    SELECT * FROM inventory_units 
    WHERE status = 'Available' AND expiry_date BETWEEN '$today' AND '$near_expiry_limit'
")->fetch_all(MYSQLI_ASSOC);

// 3. Query Real-Time Active Stock Levels
$stock_levels = $conn->query("
    SELECT blood_group, COUNT(*) as total_units 
    FROM inventory_units 
    WHERE status = 'Available' 
    GROUP BY blood_group
")->fetch_all(MYSQLI_ASSOC);

$current_stock = [];
foreach ($stock_levels as $row) {
    $current_stock[$row['blood_group']] = $row['total_units'];
}

// 4. Low-Stock Threshold Evaluation
$thresholds = $conn->query("SELECT * FROM stock_thresholds")->fetch_all(MYSQLI_ASSOC);
$alerts = [];
foreach ($thresholds as $t) {
    $bg = $t['blood_group'];
    $min = $t['min_threshold'];
    $actual = $current_stock[$bg] ?? 0;
    
    if ($actual < $min) {
        $alerts[] = "Low stock for $bg: Only $actual unit(s) remaining (Minimum required: $min)";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 0; }
        .navbar { background-color: #2599e1; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        .alert-box { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .warning-box { background-color: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
        .card { background: #fff; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .card h3 { margin: 10px 0 0 0; font-size: 28px; color: #2599e1; }
        table { width: 100%; border-collapse: collapse; background: #fff; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #c0cad0; color: white; }
    </style>
</head>
<body>

<div class="navbar">
    <h1><font color=green>Blood Inventory Dashboard</h1>
    <a href="add_stock.php" style="color: green; text-decoration: none; font-weight: bold;">+ Add New Stock</a>
</div>

<div class="container">

    <!-- Low-Stock Alerts -->
    <?php if (!empty($alerts)): ?>
        <div class="alert-box">
            <h3 style="margin-top:0;">⚠ Low-Stock Threshold Warnings</h3>
            <ul>
                <?php foreach ($alerts as $alert): ?>
                    <li><strong><?= $alert ?></strong></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Near Expiry Warning Sweep -->
    <?php if (!empty($expiring_units)): ?>
        <div class="warning-box">
            <h3 style="margin-top:0;">⏳ Automated Expiry Sweep: Units Expiring Within 72 Hours</h3>
            <table>
                <tr>
                    <th>Barcode</th>
                    <th>Blood Group</th>
                    <th>Component</th>
                    <th>Location</th>
                    <th>Expiry Date</th>
                </tr>
                <?php foreach ($expiring_units as $unit): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($unit['barcode']) ?></strong></td>
                    <td><?= htmlspecialchars($unit['blood_group']) ?></td>
                    <td><?= htmlspecialchars($unit['component_type']) ?></td>
                    <td><?= htmlspecialchars($unit['storage_location']) ?></td>
                    <td><strong style="color: #e3dada;"><?= $unit['expiry_date'] ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

    <h2>Real-Time Active Stock Levels</h2>
    <div class="grid">
        <?php 
        $all_groups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
        foreach ($all_groups as $group): 
            $count = $current_stock[$group] ?? 0;
        ?>
            <div class="card">
                <h4 style="margin:0; color:#555;"><?= $group ?></h4>
                <h3><?= $count ?> Units</h3>
            </div>
        <?php endforeach; ?>
    </div>

</div>

</body>
</html>