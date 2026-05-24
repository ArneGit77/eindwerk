<?php
/**
 * SportFlow - Trainings planner (uitgebreide versie)
 *
 * Functionaliteiten:
 *   - Workout type als dropdown (uit workout_types tabel)
 *   - Dynamische velden per categorie (kracht/cardio/team/anders)
 *   - Validatie afhankelijk van type
 *   - Lijst eigen trainingen + delete
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
vereisLogin();

$user_id       = $_SESSION['user_id'];
$foutmelding   = '';
$succesmelding = '';

// ════════════════════════════════════════════════
// WORKOUT TYPES ophalen voor dropdown
// ════════════════════════════════════════════════
$stmt = $pdo->query("SELECT naam, categorie FROM workout_types ORDER BY categorie, naam");
$workoutTypes = $stmt->fetchAll();

// Bouw een lookup: naam => categorie (nodig voor server-side validatie)
$typeNaarCategorie = [];
foreach ($workoutTypes as $wt) {
    $typeNaarCategorie[$wt['naam']] = $wt['categorie'];
}

// ════════════════════════════════════════════════
// TRAINING TOEVOEGEN
// ════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_training'])) {
    csrf_check();

    $datum        = $_POST['datum']         ?? '';
    $workout_type = trim($_POST['workout']  ?? '');
    $duur         = $_POST['duur']          ?? '';
    $intensiteit  = $_POST['intensiteit']   ?? null;
    $notitie      = trim($_POST['notitie']  ?? '');

    // Kracht velden
    $sets       = $_POST['sets']        ?? null;
    $reps       = $_POST['reps']        ?? null;
    $gewicht    = $_POST['gewicht']     ?? null;
    $oefeningen = trim($_POST['oefeningen'] ?? '');

    // Cardio velden
    $afstand    = $_POST['afstand']     ?? null;
    $calorieen  = $_POST['calorieen']   ?? null;

    // Validatie datum & duur (zoals voorheen)
    $minDatum = '2020-01-01';
    $maxDatum = date('Y-m-d', strtotime('+1 year'));

    if ($datum === '' || $workout_type === '' || $duur === '') {
        $foutmelding = "Vul alle verplichte velden in (datum, type, duur).";
    } elseif (!isset($typeNaarCategorie[$workout_type])) {
        $foutmelding = "Ongeldig workout type gekozen.";
    } elseif ($datum < $minDatum || $datum > $maxDatum) {
        $foutmelding = "Kies een geldige datum.";
    } elseif (!ctype_digit((string) $duur) || (int) $duur < 1 || (int) $duur > 1440) {
        $foutmelding = "Duur moet tussen 1 en 1440 minuten zijn.";
    } else {
        $categorie = $typeNaarCategorie[$workout_type];

        // Velden die niet bij deze categorie horen → leegmaken
        if ($categorie !== 'kracht') {
            $sets = $reps = $gewicht = null;
            $oefeningen = null;
        }
        if ($categorie !== 'cardio') {
            $afstand = $calorieen = null;
        }
        if ($categorie === 'kracht') {
            $intensiteit = null; // bij kracht geen losse intensiteit
        }

        // Lege strings → NULL
        $sets       = ($sets       === '' || $sets       === null) ? null : (int) $sets;
        $reps       = ($reps       === '' || $reps       === null) ? null : (int) $reps;
        $gewicht    = ($gewicht    === '' || $gewicht    === null) ? null : (float) $gewicht;
        $oefeningen = ($oefeningen === '' || $oefeningen === null) ? null : $oefeningen;
        $afstand    = ($afstand    === '' || $afstand    === null) ? null : (float) $afstand;
        $calorieen  = ($calorieen  === '' || $calorieen  === null) ? null : (int) $calorieen;
        $intensiteit = in_array($intensiteit, ['laag','midden','hoog'], true) ? $intensiteit : null;
        $notitie    = $notitie === '' ? null : $notitie;

        $stmt = $pdo->prepare(
            "INSERT INTO trainings
                (user_id, datum, workout_type, duur_minuten,
                 afstand_km, sets, reps, gewicht_kg, oefeningen,
                 calorieen, intensiteit, notitie)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $user_id, $datum, $workout_type, (int) $duur,
            $afstand, $sets, $reps, $gewicht, $oefeningen,
            $calorieen, $intensiteit, $notitie
        ]);
        $succesmelding = "Training toegevoegd!";
    }
}

// ════════════════════════════════════════════════
// TRAINING VERWIJDEREN
// ════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_training'])) {
    csrf_check();

    $training_id = (int) ($_POST['training_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM trainings WHERE id = ? AND user_id = ?");
    $stmt->execute([$training_id, $user_id]);

    if ($stmt->rowCount() > 0) {
        $succesmelding = "Training verwijderd.";
    } else {
        $foutmelding = "Verwijderen mislukt.";
    }
}

// ════════════════════════════════════════════════
// EIGEN TRAININGEN OPHALEN
// ════════════════════════════════════════════════
$stmt = $pdo->prepare(
    "SELECT t.*, w.categorie
     FROM trainings t
     LEFT JOIN workout_types w ON w.naam = t.workout_type
     WHERE t.user_id = ?
     ORDER BY t.datum DESC, t.id DESC"
);
$stmt->execute([$user_id]);
$trainingen = $stmt->fetchAll();

// JS-vriendelijke lookup van types → categorieën (voor dynamische velden)
$typesJson = json_encode($typeNaarCategorie, JSON_UNESCAPED_UNICODE);
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
    <form method="POST" action="" id="trainingForm">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <!-- Basis velden (altijd zichtbaar) -->
        <label>Datum:</label>
        <input type="date" name="datum" required
               min="2020-01-01"
               max="<?= date('Y-m-d', strtotime('+1 year')) ?>"
               value="<?= date('Y-m-d') ?>">

        <label>Soort workout:</label>
        <select name="workout" id="workoutSelect" required>
            <option value="">-- kies een type --</option>
            <?php
            $vorigeCategorie = '';
            $categorieLabels = [
                'kracht' => 'Krachttraining',
                'cardio' => 'Cardio',
                'team'   => 'Teamsporten & Racket',
                'anders' => 'Andere',
            ];
            foreach ($workoutTypes as $wt):
                if ($wt['categorie'] !== $vorigeCategorie):
                    if ($vorigeCategorie !== '') echo "</optgroup>";
                    echo "<optgroup label=\"" . htmlspecialchars($categorieLabels[$wt['categorie']]) . "\">";
                    $vorigeCategorie = $wt['categorie'];
                endif;
            ?>
                <option value="<?= htmlspecialchars($wt['naam']) ?>">
                    <?= htmlspecialchars($wt['naam']) ?>
                </option>
            <?php endforeach; if ($vorigeCategorie !== '') echo "</optgroup>"; ?>
        </select>

        <label>Duur (minuten):</label>
        <input type="number" name="duur" min="1" max="1440" required>

        <!-- KRACHT velden -->
        <div class="velden-groep" data-categorie="kracht" style="display:none;">
            <label>Sets:</label>
            <input type="number" name="sets" min="1" max="50">

            <label>Reps per set:</label>
            <input type="number" name="reps" min="1" max="500">

            <label>Gewicht (kg):</label>
            <input type="number" name="gewicht" min="0" max="999" step="0.5">

            <label>Oefeningen (bv. squats, bench press):</label>
            <input type="text" name="oefeningen" maxlength="500">
        </div>

        <!-- CARDIO velden -->
        <div class="velden-groep" data-categorie="cardio" style="display:none;">
            <label>Afstand (km):</label>
            <input type="number" name="afstand" min="0" max="999" step="0.01">

            <label>Calorie&euml;n verbrand (optioneel):</label>
            <input type="number" name="calorieen" min="0" max="10000">
        </div>

        <!-- INTENSITEIT (cardio, team, anders) -->
        <div class="velden-groep" data-categorie="cardio team anders" style="display:none;">
            <label>Intensiteit:</label>
            <select name="intensiteit">
                <option value="">-- kies --</option>
                <option value="laag">Laag</option>
                <option value="midden">Midden</option>
                <option value="hoog">Hoog</option>
            </select>
        </div>

        <!-- NOTITIE (altijd zichtbaar zodra type gekozen is) -->
        <div class="velden-groep" data-categorie="kracht cardio team anders" style="display:none;">
            <label>Notitie / hoe voelde het:</label>
            <input type="text" name="notitie" maxlength="500">
        </div>

        <button type="submit" name="add_training">Opslaan in Database</button>
    </form>

    <hr>

    <h3>Eerdere trainingen</h3>
    <?php if (count($trainingen) === 0): ?>
        <p><em>Je hebt nog geen trainingen opgeslagen.</em></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Type</th>
                    <th>Duur</th>
                    <th>Details</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trainingen as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['datum']) ?></td>
                        <td><?= htmlspecialchars($t['workout_type']) ?></td>
                        <td><?= htmlspecialchars($t['duur_minuten']) ?> min</td>
                        <td class="details-cel">
                            <?php
                                $details = [];
                                if ($t['afstand_km'] !== null)  $details[] = $t['afstand_km'] . " km";
                                if ($t['calorieen']  !== null)  $details[] = $t['calorieen'] . " kcal";
                                if ($t['sets']       !== null)  $details[] = $t['sets'] . " sets";
                                if ($t['reps']       !== null)  $details[] = $t['reps'] . " reps";
                                if ($t['gewicht_kg'] !== null)  $details[] = $t['gewicht_kg'] . " kg";
                                if ($t['intensiteit']!== null)  $details[] = ucfirst($t['intensiteit']);
                                if ($t['oefeningen'] !== null)  $details[] = htmlspecialchars($t['oefeningen']);
                                if ($t['notitie']    !== null)  $details[] = "\"" . htmlspecialchars($t['notitie']) . "\"";
                                echo $details ? implode(" • ", $details) : "<em>geen extra info</em>";
                            ?>
                        </td>
                        <td>
                            <form method="POST" action="" class="inline-form"
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

<script>
// ════════════════════════════════════════════════
// Dynamische velden: tonen op basis van workout categorie
// ════════════════════════════════════════════════
const typesNaarCategorie = <?= $typesJson ?>;
const select = document.getElementById('workoutSelect');
const groepen = document.querySelectorAll('.velden-groep');

function updateVelden() {
    const gekozenType = select.value;
    const categorie = typesNaarCategorie[gekozenType] || '';

    groepen.forEach(groep => {
        const categorienVoorGroep = groep.dataset.categorie.split(' ');
        if (categorienVoorGroep.includes(categorie)) {
            groep.style.display = 'block';
        } else {
            groep.style.display = 'none';
        }
    });
}

select.addEventListener('change', updateVelden);
updateVelden(); // initieel uitvoeren
</script>

</body>
</html>