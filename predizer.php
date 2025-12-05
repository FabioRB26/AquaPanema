<?php
// predizer.php — Faz upload, chama IA, insere no historico (com geodados) e redireciona
require_once __DIR__ . '/index.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método não permitido'); }
if (empty($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) { http_response_code(400); exit('Envie uma imagem válida.'); }

// (Opcional) CSRF — só se sua app usa csrf_token() guardado em sessão
if (function_exists('csrf_token')) {
  $formToken = $_POST['csrf_token'] ?? '';
  if (!$formToken || !hash_equals(csrf_token(), $formToken)) {
    http_response_code(403); exit('Sessão expirada. Recarregue a página.');
  }
}

$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
$pdo = pdo();

// ===== 0) Metadados do request (geolocalização, dispositivo, IP) =====
$lat = filter_input(INPUT_POST, 'lat', FILTER_VALIDATE_FLOAT);
$lng = filter_input(INPUT_POST, 'lng', FILTER_VALIDATE_FLOAT);
$acc = filter_input(INPUT_POST, 'acc', FILTER_VALIDATE_FLOAT);
if ($lat === false) $lat = null; if ($lng === false) $lng = null; if ($acc === false) $acc = null;
if ($lat !== null && ($lat < -90 || $lat > 90))   $lat = null;
if ($lng !== null && ($lng < -180 || $lng > 180)) $lng = null;
if ($acc !== null && $acc < 0) $acc = null;

$ua   = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 80);
$ip   = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$ipBn = @inet_pton($ip); // VARBINARY(16)

// ===== 1) Salvar imagem =====
$dir = __DIR__ . '/uploads';
if (!is_dir($dir)) mkdir($dir, 0775, true);

$mime   = mime_content_type($_FILES['imagem']['tmp_name']);
$okMime = ['image/jpeg','image/png','image/webp','image/gif'];
if (!in_array($mime, $okMime, true)) { http_response_code(400); exit('Formato de imagem não suportado.'); }

$ext        = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
$nomeArq    = 'pred_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . preg_replace('/[^a-z0-9]+/','',$ext);
$caminhoFs  = $dir . '/' . $nomeArq;
$caminhoRel = 'uploads/' . $nomeArq;

if (!move_uploaded_file($_FILES['imagem']['tmp_name'], $caminhoFs)) { http_response_code(500); exit('Falha ao salvar a imagem.'); }

// ===== 2) Chamar a IA (Flask) =====
$label = null; $confianca = null;
if (defined('PREDICT_URL') && PREDICT_URL) {
  $ch  = curl_init();
  $cF  = new CURLFile($caminhoFs, $mime, $nomeArq);
  curl_setopt_array($ch, [
    CURLOPT_URL            => PREDICT_URL,          // ex.: http://127.0.0.1:8080/predict
    CURLOPT_POST           => true,
    // Envia com duas chaves para compatibilidade ("image" e "file")
    CURLOPT_POSTFIELDS     => [ 'image' => $cF, 'file' => $cF ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 45,
  ]);
  $resp = curl_exec($ch);
  $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($resp !== false && $http >= 200 && $http < 300) {
    $data = json_decode($resp, true) ?: [];
    $rawLabel  = trim((string)($data['scientific_name'] ?? $data['nome_cientifico'] ?? $data['label'] ?? ''));
    $confianca = isset($data['confidence']) ? (float)$data['confidence'] : (isset($data['confianca']) ? (float)$data['confianca'] : null);
    // normalização leve (ex.: Astyanax_altiparanae → Astyanax altiparanae)
    $label = trim(preg_replace('/\s+/', ' ', strtr($rawLabel, ['_'=>' '])));
  }
}

// ===== 3) Tentar casar com catálogo =====
$peixeId = 0;
if ($label) {
  // Tentativa 1: nome_cientifico = label (case-insensitive)
  $st = $pdo->prepare("SELECT id FROM peixes WHERE nome_cientifico COLLATE utf8mb4_general_ci = :v LIMIT 1");
  $st->execute([':v'=>$label]);
  $peixeId = (int)($st->fetchColumn() ?: 0);

  if (!$peixeId) {
    // Tentativa 2: REPLACE(nome_cientifico,'_',' ')
    $st = $pdo->prepare("SELECT id FROM peixes WHERE REPLACE(nome_cientifico,'_',' ') COLLATE utf8mb4_general_ci = :v LIMIT 1");
    $st->execute([':v'=>$label]);
    $peixeId = (int)($st->fetchColumn() ?: 0);
  }
  if (!$peixeId) {
    // Tentativa 3: CONCAT(genero,' ',especie)
    $st = $pdo->prepare("SELECT id FROM peixes WHERE TRIM(CONCAT(genero,' ',especie)) COLLATE utf8mb4_general_ci = :v LIMIT 1");
    $st->execute([':v'=>$label]);
    $peixeId = (int)($st->fetchColumn() ?: 0);
  }
}

// ===== 4) Inserir histórico (com campos do dashboard) =====
// Evita repetir placeholders: usamos um flag geo_ok e placeholders exclusivos para POINT
$geoOk   = ($lat !== null && $lng !== null) ? 1 : 0;
$sql = "
  INSERT INTO historico
    (usuario_id, peixe_id, nome_cientifico_predito, confianca, caminho_imagem, confirmado,
     lat,   lng,   acuracia_m, local_geo,                                              origem, dispositivo, ip)
  VALUES
    (:u,   :pid,  :nc,                      :conf,     :img,     0,
     :lat, :lng,  :acc,
     CASE WHEN :geo_ok = 1 THEN POINT(:lng_geo, :lat_geo) ELSE NULL END,
     'web', :ua, :ip)
";
$pdo->prepare($sql)->execute([
  ':u'       => $usuarioId,
  ':pid'     => $peixeId ?: null,
  ':nc'      => ($label !== '') ? $label : null,
  ':conf'    => $confianca,
  ':img'     => $caminhoRel,
  ':lat'     => $lat,
  ':lng'     => $lng,
  ':acc'     => $acc,
  ':geo_ok'  => $geoOk,
  ':lng_geo' => $lng,
  ':lat_geo' => $lat,
  ':ua'      => $ua,
  ':ip'      => $ipBn,
]);
$hid = (int)$pdo->lastInsertId();

// ===== 5) Redirecionar =====
if ($peixeId) {
  header("Location: peixe.php?id={$peixeId}&hist={$hid}");
} else {
  header("Location: peixe.php?hist={$hid}");
}
exit;
