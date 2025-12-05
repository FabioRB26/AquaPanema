<?php
// logout.php — encerra a sessão e volta para o login
require_once __DIR__ . '/index.php';

// Aceita somente POST por segurança (use o form da top bar)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Método não permitido.';
    exit;
}

// Valida CSRF (token veio do form oculto na navbar)
$sessionToken = $_SESSION['csrf_token'] ?? '';
$formToken    = $_POST['csrf_token']     ?? '';
if (!$sessionToken || !$formToken || !hash_equals($sessionToken, $formToken)) {
    http_response_code(403);
    echo 'Falha de validação (CSRF).';
    exit;
}

// Limpa todos os dados da sessão
$_SESSION = [];

// Remove o cookie de sessão, se existir
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        (bool)($params['secure'] ?? false),
        (bool)($params['httponly'] ?? true)
    );
}

// Encerra a sessão
session_destroy();

// Redireciona para a página de login com flag de sucesso
header('Location: login.php?logged_out=1');
exit;
