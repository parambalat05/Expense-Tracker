<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require 'db.php';

$message = "";

// Handle Login
if (isset($_POST['login'])) {
    $username = trim($_POST['login_username'] ?? '');
    $password = trim($_POST['login_password'] ?? '');

    if ($username !== '' && $password !== '') {
        $stmt = $conn->prepare("SELECT id, password, role, preferred_currency, theme FROM users WHERE username=? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($id, $hash, $role, $pref_curr, $theme);
        if ($stmt->fetch() && password_verify($password, $hash)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['preferred_currency'] = $pref_curr;
            $_SESSION['theme'] = $theme;
            header("Location: dashboard.php");
            exit();
        } else {
            $message = "Invalid username or password.";
        }
        $stmt->close();
    } else {
        $message = "All fields are required.";
    }
}

// Handle Registration
if (isset($_POST['register'])) {
    $username = trim($_POST['reg_username'] ?? '');
    $password = trim($_POST['reg_password'] ?? '');
    $role = ($_POST['reg_role'] ?? 'user') === 'admin' ? 'admin' : 'user';
    $pref_currency = $_POST['reg_currency'] ?? 'INR';

    if ($username !== '' && $password !== '') {
        $check = $conn->prepare("SELECT id FROM users WHERE username=?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $message = "Username already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, preferred_currency) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $hash, $role, $pref_currency);
            $stmt->execute();
            $stmt->close();
            $message = "Registration successful. Please login.";
        }
        $check->close();
    } else {
        $message = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Expense Tracker - Login & Register</title>
    <style>
        body {
            margin: 0; font-family: Arial, sans-serif; min-height: 100vh;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #6c5ce7, #00cec9); color: white;
        }
        header, footer { width: 100%; background: rgba(0,0,0,0.6); padding: 15px; text-align: center; }
        .wrap { width: 92%; max-width: 1000px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card {
            background: rgba(255,255,255,0.1); padding: 20px; border-radius: 10px; text-align: center;
            box-shadow: 0 6px 18px rgba(0,0,0,0.2);
        }
        input, select, button {
            width: 92%; padding: 10px; margin: 8px 0; border: none; border-radius: 6px;
        }
        button { background: #fff; color: #6c5ce7; font-weight: bold; cursor: pointer; }
        button:hover { background: #f1f1f1; }
        .message { color: #ffe082; margin-bottom: 10px; font-weight: bold; }
        footer { margin-top: 20px; }
        @media (max-width: 800px) { .wrap { grid-template-columns: 1fr; } }
    </style>
    <script>
        function swap(to) {
            document.getElementById('loginCard').style.display = (to==='login')?'block':'none';
            document.getElementById('regCard').style.display   = (to==='register')?'block':'none';
        }
    </script>
</head>
<body>
<header>
    <h2>Expense Tracker - Login / Register</h2>
</header>

<?php if (!empty($message)) echo "<div class='message'>{$message}</div>"; ?>

<div class="wrap">
    <div id="loginCard" class="card">
        <h3>Login</h3>
        <form method="post">
            <input type="text" name="login_username" placeholder="Username" required>
            <input type="password" name="login_password" placeholder="Password" required>
            <button type="submit" name="login">Login</button>
        </form>
        <p><a href="#" onclick="swap('register')" style="color:#fff;">No account? Register</a></p>
    </div>
    <div id="regCard" class="card" style="display:none;">
        <h3>Register</h3>
        <form method="post">
            <input type="text" name="reg_username" placeholder="Choose Username" required>
            <input type="password" name="reg_password" placeholder="Choose Password" required>
            <select name="reg_role">
                <option value="user">User</option>
                <option value="admin">Admin</option>
            </select>
            <select name="reg_currency">
                <option value="INR">INR (₹)</option>
                <option value="USD">USD ($)</option>
                <option value="EUR">EUR (€)</option>
                <option value="GBP">GBP (£)</option>
            </select>
            <button type="submit" name="register">Create Account</button>
        </form>
        <p><a href="#" onclick="swap('login')" style="color:#fff;">Have an account? Login</a></p>
    </div>
</div>

<footer>
    <p>&copy; <?php echo date("Y"); ?> Expense Tracker</p>
</footer>
</body>
</html>
