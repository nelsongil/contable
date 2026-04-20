<?php
/**
 * Sistema de autenticación y control de acceso por roles.
 * Incluido por header.php y directamente por páginas que necesitan
 * verificar el rol ANTES de emitir cualquier HTML.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

// ── Verificar sesión activa ──────────────────────────────────
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

// ── Asegurar que el rol está en sesión ───────────────────────
// Para sesiones antiguas (sin usuario_rol) lo cargamos de BD una sola vez.
if (!isset($_SESSION['usuario_rol'])) {
    try {
        $st = getDB()->prepare("SELECT rol, estado FROM usuarios WHERE id = ?");
        $st->execute([$_SESSION['usuario_id']]);
        $row = $st->fetch();
        if ($row) {
            $_SESSION['usuario_rol'] = $row['rol'];
            if ($row['estado'] !== 'activo') {
                // Usuario desactivado mid-session
                session_destroy();
                header('Location: /login.php?reason=inactivo');
                exit;
            }
        } else {
            // Usuario eliminado de BD
            session_destroy();
            header('Location: /login.php');
            exit;
        }
    } catch (Exception) {
        // Si la BD falla durante migración, asumir admin (usuario único existente)
        $_SESSION['usuario_rol'] = 'admin';
    }
}

// ── Helpers de rol ───────────────────────────────────────────

/**
 * Exige rol admin. Si el usuario es colaborador, redirige a 403.
 * Si no hay sesión, redirige a login.
 * Llamar ANTES de emitir cualquier HTML.
 */
function requireAdmin(): void
{
    if (empty($_SESSION['usuario_id'])) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: /login.php?redirect=' . $redirect);
        exit;
    }
    if (($_SESSION['usuario_rol'] ?? '') !== 'admin') {
        header('Location: /errors/403.php');
        exit;
    }
}

/**
 * Exige cualquier usuario autenticado (admin o colaborador).
 * Si no hay sesión, redirige a login.
 * Para páginas accesibles a todos los roles.
 */
function requireColaborador(): void
{
    if (empty($_SESSION['usuario_id'])) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: /login.php?redirect=' . $redirect);
        exit;
    }
}

/**
 * Devuelve true si el usuario en sesión es admin.
 */
function isAdmin(): bool
{
    return ($_SESSION['usuario_rol'] ?? '') === 'admin';
}

/**
 * Devuelve el id del usuario en sesión (o null si no hay sesión).
 */
function currentUserId(): ?int
{
    return isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;
}
