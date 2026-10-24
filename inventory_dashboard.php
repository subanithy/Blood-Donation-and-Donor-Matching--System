<?php
ob_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include 'db.php';

// Protect page: Admin/Staff only
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$userRole = strtolower(trim($_SESSION['user']['role'] ?? 'donor'));
if ($userRole !== 'admin' && $userRole !== 'staff') {
    header("Location: home.php");
    exit();
}

$message = '';
$alert_class = 'alert-success';

// Helper function to generate Unique Unit ID
function generateUniqueID() {
    return 'BAG-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 4));
}

// 1. Process "Add New Stock" Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_stock_direct'])) {
    $unit_id          = trim($_POST['unit_id']);
    $blood_group      = trim($_POST['blood_group']);
    $component_type   = trim($_POST['component_type']);
    $collection_date  = trim($_POST['collection_date']);
    $storage_location = trim($_POST['storage_location']);

    // Expiry calculation
    $days_valid = 35; // Whole blood standard
    if ($component_type === 'Platelets') $days_valid = 5;
    if ($component_type === 'FFP') $days_valid = 365;

    $expiry_date = date('Y-m-d', strtotime($collection_date . " + $days_valid days"));

    try {
        $stmt = $conn->prepare("INSERT INTO inventory_units (unit_id, blood_group, component_type, collection_date, expiry_date, storage_location, status) VALUES (?, ?, ?, ?, ?, ?, 'Available')");
        $stmt->bind_param("ssssss", $unit_id, $blood_group, $component_type, $collection_date, $expiry_date, $storage_location);

        if ($stmt->execute()) {
            $message = "Unit " . htmlspecialchars($unit_id) . " added successfully! (Expires: " . $expiry_date . ")";
            $alert_class = 'alert-success';
        }
    } catch (Exception $e) {
        $message = "Error adding stock unit: " . $e->getMessage();
        $alert_class = 'alert-danger';
    }
}

// 2. Fetch Stock Summaries & Analyze Low Stock Levels
$stock_query = "SELECT blood_group, COUNT(*) as total_units FROM inventory_units WHERE status = 'Available' GROUP BY blood_group";
$stock_result = $conn->query($stock_query);

$all_groups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
$counts = array_fill_keys($all_groups, 0);

if ($stock_result) {
    while ($r = $stock_result->fetch_assoc()) {
        $counts[$r['blood_group']] = (int)$r['total_units'];
    }
}

// Define Low Stock Threshold (e.g., less than 3 units is low stock)
$threshold = 3;
$low_stock_groups = [];

foreach ($counts as $grp => $qty) {
    if ($qty < $threshold) {
        $low_stock_groups[] = "$grp ($qty units)";
    }
}

// 3. Fetch Units Expiring Within 3 Days
$expiring_query = "SELECT unit_id, blood_group, component_type, expiry_date FROM inventory_units WHERE status = 'Available' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) ORDER BY expiry_date ASC";
$expiring_result = $conn->query($expiring_query);

// 4. Fetch Recent Logs
$units_query = "SELECT unit_id, blood_group, component_type, storage_location, status, collection_date, expiry_date FROM inventory_units ORDER BY id DESC LIMIT 10";
$units_result = $conn->query($units_query);

$auto_id = generateUniqueID();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Blood Bank Inventory Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0f9ff; margin: 0; padding: 20px; color: #1e293b; }
        .header-bar { background: linear-gradient(135deg, #0284c7, #2563eb); color: white; padding: 20px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.15); }
        .header-bar h1 { margin: 0; font-size: 22px; }
        .header-bar a { color: white; text-decoration: none; font-weight: bold; margin-left: 10px; background: rgba(255,255,255,0.2); padding: 8px 14px; border-radius: 6px; }
        .header-bar a:hover { background: rgba(255,255,255,0.3); }
        
        .main-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px; }
        @media (max-width: 900px) { .main-grid { grid-template-columns: 1fr; } }

        .card { background: white; padding: 20px; border-radius: 8px; border: 1px solid #bae6fd; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .stock-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 10px; margin-bottom: 10px; }
        .stock-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; text-align: center; }
        .stock-box.low { background: #fef2f2; border-color: #fca5a5; }
        .stock-box.low h3 { color: #dc2626; }
        .stock-box h3 { margin: 0; color: #0284c7; font-size: 22px; }
        .stock-box p { margin: 4px 0 0; font-weight: bold; font-size: 13px; color: #64748b; }

        .form-group { margin-bottom: 12px; }
        label { display: block; margin-bottom: 4px; font-weight: bold; font-size: 13px; }
        input, select { width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
        input[readonly] { background-color: #f1f5f9; color: #475569; font-weight: bold; }
        
        .btn-add { background: #16a34a; color: white; border: none; padding: 10px; border-radius: 4px; width: 100%; font-weight: bold; cursor: pointer; font-size: 15px; margin-top: 5px; }
        .btn-add:hover { background: #15803d; }

        .alert { padding: 12px 15px; border-radius: 6px; margin-top: 15px; font-weight: bold; font-size: 14px; }
        .alert-success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-warning { background-color: #fffbe3; color: #b45309; border: 1px solid #fde68a; }

        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; font-size: 14px; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e0f2fe; }
        th { background-color: #e0f2fe; color: #0369a1; }
        .badge { padding: 3px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; color: white; }
        .badge-avail { background-color: #16a34a; }
        .badge-used { background-color: #64748b; }
        .badge-exp { background-color: #ef4444; }
    </style>
</head>
<body>

<div class="header-bar">
    <h1>Blood Bank Authority Dashboard</h1>
    <div>
        <a href="issue_stock.php">- Issue / Deduct Unit</a>
        <a href="request_blood.php">🔍 Emergency Matching Engine</a>
        <a href="logout.php" style="background: #3b82f6;">Logout</a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert <?= $alert_class ?>"><?= $message ?></div>
<?php endif; ?>

<!-- LOW STOCK WARNING ALERT -->
<?php if (!empty($low_stock_groups)): ?>
    <div class="alert alert-warning">
        🚨 <strong>CRITICAL LOW STOCK ALERT:</strong> The following blood groups are running low (below <?= $threshold ?> units available): 
        <span style="color: #dc2626; text-decoration: underline; margin-left: 5px;">
            <?= implode(', ', $low_stock_groups) ?>
        </span>
        — Please request donations or update inventory!
    </div>
<?php endif; ?>

<div class="main-grid">
    <!-- Left Column: Stock Cards & Tables -->
    <div>
        <div class="card">
            <h2 style="margin-top:0; color:#0284c7; font-size:18px;">Current Live Available Stock</h2>
            <div class="stock-cards">
                <?php foreach ($all_groups as $grp): 
                    $qty = $counts[$grp];
                    $is_low = ($qty < $threshold);
                ?>
                    <div class="stock-box <?= $is_low ? 'low' : '' ?>">
                        <h3><?= $qty ?></h3>
                        <p><?= $grp ?> <?= $is_low ? '<span style="color:#dc2626;">(Low)</span>' : '' ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($expiring_result && $expiring_result->num_rows > 0): ?>
            <div class="card" style="border-color: #fca5a5; background: #fff5f5;">
                <h3 style="margin-top:0; color:#991b1b; font-size:16px;">⚠️ Expiration Alert: Units Expiring Within 72h</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Unit ID</th>
                            <th>Group</th>
                            <th>Type</th>
                            <th>Expiry Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($exp = $expiring_result->fetch_assoc()): ?>
                            <tr>
                                <td><b><?= htmlspecialchars($exp['unit_id']) ?></b></td>
                                <td><?= htmlspecialchars($exp['blood_group']) ?></td>
                                <td><?= htmlspecialchars($exp['component_type']) ?></td>
                                <td><span class="badge badge-exp"><?= htmlspecialchars($exp['expiry_date']) ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2 style="margin-top:0; color:#0284c7; font-size:18px;">Recent Blood Inventory Logs</h2>
            <table>
                <thead>
                    <tr>
                        <th>Unit ID</th>
                        <th>Group</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Expiry</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($units_result && $units_result->num_rows > 0): ?>
                        <?php while ($unit = $units_result->fetch_assoc()): ?>
                            <tr>
                                <td><b><?= htmlspecialchars($unit['unit_id']) ?></b></td>
                                <td><?= htmlspecialchars($unit['blood_group']) ?></td>
                                <td><?= htmlspecialchars($unit['component_type']) ?></td>
                                <td><?= htmlspecialchars($unit['storage_location']) ?></td>
                                <td>
                                    <?php if ($unit['status'] === 'Available'): ?>
                                        <span class="badge badge-avail">Available</span>
                                    <?php else: ?>
                                        <span class="badge badge-used"><?= htmlspecialchars($unit['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($unit['expiry_date']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No blood units logged in database.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Column: Direct Add Stock Form -->
    <div>
        <div class="card">
            <h2 style="margin-top:0; color:#16a34a; font-size:18px;">+ Add New Blood Stock</h2>
            <form method="POST" action="inventory_dashboard.php">
                <div class="form-group">
                    <label>Generated Bag ID:</label>
                    <input type="text" name="unit_id" value="<?= $auto_id ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Blood Group:</label>
                    <select name="blood_group" required>
                        <option value="A+">A+</option><option value="A-">A-</option>
                        <option value="B+">B+</option><option value="B-">B-</option>
                        <option value="O+">O+</option><option value="O-">O-</option>
                        <option value="AB+">AB+</option><option value="AB-">AB-</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Component Type:</label>
                    <select name="component_type" required>
                        <option value="Whole Blood">Whole Blood (35 Days Expiry)</option>
                        <option value="PRBC">Packed Red Cells (35 Days Expiry)</option>
                        <option value="Platelets">Platelets (5 Days Expiry)</option>
                        <option value="FFP">Fresh Frozen Plasma (1 Year Expiry)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Collection Date:</label>
                    <input type="date" name="collection_date" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>Storage Location:</label>
                    <input type="text" name="storage_location" required placeholder="e.g. Fridge A - Shelf 2">
                </div>
                <button type="submit" name="add_stock_direct" class="btn-add">Save & Add Stock</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>