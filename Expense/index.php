<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Expense Tracker - Home</title>
    <style>
        body {
            margin: 0; padding: 0; font-family: Arial, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #6c5ce7, #00cec9);
            display: flex; flex-direction: column; color: white; text-align: center;
        }
        header, footer { background: rgba(0,0,0,0.6); padding: 15px; }
        .content { flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; }
        h1 { font-size: 48px; margin-bottom: 10px; }
        p { font-size: 20px; margin-bottom: 20px; }
        a { display: inline-block; background: #fff; color: #6c5ce7; padding: 12px 25px; border-radius: 6px; text-decoration: none; font-weight: bold; }
        a:hover { background: #f1f1f1; }
        footer { margin-top: auto; }
    </style>
</head>
<body>
<header>
    <h2>Welcome to Expense Tracker</h2>
</header>

<div class="content">
    <h1>Track Your Expenses Smartly</h1>
    <p>Manage your daily, monthly, and yearly finances with insights and charts.</p>
    <a href="login.php">Go to Login</a>
</div>

<footer>
    <p>&copy; <?php echo date("Y"); ?> Expense Tracker. All rights reserved.</p>
</footer>
</body>
</html>
