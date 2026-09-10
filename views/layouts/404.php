<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>404 — Página não encontrada</title>
<link rel="stylesheet" href="<?= defined('APP_URL') ? APP_URL : '' ?>/public/css/app.css">
</head><body>
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:16px;text-align:center;padding:20px">
  <span class="material-icons-outlined" style="font-size:64px;color:var(--txt3)">search_off</span>
  <h1 style="font-size:40px;font-weight:700;color:var(--txt)">404</h1>
  <p style="font-size:16px;color:var(--txt2)">Página não encontrada</p>
  <a href="<?= defined('APP_URL') ? APP_URL : '' ?>/dashboard" class="btn btn-primary">← Voltar ao Dashboard</a>
</div></body></html>
