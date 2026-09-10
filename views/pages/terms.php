<?php require_once __DIR__.'/../../core/App.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Termos de Serviço — <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= APP_URL ?>/public/css/app.css">
</head>
<body style="padding:40px 20px;max-width:800px;margin:0 auto">
<h1 style="font-size:24px;font-weight:700;margin-bottom:8px">Termos de Serviço</h1>
<p style="color:var(--txt2);margin-bottom:32px">Última atualização: <?= date('d/m/Y') ?></p>

<h2 style="font-size:16px;font-weight:600;margin:24px 0 8px">1. Aceitação dos Termos</h2>
<p style="color:var(--txt2);line-height:1.8">Ao usar este sistema, você concorda com estes Termos de Serviço. O uso continuado do serviço após alterações constitui aceitação dos novos termos.</p>

<h2 style="font-size:16px;font-weight:600;margin:24px 0 8px">2. Uso do Serviço</h2>
<p style="color:var(--txt2);line-height:1.8">Este sistema é destinado exclusivamente a gestores de tráfego pago para geração de relatórios de performance de campanhas publicitárias. É proibido usar o sistema para fins ilegais ou não autorizados.</p>

<h2 style="font-size:16px;font-weight:600;margin:24px 0 8px">3. Conta e Segurança</h2>
<p style="color:var(--txt2);line-height:1.8">Você é responsável por manter a confidencialidade da sua senha e por todas as atividades realizadas com sua conta.</p>

<h2 style="font-size:16px;font-weight:600;margin:24px 0 8px">4. Dados de Terceiros</h2>
<p style="color:var(--txt2);line-height:1.8">Ao conectar contas de anúncios de clientes, você declara ter autorização desses clientes para acessar e processar os dados das campanhas.</p>

<h2 style="font-size:16px;font-weight:600;margin:24px 0 8px">5. Limitação de Responsabilidade</h2>
<p style="color:var(--txt2);line-height:1.8">O sistema é fornecido "como está". Não nos responsabilizamos por decisões tomadas com base nos relatórios gerados.</p>

<div style="margin-top:40px;padding-top:20px;border-top:1px solid var(--border)">
<a href="<?= APP_URL ?>" style="color:var(--accent)">← Voltar ao início</a>
</div>
</body></html>
