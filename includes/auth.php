<?php
/**
 * Incluir al principio de includes/header.php
 * Verifica que el usuario esté logado. Si no, redirige al login.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

// Si no hay sesión activa, redirigir
if (empty($_SESSION['usuario_id'])) {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: /login.php?redirect=' . $redirect);
    exit;
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    $reason = $_GET['reason'] ?? 'logout';
    header('Location: /login.php?reason=' . urlencode($reason));
    exit;
}
