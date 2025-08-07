<?php
session_start();
include 'badminton_app.php'; // DB connection and fetching logic

// --- Admin flag ---
$isAdmin = true; // Set this to false or use session logic for real admin control

// --- HANDLE NAME UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_player_name'])) {
    $playerId = (int)$_POST['player_id'];
    $newName = trim($_POST['new_name']);

    if ($playerId > 0 && $newName !== '') {
        $stmt = $pdo->prepare("SELECT name FROM player_queue WHERE id = ?");
        $stmt->execute([$playerId]);
        $oldData = $stmt->fetch();
        if ($oldData) {
            $oldName = $oldData['name'];

            $tablesToUpdate = ['courts', 'standby_tables', 'game_history'];
            foreach ($tablesToUpdate as $table) {
                for ($i = 1; $i <= 4; $i++) {
                    $col = "player$i";
                    $pdo->prepare("UPDATE $table SET $col = ? WHERE $col = ?")->execute([$newName, $oldName]);
                }
            }

            $stmt = $pdo->prepare("UPDATE player_queue SET name = ? WHERE id = ?");
            $stmt->execute([$newName, $playerId]);
        }
    }
    header("Location: index.php");
    exit;
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
    .left, .middle, .right { background: #fff; padding: 20px; border-radius: 8px; }
    .left { flex: 1.2; }
    .middle { flex: 1; }
    .right { flex: 3; }

    .section { margin-bottom: 20px; }
    .inline { display: inline-block; margin-left: 6px; }
    ul { list-style: none; padding-left: 0; }
    .table-box, .court-card { border: 1px solid #ddd; padding: 8px; border-radius: 6px; background: #fff; }
    .table-full { background: #e8f7e8; }
    .table-partial { background: #fff6e6; }
    .bottom-links { text-align: center; margin-top: 20px; }
    h2 { margin-top: 0; }
    input[type="text"] { padding: 4px; }
    button { padding: 4px 8px; }
    .edit-icon { cursor: pointer; margin-left: 5px; font-size: 14px; color: #555; }

    .modal { display:none; position:fixed; z-index:999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); }
    .modal-content { background:#fff; padding:20px; margin:15% auto; width:300px; border-radius:10px; position:relative; }
    .close { position:absolute; top:10px; right:15px; font-size:24px; cursor:pointer; }
  </style>
</head>
<body>
  <div class="main-container">
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
        <ul id="waiting-queue">
          <?php foreach ($queue as $p): ?>
            <?php
              $pname = $p['name'] ?? '';
              $pid = $p['id'] ?? 0;
              $stat = $playerStatsByName[$pname] ?? null;
            ?>
            <li>
              <form method="POST" class="inline assign-to-standby-form">
                <input type="hidden" name="player_id" value="<?= $pid ?>">
                <button type="submit" name="assign_to_standby"><?= htmlspecialchars($pname) ?></button>
              </form>
              <?php if ($isAdmin): ?>
                <span class="edit-icon" onclick="openEditModal(<?= $pid ?>, '<?= htmlspecialchars($pname, ENT_QUOTES) ?>')">✏️</span>
              <?php endif; ?>
              <?php if ($stat): ?>
                <br><small style="color: #666;">(Games: <?= (int)($stat['games_played']) ?>, Revenue: ₱<?= number_format($stat['total_revenue'], 2) ?>)</small>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <div class="middle section">
      <h1>Standby Tables</h1>
      <div class="standby-grid">
        <?php foreach ($standby_tables as $table): ?>
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
                $availableCourts = array_filter($courts, function ($court) {
                  for ($i = 1; $i <= 4; $i++) {
                    if (!empty($court["player{$i}"])) return false;
                  }
                  return true;
                });
              ?>
              <?php if (!empty($availableCourts)): ?>
                <form method="POST">
                  <input type="hidden" name="table_number" value="<?= $tableNumber ?>">
                  <select name="court_number" required>
                    <?php foreach ($availableCourts as $court): ?>
                      <option value="<?= (int)$court['court_number'] ?>">Court <?= (int)$court['court_number'] ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" name="assign_table_to_court">Assign</button>
                </form>
              <?php else: ?>
                <p style="color:red; font-size:12px;">No courts available</p>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="right section">
      <h2>Courts</h2>
      <form method="POST">
        <button type="submit" name="reset_courts">Reset All Courts</button>
      </form>
      <div class="court-grid">
        <?php foreach ($courts as $court): ?>
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
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Edit Name Modal -->
  <div id="editNameModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeEditModal()">&times;</span>
      <form method="POST">
        <input type="hidden" name="player_id" id="editPlayerId">
        <label>New Name:</label>
        <input type="text" name="new_name" id="editPlayerName" required>
        <button type="submit" name="update_player_name">Update</button>
      </form>
    </div>
  </div>

  <?php if (isset($_GET['reset'])): ?>
    <script>
      alert('<?= $_GET['reset'] == 'success' ? '✅ System reset successful!' : '❌ ' . htmlspecialchars($_GET['msg']) ?>');
    </script>
  <?php endif; ?>

  <form action="reset.php" method="POST" onsubmit="return confirm('Are you sure you want to reset the system?');">
    <button type="submit">🔁 Reset System</button>
  </form>

  <div class="bottom-links section">
    <a href="Live_timers.php" target="_blank">🕒 View Live Court Timers</a> |
    <a href="player_stats.php" target="_blank">📊 View Player Statistics</a> |
    <a href="game_history.php" target="_blank">📋 View Game History</a>
  </div>

  <script>
    function openEditModal(id, name) {
      document.getElementById("editPlayerId").value = id;
      document.getElementById("editPlayerName").value = name;
      document.getElementById("editNameModal").style.display = "block";
    }
    function closeEditModal() {
      document.getElementById("editNameModal").style.display = "none";
    }
    window.onclick = function(event) {
      const modal = document.getElementById("editNameModal");
      if (event.target === modal) closeEditModal();
    }
  </script>
</body>
</html>