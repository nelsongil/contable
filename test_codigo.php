<?php
/**
 * Test del sistema de recuperación - Generar y verificar código
 * Solo accesible para admin
 */
session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$db = getDB();
$mensaje = '';
$debugInfo = [];

// Generar código de test
if (isset($_GET['generar'])) {
    $usuarioId = $_SESSION['usuario_id'];
    $email = $_SESSION['usuario_email'];

    // Generar código
    $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expira = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $tokenHash = password_hash($codigo, PASSWORD_DEFAULT);

    // Invalidar anteriores
    $db->prepare("UPDATE password_reset_tokens SET usado = 1 WHERE usuario_id = ?")->execute([$usuarioId]);

    // Guardar nuevo
    $db->prepare("INSERT INTO password_reset_tokens (usuario_id, token, expira_en) VALUES (?, ?, ?)")
       ->execute([$usuarioId, $tokenHash, $expira]);

    $mensaje = "✅ Código generado: <strong style='font-size:1.5em; letter-spacing:5px'>$codigo</strong>";
    $debugInfo = [
        'Usuario ID' => $usuarioId,
        'Email' => $email,
        'Código' => $codigo,
        'Hash' => substr($tokenHash, 0, 60) . '...',
        'Expira' => $expira,
        'Verificar con password_verify' => password_verify($codigo, $tokenHash) ? '✅ TRUE' : '❌ FALSE'
    ];
}

// Verificar código
$codigoTest = '';
$verificarResult = '';
if (isset($_POST['verificar'])) {
    $codigoArr = $_POST['codigo'] ?? [];
    $codigoUsuario = '';
    foreach ($codigoArr as $valor) {
        if (is_string($valor) && ctype_digit($valor)) {
            $codigoUsuario .= $valor;
        }
    }
    $codigoUsuario = substr($codigoUsuario, 0, 6);

    $codigoTest = $codigoUsuario;

    // Buscar último token
    $st = $db->prepare(
        "SELECT id, token FROM password_reset_tokens
         WHERE usuario_id = ? AND usado = 0 AND expira_en > NOW()
         ORDER BY creado_en DESC LIMIT 1"
    );
    $st->execute([$_SESSION['usuario_id']]);
    $tokenRow = $st->fetch();

    if (!$tokenRow) {
        $verificarResult = '❌ No hay tokens válidos';
    } else {
        $result = password_verify($codigoUsuario, $tokenRow['token']);
        $verificarResult = $result ? '✅ TRUE - Código válido' : '❌ FALSE - Código inválido';

        if ($result) {
            $db->prepare("UPDATE password_reset_tokens SET usado = 1 WHERE id = ?")->execute([$tokenRow['id']]);
        }
    }

    $debugInfo['Código introducido'] = $codigoUsuario;
    $debugInfo['Resultado'] = $verificarResult;
}

$pageTitle = 'Test Código Recuperación';
require_once __DIR__ . '/includes/header.php';
?>

<style>
.test-card {
    background: #fff;
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.codigo-display {
    font-size: 2.5rem;
    font-weight: 700;
    letter-spacing: 10px;
    text-align: center;
    padding: 1.5rem;
    background: #f8f9fa;
    border: 2px dashed #C9A84C;
    border-radius: 12px;
    margin: 1rem 0;
}
.debug-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}
.debug-table td {
    padding: 0.5rem;
    border-bottom: 1px solid #e5e7eb;
}
.debug-table td:first-child {
    font-weight: 600;
    color: #6b7280;
    width: 200px;
}
.codigo-inputs {
    display: flex;
    gap: 8px;
    justify-content: center;
    margin: 1.5rem 0;
}
.codigo-input {
    width: 60px;
    height: 64px;
    text-align: center;
    font-size: 32px;
    font-weight: 700;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 0;
}
.codigo-input:focus {
    border-color: #C9A84C;
    box-shadow: 0 0 0 4px rgba(201,168,76,.15);
}
</style>

<div class="container mt-4">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <h2 class="mb-4">🔍 Test Sistema de Recuperación</h2>

      <?php if ($mensaje): ?>
      <div class="test-card" style="background: #ecfdf5; border: 1px solid #10b981;">
        <?= $mensaje ?>
      </div>
      <?php endif; ?>

      <!-- Generar código -->
      <div class="test-card">
        <h4>1️⃣ Generar Código</h4>
        <p>Genera un nuevo código de recuperación para tu usuario actual:</p>
        <a href="?generar=1" class="btn btn-primary">
          🎲 Generar Código de Test
        </a>
      </div>

      <!-- Debug info -->
      <?php if ($debugInfo): ?>
      <div class="test-card">
        <h4>📊 Información de Debug</h4>
        <table class="debug-table">
          <?php foreach ($debugInfo as $key => $valor): ?>
          <tr>
            <td><?= e($key) ?></td>
            <td><?= $valor ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <?php endif; ?>

      <!-- Formulario de test -->
      <div class="test-card">
        <h4>2️⃣ Probar Verificación</h4>
        <p>Introduce el código generado para verificar que funciona:</p>

        <form method="post">
          <div class="codigo-inputs">
            <?php for ($i = 0; $i < 6; $i++): ?>
            <input type="text" class="codigo-input" name="codigo[]" maxlength="1"
                   pattern="[0-9]" inputmode="numeric"
                   id="test-codigo-<?= $i ?>" required
                   <?= $codigoTest ? 'value="' . ($codigoTest[$i] ?? '') . '"' : '' ?>>
            <?php endfor; ?>
          </div>

          <button type="submit" name="verificar" class="btn btn-success">
            ✅ Verificar Código
          </button>
        </form>

        <?php if ($verificarResult): ?>
        <div class="mt-3" style="font-size: 1.2rem; font-weight: 600;">
          Resultado: <?= $verificarResult ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Enlaces -->
      <div class="text-center mt-4">
        <a href="/recuperar.php" class="btn btn-outline-primary me-2">
          🔄 Ir a Recuperar Contraseña (Producción)
        </a>
        <a href="/ajustes/usuarios.php" class="btn btn-outline-secondary">
          ← Volver
        </a>
      </div>
    </div>
  </div>
</div>

<script>
// Auto-focus y avance automático
const inputs = document.querySelectorAll('.codigo-input');
inputs.forEach((input, index) => {
  input.addEventListener('input', (e) => {
    const value = e.target.value.replace(/[^0-9]/g, '');
    e.target.value = value;
    if (value && index < 5) inputs[index + 1].focus();
  });

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Backspace' && !e.target.value && index > 0) {
      inputs[index - 1].focus();
    }
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
