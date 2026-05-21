<?php
  session_start();
  require_once '../includes/db.php';
  require_once '../includes/notify.php';

  header('Content-Type: application/json');

  if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
  }

  $uid = (int)$_SESSION['user']['id'];
  $actor_name = $_SESSION['user']['name'];
  $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
  $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
  $message = trim($_POST['message'] ?? '');

  if ($receiver_id <= 0 || $item_id <= 0 || $message === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
  }

  if (strlen($message) > 1000) {
    http_response_code(400);
    echo json_encode(['error' => 'Message too long']);
    exit;
  }

  $stmt = $pdo->prepare("SELECT seller_id FROM items WHERE item_id = ? LIMIT 1");
  $stmt->execute([$item_id]);
  $item = $stmt->fetch();

  if (!$item) {
    http_response_code(404);
    echo json_encode(['error' => 'Item not found']);
    exit;
  }

  $seller_id = (int)$item['seller_id'];
  $allowed = (
    ($uid !== $seller_id && $receiver_id === $seller_id) ||
    ($uid === $seller_id && $receiver_id !== $seller_id)
  );

  if (!$allowed) {
    http_response_code(403);
    echo json_encode(['error' => 'Not allowed']);
    exit;
  }

  $pdo->prepare("
    INSERT INTO messages (sender_id, receiver_id, item_id, message)
    VALUES (?, ?, ?, ?)
  ")->execute([$uid, $receiver_id, $item_id, $message]);

  $msg_id = (int)$pdo->lastInsertId();

  insertNotification(
    $pdo,
    $receiver_id,
    $uid,
    'message',
    htmlspecialchars($actor_name, ENT_QUOTES, 'UTF-8') . ' sent you a message.',
    'messages.php?to=' . $uid . '&item=' . $item_id
  );

  echo json_encode([
    'msg_id' => $msg_id,
    'message' => $message,
    'created_at' => date('M j, g:i A'),
  ]);
  exit;
?>