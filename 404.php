<?php
http_response_code(404);
require_once __DIR__ . '/config/database.php';

$empresa  = defined('EMPRESA_SOCIEDAD') ? EMPRESA_SOCIEDAD : 'Libro Contable';
$back     = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
$uri      = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Página no encontrada — <?= htmlspecialchars($empresa) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --verde:   #1A2E2A;
  --verde-m: #2D5245;
  --verde-a: #3E7B64;
  --gold:    #C9A84C;
  --bg:      #F4F7F5;
}

body {
  font-family: 'Inter', sans-serif;
  background: var(--bg);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  color: #1a1a1a;
}

.card-404 {
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 8px 40px rgba(0,0,0,.1);
  padding: 3rem 3.5rem;
  text-align: center;
  max-width: 480px;
  width: 100%;
  animation: fadeUp .4s ease both;
}

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(24px); }
  to   { opacity: 1; transform: translateY(0); }
}

.icon-wrap {
  width: 88px; height: 88px;
  background: #f0f7f4;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1.75rem;
  border: 3px solid #d1e7dd;
}

.icon-wrap i {
  font-size: 2.4rem;
  color: var(--verde-a);
}

.code {
  font-size: 5rem;
  font-weight: 800;
  color: var(--verde);
  line-height: 1;
  letter-spacing: -.03em;
}

.code span {
  color: var(--gold);
}

.title {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--verde);
  margin: .6rem 0 .5rem;
}

.desc {
  font-size: .88rem;
  color: #6b7280;
  line-height: 1.6;
  margin-bottom: 2rem;
}

.uri-tag {
  display: inline-block;
  background: #f3f4f6;
  color: #374151;
  font-size: .75rem;
  font-family: monospace;
  padding: .25rem .7rem;
  border-radius: 6px;
  margin-bottom: 2rem;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.actions {
  display: flex;
  flex-direction: column;
  gap: .75rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: .5rem;
  padding: .75rem 1.5rem;
  border-radius: 10px;
  font-family: 'Inter', sans-serif;
  font-size: .9rem;
  font-weight: 600;
  text-decoration: none;
  border: none;
  cursor: pointer;
  transition: background .15s, transform .1s;
}
.btn:active { transform: scale(.98); }

.btn-primary {
  background: var(--verde-a);
  color: #fff;
}
.btn-primary:hover { background: var(--verde-m); color: #fff; }

.btn-secondary {
  background: #f3f4f6;
  color: #374151;
}
.btn-secondary:hover { background: #e5e7eb; color: #111; }

.brand {
  margin-top: 2.5rem;
  font-size: .75rem;
  color: #9ca3af;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .4rem;
}

.brand-dot {
  width: 18px; height: 18px;
  background: var(--verde);
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.brand-dot i { color: var(--gold); font-size: .6rem; }

@media (max-width: 480px) {
  .card-404 { padding: 2rem 1.5rem; }
  .code { font-size: 4rem; }
}
</style>
</head>
<body>

<div class="card-404">
  <div class="icon-wrap">
    <i class="bi bi-file-earmark-x"></i>
  </div>

  <div class="code">4<span>0</span>4</div>
  <div class="title">Página no encontrada</div>
  <p class="desc">
    La dirección que buscas no existe o ha sido movida.<br>
    Comprueba que la URL es correcta.
  </p>

  <?php if ($uri && $uri !== '/'): ?>
  <div class="uri-tag"><?= $uri ?></div>
  <?php endif; ?>

  <div class="actions">
    <a href="/" class="btn btn-primary">
      <i class="bi bi-speedometer2"></i>
      Ir al Dashboard
    </a>
    <?php if ($back): ?>
    <a href="<?= htmlspecialchars($back) ?>" class="btn btn-secondary">
      <i class="bi bi-arrow-left"></i>
      Volver atrás
    </a>
    <?php endif; ?>
  </div>

  <div class="brand">
    <span class="brand-dot"><i class="bi bi-journal-text"></i></span>
    <?= htmlspecialchars($empresa) ?>
  </div>
</div>

</body>
</html>
