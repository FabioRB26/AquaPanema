<?php
// pagina_inicial.php — Captura imagem + geolocalização e envia para predizer.php
require_once __DIR__ . '/index.php';

$csrf     = csrf_token();
$isLogged = is_logged_in();

/* helper local para escapar, caso não exista no index.php */
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Aquapanema • Identificar por Imagem</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
  :root{
    --brand-900:#062a33; --brand-700:#0d5868; --brand-500:#14b8a6; --brand-300:#99f6e4;
    --accent:#ffd166; --accent-2:#ff7e6b; --bg:#e9fbff; --text:#0e1b24; --muted:#5f7582; --error:#c62828; --radius:20px;
  }
  *{box-sizing:border-box}
  html,body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;background:var(--bg);color:var(--text)}

  /* Top bar — mesmo padrão das outras páginas */
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

  /* Conteúdo */
  .wrap{max-width:1100px;margin:28px auto;padding:0 16px}
  .card{background:rgba(255,255,255,.94);border:1px solid #e6f3f5;border-radius:var(--radius);box-shadow:0 16px 48px rgba(6,42,51,.16);overflow:hidden;margin-bottom:20px}
  .card-header{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;background:linear-gradient(180deg,#ffffff,#f5fdff);border-bottom:1px solid #e9f4f7}
  .card-header h2{margin:0;font-size:1.2rem;color:var(--brand-700)}
  .card-body{padding:18px 22px}
  .subtitle{margin:0 0 10px;color:var(--muted);font-size:.95rem}

  .drop{border:2px dashed #bcdde6;border-radius:16px;padding:14px;text-align:center;background:#f8fbfc;cursor:pointer}
  .drop:hover{background:#ffffff}
  .file{display:none}
  .btn{height:46px;border-radius:14px;border:0;cursor:pointer;font-weight:800;color:#fff;padding:0 16px;display:inline-flex;align-items:center;gap:10px;letter-spacing:.2px;transition:transform .06s,box-shadow .2s,filter .2s;text-decoration:none}
  .btn:hover{transform:translateY(-1px);filter:brightness(1.03)}
  .btn-primary{background:linear-gradient(180deg,var(--brand-500),var(--brand-700) 60%,var(--brand-900));box-shadow:0 14px 34px rgba(14,165,177,.28)}

  .geo-status{margin:10px 0 0;color:var(--muted);font-size:.9rem}
  .geo-status.ok{color:#0f8f6a}
  .geo-status.err{color:#b62222}

  .preview{margin-top:12px}
  .preview img{max-width:100%;border-radius:12px;border:1px solid #e6f3f5;box-shadow:0 6px 16px rgba(0,0,0,.12)}

  @media (max-width:780px){.top-bar{padding:0 12px}}
</style>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    // Drag & Drop / clique
    const dz = document.getElementById('dropzone');
    const input = document.getElementById('fileinput');
    const dzText = document.getElementById('dz-text');

    function setFileName() {
      if (input.files && input.files[0] && dzText) dzText.textContent = 'Selecionado: ' + input.files[0].name;
    }

    if (dz && input) {
      ['dragenter','dragover'].forEach(evt => dz.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); dz.style.background = '#fff'; }));
      ['dragleave','drop'].forEach(evt => dz.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); dz.style.background = '#f8fbfc'; }));
      dz.addEventListener('drop', e => { if (e.dataTransfer?.files?.length) { input.files = e.dataTransfer.files; setFileName(); } });
      dz.addEventListener('click', () => input.click());
      input.addEventListener('change', setFileName);
    }

    // Geolocalização
    const latEl = document.getElementById('lat');
    const lngEl = document.getElementById('lng');
    const accEl = document.getElementById('acc');
    const geoMsg = document.getElementById('geo-msg');

    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        pos => {
          latEl.value = String(pos.coords.latitude);
          lngEl.value = String(pos.coords.longitude);
          accEl.value = String(pos.coords.accuracy || '');
          if (geoMsg) { geoMsg.textContent = 'Localização capturada ✓'; geoMsg.classList.add('ok'); }
        },
        err => {
          if (geoMsg) { geoMsg.textContent = 'Não foi possível obter a localização (tudo bem, dá para continuar).'; geoMsg.classList.add('err'); }
          console.log('Geo off:', err?.message);
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
      );
    } else {
      if (geoMsg) { geoMsg.textContent = 'Seu navegador não oferece geolocalização, mas é possível enviar a imagem normalmente.'; }
    }
  });
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
    <a href="pagina_inicial.php" class="active"><i class="fa-solid fa-camera"></i> Buscar Por Imagem</a>
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
      <h2><i class="fa-solid fa-camera"></i> Identificar por Imagem</h2>
    </div>
    <div class="card-body">
      <p class="subtitle">
        Selecione uma foto do peixe (JPG, PNG, WEBP ou GIF, até 8MB). No celular, você pode usar a câmera.
      </p>

      <!-- Form centraliza no predizer.php: ele chama a IA e insere no histórico -->
      <form method="POST" action="predizer.php" enctype="multipart/form-data" autocomplete="off" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

        <!-- geolocalização (preenchidos via JS) -->
        <input type="hidden" name="lat" id="lat">
        <input type="hidden" name="lng" id="lng">
        <input type="hidden" name="acc" id="acc">

        <div id="dropzone" class="drop" role="button" aria-label="Arraste e solte uma imagem aqui ou clique para selecionar">
          <i class="fa-regular fa-image"></i>
          <span id="dz-text">Arraste a imagem aqui ou clique para selecionar.</span>
        </div>

        <input id="fileinput" class="file" type="file" name="imagem" accept="image/*" capture="environment" required>

        <div class="geo-status" id="geo-msg">Tentando obter sua localização…</div>

        <div style="margin-top:10px">
          <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-upload"></i> Enviar para Predição
          </button>
        </div>
      </form>

      <!-- Pré-visualização opcional no cliente (se quiser, dá pra implementar facilmente) -->
    </div>
  </section>
</main>

</body>
</html>
