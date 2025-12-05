<?php
// usuario.php — Página de perfil do usuário logado (FishID)
require_once __DIR__ . '/index.php';
require_login();

/* Evita que a página apareça ao voltar do histórico após logout */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$usuarioId = (int) $_SESSION['usuario_id'];
$pdo = pdo();

/** Formata CPF (aceita com/sem pontuação; se inválido ou vazio, devolve “—”) */
function format_cpf(?string $cpf): string {
  $d = preg_replace('/\D+/', '', (string)$cpf);
  if (!$d || strlen($d) !== 11) return '—';
  return substr($d,0,3).'.'.substr($d,3,3).'.'.substr($d,6,3).'-'.substr($d,9,2);
}

/* Busca usuário + endereço (✅ adicionamos u.cpf) */
$sql = "SELECT u.id, u.nome, u.email, u.telefone, u.cpf, u.criado_em,
               e.cep, e.rua, e.numero, e.complemento, e.cidade, e.estado
        FROM usuarios u
        LEFT JOIN enderecos e ON e.usuario_id = u.id
        WHERE u.id = :id
        LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
$stmt->execute();
$dados = $stmt->fetch();

if (!$dados) {
    http_response_code(404);
    echo 'Usuário não encontrado.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Meu Perfil — Aquapanema</title>
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

  /* Conteúdo */
  .wrap{max-width:1100px;margin:28px auto;padding:0 16px}
  .card{background:rgba(255,255,255,.94);border:1px solid #e6f3f5;border-radius:var(--radius);box-shadow:0 16px 48px rgba(6,42,51,.16);overflow:hidden;margin-bottom:20px}
  .card-header{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;background:linear-gradient(180deg,#ffffff,#f5fdff);border-bottom:1px solid #e9f4f7}
  .card-header h2{margin:0;font-size:1.2rem;color:var(--brand-700)}
  .card-body{padding:18px 22px}

  .grid{display:grid;grid-template-columns:repeat(2,minmax(240px,1fr));gap:16px 22px}
  @media (max-width:780px){.grid{grid-template-columns:1fr}.top-bar{padding:0 12px}}

  .field{display:flex;flex-direction:column;gap:6px}
  .label{font-size:.85rem;color:var(--muted);font-weight:600}
  .value{font-size:1.02rem;color:var(--text);background:#f8fbfc;border:1.5px solid #d7e3ea;padding:12px 14px;border-radius:12px}

  /* Botões no fim da página */
  .page-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px}
  .btn{height:46px;border-radius:14px;border:0;cursor:pointer;font-weight:800;color:#fff;padding:0 16px;display:inline-flex;align-items:center;gap:10px;letter-spacing:.2px;transition:transform .06s,box-shadow .2s,filter .2s;text-decoration:none}
  .btn:hover{transform:translateY(-1px);filter:brightness(1.03)}
  .btn-primary{background:linear-gradient(180deg,var(--brand-500),var(--brand-700) 60%,var(--brand-900));box-shadow:0 14px 34px rgba(14,165,177,.28)}
  .btn-danger{background:linear-gradient(180deg,#ff7e6b,#ff5a43 60%,#d9452e);box-shadow:0 14px 34px rgba(255,94,77,.28)}
</style>
<script>
function confirmarExclusao(){
  if(confirm("Tem certeza que deseja excluir sua conta? Esta ação é permanente.")){
    document.getElementById('delForm').submit();
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
    <a href="dashboard.php" class="active"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
    <a href="pagina_inicial.php"><i class="fa-solid fa-camera"></i> Buscar Por Imagem</a>
    <a href="buscar_nome.php"><i class="fa-solid fa-magnifying-glass"></i> Buscar Peixe por Nome</a>
    <a href="historico.php"><i class="fa-solid fa-clock-rotate-left"></i> Histórico</a>
    <a href="usuario.php"><i class="fa-solid fa-user"></i> Meu Perfil</a>
    <!-- Logout em POST com CSRF -->
    <form class="logout-form" action="logout.php" method="post" style="display:inline">
      <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
      <button type="submit" title="Sair">
        <i class="fa-solid fa-right-from-bracket"></i> Sair
      </button>
    </form>
  </nav>
</header>

<main class="wrap">
  <section class="card">
    <div class="card-header">
      <h2><i class="fa-solid fa-id-card"></i> Meu Perfil</h2>
    </div>
    <div class="card-body">
      <div class="grid">
        <div class="field"><div class="label">Nome</div><div class="value"><?php echo e($dados['nome']); ?></div></div>
        <div class="field"><div class="label">E-mail</div><div class="value"><?php echo e($dados['email']); ?></div></div>
        <div class="field"><div class="label">Telefone</div><div class="value"><?php echo e($dados['telefone']); ?></div></div>

        <!-- ✅ CPF exibido formatado -->
        <div class="field"><div class="label">CPF</div>
          <div class="value"><?php echo e(format_cpf($dados['cpf'] ?? null)); ?></div>
        </div>

        <div class="field"><div class="label">CEP</div><div class="value"><?php echo e($dados['cep'] ?? '—'); ?></div></div>
        <div class="field"><div class="label">Rua</div><div class="value"><?php echo e($dados['rua'] ?? '—'); ?></div></div>
        <div class="field"><div class="label">Número</div><div class="value"><?php echo e($dados['numero'] ?? '—'); ?></div></div>
        <div class="field"><div class="label">Complemento</div><div class="value"><?php echo e($dados['complemento'] ?? '—'); ?></div></div>
        <div class="field"><div class="label">Cidade</div><div class="value"><?php echo e($dados['cidade'] ?? '—'); ?></div></div>
        <div class="field"><div class="label">Estado</div><div class="value"><?php echo e($dados['estado'] ?? '—'); ?></div></div>
      </div>



      <!-- Botões AO FIM da página -->
      <div class="page-actions">
        <a class="btn btn-primary" href="editar_perfil.php"><i class="fa-solid fa-pen"></i> Editar Perfil</a>
        <button class="btn btn-danger" onclick="confirmarExclusao()"><i class="fa-solid fa-trash"></i> Excluir Conta</button>
      </div>
    </div>
  </section>

  <!-- Form oculto para excluir conta (POST + CSRF) -->
  <form id="delForm" method="POST" action="deletar_usuario.php" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
  </form>
</main>
</body>
</html>
