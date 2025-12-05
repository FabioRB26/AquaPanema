<?php
// editar_perfil.php — Editar (Usuário + Endereço + Senha opcional) em um único form
require_once __DIR__ . '/index.php';
require_login();

$pdo = pdo();
$usuarioId = (int) $_SESSION['usuario_id'];

// CSRF
$csrf = csrf_token();

$ok  = '';
$err = '';

// Helpers de CPF (normaliza e valida)
function so_digitos(string $s): string { return preg_replace('/\D+/', '', $s); }

function validar_cpf(string $cpf_raw): bool {
  $cpf = so_digitos($cpf_raw);
  if (strlen($cpf) !== 11) return false;
  if (preg_match('/^(\d)\1{10}$/', $cpf)) return false; // todos iguais
  // dígito 10
  $sum = 0; for ($i=0,$w=10; $i<9; $i++,$w--) $sum += (int)$cpf[$i] * $w;
  $rest = $sum % 11; $d10 = ($rest < 2) ? 0 : 11 - $rest;
  if ((int)$cpf[9] !== $d10) return false;
  // dígito 11
  $sum = 0; for ($i=0,$w=11; $i<10; $i++,$w--) $sum += (int)$cpf[$i] * $w;
  $rest = $sum % 11; $d11 = ($rest < 2) ? 0 : 11 - $rest;
  if ((int)$cpf[10] !== $d11) return false;
  return true;
}

// POST: processa tudo de uma vez
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!$token || !hash_equals($csrf, $token)) {
    $err = 'Sessão expirada. Recarregue a página e tente novamente.';
  } else {
    // Coleta
    $nome       = trim($_POST['nome'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $telefone   = trim($_POST['telefone'] ?? '');

    $cpf        = trim($_POST['cpf'] ?? '');      // CPF (pode vir formatado)
    $cpf_num    = so_digitos($cpf);               // normaliza para dígitos
    $changeCpf  = ($cpf !== '');                  // só altera CPF se usuário preencher

    $cep        = trim($_POST['cep'] ?? '');
    $rua        = trim($_POST['rua'] ?? '');
    $numero     = trim($_POST['numero'] ?? '');
    $complemento= trim($_POST['complemento'] ?? '');
    $cidade     = trim($_POST['cidade'] ?? '');
    $estado     = strtoupper(substr(trim($_POST['estado'] ?? ''), 0, 2));

    $senha_atual= (string)($_POST['senha_atual'] ?? '');
    $nova_senha = (string)($_POST['nova_senha'] ?? '');
    $conf_senha = (string)($_POST['conf_senha'] ?? '');

    // Validações básicas
    if ($nome === '' || $email === '') {
      $err = 'Preencha ao menos nome e e-mail.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $err = 'E-mail inválido.';
    } elseif ($changeCpf && !validar_cpf($cpf)) {
      $err = 'CPF inválido.';
    } else {
      // Se qualquer campo de senha foi preenchido, validar as 3
      $trocarSenha = ($senha_atual !== '' || $nova_senha !== '' || $conf_senha !== '');
      if ($trocarSenha) {
        if ($senha_atual === '' || $nova_senha === '' || $conf_senha === '') {
          $err = 'Para alterar a senha, preencha todos os campos de senha.';
        } elseif ($nova_senha !== $conf_senha) {
          $err = 'A confirmação da senha não confere.';
        } elseif (strlen($nova_senha) < 8) {
          $err = 'A nova senha deve ter pelo menos 8 caracteres.';
        }
      }
    }

    if ($err === '') {
      try {
        $pdo->beginTransaction();

        // Se for trocar senha, verificar a atual
        if (!empty($trocarSenha)) {
          $st = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = :id');
          $st->execute([':id' => $usuarioId]);
          $row = $st->fetch();
          if (!$row || !password_verify($senha_atual, $row['senha_hash'])) {
            throw new Exception('Senha atual incorreta.');
          }
        }

        // Verifica CPF único (apenas se usuário informou CPF)
        if ($changeCpf) {
          $st = $pdo->prepare('SELECT 1 FROM usuarios WHERE cpf = :c AND id <> :id LIMIT 1');
          $st->execute([':c'=>$cpf_num, ':id'=>$usuarioId]);
          if ($st->fetch()) {
            throw new Exception('CPF já cadastrado.');
          }
        }

        // Atualiza usuário (com ou sem senha) — com branch para CPF
        if (!empty($trocarSenha)) {
          $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
          if ($changeCpf) {
            $st = $pdo->prepare('UPDATE usuarios SET nome=:n, email=:e, telefone=:t, cpf=:c, senha_hash=:h WHERE id=:id');
            $st->execute([':n'=>$nome, ':e'=>$email, ':t'=>$telefone, ':c'=>$cpf_num, ':h'=>$hash, ':id'=>$usuarioId]);
          } else {
            $st = $pdo->prepare('UPDATE usuarios SET nome=:n, email=:e, telefone=:t, senha_hash=:h WHERE id=:id');
            $st->execute([':n'=>$nome, ':e'=>$email, ':t'=>$telefone, ':h'=>$hash, ':id'=>$usuarioId]);
          }
        } else {
          if ($changeCpf) {
            $st = $pdo->prepare('UPDATE usuarios SET nome=:n, email=:e, telefone=:t, cpf=:c WHERE id=:id');
            $st->execute([':n'=>$nome, ':e'=>$email, ':t'=>$telefone, ':c'=>$cpf_num, ':id'=>$usuarioId]);
          } else {
            $st = $pdo->prepare('UPDATE usuarios SET nome=:n, email=:e, telefone=:t WHERE id=:id');
            $st->execute([':n'=>$nome, ':e'=>$email, ':t'=>$telefone, ':id'=>$usuarioId]);
          }
        }

        // Upsert do endereço (assumindo usuario_id como PK/UNIQUE em enderecos)
        $sqlEnd = "INSERT INTO enderecos (usuario_id, cep, rua, numero, complemento, cidade, estado)
                   VALUES (:id,:cep,:rua,:num,:comp,:cid,:uf)
                   ON DUPLICATE KEY UPDATE
                     cep=VALUES(cep), rua=VALUES(rua), numero=VALUES(numero),
                     complemento=VALUES(complemento), cidade=VALUES(cidade), estado=VALUES(estado)";
        $st = $pdo->prepare($sqlEnd);
        $st->execute([
          ':id'=>$usuarioId, ':cep'=>$cep, ':rua'=>$rua, ':num'=>$numero,
          ':comp'=>$complemento, ':cid'=>$cidade, ':uf'=>$estado
        ]);

        $pdo->commit();

        // Flash e redireciona
        $_SESSION['flash_ok'] = 'Alterações salvas com sucesso.';
        header('Location: usuario.php');
        exit;

      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // 23000 = violação de unicidade (email/cpf duplicado)
        if ($e instanceof PDOException && $e->getCode()==='23000') {
          $err = 'E-mail ou CPF já cadastrado.';
        } else {
          $err = $e->getMessage() ?: 'Não foi possível salvar as alterações agora.';
        }
      }
    }
  }
}

// Carrega dados atuais para exibir no form (inclui u.cpf)
$st = $pdo->prepare("SELECT u.nome, u.email, u.telefone, u.cpf, u.criado_em,
                            e.cep, e.rua, e.numero, e.complemento, e.cidade, e.estado
                     FROM usuarios u
                     LEFT JOIN enderecos e ON e.usuario_id = u.id
                     WHERE u.id = :id LIMIT 1");
$st->execute([':id'=>$usuarioId]);
$dados = $st->fetch();
if (!$dados) {
  http_response_code(404); echo 'Usuário não encontrado.'; exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editar Perfil — Aquapanema</title>
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

  /* Top bar — igual ao perfil do usuário */
  .top-bar{height:10vh;min-height:64px;background:linear-gradient(90deg,var(--brand-900),var(--brand-700));color:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 24px;box-shadow:0 6px 16px rgba(0,0,0,.18)}
  .brand{display:flex;align-items:center;gap:12px}
  .brand img{height:44px;width:44px;border-radius:50%;border:2px solid #fff;object-fit:cover}
  .brand h1{margin:0;font-size:1.25rem;color:var(--accent)}
  nav.actions{display:flex;align-items:center;gap:14px}
  nav.actions a, nav.actions button{color:#fff;text-decoration:none;font-weight:700;display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:12px}
  nav.actions a:hover, nav.actions button:hover{background:rgba(255,255,255,.15)}
  .logout-form{display:inline;margin:0}
  .logout-form button{background:none;border:0;cursor:pointer;font:inherit}

  /* Conteúdo — mesmos cards do perfil */
  .wrap{max-width:1100px;margin:28px auto;padding:0 16px}
  .card{background:rgba(255,255,255,.94);border:1px solid #e6f3f5;border-radius:var(--radius);box-shadow:0 16px 48px rgba(6,42,51,.16);overflow:hidden;margin-bottom:20px}
  .card-header{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;background:linear-gradient(180deg,#ffffff,#f5fdff);border-bottom:1px solid #e9f4f7}
  .card-header h2{margin:0;font-size:1.2rem;color:var(--brand-700)}
  .card-body{padding:18px 22px}

  .form-grid{display:grid;grid-template-columns:repeat(2,minmax(240px,1fr));gap:16px 22px}
  @media (max-width:780px){.form-grid{grid-template-columns:1fr}.top-bar{padding:0 12px}}

  .field{display:flex;flex-direction:column;gap:6px}
  label{font-size:.85rem;color:var(--muted);font-weight:600}
  .input{font-size:1.02rem;color:var(--text);background:#f8fbfc;border:1.5px solid #d7e3ea;padding:12px 14px;border-radius:12px;outline:none}
  .input:focus{border-color:var(--brand-500);box-shadow:0 0 0 5px rgba(20,184,166,.18);background:#fff}

  .actions{display:flex;justify-content:flex-end;margin-top:10px}
  .btn{height:46px;border-radius:14px;border:0;cursor:pointer;font-weight:800;color:#fff;padding:0 16px;display:inline-flex;align-items:center;gap:10px;letter-spacing:.2px;transition:transform .06s,box-shadow .2s,filter .2s;text-decoration:none}
  .btn:hover{transform:translateY(-1px);filter:brightness(1.03)}
  .btn-primary{background:linear-gradient(180deg,var(--brand-500),var(--brand-700) 60%,var(--brand-900));box-shadow:0 14px 34px rgba(14,165,177,.28)}
  .alert{margin:10px 0 0;padding:10px 12px;border-radius:12px;font-weight:600}
  .alert.ok{background:#e6fff6;border:1px solid #b6f0d9;color:#0f8f6a}
  .alert.err{background:#ffecec;border:1px solid #f1c1c1;color:#b62222}
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
    <form class="logout-form" action="logout.php" method="post">
      <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
      <button type="submit" title="Sair"><i class="fa-solid fa-right-from-bracket"></i> Sair</button>
    </form>
  </nav>
</header>

<main class="wrap">
  <section class="card">
    <div class="card-header">
      <h2><i class="fa-solid fa-pen-to-square"></i> Editar Perfil</h2>
    </div>
    <div class="card-body">
      <?php if ($ok): ?><div class="alert ok"><?php echo e($ok); ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert err"><?php echo e($err); ?></div><?php endif; ?>

      <form method="POST" class="form-grid" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

        <!-- Dados pessoais -->
        <div class="field"><label>Nome</label>
          <input class="input" type="text" name="nome" required value="<?php echo e($dados['nome']); ?>">
        </div>
        <div class="field"><label>E-mail</label>
          <input class="input" type="email" name="email" required value="<?php echo e($dados['email']); ?>">
        </div>
        <div class="field"><label>Telefone</label>
          <input class="input" type="text" name="telefone" value="<?php echo e($dados['telefone']); ?>">
        </div>

        <!-- CPF (opcional; se preenchido, valida e atualiza) -->
        <div class="field"><label>CPF</label>
          <input class="input" type="text" name="cpf"
                 value="<?php echo e($dados['cpf'] ?? ''); ?>"
                 placeholder="000.000.000-00" inputmode="numeric" maxlength="14">
        </div>

        <!-- Endereço -->
        <div class="field"><label>CEP</label>
          <input class="input" type="text" id="cep" name="cep" value="<?php echo e($dados['cep'] ?? ''); ?>" placeholder="00000-000">
        </div>
        <div class="field"><label>Rua</label>
          <input class="input" type="text" id="rua" name="rua" value="<?php echo e($dados['rua'] ?? ''); ?>">
        </div>
        <div class="field"><label>Número</label>
          <input class="input" type="text" id="numero" name="numero" value="<?php echo e($dados['numero'] ?? ''); ?>">
        </div>
        <div class="field"><label>Complemento</label>
          <input class="input" type="text" id="complemento" name="complemento" value="<?php echo e($dados['complemento'] ?? ''); ?>">
        </div>
        <div class="field"><label>Cidade</label>
          <input class="input" type="text" id="cidade" name="cidade" value="<?php echo e($dados['cidade'] ?? ''); ?>">
        </div>
        <div class="field"><label>Estado (UF)</label>
          <input class="input" type="text" id="estado" name="estado" maxlength="2" value="<?php echo e($dados['estado'] ?? ''); ?>" oninput="this.value=this.value.toUpperCase();">
        </div>

        <!-- Senha (opcional) -->
        <div class="field" style="grid-column:1/-1"><label>Senha atual (preencha para mudar a senha)</label>
          <input class="input" type="password" name="senha_atual" autocomplete="current-password">
        </div>
        <div class="field"><label>Nova senha</label>
          <input class="input" type="password" name="nova_senha" autocomplete="new-password" minlength="8">
        </div>
        <div class="field"><label>Confirmar nova senha</label>
          <input class="input" type="password" name="conf_senha" autocomplete="new-password" minlength="8">
        </div>

        <div class="actions" style="grid-column:1/-1">
          <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar alterações</button>
        </div>
      </form>
    </div>
  </section>
</main>

<!-- ViaCEP opcional (preenchimento automático de endereço) -->
<script>
(function(){
  const cep = document.getElementById('cep');
  if (!cep) return;
  const rua = document.getElementById('rua'),
        cidade = document.getElementById('cidade'),
        estado = document.getElementById('estado'),
        comp = document.getElementById('complemento');

  function maskCep(v){ v=v.replace(/\D/g,'').slice(0,8); return v.length>5? v.slice(0,5)+'-'+v.slice(5):v; }
  cep.addEventListener('input', ()=> cep.value = maskCep(cep.value));

  async function lookup(){
    const d = cep.value.replace(/\D/g,''); if (d.length!==8) return;
    try{
      const res = await fetch('https://viacep.com.br/ws/'+d+'/json/'); if(!res.ok) throw 0;
      const j = await res.json(); if (j.erro) return;
      rua.value = j.logradouro||''; cidade.value = j.localidade||''; estado.value = (j.uf||'').toUpperCase();
      if(j.complemento && !comp.value) comp.value=j.complemento;
    }catch(e){}
  }
  cep.addEventListener('blur', lookup);
  cep.addEventListener('keyup', ()=> { if (cep.value.replace(/\D/g,'').length===8) lookup(); });
})();
</script>
</body>
</html>
