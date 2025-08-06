<?php include 'badminton_app.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Standby Tables Management</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="section">
            <h2>Standby Tables Management</h2>

            <!-- Auto-assign all button -->
            <form method="POST" style="margin-bottom: 20px;">
                <button type="submit" name="auto_assign_standby" style="background-color: #4CAF50; color: white; padding: 10px 20px; font-size: 16px;">Auto-Assign All Full Tables to Available Courts</button>
            </form>

            <?php
            // Get all standby tables once
            $tables = $pdo->query("SELECT * FROM standby_tables ORDER BY table_number ASC")->fetchAll();

            // Get all courts once
            $courts = $pdo->query("SELECT * FROM courts ORDER BY court_number ASC")->fetchAll();

            foreach ($tables as $table):
                $i = $table['table_number'];
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
                        // Check if this table is full
                        $isFull = !empty($table["player1"]) && !empty($table["player2"]) && !empty($table["player3"]) && !empty($table["player4"]);

                        // Check available courts
                        $availableCourts = [];
                        foreach ($courts as $court) {
                            $courtEmpty = true;
                            for ($c = 1; $c <= 4; $c++) {
                                if (!empty($court["player$c"])) {
                                    $courtEmpty = false;
                                    break;
                                }
                            }
                            if ($courtEmpty) {
                                $availableCourts[] = $court['court_number'];
                            }
                        }
                    ?>

                    <?php if ($isFull): ?>
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
                        <!-- Show add player form if not full -->
                        <form method="POST">
                            <input type="text" name="player_name" placeholder="Name" required>
                            <input type="hidden" name="table_number" value="<?= $i ?>">
                            <button type="submit" name="add_to_table">Add</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
