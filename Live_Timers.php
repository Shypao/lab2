<?php
$pdo = new PDO("mysql:host=localhost;dbname=badminton_queue;charset=utf8", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Add player to queue
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_player'])) {
    $name = trim($_POST['player_name']);
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO player_queue (name) VALUES (?)");
        $stmt->execute([$name]);
    }
}

// Assign player to court (and auto start when full)
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
                    $update = $pdo->prepare("UPDATE courts SET player$i = ? WHERE court_number = ?");
                    $update->execute([$playerName, $courtNumber]);

                    $pdo->prepare("DELETE FROM player_queue WHERE id = ?")->execute([$playerId]);
                }
                break;
            }
        }

        // Refresh court data
        $court = $pdo->prepare("SELECT * FROM courts WHERE court_number = ?");
        $court->execute([$courtNumber]);
        $court = $court->fetch();

        // Check if court is now full and start timer if not started
        $allFilled = true;
        for ($i = 1; $i <= 4; $i++) {
            if (empty($court["player$i"])) {
                $allFilled = false;
                break;
            }
        }
        if ($allFilled && empty($court['start_time'])) {
            $pdo->prepare("UPDATE courts SET start_time = NOW() WHERE court_number = ?")->execute([$courtNumber]);
        }
    }
}

// Start game manually
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['start_game'])) {
    $courtNumber = $_POST['court_number'];
    $pdo->prepare("UPDATE courts SET start_time = NOW() WHERE court_number = ?")->execute([$courtNumber]);
}

// Finish game: reset court, return players to queue, calculate revenue and time
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['finish_game'])) {
    $courtNumber = $_POST['court_number'];
    $court = $pdo->prepare("SELECT * FROM courts WHERE court_number = ?");
    $court->execute([$courtNumber]);
    $court = $court->fetch();

    $players = [];
    for ($i = 1; $i <= 4; $i++) {
        if (!empty($court["player$i"])) {
            $players[] = $court["player$i"];
        }
    }

    $startTime = strtotime($court['start_time']);
    $endTime = time();
    $duration = $endTime - $startTime;
    $durationMinutes = floor($duration / 60);

    $stmt = $pdo->prepare("INSERT INTO court_history (court_number, players, start_time, end_time, duration_minutes) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $courtNumber,
        implode(', ', $players),
        $court['start_time'],
        date('Y-m-d H:i:s', $endTime),
        $durationMinutes
    ]);

    $stmt = $pdo->prepare("UPDATE courts SET player1 = NULL, player2 = NULL, player3 = NULL, player4 = NULL, start_time = NULL WHERE court_number = ?");
    $stmt->execute([$courtNumber]);

    foreach ($players as $name) {
        $stmt = $pdo->prepare("INSERT INTO player_queue (name) VALUES (?)");
        $stmt->execute([$name]);

        $stmt = $pdo->prepare("UPDATE player_stats SET games_played = games_played + 1, revenue = revenue + 30 WHERE name = ?");
        $stmt->execute([$name]);
    }
}

// Reset queue
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['reset_queue'])) {
    $pdo->query("DELETE FROM player_queue");
}

// Reset all courts
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['reset_courts'])) {
    $pdo->query("UPDATE courts SET player1 = NULL, player2 = NULL, player3 = NULL, player4 = NULL, start_time = NULL");
}

// Load data
$courts = $pdo->query("SELECT * FROM courts ORDER BY court_number ASC")->fetchAll();
$queue = $pdo->query("SELECT * FROM player_queue ORDER BY queued_at ASC")->fetchAll();
$playersPerCourt = [];
$stats = $pdo->query("SELECT * FROM player_stats")->fetchAll();
foreach ($stats as $stat) {
    $playersPerCourt[$stat['name']] = $stat['games_played'];
}
?>

<!-- LIVE TIMER DISPLAY -->
<h2>Live Court Timers</h2>
<?php foreach ($courts as $court): ?>
    <div style="border:1px solid #ccc;padding:10px;margin-bottom:10px;">
        <strong>Court <?= $court['court_number'] ?>:</strong>
        <?php if ($court['start_time']): ?>
            <span id="timer<?= $court['court_number'] ?>">Loading...</span>
            <form method="post" style="display:inline">
                <input type="hidden" name="court_number" value="<?= $court['court_number'] ?>">
                <button type="submit" name="finish_game">Stop</button>
            </form>
            <script>
                const start<?= $court['court_number'] ?> = new Date("<?= $court['start_time'] ?>").getTime();
                const display<?= $court['court_number'] ?> = document.getElementById("timer<?= $court['court_number'] ?>");

                function tick<?= $court['court_number'] ?>() {
                    const now = new Date().getTime();
                    const elapsed = now - start<?= $court['court_number'] ?>;
                    const mins = Math.floor(elapsed / 60000);
                    const secs = Math.floor((elapsed % 60000) / 1000);
                    display<?= $court['court_number'] ?>.innerText = `${mins}m ${secs}s`;
                }

                tick<?= $court['court_number'] ?>();
                setInterval(tick<?= $court['court_number'] ?>, 1000);
            </script>
        <?php else: ?>
            <span>Not started</span>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
