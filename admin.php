<?php
session_start();

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=badminton_queue;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

// Is admin?
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// Handle login
if (isset($_POST['admin_login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['is_admin'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $loginError = "Invalid admin credentials.";
    }
}
// yung username and password "admin2"
// Handle logout 
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        body {
            font-family: sans-serif;
            background: #f4f4f4;
            padding: 20px;
        }
        .dashboard {
            max-width: 500px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .logout {
            text-align: right;
        }
        input[type="text"], input[type="password"] {
            padding: 10px;
            width: 100%;
            margin-top: 5px;
            margin-bottom: 15px;
            box-sizing: border-box;
        }
        button {
            padding: 10px 20px;
            background: #333;
            color: white;
            border: none;
            cursor: pointer;
        }
        a {
            text-decoration: none;
            color: #007bff;
        }
        .error {
            color: red;
            margin-bottom: 10px;
        }
        ul {
            margin-top: 20px;
        }
        ul li {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<div class="dashboard">
    <?php if (!$isAdmin): ?>
        <h2>Admin Login</h2>
        <?php if (isset($loginError)): ?>
            <div class="error"><?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>
        <form method="POST">
            <label>Username:</label>
            <input type="text" name="username" required>
            
            <label>Password:</label>
            <input type="password" name="password" required>

            <button type="submit" name="admin_login">Login</button>
        </form>
    <?php else: ?>
        <div class="logout">
            <a href="?logout">Logout</a>
        </div>
        <h2>Welcome, Kevin!</h2>
        <ul>
            <li><a href="index.php">🏸 Go to Main Queue</a></li>
            <li><a href="game_history.php">📜 View Game History</a></li>
            <!-- You can add more admin links here -->
        </ul>
    <?php endif; ?>
</div>

</body>
</html>
