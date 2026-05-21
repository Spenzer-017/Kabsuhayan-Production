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
  $comment = trim($_POST['comment'] ?? '');

  if ($comment_id <= 0 || $comment === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
  }

  if (strlen($comment) > 5000) {
    http_response_code(400);
    echo json_encode(['error' => 'Comment must be 5000 characters or less']);
    exit;
  }

  $stmt = $pdo->prepare("SELECT commenter_id, is_deleted FROM comments WHERE comment_id = ? LIMIT 1");
  $stmt->execute([$comment_id]);
  $row = $stmt->fetch();

  if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Comment not found']);
    exit;
  }

  if ((int)$row['commenter_id'] !== $user_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized']);
    exit;
  }

  if ($row['is_deleted']) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot edit a deleted comment']);
    exit;
  }

  // Store raw - never encode before saving
  $stmt = $pdo->prepare("UPDATE comments SET comment = ?, updated_at = NOW() WHERE comment_id = ?");
  $stmt->execute([$comment, $comment_id]);

  // Return htmlspecialchars only for display
  echo json_encode([
    'comment' => htmlspecialchars($comment, ENT_QUOTES, 'UTF-8'),
  ]);
  exit;
?>