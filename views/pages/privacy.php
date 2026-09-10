<?php require_once __DIR__.'/../../core/App.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Política de Privacidade — <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= APP_URL ?>/public/css/app.css">
</head>
<body style="padding:40px 20px;max-width:800px;margin:0 auto">
<h1 style="font-size:24px;font-weight:700;margin-bottom:8px">Política de Privacidade</h1>
<p style="color:var(--txt2);margin-bottom:32px">Última atualização: <?= date('d/m/Y') ?></p>

<h2 style="font-size:16px;font-weight:600;margin:24px 0 8px">1. Coleta de Dados</h2>
<p style="color:var(--txt2);line-height:1.8">Este aplicativo coleta dados de contas de anúncios do Meta Ads e Google Ads com a finalidade exclusiva de gerar relatórios de performance para gestores de tráfego pago. Os dados coletados incluem métricas de campanhas como impressões, cliques, investimento e conversões.</p>

<h2 style="font-size:16px;font-weight:600;margin:24px 0 8px">2. Uso dos Dados</h2>
<p style="color:var(--txt2);line-height:1.8">Os dados são utilizados exclusivamente para geração de relatórios internos ao usuário autenticado. Não compartilhamos, vendemos ou transferimos dados a terceiros.</p>

<h2 style="font-size:16px;font-weight:600;margin:24px 0 8px">3. Armazenamento</h2>
<p style="color:var(--txt2);line-height:1.8">Os tokens de acesso OAuth são armazenados de forma segura em banco de dados criptografado. Você pode revogar o acesso a qualquer momento desconectando a conta dentro do sistema ou revogando o acesso no Gerenciador de Negócios do Facebook.</p>

<h2 style="font-size:16px;font-weight:600;margin:24px 0 8px">4. Permissões do Facebook</h2>
<p style="color:var(--txt2);line-height:1.8">Solicitamos as permissões <code>ads_read</code>, <code>ads_management</code> e <code>business_management</code> para acessar métricas de campanhas. Essas permissões são usadas apenas para leitura e geração de relatórios.</p>

<h2 style="font-size:16px;font-weight:600;margin:24px 0 8px">5. Exclusão de Dados</h2>
<p style="color:var(--txt2);line-height:1.8">Você pode solicitar a exclusão de todos os seus dados a qualquer momento através do e-mail de contato ou acessando <?= APP_URL ?>/data-deletion.</p>

<h2 style="font-size:16px;font-weight:600;margin:24px 0 8px">6. Contato</h2>
<p style="color:var(--txt2);line-height:1.8">Para dúvidas sobre privacidade, entre em contato através do sistema.</p>

<div style="margin-top:40px;padding-top:20px;border-top:1px solid var(--border)">
<a href="<?= APP_URL ?>" style="color:var(--accent)">← Voltar ao início</a>
</div>
</body></html>
