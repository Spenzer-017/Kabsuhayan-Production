<!-- PHP Logic (Authentication, Session, etc.) -->
<?php
  /*
    my-listings.php - Manage My Listings
    Shows all listings posted by the logged-in user.
    Sellers can see status, views, edit, and delete.
  */

  session_start();

  require_once "includes/db.php";

  if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
  }

  $loggedInUser = $_SESSION['user'];
  $uid = (int)$loggedInUser['id'];

  // Read filter early so the redirect after delete can preserve it
  $filter = $_GET['filter'] ?? 'all';
  $allowed_filters = ['all', 'active', 'reserved', 'sold'];
  if (!in_array($filter, $allowed_filters)) $filter = 'all';

  // Handle delete listing
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item_id'])) {
    $delete_id = (int)$_POST['delete_item_id'];
    if ($delete_id > 0) {
      require_once "includes/delete-item.php";
      deleteItemWithImage($pdo, $delete_id, $uid);
    }
    header('Location: my-listings.php?filter=' . urlencode($filter));
    exit;
  }
?>

<!-- PHP UI/UX Logic -->
<?php
  $activePage = '';
  $pageTitle = "My Listings";
  include "includes/header.php";
?>

<!-- PHP Database Query -->
<?php
  // Build query based on filter
  $where = $filter === 'all' ? "seller_id = ?" : "seller_id = ? AND status = ?";
  $params = $filter === 'all' ? [$uid] : [$uid, $filter];

  $stmt = $pdo->prepare("
    SELECT items.*, categories.name AS category
    FROM items
    JOIN categories ON items.category_id = categories.category_id
    WHERE $where
    ORDER BY items.created_at DESC
  ");
  $stmt->execute($params);
  $listings = $stmt->fetchAll();

  // Count totals for the filter tabs
  $stmt = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM items WHERE seller_id = ? GROUP BY status");
  $stmt->execute([$uid]);
  $counts = ['all' => 0, 'active' => 0, 'reserved' => 0, 'sold' => 0];
  foreach ($stmt->fetchAll() as $row) {
    $counts[$row['status']] = (int)$row['cnt'];
    $counts['all'] += (int)$row['cnt'];
  }
?>

<div class="my-listings-page">

  <div class="my-listings-header">
    <div>
      <h1>My Listings</h1>
      <p>Manage all the items you've posted for sale.</p>
    </div>
    <a href="sell.php" class="btn-new-listing">+ Post New Item</a>
  </div>

  <!-- Filter tabs -->
  <div class="listings-filter-tabs">
    <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">All (<?= $counts['all'] ?>)</a>
    <a href="?filter=active" class="filter-tab <?= $filter === 'active' ? 'active' : '' ?>">Active (<?= $counts['active'] ?>)</a>
    <a href="?filter=reserved" class="filter-tab <?= $filter === 'reserved' ? 'active' : '' ?>">Reserved (<?= $counts['reserved'] ?>)</a>
    <a href="?filter=sold" class="filter-tab <?= $filter === 'sold' ? 'active' : '' ?>">Sold (<?= $counts['sold'] ?>)</a>
  </div>

  <?php if (empty($listings)): ?>
    <div class="empty-state" style="padding: 64px 24px;">
      <div class="empty-icon"><?= $imgNotAvailableIcon ?></div>
      <h3>No listings found</h3>
      <p>
        <?= $filter === 'all' ? "You haven't posted anything yet." : "No $filter listings." ?>
      </p>
      <?php if ($filter === 'all'): ?>
        <a href="sell.php" class="btn-cta-primary" style="margin-top:16px; display:inline-block;">Post Your First Item</a>
      <?php endif; ?>
    </div>

  <?php else: ?>
    <div class="my-listings-grid">
      <?php foreach ($listings as $listing): ?>
        <div class="my-listing-card">

          <!-- Image -->
          <div class="my-listing-img">
            <?php
              $imgFile = "uploads/" . $listing['image_path'];
              if (!empty($listing['image_path']) && file_exists($imgFile)):
            ?>
              <img src="<?= htmlspecialchars($imgFile) ?>" alt="<?= htmlspecialchars($listing['title']) ?>" />
            <?php else: ?>
              <?= $imgNotAvailableIcon ?>
            <?php endif; ?>
          </div>

          <!-- Info -->
          <div class="my-listing-info">
            <div class="my-listing-category"><?= htmlspecialchars($listing['category']) ?></div>
            <a href="listing.php?id=<?= (int)$listing['item_id'] ?>" class="my-listing-title">
              <?= htmlspecialchars($listing['title']) ?>
            </a>
            <div class="my-listing-price">&#8369;<?= number_format($listing['price'], 2) ?></div>

            <div class="my-listing-meta">
              <span class="badge badge-<?= $listing['status'] ?>"><?= ucfirst($listing['status']) ?></span>
              <span class="my-listing-views"><?= (int)$listing['views'] ?> views</span>
              <span class="my-listing-date"><?= date('M j, Y', strtotime($listing['created_at'])) ?></span>
            </div>
          </div>

          <!-- Actions -->
          <div class="my-listing-actions">
            <a href="edit-listing.php?id=<?= (int)$listing['item_id'] ?>" class="btn-edit-listing">Edit</a>
            <form method="POST" data-confirm="Delete this listing? This cannot be undone.">
              <input type="hidden" name="delete_item_id" value="<?= (int)$listing['item_id'] ?>">
              <button type="submit" class="btn-delete-listing-sm">Delete</button>
            </form>
          </div>

        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>