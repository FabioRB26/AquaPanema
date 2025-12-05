<?php
// C:\xampp\htdocs\Identificador_Peixe\login.php
require_once __DIR__ . '/index.php'; // bootstrap (sessão, PDO e helpers)

if (is_logged_in()) {
  header('Location: pagina_inicial.php');
  exit;
}

$csrf = csrf_token();
$contaExcluida = isset($_GET['conta_excluida']) && $_GET['conta_excluida'] === '1';

$erro = '';
$email_value = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!$token || !hash_equals($csrf, $token)) {
    $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
  } else {
    $email = trim($_POST['email'] ?? '');
    $senha = (string)($_POST['password'] ?? '');
    $email_value = $email;

    if ($email === '' || $senha === '') {
      $erro = 'Informe e-mail e senha.';
    } else {
      try {
        $st = pdo()->prepare('SELECT id, senha_hash FROM usuarios WHERE email = :e LIMIT 1');
        $st->execute([':e' => $email]);
        $u = $st->fetch(PDO::FETCH_ASSOC);

        if (!$u || !password_verify($senha, $u['senha_hash'])) {
          $erro = 'E-mail ou senha inválidos.';
        } else {
          // Autenticou: protege contra Session Fixation
          session_regenerate_id(true);
          $_SESSION['usuario_id'] = (int)$u['id'];

          // TODO (opcional): implementar "lembrar de mim" com token persistente

          header('Location: pagina_inicial.php');
          exit;
        }
      } catch (Throwable $e) {
        // Em produção, logar $e->getMessage()
        $erro = 'Não foi possível realizar o login agora. Tente novamente.';
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
<title>Aquapanema • Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Marcellus&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;800&display=swap" rel="stylesheet">
<style>
:root{
  --brand-900:#062a33; --brand-700:#0d5868; --brand-500:#14b8a6; --brand-300:#99f6e4;
  --accent:#ffd166; --accent-2:#ff7e6b; --bg:#e9fbff; --text:#0e1b24; --muted:#5f7582; --error:#c62828; --radius:20px;
  --bg-image:url('images/novaimagem.png');
}
*{box-sizing:border-box}
html,body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;color:var(--text);background:var(--bg)}
.split{min-height:100vh;display:grid;grid-template-columns:1.4fr 1fr}
@media (max-width:900px){.split{grid-template-columns:1fr;grid-template-rows:auto auto}}
.brand{
  position:relative;overflow:hidden;display:flex;align-items:center;justify-content:center;flex-direction:column;
  padding:40px; background:linear-gradient(120deg,rgba(6,42,51,.88),rgba(13,88,104,.65)), var(--bg-image);
  background-size:cover;background-position:center;color:#ecfeff;box-shadow:inset 0 0 120px rgba(0,0,0,.25);
}
.brand .brand-hero{width:min(560px,70%);height:auto;border-radius:16px;box-shadow:0 12px 30px rgba(0,0,0,.25)}
.brand .brand-word{font-family:'Baloo 2',system-ui; font-weight:800; font-size:clamp(28px,3.4vw,40px); color:var(--accent); margin:16px 0 6px}
.brand .brand-caption{max-width:60ch;text-align:center;color:#e6fbff}

.auth{display:flex;align-items:center;justify-content:center;padding:40px}
.auth-card{
  width:100%;max-width:460px;background:rgba(255,255,255,.92);border-radius:var(--radius);
  box-shadow:0 30px 80px rgba(6,42,51,.18);padding:28px;border:1px solid #e6f3f5;backdrop-filter:saturate(120%) blur(6px)
}
.auth-card h2{margin:0 0 8px;font-size:28px}
.auth-card p.subtitle{margin:0 0 14px;color:var(--muted)}
form{display:grid;gap:14px}
.field{display:grid;gap:6px}
.label-row{display:flex;justify-content:space-between;align-items:baseline}
label{font-weight:600;font-size:14px;color:var(--brand-900)}
.input{height:48px;padding:0 14px;border-radius:12px;border:1.5px solid #d7e3ea;background:#f8fbfc;font-size:15px;outline:none;color:var(--text);transition:border-color .2s,box-shadow .2s,background .2s}
.input:focus{border-color:var(--brand-500);box-shadow:0 0 0 5px rgba(20,184,166,.18);background:#fff}
.password-wrap{position:relative}
.toggle-pass{position:absolute;right:10px;top:50%;transform:translateY(-50%);border:0;background:transparent;cursor:pointer;padding:6px}
.toggle-pass svg{width:20px;height:20px;fill:#607786}
.row{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:4px}
.checkbox{display:inline-flex;gap:8px;align-items:center;font-size:14px;color:var(--muted)}
.actions{display:grid;gap:10px;margin-top:6px}
.btn{
  height:50px;border-radius:14px;border:0;cursor:pointer;font-weight:800;
  background:linear-gradient(180deg,#22d3ee,#0ea5b1 60%,#0c7a8a); color:#fff;
  transition:transform .06s, box-shadow .2s, filter .2s; box-shadow:0 14px 34px rgba(14,165,177,.35), inset 0 -2px 0 rgba(255,255,255,.15)
}
.btn:hover{transform:translateY(-1px);box-shadow:0 14px 28px rgba(23,162,184,.32)}
.alert{margin:10px 0 0;padding:10px 12px;border-radius:12px;font-weight:600}
.alert.err{background:#ffecec;border:1px solid #f1c1c1;color:#b62222}
.alert.ok{background:#e6fff6;border:1px solid #b6f0d9;color:#0f8f6a}
.link{color:var(--accent-2);font-weight:700;text-decoration:none}
.link:hover{text-decoration:underline}
</style>
</head>
<body>
<main class="split">
  <!-- Esquerda: logo + texto -->
  <section class="brand" aria-label="Apresentação do sistema Aquapanema">
    <img class="brand-hero" src="Imagens/novaimagem.png" alt="Logo Aquapanema">

  </section>

  <!-- Direita: cartão de login -->
  <section class="auth" aria-label="Acesso ao sistema">
    <div class="auth-card" role="region" aria-labelledby="login-title">
      <h2 id="login-title">Entrar</h2>
      <p class="subtitle">Acesse sua conta para continuar.</p>

      <?php if ($contaExcluida): ?>
        <div class="alert ok">Conta excluída com sucesso. Você pode criar outra quando quiser.</div>
      <?php endif; ?>

      <?php if ($erro): ?>
        <div class="alert err"><?php echo e($erro); ?></div>
      <?php endif; ?>

      <form action="" method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

        <div class="field">
          <div class="label-row"><label for="email">E-mail</label></div>
          <input class="input" type="email" id="email" name="email" placeholder="voce@exemplo.com" autocomplete="email" required value="<?php echo e($email_value); ?>">
        </div>

        <div class="field">
          <div class="label-row">
            <label for="password">Senha</label>
            <a class="link" href="recuperar_senha.php" title="Recuperar senha">Esqueci a senha</a>
          </div>
          <div class="password-wrap">
            <input class="input" type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
            <button type="button" class="toggle-pass" aria-label="Mostrar/ocultar senha" title="Mostrar/ocultar senha">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c-7 0-10 7-10 7s3 7 10 7 10-7 10-7-3-7-10-7Zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10Z"/></svg>
            </button>
          </div>
        </div>

        <div class="row">
          <label class="checkbox"><input type="checkbox" name="remember"> Manter conectado</label>
          <a class="link" href="registrar.php" title="Criar conta">Criar conta</a>
        </div>

        <div class="actions">
          <button class="btn" type="submit">Fazer login</button>
        </div>
      </form>
    </div>
  </section>
</main>

<script>
// Mostrar/Ocultar senha
const toggleBtn = document.querySelector('.toggle-pass');
const passInput = document.getElementById('password');
toggleBtn?.addEventListener('click', () => {
  const isPwd = passInput.type === 'password';
  passInput.type = isPwd ? 'text' : 'password';
  toggleBtn.setAttribute('aria-pressed', String(isPwd));
});
</script>
</body>
</html>
