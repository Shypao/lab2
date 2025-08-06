
<?php
try {
    $pdo = new PDO("sqlite:badminton_queue.db");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "SQLite database 'badminton_queue.db' connected successfully.<br>";
    
    // Create player_queue table
    $pdo->exec("CREATE TABLE IF NOT EXISTS player_queue (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        queued_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'player_queue' created successfully.<br>";

    // Create courts table
    $pdo->exec("CREATE TABLE IF NOT EXISTS courts (
        court_number INTEGER PRIMARY KEY,
        player1 TEXT,
        player2 TEXT,
        player3 TEXT,
        player4 TEXT,
        shuttlecock TEXT,
        start_time DATETIME,
        assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'courts' created successfully.<br>";

    // Create standby_tables table
    $pdo->exec("CREATE TABLE IF NOT EXISTS standby_tables (
        table_number INTEGER PRIMARY KEY,
        player1 TEXT,
        player2 TEXT,
        player3 TEXT,
        player4 TEXT
    )");
    echo "Table 'standby_tables' created successfully.<br>";

    // Create player_stats table
    $pdo->exec("CREATE TABLE IF NOT EXISTS player_stats (
        name TEXT PRIMARY KEY,
        games_played INTEGER DEFAULT 0,
        total_revenue REAL DEFAULT 0.0,
        last_played DATETIME
    )");
    echo "Table 'player_stats' created successfully.<br>";

    // Create game_history table
    $pdo->exec("CREATE TABLE IF NOT EXISTS game_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        court_number INTEGER,
        player1 TEXT,
        player2 TEXT,
        player3 TEXT,
        player4 TEXT,
        start_time DATETIME,
        end_time DATETIME,
        duration_minutes INTEGER,
        revenue_per_player REAL DEFAULT 30.0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'game_history' created successfully.<br>";

    // Create player_revenue table
    $pdo->exec("CREATE TABLE IF NOT EXISTS player_revenue (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        player_name TEXT,
        court_number INTEGER,
        amount REAL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'player_revenue' created successfully.<br>";

    // Insert initial courts data
    for ($i = 1; $i <= 10; $i++) {
        $pdo->exec("INSERT OR IGNORE INTO courts (court_number) VALUES ($i)");
    }
    echo "Initial courts (1-10) inserted successfully.<br>";

    echo "<br><strong>Database setup completed successfully!</strong><br>";
    echo "<a href='index.php'>Go to Badminton Queue System</a>";

} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage());
}
?>
