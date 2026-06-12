<?php
/**
 * Script de test para depurar la recuperación de contraseña
 * Solo accesible para admin
 */
session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$db = getDB();
echo "<h2>🔍 Test Recuperación de Contraseña</h2>";

// 1. Verificar tabla
echo "<h3>1. Tabla password_reset_tokens</h3>";
try {
    $st = $db->query("SHOW TABLES LIKE 'password_reset_tokens'");
    if ($st->fetch()) {
        echo "✅ La tabla existe<br>";

        // Ver estructura
        $st = $db->query("DESCRIBE password_reset_tokens");
        echo "<pre>";
        print_r($st->fetchAll());
        echo "</pre>";
    } else {
        echo "❌ La tabla NO existe<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// 2. Verificar tokens existentes
echo "<h3>2. Tokens en la tabla</h3>";
$st = $db->query("SELECT * FROM password_reset_tokens ORDER BY creado_en DESC LIMIT 5");
$tokens = $st->fetchAll();
if ($tokens) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Usuario ID</th><th>Token (hash)</th><th>Expira</th><th>Usado</th><th>Creado</th></tr>";
    foreach ($tokens as $t) {
        echo "<tr>";
        echo "<td>{$t['id']}</td>";
        echo "<td>{$t['usuario_id']}</td>";
        echo "<td>" . substr($t['token'], 0, 30) . "...</td>";
        echo "<td>{$t['expira_en']}</td>";
        echo "<td>{$t['usado']}</td>";
        echo "<td>{$t['creado_en']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No hay tokens registrados<br>";
}

// 3. Test de password_verify
echo "<h3>3. Test password_verify</h3>";
$testCodigo = "123456";
$testHash = password_hash($testCodigo, PASSWORD_DEFAULT);
echo "Código original: <strong>$testCodigo</strong><br>";
echo "Hash generado: <strong>" . substr($testHash, 0, 40) . "...</strong><br>";
echo "password_verify('123456', hash): " . (password_verify($testCodigo, $testHash) ? '✅ TRUE' : '❌ FALSE') . "<br>";

// 4. Verificar usuario actual
echo "<h3>4. Usuario en sesión</h3>";
echo "Usuario ID: " . ($_SESSION['usuario_id'] ?? 'N/A') . "<br>";
echo "Email: " . ($_SESSION['usuario_email'] ?? 'N/A') . "<br>";

// 5. Simular flujo completo
echo "<h3>5. Simular generación de código</h3>";
$usuarioId = $_SESSION['usuario_id'];
$codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expira = date('Y-m-d H:i:s', strtotime('+10 minutes'));
$tokenHash = password_hash($codigo, PASSWORD_DEFAULT);

echo "Código generado: <strong>$codigo</strong><br>";
echo "Hash: " . substr($tokenHash, 0, 40) . "...<br>";

// Insertar token de test
$db->prepare("INSERT INTO password_reset_tokens (usuario_id, token, expira_en) VALUES (?, ?, ?)")
  ->execute([$usuarioId, $tokenHash, $expira]);
echo "✅ Token insertado en BD<br>";

// Verificar que se puede validar
$st = $db->prepare(
    "SELECT id, token FROM password_reset_tokens
     WHERE usuario_id = ? AND usado = 0 AND expira_en > NOW()
     ORDER BY creado_en DESC LIMIT 1"
);
$st->execute([$usuarioId]);
$tokenRow = $st->fetch();

if ($tokenRow) {
    echo "Token recuperado: " . substr($tokenRow['token'], 0, 40) . "...<br>";
    echo "password_verify('$codigo', token): " . (password_verify($codigo, $tokenRow['token']) ? '✅ TRUE' : '❌ FALSE') . "<br>";
} else {
    echo "❌ No se encontró el token<br>";
}

echo "<hr>";
echo "<a href='/recuperar.php'>Ir a recuperar contraseña</a> | ";
echo "<a href='/ajustes/usuarios.php'>Volver a usuarios</a>";
