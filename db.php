<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=psppa_feedback', 'root');
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
