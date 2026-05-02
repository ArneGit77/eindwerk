<?php
/**
 * SportFlow - Statistieken (Power BI dashboard)
 *
 * ➤ Plak je Power BI embed-link in de iframe-src hieronder zodra je
 *   dashboard online staat. Tot zo lang toon je een placeholder.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
vereisLogin();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>SportFlow - Statistieken</title>
    <link rel="stylesheet" href="../CSSfiles/style.css">
    <link rel="stylesheet" href="../CSSfiles/components.css">
</head>
<body>
    <h1>Statistieken</h1>

    <?php require __DIR__ . '/_nav.php'; ?>

    <hr>
    <h2>Power BI Rapportage</h2>

    <!-- ➤ Vervang de src hieronder door je Power BI embed URL -->
    <!--
    <iframe
        title="SportFlow Power BI Dashboard"
        width="100%" height="600"
        src="HIER_KOMT_JE_POWER_BI_EMBED_LINK"
        frameborder="0" allowFullScreen="true">
    </iframe>
    -->

    <p><em>Hier komt het Power BI dashboard. Voeg je embed-link toe in deze pagina (zie commentaar in de code).</em></p>
</body>
</html>
