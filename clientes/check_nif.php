<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$nif = trim($_GET['nif'] ?? '');
$excludeId = (int)($_GET['exclude_id'] ?? 0);

if ($nif === '') {
    echo json_encode(['exists' => false, 'cliente' => null]);
    exit;
}

try {
    $db = getDB();
    $sql = "SELECT id, nombre, nif, activo FROM clientes WHERE UPPER(TRIM(nif)) = UPPER(?)";
    if ($excludeId) {
        $sql .= " AND id != ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$nif, $excludeId]);
    } else {
        $stmt = $db->prepare($sql);
        $stmt->execute([$nif]);
    }
    $cliente = $stmt->fetch();

    echo json_encode([
        'exists' => (bool)$cliente,
        'cliente' => $cliente ?: null,
    ]);
} catch (Exception $e) {
    echo json_encode(['exists' => false, 'cliente' => null, 'error' => 'Error al consultar']);
}
