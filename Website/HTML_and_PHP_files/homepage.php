<?php
/**
 * SportFlow - Homepage (na inloggen)
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
vereisLogin();
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
    <h1>SportFlow App</h1>

    <?php require __DIR__ . '/_nav.php'; ?>

    <hr>
    <h2>Welkom bij SportFlow</h2>
    <p>
        SportFlow is een webapplicatie die speciaal is ontworpen om jou te helpen bij het 
        bijhouden van je sportprestaties. Je kan je trainingen plannen, opslaan en analyseren.
        Je hebt ook een mooi overzicht van je vooruitgang. Zo blijf je gemotiveerd om je sport 
        doelen te bereiken!
    </p>
</body>
</html>
