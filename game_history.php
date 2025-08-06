<?php include 'badminton_app.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Game History - Badminton Queue</title>
    <link rel="stylesheet" href="courts.css">
    <style>
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .history-table th, .history-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .history-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .history-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .players-list {
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="section">
            <h2>Game History</h2>
            <a href="index.php">← Back to Main</a>

            <?php
            // Fetch game history
            $gameHistory = $pdo->query("SELECT * FROM game_history ORDER BY end_time DESC LIMIT 50")->fetchAll();
            ?>

            <table class="history-table">
                <thead>
                    <tr>
                        <th>Game #</th>
                        <th>Court</th>
                        <th>Players</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Duration</th>
                        <th>Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($gameHistory)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px;">
                                <em>No game history available yet. Games will appear here after completion.</em>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($gameHistory as $game): ?>
                            <tr>
                                <td><?= $game['id'] ?></td>
                                <td>Court <?= $game['court_number'] ?></td>
                                <td class="players-list">
                                    <?php
                                    $players = array_filter([
                                        $game['player1'],
                                        $game['player2'],
                                        $game['player3'],
                                        $game['player4']
                                    ]);
                                    echo implode('<br>', array_map('htmlspecialchars', $players));
                                    ?>
                                </td>
                                <td><?= date('M j, g:i A', strtotime($game['start_time'])) ?></td>
                                <td><?= date('M j, g:i A', strtotime($game['end_time'])) ?></td>
                                <td><?= $game['duration_minutes'] ?> min</td>
                                <td>₱<?= number_format(count($players) * $game['revenue_per_player'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div style="margin-top: 20px;">
                <p><em>Showing last 50 games. Total games in database: <?= $pdo->query("SELECT COUNT(*) FROM game_history")->fetchColumn() ?></em></p>
            </div>
        </div>
    </div>
</body>
</html>
