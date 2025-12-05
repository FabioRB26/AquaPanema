<?php
// peixe_historico.php — Detalhe da espécie quando o usuário veio do histórico
require_once __DIR__ . '/index.php';

$pdo      = pdo();
$isLogged = is_logged_in();
$csrf     = csrf_token();

if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$id   = isset($_GET['id'])   ? (int)$_GET['id']   : 0;
$back = isset($_GET['back']) ? (string)$_GET['back'] : '';

if ($id <= 0) { http_response_code(400); echo 'ID inválido.'; exit; }

/** Sanitiza a URL de retorno: precisa começar com "historico.php" (relativa) */
$backUrl = 'historico.php';
if ($back !== '') {
  $decoded = rawurldecode($back);
  // só aceita caminho relativo para historico.php
  if (preg_match('#^historico\.php(\?.*)?$#', $decoded)) {
    $backUrl = $decoded;
  }
}

// Tenta buscar com colunas completas (nome_cientifico, imagem_url)
try {
  $sql = "SELECT id, nome_comum, nome_cientifico,
                 reino, filo, classe, ordem, familia, genero, especie,
                 informacoes, imagem_url, imagem
          FROM peixes WHERE id = :id LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([':id'=>$id]);
  $px = $st->fetch();
} catch (Throwable $e) {
  $px = null;
}

if (!$px) {
  // fallback mais simples
  $st = $pdo->prepare("SELECT id, nome_comum, reino, filo, classe, ordem, familia, genero, especie, informacoes
                       FROM peixes WHERE id = :id LIMIT 1");
  $st->execute([':id'=>$id]);
  $px = $st->fetch();
  if (!$px) { http_response_code(404); echo 'Peixe não encontrado.'; exit; }
}

$sci = trim((string)($px['nome_cientifico'] ?? ''));
if ($sci === '') {
  $gn = trim((string)($px['genero']  ?? ''));
  $sp = trim((string)($px['especie'] ?? ''));
  $sci = trim($gn.' '.$sp);
}
$com = trim((string)($px['nome_comum'] ?? ''));

$img = null;
if (!empty($px['imagem_url'] ?? null)) $img = $px['imagem_url'];
elseif (!empty($px['imagem'] ?? null))  $img = $px['imagem'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($sci !== '' ? $sci : 'Peixe'); ?> — Aquapanema</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
  :root{--brand-900:#062a33;--brand-700:#0d5868;--brand-500:#14b8a6;--brand-300:#99f6e4;--accent:#ffd166;--accent-2:#ff7e6b;--bg:#e9fbff;--text:#0e1b24;--muted:#5f7582;--radius:20px}
  *{box-sizing:border-box}
  html,body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;background:var(--bg);color:var(--text)}
  .top-bar{height:10vh;min-height:64px;background:linear-gradient(90deg,var(--brand-900),var(--brand-700));color:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 24px;box-shadow:0 6px 16px rgba(0,0,0,.18)}
  .brand{display:flex;align-items:center;gap:12px}
  .brand img{height:44px;width:44px;border-radius:50%;border:2px solid #fff;object-fit:cover}
  .brand h1{margin:0;font-size:1.25rem;color:var(--accent)}
  nav.actions{display:flex;align-items:center;gap:14px}
  nav.actions a, nav.actions button{color:#fff;text-decoration:none;font-weight:700;display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:12px}
  nav.actions a:hover, nav.actions button:hover{background:rgba(255,255,255,.15)}
  .logout-form{display:inline;margin:0}
  .logout-form button{background:none;border:0;cursor:pointer;font:inherit}

  .wrap{max-width:1100px;margin:28px auto;padding:0 16px}
  .card{background:rgba(255,255,255,.94);border:1px solid #e6f3f5;border-radius:var(--radius);box-shadow:0 16px 48px rgba(6,42,51,.16);overflow:hidden;margin-bottom:20px}
  .card-header{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;background:linear-gradient(180deg,#ffffff,#f5fdff);border-bottom:1px solid #e9f4f7}
  .card-header h2{margin:0;font-size:1.2rem;color:var(--brand-700)}
  .card-body{padding:18px 22px}

  .fish-head{display:grid;grid-template-columns: 2fr 1fr; gap:18px; align-items:start}
  .fish-title{margin:0 0 4px; font-size:1.3rem; color:var(--brand-700)}
  .fish-sub {margin:0 0 12px; color:var(--muted)}
  .figure{justify-self:end}
  .figure img{max-width:100%; height:auto; border-radius:16px; border:1px solid #e6f3f5; box-shadow:0 8px 20px rgba(0,0,0,.12)}

  .grid{display:grid;grid-template-columns:repeat(2,minmax(240px,1fr));gap:16px 22px; margin-top:14px}
  .field{display:flex;flex-direction:column;gap:6px}
  .label{font-size:.85rem;color:var(--muted);font-weight:600}
  .value{font-size:1.02rem;color:var(--text);background:#f8fbfc;border:1.5px solid #d7e3ea;padding:12px 14px;border-radius:12px}
  .info{margin-top:16px}
  .info .value{white-space:pre-wrap}

  .page-actions{display:flex;justify-content:space-between;gap:10px;margin-top:18px}
  .btn{height:46px;border-radius:14px;border:0;cursor:pointer;font-weight:800;color:#fff;padding:0 16px;display:inline-flex;align-items:center;gap:10px;letter-spacing:.2px;transition:transform .06s,box-shadow .2s,filter .2s;text-decoration:none}
  .btn:hover{transform:translateY(-1px);filter:brightness(1.03)}
  .btn-primary{background:linear-gradient(180deg,var(--brand-500),var(--brand-700) 60%,var(--brand-900));box-shadow:0 14px 34px rgba(14,165,177,.28)}
  .btn-secondary{background:linear-gradient(180deg,#ffd166,#ffb84d 60%,#e39d1e); color:#1b1200; box-shadow:0 14px 34px rgba(255,209,102,.28)}
  @media (max-width:880px){.fish-head{grid-template-columns: 1fr}.figure{justify-self:start}}
  @media (max-width:780px){.top-bar{padding:0 12px}}
</style>
</head>
<body>

<header class="top-bar">
 <a href="pagina_inicial.php" style="text-decoration:none; color: inherit;"> <div class="brand">
    <img src="Imagens/novaimagem.png" alt="Logo">
    <h1>Aquapanema</h1>
  </div></a>
  <nav class="actions">
    <a href="dashboard.php" class="active"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
    <a href="pagina_inicial.php"><i class="fa-solid fa-camera"></i> Buscar Por Imagem</a>
    <a href="buscar_nome.php"><i class="fa-solid fa-magnifying-glass"></i> Buscar Peixe por Nome</a>
    <a href="historico.php"><i class="fa-solid fa-clock-rotate-left"></i> Histórico</a>
    <a href="usuario.php"><i class="fa-solid fa-user"></i> Meu Perfil</a>
    <?php if ($isLogged): ?>
      <form class="logout-form" action="logout.php" method="post" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
        <button type="submit" title="Sair"><i class="fa-solid fa-right-from-bracket"></i> Sair</button>
      </form>
    <?php else: ?>
      <a href="login.php"><i class="fa-solid fa-right-to-bracket"></i> Login</a>
    <?php endif; ?>
  </nav>
</header>

<main class="wrap">
  <section class="card">
    <div class="card-header">
      <h2><i class="fa-solid fa-fish"></i> Detalhes da Espécie</h2>
    </div>
    <div class="card-body">
      <div class="fish-head">
        <div>
          <h3 class="fish-title"><?php echo e($sci !== '' ? $sci : 'Não informado'); ?></h3>
          <p class="fish-sub"><?php echo e($com !== '' ? $com : '—'); ?></p>
        </div>
        <?php if (!empty($img)): ?>
          <figure class="figure">
            <img src="<?php echo e($img); ?>" alt="Imagem da espécie <?php echo e($sci); ?>">
          </figure>
        <?php endif; ?>
      </div>

      <div class="grid">
        <div class="field"><div class="label">Reino</div><div class="value"><?php echo e($px['reino'] ?? '—'); ?></div></div>
        <div class="field"><div class="label">Filo</div><div class="value"><?php echo e($px['filo'] ?? '—'); ?></div></div>
        <div class="field"><div class="label">Classe</div><div class="value"><?php echo e($px['classe'] ?? '—'); ?></div></div>
        <div class="field"><div class="label">Ordem</div><div class="value"><?php echo e($px['ordem'] ?? '—'); ?></div></div>
        <div class="field"><div class="label">Família</div><div class="value"><?php echo e($px['familia'] ?? '—'); ?></div></div>
        <div class="field"><div class="label">Gênero</div><div class="value"><?php echo e($px['genero'] ?? '—'); ?></div></div>
        <div class="field"><div class="label">Espécie</div><div class="value"><?php echo e($px['especie'] ?? '—'); ?></div></div>
      </div>

      <div class="field info" style="grid-column:1/-1">
        <div class="label">Informações</div>
        <div class="value"><?php echo e($px['informacoes'] ?? '—'); ?></div>
      </div>

      <div class="page-actions">
        <a class="btn btn-secondary" href="<?php echo e($backUrl); ?>">
          <i class="fa-solid fa-arrow-left"></i> Voltar ao histórico
        </a>
        <a class="btn btn-primary" href="pagina_inicial.php">
          <i class="fa-solid fa-camera"></i> Identificar por Imagem
        </a>
      </div>
    </div>
  </section>
</main>

</body>
</html>
