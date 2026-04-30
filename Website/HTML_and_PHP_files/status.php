<?php
/**
 * SportFlow - Systeem Status
 *
 * Toont de meest recente meting uit de system_stats tabel.
 * Die wordt elke 5 minuten gevuld door scripts/update_stats.sh (via cron).
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
vereisLogin();

// Laatste meting ophalen
$stmt  = $pdo->query("SELECT * FROM system_stats ORDER BY measured_at DESC LIMIT 1");
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>SportFlow - Status</title>
    <link rel="stylesheet" href="../CSSfiles/style.css">
    <link rel="stylesheet" href="../CSSfiles/components.css">
</head>
<body>
    <h1>Systeem Status</h1>

    <?php require __DIR__ . '/_nav.php'; ?>

    <hr>

    <h2>Server Informatie</h2>
    <ul>
        <li><strong>Server:</strong> Raspberry Pi 5</li>
        <li><strong>Database:</strong> MySQL</li>
        <li><strong>Webserver:</strong> Apache 2</li>
    </ul>

    <hr>

    <h2>Live meting</h2>

    <?php if (!$stats): ?>
        <p><em>Nog geen meetgegevens beschikbaar. Het cron-script vult deze tabel elke 5 minuten.</em></p>
    <?php else: ?>
        <p>Laatst gemeten: <strong><?= htmlspecialchars($stats['measured_at']) ?></strong></p>

        <div class="status-container">
            <div class="status-card">
                <strong>CPU Temperatuur</strong>
                <span><?= htmlspecialchars($stats['cpu_temp'] ?? '–') ?>&nbsp;&deg;C</span>
            </div>
            <div class="status-card">
                <strong>CPU Belasting</strong>
                <span><?= htmlspecialchars($stats['cpu_usage'] ?? '–') ?>&nbsp;%</span>
            </div>
            <div class="status-card">
                <strong>RAM Gebruik</strong>
                <span><?= htmlspecialchars($stats['ram_usage_mb'] ?? '–') ?>&nbsp;MB</span>
            </div>
            <div class="status-card">
                <strong>Vrije Schijfruimte</strong>
                <span><?= htmlspecialchars($stats['disk_free_gb'] ?? '–') ?>&nbsp;GB</span>
            </div>
            <div class="status-card">
                <strong>Database Grootte</strong>
                <span><?= htmlspecialchars($stats['db_size_mb'] ?? '–') ?>&nbsp;MB</span>
            </div>
            <div class="status-card">
                <strong>Aantal Trainingen</strong>
                <span><?= htmlspecialchars($stats['total_trainings'] ?? '–') ?></span>
            </div>
            <div class="status-card">
                <strong>Uptime</strong>
                <span><?= htmlspecialchars($stats['uptime_days'] ?? '–') ?>&nbsp;dagen</span>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>
