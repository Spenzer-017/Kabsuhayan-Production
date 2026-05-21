<?php
  session_start();
  require_once '../includes/db.php';

  header('Content-Type: application/json');

  if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
  }

  $user_id = (int)$_SESSION['user']['id'];
  $mode = trim($_POST['mode'] ?? '');
  $notif_id = isset($_POST['notif_id']) ? (int)$_POST['notif_id'] : 0;

  if ($mode === 'one' && $notif_id > 0) {
    // Verify ownership
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE notif_id = ? AND user_id = ?");
    $stmt->execute([$notif_id, $user_id]);
    echo json_encode(['ok' => true]);
  } elseif ($mode === 'all') {
    $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$user_id]);
    echo json_encode(['ok' => true]);
  } else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
  }
  exit;
?>