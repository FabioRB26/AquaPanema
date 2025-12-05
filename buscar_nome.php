<?php
// buscar_nome.php — Listagem e busca de peixes por nome (científico/comum)
require_once __DIR__ . '/index.php';

$pdo      = pdo();
$isLogged = is_logged_in();
$csrf     = csrf_token();

/* helper, se não existir */
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$q = trim($_GET['q'] ?? '');

/* Descobre se existe coluna nome_cientifico na tabela peixes */
$hasNomeCient = false;
try {
  $chk = $pdo->query("SHOW COLUMNS FROM peixes LIKE 'nome_cientifico'");
  $hasNomeCient = $chk && $chk->rowCount() > 0;
} catch (Throwable $e) {
  $hasNomeCient = false;
}

/* Select base: sempre expõe um alias nome_cientifico calculado */
$selectNomeCient = $hasNomeCient
  ? "COALESCE(NULLIF(nome_cientifico,''), TRIM(CONCAT(COALESCE(genero,''),' ',COALESCE(especie,''))))"
  : "TRIM(CONCAT(COALESCE(genero,''),' ',COALESCE(especie,'')))";

$sql = "
  SELECT
    id,
    nome_comum,
    $selectNomeCient AS nome_cientifico
  FROM peixes
";

$whereParts = [];
$params     = [];

/* WHERE com placeholders únicos p/ evitar HY093 */
if ($q !== '') {
  $whereParts[] = "nome_comum LIKE :q1";                                   $params[':q1'] = '%'.$q.'%';
  $whereParts[] = "TRIM(CONCAT(COALESCE(genero,''),' ',COALESCE(especie,''))) LIKE :q2"; $params[':q2'] = '%'.$q.'%';
  $whereParts[] = "genero LIKE :q3";                                        $params[':q3'] = '%'.$q.'%';
  $whereParts[] = "especie LIKE :q4";                                       $params[':q4'] = '%'.$q.'%';
  if ($hasNomeCient) { $whereParts[] = "nome_cientifico LIKE :q5";          $params[':q5'] = '%'.$q.'%'; }
}
if (!empty($whereParts)) $sql .= "\nWHERE (" . implode(" OR ", $whereParts) . ")";

$sql .= "\nORDER BY nome_cientifico ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$peixes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Aquapanema • Buscar Peixe por Nome</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
  :root { --brand-900:#062a33; --brand-700:#0d5868; --brand-500:#14b8a6; --brand-300:#99f6e4; --accent:#ffd166; --accent-2:#ff7e6b; --bg:#e9fbff; --text:#0e1b24; --muted:#5f7582; --radius:20px; }
  *{box-sizing:border-box}
  html,body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;background:var(--bg);color:var(--text)}

  /* Top bar — igual ao perfil */
  .top-bar{height:10vh;min-height:64px;background:linear-gradient(90deg,var(--brand-900),var(--brand-700));color:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 24px;box-shadow:0 6px 16px rgba(0,0,0,.18)}
  .brand{display:flex;align-items:center;gap:12px}
  .brand img{height:44px;width:44px;border-radius:50%;border:2px solid #fff;object-fit:cover}
  .brand h1{margin:0;font-size:1.25rem;color:var(--accent)}
  nav.actions{display:flex;align-items:center;gap:14px}
  nav.actions a, nav.actions button{color:#fff;text-decoration:none;font-weight:700;display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:12px}
  nav.actions a:hover, nav.actions button:hover{background:rgba(255,255,255,.15)}
  .logout-form{display:inline;margin:0}
  .logout-form button{background:none;border:0;cursor:pointer;font:inherit}

  /* Conteúdo — padrão de cards */
  .wrap{max-width:1100px;margin:28px auto;padding:0 16px}
  .card{background:rgba(255,255,255,.94);border:1px solid #e6f3f5;border-radius:var(--radius);box-shadow:0 16px 48px rgba(6,42,51,.16);overflow:hidden;margin-bottom:20px}
  .card-header{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;background:linear-gradient(180deg,#ffffff,#f5fdff);border-bottom:1px solid #e9f4f7}
  .card-header h2{margin:0;font-size:1.2rem;color:var(--brand-700)}
  .card-body{padding:18px 22px}

  .search-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px}
  .input{flex:1;min-width:240px;height:46px;padding:0 14px;border-radius:12px;border:1.5px solid #d7e3ea;background:#f8fbfc;font-size:15px;outline:none}
  .input:focus{border-color:var(--brand-500);box-shadow:0 0 0 5px rgba(20,184,166,.18);background:#fff}
  .btn{height:46px;border-radius:14px;border:0;cursor:pointer;font-weight:800;color:#fff;padding:0 16px;display:inline-flex;align-items:center;gap:10px;letter-spacing:.2px;transition:transform .06s,box-shadow .2s,filter .2s;text-decoration:none}
  .btn:hover{transform:translateY(-1px);filter:brightness(1.03)}
  .btn-primary{background:linear-gradient(180deg,var(--brand-500),var(--brand-700) 60%,var(--brand-900));box-shadow:0 14px 34px rgba(14,165,177,.28)}
  .btn-ghost{background:#fff;color:var(--brand-700);border:1.5px solid #d7e3ea;border-radius:12px;box-shadow:none}

  .meta{margin:6px 0 12px;color:var(--muted);font-size:.95rem}
  .list{list-style:none;margin:0;padding:0}
  .item{border:1.5px solid #e6f3f5;border-radius:14px;background:#f8fbfc;margin:8px 0}
  .item a{display:flex;align-items:center;gap:10px;padding:12px 14px;color:var(--text);text-decoration:none}
  .item a:hover{background:#ffffff}
  .sci{font-weight:800;color:var(--brand-700)}
  .sep{opacity:.5;margin:0 6px}
  .com{color:var(--muted)}
  .actions-bottom{display:flex;justify-content:flex-end;margin-top:14px}
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
      <h2><i class="fa-solid fa-magnifying-glass"></i> Buscar Peixe por Nome</h2>
    </div>
    <div class="card-body">
      <form class="search-row" method="GET" action="buscar_nome.php">
        <input class="input" type="text" name="q" placeholder="Digite nome científico, comum, gênero ou espécie..." value="<?php echo e($q); ?>">
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-search"></i> Buscar</button>
      </form>

      <div class="meta">
        <?php
          $total = count($peixes);
          if ($q === '') echo e($total).' espécies encontradas.';
          else           echo e($total)." resultado(s) para '".e($q)."'.";
        ?>
      </div>

      <?php if ($total === 0): ?>
        <p class="meta">Nada encontrado. Tente outro termo.</p>
        <?php if ($q !== ''): ?>
          <div class="actions-bottom">
            <a class="btn btn-ghost" href="buscar_nome.php"><i class="fa-solid fa-rotate-left"></i> Mostrar lista completa</a>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <ul class="list">
          <?php foreach ($peixes as $px):
            $sci = trim((string)$px['nome_cientifico']);
            $com = trim((string)($px['nome_comum'] ?? ''));
          ?>
          <li class="item">
            <a href="peixe.php?id=<?php echo (int)$px['id']; ?>" title="Ver detalhes de <?php echo e($sci); ?>">
              <i class="fa-solid fa-fish"></i>
              <span class="sci"><?php echo e($sci !== '' ? $sci : '—'); ?></span>
              <span class="sep">—</span>
              <span class="com"><?php echo e($com !== '' ? $com : 'sem nome comum'); ?></span>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>

        <?php if ($q !== ''): ?>
          <div class="actions-bottom">
            <a class="btn btn-ghost" href="buscar_nome.php"><i class="fa-solid fa-rotate-left"></i> Mostrar lista completa</a>
          </div>
        <?php endif; ?>

      <?php endif; ?>
    </div>
  </section>
</main>

</body>
</html>
