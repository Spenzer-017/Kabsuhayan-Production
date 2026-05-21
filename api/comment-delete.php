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
  $comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
  $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id']    : 0;

  if ($comment_id <= 0 || $item_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
  }

  // Fetch comment and item seller for authorization check
  $stmt = $pdo->prepare("
    SELECT c.commenter_id, c.is_deleted, i.seller_id
    FROM comments c
    JOIN items i ON c.item_id = i.item_id
    WHERE c.comment_id = ? AND c.item_id = ?
    LIMIT 1
  ");
  $stmt->execute([$comment_id, $item_id]);
  $row = $stmt->fetch();

  if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Comment not found']);
    exit;
  }

  // Allow if own comment or seller moderating their listing
  $is_owner = (int)$row['commenter_id'] === $user_id;
  $is_seller = (int)$row['seller_id'] === $user_id;

  if (!$is_owner && !$is_seller) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized']);
    exit;
  }

  if ($row['is_deleted']) {
    http_response_code(400);
    echo json_encode(['error' => 'Already deleted']);
    exit;
  }

  // Soft delete
  $stmt = $pdo->prepare("UPDATE comments SET is_deleted = 1, comment = '' WHERE comment_id = ?");
  $stmt->execute([$comment_id]);

  echo json_encode(['deleted' => true]);
  exit;
?>