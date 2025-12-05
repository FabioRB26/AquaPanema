<?php
// peixe.php — Detalhe do peixe selecionado
require_once __DIR__ . '/index.php';

$pdo      = pdo();
$isLogged = is_logged_in();
$csrf     = csrf_token();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo 'ID inválido.';
  exit;
}

/* ====== Mapeamento de caminho Windows -> URL do seu servidor ======
   Exemplo:
   - No BD:  C:\Users\Fábio\Desktop\Banco de Imagens Completo\original\Olygosarcus_paranensis\Olygosarcus_paranensis001.jpg
   - Na web: /imagens/original/Olygosarcus_paranensis/Olygosarcus_paranensis001.jpg
   Ajuste abaixo para bater com sua estrutura.
*/
$WINDOWS_PREFIX = 'C:\Users\Fábio\Desktop\Banco de Imagens Completo\original\\';
$WEB_PREFIX     = '/imagens/original/'; // coloque as pastas de imagens dentro do htdocs (ex.: htdocs/imagens/original/...)

/* Normaliza um caminho do BD (Windows) para URL web */
function img_src_from_db($raw) {
  global $WINDOWS_PREFIX, $WEB_PREFIX;
  $s = (string)$raw;
  if ($s === '') return null;

  // Normaliza barras
  $s_norm = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $s);

  // Se começa com o prefixo Windows, mapeia para uma URL do servidor
  $pref_norm = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $WINDOWS_PREFIX);
  if (stripos($s_norm, $pref_norm) === 0) {
    $rel = substr($s_norm, strlen($pref_norm));                       // parte relativa após o prefixo Windows
    $rel = str_replace(DIRECTORY_SEPARATOR, '/', $rel);               // volta para barra normal de URL
    return $WEB_PREFIX . $rel;                                        // monta URL
  }

  // Se já vier algo “web-friendly”, só troca \ por /
  return str_replace('\\', '/', $s);
}

// Busca peixe + fotos (img1..img3)
try {
  $sql = "SELECT p.id,
                 p.nome_comum, p.nome_cientifico,
                 p.reino, p.filo, p.classe, p.ordem, p.familia, p.genero, p.especie,
                 p.informacoes,
                 f.img1, f.img2, f.img3
          FROM peixes p
          LEFT JOIN peixe_fotos f ON f.peixe_id = p.id
          WHERE p.id = :id
          LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([':id' => $id]);
  $px = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  http_response_code(500);
  echo 'Erro ao buscar peixe.';
  exit;
}

if (!$px) {
  http_response_code(404);
  echo 'Peixe não encontrado.';
  exit;
}

// Nome científico (cálculo se vier vazio)
$sci = trim((string)($px['nome_cientifico'] ?? ''));
if ($sci === '') {
  $gn = trim((string)($px['genero']  ?? ''));
  $sp = trim((string)($px['especie'] ?? ''));
  $sci = trim($gn . ' ' . $sp);
}
$com = trim((string)($px['nome_comum'] ?? ''));

// Galeria: até 3 imagens
$imgs = [];
foreach (['img1','img2','img3'] as $k) {
  if (!empty($px[$k])) {
    $url = img_src_from_db($px[$k]);
    if ($url) $imgs[] = $url;
  }
}
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
  :root {
    --brand-900:#062a33; --brand-700:#0d5868; --brand-500:#14b8a6; --brand-300:#99f6e4;
    --accent:#ffd166; --accent-2:#ff7e6b; --bg:#e9fbff; --text:#0e1b24; --muted:#5f7582; --radius:20px;
  }
  *{box-sizing:border-box}
  html,body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;background:var(--bg);color:var(--text)}

  /* Top bar */
  .top-bar{height:10vh;min-height:64px;background:linear-gradient(90deg,var(--brand-900),var(--brand-700));color:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 24px;box-shadow:0 6px 16px rgba(0,0,0,.18)}
  .brand{display:flex;align-items:center;gap:12px}
  .brand img{height:44px;width:44px;border-radius:50%;border:2px solid #fff;object-fit:cover}
  .brand h1{margin:0;font-size:1.25rem;color:var(--accent)}
  nav.actions{display:flex;align-items:center;gap:14px}
  nav.actions a, nav.actions button{color:#fff;text-decoration:none;font-weight:700;display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:12px}
  nav.actions a:hover, nav.actions button:hover{background:rgba(255,255,255,.15)}
  .logout-form{display:inline;margin:0}
  .logout-form button{background:none;border:0;cursor:pointer;font:inherit}

  /* Conteúdo — cards */
  .wrap{max-width:1100px;margin:28px auto;padding:0 16px}
  .card{background:rgba(255,255,255,.94);border:1px solid #e6f3f5;border-radius:var(--radius);box-shadow:0 16px 48px rgba(6,42,51,.16);overflow:hidden;margin-bottom:20px}
  .card-header{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;background:linear-gradient(180deg,#ffffff,#f5fdff);border-bottom:1px solid #e9f4f7}
  .card-header h2{margin:0;font-size:1.2rem;color:var(--brand-700)}
  .card-body{padding:18px 22px}

  .fish-head{display:grid;grid-template-columns: 1fr; gap:12px; align-items:start}
  .fish-title{margin:0 0 4px; font-size:1.3rem; color:var(--brand-700)}
  .fish-sub {margin:0 0 12px; color:var(--muted)}

  /* Galeria horizontal */
.gallery{
  display:flex;
  justify-content:center;   /* centraliza horizontal */
  gap:12px;
  align-items:center;
  padding:8px 2px;
  margin:8px 0 18px 0;
  flex-wrap:wrap;           /* permite quebrar linha se a tela for estreita */
}
.gallery img{
  height:160px;
  width:auto;
  border-radius:12px;
  border:1px solid #e6f3f5;
  box-shadow:0 6px 16px rgba(0,0,0,.12);
}


  .grid{display:grid;grid-template-columns:repeat(2,minmax(240px,1fr));gap:16px 22px; margin-top:6px}
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

  @media (max-width:880px){ .grid{grid-template-columns:1fr} }
  @media (max-width:780px){ .top-bar{padding:0 12px} .gallery img{height:140px} }
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
        <button type="submit" title="Sair">
          <i class="fa-solid fa-right-from-bracket"></i> Sair
        </button>
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

        <?php if (!empty($imgs)): ?>
          <div class="gallery">
            <?php foreach ($imgs as $src): ?>
              <img src="<?php echo e($src); ?>" alt="Imagem da espécie <?php echo e($sci); ?>">
            <?php endforeach; ?>
          </div>
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
        <a class="btn btn-secondary" href="buscar_nome.php"><i class="fa-solid fa-arrow-left"></i> Voltar para a lista</a>
        <a class="btn btn-primary" href="pagina_inicial.php"><i class="fa-solid fa-camera"></i> Identificar por Imagem</a>
      </div>
    </div>
  </section>
</main>

</body>
</html>
