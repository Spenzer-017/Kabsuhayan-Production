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
  $other_id = isset($_GET['to']) ? (int)$_GET['to'] : 0;
  $item_id = isset($_GET['item']) ? (int)$_GET['item'] : 0;
  $last_msg_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

  if ($other_id <= 0 || $item_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
  }

  // Mark incoming messages as read
  $pdo->prepare("
    UPDATE messages SET is_read = 1
    WHERE receiver_id = ? AND sender_id = ? AND item_id = ? AND is_read = 0
  ")->execute([$uid, $other_id, $item_id]);

  // Fetch only new messages since last known ID
  $stmt = $pdo->prepare("
    SELECT m.msg_id, m.sender_id, m.message, m.created_at,
      u.name AS sender_name, u.avatar AS sender_avatar
    FROM messages m
    JOIN users u ON u.id = m.sender_id
    WHERE ((m.sender_id = ? AND m.receiver_id = ?)
      OR (m.sender_id = ? AND m.receiver_id = ?))
    AND m.item_id = ?
    AND m.msg_id > ?
    ORDER BY m.created_at ASC
  ");
  $stmt->execute([$uid, $other_id, $other_id, $uid, $item_id, $last_msg_id]);
  $new_messages = $stmt->fetchAll();

  // Fetch updated conversation list for left panel
  $stmt = $pdo->prepare("
    SELECT
      m.item_id,
      CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AS other_user_id,
      u.name AS other_user_name,
      u.avatar AS other_user_avatar,
      i.title AS item_title,
      MAX(m.created_at) AS last_message_time,
      SUM(CASE WHEN m.receiver_id = ? AND m.is_read = 0 THEN 1 ELSE 0 END) AS unread_count,
      (SELECT message FROM messages m2
      WHERE ((m2.sender_id = ? AND m2.receiver_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END)
        OR (m2.sender_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AND m2.receiver_id = ?))
      AND m2.item_id = m.item_id
      ORDER BY m2.created_at DESC LIMIT 1) AS last_message
    FROM messages m
    JOIN users u ON u.id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
    JOIN items i ON i.item_id = m.item_id
    WHERE m.sender_id = ? OR m.receiver_id = ?
    GROUP BY m.item_id, other_user_id
    ORDER BY last_message_time DESC
  ");
  $stmt->execute([$uid, $uid, $uid, $uid, $uid, $uid, $uid, $uid, $uid]);
  $conversations = $stmt->fetchAll();

  $formatted_messages = [];
  foreach ($new_messages as $msg) {
    $formatted_messages[] = [
      'msg_id' => (int)$msg['msg_id'],
      'sender_id' => (int)$msg['sender_id'],
      'message' => $msg['message'],
      'created_at' => date('M j, g:i A', strtotime($msg['created_at'])),
      'avatar' => $msg['sender_avatar'],
      'is_mine' => (int)$msg['sender_id'] === $uid,
    ];
  }

  $formatted_convs = [];
  foreach ($conversations as $conv) {
    $formatted_convs[] = [
      'other_user_id' => (int)$conv['other_user_id'],
      'item_id' => (int)$conv['item_id'],
      'other_user_name' => htmlspecialchars($conv['other_user_name'], ENT_QUOTES, 'UTF-8'),
      'other_user_avatar' => $conv['other_user_avatar'],
      'item_title' => htmlspecialchars($conv['item_title'], ENT_QUOTES, 'UTF-8'),
      'last_message' => $conv['last_message'] ?? '',
      'last_message_time' => date('M j', strtotime($conv['last_message_time'])),
      'unread_count' => (int)$conv['unread_count'],
    ];
  }

  echo json_encode([
    'messages' => $formatted_messages,
    'conversations' => $formatted_convs,
  ]);
  exit;
?>