<?php
// index.php
include 'badminton_app.php';

$courts = isset($courts) && is_array($courts) ? $courts : [];
$queue = isset($queue) && is_array($queue) ? $queue : [];
$standby_tables = isset($standby_tables) && is_array($standby_tables) ? $standby_tables : [];
$playerPlays = isset($playerPlays) && is_array($playerPlays) ? $playerPlays : [];
$tables = $pdo->query("SELECT * FROM standby_tables ORDER BY table_number ASC")->fetchAll(PDO::FETCH_ASSOC);


// Player stats lookup
$playerStatsByName = [];
foreach ($playerPlays as $stat) {
    $name = $stat['player_name'] ?? $stat['name'] ?? null;
    if ($name !== null) {
        $playerStatsByName[$name] = $stat;
    }
}

// Standby tables lookup
$tablesByNumber = [];
foreach ($standby_tables as $t) {
    if (isset($t['table_number'])) {
        $tablesByNumber[(int)$t['table_number']] = $t;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Badminton Court Queue</title>
  <link rel="stylesheet" href="courts.css">
  <style>
    body { margin: 0; font-family: sans-serif; background: #f2f2f2; }
    .main-container { display: flex; padding: 16px; gap: 20px; }
    .left, .middle, .right { background: #fff; padding: 20px; border-radius: px; }
    .left { flex: 1.2; }
    .middle { flex: 1; }
    .right { flex: 3; max: width 70px;px; }

    .section { margin-bottom: 20px; }
    .inline { display: inline-block; margin-left: 6px; }
    ul { list-style: none; padding-left: 0; }


    .table-box, .court-card {
      border: 1px solid #ddd;
      padding: 8px;
      border-radius: 6px;
      background: #fff;
    }

    .table-full { background: #e8f7e8; }
    .table-partial { background: #fff6e6; }

    .bottom-links {
      text-align: center;
      margin-top: 20px;
    }

    h2 { margin-top: 0; }
    input[type="text"] { padding: 4px; }
    button { padding: 4px 8px; }
  </style>
</head>
<body>
  <div class="main-container">
    
    <!-- LEFT COLUMN -->
    <div class="left">
      <div class="section">
        <h2>Add Player to Queue</h2>
        <form method="POST">
          <input type="text" name="player_name" required>
          <button type="submit" name="add_player">Add</button>
        </form>
        <form method="POST" class="inline">
          <button type="submit" name="reset_queue">Reset Queue</button>
        </form>
      </div>

      <div class="section">
        <h2>Waiting Queue (<?= count($queue) ?>)</h2>
        <ul>
          <?php foreach ($queue as $p): ?>
            <?php
              $pname = $p['name'] ?? '';
              $pid = isset($p['id']) ? (int)$p['id'] : 0;
              $stat = $playerStatsByName[$pname] ?? null;
            ?>
            <li>
              <?= htmlspecialchars($pname) ?>
              <?php if ($stat): ?>
                <small style="color: #666;">
                  (Games: <?= (int)($stat['games_played'] ?? 0) ?>,
                  Revenue: ₱<?= number_format($stat['total_revenue'] ?? 0, 2) ?>)
                </small>
              <?php endif; ?>
              <form method="POST" class="inline" style="margin-left:8px;">
                <input type="hidden" name="player_id" value="<?= $pid ?>">
                <button type="submit" name="assign_to_standby">To Standby</button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <!-- MIDDLE COLUMN: STANDBY TABLES -->
    <div class="middle section">
      <h1>Standby Tables</h1>
      <div class="standby-grid">
        <?php foreach ($tables as $table): ?>
          <?php
            $tableNumber = (int)$table['table_number'];
            $players = [];
            for ($i = 1; $i <= 4; $i++) {
              $players[] = !empty($table["player{$i}"]) ? htmlspecialchars($table["player{$i}"]) : '-';
            }
            $fullCount = count(array_filter($players, fn($p) => $p !== '-'));
            $statusClass = $fullCount === 4 ? 'table-full' : ($fullCount > 0 ? 'table-partial' : '');
          ?>
          <div class="table-box <?= $statusClass ?>">
            <strong>Table <?= $tableNumber ?></strong>
            <ul>
              <?php foreach ($players as $p): ?>
                <li><?= $p ?></li>
              <?php endforeach; ?>
            </ul>

            <?php if ($fullCount === 4): ?>
              <?php
                $availableCourts = [];
                foreach ($courts as $court) {
                  $empty = true;
                  for ($c = 1; $c <= 4; $c++) {
                    if (!empty($court["player{$c}"])) {
                      $empty = false;
                      break;
                    }
                  }
                  if ($empty) $availableCourts[] = $court['court_number'];
                }
              ?>
              <?php if (!empty($availableCourts)): ?>
                <form method="POST" style="margin-top:6px;">
                  <input type="hidden" name="table_number" value="<?= $tableNumber ?>">
                  <select name="court_number" required style="font-size:12px;">
                    <?php foreach ($availableCourts as $cNum): ?>
                      <option value="<?= (int)$cNum ?>">Court <?= (int)$cNum ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" name="assign_table_to_court" style="font-size:12px;">Assign</button>
                </form>
              <?php else: ?>
                <p style="font-size:12px;color:red;"><em>No courts available</em></p>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- RIGHT COLUMN: COURTS -->
    <div class="right section">
      <h2>Courts</h2>
      <form method="POST" style="margin-bottom:8px;">
        <button type="submit" name="reset_courts">Reset All Courts</button>
      </form>
      <div class="court-grid">
        <?php foreach ($courts as $court): ?>
          <?php if ((int)$court['court_number'] >= 1 && (int)$court['court_number'] <= 9): ?>
            <div class="court-card">
              <strong>Court <?= (int)$court['court_number'] ?></strong>
              <ul>
                <?php for ($i = 1; $i <= 4; $i++): ?>
                  <?php $slot = "player{$i}"; $pname = $court[$slot] ?? ''; ?>
                  <li>
                    <?= $pname ? htmlspecialchars($pname) : '-' ?>
                    <?php if ($pname): ?>
                      <form method="POST" class="inline">
                        <input type="hidden" name="court_number" value="<?= (int)$court['court_number'] ?>">
                        <input type="hidden" name="player_slot" value="<?= htmlspecialchars($slot) ?>">
                        <button type="submit" name="remove_from_court">Remove</button>
                      </form>
                    <?php endif; ?>
                  </li>
                <?php endfor; ?>
              </ul>

              <form method="POST" class="inline">
                <input type="hidden" name="court_number" value="<?= (int)$court['court_number'] ?>">
                <input type="text" name="shuttlecock" placeholder="Shuttlecock" value="<?= htmlspecialchars($court['shuttlecock'] ?? '') ?>">
                <button type="submit" name="update_shuttlecock">Update Shuttlecock</button>
              </form>

              <form method="POST" class="inline" onsubmit="return confirm('Finish game on Court <?= (int)$court['court_number'] ?>?');">
                <input type="hidden" name="court_number" value="<?= (int)$court['court_number'] ?>">
                <button type="submit" name="finish_game">Finish Game</button>
              </form>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php
  
if (isset($_GET['reset'])) {
    if ($_GET['reset'] == 'success') {
        echo "<script>alert('✅ System reset successful!');</script>";
    } elseif ($_GET['reset'] == 'error' && isset($_GET['msg'])) {
        echo "<script>alert('❌ " . htmlspecialchars($_GET['msg']) . "');</script>";
    }
}
?>

<!-- Reset Entire System -->
<form action="reset.php" method="POST" onsubmit="return confirm('Are you sure you want to reset the system?');">
  <button type="submit">🔁 Reset System</button>
</form>


  <!-- BOTTOM LINKS -->
  <div class="bottom-links section">
    <a href="Live_timers.php" target="_blank">🕒 View Live Court Timers</a> |
    <a href="player_stats.php" target="_blank">📊 View Player Statistics</a> |
    <a href="game_history.php" target="_blank">📋 View Game History</a>
  </div>
</body>
</html>
