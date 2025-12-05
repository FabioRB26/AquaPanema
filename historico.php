<?php
// historico.php — Histórico de predições do usuário (confirmação/feedback simples + esconder ações após decisão)
require_once __DIR__ . '/index.php';
require_login();

/* Evita exibir conteúdo ao voltar do cache após logout */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$pdo       = pdo();
$usuarioId = (int) $_SESSION['usuario_id'];

/* Helpers */
if (!function_exists('e')) {
  function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('csrf_token')) {
  if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  function csrf_token(){ return $_SESSION['csrf_token']; }
}

/* Verifica se existe a tabela de feedback */
$temFeedback = false;
try {
  $chk = $pdo->query("SHOW TABLES LIKE 'feedback_historico'");
  $temFeedback = $chk && $chk->rowCount() > 0;
} catch(Throwable $e) { $temFeedback = false; }

/* Ações (POST): confirmar, marcar errado, excluir */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    die('Falha de validação (CSRF).');
  }
  $acao = $_POST['acao'] ?? '';
  $hid  = (int)($_POST['hid'] ?? 0);

  if ($hid > 0) {
    if ($acao === 'confirmar') {
      if ($temFeedback) {
        $st = $pdo->prepare("
          INSERT INTO feedback_historico (historico_id, usuario_id, correta, peixe_correto_id, comentario)
          VALUES (:hid, :uid, 1, NULL, NULL)
          ON DUPLICATE KEY UPDATE correta=VALUES(correta), peixe_correto_id=NULL, comentario=NULL, feedback_em=CURRENT_TIMESTAMP
        ");
        $st->execute([':hid'=>$hid, ':uid'=>$usuarioId]);
      } else {
        // Fallback: marca como confirmado (correto)
        $st = $pdo->prepare("UPDATE historico SET confirmado=1 WHERE id=:id AND usuario_id=:uid");
        $st->execute([':id'=>$hid, ':uid'=>$usuarioId]);
      }
      $_SESSION['flash_ok'] = 'Predição confirmada como correta.';
    }
    elseif ($acao === 'errado') {
      if ($temFeedback) {
        $st = $pdo->prepare("
          INSERT INTO feedback_historico (historico_id, usuario_id, correta, peixe_correto_id, comentario)
          VALUES (:hid, :uid, 0, NULL, NULL)
          ON DUPLICATE KEY UPDATE correta=VALUES(correta), peixe_correto_id=NULL, comentario=NULL, feedback_em=CURRENT_TIMESTAMP
        ");
        $st->execute([':hid'=>$hid, ':uid'=>$usuarioId]);
      } else {
        // Fallback: usa -1 para representar "incorreto"
        $st = $pdo->prepare("UPDATE historico SET confirmado=-1 WHERE id=:id AND usuario_id=:uid");
        $st->execute([':id'=>$hid, ':uid'=>$usuarioId]);
      }
      $_SESSION['flash_ok'] = 'Predição marcada como incorreta.';
    }
    elseif ($acao === 'excluir') {
      $st = $pdo->prepare("DELETE FROM historico WHERE id = :id AND usuario_id = :uid");
      $st->execute([':id'=>$hid, ':uid'=>$usuarioId]);
      $_SESSION['flash_ok'] = 'Registro excluído.';
    }
  }

  // PRG: redireciona preservando a query string atual
  $qs = $_SERVER['QUERY_STRING'] ?? '';
  header('Location: historico.php' . ($qs ? ('?'.$qs) : ''));
  exit;
}

/* Filtros GET */
$q       = trim($_GET['q']    ?? '');
$per     = (int)($_GET['per'] ?? 12);
$page    = (int)($_GET['page']?? 1);
if ($per < 6)  $per = 12;
if ($per > 50) $per = 50;
if ($page < 1) $page = 1;
$offset  = ($page - 1) * $per;

/* WHERE dinâmico — placeholders únicos */
$where  = ["h.usuario_id = :uid"];
$params = [':uid' => $usuarioId];

if ($q !== '') {
  $where[] = "(h.nome_cientifico_predito LIKE :q1
            OR p.nome_cientifico LIKE :q2
            OR CONCAT(COALESCE(p.genero,''),' ',COALESCE(p.especie,'')) LIKE :q3
            OR p.nome_comum LIKE :q4
            OR p.genero LIKE :q5
            OR p.especie LIKE :q6)";
  $like = '%'.$q.'%';
  $params[':q1'] = $like;
  $params[':q2'] = $like;
  $params[':q3'] = $like;
  $params[':q4'] = $like;
  $params[':q5'] = $like;
  $params[':q6'] = $like;
}
$whereSql = 'WHERE '.implode(' AND ', $where);

/* COUNT total */
$sqlCount = "
  SELECT COUNT(*) AS total
  FROM historico h
  LEFT JOIN peixes p ON p.id = h.peixe_id
  $whereSql
";
$st = $pdo->prepare($sqlCount);
$st->execute($params);
$total = (int)($st->fetchColumn() ?: 0);
$pages = max(1, (int)ceil($total / $per));
if ($page > $pages) { $page = $pages; $offset = ($page-1)*$per; }

/* SELECT page */
$limit  = (int)$per;
$offset = (int)$offset;
$sql = "
  SELECT
    h.id, h.usuario_id, h.peixe_id, h.nome_cientifico_predito, h.confianca,
    h.caminho_imagem, h.criado_em, h.confirmado,
    COALESCE(NULLIF(p.nome_cientifico,''), TRIM(CONCAT(COALESCE(p.genero,''),' ',COALESCE(p.especie,'')))) AS nome_cient_catalogo,
    p.nome_comum
    ".($temFeedback ? ",
      f.correta AS fb_correta
    " : "")."
  FROM historico h
  LEFT JOIN peixes p  ON p.id  = h.peixe_id
  ".($temFeedback ? "LEFT JOIN feedback_historico f ON f.historico_id = h.id" : "")."
  $whereSql
  ORDER BY h.criado_em DESC, h.id DESC
  LIMIT $limit OFFSET $offset
";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

/* Flash */
$flash_ok = $_SESSION['flash_ok'] ?? '';
unset($_SESSION['flash_ok']);

/* Helper para URLs de paginação preservando filtros */
function build_url($overrides = []) {
  $qs = array_merge($_GET, $overrides);
  foreach ($qs as $k=>$v) if ($v === '' || $v === null) unset($qs[$k]); // preserva '0'
  $qstr = http_build_query($qs);
  return 'historico.php'.($qstr ? ('?'.$qstr) : '');
}

/* URL atual codificada para usar como back */
$currentUrlEncoded = rawurlencode($_SERVER['REQUEST_URI'] ?? 'historico.php');

/* Janela de paginação (números) */
$win   = 2; // mostra 2 antes/depois da atual
$start = max(1, $page - $win);
$end   = min($pages, $page + $win);
if ($start > 2) $showLeftEllipsis = true;
if ($end < $pages - 1) $showRightEllipsis = true;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Aquapanema • Histórico</title>
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
  .card-header{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:18px 22px;background:linear-gradient(180deg,#ffffff,#f5fdff);border-bottom:1px solid #e9f4f7}
  .card-header h2{margin:0;font-size:1.2rem;color:var(--brand-700)}
  .card-body{padding:18px 22px}

  .alert{margin:10px 0 0;padding:10px 12px;border-radius:12px;font-weight:600}
  .alert.ok{background:#e6fff6;border:1px solid #b6f0d9;color:#0f8f6a}
  .alert.err{background:#ffecec;border:1px solid #f1c1c1;color:#b62222}

  .filters{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px}
  .input{height:46px;min-width:240px;flex:1;padding:0 14px;border-radius:12px;border:1.5px solid #d7e3ea;background:#f8fbfc;font-size:15px;outline:none}
  .input:focus{border-color:var(--brand-500);box-shadow:0 0 0 5px rgba(20,184,166,.18);background:#fff}
  .btn{height:40px;border-radius:12px;border:0;cursor:pointer;font-weight:800;color:#fff;padding:0 12px;display:inline-flex;align-items:center;gap:8px;letter-spacing:.2px;text-decoration:none}
  .btn-primary{background:linear-gradient(180deg,var(--brand-500),var(--brand-700))} .btn-danger{background:linear-gradient(180deg,#ff7e6b,#d9452e)}
  .btn-ghost{background:#fff;color:var(--brand-700);border:1.5px solid #d7e3ea;border-radius:12px}
  .btn-small{height:32px;padding:0 10px;font-size:.9rem}

  .meta{color:var(--muted);font-size:.95rem;margin:6px 0 12px}

  .hist-list{display:grid;grid-template-columns:1fr;gap:12px}
  .hist-item{display:grid;grid-template-columns:120px 1fr auto;gap:12px;align-items:center;border:1.5px solid #e6f3f5;border-radius:14px;background:#f8fbfc;padding:10px 12px}
  .thumb{width:120px;height:80px;border-radius:10px;border:1.5px solid #e6f3f5;object-fit:cover;background:#fff}
  .title{margin:0 0 6px;color:var(--brand-700);font-weight:800}
  .line{color:var(--muted);font-size:.95rem}
  .badge{display:inline-block;padding:2px 8px;border-radius:999px;font-weight:800;font-size:.75rem}
  .ok{background:#e6fff6;color:#0f8f6a;border:1px solid #b6f0d9}
  .nok{background:#fff1f0;color:#b12020;border:1px solid #f0c0c0}

  .actions-row{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end}

  .pager{display:flex;justify-content:space-between;align-items:center;margin-top:14px}
  .pager .info{color:var(--muted);font-size:.95rem}
  .pager .pages{display:flex;gap:6px;align-items:center}
  .pager a{color:var(--brand-700);text-decoration:none;padding:8px 12px;border-radius:10px;border:1.5px solid #d7e3ea;background:#fff}
  .pager a:hover{background:#f8fbfc}
  .pager .active{background:var(--brand-300);border-color:var(--brand-300);color:#063b44;font-weight:800}

  @media (max-width:780px){
    .top-bar{padding:0 12px}
    .hist-item{grid-template-columns:1fr}
    .thumb{width:100%; height:160px}
    .actions-row{justify-content:flex-start}
  }
</style>
<script>
function confirmarExclusao(hid){
  if(confirm('Excluir este registro do histórico? Esta ação não pode ser desfeita.')){
    const f = document.getElementById('form-excluir');
    f.hid.value = String(hid);
    f.submit();
  }
}
</script>
</head>
<body>

<header class="top-bar">
 <a href="pagina_inicial.php" style="text-decoration:none; color: inherit;"> <div class="brand">
    <img src="Imagens/novaimagem.png" alt="Logo">
    <h1>Aquapanema</h1>
  </div></a>
  <nav class="actions">
    <a href="dashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
    <a href="pagina_inicial.php"><i class="fa-solid fa-camera"></i> Buscar Por Imagem</a>
    <a href="buscar_nome.php"><i class="fa-solid fa-magnifying-glass"></i> Buscar Peixe por Nome</a>
    <a href="historico.php" class="active"><i class="fa-solid fa-clock-rotate-left"></i> Histórico</a>
    <a href="usuario.php"><i class="fa-solid fa-user"></i> Meu Perfil</a>
    <form class="logout-form" action="logout.php" method="post" style="display:inline">
      <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
      <button type="submit" title="Sair"><i class="fa-solid fa-right-from-bracket"></i> Sair</button>
    </form>
  </nav>
</header>

<main class="wrap">
  <section class="card">
    <div class="card-header">
      <h2><i class="fa-solid fa-clock-rotate-left"></i> Meu Histórico</h2>
    </div>
    <div class="card-body">

      <?php if (!empty($flash_ok)): ?>
        <div class="alert ok"><i class="fa-solid fa-circle-check"></i> <?php echo e($flash_ok); ?></div>
      <?php endif; ?>

      <!-- Filtros -->
      <form method="get" class="filters" action="historico.php">
        <input class="input" type="text" name="q" placeholder="Buscar por científico, comum, gênero, espécie..." value="<?php echo e($q); ?>">
        <input type="hidden" name="page" value="1">
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-search"></i> Filtrar</button>
        <?php if ($q !== '' || $per !== 12): ?>
          <a class="btn btn-ghost" href="historico.php"><i class="fa-solid fa-rotate-left"></i> Limpar</a>
        <?php endif; ?>
      </form>

      <div class="meta">
        <?php
          $ini = $total ? ($offset + 1) : 0;
          $fim = min($offset + $per, $total);
          echo e($total) . " registro(s) — mostrando " . $ini . "–" . $fim;
        ?>
      </div>

      <?php if ($total === 0): ?>
        <p class="meta">Nenhum registro encontrado.</p>
      <?php else: ?>
        <div class="hist-list">
          <?php foreach ($rows as $r):
            $sciCat = trim((string)($r['nome_cient_catalogo'] ?? ''));
            $sciRaw = trim((string)($r['nome_cientifico_predito'] ?? ''));
            $nomeSci = $sciCat !== '' ? $sciCat : ($sciRaw !== '' ? $sciRaw : '—');
            $nomeCom = trim((string)($r['nome_comum'] ?? ''));
            $conf    = is_null($r['confianca']) ? null : (float)$r['confianca'];
            $pct     = is_null($conf) ? null : number_format($conf * 100, 2, ',', '.').'%';
            $dt      = date('d/m/Y H:i', strtotime($r['criado_em']));
            $img     = $r['caminho_imagem'] ?: '';
            $linkEsp = $r['peixe_id']
              ? ('peixe_historico.php?id='.(int)$r['peixe_id'].'&back='.$currentUrlEncoded)
              : '';

            // Badge/status + decisão
            $badge = '';
            $decidido = false;
            if ($temFeedback) {
              if ($r['fb_correta'] === '1' || $r['fb_correta'] === 1) {
                $badge = '<span class="badge ok">Correto</span>'; $decidido = true;
              } elseif ($r['fb_correta'] === '0' || $r['fb_correta'] === 0) {
                $badge = '<span class="badge nok">Incorreto</span>'; $decidido = true;
              }
            } else {
              $confVal = (int)($r['confirmado'] ?? 0);
              if ($confVal === 1) { $badge = '<span class="badge ok">Confirmado</span>'; $decidido = true; }
              elseif ($confVal === -1) { $badge = '<span class="badge nok">Incorreto</span>'; $decidido = true; }
            }
          ?>
          <div class="hist-item">
            <div>
              <?php if ($img): ?>
                <img class="thumb" src="<?php echo e($img); ?>" alt="Imagem enviada em <?php echo e($dt); ?>">
              <?php else: ?>
                <div class="thumb" style="display:grid;place-items:center;color:#9db3bd"><i class="fa-solid fa-image"></i></div>
              <?php endif; ?>
            </div>

            <div>
              <div class="title">
                <?php echo e($nomeSci); ?>
                <?php if ($nomeCom !== ''): ?>
                  <span class="line">— <?php echo e($nomeCom); ?></span>
                <?php endif; ?>
                <?php if ($badge) echo ' '.$badge; ?>
              </div>
              <div class="line">
                <?php if ($pct): ?>Confiança: <strong><?php echo e($pct); ?></strong> · <?php endif; ?>
                Enviado em: <?php echo e($dt); ?>
              </div>
              <?php if (!$r['peixe_id'] && $sciRaw !== ''): ?>
                <div class="line">Previsto: <?php echo e($sciRaw); ?> (não encontrado no catálogo)</div>
              <?php endif; ?>
            </div>

            <div class="actions-row">
              <?php if ($linkEsp): ?>
                <a class="btn btn-ghost btn-small" href="<?php echo e($linkEsp); ?>">
                  <i class="fa-solid fa-fish"></i> Ver espécie
                </a>
              <?php endif; ?>

              <?php if (!$decidido): ?>
                <!-- Confirmar -->
                <form method="post" action="historico.php" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                  <input type="hidden" name="acao" value="confirmar">
                  <input type="hidden" name="hid"  value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-primary btn-small" type="submit"><i class="fa-solid fa-check"></i> Confirmar</button>
                </form>

                <!-- Marcar como incorreta -->
                <form method="post" action="historico.php" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                  <input type="hidden" name="acao" value="errado">
                  <input type="hidden" name="hid"  value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-ghost btn-small" type="submit"><i class="fa-solid fa-xmark"></i> Marcar como incorreta</button>
                </form>
              <?php endif; ?>

              <!-- Excluir -->
              <button class="btn btn-danger btn-small" onclick="confirmarExclusao(<?php echo (int)$r['id']; ?>)">
                <i class="fa-solid fa-trash"></i> Excluir
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Paginação -->
        <div class="pager">
          <div class="info">Página <?php echo e($page); ?> de <?php echo e($pages); ?></div>
          <div class="pages">
            <?php if ($page > 1): ?>
              <a href="<?php echo e(build_url(['page'=>$page-1])); ?>"><i class="fa-solid fa-angle-left"></i> Anterior</a>
            <?php endif; ?>

            <?php if ($start > 1): ?>
              <a href="<?php echo e(build_url(['page'=>1])); ?>">1</a>
              <?php if (!empty($showLeftEllipsis)): ?><span>…</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start; $i <= $end; $i++): ?>
              <?php if ($i == $page): ?>
                <a class="active" href="<?php echo e(build_url(['page'=>$i])); ?>"><?php echo $i; ?></a>
              <?php else: ?>
                <a href="<?php echo e(build_url(['page'=>$i])); ?>"><?php echo $i; ?></a>
              <?php endif; ?>
            <?php endfor; ?>

            <?php if ($end < $pages): ?>
              <?php if (!empty($showRightEllipsis)): ?><span>…</span><?php endif; ?>
              <a href="<?php echo e(build_url(['page'=>$pages])); ?>"><?php echo $pages; ?></a>
            <?php endif; ?>

            <?php if ($page < $pages): ?>
              <a href="<?php echo e(build_url(['page'=>$page+1])); ?>">Próxima <i class="fa-solid fa-angle-right"></i></a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<!-- Form oculto (POST + CSRF) para excluir -->
<form id="form-excluir" method="POST" action="historico.php" style="display:none">
  <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
  <input type="hidden" name="acao" value="excluir">
  <input type="hidden" name="hid" value="">
</form>

</body>
</html>
