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
  $username = $_SESSION['user']['name'];

  $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id']   : 0;
  $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
  $comment = trim($_POST['comment'] ?? '');

  if ($item_id <= 0 || $comment === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
  }

  if (strlen($comment) > 5000) {
    http_response_code(400);
    echo json_encode(['error' => 'Comment must be 5000 characters or less']);
    exit;
  }

  $stmt = $pdo->prepare("SELECT item_id FROM items WHERE item_id = ? LIMIT 1");
  $stmt->execute([$item_id]);
  if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Item not found']);
    exit;
  }

  $parent_id_val = null;
  $reply_to_name = null;

  if ($parent_id > 0) {
    $stmt = $pdo->prepare("
      SELECT c.comment_id, c.parent_id, u.name AS commenter_name
      FROM comments c
      JOIN users u ON c.commenter_id = u.id
      WHERE c.comment_id = ? AND c.item_id = ?
      LIMIT 1
    ");
    $stmt->execute([$parent_id, $item_id]);
    $parent = $stmt->fetch();

    if (!$parent) {
      http_response_code(404);
      echo json_encode(['error' => 'Parent comment not found']);
      exit;
    }

    // If user sent a reply_to_name explicitly (replying to a specific reply), use it
    $posted_reply_to = trim($_POST['reply_to_name'] ?? '');
    if ($parent['parent_id'] !== null) {
      // Replying to a reply - flatten to top-level parent
      $parent_id_val = (int)$parent['parent_id'];
      $reply_to_name = $posted_reply_to !== '' ? $posted_reply_to : $parent['commenter_name'];
    } else {
      $parent_id_val = $parent_id;
      $reply_to_name = $posted_reply_to !== '' ? $posted_reply_to : null;
    }
  }

  // Store raw - never encode before saving
  $stmt = $pdo->prepare("
    INSERT INTO comments (commenter_id, item_id, parent_id, reply_to_name, comment, created_at)
    VALUES (?, ?, ?, ?, ?, NOW())
  ");
  $stmt->execute([$user_id, $item_id, $parent_id_val, $reply_to_name, $comment]);
  $new_id = (int)$pdo->lastInsertId();

  require_once '../includes/notify.php';
  $actor_name = $_SESSION['user']['name'];

  // Notify listing owner of new comment (top-level only)
  if ($parent_id_val === null) {
    // Get item seller
    $stmt2 = $pdo->prepare("SELECT seller_id FROM items WHERE item_id = ? LIMIT 1");
    $stmt2->execute([$item_id]);
    $item_row = $stmt2->fetch();
    if ($item_row) {
      insertNotification(
        $pdo,
        (int)$item_row['seller_id'],
        $user_id,
        'comment',
        htmlspecialchars($actor_name, ENT_QUOTES, 'UTF-8') . ' commented on your listing.',
        'listing.php?id=' . $item_id
      );
    }
  } else {
    // Notify parent comment author of reply
    $stmt2 = $pdo->prepare("SELECT commenter_id FROM comments WHERE comment_id = ? LIMIT 1");
    $stmt2->execute([$parent_id_val]);
    $parent_row = $stmt2->fetch();
    if ($parent_row) {
      insertNotification(
        $pdo,
        (int)$parent_row['commenter_id'],
        $user_id,
        'reply',
        htmlspecialchars($actor_name, ENT_QUOTES, 'UTF-8') . ' replied to your comment.',
        'listing.php?id=' . $item_id
      );
    }
  }

  echo json_encode([
    'comment_id' => $new_id,
    'comment' => htmlspecialchars($comment, ENT_QUOTES, 'UTF-8'),
    'commenter_name' => htmlspecialchars($username, ENT_QUOTES, 'UTF-8'),
    'created_at' => date('M j, Y'),
    'user_id' => $user_id,
    'reply_to_name' => $reply_to_name ? htmlspecialchars($reply_to_name, ENT_QUOTES, 'UTF-8') : null,
  ]);
  exit;
?>