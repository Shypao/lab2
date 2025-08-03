<?php
// Fetch standby tables
$tables = $pdo->query("SELECT * FROM standby_tables ORDER BY table_number ASC")->fetchAll();

// Add player to standby table (with duplicate name check)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_to_table'])) {
    $name = trim($_POST['player_name']);
    $tableNum = $_POST['table_number'];

    if (!empty($name)) {
        $check = $pdo->prepare("SELECT * FROM standby_tables 
            WHERE player1 = ? OR player2 = ? OR player3 = ? OR player4 = ?");
        $check->execute([$name, $name, $name, $name]);

        if ($check->rowCount() === 0) {
            $table = $pdo->prepare("SELECT * FROM standby_tables WHERE table_number = ?");
            $table->execute([$tableNum]);
            $table = $table->fetch();

            for ($i = 1; $i <= 4; $i++) {
                if (empty($table["player$i"])) {
                    $stmt = $pdo->prepare("UPDATE standby_tables SET player$i = ? WHERE table_number = ?");
                    $stmt->execute([$name, $tableNum]);
                    break;
                }
            }
        }
    }
}

// Assign full table to a court if it's empty
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['assign_table_to_court'])) {
    $tableNum = $_POST['table_number'];
    $courtNum = $_POST['court_number'];

    $checkCourt = $pdo->prepare("SELECT * FROM courts WHERE court_number = ?");
    $checkCourt->execute([$courtNum]);
    $court = $checkCourt->fetch();

    $isCourtEmpty = true;
    for ($i = 1; $i <= 4; $i++) {
        if (!empty($court["player$i"])) {
            $isCourtEmpty = false;
            break;
        }
    }

    if ($isCourtEmpty) {
        $table = $pdo->prepare("SELECT * FROM standby_tables WHERE table_number = ?");
        $table->execute([$tableNum]);
        $table = $table->fetch();

        $players = [];
        for ($i = 1; $i <= 4; $i++) {
            $players[] = $table["player$i"];
        }

        $stmt = $pdo->prepare("UPDATE courts SET player1 = ?, player2 = ?, player3 = ?, player4 = ?, start_time = NOW() WHERE court_number = ?");
        $stmt->execute([$players[0], $players[1], $players[2], $players[3], $courtNum]);

        $pdo->prepare("UPDATE standby_tables SET player1=NULL, player2=NULL, player3=NULL, player4=NULL WHERE table_number = ?")->execute([$tableNum]);
    }
}
?>

<div class="section">
    <h2>Standby Tables (1–50)</h2>

    <?php for ($i = 1; $i <= 50; $i++): 
        $table = array_filter($tables, fn($t) => $t['table_number'] == $i);
        $table = reset($table);
    ?>
        <div class="table-box">
            <strong>Table <?= $i ?></strong>
            <ul>
                <?php for ($j = 1; $j <= 4; $j++): ?>
                    <li>
                        <?= htmlspecialchars($table["player$j"] ?? '-') ?>
                        <?php if (!empty($table["player$j"])): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="slot" value="player<?= $j ?>">
                                <input type="hidden" name="table_number" value="<?= $i ?>">
                                <button type="submit" name="remove_from_table">Remove</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endfor; ?>
            </ul>

            <form method="POST">
                <input type="text" name="player_name" placeholder="Name" required>
                <input type="hidden" name="table_number" value="<?= $i ?>">
                <button type="submit" name="add_to_table">Add</button>
            </form>

            <?php 
                $isFull = true;
                for ($j = 1; $j <= 4; $j++) {
                    if (empty($table["player$j"])) {
                        $isFull = false;
                        break;
                    }
                }
            ?>

            <?php if ($isFull): 
                // Check available courts
                $availableCourts = [];
                $stmt = $pdo->query("SELECT * FROM courts ORDER BY court_number ASC");
                $allCourts = $stmt->fetchAll();
                foreach ($allCourts as $court) {
                    $empty = true;
                    for ($c = 1; $c <= 4; $c++) {
                        if (!empty($court["player$c"])) {
                            $empty = false;
                            break;
                        }
                    }
                    if ($empty) $availableCourts[] = $court['court_number'];
                }
            ?>

                <?php if (count($availableCourts) > 0): ?>
                    <form method="POST" onsubmit="return confirm('Assign Table <?= $i ?> to a court?');">
                        <input type="hidden" name="table_number" value="<?= $i ?>">
                        <select name="court_number" required>
                            <?php foreach ($availableCourts as $cNum): ?>
                                <option value="<?= $cNum ?>">Court <?= $cNum ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="assign_table_to_court">Assign to Court</button>
                    </form>
                <?php else: ?>
                    <p><em>No courts available</em></p>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    <?php endfor; ?>
</div>
