<?php
/**
 * index.php — Bootstrap e Conexão (MySQL/MariaDB)
 * Use em outras páginas:
 *   require_once __DIR__ . '/index.php';
 *   $pdo = pdo();
 *   // se precisar proteger:
 *   require_login();
 */

// URL do serviço de IA (Flask)
define('PREDICT_URL', 'http://127.0.0.1:8000/predict');
// (opcional) healthcheck
define('PREDICT_HEALTH', 'http://127.0.0.1:8000/health');


// Evita reprocessar se incluído várias vezes
if (!defined('APP_BOOTSTRAPPED')) {
  define('APP_BOOTSTRAPPED', true);

  /*==============================
   | Configurações da Aplicação  |
   ==============================*/
  date_default_timezone_set('America/Sao_Paulo');

  // DEV: exibir erros (em produção, desligue)
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);

  // Sessão
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }

  /*============================
   | Credenciais do Banco (PDO) |
   ============================*/
  if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
  if (!defined('DB_NAME')) define('DB_NAME', 'fishid');     // ajuste
  if (!defined('DB_USER')) define('DB_USER', 'root');
  if (!defined('DB_PASS')) define('DB_PASS', '159753258456'); // sua senha
  if (!defined('DB_DSN'))  define('DB_DSN', 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4');

  /**
   * Conexão PDO (singleton)
   */
  function pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $pdo = new PDO(
      DB_DSN,
      DB_USER,
      DB_PASS,
      [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
      ]
    );
    return $pdo;
  }

  /**
   * Escapar HTML
   */
  if (!function_exists('e')) {
    function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
  }

  /**
   * Autenticação
   */
  function is_logged_in(): bool {
    return !empty($_SESSION['usuario_id']);
  }

  function require_login(): void {
    if (!is_logged_in()) {
      header('Location: login.php');
      exit;
    }
  }

  /**
   * CSRF helper (opcional)
   */
  if (!function_exists('csrf_token')) {
    function csrf_token(): string {
      if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
      }
      return $_SESSION['csrf_token'];
    }
  }
}

/*=========================================================
 | Página de STATUS quando acessado diretamente            |
 | (não roda quando este arquivo é somente incluído)       |
 =========================================================*/
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
  $ok = true; $msg = 'Conexão realizada com sucesso.';
  try {
    $db = pdo();
    $db->query('SELECT 1')->fetch();
  } catch (Throwable $ex) {
    $ok = false;
    $msg = 'Falha na conexão: ' . $ex->getMessage();
  }
  ?>
  <!DOCTYPE html>
  <html lang="pt-BR">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status da Conexão — FishID</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
      :root {
        --brand-900:#062a33; --brand-700:#0d5868; --brand-500:#14b8a6; --brand-300:#99f6e4;
        --accent:#ffd166; --accent-2:#ff7e6b; --bg:#e9fbff; --text:#0e1b24; --muted:#5f7582; --radius:20px;
      }
      html, body { height:100%; margin:0; font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial; background: var(--bg); color: var(--text); }
      .center { min-height:100%; display:grid; place-items:center; padding: 24px; }
      .card { max-width: 720px; width: 100%; background: #fff; border:1px solid #e6f3f5; border-radius: var(--radius); box-shadow: 0 18px 44px rgba(6,42,51,.16); padding: 24px; }
      h1 { margin: 0 0 8px; font-size: 1.4rem; color: var(--brand-700); }
      .ok { color: #0f8f6a; font-weight: 800; }
      .err { color: #d9432f; font-weight: 800; }
      .meta { margin-top: 12px; font-size: .95rem; color: var(--muted); }
      code { background: #f7fbfd; border:1px solid #e3eef2; border-radius: 8px; padding: 2px 6px; }
      .grid { display:grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px; }
      @media (max-width: 640px) { .grid { grid-template-columns: 1fr; } }
      .btn { display:inline-block; margin-top:16px; background: linear-gradient(180deg, var(--brand-500), var(--brand-700) 60%, var(--brand-900)); color:#fff; text-decoration:none; padding:10px 14px; border-radius: 12px; font-weight: 800; }
    </style>
  </head>
  <body>
    <div class="center">
      <div class="card">
        <h1>Status da Conexão</h1>
        <p><span class="<?php echo $ok ? 'ok' : 'err'; ?>"><?php echo $ok ? 'ONLINE' : 'OFFLINE'; ?></span> — <?php echo e($msg); ?></p>
        <div class="grid">
          <div><strong>Host:</strong> <code><?php echo e(DB_HOST); ?></code></div>
          <div><strong>Banco:</strong> <code><?php echo e(DB_NAME); ?></code></div>
          <div><strong>Usuário:</strong> <code><?php echo e(DB_USER); ?></code></div>
          <div><strong>Charset:</strong> <code>utf8mb4</code></div>
        </div>
        <a class="btn" href="usuario.php">Ir para Meus Dados</a>
      </div>
    </div>
  </body>
  </html>
  <?php
}
