<?php
  session_start();
  require_once '../includes/db.php';
  require_once '../includes/mail.php';

  header('Content-Type: application/json');

  if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
  }

  $reporter_id = (int)$_SESSION['user']['id'];
  $reporter_name = $_SESSION['user']['name'];

  $report_type = trim($_POST['report_type'] ?? '');
  $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : null;
  $reported_user_id = isset($_POST['reported_user_id']) ? (int)$_POST['reported_user_id'] : null;
  $reason = trim($_POST['reason'] ?? '');
  $details = trim($_POST['details'] ?? '');

  // Validate report type
  if (!in_array($report_type, ['listing', 'user'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid report type']);
    exit;
  }

  // Validate based on type
  if ($report_type === 'listing' && (!$item_id || $item_id <= 0)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid listing']);
    exit;
  }

  if ($report_type === 'user' && (!$reported_user_id || $reported_user_id <= 0)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user']);
    exit;
  }

  // Cannot report yourself
  if ($report_type === 'user' && $reported_user_id === $reporter_id) {
    http_response_code(400);
    echo json_encode(['error' => 'You cannot report yourself']);
    exit;
  }

  if ($reason === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Please select a reason']);
    exit;
  }

  if (strlen($details) > 1000) {
    http_response_code(400);
    echo json_encode(['error' => 'Details must be 1000 characters or less']);
    exit;
  }

  // Check for duplicate report
  if ($report_type === 'listing') {
    $stmt = $pdo->prepare("SELECT 1 FROM reports WHERE reporter_id = ? AND item_id = ? LIMIT 1");
    $stmt->execute([$reporter_id, $item_id]);
    if ($stmt->fetch()) {
      echo json_encode(['error' => 'You have already reported this listing']);
      exit;
    }
  } else {
    $stmt = $pdo->prepare("SELECT 1 FROM reports WHERE reporter_id = ? AND reported_user_id = ? LIMIT 1");
    $stmt->execute([$reporter_id, $reported_user_id]);
    if ($stmt->fetch()) {
      echo json_encode(['error' => 'You have already reported this user']);
      exit;
    }
  }

  // Fetch target info for email
  $target_name = '';
  $target_link = '';

  if ($report_type === 'listing') {
    $stmt = $pdo->prepare("SELECT title FROM items WHERE item_id = ? LIMIT 1");
    $stmt->execute([$item_id]);
    $row = $stmt->fetch();
    if (!$row) { http_response_code(404); echo json_encode(['error' => 'Listing not found']); exit; }
    $target_name = $row['title'];
    $target_link = 'http://' . $_SERVER['HTTP_HOST'] . '/cvsu-marketplace/listing.php?id=' . $item_id;
  } else {
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$reported_user_id]);
    $row = $stmt->fetch();
    if (!$row) { http_response_code(404); echo json_encode(['error' => 'User not found']); exit; }
    $target_name = $row['name'];
    $target_link = 'http://' . $_SERVER['HTTP_HOST'] . '/cvsu-marketplace/profile.php?id=' . $reported_user_id;
  }

  // Fetch reporter email
  $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
  $stmt->execute([$reporter_id]);
  $reporter = $stmt->fetch();
  $reporter_email = $reporter ? $reporter['email'] : '';

  // Insert report
  $stmt = $pdo->prepare("
    INSERT INTO reports (reporter_id, item_id, reported_user_id, reason, details, status, created_at)
    VALUES (?, ?, ?, ?, ?, 'open', NOW())
  ");
  $stmt->execute([
    $reporter_id,
    $report_type === 'listing' ? $item_id : null,
    $report_type === 'user' ? $reported_user_id : null,
    $reason,
    $details !== '' ? $details : null,
  ]);

  $report_id = (int)$pdo->lastInsertId();

  // Send email notification
  sendReportEmail(
    $reporter_name,
    $reporter_email,
    $report_type,
    $target_name,
    $reason,
    $details,
    $target_link,
    $report_id
  );

  echo json_encode(['ok' => true]);
  exit;
?>