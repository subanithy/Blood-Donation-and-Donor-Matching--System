<?php
ob_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include 'db.php';

$message = '';

if (isset($_POST['login'])) {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, full_name, email, phone, blood_group, status, password, role FROM users WHERE email = ?");
    if (!$stmt) {
        die("SQL Error: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            unset($user['password']);
            $_SESSION['user'] = $user;

            // Route dynamically based on user role
            $userRole = strtolower(trim($user['role'] ?? 'donor'));

            if ($userRole === 'admin' || $userRole === 'staff') {
                header("Location: inventory_dashboard.php");
            } else {
                header("Location: home.php");
            }
            exit();
        } else {
            $message = "Invalid Password!";
        }
    } else {
        $message = "No account found with this email!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Blood Donor & Staff Login Portal</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #e0f2fe, #bae6fd); margin: 0; padding: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #ffffff; border-radius: 12px; box-shadow: 0 10px 25px rgba(2, 132, 199, 0.15); width: 100%; max-width: 420px; overflow: hidden; border: 1px solid #7dd3fc; }
        .card-header { background: linear-gradient(135deg, #0284c7, #2563eb); padding: 28px 20px; text-align: center; color: #ffffff; }
        .card-header h2 { margin: 0; font-size: 22px; font-weight: 700; letter-spacing: 0.5px; }
        .card-header p { margin: 6px 0 0; font-size: 13px; color: #e0f2fe; }
        .card-body { padding: 28px 25px; }
        .alert { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; padding: 10px 15px; border-radius: 6px; font-size: 14px; margin-bottom: 20px; text-align: center; font-weight: 600; }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-weight: 600; color: #1e293b; margin-bottom: 6px; font-size: 14px; }
        input { width: 100%; padding: 12px; background: #f8fafc; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 6px; box-sizing: border-box; font-size: 14px; outline: none; transition: all 0.2s ease-in-out; }
        input:focus { border-color: #0284c7; background: #ffffff; box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.2); }
        .btn { width: 100%; padding: 12px; background: linear-gradient(135deg, #0284c7, #2563eb); color: #ffffff; border: none; border-radius: 6px; font-weight: bold; font-size: 16px; cursor: pointer; transition: background 0.2s; margin-top: 5px; }
        .btn:hover { background: linear-gradient(135deg, #0369a1, #1d4ed8); }
        .card-footer { background-color: #f0f9ff; border-top: 1px solid #e0f2fe; padding: 15px; text-align: center; font-size: 13px; color: #475569; }
        .card-footer a { color: #0284c7; text-decoration: none; font-weight: bold; }
        .card-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="card">
    <div class="card-header">
        <h2>Blood Bank Portal</h2>
        <p>Log in to access your donor profile or administrative control</p>
    </div>
    <div class="card-body">
        <?php if ($message): ?>
            <div class="alert"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- Forms post directly to login.php -->
        <form action="login.php" method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required placeholder="user@example.com">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" name="login" class="btn">Login</button>
        </form>
    </div>
    <div class="card-footer">
        Don't have an account? <a href="register.php">Register Here</a>
        <div style="margin-top: 8px;">
            Are you Blood Bank Staff? <a href="login.php">Staff Login Here</a>
        </div>
    </div>
</div>

</body>
</html>