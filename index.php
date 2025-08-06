<?php
// index.php
// Main UI — expects badminton_app.php to set $courts, $queue, $standby_tables, $playerPlays
include 'badminton_app.php';

// Ensure variables exist and are arrays (fall back to empty arrays to avoid warnings)
$courts = isset($courts) && is_array($courts) ? $courts : [];
$queue = isset($queue) && is_array($queue) ? $queue : [];
$standby_tables = isset($standby_tables) && is_array($standby_tables) ? $standby_tables : [];
$playerPlays = isset($playerPlays) && is_array($playerPlays) ? $playerPlays : [];

// Build a lookup for player stats (playerPlays likely has player_name, games_played, total_revenue)
$playerStatsByName = [];
foreach ($playerPlays as $stat) {
    $name = $stat['player_name'] ?? $stat['name'] ?? null;
    if ($name !== null) {
        $playerStatsByName[$name] = $stat;
    }
}

// Build a lookup for standby tables by table_number to avoid array_filter + reset pitfalls
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
    /* minimal fallback styles */
    .container { display:flex; gap:20px; padding:16px; }
    .left, .right { flex:1; }
    .section { margin-bottom:16px; background:#f9f9f9; padding:12px; border-radius:6px; }
    .inline { display:inline-block; margin-left:6px; }
    ul { list-style:none; padding-left:0; }
    .standby-grid, .court-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:12px; }
    .table-box, .court-card { border:1px solid #ddd; padding:8px; border-radius:6px; background:#fff; }
    .table-full { background:#e8f7e8; } .table-partial { background:#fff6e6; }
  </style>
</head>

<body>
  <div class="container">
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
        <h2>Waiting Queue (<?= is_array($queue) ? count($queue) : 0 ?>)</h2>
        <ul>
          <?php if (!empty($queue)): ?>
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
                     <?php if (isset($stat['total_revenue'])): ?>
                       Revenue: ₱<?= number_format($stat['total_revenue'], 2) ?>
                     <?php endif; ?>
                    )
                  </small>
                <?php endif; ?>

                <form method="POST" class="inline" style="margin-left:8px;">
                  <input type="hidden" name="player_id" value="<?= $pid ?>">
                  <select name="table_number" required>
                    <?php for ($i = 1; $i <= 20; $i++): ?>
                      <option value="<?= $i ?>">Standby <?= $i ?></option>
                    <?php endfor; ?>
                  </select>
                  <button type="submit" name="assign_to_standby">To Standby</button>
                </form>
              </li>
            <?php endforeach; ?>
          <?php else: ?>
            <li><em>No players in queue</em></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>

    <div class="right">
      <div class="standby section">
        <h2>Standby Tables</h2>

        <!-- Auto-assign button -->
        <form method="POST" class="inline" style="margin-bottom:10px;">
          <button type="submit" name="auto_assign_standby" style="background-color:#4CAF50;color:#fff;padding:8px 16px;">Auto-Assign Full Tables to Courts</button>
        </form>

        <div class="standby-grid">
          <?php for ($i = 1; $i <= 20; $i++):
            $table = $tablesByNumber[$i] ?? null;
            $fullCount = 0;
            if ($table) {
                for ($j = 1; $j <= 4; $j++) {
                    if (!empty($table["player{$j}"])) $fullCount++;
                }
            }
          ?>
            <div class="table-box <?= $table ? ($fullCount === 4 ? 'table-full' : ($fullCount > 0 ? 'table-partial' : '')) : '' ?>">
              <strong>Table <?= $i ?></strong>
              <ul>
                <?php
                  if ($table) {
                    for ($j = 1; $j <= 4; $j++):
                      $name = $table["player{$j}"] ?? '';
                      echo '<li>' . ($name ? htmlspecialchars($name) : '-') . '</li>';
                    endfor;
                  } else {
                    for ($j = 1; $j <= 4; $j++):
                      echo '<li>-</li>';
                    endfor;
                  }
                ?>
              </ul>

              <?php if ($table && $fullCount === 4): ?>
                <?php
                  // find available courts (courts might be empty array)
                  $availableCourts = [];
                  foreach ($courts as $court) {
                      $empty = true;
                      for ($c = 1; $c <= 4; $c++) {
                          if (!empty($court["player{$c}"])) { $empty = false; break; }
                      }
                      if ($empty) $availableCourts[] = $court['court_number'];
                  }
                ?>
                <?php if (count($availableCourts) > 0): ?>
                  <form method="POST" style="margin-top:6px;">
                    <input type="hidden" name="table_number" value="<?= $i ?>">
                    <select name="court_number" required style="font-size:12px;">
                      <?php foreach ($availableCourts as $cNum): ?>
                        <option value="<?= (int)$cNum ?>">Court <?= (int)$cNum ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" name="assign_table_to_court" style="font-size:12px;">Assign</button>
                  </form>
                <?php else: ?>
                  <p style="font-size:12px;color:red;margin:4px 0;"><em>No courts available</em></p>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endfor; ?>
        </div>
      </div>

      <div class="courts section">
        <h2>Courts</h2>
        <form method="POST" style="margin-bottom:8px;">
          <button type="submit" name="reset_courts">Reset All Courts</button>
        </form>

        <div class="court-grid">
          <?php if (!empty($courts)): ?>
            <?php foreach ($courts as $court): ?>
              <div class="court-card">
                <strong>Court <?= (int)($court['court_number'] ?? 0) ?></strong>
                <ul>
                  <?php for ($i = 1; $i <= 4; $i++): ?>
                    <?php $slot = "player{$i}"; $pname = $court[$slot] ?? ''; ?>
                    <li>
                      <?= $pname ? htmlspecialchars($pname) : '-' ?>
                      <?php if ($pname): ?>
                        <form method="POST" class="inline">
                          <input type="hidden" name="court_number" value="<?= (int)($court['court_number'] ?? 0) ?>">
                          <input type="hidden" name="player_slot" value="<?= htmlspecialchars($slot) ?>">
                          <button type="submit" name="remove_from_court">Remove</button>
                        </form>
                      <?php endif; ?>
                    </li>
                  <?php endfor; ?>
                </ul>

                <form method="POST" class="inline">
                  <input type="hidden" name="court_number" value="<?= (int)($court['court_number'] ?? 0) ?>">
                  <input type="text" name="shuttlecock" placeholder="Shuttlecock" value="<?= htmlspecialchars($court['shuttlecock'] ?? '') ?>">
                  <button type="submit" name="update_shuttlecock">Update Shuttlecock</button>
                </form>

                <form method="POST" class="inline" onsubmit="return confirm('Finish game on Court <?= (int)($court['court_number'] ?? 0) ?>?');">
                  <input type="hidden" name="court_number" value="<?= (int)($court['court_number'] ?? 0) ?>">
                  <button type="submit" name="finish_game">Finish Game</button>
                </form>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p><em>No courts defined.</em></p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="section">
    <a href="Live_timers.php" target="_blank">🕒 View Live Court Timers</a> |
    <a href="player_stats.php" target="_blank">📊 View Player Statistics</a> |
    <a href="game_history.php" target="_blank">📋 View Game History</a>
  </div>
</body>
</html>
