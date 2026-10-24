<?php
ob_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include 'db.php';

// Protect page: require login
if (!isset($_SESSION['user'])) {
    header("Location: admin_login.php");
    exit();
}

$message = '';
$alert_class = 'alert-info';
$matching_inventory = [];
$matching_donors = [];

// Helper function for medical compatibility rules
function getCompatibleBloodGroups($requested_group) {
    $compatibility = [
        'A+'  => ['A+', 'A-', 'O+', 'O-'],
        'A-'  => ['A-', 'O-'],
        'B+'  => ['B+', 'B-', 'O+', 'O-'],
        'B-'  => ['B-', 'O-'],
        'AB+' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
        'AB-' => ['AB-', 'A-', 'B-', 'O-'],
        'O+'  => ['O+', 'O-'],
        'O-'  => ['O-']
    ];
    return $compatibility[$requested_group] ?? [$requested_group];
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_request'])) {
    $hospital_name  = trim($_POST['hospital_name']);
    $blood_group    = trim($_POST['blood_group']);
    $component_type = trim($_POST['component_type']);
    $units_needed   = intval($_POST['units_needed']);
    $urgency_level  = trim($_POST['urgency_level']);

    // 1. Save request to database
    try {
        $stmt = $conn->prepare("INSERT INTO blood_requests (hospital_name, blood_group, component_type, units_needed, urgency_level) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssis", $hospital_name, $blood_group, $component_type, $units_needed, $urgency_level);
        $stmt->execute();
        $message = "Blood request created! Running compatibility matching engine...";
        $alert_class = 'alert-success';
    } catch (Exception $e) {
        $message = "Processing donor matching...";
    }

    // 2. Cross-Compatibility Matrix Evaluation
    $compatible_groups = getCompatibleBloodGroups($blood_group);
    $in_clause = "'" . implode("','", $compatible_groups) . "'";

    // Step A: Check available internal inventory
    $inv_sql = "SELECT unit_id, blood_group, component_type, storage_location, expiry_date 
                FROM inventory_units 
                WHERE status = 'Available' 
                AND blood_group IN ($in_clause) 
                AND component_type = ? 
                ORDER BY expiry_date ASC";
    
    $inv_stmt = $conn->prepare($inv_sql);
    $inv_stmt->bind_param("s", $component_type);
    $inv_stmt->execute();
    $matching_inventory = $inv_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Step B: Query eligible available donors (Cooldown check: 90 days since last donation)
    $donor_sql = "SELECT full_name, email, phone, blood_group, address, last_donation_date 
                  FROM users 
                  WHERE status = 'Available' 
                  AND role = 'donor' 
                  AND blood_group IN ($in_clause)
                  AND (last_donation_date IS NULL OR last_donation_date <= DATE_SUB(CURDATE(), INTERVAL 90 DAY))";
    
    $donor_res = $conn->query($donor_sql);
    if ($donor_res) {
        $matching_donors = $donor_res->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Blood Request & Matching Engine</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0f9ff; margin: 0; padding: 20px; color: #1e293b; }
        .navbar { background: linear-gradient(135deg, #0284c7, #2563eb); color: white; padding: 15px 25px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 900px; margin: 20px auto; background: #fff; padding: 25px; border-radius: 8px; border: 1px solid #bae6fd; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 4px; box-sizing: border-box; }
        .btn { background: #0284c7; color: #fff; padding: 12px; border: none; border-radius: 4px; width: 100%; font-size: 16px; cursor: pointer; font-weight: bold; }
        .btn:hover { background: #0369a1; }
        .alert { padding: 12px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; }
        .alert-success { background-color: #d1fae5; color: #065f46; }
        .alert-info { background-color: #dbeafe; color: #1e40af; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; border: 1px solid #e0f2fe; text-align: left; }
        th { background-color: #e0f2fe; color: #0369a1; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; color: white; }
    </style>
</head>
<body>

<div class="navbar">
    <h2>Blood Matching & Request System</h2>
    <a href="inventory_dashboard.php" style="color: white; text-decoration: none; font-weight: bold;">View Dashboard</a>
</div>

<div class="container">
    <h2>Create Emergency Blood Request</h2>

    <?php if ($message): ?>
        <div class="alert <?= $alert_class ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" action="request_blood.php">
        <div class="form-group">
            <label>Hospital / Clinic Name:</label>
            <input type="text" name="hospital_name" required placeholder="General Hospital - Emergency Unit">
        </div>
        <div style="display: flex; gap: 15px;">
            <div class="form-group" style="flex: 1;">
                <label>Required Blood Group:</label>
                <select name="blood_group" required>
                    <option value="A+">A+</option><option value="A-">A-</option>
                    <option value="B+">B+</option><option value="B-">B-</option>
                    <option value="O+">O+</option><option value="O-">O-</option>
                    <option value="AB+">AB+</option><option value="AB-">AB-</option>
                </select>
            </div>
            <div class="form-group" style="flex: 1;">
                <label>Component Type:</label>
                <select name="component_type" required>
                    <option value="Whole Blood">Whole Blood</option>
                    <option value="PRBC">PRBC (Packed Red Blood Cells)</option>
                    <option value="Platelets">Platelets</option>
                    <option value="FFP">FFP (Fresh Frozen Plasma)</option>
                </select>
            </div>
        </div>
        <div style="display: flex; gap: 15px;">
            <div class="form-group" style="flex: 1;">
                <label>Units Needed:</label>
                <input type="number" name="units_needed" min="1" value="1" required>
            </div>
            <div class="form-group" style="flex: 1;">
                <label>Urgency Level:</label>
                <select name="urgency_level" required>
                    <option value="Standard">Standard</option>
                    <option value="Urgent">Urgent</option>
                    <option value="Emergency/Critical">Emergency / Critical</option>
                </select>
            </div>
        </div>
        <button type="submit" name="submit_request" class="btn">Run Matching Engine & Search Stock</button>
    </form>

    <?php if ($_SERVER["REQUEST_METHOD"] == "POST"): ?>
        <hr style="margin: 30px 0; border: 0; border-top: 1px solid #cbd5e1;">

        <h3>1. Matching In-Stock Units (Medical Compatibility Rules)</h3>
        <?php if (count($matching_inventory) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Unit ID</th>
                        <th>Blood Group</th>
                        <th>Component</th>
                        <th>Location</th>
                        <th>Expiry Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matching_inventory as $inv): ?>
                        <tr>
                            <td><b><?= htmlspecialchars($inv['unit_id']) ?></b></td>
                            <td><span class="badge" style="background:#0284c7;"><?= htmlspecialchars($inv['blood_group']) ?></span></td>
                            <td><?= htmlspecialchars($inv['component_type']) ?></td>
                            <td><?= htmlspecialchars($inv['storage_location']) ?></td>
                            <td><?= htmlspecialchars($inv['expiry_date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color: #ef4444; font-weight: bold;">No matching units in current inventory.</p>
        <?php endif; ?>

        <h3>2. Eligible & Compatible Registered Donors</h3>
        <?php if (count($matching_donors) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Donor Name</th>
                        <th>Blood Group</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Location</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matching_donors as $donor): ?>
                        <tr>
                            <td><b><?= htmlspecialchars($donor['full_name']) ?></b></td>
                            <td><span class="badge" style="background:#16a34a;"><?= htmlspecialchars($donor['blood_group']) ?></span></td>
                            <td><?= htmlspecialchars($donor['phone']) ?></td>
                            <td><?= htmlspecialchars($donor['email']) ?></td>
                            <td><?= htmlspecialchars($donor['address']) ?></td>
                            <td><button onclick="alert('Alert dispatched to <?= htmlspecialchars($donor['full_name']) ?>!')" style="background:#2563eb; color:white; border:none; padding:5px 10px; border-radius:4px; cursor:pointer;">Alert Donor</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color: #ef4444; font-weight: bold;">No eligible compatible donors found at this moment.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>