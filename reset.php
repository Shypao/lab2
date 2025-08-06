<?php
date_default_timezone_set('Asia/Manila');

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = new PDO("mysql:host=localhost;dbname=badminton_queue;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

try {
    // Disable FK checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Truncate all
    $pdo->exec("TRUNCATE TABLE player_queue");
    $pdo->exec("TRUNCATE TABLE courts");
    $pdo->exec("TRUNCATE TABLE standby_tables");
    $pdo->exec("TRUNCATE TABLE game_history");
    $pdo->exec("TRUNCATE TABLE player_stats");
    $pdo->exec("TRUNCATE TABLE player_revenue");
    $pdo->exec("TRUNCATE TABLE court_history");

    // Re-enable FK checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Re-insert 9 courts
    for ($i = 1; $i <= 9; $i++) {
        $stmt = $pdo->prepare("INSERT INTO courts (court_number) VALUES (?)");
        $stmt->execute([$i]);
    }

    // Redirect with status
    header("Location: index.php?reset=success");
    exit;

} catch (PDOException $e) {
    // Redirect with error message
    $msg = urlencode("Reset error: " . $e->getMessage());
    header("Location: index.php?reset=error&msg=$msg");
    exit;
}
