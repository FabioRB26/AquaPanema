<?php
// C:\xampp\htdocs\Identificador_Peixe\recuperar_senha.php
require_once __DIR__ . '/index.php'; // pdo(), csrf_token(), e()

$csrf = csrf_token();
$ok   = false;
$erro = '';

$email_value = '';
$cpf_value = '';
$tel_value = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $tok = $_POST['csrf_token'] ?? '';
  if (!$tok || !hash_equals($csrf, $tok)) {
    $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
  } else {
    $email = trim($_POST['email'] ?? '');
    $cpf   = preg_replace('/\D+/', '', $_POST['cpf'] ?? '');
    $tel   = preg_replace('/\D+/', '', $_POST['telefone'] ?? '');
    $new   = (string)($_POST['password'] ?? '');
    $rep   = (string)($_POST['password_confirm'] ?? '');

    $email_value = $email;
    $cpf_value   = $_POST['cpf'] ?? '';
    $tel_value   = $_POST['telefone'] ?? '';

    if ($email === '' || strlen($cpf) !== 11 || $tel === '' || strlen($new) < 8) {
      $erro = 'Preencha todos os campos corretamente (CPF com 11 dígitos e senha ≥ 8).';
    } elseif ($new !== $rep) {
      $erro = 'As senhas não coincidem.';
    } else {
      try {
        $db = pdo();
        // Busca pelo par (email, cpf)
        $st = $db->prepare('SELECT id, telefone FROM usuarios WHERE email = :e AND cpf = :c LIMIT 1');
        $st->execute([':e' => $email, ':c' => $cpf]);
        $u = $st->fetch(PDO::FETCH_ASSOC);

        if (!$u) {
          // Mensagem genérica para não dar pista
          $erro = 'Dados informados não conferem.';
        } else {
          // Compara telefone ignorando máscara
          $bdTelDigits = preg_replace('/\D+/', '', $u['telefone'] ?? '');
          if ($bdTelDigits !== $tel) {
            $erro = 'Dados informados não conferem.';
          } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $up = $db->prepare('UPDATE usuarios SET senha_hash = :h WHERE id = :id');
            $up->execute([':h' => $hash, ':id' => (int)$u['id']]);
            $ok = true;
          }
        }
      } catch (Throwable $e) {
        // Em produção, logar $e->getMessage()
        $erro = 'Não foi possível alterar a senha agora.';
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
<title>Aquapanema • Recuperar senha</title>
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
.small{font-size:12px;color:var(--muted)}
</style>
</head>
<body>
<main class="split">
  <section class="brand" aria-label="Apresentação do sistema Aquapanema">
    <img class="brand-hero" src="Imagens/novaimagem.png" alt="Logo Aquapanema">
    <div class="brand-word">Aquapanema</div>
    <p class="brand-caption">Sistema para identificação de espécies de peixes da bacia do rio Paranapanema.</p>
  </section>

  <section class="auth" aria-label="Recuperar senha">
    <div class="auth-card" role="region" aria-labelledby="rec-title">
      <h2 id="rec-title">Recuperar senha</h2>
      <p class="subtitle">Informe seus dados cadastrais e defina uma nova senha.</p>

      <?php if ($ok): ?>
        <div class="alert ok">Senha alterada com sucesso! Você já pode entrar.</div>
        <p><a class="link" href="login.php">Ir para o login</a></p>
      <?php else: ?>
        <?php if ($erro): ?><div class="alert err"><?= e($erro) ?></div><?php endif; ?>

        <form method="post" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

          <div class="field">
            <div class="label-row"><label for="email">E-mail</label></div>
            <input class="input" type="email" id="email" name="email" placeholder="voce@exemplo.com"
                   required value="<?= e($email_value) ?>">
          </div>

          <div class="field">
            <div class="label-row"><label for="cpf">CPF</label></div>
            <input class="input" type="text" id="cpf" name="cpf" placeholder="somente números" inputmode="numeric" pattern="\d{11}" required
                   value="<?= e($cpf_value) ?>">
            <div class="small">Digite apenas números (11 dígitos).</div>
          </div>

          <div class="field">
            <div class="label-row"><label for="telefone">Telefone</label></div>
            <input class="input" type="text" id="telefone" name="telefone" placeholder="(11) 99999-0000" inputmode="tel" required
                   value="<?= e($tel_value) ?>">
            <div class="small">Formato livre; vamos comparar ignorando pontos/parênteses/traços.</div>
          </div>

          <div class="field">
            <div class="label-row"><label for="password">Nova senha</label></div>
            <input class="input" type="password" id="password" name="password" minlength="8" required>
          </div>

          <div class="field">
            <div class="label-row"><label for="password_confirm">Confirmar nova senha</label></div>
            <input class="input" type="password" id="password_confirm" name="password_confirm" minlength="8" required>
          </div>

          <div class="actions">
            <button class="btn" type="submit">Salvar nova senha</button>
          </div>
        </form>
        <p><a class="link" href="login.php">Voltar ao login</a></p>
      <?php endif; ?>
    </div>
  </section>
</main>
</body>
</html>
