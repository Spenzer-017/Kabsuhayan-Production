<!-- PHP Logic (Authentication, Session, etc.) -->
<?php
  /*
    dashboard.php
    The main page users land on after logging in.
  */

  session_start();

  if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
  }

  require_once "includes/db.php";

  $loggedInUser = $_SESSION['user'];
  $uid = (int)$loggedInUser['id'];

  // Handle delete listing
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_item_id'])) {
    $delete_id = (int)$_POST['delete_item_id'];
    if ($delete_id > 0) {
      require_once "includes/delete-item.php";
      deleteItemWithImage($pdo, $delete_id, $uid);
    }
    header('Location: dashboard.php');
    exit;
  }
?>

<!-- PHP UI/UX Logic -->
<?php
  $activePage = "dashboard";
  $pageTitle = "Dashboard";
  include "includes/header.php";
?>

<!-- Database Query -->
<?php
  // Stats Database Query

  // Active listings
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE seller_id = ? AND status IN ('active', 'reserved')");
  $stmt->execute([$uid]);
  $stat_active = (int)$stmt->fetchColumn();

  // Items sold
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE seller_id = ? AND status = 'sold'");
  $stmt->execute([$uid]);
  $stat_sold = (int)$stmt->fetchColumn();

  // Purchases made
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE buyer_id = ? AND status = 'completed'");
  $stmt->execute([$uid]);
  $stat_purchases = (int)$stmt->fetchColumn();

  // Unread messages
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
  $stmt->execute([$uid]);
  $stat_unread = (int)$stmt->fetchColumn();

  // My Listings Database Query
  $stmt = $pdo->prepare("
    SELECT item_id, title, price, status, views
    FROM items
    WHERE seller_id = ?
    ORDER BY created_at DESC
    LIMIT 4
  ");
  $stmt->execute([$uid]);
  $my_listings = $stmt->fetchAll();

  // Recent Messages Database Query
  $stmt = $pdo->prepare("
    SELECT
      CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AS other_user_id,
      u.name AS other_name,
      u.avatar AS other_avatar,
      i.item_id,
      i.title AS item_title,
      MAX(m.msg_id) AS last_msg_id,
      MAX(m.created_at) AS last_time,
      SUM(CASE WHEN m.receiver_id = ? AND m.is_read = 0 THEN 1 ELSE 0 END) AS unread_count
    FROM messages m
    JOIN users u ON u.id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
    JOIN items i ON i.item_id = m.item_id
    WHERE m.sender_id = ? OR m.receiver_id = ?
    GROUP BY m.item_id, other_user_id
    ORDER BY last_time DESC
    LIMIT 3
  ");
  $stmt->execute([$uid, $uid, $uid, $uid, $uid]);
  $conv_rows = $stmt->fetchAll();

  // Fetch the last message
  $recent_messages = [];
  if (!empty($conv_rows)) {
    $msg_ids = array_column($conv_rows, 'last_msg_id');

    if (!empty($msg_ids)) {
      $placeholders = implode(',', array_fill(0, count($msg_ids), '?'));
      $msg_stmt = $pdo->prepare("SELECT msg_id, message FROM messages WHERE msg_id IN ($placeholders)");
      $msg_stmt->execute($msg_ids);
      $msg_map = [];
      foreach ($msg_stmt->fetchAll() as $row) {
        $msg_map[(int)$row['msg_id']] = $row['message'];
      }
      foreach ($conv_rows as $row) {
        $row['last_message'] = $msg_map[(int)$row['last_msg_id']] ?? '';
        $recent_messages[] = $row;
      }
    }
  }

  // Recent Purchases Database Query
  $stmt = $pdo->prepare("
    SELECT t.amount, t.completed_at, i.title AS item_title, u.name AS seller_name
    FROM transactions t
    JOIN items i ON t.item_id = i.item_id
    JOIN users u ON t.seller_id = u.id
    WHERE t.buyer_id = ? AND t.status = 'completed'
    ORDER BY t.completed_at DESC
    LIMIT 3
  ");
  $stmt->execute([$uid]);
  $recent_purchases = $stmt->fetchAll();

  // Saved Items Database Query
  $stmt = $pdo->prepare("
    SELECT i.item_id, i.title, i.price, i.image_path, i.status, c.name AS category
    FROM saved_items s
    JOIN items i ON s.item_id = i.item_id
    JOIN categories c ON i.category_id = c.category_id
    WHERE s.user_id = ?
    ORDER BY s.saved_at DESC
    LIMIT 4
  ");
  $stmt->execute([$uid]);
  $saved_items = $stmt->fetchAll();

  // Truncate text for previews
  function truncate(string $text, int $limit = 80): string {
    $text = trim($text);
    if (mb_strlen($text) <= $limit) return $text;
    return mb_substr($text, 0, $limit) . '…';
  }
?>

<div class="dashboard">

  <!-- Welcome Banner -->
  <div class="welcome-banner">
    <div>
      <h1>Welcome back, <?= htmlspecialchars($loggedInUser['name'] ?? '') ?>!</h1>
      <p>
        <?= !empty($loggedInUser['course']) ? htmlspecialchars($loggedInUser['course']) . '&nbsp; | &nbsp;CvSU Main Campus' : 'CvSU Main Campus' ?>
      </p>
    </div>
    <a href="sell.php" class="btn-post">+ Post an Item</a>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-value"><?= $stat_active ?></div>
      <div class="stat-label">Available Listings</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= $stat_sold ?></div>
      <div class="stat-label">Items Sold</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= $stat_purchases ?></div>
      <div class="stat-label">Purchases Made</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= $stat_unread ?></div>
      <div class="stat-label">Unread Messages</div>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="section-heading">
    <h2>Quick Actions</h2>
  </div>
  <div class="quick-actions">
    <a href="sell.php" class="quick-action-btn">
      <div class="quick-action-icon"><?= $postItemIcon ?></div>
      <div class="quick-action-label">Post Item</div>
    </a>
    <a href="browse.php" class="quick-action-btn">
      <div class="quick-action-icon"><?= $searchIcon ?></div>
      <div class="quick-action-label">Browse</div>
    </a>
    <a href="transactions.php" class="quick-action-btn">
      <div class="quick-action-icon"><?= $transactionIcon ?></div>
      <div class="quick-action-label">Transactions</div>
    </a>
    <a href="profile.php" class="quick-action-btn">
      <div class="quick-action-icon"><?= $userIcon ?></div>
      <div class="quick-action-label">My Profile</div>
    </a>
  </div>

  <!-- My Listings & Messages Grid -->
  <div class="dashboard-grid">

    <!-- My Listings Table -->
    <div>
      <div class="section-heading">
        <h2>My Listings</h2>
        <a href="my-listings.php">View all</a>
      </div>

      <?php if (empty($my_listings)): ?>
        <div class="dashboard-empty-card">
          <p>You haven't posted anything yet.</p>
          <a href="sell.php" class="dashboard-empty-link">Post your first item</a>
        </div>
      <?php else: ?>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Item</th>
                <th>Price</th>
                <th class="hide-mobile">Views</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($my_listings as $listing): ?>
                <tr>
                  <td class="td-title"><?= htmlspecialchars(truncate($listing['title'], 35)) ?></td>
                  <td class="td-nowrap">&#8369;<?= number_format($listing['price']) ?></td>
                  <td class="hide-mobile"><?= (int)$listing['views'] ?></td>
                  <td>
                    <span class="badge badge-<?= $listing['status'] ?>">
                      <?= ucfirst($listing['status']) ?>
                    </span>
                  </td>
                  <td class="table-actions td-nowrap">
                    <a href="edit-listing.php?id=<?= (int)$listing['item_id'] ?>">Edit</a>
                    <form method="POST" style="display:inline;" data-confirm="Delete this listing? This cannot be undone.">
                      <input type="hidden" name="delete_item_id" value="<?= (int)$listing['item_id'] ?>">
                      <button type="submit" class="delete table-delete-btn">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Messages Panel -->
    <div>
      <div class="section-heading">
        <h2>Messages</h2>
        <a href="messages.php">View all</a>
      </div>

      <?php if (empty($recent_messages)): ?>
        <div class="dashboard-empty-card">
          <p>No conversations yet.</p>
          <a href="browse.php" class="dashboard-empty-link">Browse listings to start messaging</a>
        </div>
      <?php else: ?>
        <div class="messages-box">
          <?php foreach ($recent_messages as $msg): ?>
            <a href="messages.php?to=<?= (int)$msg['other_user_id'] ?>&item=<?= (int)$msg['item_id'] ?>" class="message-item">
              <div class="avatar">
                <?php if (!empty($msg['other_avatar'])): ?>
                  <img src="assets/img/<?= htmlspecialchars($msg['other_avatar']) ?>.png" alt="" class="avatar-pixel-img-sm" />
                <?php else: ?>
                  <?= strtoupper(substr($msg['other_name'] ?? '?', 0, 1)) ?>
                <?php endif; ?>
              </div>
              <div class="message-content">
                <div class="message-top">
                  <span class="message-sender"><?= htmlspecialchars(truncate($msg['other_name'], 20)) ?></span>
                  <span class="message-time"><?= date('M j', strtotime($msg['last_time'])) ?></span>
                </div>
                <div class="message-item-name"><?= htmlspecialchars(truncate($msg['item_title'], 30)) ?></div>
                <div class="message-preview"><?= htmlspecialchars(truncate($msg['last_message'], 60)) ?></div>
              </div>
              <?php if ((int)$msg['unread_count'] > 0): ?>
                <div class="unread-dot"></div>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- Recent Purchases -->
  <div class="section-heading">
    <h2>Recent Purchases</h2>
    <a href="transactions.php?view=buying">View all</a>
  </div>

  <?php if (empty($recent_purchases)): ?>
    <div class="dashboard-empty-card" style="margin-bottom: 24px;">
      <p>You haven't bought anything yet.</p>
      <a href="browse.php" class="dashboard-empty-link">Start browsing</a>
    </div>
  <?php else: ?>
    <div class="purchases-box" style="margin-bottom: 24px;">
      <?php foreach ($recent_purchases as $purchase): ?>
        <div class="purchase-item">
          <div class="purchase-info">
            <div class="purchase-title"><?= htmlspecialchars(truncate($purchase['item_title'], 40)) ?></div>
            <div class="purchase-from">From: <?= htmlspecialchars(truncate($purchase['seller_name'], 25)) ?></div>
          </div>
          <div class="purchase-right">
            <div class="purchase-price">&#8369;<?= number_format($purchase['amount'], 2) ?></div>
            <div class="purchase-date"><?= date('M j', strtotime($purchase['completed_at'])) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Saved Items -->
  <div class="section-heading">
    <h2>Saved Items</h2>
    <a href="saved-items.php">View all</a>
  </div>

  <?php if (empty($saved_items)): ?>
    <div class="dashboard-empty-card">
      <p>You haven't saved any items yet.</p>
      <a href="browse.php" class="dashboard-empty-link">Browse listings to save items</a>
    </div>
  <?php else: ?>
    <div class="dashboard-saved-grid">
      <?php foreach ($saved_items as $saved): ?>
        <a href="listing.php?id=<?= (int)$saved['item_id'] ?>" class="dashboard-saved-card <?= $saved['status'] !== 'active' ? 'dashboard-saved-unavailable' : '' ?>">
          <div class="dashboard-saved-img">
            <?php if (!empty($saved['image_path'])): ?>
              <img src="uploads/<?= htmlspecialchars($saved['image_path']) ?>" alt="<?= htmlspecialchars($saved['title']) ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"/>
              <div class="dashboard-saved-fallback" style="display:none;"><?= $imgNotAvailableIcon ?></div>
            <?php else: ?>
              <?= $imgNotAvailableIcon ?>
            <?php endif; ?>
            <?php if ($saved['status'] === 'sold'): ?>
              <div class="dashboard-saved-badge sold">Sold</div>
            <?php elseif ($saved['status'] === 'reserved'): ?>
              <div class="dashboard-saved-badge reserved">Reserved</div>
            <?php endif; ?>
          </div>
          <div class="dashboard-saved-info">
            <div class="dashboard-saved-category"><?= htmlspecialchars($saved['category']) ?></div>
            <div class="dashboard-saved-title"><?= htmlspecialchars($saved['title']) ?></div>
            <div class="dashboard-saved-price">&#8369;<?= number_format($saved['price'], 2) ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php include "includes/footer.php"; ?>