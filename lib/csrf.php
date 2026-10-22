<?php
/** CSRF token helpers. Call csrf_start() once session is active. */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

/** Call at the top of every POST handler. Dies with 400 on mismatch. */
function csrf_verify(): void
{
    $sent = $_POST['csrf'] ?? '';
    $known = $_SESSION['csrf'] ?? '';
    if ($known === '' || !hash_equals($known, (string)$sent)) {
        http_response_code(400);
        die('Session expired or invalid request. Please go back, refresh the page and try again.');
    }
}
