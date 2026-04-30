<?php
/**
 * SportFlow - Databaseverbinding (PDO)
 *
 * ➤ Verander hieronder de database-instellingen als jouw setup verandert.
 * Standaard: root zonder wachtwoord op localhost (zoals jouw Pi nu).
 */

$db_host = 'localhost';
$db_name = 'sportflow';
$db_user = 'root';
$db_pass = '';   // ➤ Voeg hier je wachtwoord toe als je er ooit een instelt

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Databaseverbinding mislukt: " . htmlspecialchars($e->getMessage()));
}
