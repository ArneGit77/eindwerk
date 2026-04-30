<?php
/**
 * SportFlow - Gedeelde navigatie
 *
 * Wordt ingeladen op elke beschermde pagina (homepage, planner, statistieken, status).
 * Toont links + ingelogde gebruiker + logout knop.
 *
 * Voor "aantal trainingen" badge: berekent live uit database.
 */

// Aantal trainingen van de huidige user ophalen voor de badge
$aantalTrainingen = 0;
if (isset($pdo) && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM trainings WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $aantalTrainingen = (int) $stmt->fetchColumn();
}
?>
<nav>
    <ul>
        <li><a href="homepage.php">Home</a></li>
        <li><a href="planner.php">Training Plannen <?= $aantalTrainingen > 0 ? "({$aantalTrainingen})" : "" ?></a></li>
        <li><a href="statistieken.php">Statistieken</a></li>
        <li><a href="status.php">Systeem Status</a></li>
        <li class="nav-user">
            Ingelogd als: <strong><?= htmlspecialchars($_SESSION['username'] ?? '') ?></strong>
        </li>
        <li><a href="logout.php" class="nav-logout">Uitloggen</a></li>
    </ul>
</nav>
