<?php
// deletar_usuario.php — Exclui a conta do usuário logado
require_once __DIR__ . '/index.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Método não permitido.';
    exit;
}

// CSRF
$sessionToken = $_SESSION['csrf_token'] ?? '';
$formToken    = $_POST['csrf_token']     ?? '';
if (!$sessionToken || !$formToken || !hash_equals($sessionToken, $formToken)) {
    http_response_code(403);
    echo 'Falha de validação (CSRF).';
    exit;
}

$usuarioId = (int) $_SESSION['usuario_id'];
$pdo = pdo();

try {
    $pdo->beginTransaction();

    // Se suas FKs já têm ON DELETE CASCADE, só o DELETE de usuarios bastaria.
    // Ainda assim, apagamos dependentemente para funcionar mesmo sem as FKs configuradas.
    $stmt = $pdo->prepare("DELETE FROM historico WHERE usuario_id = :id");
    $stmt->execute([':id' => $usuarioId]);

    $stmt = $pdo->prepare("DELETE FROM enderecos WHERE usuario_id = :id");
    $stmt->execute([':id' => $usuarioId]);

    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $usuarioId]);

    $pdo->commit();

    // Encerrar a sessão
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
    }
    session_destroy();

    // Redireciona para login com um aviso
    header('Location: login.php?conta_excluida=1');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    // Em produção, logue $e->getMessage()
    echo 'Não foi possível excluir a conta no momento.';
    exit;
}
