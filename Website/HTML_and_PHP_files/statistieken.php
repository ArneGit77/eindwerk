<?php
/**
 * SportFlow - Statistieken pagina (v3)
 *
 * Layout:
 *   - Gecentreerd, max-width container
 *   - Grote cards, max 2 grafieken naast elkaar
 *   - Sectie 3: staafdiagram + cards naast elkaar
 *   - Sectie 4: cards + 2 grafieken naast elkaar
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
vereisLogin();

$user_id  = $_SESSION['user_id'];
$periode  = $_GET['periode'] ?? 'alles';

switch ($periode) {
    case 'week':
        $sinds = date('Y-m-d', strtotime('-7 days'));
        $periodeLabel = 'Laatste week';
        break;
    case 'maand':
        $sinds = date('Y-m-d', strtotime('-30 days'));
        $periodeLabel = 'Laatste maand';
        break;
    default:
        $sinds = '2000-01-01';
        $periodeLabel = 'Alles';
        $periode = 'alles';
}

// SECTIE 1
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS aantal, COALESCE(SUM(duur_minuten), 0) AS totaal_minuten
     FROM trainings WHERE user_id = ? AND datum >= ?"
);
$stmt->execute([$user_id, $sinds]);
$sectie1 = $stmt->fetch();
$totaalUren = round($sectie1['totaal_minuten'] / 60, 1);

// SECTIE 2: Doelen
$stmt = $pdo->prepare("SELECT * FROM goals WHERE user_id = ?");
$stmt->execute([$user_id]);
$goals = $stmt->fetch() ?: ['weekly_sessions'=>null, 'weekly_minutes'=>null, 'target_weight_kg'=>null];

$weekStart = date('Y-m-d', strtotime('monday this week'));
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS aantal, COALESCE(SUM(duur_minuten),0) AS minuten
     FROM trainings WHERE user_id = ? AND datum >= ?"
);
$stmt->execute([$user_id, $weekStart]);
$weekStats = $stmt->fetch();

$stmt = $pdo->prepare(
    "SELECT gewicht_kg, gemeten_op FROM body_weight WHERE user_id = ? ORDER BY gemeten_op ASC"
);
$stmt->execute([$user_id]);
$gewichtData = $stmt->fetchAll();

// SECTIE 3: Streaks
$stmt = $pdo->prepare(
    "SELECT DISTINCT datum FROM trainings WHERE user_id = ? ORDER BY datum DESC LIMIT 365"
);
$stmt->execute([$user_id]);
$dagenMetTraining = array_column($stmt->fetchAll(), 'datum');

$huidigeStreak = 0;
$vandaag = new DateTime('today');
$gisteren = (clone $vandaag)->modify('-1 day');
$startDatum = null;
if (in_array($vandaag->format('Y-m-d'), $dagenMetTraining, true)) $startDatum = $vandaag;
elseif (in_array($gisteren->format('Y-m-d'), $dagenMetTraining, true)) $startDatum = $gisteren;
if ($startDatum !== null) {
    $checkDatum = clone $startDatum;
    while (in_array($checkDatum->format('Y-m-d'), $dagenMetTraining, true)) {
        $huidigeStreak++;
        $checkDatum->modify('-1 day');
    }
}

$langsteStreak = 0;
if (!empty($dagenMetTraining)) {
    $gesorteerd = $dagenMetTraining;
    sort($gesorteerd);
    $tempStreak = 1; $langsteStreak = 1;
    for ($i = 1; $i < count($gesorteerd); $i++) {
        $vorige = new DateTime($gesorteerd[$i - 1]);
        $huidige = new DateTime($gesorteerd[$i]);
        $diff = $vorige->diff($huidige)->days;
        if ($diff === 1) {
            $tempStreak++;
            if ($tempStreak > $langsteStreak) $langsteStreak = $tempStreak;
        } else $tempStreak = 1;
    }
}

// Trainingen per week
$stmt = $pdo->prepare(
    "SELECT YEARWEEK(datum, 1) AS jaarweek, MIN(datum) AS week_start, COUNT(*) AS aantal
     FROM trainings WHERE user_id = ?
     GROUP BY YEARWEEK(datum, 1) ORDER BY jaarweek ASC"
);
$stmt->execute([$user_id]);
$rawPerWeek = $stmt->fetchAll();

$trainingenPerWeek = [];
if (!empty($rawPerWeek)) {
    $lookup = [];
    foreach ($rawPerWeek as $row) $lookup[$row['jaarweek']] = (int) $row['aantal'];

    $eersteDatum = new DateTime($rawPerWeek[0]['week_start']);
    $eersteMaandag = (clone $eersteDatum)->modify('monday this week');
    $vandaagMaandag = (new DateTime('today'))->modify('monday this week');

    $cursor = clone $eersteMaandag;
    while ($cursor <= $vandaagMaandag) {
        $jw = $cursor->format('oW');
        $trainingenPerWeek[] = [
            'label' => $cursor->format('d/m'),
            'aantal' => $lookup[$jw] ?? 0,
        ];
        $cursor->modify('+7 days');
    }
}

// SECTIE 4
$gewichtsVerandering = null;
if (count($gewichtData) >= 2) {
    $gewichtsVerandering = round(end($gewichtData)['gewicht_kg'] - $gewichtData[0]['gewicht_kg'], 1);
}

$stmt = $pdo->prepare(
    "SELECT workout_type, COUNT(*) AS aantal
     FROM trainings WHERE user_id = ? AND datum >= ?
     GROUP BY workout_type ORDER BY aantal DESC LIMIT 1"
);
$stmt->execute([$user_id, $sinds]);
$meestGedaan = $stmt->fetch();
$favorietSport = $meestGedaan['workout_type'] ?? '–';

$gemDuur = $sectie1['aantal'] > 0 ? round($sectie1['totaal_minuten'] / $sectie1['aantal']) : 0;

$stmt = $pdo->prepare(
    "SELECT workout_type, COUNT(*) AS aantal
     FROM trainings WHERE user_id = ? AND datum >= ?
     GROUP BY workout_type ORDER BY aantal DESC"
);
$stmt->execute([$user_id, $sinds]);
$workoutVerdeling = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT datum, SUM(duur_minuten) AS minuten
     FROM trainings WHERE user_id = ? AND datum >= ?
     GROUP BY datum ORDER BY datum ASC"
);
$stmt->execute([$user_id, $sinds]);
$dagelijksMinuten = $stmt->fetchAll();

$cumulatief = []; $totaal = 0;
foreach ($dagelijksMinuten as $row) {
    $totaal += (int) $row['minuten'];
    $cumulatief[] = ['datum' => $row['datum'], 'totaal' => $totaal];
}

// JSON voor JS
$jsonGewicht = json_encode([
    'labels' => array_map(fn($r) => date('d/m', strtotime($r['gemeten_op'])), $gewichtData),
    'data'   => array_map(fn($r) => (float) $r['gewicht_kg'], $gewichtData),
    'doel'   => $goals['target_weight_kg'] !== null ? (float) $goals['target_weight_kg'] : null,
]);
$jsonWorkout = json_encode([
    'labels' => array_column($workoutVerdeling, 'workout_type'),
    'data'   => array_map('intval', array_column($workoutVerdeling, 'aantal')),
]);
$jsonCumulatief = json_encode([
    'labels' => array_map(fn($r) => date('d/m', strtotime($r['datum'])), $cumulatief),
    'data'   => array_column($cumulatief, 'totaal'),
]);
$jsonWeken = json_encode([
    'labels' => array_column($trainingenPerWeek, 'label'),
    'data'   => array_column($trainingenPerWeek, 'aantal'),
    'doel'   => $goals['weekly_sessions'] !== null ? (int) $goals['weekly_sessions'] : null,
]);

function percent($huidig, $doel) {
    if (!$doel || $doel <= 0) return 0;
    return min(100, round(($huidig / $doel) * 100));
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SportFlow - Statistieken</title>
    <link rel="stylesheet" href="../CSSfiles/style.css">
    <link rel="stylesheet" href="../CSSfiles/components.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <h1>Statistieken</h1>

    <?php require __DIR__ . '/_nav.php'; ?>

    <hr>

    <div class="stats-container">

        <!-- ═══ Periode filter ═══ -->
        <div class="periode-filter">
            <a href="?periode=week"  class="periode-knop <?= $periode === 'week'  ? 'actief' : '' ?>">Laatste week</a>
            <a href="?periode=maand" class="periode-knop <?= $periode === 'maand' ? 'actief' : '' ?>">Laatste maand</a>
            <a href="?periode=alles" class="periode-knop <?= $periode === 'alles' ? 'actief' : '' ?>">Alles</a>
        </div>

        <!-- ═══ Sectie 1 ═══ -->
        <details class="stat-sectie" open>
            <summary class="sectie-titel">🏆 Wat je al bereikt hebt</summary>
            <div class="sectie-inhoud">
                <div class="grid-2">
                    <div class="stat-kaart">
                        <strong>Totaal trainingen</strong>
                        <span class="grote-cijfer"><?= (int) $sectie1['aantal'] ?></span>
                        <p class="hint">in periode: <?= htmlspecialchars($periodeLabel) ?></p>
                    </div>
                    <div class="stat-kaart">
                        <strong>Totale tijd gesport</strong>
                        <span class="grote-cijfer"><?= $totaalUren ?> u</span>
                        <p class="hint"><?= (int) $sectie1['totaal_minuten'] ?> minuten in totaal</p>
                    </div>
                </div>
            </div>
        </details>

        <!-- ═══ Sectie 2 ═══ -->
        <details class="stat-sectie" open>
            <summary class="sectie-titel">🎯 Hoe gaat het met je doelen?</summary>
            <div class="sectie-inhoud">
                <div class="grid-2">
                    <div class="stat-kaart">
                        <strong>Weekdoel: trainingen</strong>
                        <?php if ($goals['weekly_sessions']): ?>
                            <p class="grote-cijfer"><?= (int) $weekStats['aantal'] ?> / <?= (int) $goals['weekly_sessions'] ?></p>
                            <div class="progress-bar"><div class="progress-fill" style="width: <?= percent($weekStats['aantal'], $goals['weekly_sessions']) ?>%;"></div></div>
                            <p class="hint">
                                <?php
                                    $nog = (int) $goals['weekly_sessions'] - (int) $weekStats['aantal'];
                                    echo $nog <= 0 ? "Doel behaald! 🎉" : "Nog $nog te gaan deze week";
                                ?>
                            </p>
                        <?php else: ?>
                            <p class="hint">Stel een doel in op de homepage</p>
                        <?php endif; ?>
                    </div>
                    <div class="stat-kaart">
                        <strong>Weekdoel: minuten</strong>
                        <?php if ($goals['weekly_minutes']): ?>
                            <p class="grote-cijfer"><?= (int) $weekStats['minuten'] ?> / <?= (int) $goals['weekly_minutes'] ?></p>
                            <div class="progress-bar"><div class="progress-fill" style="width: <?= percent($weekStats['minuten'], $goals['weekly_minutes']) ?>%;"></div></div>
                            <p class="hint">
                                <?php
                                    $nog = (int) $goals['weekly_minutes'] - (int) $weekStats['minuten'];
                                    echo $nog <= 0 ? "Doel behaald! 🎉" : "Nog $nog minuten te gaan";
                                ?>
                            </p>
                        <?php else: ?>
                            <p class="hint">Stel een doel in op de homepage</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (count($gewichtData) > 1): ?>
                    <div class="stat-kaart grafiek-kaart">
                        <strong>Gewicht over tijd</strong>
                        <?php if ($goals['target_weight_kg']): ?>
                            <p class="hint">Doelgewicht: <?= htmlspecialchars($goals['target_weight_kg']) ?> kg</p>
                        <?php endif; ?>
                        <div class="grafiek-wrap"><canvas id="gewichtGrafiek"></canvas></div>
                    </div>
                <?php else: ?>
                    <p class="hint" style="margin-top:15px;">Voeg minstens 2 gewicht-metingen toe op de homepage om een grafiek te zien.</p>
                <?php endif; ?>
            </div>
        </details>

        <!-- ═══ Sectie 3: Streaks ═══ -->
        <details class="stat-sectie">
            <summary class="sectie-titel">🔥 Streaks</summary>
            <div class="sectie-inhoud">
                <div class="grid-mix">
                    <!-- Links: 2 cards onder elkaar -->
                    <div class="grid-stack">
                        <div class="stat-kaart">
                            <strong>Huidige streak</strong>
                            <span class="grote-cijfer"><?= $huidigeStreak ?></span>
                            <p class="hint">dag<?= $huidigeStreak === 1 ? '' : 'en' ?> op rij</p>
                        </div>
                        <div class="stat-kaart">
                            <strong>Langste streak ooit</strong>
                            <span class="grote-cijfer"><?= $langsteStreak ?></span>
                            <p class="hint">dag<?= $langsteStreak === 1 ? '' : 'en' ?> recordhouder</p>
                        </div>
                    </div>

                    <!-- Rechts: staafdiagram -->
                    <?php if (count($trainingenPerWeek) > 0): ?>
                        <div class="stat-kaart grafiek-kaart">
                            <strong>Trainingen per week</strong>
                            <p class="hint">Sinds je eerste training (<?= count($trainingenPerWeek) ?> weken)</p>
                            <div class="grafiek-wrap"><canvas id="wekenGrafiek"></canvas></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </details>

        <!-- ═══ Sectie 4: Trainingsgewoontes ═══ -->
        <details class="stat-sectie">
            <summary class="sectie-titel">📊 Jouw trainingsgewoontes</summary>
            <div class="sectie-inhoud">
                <div class="grid-3">
                    <div class="stat-kaart">
                        <strong>Gewichtsverandering</strong>
                        <?php if ($gewichtsVerandering !== null): ?>
                            <span class="grote-cijfer <?= $gewichtsVerandering < 0 ? 'omlaag' : ($gewichtsVerandering > 0 ? 'omhoog' : '') ?>">
                                <?= $gewichtsVerandering > 0 ? '+' : '' ?><?= $gewichtsVerandering ?> kg
                            </span>
                            <p class="hint">sinds eerste meting</p>
                        <?php else: ?>
                            <span class="grote-cijfer">–</span>
                            <p class="hint">Te weinig metingen</p>
                        <?php endif; ?>
                    </div>
                    <div class="stat-kaart">
                        <strong>Meest gedane sport</strong>
                        <span class="medium-cijfer"><?= htmlspecialchars($favorietSport) ?></span>
                        <?php if (isset($meestGedaan['aantal'])): ?>
                            <p class="hint"><?= (int) $meestGedaan['aantal'] ?>x in periode</p>
                        <?php endif; ?>
                    </div>
                    <div class="stat-kaart">
                        <strong>Gemiddelde duur</strong>
                        <span class="grote-cijfer"><?= $gemDuur ?> min</span>
                        <p class="hint">per training</p>
                    </div>
                </div>

                <?php if (count($workoutVerdeling) > 0): ?>
                    <div class="grid-2 grafieken-rij">
                        <div class="stat-kaart grafiek-kaart">
                            <strong>Verdeling per workout type</strong>
                            <div class="grafiek-wrap"><canvas id="workoutDonut"></canvas></div>
                        </div>
                        <?php if (count($cumulatief) > 1): ?>
                            <div class="stat-kaart grafiek-kaart">
                                <strong>Cumulatief gesporte minuten</strong>
                                <p class="hint">Je totaal blijft maar groeien!</p>
                                <div class="grafiek-wrap"><canvas id="cumulatiefGrafiek"></canvas></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </details>

    </div>

<script>
const racingRed = '#ff003c';
const electricBlue = '#007cf0';
const textColor = '#e0e6ed';
const gridColor = '#1a2a40';

Chart.defaults.color = textColor;
Chart.defaults.borderColor = gridColor;
Chart.defaults.font.family = 'Montserrat, sans-serif';

<?php if (count($gewichtData) > 1): ?>
(() => {
    const data = <?= $jsonGewicht ?>;
    const datasets = [{
        label: 'Gewicht (kg)', data: data.data,
        borderColor: electricBlue, backgroundColor: 'rgba(0,124,240,0.15)',
        fill: true, tension: 0.3, pointBackgroundColor: racingRed, pointRadius: 4,
    }];
    if (data.doel) datasets.push({
        label: 'Doelgewicht', data: new Array(data.data.length).fill(data.doel),
        borderColor: racingRed, borderDash: [6, 4], pointRadius: 0, fill: false,
    });
    new Chart(document.getElementById('gewichtGrafiek'), {
        type: 'line', data: { labels: data.labels, datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: { ticks: { color: textColor }, grid: { color: gridColor } },
                x: { ticks: { color: textColor }, grid: { color: gridColor } },
            }
        }
    });
})();
<?php endif; ?>

<?php if (count($trainingenPerWeek) > 0): ?>
(() => {
    const data = <?= $jsonWeken ?>;
    const datasets = [{
        label: 'Aantal trainingen', data: data.data,
        backgroundColor: racingRed, borderColor: racingRed, borderWidth: 0,
    }];
    if (data.doel) datasets.push({
        label: 'Weekdoel', type: 'line', data: new Array(data.data.length).fill(data.doel),
        borderColor: electricBlue, borderDash: [6, 4], pointRadius: 0, fill: false,
    });
    new Chart(document.getElementById('wekenGrafiek'), {
        type: 'bar', data: { labels: data.labels, datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: { beginAtZero: true, ticks: { color: textColor, stepSize: 1 }, grid: { color: gridColor } },
                x: { ticks: { color: textColor }, grid: { color: gridColor } },
            }
        }
    });
})();
<?php endif; ?>

<?php if (count($workoutVerdeling) > 0): ?>
(() => {
    const data = <?= $jsonWorkout ?>;
    const kleuren = ['#ff003c','#007cf0','#ff6b00','#00d4aa','#9b59b6','#f1c40f','#e74c3c','#3498db','#1abc9c','#e67e22','#34495e'];
    new Chart(document.getElementById('workoutDonut'), {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{ data: data.data, backgroundColor: kleuren.slice(0, data.labels.length), borderColor: '#0d1520', borderWidth: 2 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { color: textColor } } },
        }
    });
})();
<?php endif; ?>

<?php if (count($cumulatief) > 1): ?>
(() => {
    const data = <?= $jsonCumulatief ?>;
    new Chart(document.getElementById('cumulatiefGrafiek'), {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Totaal minuten', data: data.data,
                borderColor: racingRed, backgroundColor: 'rgba(255,0,60,0.1)',
                fill: true, tension: 0.2, pointRadius: 3, pointBackgroundColor: electricBlue,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { ticks: { color: textColor }, grid: { color: gridColor } },
                x: { ticks: { color: textColor }, grid: { color: gridColor } },
            }
        }
    });
})();
<?php endif; ?>
</script>

</body>
</html>