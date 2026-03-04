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
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => 'Libro-Contable/' . (defined('APP_VERSION') ? APP_VERSION : '1.0'),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    // Guardar timestamp de última comprobación siempre (para no saturar si hay errores)
    setConfig('last_update_check', time());

    if ($curlErr) {
        $_SESSION['update_error'] = "Error de conexión cURL: $curlErr";
        return;
    }

    if ($httpCode !== 200) {
        $_SESSION['update_error'] = "GitHub API devolvió HTTP $httpCode" . ($httpCode === 403 ? " (Posible límite de tasa o repo privado)" : "");
        return;
    }

    $body = trim($response);
    
    // Limpieza de posibles caracteres nulos (Hotfix por archivos guardados en UTF-16)
    $body = str_replace("\0", '', $body);
    $body = preg_replace('/\x00/', '', $body);
    
    // Si detectamos UTF-16 (común tras ediciones erróneas), intentamos convertir a UTF-8
    if (function_exists('mb_detect_encoding')) {
        $enc = mb_detect_encoding($body, ['UTF-8', 'UTF-16', 'ISO-8859-1'], true);
        if ($enc === 'UTF-16') {
            $body = mb_convert_encoding($body, 'UTF-8', 'UTF-16');
        }
    }

    if (empty($body)) {
        $_SESSION['update_error'] = "Respuesta vacía de GitHub";
        return;
    }

    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $_SESSION['update_error'] = "Respuesta inválida de GitHub: " . json_last_error_msg() . " — Snippet: " . substr($body, 0, 50);
        return;
    }

    if (!isset($data['tag_name'])) {
        return;
    }

    $latestTag = $data['tag_name']; // ej: "v1.3"
    $latestVer = ltrim($latestTag, 'v');
    $currentVer = defined('APP_VERSION') ? APP_VERSION : '1.0';

    if (version_compare($latestVer, $currentVer, '>')) {
        // Sanitizar notas de versión (pueden venir con \u0000 si se guardó en UTF-16)
        $notes = $data['body'] ?? '';
        $notes = str_replace("\0", '', $notes);
        // Eliminar caracteres de control no deseados
        $notes = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $notes);

        $_SESSION['update_available'] = [
            'version' => $latestTag,
            'url'     => $data['zipball_url'],
            'notes'   => $notes,
            'at'      => time()
        ];
        unset($_SESSION['update_error']);
    } else {
        unset($_SESSION['update_available']);
        unset($_SESSION['update_error']);
    }
}

/**
 * Limpia la sesión si el usuario descarta la notificación
 */
function dismissUpdateNotification() {
    if (isset($_SESSION['update_available'])) {
        $_SESSION['update_dismissed_version'] = $_SESSION['update_available']['version'];
    }
}
