<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Asegurar que solo el admin pueda ver esto
if (empty($_SESSION['usuario_id'])) {
    die("No autorizado");
}

$url = 'https://api.github.com/repos/nelsongil/contable/releases/latest';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERAGENT      => 'contable-app/1.2',
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => ['Accept: application/vnd.github.v3+json'],
]);
$body     = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

echo "<h2>GitHub API Debug</h2>";
echo "<pre style='background:#f4f4f4; pading:15px; border:1px solid #ccc;'>";
echo "HTTP Code: $httpCode\n";
echo "cURL error: $error\n";
echo "----------------------------------------\n";
echo "Respuesta Completa (o primeros 2000 chars):\n";
echo "----------------------------------------\n";
echo htmlspecialchars(substr($body, 0, 2000));
echo "\n----------------------------------------\n";

if ($httpCode === 200) {
    echo "\nIntento de decodificación JSON:\n";
    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "ERROR JSON: " . json_last_error_msg() . "\n";
        echo "Posición del error: " . json_last_error() . "\n";
    } else {
        echo "JSON Válido. Tag detectado: " . ($data['tag_name'] ?? 'N/A') . "\n";
    }
}
echo "</pre>";
?>
