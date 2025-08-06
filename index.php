
<?php include 'badminton_app.php'; ?>
<!DOCTYPE html>
<html>
<head>
  <title>Badminton Court Queue</title>
  <link rel="stylesheet" href="courts.css">
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
          <?php if (is_array($queue)): ?>
          <?php foreach ($queue as $p): ?>
            <li>
              <?= htmlspecialchars($p['name']) ?>
              <?php 
              // Show player stats if available
              $playerStat = null;
              foreach ($playerStats as $stat) {
                if ($stat['name'] === $p['name']) {
                  $playerStat = $stat;
                  break;
                }
              }
              if ($playerStat): ?>
                <small style="color: #666;">(Games: <?= $playerStat['games_played'] ?>, Revenue: ₱<?= number_format($playerStat['total_revenue'], 2) ?>)</small>
              <?php endif; ?>
              <form method="POST" class="inline">
                <input type="hidden" name="player_id" value="<?= $p['id'] ?>">
                <select name="table_number" required>
                  <?php for ($i = 1; $i <= 50; $i++): ?>
                    <option value="<?= $i ?>">Standby <?= $i ?></option>
                  <?php endfor; ?>
                </select>
                <button type="submit" name="assign_to_standby">To Standby</button>
              </form>
            </li>
          <?php endforeach; ?>
          <?php endif; ?>
        </ul>
      </div>
    </div>

    <div class="right">
      <div class="standby">
        <h2>Standby Tables (Max 50)</h2>
        
        <!-- Auto-assign button -->
        <form method="POST" class="inline">
          <button type="submit" name="auto_assign_standby" style="background-color: #4CAF50; color: white; padding: 8px 16px; margin-bottom: 10px;">Auto-Assign Full Tables to Courts</button>
        </form>
        
        <div class="standby-grid">
          <?php
          $tables = $pdo->query("SELECT * FROM standby_tables ORDER BY table_number ASC")->fetchAll();
          for ($i = 1; $i <= 50; $i++):
            $table = array_filter($tables, fn($t) => $t['table_number'] == $i);
            $table = reset($table);
          ?>
            <div class="table-box <?php 
              if ($table) {
                $fullCount = 0;
                for ($j = 1; $j <= 4; $j++) {
                  if (!empty($table["player$j"])) $fullCount++;
                }
                if ($fullCount === 4) echo 'table-full';
                elseif ($fullCount > 0) echo 'table-partial';
              }
            ?>">
              <strong>Table <?= $i ?></strong>
              <ul>
                <?php
                if ($table) {
                  for ($j = 1; $j <= 4; $j++):
                    echo "<li>" . ($table["player$j"] ?: '-') . "</li>";
                  endfor;
                } else {
                  for ($j = 1; $j <= 4; $j++):
                    echo "<li>-</li>";
                  endfor;
                }
                ?>
              </ul>
              
              <?php if ($table): ?>
                <?php 
                  $fullCount = 0;
                  for ($j = 1; $j <= 4; $j++) {
                    if (!empty($table["player$j"])) $fullCount++;
                  }
                  
                  if ($fullCount === 4): 
                    // Show assign to court options
                    $availableCourts = [];
                    foreach ($courts as $court) {
                      $isEmpty = true;
                      for ($c = 1; $c <= 4; $c++) {
                        if (!empty($court["player$c"])) {
                          $isEmpty = false;
                          break;
                        }
                      }
                      if ($isEmpty) $availableCourts[] = $court['court_number'];
                    }
                ?>
                  <?php if (count($availableCourts) > 0): ?>
                    <form method="POST" style="margin-top: 5px;">
                      <input type="hidden" name="table_number" value="<?= $i ?>">
                      <select name="court_number" required style="font-size: 10px;">
                        <?php foreach ($availableCourts as $cNum): ?>
                          <option value="<?= $cNum ?>">Court <?= $cNum ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button type="submit" name="assign_table_to_court" style="font-size: 10px;">Assign</button>
                    </form>
                  <?php else: ?>
                    <p style="font-size: 10px; color: red; margin: 2px 0;"><em>No courts available</em></p>
                  <?php endif; ?>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endfor; ?>
        </div>
      </div>

      <div class="courts">
        <h2>Courts</h2>
        <form method="POST">
          <button type="submit" name="reset_courts">Reset All Courts</button>
        </form>

        <div class="court-grid">
          <?php foreach ($courts as $court): ?>
            <div class="court-card">
              <strong>Court <?= $court['court_number'] ?></strong>
              <ul>
                <?php for ($i = 1; $i <= 4; $i++): ?>
                  <li>
                    <?= $court["player$i"] ?: '-' ?>
                    <?php if ($court["player$i"]): ?>
                      <form method="POST" class="inline">
                        <input type="hidden" name="court_number" value="<?= $court['court_number'] ?>">
                        <input type="hidden" name="player_slot" value="player<?= $i ?>">
                        <button type="submit" name="remove_from_court">Remove</button>
                      </form>
                    <?php endif; ?>
                  </li>
                <?php endfor; ?>
              </ul>

              <form method="POST" class="inline">
                <input type="hidden" name="court_number" value="<?= $court['court_number'] ?>">
                <input type="text" name="shuttlecock" placeholder="Shuttlecock" value="<?= $court['shuttlecock'] ?>">
                <button type="submit" name="update_shuttlecock">Update Shuttlecock</button>
              </form>

              <form method="POST" class="inline" onsubmit="return confirm('Finish game on Court <?= $court['court_number'] ?>?');">
                <input type="hidden" name="court_number" value="<?= $court['court_number'] ?>">
                <button type="submit" name="finish_game">Finish Game</button>
              </form>
            </div>
          <?php endforeach; ?>
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
