<?php
// dashboard.php — Painel unificado no padrão visual do sistema
require_once __DIR__ . '/index.php';

$pdo       = pdo();
$isLogged  = is_logged_in();
$csrf      = csrf_token();
$usuarioId = $_SESSION['usuario_id'] ?? null;

date_default_timezone_set('America/Sao_Paulo');

/* helper */
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

// Filtros de período (default: últimos 30 dias)
$ini = $_GET['ini'] ?? date('Y-m-d', strtotime('-30 days'));
$fim = $_GET['fim'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ini)) $ini = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim)) $fim = date('Y-m-d');

// Descobrir se há tabela de feedback
$temFeedback = false;
try { $q = $pdo->query("SHOW TABLES LIKE 'feedback_historico'"); $temFeedback = $q && $q->rowCount()>0; } catch(Throwable $e){ $temFeedback=false; }

// ===== Coleta de dados =====
// INDIVIDUAL
$cardsMe = ['total_predicoes'=>0,'especies_distintas'=>0,'taxa_acerto'=>null];
$serieDiaMe=[]; $topEspeciesMe=[]; $porHoraMe=[]; $markersMe=[];
if ($usuarioId) {
  $stmt = $pdo->prepare("SELECT COUNT(*) AS total, COUNT(DISTINCT COALESCE(peixe_id, nome_cientifico_predito)) AS distintos
                         FROM historico WHERE usuario_id=:uid AND DATE(criado_em) BETWEEN :ini AND :fim");
  $stmt->execute([':uid'=>$usuarioId, ':ini'=>$ini, ':fim'=>$fim]);
  $r = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'distintos'=>0];
  $cardsMe['total_predicoes']    = (int)$r['total'];
  $cardsMe['especies_distintas'] = (int)$r['distintos'];

  if ($temFeedback) {
    $stmt = $pdo->prepare("SELECT SUM(f.correta=1)/NULLIF(COUNT(*),0) FROM feedback_historico f JOIN historico h ON h.id=f.historico_id WHERE h.usuario_id=:uid AND DATE(h.criado_em) BETWEEN :ini AND :fim");
    $stmt->execute([':uid'=>$usuarioId, ':ini'=>$ini, ':fim'=>$fim]);
    $cardsMe['taxa_acerto'] = $stmt->fetchColumn();
  }

  $stmt = $pdo->prepare("SELECT DATE(criado_em) AS dia, COUNT(*) AS total FROM historico WHERE usuario_id=:uid AND DATE(criado_em) BETWEEN :ini AND :fim GROUP BY dia ORDER BY dia");
  $stmt->execute([':uid'=>$usuarioId, ':ini'=>$ini, ':fim'=>$fim]);
  $serieDiaMe = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $stmt = $pdo->prepare("SELECT COALESCE(p.nome_comum, h.nome_cientifico_predito) AS especie, COUNT(*) AS total
                         FROM historico h LEFT JOIN peixes p ON p.id=h.peixe_id
                         WHERE h.usuario_id=:uid AND DATE(h.criado_em) BETWEEN :ini AND :fim
                         GROUP BY especie ORDER BY total DESC LIMIT 10");
  $stmt->execute([':uid'=>$usuarioId, ':ini'=>$ini, ':fim'=>$fim]);
  $topEspeciesMe = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $stmt = $pdo->prepare("SELECT HOUR(criado_em) AS hora, COUNT(*) AS total FROM historico WHERE usuario_id=:uid AND DATE(criado_em) BETWEEN :ini AND :fim GROUP BY hora ORDER BY hora");
  $stmt->execute([':uid'=>$usuarioId, ':ini'=>$ini, ':fim'=>$fim]);
  $porHoraMe = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $stmt = $pdo->prepare("SELECT h.id, h.lat, h.lng, h.criado_em, COALESCE(p.nome_comum, h.nome_cientifico_predito) AS especie, h.caminho_imagem
                         FROM historico h LEFT JOIN peixes p ON p.id=h.peixe_id
                         WHERE h.usuario_id=:uid AND h.lat IS NOT NULL AND h.lng IS NOT NULL AND DATE(h.criado_em) BETWEEN :ini AND :fim
                         ORDER BY h.criado_em DESC LIMIT 500");
  $stmt->execute([':uid'=>$usuarioId, ':ini'=>$ini, ':fim'=>$fim]);
  $markersMe = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// GERAL
$cardsAll = ['total_predicoes'=>0,'usuarios_ativos'=>0,'especies_distintas'=>0,'taxa_acerto'=>null];
$serieDiaAll=[]; $topEspeciesAll=[]; $porHoraAll=[]; $markersAll=[];

$stmt = $pdo->prepare("SELECT COUNT(*) AS total, COUNT(DISTINCT usuario_id) AS ativos, COUNT(DISTINCT COALESCE(peixe_id, nome_cientifico_predito)) AS distintos FROM historico WHERE DATE(criado_em) BETWEEN :ini AND :fim");
$stmt->execute([':ini'=>$ini, ':fim'=>$fim]);
$r = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'ativos'=>0,'distintos'=>0];
$cardsAll['total_predicoes']    = (int)$r['total'];
$cardsAll['usuarios_ativos']    = (int)$r['ativos'];
$cardsAll['especies_distintas'] = (int)$r['distintos'];

if ($temFeedback) {
  $stmt = $pdo->prepare("SELECT SUM(f.correta=1)/NULLIF(COUNT(*),0) FROM feedback_historico f JOIN historico h ON h.id=f.historico_id WHERE DATE(h.criado_em) BETWEEN :ini AND :fim");
  $stmt->execute([':ini'=>$ini, ':fim'=>$fim]);
  $cardsAll['taxa_acerto'] = $stmt->fetchColumn();
}

$stmt = $pdo->prepare("SELECT DATE(criado_em) AS dia, COUNT(*) AS total FROM historico WHERE DATE(criado_em) BETWEEN :ini AND :fim GROUP BY dia ORDER BY dia");
$stmt->execute([':ini'=>$ini, ':fim'=>$fim]);
$serieDiaAll = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$stmt = $pdo->prepare("SELECT COALESCE(p.nome_comum, h.nome_cientifico_predito) AS especie, COUNT(*) AS total FROM historico h LEFT JOIN peixes p ON p.id=h.peixe_id WHERE DATE(h.criado_em) BETWEEN :ini AND :fim GROUP BY especie ORDER BY total DESC LIMIT 10");
$stmt->execute([':ini'=>$ini, ':fim'=>$fim]);
$topEspeciesAll = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$stmt = $pdo->prepare("SELECT HOUR(criado_em) AS hora, COUNT(*) AS total FROM historico WHERE DATE(criado_em) BETWEEN :ini AND :fim GROUP BY hora ORDER BY hora");
$stmt->execute([':ini'=>$ini, ':fim'=>$fim]);
$porHoraAll = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$stmt = $pdo->prepare("SELECT h.id, h.lat, h.lng, h.criado_em, COALESCE(p.nome_comum, h.nome_cientifico_predito) AS especie, h.caminho_imagem FROM historico h LEFT JOIN peixes p ON p.id=h.peixe_id WHERE h.lat IS NOT NULL AND h.lng IS NOT NULL AND DATE(h.criado_em) BETWEEN :ini AND :fim ORDER BY h.criado_em DESC LIMIT 800");
$stmt->execute([':ini'=>$ini, ':fim'=>$fim]);
$markersAll = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Aquapanema • Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<!-- Leaflet e MarkerCluster -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css" />
<script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
  :root { --brand-900:#062a33; --brand-700:#0d5868; --brand-500:#14b8a6; --brand-300:#99f6e4; --accent:#ffd166; --accent-2:#ff7e6b; --bg:#e9fbff; --text:#0e1b24; --muted:#5f7582; --radius:20px; }
  *{box-sizing:border-box}
  html,body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;background:var(--bg);color:var(--text)}

  /* Top bar — MESMO PADRÃO das outras páginas */
  .top-bar{height:10vh;min-height:64px;background:linear-gradient(90deg,var(--brand-900),var(--brand-700));color:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 24px;box-shadow:0 6px 16px rgba(0,0,0,.18)}
  .brand{display:flex;align-items:center;gap:12px}
  .brand img{height:44px;width:44px;border-radius:50%;border:2px solid #fff;object-fit:cover}
  .brand h1{margin:0;font-size:1.25rem;color:var(--accent)}
  nav.actions{display:flex;align-items:center;gap:14px}
  nav.actions a, nav.actions button{color:#fff;text-decoration:none;font-weight:700;display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:12px}
  nav.actions a:hover, nav.actions button:hover{background:rgba(255,255,255,.15)}
  nav.actions a.active{background:rgba(255,255,255,.22)}
  .logout-form{display:inline;margin:0}
  .logout-form button{background:none;border:0;cursor:pointer;font:inherit}

  /* Cards / layout */
  .wrap{max-width:1100px;margin:28px auto;padding:0 16px}
  .card{background:rgba(255,255,255,.94);border:1px solid #e6f3f5;border-radius:var(--radius);box-shadow:0 16px 48px rgba(6,42,51,.16);overflow:hidden;margin-bottom:20px}
  .card-header{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;background:linear-gradient(180deg,#ffffff,#f5fdff);border-bottom:1px solid #e9f4f7}
  .card-header h2{margin:0;font-size:1.2rem;color:var(--brand-700)}
  .card-body{padding:18px 22px}

  .grid{display:grid;gap:16px;grid-template-columns:repeat(12,1fr)}
  .kpi{display:flex;align-items:center;justify-content:space-between;gap:10px}
  .kpi h3{margin:4px 0;font-size:.95rem;color:var(--muted)}
  .kpi .value{font-size:1.6rem;font-weight:800}
  .pill{font-size:.8rem;background:#ecfeff;color:#055a59;padding:4px 8px;border-radius:999px}

  .map{height:360px;border-radius:14px;overflow:hidden}

  .table{width:100%;border-collapse:collapse;font-size:14px}
  .table th,.table td{padding:10px 8px;border-bottom:1px solid #eef4f6}
  .table th{text-align:left;color:var(--muted);font-weight:700;font-size:12px}
  .table tr:hover{background:#f7fdfe}

  .filters{display:flex;gap:10px;flex-wrap:wrap}
  .filters input[type=date]{height:40px;padding:0 12px;border-radius:10px;border:1.5px solid #d7e3ea;background:#f8fbfc}
  .filters button{height:40px;border-radius:12px;border:0;padding:0 14px;font-weight:800;color:#fff;background:linear-gradient(180deg,var(--brand-500),var(--brand-700));box-shadow:0 10px 24px rgba(14,165,166,.25);cursor:pointer}

  .tabbar{display:flex;gap:8px;margin-bottom:16px}
  .tabbar button{padding:8px 12px;border-radius:999px;border:1px solid #d7e3ea;background:#fff;color:var(--brand-700);font-weight:800;cursor:pointer}
  .tabbar button.active{background:#dff6f6;border-color:#14b8a6}

  .badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#fef3c7;color:#92400e;font-weight:700;font-size:12px}
</style>
</head>
<body>
<header class="top-bar">
 <a href="pagina_inicial.php" style="text-decoration:none; color: inherit;"> <div class="brand">
    <img src="Imagens/novaimagem.png" alt="Logo">
    <h1>Aquapanema</h1>
  </div></a>
  <nav class="actions">
    <!-- NOVA referência do Dashboard, no mesmo padrão -->
    <a href="dashboard.php" class="active"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
    <a href="pagina_inicial.php"><i class="fa-solid fa-camera"></i> Buscar Por Imagem</a>
    <a href="buscar_nome.php"><i class="fa-solid fa-magnifying-glass"></i> Buscar Peixe por Nome</a>
    <a href="historico.php"><i class="fa-solid fa-clock-rotate-left"></i> Histórico</a>
    <a href="usuario.php"><i class="fa-solid fa-user"></i> Meu Perfil</a>
    <?php if ($isLogged): ?>
      <form class="logout-form" action="logout.php" method="post">
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
      <h2><i class="fa-solid fa-chart-line"></i> Dashboard</h2>
      <form class="filters" method="get">
        <label>De: <input type="date" name="ini" value="<?= e($ini) ?>"></label>
        <label>Até: <input type="date" name="fim" value="<?= e($fim) ?>"></label>
        <button type="submit"><i class="fa-solid fa-rotate"></i> Atualizar</button>
      </form>
    </div>
    <div class="card-body">

      <div class="tabbar">
        <button class="tab active" data-tab="meu"><i class="fa-solid fa-user"></i> Meu painel</button>
        <button class="tab" data-tab="geral"><i class="fa-solid fa-globe"></i> Geral</button>
      </div>

      <!-- ====== MEU PAINEL ====== -->
      <section id="tab-meu">
        <div class="grid">
          <div class="card" style="grid-column: span 3;">
            <div class="kpi"><div><h3>&nbsp;&nbsp;Minhas predições</h3><div class="value"><?= number_format($cardsMe['total_predicoes'] ?? 0, 0, ',', '.') ?></div></div><span class="pill">Período</span></div>
          </div>
          <div class="card" style="grid-column: span 3;">
            <div class="kpi"><div><h3>&nbsp;&nbsp;Espécies &nbsp;&nbsp;diferentes</h3><div class="value"><?= number_format($cardsMe['especies_distintas'] ?? 0, 0, ',', '.') ?></div></div><span class="pill">Diversidade</span></div>
          </div>
          <div class="card" style="grid-column: span 3;">
            <div class="kpi"><div><h3>&nbsp;&nbsp;Taxa de acerto</h3><div class="value"><?= ($cardsMe['taxa_acerto']===null? '—' : number_format($cardsMe['taxa_acerto']*100, 1, ',', '.').'%') ?></div></div><span class="pill">Feedback</span></div>
          </div>
          <div class="card" style="grid-column: span 3;">
            <div class="kpi"><div><h3>&nbsp;&nbsp;Período</h3><div class="value"><span class="badge"><?= e($ini) ?> → <?= e($fim) ?></span></div></div></div>
          </div>

          <div class="card" style="grid-column: span 7;">
            <h3 style="margin:6px 0 12px;">Mapa das minhas predições</h3>
            <div id="mapMe" class="map"></div>
            <p class="muted" style="color:var(--muted);margin:8px 0 0;">Pins aparecem apenas quando há coordenadas (lat/lng) salvas nas predições.</p>
          </div>
          <div class="card" style="grid-column: span 5;">
            <h3 style="margin:6px 0 12px;">Minhas predições por dia</h3>
            <canvas id="chartMeDia" height="200"></canvas>
          </div>

          <div class="card" style="grid-column: span 6;">
            <h3 style="margin:6px 0 12px;">Top espécies (minhas)</h3>
            <canvas id="chartMeTop" height="220"></canvas>
          </div>
          <div class="card" style="grid-column: span 6;">
            <h3 style="margin:6px 0 12px;">Picos por hora (minhas)</h3>
            <canvas id="chartMeHora" height="220"></canvas>
          </div>

          <div class="card" style="grid-column: span 12;">
            <h3 style="margin:6px 0 12px;">Últimas predições</h3>
            <table class="table">
              <thead><tr><th>#</th><th>Data</th><th>Espécie</th><th>Confiança</th><th>Local</th><th>Imagem</th></tr></thead>
              <tbody>
              <?php
              if ($usuarioId) {
                $stmt = $pdo->prepare("SELECT h.id, h.criado_em, h.confianca, h.lat, h.lng, h.caminho_imagem, COALESCE(p.nome_comum, h.nome_cientifico_predito) AS especie FROM historico h LEFT JOIN peixes p ON p.id=h.peixe_id WHERE h.usuario_id=:uid AND DATE(h.criado_em) BETWEEN :ini AND :fim ORDER BY h.criado_em DESC LIMIT 20");
                $stmt->execute([':uid'=>$usuarioId, ':ini'=>$ini, ':fim'=>$fim]);
                foreach ($stmt as $r): ?>
                  <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td><?= e($r['criado_em']) ?></td>
                    <td><?= e($r['especie'] ?? '—') ?></td>
                    <td><?= $r['confianca']!==null ? number_format($r['confianca']*100,1,',','.') . '%' : '—' ?></td>
                    <td><?= ($r['lat'] && $r['lng']) ? (e($r['lat']).', '.e($r['lng'])) : '—' ?></td>
                    <td><?= $r['caminho_imagem'] ? '<a target="_blank" href="'.e($r['caminho_imagem']).'">abrir</a>' : '—' ?></td>
                  </tr>
                <?php endforeach; } else { echo '<tr><td colspan="6">Faça login para ver seu painel.</td></tr>'; } ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- ====== GERAL ====== -->
      <section id="tab-geral" style="display:none;">
        <div class="grid">
          <div class="card" style="grid-column: span 3;">
            <div class="kpi"><div><h3>&nbsp;&nbsp;Predições (todas)</h3><div class="value"><?= number_format($cardsAll['total_predicoes'] ?? 0, 0, ',', '.') ?></div></div><span class="pill">Período</span></div>
          </div>
          <div class="card" style="grid-column: span 3;">
            <div class="kpi"><div><h3>&nbsp;&nbsp;Usuários ativos</h3><div class="value"><?= number_format($cardsAll['usuarios_ativos'] ?? 0, 0, ',', '.') ?></div></div><span class="pill">No filtro</span></div>
          </div>
          <div class="card" style="grid-column: span 3;">
            <div class="kpi"><div><h3>&nbsp;&nbsp;Espécies &nbsp;&nbsp;diferentes</h3><div class="value"><?= number_format($cardsAll['especies_distintas'] ?? 0, 0, ',', '.') ?></div></div><span class="pill">Diversidade</span></div>
          </div>
          <div class="card" style="grid-column: span 3;">
            <div class="kpi"><div><h3>&nbsp;&nbsp;Taxa de acerto &nbsp;&nbsp;(global)</h3><div class="value"><?= ($cardsAll['taxa_acerto']===null? '—' : number_format($cardsAll['taxa_acerto']*100, 1, ',', '.') . '%') ?></div></div><span class="pill">Feedback</span></div>
          </div>

          <div class="card" style="grid-column: span 7;">
            <h3 style="margin:6px 0 12px;">Mapa geral</h3>
            <div id="mapAll" class="map"></div>
            <p class="muted" style="color:var(--muted);margin:8px 0 0;">Pins aparecem apenas quando há coordenadas salvas nas predições.</p>
          </div>
          <div class="card" style="grid-column: span 5;">
            <h3 style="margin:6px 0 12px;">Predições por dia (geral)</h3>
            <canvas id="chartAllDia" height="200"></canvas>
          </div>

          <div class="card" style="grid-column: span 6;">
            <h3 style="margin:6px 0 12px;">Top espécies (geral)</h3>
            <canvas id="chartAllTop" height="220"></canvas>
          </div>
          <div class="card" style="grid-column: span 6;">
            <h3 style="margin:6px 0 12px;">Picos por hora (geral)</h3>
            <canvas id="chartAllHora" height="220"></canvas>
          </div>

          <div class="card" style="grid-column: span 12;">
            <h3 style="margin:6px 0 12px;">Predições recentes</h3>
            <table class="table">
            <thead><tr><th>#</th><th>Data</th><th>Espécie</th><th>Confiança</th><th>Local</th></tr></thead>

              <tbody>
<?php
$stmt = $pdo->prepare("
  SELECT h.id, h.criado_em, h.confianca, h.lat, h.lng,
         COALESCE(p.nome_comum, h.nome_cientifico_predito) AS especie
  FROM historico h
  LEFT JOIN peixes p ON p.id = h.peixe_id
  WHERE DATE(h.criado_em) BETWEEN :ini AND :fim
  ORDER BY h.criado_em DESC
  LIMIT 30
");
$stmt->execute([':ini'=>$ini, ':fim'=>$fim]);
foreach ($stmt as $r): ?>
  <tr>
    <td><?= (int)$r['id'] ?></td>
    <td><?= e($r['criado_em']) ?></td>
    <td><?= e($r['especie'] ?? '—') ?></td>
    <td><?= $r['confianca']!==null ? number_format($r['confianca']*100,1,',','.') . '%' : '—' ?></td>
    <td><?= ($r['lat'] && $r['lng']) ? (e($r['lat']).', '.e($r['lng'])) : '—' ?></td>
  </tr>
<?php endforeach; ?>
</tbody>

            </table>
          </div>
        </div>
      </section>

    </div>
  </section>
</main>

<script>
  // Tabs
// Tabs
const tabBtns = document.querySelectorAll('.tabbar .tab');
const tabMeu  = document.getElementById('tab-meu');
const tabAll  = document.getElementById('tab-geral');

tabBtns.forEach(btn => btn.addEventListener('click', () => {
  tabBtns.forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');

  if (btn.dataset.tab === 'meu') {
    tabMeu.style.display = 'block';
    tabAll.style.display = 'none';
    if (mapMe) setTimeout(() => mapMe.invalidateSize(), 50);
  } else {
    tabMeu.style.display = 'none';
    tabAll.style.display = 'block';
    if (!mapAll) {
      mapAll = buildMap('mapAll', markersAll);  // cria só agora
    } else {
      setTimeout(() => mapAll.invalidateSize(), 50);
    }
  }
}));


  // Dados do PHP
  const serieDiaMe    = <?php echo json_encode($serieDiaMe, JSON_UNESCAPED_UNICODE); ?>;
  const topEspeciesMe = <?php echo json_encode($topEspeciesMe, JSON_UNESCAPED_UNICODE); ?>;
  const porHoraMe     = <?php echo json_encode($porHoraMe, JSON_UNESCAPED_UNICODE); ?>;
  const markersMe     = <?php echo json_encode($markersMe, JSON_UNESCAPED_UNICODE); ?>;

  const serieDiaAll    = <?php echo json_encode($serieDiaAll, JSON_UNESCAPED_UNICODE); ?>;
  const topEspeciesAll = <?php echo json_encode($topEspeciesAll, JSON_UNESCAPED_UNICODE); ?>;
  const porHoraAll     = <?php echo json_encode($porHoraAll, JSON_UNESCAPED_UNICODE); ?>;
  const markersAll     = <?php echo json_encode($markersAll, JSON_UNESCAPED_UNICODE); ?>;
let mapMe  = null;
let mapAll = null;

  // Charts
  const mkLine = (ctx, labels, data) => new Chart(ctx, { type:'line', data:{ labels, datasets:[{ label:'Predições', data, tension:.3, fill:true }] }, options:{ plugins:{ legend:{ display:false } } } });
  const mkBar  = (ctx, labels, data, label) => new Chart(ctx, { type:'bar', data:{ labels, datasets:[{ label:label||'Total', data }] }, options:{ plugins:{ legend:{ display:false } } } });
  const lblDia  = serieDia => serieDia.map(r=>r.dia);
  const datDia  = serieDia => serieDia.map(r=>Number(r.total));
  const lblTop  = arr => arr.map(r=>r.especie||'—');
  const datTop  = arr => arr.map(r=>Number(r.total));
  const lblHora = arr => arr.map(r=>String(r.hora).padStart(2,'0')+':00');
  const datHora = arr => arr.map(r=>Number(r.total));

  mkLine(document.getElementById('chartMeDia'),  lblDia(serieDiaMe),  datDia(serieDiaMe));
  mkBar (document.getElementById('chartMeTop'),  lblTop(topEspeciesMe), datTop(topEspeciesMe), 'Minhas');
  mkBar (document.getElementById('chartMeHora'), lblHora(porHoraMe),    datHora(porHoraMe),   'Minhas');

  mkLine(document.getElementById('chartAllDia'), lblDia(serieDiaAll),  datDia(serieDiaAll));
  mkBar (document.getElementById('chartAllTop'), lblTop(topEspeciesAll), datTop(topEspeciesAll), 'Geral');
  mkBar (document.getElementById('chartAllHora'),lblHora(porHoraAll),    datHora(porHoraAll),   'Geral');

  // Mapas
  function buildMap(elId, markers){
    const map = L.map(elId).setView([-14.2350, -51.9253], 4); // Brasil
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution:'&copy; OpenStreetMap', maxZoom: 18 }).addTo(map);
    const cluster = L.markerClusterGroup();
    const bounds = [];
    (markers||[]).forEach(m => {
      const lat = Number(m.lat), lng = Number(m.lng);
      if(!isFinite(lat) || !isFinite(lng)) return;
      const content = `<b>${m.especie||'Espécie'}</b><br>${m.criado_em||''}` + (m.caminho_imagem? `<br><a href="${m.caminho_imagem}" target="_blank">ver imagem</a>`:'');
      const mk = L.marker([lat, lng]).bindPopup(content);
      cluster.addLayer(mk); bounds.push([lat, lng]);
    });
    map.addLayer(cluster);
    if(bounds.length) map.fitBounds(bounds, { padding: [20,20] });
    return map;
  }
mapMe = buildMap('mapMe', markersMe);  // cria o mapa da aba visível
// mapAll será criado quando a aba “Geral” for aberta

</script>
</body>
</html>
