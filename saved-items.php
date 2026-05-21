<!-- PHP Logic (Authentication, Session, etc.) -->
<?php
  /*
    saved-items.php - Saved / Wishlisted Items
    Shows all items the logged-in user has saved.
  */

  session_start();

  require_once "includes/db.php";

  if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
  }

  $loggedInUser = $_SESSION['user'];
  $uid = (int)$loggedInUser['id'];
?>

<!-- PHP Database Query -->
<?php
  // Handle unsave POST
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unsave_item'])) {
    $unsave_id = (int)($_POST['item_id'] ?? 0);
    if ($unsave_id > 0) {
      $pdo->prepare("DELETE FROM saved_items WHERE user_id = ? AND item_id = ?")->execute([$uid, $unsave_id]);
    }
    header("Location: saved-items.php");
    exit;
  }

  // Fetch all saved items with item + category details
  $stmt = $pdo->prepare("
    SELECT i.item_id, i.title, i.price, i.image_path, i.status, i.condition_type,
          c.name AS category, u.name AS seller_name, s.saved_at
    FROM saved_items s
    JOIN items i ON s.item_id = i.item_id
    JOIN categories c ON i.category_id = c.category_id
    JOIN users u ON i.seller_id = u.id
    WHERE s.user_id = ?
    ORDER BY s.saved_at DESC
  ");
  $stmt->execute([$uid]);
  $saved_items = $stmt->fetchAll();
?>

<!-- PHP UI/UX Logic -->
<?php
  $activePage = '';
  $pageTitle = "Saved Items";
  include "includes/header.php";
?>

<div class="saved-page">

  <div class="saved-header">
    <div>
      <h1>Saved Items</h1>
      <p>Items you saved to keep an eye on.</p>
    </div>
    <a href="browse.php" class="btn-new-listing">Browse More</a>
  </div>

  <?php if (empty($saved_items)): ?>
    <div class="empty-state" style="padding: 64px 24px;">
      <div class="empty-icon"><?= $savedItemIcon ?></div>
      <h3>No saved items yet</h3>
      <p>Browse listings and click Save Item to add them here.</p>
      <a href="browse.php" class="btn-cta-primary" style="margin-top: 16px; display: inline-block;">Browse Listings</a>
    </div>

  <?php else: ?>
    <div class="saved-grid">
      <?php foreach ($saved_items as $item): ?>
        <div class="saved-card <?= $item['status'] !== 'active' ? 'saved-card-unavailable' : '' ?>">

          <!-- Image -->
          <div class="saved-card-img">
            <?php
              $imgFile = "uploads/" . $item['image_path'];
              if (!empty($item['image_path']) && file_exists($imgFile)):
            ?>
              <img src="<?= htmlspecialchars($imgFile) ?>" alt="<?= htmlspecialchars($item['title']) ?>" />
            <?php else: ?>
              <?= $imgNotAvailableIcon ?>
            <?php endif; ?>
          </div>

          <!-- Info -->
          <div class="saved-card-info">
            <div class="saved-card-category"><?= htmlspecialchars($item['category']) ?></div>
            <a href="listing.php?id=<?= (int)$item['item_id'] ?>" class="saved-card-title">
              <?= htmlspecialchars($item['title']) ?>
            </a>
            <div class="saved-card-price">&#8369;<?= number_format($item['price'], 2) ?></div>

            <div class="saved-card-meta">
              <?php if ($item['status'] === 'active'): ?>
                <span class="badge badge-active">Available</span>
              <?php elseif ($item['status'] === 'sold'): ?>
                <span class="badge badge-sold">Sold</span>
              <?php else: ?>
                <span class="badge badge-reserved">Reserved</span>
              <?php endif; ?>
              <span class="saved-card-seller"><?= htmlspecialchars($item['seller_name']) ?></span>
              <span class="saved-card-date">Saved <?= date('M j', strtotime($item['saved_at'])) ?></span>
            </div>
          </div>

          <!-- Actions: View & Remove -->
          <div class="saved-card-actions">
            <a href="listing.php?id=<?= (int)$item['item_id'] ?>" class="saved-btn-view <?= $item['status'] !== 'active' ? 'saved-btn-view-disabled' : '' ?>">
              View
            </a>
            <form method="POST">
              <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">
              <button type="submit" name="unsave_item" class="saved-btn-remove" data-confirm="Remove this item from your saved list?">
                Remove
              </button>
            </form>
          </div>

        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>