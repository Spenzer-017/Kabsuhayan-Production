<?php
  /*
    api/get_listings.php
    Returns a paginated batch of active listings as JSON.
  */

  session_start();

  // Must be logged in
  if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
  }

  require_once '../includes/db.php';

  header('Content-Type: application/json');

  // Sanitize inputs 
  $page = max(1, (int)($_GET['page'] ?? 1));
  $limit = min(24, max(1, (int)($_GET['limit'] ?? 12)));
  $offset = ($page - 1) * $limit;
  $category = trim($_GET['category'] ?? 'All');
  $condition = trim($_GET['condition'] ?? 'Any Condition');
  $sort = trim($_GET['sort'] ?? 'newest');
  $q = trim($_GET['q'] ?? '');

  // Build WHERE clauses 
  $where = ["items.status = 'active'"];
  $params = [];

  // Category filter
  if ($category !== '' && $category !== 'All') {
    $where[] = "categories.name = ?";
    $params[] = $category;
  }

  // Condition filter
  if ($condition !== '' && $condition !== 'Any Condition') {
    $where[] = "items.condition_type = ?";
    $params[] = $condition;
  }

  // Search query - matches title or category name
  if ($q !== '') {
    $where[] = "(items.title LIKE ? OR categories.name LIKE ?)";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
  }

  $whereSQL = implode(' AND ', $where);

  // ORDER BY price ascending/descending
  $orderSQL = match($sort) {
    'price_asc' => 'items.price ASC, items.created_at DESC',
    'price_desc' => 'items.price DESC, items.created_at DESC',
    default => 'items.created_at DESC',
  };

  // Main query 
  // Fetch limit + 1 to check if there are more pages without a second COUNT query
  $stmt = $pdo->prepare("
    SELECT
      items.item_id AS id,
      items.title,
      items.price,
      items.image_path,
      items.condition_type AS `condition`,
      items.meetup_location AS location,
      categories.name AS category,
      users.name AS seller
    FROM items
    JOIN users ON items.seller_id = users.id
    JOIN categories ON items.category_id = categories.category_id
    WHERE $whereSQL
    ORDER BY $orderSQL
    LIMIT ? OFFSET ?
  ");

  // PDO needs explicit int binding for LIMIT/OFFSET
  $allParams = $params;
  $allParams[] = $limit + 1;
  $allParams[] = $offset;

  $i = 1;
  foreach ($allParams as $val) {
    $type = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($i++, $val, $type);
  }
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Detect if there's more
  $has_more = count($rows) > $limit;
  if ($has_more) {
    array_pop($rows);
  }

  // Build intl_get_error_message card 
  $items = [];
  foreach ($rows as $row) {
    $imgPath = '../uploads/' . $row['image_path'];
    $imgHtml = (!empty($row['image_path']) && file_exists($imgPath)) ? '<img src="uploads/' . htmlspecialchars($row['image_path'], ENT_QUOTES) . '" alt="Item Image" loading="lazy">' : null;

    $items[] = [
      'id' => (int)$row['id'],
      'title' => htmlspecialchars($row['title'], ENT_QUOTES),
      'price' => (float)$row['price'],
      'category' => $row['category'],
      'seller' => $row['seller'],
      'location' => $row['location'] ?? '',
      'condition' => $row['condition'],
      'img' => $row['image_path'],
    ];
  }

  echo json_encode([
    'items' => $items,
    'has_more' => $has_more,
    'page' => $page,
  ]);
?>