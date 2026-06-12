<?php
/**
 * Verificar estructura de password_reset_tokens
 */
session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$db = getDB();

// Ver estructura de la tabla
echo "<h2>Estructura de password_reset_tokens</h2>";
$st = $db->query("DESCRIBE password_reset_tokens");
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
foreach ($st->fetchAll() as $row) {
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "<td>{$row['Default']}</td>";
    echo "</tr>";
}
echo "</table>";

// Ver tokens recientes
echo "<h2>Tokens recientes</h2>";
$st = $db->query("SELECT id, usuario_id, LENGTH(token) as token_len, token, expira_en, usado, creado_en FROM password_reset_tokens ORDER BY creado_en DESC LIMIT 10");
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Usuario</th><th>Token Len</th><th>Token (primeros 70 chars)</th><th>Expira</th><th>Usado</th><th>Creado</th></tr>";
foreach ($st->fetchAll() as $row) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['usuario_id']}</td>";
    echo "<td>{$row['token_len']}</td>";
    echo "<td>" . htmlspecialchars(substr($row['token'], 0, 70)) . "...</td>";
    echo "<td>{$row['expira_en']}</td>";
    echo "<td>{$row['usado']}</td>";
    echo "<td>{$row['creado_en']}</td>";
    echo "</tr>";
}
echo "</table>";

// Test de password_verify
echo "<h2>Test password_verify</h2>";
$testCodigo = "050904";
$testHash = password_hash($testCodigo, PASSWORD_DEFAULT);
echo "Código test: <strong>$testCodigo</strong><br>";
echo "Hash generado: <strong>" . htmlspecialchars($testHash) . "</strong> (long: " . strlen($testHash) . ")<br>";
echo "password_verify con nuevo hash: " . (password_verify($testCodigo, $testHash) ? '✅ TRUE' : '❌ FALSE') . "<br>";

// Verificar si el último token funciona
$st = $db->query("SELECT token FROM password_reset_tokens ORDER BY creado_en DESC LIMIT 1");
$ultimoToken = $st->fetchColumn();
if ($ultimoToken) {
    echo "<br>Último token en BD (long: " . strlen($ultimoToken) . "):<br>";
    echo "<code>" . htmlspecialchars($ultimoToken) . "</code><br>";
    echo "password_verify con token BD: " . (password_verify($testCodigo, $ultimoToken) ? '✅ TRUE' : '❌ FALSE') . "<br>";
}

echo "<hr><a href='/recuperar.php'>Ir a recuperar</a> | <a href='/'>Volver</a>";
