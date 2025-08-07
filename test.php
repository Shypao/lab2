<?php
$enteredPassword = 'secret123';
$storedHash = '$2y$10$gNn9RgKqewF3x...'; // replace with actual hash from DB

if (password_verify($enteredPassword, $storedHash)) {
    echo "✅ Password works!";
} else {
    echo "❌ Password does NOT match.";
}
