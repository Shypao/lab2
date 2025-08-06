<?php include 'badminton_app.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Standby Tables Management</title>
    <link rel="stylesheet" href="courts.css">
</head>
<body>
    <div class="container">
        <div class="section">
            <h2>Standby Tables Management</h2>

            <!-- Auto-assign all button -->
            <form method="POST" style="margin-bottom: 20px;">
                <button type="submit" name="auto_assign_standby" style="background-color: #4CAF50; color: white; padding: 10px 20px; font-size: 16px;">Auto-Assign All Full Tables to Available Courts</button>
            </form>

            <?php for ($i = 1; $i <= 50; $i++): 
                $tables = $pdo->query("SELECT * FROM standby_tables ORDER BY table_number ASC")->fetchAll();
                $table = array_filter($tables, fn($t) => $t['table_number'] == $i);
                $table = reset($table);
            ?>
                <div class="table-box" style="width: 300px; display: inline-block; margin: 10px; vertical-align: top;">
                    <strong>Table <?= $i ?></strong>
                    <ul>
                        <?php for ($j = 1; $j <= 4; $j++): ?>
                            <li>
                                <?= htmlspecialchars($table["player$j"] ?? '-') ?>
                                <?php if (!empty($table["player$j"])): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="slot" value="player<?= $j ?>">
                                        <input type="hidden" name="table_number" value="<?= $i ?>">
                                        <button type="submit" name="remove_from_table" style="font-size: 10px;">Remove</button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endfor; ?>
                    </ul>

                    <?php 
                        $isFull = $table && !empty($table["player1"]) && !empty($table["player2"]) && !empty($table["player3"]) && !empty($table["player4"]);
                    ?>

                    <?php if ($isFull): 
                        // Check available courts
                        $availableCourts = [];
                        foreach ($courts as $court) {
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

                    <?php else: ?>
                        <!-- Add player form only shows if table is not full -->
                        <form method="POST">
                            <input type="text" name="player_name" placeholder="Name" required>
                            <input type="hidden" name="table_number" value="<?= $i ?>">
                            <button type="submit" name="add_to_table">Add</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</body>
</html>