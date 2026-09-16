<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db.php';

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $full_name    = trim($_POST['full_name']);
    $gender       = trim($_POST['gender']);
    $dob          = trim($_POST['dob']);
    $age          = intval($_POST['age']);
    $address      = trim($_POST['address']);
    $email        = trim($_POST['email']);
    $phone        = trim($_POST['phone']);
    $nic_passport = trim($_POST['nic_passport']);
    $blood_group  = trim($_POST['blood_group']);
    $password     = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $stmt = $conn->prepare("INSERT INTO users (full_name, gender, dob, age, address, email, phone, nic_passport, blood_group, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt === false) {
            throw new Exception("Database Error: " . $conn->error);
        }

        $stmt->bind_param("sssissssss", $full_name, $gender, $dob, $age, $address, $email, $phone, $nic_passport, $blood_group, $password);

        if ($stmt->execute()) {
            echo "<script>
                alert('Registration Successful! Please Login To Your Account.');
                window.location.href='login.php';
            </script>";
            exit();
        }
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) {
            $message = "This email address is already registered!";
        } else {
            $message = "Error: " . $e->getMessage();
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Donor Registration</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 0; }
        .container { max-width: 500px; margin: 30px auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #3549dc; margin-bottom: 20px; }
        .alert { padding: 10px; background-color: #ece3e4; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; text-align: center; margin-bottom: 15px; }
        .form-group { margin-bottom: 12px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ebc9c9; border-radius: 4px; box-sizing: border-box; }
        input[readonly] { background-color: #e9ecef; cursor: not-allowed; }
        .btn { width: 100%; padding: 12px; background-color: #6084ee; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 16px; margin-top: 10px; }
        .btn:hover { background-color: #4360e5; }
        .footer-text { text-align: center; margin-top: 15px; }
        .footer-text a { color: #53aee2; text-decoration: none; font-weight: bold; }
        .error-text { color: #dc3545; font-size: 13px; font-weight: bold; margin-top: 4px; display: none; }
    </style>
</head>
<body>

<div class="container">
    <h1>Donor Registration</h1>
    <?php if ($message): ?>
        <div class="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form action="register.php" method="POST" id="regForm">
        <div class="form-group">
            <label>Full Name:</label>
            <input type="text" name="full_name" required placeholder="krish Thenu">
        </div>

        <div class="form-group">
            <label>Gender:</label>
            <select name="gender" required>
                <option value="">Select Gender...</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
            </select>
        </div>

        <div class="form-group">
            <label>Date of Birth:</label>
            <input type="date" id="dob" name="dob" required>
        </div>

        <div class="form-group">
            <label>Age:</label>
            <input type="number" id="age" name="age" required placeholder="Auto-calculated" readonly>
        </div>

        <div class="form-group">
            <label>Address:</label>
            <textarea name="address" rows="3" required placeholder="Enter full address"></textarea>
        </div>

        <div class="form-group">
            <label>Email Address:</label>
            <input type="email" name="email" required placeholder="thenu@example.com">
        </div>

        <div class="form-group">
            <label>Phone Number:</label>
            <input type="tel" id="phone" name="phone" required placeholder="0771234567" maxlength="10">
            <small id="phone-error" class="error-text">Phone number cannot exceed 10 digits!</small>
        </div>

        <div class="form-group">
            <label>NIC / Passport Number:</label>
            <input type="text" id="nic_passport" name="nic_passport" required placeholder="199012345678" maxlength="12">
            <small id="nic-error" class="error-text">NIC / Passport Number cannot exceed 12 characters!</small>
        </div>

        <div class="form-group">
            <label>Blood Group:</label>
            <select name="blood_group" required>
                <option value="">Select Blood Group...</option>
                <option value="A+">A+</option><option value="A-">A-</option>
                <option value="B+">B+</option><option value="B-">B-</option>
                <option value="O+">O+</option><option value="O-">O-</option>
                <option value="AB+">AB+</option><option value="AB-">AB-</option>
            </select>
        </div>

        <div class="form-group">
            <label>Password:</label>
            <input type="password" name="password" required placeholder="••••••••">
        </div>

        <button type="submit" name="register" class="btn">Register</button>
    </form>
    <p class="footer-text">Already have an account? <a href="login.php">Login Here</a></p>
</div>

<script>
// 1. Automatic Age Calculator
document.getElementById('dob').addEventListener('change', function() {
    const dobInput = this.value;
    if (dobInput) {
        const dob = new Date(dobInput);
        const today = new Date();
        
        let age = today.getFullYear() - dob.getFullYear();
        const monthDiff = today.getMonth() - dob.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
            age--;
        }
        
        document.getElementById('age').value = age > 0 ? age : 0;
    } else {
        document.getElementById('age').value = '';
    }
});

// 2. Real-Time 12-character IC Validation
const nicInput = document.getElementById('nic_passport');
const nicError = document.getElementById('nic-error');

nicInput.addEventListener('input', function() {
    if (this.value.length > 12) {
        nicError.style.display = 'block';
        this.style.borderColor = '#dc3545';
    } else {
        nicError.style.display = 'none';
        this.style.borderColor = '#ebc9c9';
    }
});

// 3. Real-Time 10-digit Phone Validation
const phoneInput = document.getElementById('phone');
const phoneError = document.getElementById('phone-error');

phoneInput.addEventListener('input', function() {
    // Keep only numbers
    this.value = this.value.replace(/[^0-9]/g, '');
    
    if (this.value.length > 10) {
        phoneError.style.display = 'block';
        this.style.borderColor = '#dc3545';
    } else {
        phoneError.style.display = 'none';
        this.style.borderColor = '#ebc9c9';
    }
});

// 4. Form Submission Control
document.getElementById('regForm').addEventListener('submit', function(e) {
    if (nicInput.value.length > 12) {
        e.preventDefault();
        alert('Please enter a valid NIC / Passport number (maximum 12 characters).');
        return;
    }
    
    if (phoneInput.value.length > 10) {
        e.preventDefault();
        alert('Please enter a valid Phone Number (maximum 10 digits).');
        return;
    }
});
</script>

</body>
</html>