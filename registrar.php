<?php
// registrar.php — Criação de conta (usuário + endereço) com CPF (coluna cpf CHAR(11) só dígitos)
require_once __DIR__ . '/index.php';

// Se já estiver logado, manda para a área logada
if (!empty($_SESSION['usuario_id'])) {
  header('Location: usuario.php'); exit;
}

// CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$erro = '';
$ok   = '';

// manter valores do form em caso de erro
$val = [
  'nome' => '', 'email' => '', 'telefone' => '',
  'cpf'  => '',
  'cep' => '', 'rua' => '', 'numero' => '', 'complemento' => '',
  'cidade' => '', 'estado' => '',
];

/* helper seguro para escapar */
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

/* Funções auxiliares p/ CPF */
function so_digitos(string $s): string {
  return preg_replace('/\D+/', '', $s);
}
function validar_cpf(string $cpf_raw): bool {
  $cpf = so_digitos($cpf_raw);
  if (strlen($cpf) !== 11) return false;
  if (preg_match('/^(\d)\1{10}$/', $cpf)) return false; // todos iguais

  // dígito 10
  $sum = 0;
  for ($i=0, $w=10; $i<9; $i++, $w--) $sum += (int)$cpf[$i] * $w;
  $rest = $sum % 11; $d10 = ($rest < 2) ? 0 : 11 - $rest;
  if ((int)$cpf[9] !== $d10) return false;

  // dígito 11
  $sum = 0;
  for ($i=0, $w=11; $i<10; $i++, $w--) $sum += (int)$cpf[$i] * $w;
  $rest = $sum % 11; $d11 = ($rest < 2) ? 0 : 11 - $rest;
  if ((int)$cpf[10] !== $d11) return false;

  return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!$token || !hash_equals($csrf, $token)) {
    $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
  } else {
    // Coletar/limpar
    $val['nome']     = trim($_POST['nome'] ?? '');
    $val['email']    = trim($_POST['email'] ?? '');
    $val['telefone'] = trim($_POST['telefone'] ?? '');
    $val['cpf']      = trim($_POST['cpf'] ?? ''); // pode vir com máscara do input; backend normaliza
    $senha           = $_POST['senha'] ?? '';
    $confirma        = $_POST['confirma'] ?? '';

    $val['cep']    = trim($_POST['cep'] ?? '');
    $val['rua']    = trim($_POST['rua'] ?? '');
    $val['numero'] = trim($_POST['numero'] ?? '');
    $val['complemento'] = trim($_POST['complemento'] ?? '');
    $val['cidade'] = trim($_POST['cidade'] ?? '');
    $val['estado'] = strtoupper(substr(trim($_POST['estado'] ?? ''), 0, 2));

    // Normaliza CPF para 11 dígitos (o que será salvo)
    $cpf_num = so_digitos($val['cpf']);

    // Regras simples
    if ($val['nome'] === '' || $val['email'] === '' || $senha === '' || $confirma === '') {
      $erro = 'Preencha nome, e-mail e senha.';
    } elseif (!filter_var($val['email'], FILTER_VALIDATE_EMAIL)) {
      $erro = 'E-mail inválido.';
    } elseif ($val['cpf'] === '') {
      $erro = 'Informe o CPF.';
    } elseif (!validar_cpf($val['cpf'])) {
      $erro = 'CPF inválido.';
    } elseif (strlen($senha) < 8) {
      $erro = 'A senha deve ter pelo menos 8 caracteres.';
    } elseif ($senha !== $confirma) {
      $erro = 'A confirmação de senha não confere.';
    } elseif ($val['cep'] === '' || $val['rua'] === '' || $val['numero'] === '' || $val['cidade'] === '' || $val['estado'] === '') {
      $erro = 'Preencha os campos obrigatórios do endereço (CEP, Rua, Número, Cidade e UF).';
    } else {
      // Inserir em transação
      $pdo = pdo();
      try {
        $pdo->beginTransaction();

        // Verifica e-mail único
        $st = $pdo->prepare("SELECT 1 FROM usuarios WHERE email = :e LIMIT 1");
        $st->execute([':e'=>$val['email']]);
        if ($st->fetch()) {
          throw new Exception('E-mail já cadastrado. Use outro.');
        }

        // Verifica CPF único (a coluna guarda só dígitos)
        $st = $pdo->prepare("SELECT 1 FROM usuarios WHERE cpf = :c LIMIT 1");
        $st->execute([':c'=>$cpf_num]);
        if ($st->fetch()) {
          throw new Exception('CPF já cadastrado. Use outro.');
        }

        $hash = password_hash($senha, PASSWORD_DEFAULT);

        // Insere usuário salvando o CPF APENAS com dígitos
        $st = $pdo->prepare("
          INSERT INTO usuarios (nome, email, telefone, cpf, senha_hash)
          VALUES (:n, :e, :t, :c, :h)
        ");
        $st->execute([
          ':n'=>$val['nome'],
          ':e'=>$val['email'],
          ':t'=>$val['telefone'] !== '' ? $val['telefone'] : null,
          ':c'=>$cpf_num, // <<-- só dígitos
          ':h'=>$hash
        ]);
        $userId = (int)$pdo->lastInsertId();

        // Endereço
        $st = $pdo->prepare("
          INSERT INTO enderecos (usuario_id, cep, rua, numero, complemento, cidade, estado)
          VALUES (:id, :cep, :rua, :num, :comp, :cid, :uf)
        ");
        $st->execute([
          ':id'=>$userId,
          ':cep'=>$val['cep'],
          ':rua'=>$val['rua'],
          ':num'=>$val['numero'],
          ':comp'=>($val['complemento'] !== '' ? $val['complemento'] : null),
          ':cid'=>$val['cidade'],
          ':uf'=>$val['estado']
        ]);

        $pdo->commit();

        // Login automático
        $_SESSION['usuario_id'] = $userId;
        header('Location: usuario.php');
        exit;

      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Mensagem amigável (email/CPF duplicado ou erro genérico)
        $msg = $e->getMessage();
        if (stripos($msg, 'E-mail já cadastrado') !== false) {
          $erro = $msg;
        } elseif (stripos($msg, 'CPF já cadastrado') !== false) {
          $erro = $msg;
        } elseif ($e instanceof PDOException && $e->getCode() === '23000') {
          // UNIQUE do banco
          $erro = 'E-mail ou CPF já cadastrado.';
        } else {
          $erro = 'Não foi possível concluir o cadastro agora. Tente novamente.';
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Aquapanema • Criar conta</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Marcellus&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;800&display=swap" rel="stylesheet">
<style>
:root{
  --brand-900:#062a33; --brand-700:#0d5868; --brand-500:#14b8a6; --brand-300:#99f6e4;
  --accent:#ffd166; --accent-2:#ff7e6b; --bg:#e9fbff; --text:#0e1b24; --muted:#5f7582; --error:#c62828; --radius:20px;
  --bg-image:url('images/aquapanema-bg.jpg');
}
*{box-sizing:border-box}
html,body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;color:var(--text);background:var(--bg)}
.split{min-height:100vh;display:grid;grid-template-columns:1.2fr 1fr}
@media (max-width:980px){.split{grid-template-columns:1fr;grid-template-rows:auto auto}}
.brand{
  position:relative;overflow:hidden;display:flex;align-items:center;justify-content:center;flex-direction:column;
  padding:40px; background:linear-gradient(120deg,rgba(6,42,51,.88),rgba(13,88,104,.65)), var(--bg-image);
  background-size:cover;background-position:center;color:#ecfeff;box-shadow:inset 0 0 120px rgba(0,0,0,.25);
}
.brand .brand-hero{width:min(560px,70%);height:auto;border-radius:16px;box-shadow:0 12px 30px rgba(0,0,0,.25)}
.brand .brand-word{font-family:'Baloo 2',system-ui;font-weight:800;font-size:clamp(28px,3.4vw,40px);color:var(--accent);margin:16px 0 6px}
.brand .brand-caption{max-width:60ch;text-align:center;color:#e6fbff}
.auth{display:flex;align-items:center;justify-content:center;padding:40px}
.auth-card{
  width:100%;max-width:720px;background:rgba(255,255,255,.94);border-radius:var(--radius);
  box-shadow:0 30px 80px rgba(6,42,51,.18);padding:28px;border:1px solid #e6f3f5;backdrop-filter:saturate(120%) blur(6px)
}
.auth-card h2{margin:0 0 8px;font-size:28px}
.auth-card p.subtitle{margin:0 0 14px;color:var(--muted)}
.alert{margin:10px 0 0;padding:10px 12px;border-radius:12px;font-weight:600}
.alert.err{background:#ffecec;border:1px solid #f1c1c1;color:#b62222}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:16px 18px}
@media (max-width:680px){.form-grid{grid-template-columns:1fr}}
.field{display:flex;flex-direction:column;gap:6px}
label{font-weight:600;font-size:14px;color:var(--brand-900)}
.input{
  height:46px;padding:0 12px;border-radius:12px;border:1.5px solid #d7e3ea;background:#f8fbfc;
  font-size:15px;outline:none;color:var(--text);transition:border-color .2s,box-shadow .2s,background .2s
}
.input:focus{border-color:var(--brand-500);box-shadow:0 0 0 5px rgba(20,184,166,.18);background:#fff}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
.btn{
  height:48px;border-radius:14px;border:0;cursor:pointer;font-weight:800;color:#fff;padding:0 16px;
  background:linear-gradient(180deg,var(--brand-500),var(--brand-700) 60%,var(--brand-900));
  box-shadow:0 14px 34px rgba(14,165,177,.35), inset 0 -2px 0 rgba(255,255,255,.15)
}
.link{color:var(--accent-2);text-decoration:none;font-weight:700}
.link:hover{text-decoration:underline}
</style>
<script>
// Máscara no input apenas para UX; backend salva só dígitos.
function mascaraCPF(el){
  const d = (el.value || '').replace(/\D+/g,'').slice(0,11);
  let out = d;
  if (d.length > 9) out = d.replace(/^(\d{3})(\d{3})(\d{3})(\d{0,2}).*/, '$1.$2.$3-$4');
  else if (d.length > 6) out = d.replace(/^(\d{3})(\d{3})(\d{0,3}).*/, '$1.$2.$3');
  else if (d.length > 3) out = d.replace(/^(\d{3})(\d{0,3}).*/, '$1.$2');
  el.value = out;
}
</script>
</head>
<body>
<main class="split">
  <section class="brand" aria-label="Apresentação">
    <img class="brand-hero" src="Imagens/novaimagem.png" alt="Logo Aquapanema">
    <div class="brand-word">Aquapanema</div>
    <p class="brand-caption">Crie sua conta para identificar espécies de peixes e salvar seu histórico.</p>
  </section>

  <section class="auth" aria-label="Criar conta">
    <div class="auth-card">
      <h2>Criar conta</h2>
      <p class="subtitle">Preencha seus dados de acesso e endereço.</p>

      <?php if ($erro): ?><div class="alert err"><?php echo e($erro); ?></div><?php endif; ?>

      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

        <h3 style="margin:10px 0 6px;color:#0d5868;font-size:1.05rem">Dados do Usuário</h3>
        <div class="form-grid">
          <div class="field">
            <label for="nome">Nome *</label>
            <input class="input" type="text" id="nome" name="nome" required value="<?php echo e($val['nome']); ?>">
          </div>
          <div class="field">
            <label for="email">E-mail *</label>
            <input class="input" type="email" id="email" name="email" required value="<?php echo e($val['email']); ?>">
          </div>

          <!-- CPF -->
          <div class="field">
            <label for="cpf">CPF *</label>
            <input class="input" type="text" id="cpf" name="cpf"
                   inputmode="numeric" maxlength="14" placeholder="000.000.000-00"
                   required value="<?php echo e($val['cpf']); ?>"
                   oninput="mascaraCPF(this)">
          </div>

          <div class="field">
            <label for="telefone">Telefone</label>
            <input class="input" type="tel" id="telefone" name="telefone" value="<?php echo e($val['telefone']); ?>">
          </div>

          <div class="field">
            <label for="senha">Senha *</label>
            <input class="input" type="password" id="senha" name="senha" required minlength="8" placeholder="Mínimo 8 caracteres">
          </div>
          <div class="field">
            <label for="confirma">Confirmar senha *</label>
            <input class="input" type="password" id="confirma" name="confirma" required minlength="8">
          </div>
        </div>

        <h3 style="margin:18px 0 6px;color:#0d5868;font-size:1.05rem">Endereço</h3>
        <div class="form-grid">
          <div class="field">
            <label for="cep">CEP *</label>
            <input class="input" type="text" id="cep" name="cep" required value="<?php echo e($val['cep']); ?>">
          </div>
          <div class="field">
            <label for="rua">Rua *</label>
            <input class="input" type="text" id="rua" name="rua" required value="<?php echo e($val['rua']); ?>">
          </div>
          <div class="field">
            <label for="numero">Número *</label>
            <input class="input" type="text" id="numero" name="numero" required value="<?php echo e($val['numero']); ?>">
          </div>
          <div class="field">
            <label for="complemento">Complemento</label>
            <input class="input" type="text" id="complemento" name="complemento" value="<?php echo e($val['complemento']); ?>">
          </div>
          <div class="field">
            <label for="cidade">Cidade *</label>
            <input class="input" type="text" id="cidade" name="cidade" required value="<?php echo e($val['cidade']); ?>">
          </div>
          <div class="field">
            <label for="estado">UF *</label>
            <input class="input" type="text" id="estado" name="estado" maxlength="2" required value="<?php echo e($val['estado']); ?>" oninput="this.value=this.value.toUpperCase();">
          </div>
        </div>

        <div class="actions">
          <button class="btn" type="submit">Criar conta</button>
          <a class="link" href="login.php">Já tenho conta</a>
        </div>
      </form>
    </div>
  </section>
</main>

</body>
</html>
