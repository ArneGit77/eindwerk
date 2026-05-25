<?php
/**
 * SportFlow - Homepage / Dashboard
 *
 * Toont:
 *   1. Welkom + streak
 *   2. Weekdoel sessies (voortgangsbalk)
 *   3. Weekdoel minuten (voortgangsbalk)
 *   4. Gewicht snelinvoer + huidig gewicht + verschil + historiek-knop
 *   5. Doelen kaartje (direct invulbaar)
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
vereisLogin();

$user_id     = $_SESSION['user_id'];
$foutmelding = '';
$succesmelding = '';

// ════════════════════════════════════════════════
// FORMULIER: gewicht toevoegen
// ════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_weight'])) {
    csrf_check();
    $gewicht = $_POST['gewicht'] ?? '';

    if ($gewicht === '' || !is_numeric($gewicht)) {
        $foutmelding = "Vul een geldig gewicht in.";
    } elseif ((float) $gewicht < 20 || (float) $gewicht > 400) {
        $foutmelding = "Gewicht moet tussen 20 en 400 kg liggen.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO body_weight (user_id, gewicht_kg) VALUES (?, ?)");
        $stmt->execute([$user_id, (float) $gewicht]);
        $succesmelding = "Gewicht opgeslagen!";
    }
}

// ════════════════════════════════════════════════
// FORMULIER: gewicht verwijderen (uit historiek)
// ════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_weight'])) {
    csrf_check();
    $weight_id = (int) ($_POST['weight_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM body_weight WHERE id = ? AND user_id = ?");
    $stmt->execute([$weight_id, $user_id]);
    if ($stmt->rowCount() > 0) {
        $succesmelding = "Gewicht-meting verwijderd.";
    }
}

// ════════════════════════════════════════════════
// FORMULIER: doelen opslaan
// ════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_goals'])) {
    csrf_check();

    $weekly_sessions = $_POST['weekly_sessions'] ?? '';
    $weekly_minutes  = $_POST['weekly_minutes']  ?? '';
    $target_weight   = $_POST['target_weight']   ?? '';

    $weekly_sessions = ($weekly_sessions === '' || !ctype_digit((string) $weekly_sessions)) ? null : (int) $weekly_sessions;
    $weekly_minutes  = ($weekly_minutes  === '' || !ctype_digit((string) $weekly_minutes))  ? null : (int) $weekly_minutes;
    $target_weight   = ($target_weight   === '' || !is_numeric($target_weight))             ? null : (float) $target_weight;

    // Limieten
    if ($weekly_sessions !== null && ($weekly_sessions < 0 || $weekly_sessions > 30)) {
        $foutmelding = "Aantal sessies/week moet tussen 0 en 30 liggen.";
    } elseif ($weekly_minutes !== null && ($weekly_minutes < 0 || $weekly_minutes > 10000)) {
        $foutmelding = "Aantal minuten/week moet tussen 0 en 10000 liggen.";
    } elseif ($target_weight !== null && ($target_weight < 20 || $target_weight > 400)) {
        $foutmelding = "Doelgewicht moet tussen 20 en 400 kg liggen.";
    } else {
        // INSERT of UPDATE
        $stmt = $pdo->prepare(
            "INSERT INTO goals (user_id, weekly_sessions, weekly_minutes, target_weight_kg)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE
                 weekly_sessions = VALUES(weekly_sessions),
                 weekly_minutes = VALUES(weekly_minutes),
                 target_weight_kg = VALUES(target_weight_kg)"
        );
        $stmt->execute([$user_id, $weekly_sessions, $weekly_minutes, $target_weight]);
        $succesmelding = "Doelen opgeslagen!";
    }
}

// ════════════════════════════════════════════════
// DATA OPHALEN
// ════════════════════════════════════════════════

// Huidige doelen
$stmt = $pdo->prepare("SELECT * FROM goals WHERE user_id = ?");
$stmt->execute([$user_id]);
$goals = $stmt->fetch() ?: ['weekly_sessions'=>null, 'weekly_minutes'=>null, 'target_weight_kg'=>null];

// Gewicht: laatste meting + vorige meting (voor verschil)
$stmt = $pdo->prepare("SELECT id, gewicht_kg, gemeten_op FROM body_weight WHERE user_id = ? ORDER BY gemeten_op DESC LIMIT 2");
$stmt->execute([$user_id]);
$gewichtMetingen = $stmt->fetchAll();
$huidigGewicht = $gewichtMetingen[0]['gewicht_kg'] ?? null;
$vorigGewicht  = $gewichtMetingen[1]['gewicht_kg'] ?? null;
$gewichtVerschil = ($huidigGewicht !== null && $vorigGewicht !== null)
    ? round($huidigGewicht - $vorigGewicht, 2)
    : null;

// Gewicht historiek (laatste 20)
$stmt = $pdo->prepare("SELECT id, gewicht_kg, gemeten_op FROM body_weight WHERE user_id = ? ORDER BY gemeten_op DESC LIMIT 20");
$stmt->execute([$user_id]);
$gewichtHistoriek = $stmt->fetchAll();

// Trainingen deze week (maandag 00:00 → nu)
$weekStart = date('Y-m-d', strtotime('monday this week'));
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS aantal, COALESCE(SUM(duur_minuten),0) AS minuten
     FROM trainings
     WHERE user_id = ? AND datum >= ?"
);
$stmt->execute([$user_id, $weekStart]);
$weekStats = $stmt->fetch();
$sessiesDezeWeek = (int) $weekStats['aantal'];
$minutenDezeWeek = (int) $weekStats['minuten'];

// Streak berekenen (opeenvolgende dagen met minstens 1 training)
$stmt = $pdo->prepare(
    "SELECT DISTINCT datum FROM trainings WHERE user_id = ? ORDER BY datum DESC LIMIT 365"
);
$stmt->execute([$user_id]);
$dagenMetTraining = array_column($stmt->fetchAll(), 'datum');

$streak = 0;
$vandaag = new DateTime('today');
$gisteren = (clone $vandaag)->modify('-1 day');

// Streak telt vanaf vandaag óf gisteren (zodat je hem niet verliest als je vandaag nog niet sportte)
$startDatum = null;
if (in_array($vandaag->format('Y-m-d'), $dagenMetTraining, true)) {
    $startDatum = $vandaag;
} elseif (in_array($gisteren->format('Y-m-d'), $dagenMetTraining, true)) {
    $startDatum = $gisteren;
}

if ($startDatum !== null) {
    $checkDatum = clone $startDatum;
    while (in_array($checkDatum->format('Y-m-d'), $dagenMetTraining, true)) {
        $streak++;
        $checkDatum->modify('-1 day');
    }
}

// Helper voor voortgangsbalk percentage
function percent($huidig, $doel) {
    if (!$doel || $doel <= 0) return 0;
    return min(100, round(($huidig / $doel) * 100));
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>SportFlow - Home</title>
    <link rel="stylesheet" href="../CSSfiles/style.css">
    <link rel="stylesheet" href="../CSSfiles/components.css">
</head>
<body>
    <h1>SportFlow Dashboard</h1>

    <?php require __DIR__ . '/_nav.php'; ?>

    <hr>

    <?= alert($foutmelding, 'error') ?>
    <?= alert($succesmelding, 'success') ?>

    <!-- ═══ Welkom + Streak ═══ -->
    <div class="dashboard-welkom">
        <h2>Welkom terug, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>
        <?php if ($streak > 0): ?>
            <p class="streak-text">🔥 Je hebt <strong><?= $streak ?></strong> dag<?= $streak === 1 ? '' : 'en' ?> op rij gesport!</p>
        <?php else: ?>
            <p class="streak-text">Voeg een training toe om je streak te starten 💪</p>
        <?php endif; ?>
    </div>

    <!-- ═══ Weekdoelen grid ═══ -->
    <div class="dashboard-grid">

        <!-- Weekdoel sessies -->
        <div class="dashboard-kaart">
            <h3>Weekdoel: trainingen</h3>
            <?php if ($goals['weekly_sessions']): ?>
                <p class="grote-cijfer">
                    <?= $sessiesDezeWeek ?> / <?= $goals['weekly_sessions'] ?>
                </p>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= percent($sessiesDezeWeek, $goals['weekly_sessions']) ?>%;"></div>
                </div>
                <?php
                $nog = $goals['weekly_sessions'] - $sessiesDezeWeek;
                if ($nog <= 0) echo "<p class='success-tekst'>Doel behaald! 🎉</p>";
                else echo "<p class='hint'>Nog $nog te gaan deze week</p>";
                ?>
            <?php else: ?>
                <p class="hint">Stel een doel in (zie kaart onderaan)</p>
            <?php endif; ?>
        </div>

        <!-- Weekdoel minuten -->
        <div class="dashboard-kaart">
            <h3>Weekdoel: minuten</h3>
            <?php if ($goals['weekly_minutes']): ?>
                <p class="grote-cijfer">
                    <?= $minutenDezeWeek ?> / <?= $goals['weekly_minutes'] ?>
                </p>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= percent($minutenDezeWeek, $goals['weekly_minutes']) ?>%;"></div>
                </div>
                <?php
                $nog = $goals['weekly_minutes'] - $minutenDezeWeek;
                if ($nog <= 0) echo "<p class='success-tekst'>Doel behaald! 🎉</p>";
                else echo "<p class='hint'>Nog $nog minuten te gaan</p>";
                ?>
            <?php else: ?>
                <p class="hint">Stel een doel in (zie kaart onderaan)</p>
            <?php endif; ?>
        </div>

        <!-- Huidig gewicht + verschil -->
        <div class="dashboard-kaart">
            <h3>Huidig gewicht</h3>
            <?php if ($huidigGewicht !== null): ?>
                <p class="grote-cijfer"><?= $huidigGewicht ?> kg</p>
                <?php if ($gewichtVerschil !== null && $gewichtVerschil != 0): ?>
                    <p class="verschil-tekst <?= $gewichtVerschil > 0 ? 'omhoog' : 'omlaag' ?>">
                        <?= $gewichtVerschil > 0 ? '▲' : '▼' ?>
                        <?= abs($gewichtVerschil) ?> kg sinds vorige meting
                    </p>
                <?php endif; ?>
                <?php if ($goals['target_weight_kg']): ?>
                    <?php $tot = round($huidigGewicht - $goals['target_weight_kg'], 2); ?>
                    <p class="hint">
                        Doel: <?= $goals['target_weight_kg'] ?> kg
                        <?php if ($tot > 0) echo "({$tot} kg te gaan)"; ?>
                        <?php if ($tot < 0) echo "(" . abs($tot) . " kg onder doel)"; ?>
                        <?php if ($tot == 0) echo "(doel behaald!)"; ?>
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <p class="hint">Nog geen meting</p>
            <?php endif; ?>
        </div>

    </div>

    <!-- ═══ Gewicht invoer + historiek ═══ -->
    <div class="dashboard-kaart-breed">
        <h3>Gewicht bijhouden</h3>

        <form method="POST" action="" class="inline-flex-form">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="number" name="gewicht" step="0.1" min="20" max="400"
                   placeholder="bv. 75.5" required style="max-width:200px;">
            <button type="submit" name="add_weight" class="btn-inline">Opslaan</button>
        </form>

        <?php if (!empty($gewichtHistoriek)): ?>
            <button type="button" id="toggleHistoriek" class="btn-secondary">Bekijk historiek</button>

            <div id="historiekLijst" style="display:none;">
                <table class="historiek-tabel">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Gewicht</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gewichtHistoriek as $m): ?>
                            <tr>
                                <td><?= htmlspecialchars($m['gemeten_op']) ?></td>
                                <td><?= htmlspecialchars($m['gewicht_kg']) ?> kg</td>
                                <td>
                                    <form method="POST" action="" class="inline-form"
                                          onsubmit="return confirm('Deze meting verwijderen?');">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="weight_id" value="<?= (int) $m['id'] ?>">
                                        <button type="submit" name="delete_weight" class="btn-small">Verwijder</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- ═══ Doelen instellen ═══ -->
    <div class="dashboard-kaart-breed">
        <h3>Mijn doelen</h3>
        <p class="hint">Stel je weekdoelen en doelgewicht in. Laat leeg om geen doel te gebruiken.</p>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="doelen-grid">
                <div>
                    <label>Trainingen per week</label>
                    <input type="number" name="weekly_sessions" min="0" max="30"
                           value="<?= htmlspecialchars($goals['weekly_sessions'] ?? '') ?>"
                           placeholder="bv. 4">
                </div>
                <div>
                    <label>Minuten per week</label>
                    <input type="number" name="weekly_minutes" min="0" max="10000"
                           value="<?= htmlspecialchars($goals['weekly_minutes'] ?? '') ?>"
                           placeholder="bv. 240">
                </div>
                <div>
                    <label>Doelgewicht (kg)</label>
                    <input type="number" name="target_weight" step="0.1" min="20" max="400"
                           value="<?= htmlspecialchars($goals['target_weight_kg'] ?? '') ?>"
                           placeholder="bv. 75">
                </div>
            </div>

            <button type="submit" name="save_goals">Doelen opslaan</button>
        </form>
    </div>

<script>
// Historiek inklapbaar
const toggleBtn = document.getElementById('toggleHistoriek');
if (toggleBtn) {
    const lijst = document.getElementById('historiekLijst');
    toggleBtn.addEventListener('click', () => {
        const open = lijst.style.display !== 'none';
        lijst.style.display = open ? 'none' : 'block';
        toggleBtn.textContent = open ? 'Bekijk historiek' : 'Verberg historiek';
    });
}
</script>

</body>
</html>