<?php
  session_start();
  require_once '../includes/db.php';

  header('Content-Type: application/json');

  if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
  }

  $uid = (int)$_SESSION['user']['id'];
  $other_id = isset($_GET['to']) ? (int)$_GET['to']   : 0;
  $item_id = isset($_GET['item']) ? (int)$_GET['item'] : 0;

  if ($other_id <= 0 || $item_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
  }

  // Mark messages as read
  $pdo->prepare("
    UPDATE messages SET is_read = 1
    WHERE receiver_id = ? AND sender_id = ? AND item_id = ? AND is_read = 0
  ")->execute([$uid, $other_id, $item_id]);

  // Fetch all messages in thread
  $stmt = $pdo->prepare("
    SELECT m.msg_id, m.sender_id, m.message, m.created_at,
      u.name AS sender_name, u.avatar AS sender_avatar
    FROM messages m
    JOIN users u ON u.id = m.sender_id
    WHERE ((m.sender_id = ? AND m.receiver_id = ?)
      OR (m.sender_id = ? AND m.receiver_id = ?))
    AND m.item_id = ?
    ORDER BY m.created_at ASC
  ");
  $stmt->execute([$uid, $other_id, $other_id, $uid, $item_id]);
  $messages = $stmt->fetchAll();

  // Fetch other user info
  $stmt = $pdo->prepare("SELECT id, name, avatar, course FROM users WHERE id = ? LIMIT 1");
  $stmt->execute([$other_id]);
  $other_user = $stmt->fetch();

  // Fetch item data
  $stmt = $pdo->prepare("
    SELECT item_id, title, price, image_path, status, seller_id
    FROM items WHERE item_id = ? LIMIT 1
  ");
  $stmt->execute([$item_id]);
  $item = $stmt->fetch();

  // Fetch transaction for this chat
  $is_seller = $item && (int)$item['seller_id'] === $uid;
  $chat_seller_id = $item ? (int)$item['seller_id'] : 0;
  $chat_buyer_id = ($uid === $chat_seller_id) ? $other_id : $uid;

  $stmt = $pdo->prepare("
    SELECT * FROM transactions
    WHERE item_id = ? AND buyer_id = ? AND seller_id = ?
    ORDER BY CASE status WHEN 'pending' THEN 0 WHEN 'completed' THEN 1 ELSE 2 END, created_at DESC
    LIMIT 1
  ");
  $stmt->execute([$item_id, $chat_buyer_id, $chat_seller_id]);
  $transaction = $stmt->fetch();

  $stmt = $pdo->prepare("SELECT 1 FROM transactions WHERE item_id = ? AND status = 'pending' LIMIT 1");
  $stmt->execute([$item_id]);
  $any_pending = (bool)$stmt->fetch();

  $formatted = [];
  foreach ($messages as $msg) {
    $formatted[] = [
      'msg_id' => (int)$msg['msg_id'],
      'sender_id' => (int)$msg['sender_id'],
      'message' => $msg['message'],
      'created_at' => date('M j, g:i A', strtotime($msg['created_at'])),
      'avatar' => $msg['sender_avatar'],
      'is_mine' => (int)$msg['sender_id'] === $uid,
    ];
  }

  echo json_encode([
    'messages' => $formatted,
    'other_user' => [
      'id' => (int)$other_user['id'],
      'name' => htmlspecialchars($other_user['name'],   ENT_QUOTES, 'UTF-8'),
      'avatar' => $other_user['avatar'] ?? '',
      'course' => htmlspecialchars($other_user['course'] ?? '', ENT_QUOTES, 'UTF-8'),
    ],
    'item' => [
      'item_id' => (int)$item['item_id'],
      'title' => htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'),
      'price' => number_format((float)$item['price'], 2),
      'image_path' => $item['image_path'] ?? '',
      'status' => $item['status'],
      'seller_id' => (int)$item['seller_id'],
      'image_exists' => !empty($item['image_path']) && file_exists(__DIR__ . '/../uploads/' . $item['image_path']),
    ],
    'transaction' => $transaction ? [
      'transaction_id' => (int)$transaction['transaction_id'],
      'status' => $transaction['status'],
    ] : null,
    'any_pending' => $any_pending,
    'is_seller' => $is_seller,
  ]);
  exit;
?>