<?php
/**
 * SportFlow - Login & Registratie pagina
 *
 * Verwerkt:
 *   - Login (POST met 'login' knop)
 *   - Registratie (POST met 'register' knop)
 *
 * Wachtwoorden worden ALTIJD gehasht met password_hash() (bcrypt).
 * Beveiligd met CSRF-tokens en prepared statements.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Al ingelogd? → meteen door naar homepage
if (isset($_SESSION['user_id'])) {
    header("Location: HTML_and_PHP_files/homepage.php");
    exit();
}

$foutmelding   = '';
$succesmelding = '';

// ════════════════════════════════════════════════
// LOGIN
// ════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    csrf_check();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $foutmelding = "Vul gebruikersnaam én wachtwoord in.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Wachtwoord klopt → sessie aanmaken
            session_regenerate_id(true);   // beveiliging tegen session fixation
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: HTML_and_PHP_files/homepage.php");
            exit();
        } else {
            $foutmelding = "Verkeerde gebruikersnaam of wachtwoord.";
        }
    }
}

// ════════════════════════════════════════════════
// REGISTRATIE
// ════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    csrf_check();

    $new_username     = trim($_POST['new_username'] ?? '');
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_username === '' || $new_password === '') {
        $foutmelding = "Vul alle velden in.";
    } elseif ($new_password !== $confirm_password) {
        $foutmelding = "De wachtwoorden komen niet overeen.";
    } elseif (strlen($new_password) < 6) {
        $foutmelding = "Het wachtwoord moet minstens 6 tekens hebben.";
    } elseif (strlen($new_username) > 50) {
        $foutmelding = "Gebruikersnaam mag maximaal 50 tekens zijn.";
    } else {
        // Bestaat de gebruikersnaam al?
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$new_username]);

        if ($stmt->fetch()) {
            $foutmelding = "Die gebruikersnaam bestaat al, kies een andere.";
        } else {
            // Wachtwoord HASHEN met password_hash() — gebruikt bcrypt onder de motorkap
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->execute([$new_username, $hash]);
            $succesmelding = "Account aangemaakt. Je kan nu inloggen.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>SportFlow - Login</title>
    <link rel="stylesheet" href="CSSfiles/style.css">
    <link rel="stylesheet" href="CSSfiles/components.css">
</head>
<body>
    <h1>Welkom bij SportFlow</h1>
    <p>Log in of maak een account aan om je trainingen te beheren.</p>

    <?= alert($foutmelding, 'error') ?>
    <?= alert($succesmelding, 'success') ?>

    <hr>

    <h2>Inloggen</h2>
    <!-- ➤ Login formulier -->
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <label>Gebruikersnaam:</label>
        <input type="text" name="username" required>

        <label>Wachtwoord:</label>
        <input type="password" name="password" required>

        <button type="submit" name="login">Inloggen</button>
    </form>

    <hr>

    <h2>Nieuw account aanmaken</h2>
    <!-- ➤ Registratie formulier -->
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <label>Kies een gebruikersnaam:</label>
        <input type="text" name="new_username" maxlength="50" required>

        <label>Kies een wachtwoord (min. 6 tekens):</label>
        <input type="password" name="new_password" minlength="6" required>

        <label>Bevestig wachtwoord:</label>
        <input type="password" name="confirm_password" minlength="6" required>

        <button type="submit" name="register">Account aanmaken</button>
    </form>
</body>
</html>
