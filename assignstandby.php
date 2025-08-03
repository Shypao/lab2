<?php
include 'badminton_app.php'; // adjust as needed
date_default_timezone_set('Asia/Manila');

// Handle assignment from standby table to court
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['assign_table_to_court'])) {
    $tableNum = $_POST['table_number'];
    $courtNum = $_POST['court_number'];

    $table = $pdo->prepare("SELECT * FROM standby_tables WHERE table_number = ?");
    $table->execute([$tableNum]);
    $table = $table->fetch();

    $players = [];
    for ($i = 1; $i <= 4; $i++) {
        $players[] = $table["player$i"];
    }

    // Check if court is empty
    $court = $pdo->prepare("SELECT * FROM courts WHERE court_number = ?");
    $court->execute([$courtNum]);
    $court = $court->fetch();

    $isEmpty = true;
    for ($i = 1; $i <= 4; $i++) {
        if (!empty($court["player$i"])) {
            $isEmpty = false;
            break;
        }
    }

    if ($isEmpty && count(array_filter($players)) === 4) {
        // Assign players to court
        $assign = $pdo->prepare("UPDATE courts SET player1=?, player2=?, player3=?, player4=?, start_time=NOW() WHERE court_number = ?");
        $assign->execute([$players[0], $players[1], $players[2], $players[3], $courtNum]);

        // Clear table
        $pdo->prepare("UPDATE standby_tables SET player1=NULL, player2=NULL, player3=NULL, player4=NULL WHERE table_number = ?")->execute([$tableNum]);
    }
}

// Fetch standby tables and available courts
$tables = $pdo->query("SELECT * FROM standby_tables ORDER BY table_number ASC")->fetchAll();
$courts = $pdo->query("SELECT * FROM courts ORDER BY court_number ASC")->fetchAll();
?>

<h2>Assign Standby Tables to Courts</h2>

<?php foreach ($tables as $table): ?>
    <?php
    $isFull = true;
    for ($i = 1; $i <= 4; $i++) {
        if (empty($table["player$i"])) {
            $isFull = false;
            break;
        }
    }

    if (!$isFull) continue;

    $availableCourts = [];
    foreach ($courts as $court) {
        $courtEmpty = true;
        for ($i = 1; $i <= 4; $i++) {
            if (!empty($court["player$i"])) {
                $courtEmpty = false;
                break;
            }
        }
        if ($courtEmpty) $availableCourts[] = $court["court_number"];
    }
    ?>

    <div style="margin-bottom: 20px; padding: 10px; border: 1px solid #ccc;">
        <strong>Table <?= $table['table_number'] ?></strong>
        <ul>
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <li><?= htmlspecialchars($table["player$i"]) ?></li>
            <?php endfor; ?>
        </ul>

        <?php if (count($availableCourts) > 0): ?>
            <form method="POST">
                <input type="hidden" name="table_number" value="<?= $table['table_number'] ?>">
                <label for="court_number">Assign to Court:</label>
                <select name="court_number" required>
                    <?php foreach ($availableCourts as $courtNum): ?>
                        <option value="<?= $courtNum ?>">Court <?= $courtNum ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="assign_table_to_court">Assign</button>
            </form>
        <?php else: ?>
            <p><em>No available courts</em></p>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
