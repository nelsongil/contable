<?php
http_response_code(403);
if (session_status() === PHP_SESSION_NONE) session_start();

// Determinar destino del botón "Volver"
$back = ($_SESSION['usuario_rol'] ?? 'colaborador') === 'admin' ? '/' : '/facturas/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Acceso denegado</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; }
body {
  font-family: 'Inter', sans-serif;
  background: #0f1e1b;
  min-height: 100vh;
  display: flex; align-items: center; justify-content: center;
  padding: 1.5rem;
}
.box {
  background: #fff; border-radius: 16px;
  padding: 3rem 2.5rem; text-align: center;
  max-width: 440px; width: 100%;
  box-shadow: 0 20px 60px rgba(0,0,0,.35);
}
.code { font-size: 4rem; font-weight: 700; color: #1A2E2A; line-height: 1; margin-bottom: .5rem; }
h2 { color: #1a1a1a; margin: .5rem 0 .75rem; }
p { color: #6b7280; font-size: .9rem; line-height: 1.6; margin-bottom: 1.5rem; }
.btn {
  display: inline-block; background: #C9A84C; color: #1A2E2A;
  padding: .75rem 2rem; border-radius: 8px; text-decoration: none;
  font-weight: 700; font-size: .95rem;
  transition: background .15s;
}
.btn:hover { background: #b8923e; }
</style>
</head>
<body>
<div class="box">
  <div class="code">403</div>
  <h2>Acceso denegado</h2>
  <p>No tienes permiso para ver esta sección.<br>
     Contacta con el administrador si crees que es un error.</p>
  <a href="<?= htmlspecialchars($back) ?>" class="btn">← Volver</a>
</div>
</body>
</html>
