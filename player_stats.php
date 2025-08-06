
<?php include 'badminton_app.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Player Statistics - Badminton Queue</title>
    <link rel="stylesheet" href="courts.css">
    <style>
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .stats-table th, .stats-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .stats-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .stats-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .summary-card h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .summary-card .number {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="section">
            <h2>Player Statistics Dashboard</h2>
            <a href="index.php">← Back to Main</a>

            <?php
            // Calculate summary statistics
            $totalPlayers = count($playerStats);
            $totalGames = array_sum(array_column($playerStats, 'games_played'));
            $totalRevenue = array_sum(array_column($playerStats, 'total_revenue'));
            $avgGamesPerPlayer = $totalPlayers > 0 ? round($totalGames / $totalPlayers, 1) : 0;
            ?>

            <div class="stats-summary">
                <div class="summary-card">
                    <h3>Total Players</h3>
                    <div class="number"><?= $totalPlayers ?></div>
                </div>
                <div class="summary-card">
                    <h3>Total Games Played</h3>
                    <div class="number"><?= $totalGames ?></div>
                </div>
                <div class="summary-card">
                    <h3>Total Revenue</h3>
                    <div class="number">₱<?= number_format($totalRevenue, 2) ?></div>
                </div>
                <div class="summary-card">
                    <h3>Avg Games/Player</h3>
                    <div class="number"><?= $avgGamesPerPlayer ?></div>
                </div>
            </div>

            <table class="stats-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Player Name</th>
                        <th>Games Played</th>
                        <th>Total Revenue</th>
                        <th>Avg Revenue/Game</th>
                        <th>Last Played</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($playerStats)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px;">
                                <em>No player statistics available yet. Players will appear here after finishing their first game.</em>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($playerStats as $index => $player): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><strong><?= htmlspecialchars($player['name']) ?></strong></td>
                                <td><?= $player['games_played'] ?></td>
                                <td>₱<?= number_format($player['total_revenue'], 2) ?></td>
                                <td>₱<?= $player['games_played'] > 0 ? number_format($player['total_revenue'] / $player['games_played'], 2) : '0.00' ?></td>
                                <td><?= $player['last_played'] ? date('M j, Y g:i A', strtotime($player['last_played'])) : 'Never' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div style="margin-top: 30px;">
                <form method="POST" onsubmit="return confirm('Are you sure you want to reset all player statistics? This cannot be undone.');">
                    <button type="submit" name="reset_stats" style="background-color: #f44336; color: white; padding: 10px 20px;">Reset All Statistics</button>
                </form>
            </div>
        </div>
    </div>

    <?php
    // Handle reset statistics
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['reset_stats'])) {
        $pdo->query("DELETE FROM player_stats");
        $pdo->query("DELETE FROM game_history");
        $pdo->query("DELETE FROM player_revenue");
        header("Location: player_stats.php");
        exit;
    }
    ?>
</body>
</html>
