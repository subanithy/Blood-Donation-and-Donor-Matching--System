<?php
session_start();
include 'db.php';

$message = '';

// Helper function to generate a readable unique ID
function generateUniqueID() {
    return 'BAG-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 4));
}

// Generate an ID for display in the form
$auto_unit_id = generateUniqueID();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_stock'])) {
    $unit_id          = trim($_POST['unit_id']);
    $blood_group      = trim($_POST['blood_group']);
    $component_type   = trim($_POST['component_type']);
    $collection_date  = trim($_POST['collection_date']);
    $storage_location = trim($_POST['storage_location']);

    // Auto-calculate expiry based on component
    $days_valid = 35; // Standard for Whole Blood & PRBC
    if ($component_type === 'Platelets') $days_valid = 5;
    if ($component_type === 'FFP') $days_valid = 365;

    $expiry_date = date('Y-m-d', strtotime($collection_date . " + $days_valid days"));

    try {
        $stmt = $conn->prepare("INSERT INTO inventory_units (unit_id, blood_group, component_type, collection_date, expiry_date, storage_location) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $unit_id, $blood_group, $component_type, $collection_date, $expiry_date, $storage_location);

        if ($stmt->execute()) {
            $message = "Unit logged successfully! Assigned Unique ID: " . $unit_id . " | Expiry: " . $expiry_date;
            // Generate a fresh ID for the next entry
            $auto_unit_id = generateUniqueID();
        }
    } catch (mysqli_sql_exception $e) {
        $message = "Error: Unique ID collision or database issue. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock Entry - Auto ID</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 0; }
        .navbar { background-color: #bedaeb; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .navbar h2 { margin: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        input[readonly] { background-color: #e9ecef; color: #495057; font-weight: bold; }
        .btn { background: #28a745; color: #fff; padding: 12px; border: none; border-radius: 4px; width: 100%; font-size: 16px; cursor: pointer; font-weight: bold; }
        .btn:hover { background: #218838; }
        .alert { padding: 10px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="navbar">
    <h1><font color=green>Blood Inventory Management</h1>
    <a href="inventory_dashboard.php" style="color: green; text-decoration: none; font-weight: bold;">View Inventory Dashboard</a>
</div>

<div class="container">
    <h1>Log Blood Bank Unit</h1>
    <?php if ($message): ?>
        <div class="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" action="add_stock.php">
        <div class="form-group">
            <label>Auto-Generated Unique Unit ID:</label>
            <!-- Auto-populated & Readonly so user cannot manually mess up format -->
            <input type="text" name="unit_id" value="<?= $auto_unit_id ?>" readonly>
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
                <option value="Whole Blood">Whole Blood</option>
                <option value="PRBC">PRBC (Packed Red Blood Cells)</option>
                <option value="Platelets">Platelets</option>
                <option value="FFP">FFP (Fresh Frozen Plasma)</option>
            </select>
        </div>
        <div class="form-group">
            <label>Collection Date:</label>
            <input type="date" name="collection_date" required value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
            <label>Storage Location Identifier:</label>
            <input type="text" name="storage_location" required placeholder="Fridge 01 - Shelf B">
        </div>
        <button type="submit" name="add_stock" class="btn">Add to Blood Inventory</button>
    </form>
</div>

</body>
</html>