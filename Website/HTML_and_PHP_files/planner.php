<?php
/**
 * SportFlow - Trainings planner
 *
 * Functionaliteiten:
 *   - Nieuwe training toevoegen (gekoppeld aan ingelogde user)
 *   - Lijst met alleen EIGEN trainingen
 *   - Delete-knop per training (eigen trainingen)
 *
 * Gebruikt prepared statements (SQL-injectie veilig) en CSRF-tokens.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
vereisLogin();

$user_id       = $_SESSION['user_id'];
$foutmelding   = '';
$succesmelding = '';

// ════════════════════════════════════════════════
// TRAINING TOEVOEGEN
// ════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_training'])) {
    csrf_check();

    $datum        = $_POST['datum']   ?? '';
    $workout_type = trim($_POST['workout'] ?? '');
    $duur         = $_POST['duur']    ?? '';

    // Datum validatie: niet vóór 2020 en niet meer dan 1 jaar in de toekomst
    $minDatum = '2020-01-01';
    $maxDatum = date('Y-m-d', strtotime('+1 year'));

    if ($datum === '' || $workout_type === '' || $duur === '') {
        $foutmelding = "Vul alle velden in.";
    } elseif ($datum < $minDatum || $datum > $maxDatum) {
        $foutmelding = "Kies een geldige datum (tussen $minDatum en $maxDatum).";
    } elseif (!ctype_digit((string) $duur) || (int) $duur < 1 || (int) $duur > 1440) {
        $foutmelding = "Duur moet een geheel getal zijn tussen 1 en 1440 minuten.";
    } elseif (strlen($workout_type) > 100) {
        $foutmelding = "Type workout mag maximaal 100 tekens zijn.";
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO trainings (user_id, datum, workout_type, duur_minuten)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$user_id, $datum, $workout_type, (int) $duur]);
        $succesmelding = "Training toegevoegd!";
    }
}

// ════════════════════════════════════════════════
// TRAINING VERWIJDEREN
// ════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_training'])) {
    csrf_check();

    $training_id = (int) ($_POST['training_id'] ?? 0);

    // BELANGRIJK: filter op user_id zodat je niet andermans trainingen kan wissen
    $stmt = $pdo->prepare("DELETE FROM trainings WHERE id = ? AND user_id = ?");
    $stmt->execute([$training_id, $user_id]);

    if ($stmt->rowCount() > 0) {
        $succesmelding = "Training verwijderd.";
    } else {
        $foutmelding = "Verwijderen mislukt (training niet gevonden of niet van jou).";
    }
}

// ════════════════════════════════════════════════
// EIGEN TRAININGEN OPHALEN
// ════════════════════════════════════════════════
$stmt = $pdo->prepare(
    "SELECT id, datum, workout_type, duur_minuten
     FROM trainings
     WHERE user_id = ?
     ORDER BY datum DESC, id DESC"
);
$stmt->execute([$user_id]);
$trainingen = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>SportFlow - Planner</title>
    <link rel="stylesheet" href="../CSSfiles/style.css">
    <link rel="stylesheet" href="../CSSfiles/components.css">
</head>
<body>
    <h1>Training Plannen</h1>

    <?php require __DIR__ . '/_nav.php'; ?>

    <hr>

    <?= alert($foutmelding, 'error') ?>
    <?= alert($succesmelding, 'success') ?>

    <h2>Nieuwe training toevoegen</h2>
    <!-- ➤ Formulier nieuwe training -->
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <label>Datum:</label>
        <input type="date" name="datum" required
               min="2020-01-01"
               max="<?= date('Y-m-d', strtotime('+1 year')) ?>"
               value="<?= date('Y-m-d') ?>">

        <label>Soort workout:</label>
        <input type="text" name="workout" placeholder="bijv. Krachttraining" maxlength="100" required>

        <label>Duur (minuten):</label>
        <input type="number" name="duur" min="1" max="1440" required>

        <button type="submit" name="add_training">Opslaan in Database</button>
    </form>

    <hr>

    <h3>Eerdere trainingen</h3>
    <?php if (count($trainingen) === 0): ?>
        <p><em>Je hebt nog geen trainingen opgeslagen. Voeg er hierboven eentje toe!</em></p>
    <?php else: ?>
        <table border="1">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Workout</th>
                    <th>Duur (min)</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trainingen as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['datum']) ?></td>
                        <td><?= htmlspecialchars($t['workout_type']) ?></td>
                        <td><?= htmlspecialchars($t['duur_minuten']) ?></td>
                        <td>
                            <!-- ➤ Verwijder formulier per training -->
                            <form method="POST" action="" style="display:inline; padding:0; border:none; background:none;"
                                  onsubmit="return confirm('Weet je zeker dat je deze training wil verwijderen?');">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="training_id" value="<?= (int) $t['id'] ?>">
                                <button type="submit" name="delete_training" class="btn-small">Verwijder</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
