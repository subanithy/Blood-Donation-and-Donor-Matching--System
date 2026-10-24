<?php
session_start();
include 'db.php';

// 1. Check if donor is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
$donor_blood_group = trim($user['blood_group'] ?? '');
$update_message = '';
$update_error = '';

// 2. Process Last Donation Date Update Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_donation_date'])) {
    $new_date = trim($_POST['last_donation_date']);
    $user_id  = $user['id'];

    if (!empty($new_date)) {
        $stmt = $conn->prepare("UPDATE users SET last_donation_date = ? WHERE id = ?");
        $stmt->bind_param("si", $new_date, $user_id);

        if ($stmt->execute()) {
            $lastDonationTimestamp = strtotime($new_date);
            $daysSinceDonation = floor((time() - $lastDonationTimestamp) / (60 * 60 * 24));
            $isAvailable = ($daysSinceDonation >= 90);

            $_SESSION['user']['last_donation_date'] = $new_date;
            $_SESSION['user']['availability_status'] = $isAvailable ? 'Available' : 'Not Available';
            $_SESSION['user']['last_donation_formatted'] = date('F d, Y', $lastDonationTimestamp);

            $user = $_SESSION['user'];
            $update_message = "Last donation date updated successfully!";
        } else {
            $update_error = "Database Error: Failed to update record.";
        }
    } else {
        $update_error = "Please select a valid date.";
    }
}

$availability_status = $user['availability_status'] ?? 'Available';
$last_donation_formatted = $user['last_donation_formatted'] ?? 'No Prior History';

// 3. Query blood requests with flexible blood group matching
$requests_result = false;
if (!empty($donor_blood_group)) {
    $clean_bg = str_replace(' ', '', $donor_blood_group);
    $stmt = $conn->prepare("SELECT * FROM blood_requests WHERE REPLACE(blood_group, ' ', '') = ? ORDER BY id DESC");
    if ($stmt) {
        $stmt->bind_param("s", $clean_bg);
        $stmt->execute();
        $requests_result = $stmt->get_result();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Donor Dashboard - Blood Bank</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f9ff; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 20px; border: 1px solid #bae6fd; }
        .header { display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #0284c7, #2563eb); color: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 20px; }
        .header a { color: white; background: rgba(255,255,255,0.2); padding: 8px 12px; border-radius: 5px; text-decoration: none; font-weight: bold; }
        
        /* Profile Layout */
        .profile-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px; }
        .profile-item { background: #f8fafc; padding: 12px 15px; border-radius: 6px; border: 1px solid #e2e8f0; }
        .profile-item label { display: block; font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 700; margin-bottom: 4px; }
        .profile-item span { font-size: 14px; font-weight: 600; color: #0f172a; }

        /* Badges */
        .badge { background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 4px; font-size: 13px; font-weight: bold; display: inline-block; }
        .badge-urgent { background: #fee2e2; color: #991b1b; }
        .badge-status-available { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .badge-status-unavailable { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

        /* Form */
        .update-form { display: flex; gap: 10px; align-items: center; max-width: 450px; margin-top: 10px; }
        .update-form input[type="date"] { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; flex: 1; outline: none; }
        .btn-update { background: #0284c7; color: white; border: none; padding: 9px 16px; border-radius: 6px; font-weight: bold; font-size: 14px; cursor: pointer; }
        
        /* Alerts */
        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #86efac; padding: 10px 15px; border-radius: 6px; font-size: 14px; margin-bottom: 10px; font-weight: 600; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; padding: 10px 15px; border-radius: 6px; font-size: 14px; margin-bottom: 10px; font-weight: 600; }

        /* Table */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        th { background: #f8fafc; color: #475569; }
        .btn-respond { background: #16a34a; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 13px; font-weight: bold; display: inline-block; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h2>Welcome, <?= htmlspecialchars($user['full_name']) ?>!</h2>
        <a href="logout.php">Logout</a>
    </div>

    <!-- Profile Summary -->
    <div class="card">
        <h3 style="margin-top:0; color:#0f172a;">Donor Profile Summary</h3>
        <div class="profile-grid">
            <div class="profile-item">
                <label>Email Address</label>
                <span><?= htmlspecialchars($user['email']) ?></span>
            </div>
            <div class="profile-item">
                <label>Blood Group</label>
                <span class="badge"><?= htmlspecialchars($donor_blood_group ?: 'Not Specified') ?></span>
            </div>
            <div class="profile-item">
                <label>Availability Status</label>
                <?php if ($availability_status === 'Available'): ?>
                    <span class="badge badge-status-available">🟢 Available to Donate</span>
                <?php else: ?>
                    <span class="badge badge-status-unavailable">🔴 Not Available (Cooldown)</span>
                <?php endif; ?>
            </div>
            <div class="profile-item">
                <label>Last Donation Date</label>
                <span><?= htmlspecialchars($last_donation_formatted) ?></span>
            </div>
        </div>
    </div>

    <!-- Update Date Form -->
    <div class="card">
        <h3 style="margin-top:0; color:#0f172a;">Update Last Donation Date</h3>
        
        <?php if ($update_message): ?>
            <div class="alert-success"><?= htmlspecialchars($update_message) ?></div>
        <?php endif; ?>

        <?php if ($update_error): ?>
            <div class="alert-error"><?= htmlspecialchars($update_error) ?></div>
        <?php endif; ?>

        <form method="POST" action="home.php" class="update-form">
            <input 
                type="date" 
                name="last_donation_date" 
                max="<?= date('Y-m-d') ?>" 
                value="<?= htmlspecialchars($user['last_donation_date'] ?? '') ?>" 
                required
            >
            <button type="submit" name="update_donation_date" class="btn-update">
                Save Date
            </button>
        </form>
    </div>

    <!-- Matching Blood Requests -->
    <div class="card">
        <h3 style="color: #0284c7; margin-top:0;">🚨 Urgent Requests Matching Your Blood Group (<?= htmlspecialchars($donor_blood_group ?: 'N/A') ?>)</h3>
        
        <?php if ($requests_result && $requests_result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Hospital</th>
                        <th>Units</th>
                        <th>Urgency / Status</th>
                        <th>Contact</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($req = $requests_result->fetch_assoc()): ?>
                        <?php 
                            // Automatic Fallback Matching for Column Names
                            $patient  = $req['patient_name'] ?? $req['patient'] ?? $req['name'] ?? $req['full_name'] ?? 'N/A';
                            $hospital = $req['hospital_name'] ?? $req['hospital'] ?? $req['location'] ?? 'N/A';
                            $units    = $req['units_needed'] ?? $req['units'] ?? $req['quantity'] ?? '1';
                            $urgency  = $req['urgency_level'] ?? $req['urgency'] ?? $req['status'] ?? 'Urgent';
                            $phone    = $req['contact_phone'] ?? $req['phone'] ?? $req['contact'] ?? $req['mobile'] ?? '';
                        ?>
                        <tr>
                            <td><b><?= htmlspecialchars($patient) ?></b></td>
                            <td><?= htmlspecialchars($hospital) ?></td>
                            <td><?= htmlspecialchars($units) ?> Unit(s)</td>
                            <td><span class="badge badge-urgent"><?= htmlspecialchars($urgency) ?></span></td>
                            <td><?= htmlspecialchars($phone ?: 'N/A') ?></td>
                            <td>
                                <?php if (!empty($phone)): ?>
                                    <a href="tel:<?= htmlspecialchars($phone) ?>" class="btn-respond">Contact / Donate</a>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">No phone</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color: #64748b; margin-bottom: 0;">No active requests found matching blood group <b><?= htmlspecialchars($donor_blood_group ?: 'N/A') ?></b> in the database.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>