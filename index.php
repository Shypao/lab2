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
        <h2>Waiting Queue (<?= count($queue) ?>)</h2>
        <ul>
          <?php foreach ($queue as $p): ?>
            <li>
              <?= htmlspecialchars($p['name']) ?>
              <form method="POST" class="inline">
                <input type="hidden" name="player_id" value="<?= $p['id'] ?>">
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
        </ul>
      </div>
    </div>

    <div class="right">
      <div class="standby">
        <h2>Standby Tables (Max 50)</h2>
        <div class="standby-grid">
          <?php
          $tables = $pdo->query("SELECT * FROM standby_tables ORDER BY table_number ASC")->fetchAll();
          for ($i = 1; $i <= 50; $i++):
            $table = array_filter($tables, fn($t) => $t['table_number'] == $i);
            $table = reset($table);
          ?>
            <div class="table-box">
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
    <a href="Live_timers.php" target="_blank">🕒 View Live Court Timers</a>
  </div>
</body>
</html>
