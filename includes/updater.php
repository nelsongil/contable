<?php
/**
 * Lógica de detección de actualizaciones desde GitHub
 */

require_once __DIR__ . '/functions.php';

/**
 * Comprueba si hay una versión nueva en GitHub.
 * Se ejecuta máximo una vez cada 24 horas.
 */
function checkForUpdates() {
    // Si ya sabemos que hay una actualización en esta sesión y se comprobó hace poco, no re-comprobar
    if (isset($_SESSION['update_available']) && (time() - ($_SESSION['update_available']['at'] ?? 0)) < 3600) {
        return;
    }

    // Comprobar última vez que se consultó a la API (máximo 1 vez cada 24h)
    $lastCheck = (int)getConfig('last_update_check', 0);
    if (time() - $lastCheck < 86400) {
        return;
    }

    $repo = 'nelsongil/contable';
    $url  = "https://api.github.com/repos/$repo/releases/latest";

    // Preparar cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Libro-Contable-Updater/1.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Importante para seguridad

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Si la API falla, ignoramos silenciosamente
    if ($httpCode !== 200 || !$response) {
        setConfig('last_update_check', time()); // Evitar reintentos inmediatos si falla la red
        return;
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['tag_name'])) {
        return;
    }

    $latestTag = $data['tag_name']; // ej: "v1.3"
    $latestVer = ltrim($latestTag, 'v');
    $currentVer = defined('APP_VERSION') ? APP_VERSION : '1.0';

    if (version_compare($latestVer, $currentVer, '>')) {
        $_SESSION['update_available'] = [
            'version' => $latestTag,
            'url'     => $data['zipball_url'], // ZIP automático de GitHub
            'notes'   => $data['body'],
            'at'      => time()
        ];
    } else {
        unset($_SESSION['update_available']);
    }

    // Actualizar timestamp de última comprobación
    setConfig('last_update_check', time());
}

/**
 * Limpia la sesión si el usuario descarta la notificación
 */
function dismissUpdateNotification() {
    if (isset($_SESSION['update_available'])) {
        $_SESSION['update_dismissed_version'] = $_SESSION['update_available']['version'];
    }
}
