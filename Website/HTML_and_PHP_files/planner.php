<?php
/**
 * SportFlow - Trainings planner (v3)
 *
 * Krachttraining ondersteunt nu MEERDERE oefeningen,
 * elk met eigen sets/reps/gewicht. Andere types blijven werken.
 * Tabel toont inklapbare details per training.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
vereisLogin();

$user_id       = $_SESSION['user_id'];
$foutmelding   = '';
$succesmelding = '';

// ════════════════════════════════════════════════
// WORKOUT TYPES ophalen
// ════════════════════════════════════════════════
$stmt = $pdo->query("SELECT naam, categorie FROM workout_types ORDER BY categorie, naam");
$workoutTypes = $stmt->fetchAll();

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
    $afstand      = $_POST['afstand']       ?? null;
    $calorieen    = $_POST['calorieen']     ?? null;

    // Oefeningen array (alleen voor kracht)
    $oef_namen    = $_POST['oef_naam']    ?? [];
    $oef_sets     = $_POST['oef_sets']    ?? [];
    $oef_reps     = $_POST['oef_reps']    ?? [];
    $oef_gewicht  = $_POST['oef_gewicht'] ?? [];

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
        if ($categorie !== 'cardio') {
            $afstand = $calorieen = null;
        }
        if ($categorie === 'kracht') {
            $intensiteit = null;
        }

        $afstand    = ($afstand    === '' || $afstand    === null) ? null : (float) $afstand;
        $calorieen  = ($calorieen  === '' || $calorieen  === null) ? null : (int) $calorieen;
        $intensiteit = in_array($intensiteit, ['laag','midden','hoog'], true) ? $intensiteit : null;
        $notitie    = $notitie === '' ? null : $notitie;

        // Insert training
        $stmt = $pdo->prepare(
            "INSERT INTO trainings
                (user_id, datum, workout_type, duur_minuten,
                 afstand_km, calorieen, intensiteit, notitie)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $user_id, $datum, $workout_type, (int) $duur,
            $afstand, $calorieen, $intensiteit, $notitie
        ]);
        $training_id = (int) $pdo->lastInsertId();

        // Bij krachttraining: oefeningen wegschrijven
        if ($categorie === 'kracht' && is_array($oef_namen)) {
            $stmtOef = $pdo->prepare(
                "INSERT INTO training_oefeningen
                    (training_id, naam, sets, reps, gewicht_kg, volgorde)
                 VALUES (?,?,?,?,?,?)"
            );
            $volgorde = 0;
            foreach ($oef_namen as $i => $naam) {
                $naam = trim((string) $naam);
                if ($naam === '') continue; // skip lege rijen

                $sets    = isset($oef_sets[$i])    && $oef_sets[$i]    !== '' ? (int) $oef_sets[$i]    : null;
                $reps    = isset($oef_reps[$i])    && $oef_reps[$i]    !== '' ? (int) $oef_reps[$i]    : null;
                $gewicht = isset($oef_gewicht[$i]) && $oef_gewicht[$i] !== '' ? (float) $oef_gewicht[$i] : null;

                $stmtOef->execute([$training_id, $naam, $sets, $reps, $gewicht, $volgorde]);
                $volgorde++;
            }
        }

        $succesmelding = "Training toegevoegd!";
    }
}

// ════════════════════════════════════════════════
// TRAINING VERWIJDEREN
// ════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_training'])) {
    csrf_check();

    $training_id = (int) ($_POST['training_id'] ?? 0);
    // ON DELETE CASCADE op de FK zorgt dat oefeningen mee verwijderd worden
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

// Oefeningen per training ophalen (één query voor alles)
$oefeningenPerTraining = [];
if (count($trainingen) > 0) {
    $ids = array_column($trainingen, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmtOef = $pdo->prepare(
        "SELECT * FROM training_oefeningen
         WHERE training_id IN ($placeholders)
         ORDER BY training_id, volgorde"
    );
    $stmtOef->execute($ids);
    foreach ($stmtOef->fetchAll() as $oef) {
        $oefeningenPerTraining[$oef['training_id']][] = $oef;
    }
}

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

        <!-- KRACHT: dynamische lijst van oefeningen -->
        <div class="velden-groep" data-categorie="kracht" style="display:none;">
            <label>Oefeningen:</label>
            <p class="hint">Voeg per oefening sets, reps en gewicht toe. Klik op + voor extra oefeningen.</p>

            <div id="oefeningenLijst">
                <!-- JavaScript voegt hier rijen toe -->
            </div>

            <button type="button" id="addOefeningBtn" class="btn-secondary">+ Oefening toevoegen</button>
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

        <!-- NOTITIE -->
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
                    <th style="width:40px;"></th>
                    <th>Datum</th>
                    <th>Type</th>
                    <th>Duur</th>
                    <th>Samenvatting</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trainingen as $t):
                    $oef = $oefeningenPerTraining[$t['id']] ?? [];
                    $heeftDetails = !empty($oef) || $t['notitie'] !== null;
                ?>
                    <tr class="training-rij" data-id="<?= (int) $t['id'] ?>">
                        <td>
                            <?php if ($heeftDetails): ?>
                                <button type="button" class="toggle-btn" aria-expanded="false">▶</button>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($t['datum']) ?></td>
                        <td><?= htmlspecialchars($t['workout_type']) ?></td>
                        <td><?= htmlspecialchars($t['duur_minuten']) ?> min</td>
                        <td class="details-cel">
                            <?php
                                $samenvatting = [];
                                if ($t['categorie'] === 'kracht' && !empty($oef)) {
                                    $samenvatting[] = count($oef) . " oefening" . (count($oef) === 1 ? '' : 'en');
                                }
                                if ($t['afstand_km']  !== null) $samenvatting[] = $t['afstand_km'] . " km";
                                if ($t['calorieen']   !== null) $samenvatting[] = $t['calorieen'] . " kcal";
                                if ($t['intensiteit'] !== null) $samenvatting[] = ucfirst($t['intensiteit']);
                                echo $samenvatting ? implode(" • ", $samenvatting) : "<em>geen extra info</em>";
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
                    <?php if ($heeftDetails): ?>
                    <tr class="details-rij" id="details-<?= (int) $t['id'] ?>" style="display:none;">
                        <td colspan="6">
                            <div class="details-inhoud">
                                <?php if (!empty($oef)): ?>
                                    <strong>Oefeningen:</strong>
                                    <table class="oefeningen-tabel">
                                        <thead>
                                            <tr>
                                                <th>Oefening</th>
                                                <th>Sets</th>
                                                <th>Reps</th>
                                                <th>Gewicht</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($oef as $o): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($o['naam']) ?></td>
                                                    <td><?= $o['sets'] !== null ? (int) $o['sets'] : '–' ?></td>
                                                    <td><?= $o['reps'] !== null ? (int) $o['reps'] : '–' ?></td>
                                                    <td><?= $o['gewicht_kg'] !== null ? $o['gewicht_kg'] . ' kg' : '–' ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>

                                <?php if ($t['notitie'] !== null): ?>
                                    <p><strong>Notitie:</strong> <?= htmlspecialchars($t['notitie']) ?></p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

<script>
// ════════════════════════════════════════════════
// Dynamische velden per workout categorie
// ════════════════════════════════════════════════
const typesNaarCategorie = <?= $typesJson ?>;
const select = document.getElementById('workoutSelect');
const groepen = document.querySelectorAll('.velden-groep');

function updateVelden() {
    const categorie = typesNaarCategorie[select.value] || '';
    groepen.forEach(groep => {
        const groepCategories = groep.dataset.categorie.split(' ');
        groep.style.display = groepCategories.includes(categorie) ? 'block' : 'none';
    });
    // Zorg dat er bij kracht minstens 1 oefening-rij staat
    if (categorie === 'kracht' && document.querySelectorAll('.oefening-rij').length === 0) {
        addOefeningRij();
    }
}

select.addEventListener('change', updateVelden);

// ════════════════════════════════════════════════
// Oefeningen lijst (kracht): + en X knoppen
// ════════════════════════════════════════════════
const lijst = document.getElementById('oefeningenLijst');
const addBtn = document.getElementById('addOefeningBtn');

function addOefeningRij() {
    const rij = document.createElement('div');
    rij.className = 'oefening-rij';
    rij.innerHTML = `
        <input type="text"   name="oef_naam[]"    placeholder="Naam (bv. Bench Press)" maxlength="100">
        <input type="number" name="oef_sets[]"    placeholder="Sets"     min="1" max="50">
        <input type="number" name="oef_reps[]"    placeholder="Reps"     min="1" max="500">
        <input type="number" name="oef_gewicht[]" placeholder="Kg"       min="0" max="999" step="0.5">
        <button type="button" class="btn-remove" aria-label="Verwijder oefening">✕</button>
    `;
    lijst.appendChild(rij);

    rij.querySelector('.btn-remove').addEventListener('click', () => {
        rij.remove();
        // Als alle rijen weg zijn, voeg een lege terug
        if (lijst.querySelectorAll('.oefening-rij').length === 0) {
            addOefeningRij();
        }
    });
}

addBtn.addEventListener('click', addOefeningRij);

// ════════════════════════════════════════════════
// Tabel: inklapbare details per training
// ════════════════════════════════════════════════
document.querySelectorAll('.toggle-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const rij = btn.closest('.training-rij');
        const id = rij.dataset.id;
        const details = document.getElementById('details-' + id);
        const open = details.style.display !== 'none';
        details.style.display = open ? 'none' : 'table-row';
        btn.textContent = open ? '▶' : '▼';
        btn.setAttribute('aria-expanded', open ? 'false' : 'true');
    });
});

// Initieel uitvoeren
updateVelden();
</script>

</body>
</html>