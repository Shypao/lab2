<?php
session_start();
require 'badminton_app.php'; // your DB connection

// Admin check (you should have a login system for this)
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;


// Additional code to handle player name update by ID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_player_name'])) {
    $playerId = (int)$_POST['player_id'];
    $newName = trim($_POST['new_name']);

    if ($playerId > 0 && $newName !== '' && $isAdmin) {
        try {
            $stmt = $pdo->prepare("UPDATE queue SET name = :new_name WHERE id = :player_id");
            $stmt->execute(['new_name' => $newName, 'player_id' => $playerId]);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
        }

        // Redirect to avoid form resubmission
        header("Location: index.php");
        exit;
    }
}

// Fetch player list from DB
$players = $pdo->query("SELECT name FROM players ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Player List with Edit Modal</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        ul.queue-list {
            list-style-type: none;
            padding: 0;
        }
        ul.queue-list li {
            margin: 5px 0;
        }
        button.inline {
            margin-left: 10px;
            cursor: pointer;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 300px;
            border-radius: 8px;
            position: relative;
        }

        .close {
            position: absolute;
            top: 10px;
            right: 14px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }
    </style>
</head>
<body>

<h2>Player List</h2>
<ul class="queue-list">
    <?php foreach ($players as $player): ?>
        <?php $name = htmlspecialchars($player['name']); ?>
        <li>
            <?= $name ?>
            <?php if ($isAdmin): ?>
                <button class="inline" onclick="openEditModal('<?= $name ?>')">✏️ Edit</button>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>

<!-- Modal -->
<div id="editPlayerModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <form method="POST">
            <input type="hidden" name="original_name" id="originalName">
            <label for="newName">Edit Name:</label>
            <input type="text" name="new_name" id="newName" required>
            <button type="submit">Save</button>
        </form>
    </div>
</div>

<script>
function openEditModal(name) {
    document.getElementById('originalName').value = name;
    document.getElementById('newName').value = name;
    document.getElementById('editPlayerModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('editPlayerModal').style.display = 'none';
}

// Optional: close modal when clicking outside of it
window.onclick = function(event) {
    const modal = document.getElementById('editPlayerModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>

</body>
</html>
