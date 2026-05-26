<?php

// FIXED redirect() — adds session_write_close() before exit

function redirect(string $path): never
{
    // Ensure session data is written before redirecting
    // Prevents flash messages from being lost on same-page redirects
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    // $path should start with / (relative to APP_URL) or be a full URL
    if (str_starts_with($path, 'http')) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . APP_URL . $path);
    }
    exit;
}


// FIXED flash() — ensures session is active and writes immediately

function flash(string $key, string $msg = ''): string
{
    if ($msg !== '') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash'][$key] = $msg;
        // Force immediate write so the message survives redirects
        session_write_close();
        return '';
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $val = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $val;
}
