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

  $stmt = $pdo->prepare("
    SELECT * FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
  ");
  $stmt->execute([$user_id]);
  $rows = $stmt->fetchAll();

  $unread = 0;
  $notifications = [];

  foreach ($rows as $r) {
    if (!$r['is_read']) $unread++;

    // Human-readable time ago
    $notifications[] = [
      'notif_id' => (int)$r['notif_id'],
      'type' => $r['type'],
      'message' => $r['message'],
      'link' => $r['link'],
      'is_read' => (bool)$r['is_read'],
      'created_at' => $r['created_at'],
    ];
  }

  echo json_encode([
    'notifications' => $notifications,
    'unread_count' => $unread,
  ]);
  exit;
?>