<?php
/**
 * SportFlow - Authenticatie & CSRF-bescherming
 *
 * Roep dit bestand bovenaan elke beschermde pagina.
 * Bevat:
 *   - vereisLogin()      : redirect naar index.php als niet ingelogd
 *   - csrf_token()       : geeft (en maakt indien nodig) een CSRF-token
 *   - csrf_check()       : controleert ingestuurde token, killt request als fout
 *   - alert($msg, $type) : toont een nette alert-box (type: 'error' of 'success')
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ────────────────────────────────────────────────────────────
// LOGIN
// ────────────────────────────────────────────────────────────
function vereisLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../index.php");
        exit();
    }
}

// ────────────────────────────────────────────────────────────
// CSRF (Cross-Site Request Forgery) bescherming
// ────────────────────────────────────────────────────────────
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check() {
    $ingestuurd = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $ingestuurd)) {
        http_response_code(403);
        die("CSRF-fout: het formulier is verlopen of ongeldig. Ga terug en probeer opnieuw.");
    }
}

// ────────────────────────────────────────────────────────────
// Nette alert-box
// ────────────────────────────────────────────────────────────
function alert($bericht, $type = 'error') {
    if ($bericht === '') return '';
    $klasse = $type === 'success' ? 'alert-success' : 'alert-error';
    return "<div class=\"alert {$klasse}\">" . htmlspecialchars($bericht) . "</div>";
}
