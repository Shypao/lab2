<?php
// DEVELOPMENT: show errors (disable on production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Manila');


try {
    $pdo = new PDO("mysql:host=localhost;dbname=badminton_queue;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

/**
 * Helpers
 */
function safe_int($v, $default = 0) {
    if (!isset($v)) return $default;
    $v = filter_var($v, FILTER_VALIDATE_INT);
    return ($v === false) ? $default : (int)$v;
}

function redirect_self() {
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/**
 * Validate dynamic column names (only player1..player4 allowed)
 */
function validate_player_slot($slot) {
    $allowed = ['player1','player2','player3','player4'];
    return in_array($slot, $allowed, true);
}

/**
 * POST handlers
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // ADD PLAYER to queue
    if (isset($_POST['add_player'])) {
        $name = trim($_POST['player_name'] ?? '');
        if ($name !== '') {
            $stmt = $pdo->prepare("INSERT INTO player_queue (name, queued_at) VALUES (?, NOW())");
            $stmt->execute([$name]);
        }
        redirect_self();
    }

    // ADD directly to a standby table (manual)
    if (isset($_POST['add_to_table'])) {
        $name = trim($_POST['player_name'] ?? '');
        $tableNumber = safe_int($_POST['table_number'] ?? null, 0);
        if ($name !== '' && $tableNumber > 0) {
            $stmt = $pdo->prepare("SELECT * FROM standby_tables WHERE table_number = ?");
            $stmt->execute([$tableNumber]);
            $table = $stmt->fetch();

            if (!$table) {
                $stmt = $pdo->prepare("INSERT INTO standby_tables (table_number, player1) VALUES (?, ?)");
                $stmt->execute([$tableNumber, $name]);
            } else {
                for ($i = 1; $i <= 4; $i++) {
                    if (empty($table["player{$i}"])) {
                        $stmt = $pdo->prepare("UPDATE standby_tables SET player{$i} = ? WHERE table_number = ?");
                        $stmt->execute([$name, $tableNumber]);
                        break;
                    }
                }
            }
        }
        redirect_self();
    }
// ASSIGN a player from queue to a standby table (auto-create new tables as needed)
if (isset($_POST['assign_to_standby'])) {
    $playerId = safe_int($_POST['player_id'] ?? null, 0);

    if ($playerId > 0) {
        // Get player name from queue
        $stmt = $pdo->prepare("SELECT name FROM player_queue WHERE id = ?");
        $stmt->execute([$playerId]);
        $playerName = $stmt->fetchColumn();

        if ($playerName) {
            // Try to find a non-full table
            $tables = $pdo->query("SELECT * FROM standby_tables ORDER BY table_number ASC")->fetchAll();
            $assigned = false;

            foreach ($tables as $table) {
                for ($i = 1; $i <= 4; $i++) {
                    if (empty($table["player$i"])) {
                        $stmt = $pdo->prepare("UPDATE standby_tables SET player{$i} = ? WHERE table_number = ?");
                        $stmt->execute([$playerName, $table['table_number']]);
                        $assigned = true;
                        break 2;
                    }
                }
            }

            // No available spot, create new table
            if (!$assigned) {
                $stmt = $pdo->query("SELECT MAX(table_number) AS max_num FROM standby_tables");
                $nextTable = ($stmt->fetch()['max_num'] ?? 0) + 1;

                $stmt = $pdo->prepare("INSERT INTO standby_tables (table_number, player1) VALUES (?, ?)");
                $stmt->execute([$nextTable, $playerName]);
            }

            // Remove from queue
            $pdo->prepare("DELETE FROM player_queue WHERE id = ?")->execute([$playerId]);
        }
    }
    redirect_self();
}

    

    
    // REMOVE player from standby back to queue
    if (isset($_POST['remove_from_table'])) {
        $tableNumber = safe_int($_POST['table_number'] ?? null, 0);
        $slot = $_POST['slot'] ?? '';

        if ($tableNumber > 0 && validate_player_slot($slot)) {
            $stmt = $pdo->prepare("SELECT {$slot} FROM standby_tables WHERE table_number = ?");
            $stmt->execute([$tableNumber]);
            $playerName = $stmt->fetchColumn();

            if ($playerName) {
                $stmt = $pdo->prepare("INSERT INTO player_queue (name, queued_at) VALUES (?, NOW())");
                $stmt->execute([$playerName]);

                $stmt = $pdo->prepare("UPDATE standby_tables SET {$slot} = NULL WHERE table_number = ?");
                $stmt->execute([$tableNumber]);

                // delete table if empty
                $stmt = $pdo->prepare("SELECT player1, player2, player3, player4 FROM standby_tables WHERE table_number = ?");
                $stmt->execute([$tableNumber]);
                $table = $stmt->fetch();
                $isEmpty = true;
                for ($i = 1; $i <= 4; $i++) {
                    if (!empty($table["player{$i}"])) { $isEmpty = false; break; }
                }
                if ($isEmpty) {
                    $pdo->prepare("DELETE FROM standby_tables WHERE table_number = ?")->execute([$tableNumber]);
                }
            }
        }
        redirect_self();
    }

    // AUTO-ASSIGN full standby tables to empty courts
    if (isset($_POST['auto_assign_standby'])) {
        $fullTables = $pdo->query("
            SELECT * FROM standby_tables
            WHERE player1 IS NOT NULL AND player2 IS NOT NULL AND player3 IS NOT NULL AND player4 IS NOT NULL
            ORDER BY table_number ASC
        ")->fetchAll();

        $emptyCourts = $pdo->query("
            SELECT * FROM courts
            WHERE player1 IS NULL AND player2 IS NULL AND player3 IS NULL AND player4 IS NULL
            ORDER BY court_number ASC
        ")->fetchAll();

        $assigned = 0;
        foreach ($fullTables as $table) {
            if ($assigned >= count($emptyCourts)) break;
            $court = $emptyCourts[$assigned];

            $stmt = $pdo->prepare("UPDATE courts SET player1 = ?, player2 = ?, player3 = ?, player4 = ?, start_time = NOW() WHERE court_number = ?");
            $stmt->execute([$table['player1'], $table['player2'], $table['player3'], $table['player4'], $court['court_number']]);

            $pdo->prepare("DELETE FROM standby_tables WHERE table_number = ?")->execute([$table['table_number']]);
            $assigned++;
        }
        redirect_self();
    }

    // MANUAL assign table to court
    if (isset($_POST['assign_table_to_court'])) {
        $tableNumber = safe_int($_POST['table_number'] ?? null, 0);
        $courtNumber = safe_int($_POST['court_number'] ?? null, 0);

        if ($tableNumber > 0 && $courtNumber > 0) {
            $stmt = $pdo->prepare("SELECT player1, player2, player3, player4 FROM courts WHERE court_number = ?");
            $stmt->execute([$courtNumber]);
            $court = $stmt->fetch();
            $isEmpty = true;
            for ($i = 1; $i <= 4; $i++) {
                if (!empty($court["player{$i}"])) { $isEmpty = false; break; }
            }

            if ($isEmpty) {
                $stmt = $pdo->prepare("SELECT * FROM standby_tables WHERE table_number = ?");
                $stmt->execute([$tableNumber]);
                $table = $stmt->fetch();
                if ($table) {
                    $isFull = true;
                    for ($i = 1; $i <= 4; $i++) {
                        if (empty($table["player{$i}"])) { $isFull = false; break; }
                    }
                    if ($isFull) {
                        $stmt = $pdo->prepare("UPDATE courts SET player1 = ?, player2 = ?, player3 = ?, player4 = ?, start_time = NOW() WHERE court_number = ?");
                        $stmt->execute([$table['player1'], $table['player2'], $table['player3'], $table['player4'], $courtNumber]);

                        $pdo->prepare("DELETE FROM standby_tables WHERE table_number = ?")->execute([$tableNumber]);
                    }
                }
            }
        }
        redirect_self();
    }

    // ASSIGN player directly from queue to court
    if (isset($_POST['assign_player'])) {
        $playerId = safe_int($_POST['player_id'] ?? null, 0);
        $courtNumber = safe_int($_POST['court_number'] ?? null, 0);

        if ($playerId > 0 && $courtNumber > 0) {
            $court = $pdo->prepare("SELECT * FROM courts WHERE court_number = ?");
            $court->execute([$courtNumber]);
            $court = $court->fetch();

            if ($court) {
                for ($i = 1; $i <= 4; $i++) {
                    if (empty($court["player{$i}"])) {
                        $stmt = $pdo->prepare("SELECT name FROM player_queue WHERE id = ?");
                        $stmt->execute([$playerId]);
                        $playerName = $stmt->fetchColumn();

                        if ($playerName) {
                            $update = $pdo->prepare("UPDATE courts SET player{$i} = ?, assigned_at = NOW() WHERE court_number = ?");
                            $update->execute([$playerName, $courtNumber]);
                            $pdo->prepare("DELETE FROM player_queue WHERE id = ?")->execute([$playerId]);
                        }
                        break;
                    }
                }

                // check full and set start_time if needed
                $court = $pdo->prepare("SELECT player1, player2, player3, player4, start_time FROM courts WHERE court_number = ?");
                $court->execute([$courtNumber]);
                $court = $court->fetch();

                if (!empty($court['player1']) && !empty($court['player2']) && !empty($court['player3']) && !empty($court['player4']) && empty($court['start_time'])) {
                    $pdo->prepare("UPDATE courts SET start_time = NOW() WHERE court_number = ?")->execute([$courtNumber]);
                }
            }
        }
        redirect_self();
    }

    // REMOVE player from court and return to queue
    if (isset($_POST['remove_from_court'])) {
        $courtNumber = safe_int($_POST['court_number'] ?? null, 0);
        $playerSlot = $_POST['player_slot'] ?? '';

        if ($courtNumber > 0 && validate_player_slot($playerSlot)) {
            $stmt = $pdo->prepare("SELECT {$playerSlot} FROM courts WHERE court_number = ?");
            $stmt->execute([$courtNumber]);
            $playerName = $stmt->fetchColumn();

            if ($playerName) {
                $stmt = $pdo->prepare("INSERT INTO player_queue (name, queued_at) VALUES (?, NOW())");
                $stmt->execute([$playerName]);

                $stmt = $pdo->prepare("UPDATE courts SET {$playerSlot} = NULL WHERE court_number = ?");
                $stmt->execute([$courtNumber]);
            }
        }
        redirect_self();
    }

    // UPDATE shuttlecock text
    if (isset($_POST['update_shuttlecock'])) {
        $courtNumber = safe_int($_POST['court_number'] ?? null, 0);
        $shuttlecock = trim($_POST['shuttlecock'] ?? '');
        if ($courtNumber > 0) {
            $stmt = $pdo->prepare("UPDATE courts SET shuttlecock = ? WHERE court_number = ?");
            $stmt->execute([$shuttlecock, $courtNumber]);
        }
        redirect_self();
    }

    // RESET queue
    if (isset($_POST['reset_queue'])) {
        $pdo->query("DELETE FROM player_queue");
        redirect_self();
    }

    // RESET all courts
    if (isset($_POST['reset_courts'])) {
        $pdo->query("UPDATE courts SET player1 = NULL, player2 = NULL, player3 = NULL, player4 = NULL, shuttlecock = NULL, start_time = NULL");
        redirect_self();
    }

    // FINISH GAME (transactional)
    if (isset($_POST['finish_game'])) {
        $courtNumber = safe_int($_POST['court_number'] ?? null, 0);
        if ($courtNumber <= 0) {
            echo "Invalid court number.";
            exit;
        }

        try {
            $pdo->beginTransaction();

            // lock court row
            $stmt = $pdo->prepare("SELECT * FROM courts WHERE court_number = ? FOR UPDATE");
            $stmt->execute([$courtNumber]);
            $court = $stmt->fetch();

            if (!$court) {
                throw new Exception("Court not found: {$courtNumber}");
            }

            $players = [];
            for ($i = 1; $i <= 4; $i++) {
                $p = $court["player{$i}"] ?? null;
                if (!empty($p)) $players[] = $p;
            }

            if (!empty($players)) {
                $startTime = $court['start_time'] ?? date("Y-m-d H:i:s");
                $endTime = date("Y-m-d H:i:s");
                $durationMinutes = round((strtotime($endTime) - strtotime($startTime)) / 60);

                // Insert players back to queue and update stats (no revenue)
                $insertQueue = $pdo->prepare("INSERT INTO player_queue (name, queued_at) VALUES (?, NOW())");
                $updateStats = $pdo->prepare("
                    INSERT INTO player_stats (name, games_played, last_played)
                    VALUES (?, 1, NOW())
                    ON DUPLICATE KEY UPDATE
                        games_played = games_played + 1,
                        last_played = NOW()
                ");

                foreach ($players as $p) {
                    $insertQueue->execute([$p]);
                    $updateStats->execute([$p]);
                }

                // log game history (no revenue columns)
                $stmt = $pdo->prepare("INSERT INTO game_history
                    (court_number, player1, player2, player3, player4, start_time, end_time, duration_minutes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $courtNumber,
                    $court['player1'],
                    $court['player2'],
                    $court['player3'],
                    $court['player4'],
                    $startTime,
                    $endTime,
                    $durationMinutes
                ]);
            }

            // clear the court
            $stmt = $pdo->prepare("UPDATE courts SET player1 = NULL, player2 = NULL, player3 = NULL, player4 = NULL, shuttlecock = NULL, start_time = NULL WHERE court_number = ?");
            $stmt->execute([$courtNumber]);

            $pdo->commit();

            redirect_self();
        } catch (Exception $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo "<h3>Finish game error:</h3><pre>" . htmlspecialchars($ex->getMessage()) . "\n\n" . htmlspecialchars($ex->getTraceAsString()) . "</pre>";
            error_log("Finish game exception: " . $ex->getMessage());
            exit;
        }
    }
}

/**
 * Fetch data for UI
 */
$courts = $pdo->query("SELECT * FROM courts ORDER BY court_number ASC")->fetchAll();
$queue = $pdo->query("SELECT * FROM player_queue ORDER BY queued_at ASC")->fetchAll();
$standby_tables = $pdo->query("SELECT * FROM standby_tables ORDER BY table_number ASC")->fetchAll();

// Simplified player plays summary read from player_stats (no revenue column)
$playerPlays = [];
try {
    $playerPlays = $pdo->query("SELECT name, games_played FROM player_stats ORDER BY games_played DESC")->fetchAll();
} catch (Exception $e) {
    // if player_stats doesn't exist or has different columns, just leave empty
    $playerPlays = [];
}
?>