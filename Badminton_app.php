<?php
$pdo = new PDO("mysql:host=localhost;dbname=badminton_queue;charset=utf8", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

date_default_timezone_set('Asia/Manila');

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=badminton_queue;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
// Add player to queue
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_player'])) {
    $name = trim($_POST['player_name']);
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO player_queue (name) VALUES (?)");
        $stmt->execute([$name]);
    }
    
}// Assign player to standby table
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['assign_to_standby'])) {
    $playerId = $_POST['player_id'];
    $tableNumber = $_POST['table_number'];

    // Get player name from queue
    $stmt = $pdo->prepare("SELECT name FROM player_queue WHERE id = ?");
    $stmt->execute([$playerId]);
    $playerName = $stmt->fetchColumn();

    if ($playerName) {
        // Fetch current table data
        $stmt = $pdo->prepare("SELECT * FROM standby_tables WHERE table_number = ?");
        $stmt->execute([$tableNumber]);
        $table = $stmt->fetch();

        if (!$table) {
            // Table doesn't exist yet, insert new
            $stmt = $pdo->prepare("INSERT INTO standby_tables (table_number, player1) VALUES (?, ?)");
            $stmt->execute([$tableNumber, $playerName]);
            $pdo->prepare("DELETE FROM player_queue WHERE id = ?")->execute([$playerId]);
        } else {
            // Table exists, find first empty slot
            for ($i = 1; $i <= 4; $i++) {
                if (empty($table["player$i"])) {
                    $stmt = $pdo->prepare("UPDATE standby_tables SET player$i = ? WHERE table_number = ?");
                    $stmt->execute([$playerName, $tableNumber]);
                    $pdo->prepare("DELETE FROM player_queue WHERE id = ?")->execute([$playerId]);
                    break;
                }
            }
        }
    }
}

// Remove player from standby table
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['remove_from_table'])) {
    $tableNumber = $_POST['table_number'];
    $slot = $_POST['slot'];

    $stmt = $pdo->prepare("SELECT $slot FROM standby_tables WHERE table_number = ?");
    $stmt->execute([$tableNumber]);
    $playerName = $stmt->fetchColumn();

    if ($playerName) {
        // Return player to queue
        $stmt = $pdo->prepare("INSERT INTO player_queue (name) VALUES (?)");
        $stmt->execute([$playerName]);

        // Remove from standby table
        $stmt = $pdo->prepare("UPDATE standby_tables SET $slot = NULL WHERE table_number = ?");
        $stmt->execute([$tableNumber]);

        // Check if table is now empty and delete if so
        $stmt = $pdo->prepare("SELECT * FROM standby_tables WHERE table_number = ?");
        $stmt->execute([$tableNumber]);
        $table = $stmt->fetch();
        
        $isEmpty = true;
        for ($i = 1; $i <= 4; $i++) {
            if (!empty($table["player$i"])) {
                $isEmpty = false;
                break;
            }
        }
        
        if ($isEmpty) {
            $pdo->prepare("DELETE FROM standby_tables WHERE table_number = ?")->execute([$tableNumber]);
        }
    }
}

// Add player directly to standby table
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_to_table'])) {
    $name = trim($_POST['player_name']);
    $tableNumber = $_POST['table_number'];
    
    if (!empty($name)) {
        // Check if table exists
        $stmt = $pdo->prepare("SELECT * FROM standby_tables WHERE table_number = ?");
        $stmt->execute([$tableNumber]);
        $table = $stmt->fetch();
        
        if (!$table) {
            // Create new table with first player
            $stmt = $pdo->prepare("INSERT INTO standby_tables (table_number, player1) VALUES (?, ?)");
            $stmt->execute([$tableNumber, $name]);
        } else {
            // Find first empty slot
            for ($i = 1; $i <= 4; $i++) {
                if (empty($table["player$i"])) {
                    $stmt = $pdo->prepare("UPDATE standby_tables SET player$i = ? WHERE table_number = ?");
                    $stmt->execute([$name, $tableNumber]);
                    break;
                }
            }
        }
    }
}

// Auto-assign standby tables to courts
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['auto_assign_standby'])) {
    // Get all full standby tables
    $fullTables = $pdo->query("SELECT * FROM standby_tables WHERE player1 IS NOT NULL AND player2 IS NOT NULL AND player3 IS NOT NULL AND player4 IS NOT NULL ORDER BY table_number ASC")->fetchAll();
    
    // Get all empty courts
    $emptyCourts = $pdo->query("SELECT * FROM courts WHERE player1 IS NULL AND player2 IS NULL AND player3 IS NULL AND player4 IS NULL ORDER BY court_number ASC")->fetchAll();
    
    $assigned = 0;
    foreach ($fullTables as $table) {
        if ($assigned >= count($emptyCourts)) break;
        
        $court = $emptyCourts[$assigned];
        
        // Assign table to court
        $stmt = $pdo->prepare("UPDATE courts SET player1 = ?, player2 = ?, player3 = ?, player4 = ?, start_time = NOW() WHERE court_number = ?");
        $stmt->execute([$table['player1'], $table['player2'], $table['player3'], $table['player4'], $court['court_number']]);
        
        // Clear the standby table
        $pdo->prepare("DELETE FROM standby_tables WHERE table_number = ?")->execute([$table['table_number']]);
        
        $assigned++;
    }
}

// Manual assign table to court
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['assign_table_to_court'])) {
    $tableNumber = $_POST['table_number'];
    $courtNumber = $_POST['court_number'];

    // Check if court is empty
    $stmt = $pdo->prepare("SELECT * FROM courts WHERE court_number = ?");
    $stmt->execute([$courtNumber]);
    $court = $stmt->fetch();
    
    $isEmpty = true;
    for ($i = 1; $i <= 4; $i++) {
        if (!empty($court["player$i"])) {
            $isEmpty = false;
            break;
        }
    }

    if ($isEmpty) {
        // Fetch players from standby table
        $stmt = $pdo->prepare("SELECT * FROM standby_tables WHERE table_number = ?");
        $stmt->execute([$tableNumber]);
        $table = $stmt->fetch();

        if ($table) {
            // Check if table is full
            $isFull = true;
            for ($i = 1; $i <= 4; $i++) {
                if (empty($table["player$i"])) {
                    $isFull = false;
                    break;
                }
            }
            
            if ($isFull) {
                // Update court with players and start_time
                $stmt = $pdo->prepare("UPDATE courts SET player1 = ?, player2 = ?, player3 = ?, player4 = ?, start_time = NOW() WHERE court_number = ?");
                $stmt->execute([$table['player1'], $table['player2'], $table['player3'], $table['player4'], $courtNumber]);

                // Remove from standby
                $stmt = $pdo->prepare("DELETE FROM standby_tables WHERE table_number = ?");
                $stmt->execute([$tableNumber]);
            }
        }
    }
}

// Assign player to court and auto-start timer if court becomes full
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['assign_player'])) {
    $playerId = $_POST['player_id'];
    $courtNumber = $_POST['court_number'];

    $court = $pdo->prepare("SELECT * FROM courts WHERE court_number = ?");
    $court->execute([$courtNumber]);
    $court = $court->fetch();

    if ($court) {
        for ($i = 1; $i <= 4; $i++) {
            if (empty($court["player$i"])) {
                $stmt = $pdo->prepare("SELECT name FROM player_queue WHERE id = ?");
                $stmt->execute([$playerId]);
                $playerName = $stmt->fetchColumn();

                if ($playerName) {
                    $update = $pdo->prepare("UPDATE courts SET player$i = ?, assigned_at = NOW() WHERE court_number = ?");
                    $update->execute([$playerName, $courtNumber]);
                    $pdo->prepare("DELETE FROM player_queue WHERE id = ?")->execute([$playerId]);
                }
                break;
            }
        }

        // Check if court is now full and set start_time if not already set
        $court = $pdo->prepare("SELECT player1, player2, player3, player4, start_time FROM courts WHERE court_number = ?");
        $court->execute([$courtNumber]);
        $court = $court->fetch();

        if ($court['player1'] && $court['player2'] && $court['player3'] && $court['player4'] && !$court['start_time']) {
            $pdo->prepare("UPDATE courts SET start_time = NOW() WHERE court_number = ?")->execute([$courtNumber]);
        }
    }
}
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['assign_table_to_court'])) {
    $tableNumber = $_POST['table_number'];
    $courtNumber = $_POST['court_number'];

    // Fetch players from standby table
    $stmt = $pdo->prepare("SELECT * FROM standby_tables WHERE table_number = ?");
    $stmt->execute([$tableNumber]);
    $table = $stmt->fetch();

    if ($table) {
        $players = [$table['player1'], $table['player2'], $table['player3'], $table['player4']];

        // Update court with players and start_time
        $stmt = $pdo->prepare("UPDATE courts SET player1 = ?, player2 = ?, player3 = ?, player4 = ?, start_time = NOW() WHERE court_number = ?");
        $stmt->execute([$players[0], $players[1], $players[2], $players[3], $courtNumber]);

        // Remove from standby
        $stmt = $pdo->prepare("DELETE FROM standby_tables WHERE table_number = ?");
        $stmt->execute([$tableNumber]);

        // Optional: Log the assignment or show a message
    }
}



if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['finish_game'])) {
    $courtNumber = $_POST['court_number'];

    $stmt = $pdo->prepare("SELECT * FROM courts WHERE court_number = ?");
    $stmt->execute([$courtNumber]);
    $court = $stmt->fetch();

    if ($court) {
        $players = [];
        for ($i = 1; $i <= 4; $i++) {
            $player = $court["player$i"];
            if ($player) {
                $players[] = $player;

                // Add to queue
                $stmt = $pdo->prepare("INSERT INTO player_queue (name) VALUES (?)");
                $stmt->execute([$player]);

                // Add to revenue
                $stmt = $pdo->prepare("INSERT INTO player_revenue (player_name, court_number, amount) VALUES (?, ?, ?)");
                $stmt->execute([$player, $courtNumber, 30.00]);

                // Update player_stats
                $stmt = $pdo->prepare("INSERT INTO player_stats (name, games_played, total_revenue, last_played) 
                    VALUES (?, 1, 30.00, NOW()) 
                    ON DUPLICATE KEY UPDATE 
                        games_played = games_played + 1, 
                        total_revenue = total_revenue + 30.00, 
                        last_played = NOW()");
                $stmt->execute([$player]);
            }
        }

        // Insert into game_history
        if (!empty($players)) {
            $startTime = $court['start_time'] ?? date("Y-m-d H:i:s");
            $endTime = date("Y-m-d H:i:s");
            $duration = round((strtotime($endTime) - strtotime($startTime)) / 60);

            $stmt = $pdo->prepare("INSERT INTO game_history (court_number, player1, player2, player3, player4, start_time, end_time, duration_minutes, revenue_per_player) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 30.00)");
            $stmt->execute([
                $courtNumber,
                $court["player1"],
                $court["player2"],
                $court["player3"],
                $court["player4"],
                $startTime,
                $endTime,
                $duration
            ]);
        }

        // Clear court
        $stmt = $pdo->prepare("UPDATE courts SET player1 = NULL, player2 = NULL, player3 = NULL, player4 = NULL, shuttlecock = NULL, start_time = NULL WHERE court_number = ?");
        $stmt->execute([$courtNumber]);
    }
}


// Remove player from court and return to queue
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['remove_from_court'])) {
    $courtNumber = $_POST['court_number'];
    $playerSlot = $_POST['player_slot'];

    $stmt = $pdo->prepare("SELECT $playerSlot FROM courts WHERE court_number = ?");
    $stmt->execute([$courtNumber]);
    $playerName = $stmt->fetchColumn();

    if ($playerName) {
        $stmt = $pdo->prepare("INSERT INTO player_queue (name) VALUES (?)");
        $stmt->execute([$playerName]);

        $stmt = $pdo->prepare("UPDATE courts SET $playerSlot = NULL WHERE court_number = ?");
        $stmt->execute([$courtNumber]);
    }
}

// Reset queue
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['reset_queue'])) {
    $pdo->query("DELETE FROM player_queue");
}

// Reset all courts
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['reset_courts'])) {
    $pdo->query("UPDATE courts SET player1 = NULL, player2 = NULL, player3 = NULL, player4 = NULL, shuttlecock = NULL, start_time = NULL");
}

// Update shuttlecock
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_shuttlecock'])) {
    $courtNumber = $_POST['court_number'];
    $shuttlecock = $_POST['shuttlecock'];
    $stmt = $pdo->prepare("UPDATE courts SET shuttlecock = ? WHERE court_number = ?");
    $stmt->execute([$shuttlecock, $courtNumber]);
}

// Finish game
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['finish_game'])) {
    $courtNumber = $_POST['court_number'];
    $stmt = $pdo->prepare("SELECT * FROM courts WHERE court_number = ?");
    $stmt->execute([$courtNumber]);
    $court = $stmt->fetch();

    if ($court) {
        for ($i = 1; $i <= 4; $i++) {
            $player = $court["player$i"];
            if ($player) {
                $stmt = $pdo->prepare("INSERT INTO player_queue (name) VALUES (?)");
                $stmt->execute([$player]);

                $stmt = $pdo->prepare("INSERT INTO player_revenue (player_name, court_number, amount) VALUES (?, ?, ?)");
                $stmt->execute([$player, $courtNumber, 30.00]);
            }
        }

        $stmt = $pdo->prepare("UPDATE courts SET player1 = NULL, player2 = NULL, player3 = NULL, player4 = NULL, shuttlecock = NULL, start_time = NULL WHERE court_number = ?");
        $stmt->execute([$courtNumber]);
    }
    

}

// Fetch data
$courts = $pdo->query("SELECT * FROM courts ORDER BY court_number ASC")->fetchAll();
$queue = $pdo->query("SELECT * FROM player_queue ORDER BY queued_at ASC")->fetchAll();
$playerPlays = $pdo->query("SELECT player_name, COUNT(*) AS games_played, SUM(amount) AS total_revenue FROM player_revenue GROUP BY player_name ORDER BY games_played DESC")->fetchAll();
?>
