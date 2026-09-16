<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user']['id'];

// Handle Profile, Availability Status & Last Donation Date Update
if (isset($_POST['update_profile'])) {
    $full_name          = trim($_POST['full_name']);
    $phone              = trim($_POST['phone']);
    $address            = trim($_POST['address']);
    $status             = trim($_POST['status']);
    $last_donation_date = !empty($_POST['last_donation_date']) ? $_POST['last_donation_date'] : NULL;

    $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, address = ?, status = ?, last_donation_date = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $full_name, $phone, $address, $status, $last_donation_date, $user_id);

    if ($stmt->execute()) {
        // Refresh session variables
        $_SESSION['user']['full_name']          = $full_name;
        $_SESSION['user']['phone']              = $phone;
        $_SESSION['user']['address']            = $address;
        $_SESSION['user']['status']             = $status;
        $_SESSION['user']['last_donation_date'] = $last_donation_date;
        header("Location: home.php");
        exit();
    }
}

$user = $_SESSION['user'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Donor Home Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 0; }
        .navbar { background-color: #2599e1; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .navbar h2 { margin: 0; }
        .logout-btn { color: black; text-decoration: none; border: 1px solid white; padding: 6px 12px; border-radius: 4px; font-weight: bold; }
        .logout-btn:hover { background-color: white; color: #218d9b; }
        .container { max-width: 600px; margin: 40px auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: center; position: relative; }
        .blood-badge { background-color: #17efd6; color: white; font-size: 24px; font-weight: bold; padding: 8px 18px; border-radius: 50px; display: inline-block; margin-bottom: 10px; }
        .status-available { color: #3f0671; font-weight: bold; }
        .status-unavailable { color: #926de2; font-weight: bold; }
        .btn-edit { background-color: #ffc107; color: #333; padding: 10px 20px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; margin-top: 15px; font-size: 15px; }
        .btn-edit:hover { background-color: #e0a800; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fff; margin: 5% auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 450px; text-align: left; }
        .modal-content h3 { margin-top: 0; color: #2599e1; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .modal-actions { text-align: right; margin-top: 15px; }
        .btn-save { background-color: #2599e1; color: white; border: none; padding: 8px 15px; border-radius: 4px; font-weight: bold; cursor: pointer; }
        .btn-cancel { background-color: #6c757d; color: white; border: none; padding: 8px 15px; border-radius: 4px; font-weight: bold; cursor: pointer; margin-right: 5px; }
    </style>
</head>
<body>

<div class="navbar">
    <h2>Blood Donation System</h2>
    <a href="logout.php" class="logout-btn">Logout</a>
</div>

<div class="container">
    <div class="blood-badge"><?= htmlspecialchars($user['blood_group']) ?></div>
    <h2>Welcome, <?= htmlspecialchars($user['full_name']) ?></h2>
    <p style="color: #6c757d;"><?= htmlspecialchars($user['email']) ?> | <?= htmlspecialchars($user['phone']) ?></p>
    <p style="color: #6c757d;">Address: <?= htmlspecialchars($user['address'] ?? 'No address provided') ?></p>
    <hr>
    
    <p>Current Availability Status: 
        <span class="<?= $user['status'] == 'Available' ? 'status-available' : 'status-unavailable' ?>">
            <?= htmlspecialchars($user['status']) ?>
        </span>
    </p>

    <p><strong>Last Donation Date:</strong> 
        <?= !empty($user['last_donation_date']) ? htmlspecialchars($user['last_donation_date']) : '<em>Never / Not Recorded</em>' ?>
    </p>

    <button type="button" class="btn-edit" onclick="openModal()">Edit Profile</button>
</div>

<!-- Edit Profile Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <h3>Edit Profile Details</h3>
        <form action="home.php" method="POST">
            <div class="form-group">
                <label>Full Name:</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
            </div>
            <div class="form-group">
                <label>Phone Number:</label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" required>
            </div>
            <div class="form-group">
                <label>Address:</label>
                <textarea name="address" rows="3" required><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label>Availability Status:</label>
                <select name="status" required>
                    <option value="Available" <?= $user['status'] === 'Available' ? 'selected' : '' ?>>Available</option>
                    <option value="Unavailable" <?= $user['status'] === 'Unavailable' ? 'selected' : '' ?>>Unavailable</option>
                </select>
            </div>
            <div class="form-group">
                <label>Last Donation Date:</label>
                <input type="date" name="last_donation_date" value="<?= htmlspecialchars($user['last_donation_date'] ?? '') ?>">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" name="update_profile" class="btn-save">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('editModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

</body>
</html>