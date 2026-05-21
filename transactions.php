<!-- PHP Logic (Authentication, Session, etc.) -->
<?php
  /*
    transactions.php - Transaction Management
    Buyers see their purchases, sellers see their sales.
    Both can update transaction status.
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

<!-- Database Query -->
<?php 
  // Handle status update POST
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $txn_id = isset($_POST['transaction_id']) ? (int)$_POST['transaction_id'] : 0;
    $newStatus = strtolower(trim($_POST['status'] ?? ''));
    $allowed = ['completed', 'cancelled'];

    if ($txn_id > 0 && in_array($newStatus, $allowed)) {
      // Only allow the buyer or seller of this transaction to update it
      $stmt = $pdo->prepare("
        SELECT * FROM transactions WHERE transaction_id = ? AND (buyer_id = ? OR seller_id = ?)
      ");
      $stmt->execute([$txn_id, $uid, $uid]);
      $txn = $stmt->fetch();

      if ($txn) {
        // Only seller can complete request
        if ($newStatus === 'completed' && (int)$txn['seller_id'] !== $uid) {
          header("Location: transactions.php");
          exit;
        }

        if ($newStatus === 'completed' && (int)$txn['seller_id'] === $uid) {
          // Mark transaction completed and set completed_at timestamp
          $pdo->prepare("
            UPDATE transactions SET status = 'completed', completed_at = NOW()
            WHERE transaction_id = ?
          ")->execute([$txn_id]);

          // Mark the item as sold
          $pdo->prepare("
            UPDATE items 
            SET status = 'sold' 
            WHERE item_id = ? AND status != 'sold'
          ")->execute([$txn['item_id']]);

          require_once 'includes/notify.php';
          insertNotification(
            $pdo,
            (int)$txn['buyer_id'],
            $uid,
            'transaction',
            'Your transaction has been marked as completed.',
            'transactions.php?view=buying&status=completed'
          );

        } elseif ($newStatus === 'cancelled') {

          // Transaction is cancelled
          $pdo->prepare("
            UPDATE transactions SET status = 'cancelled' WHERE transaction_id = ?
          ")->execute([$txn_id]);

          $pdo->prepare("
            UPDATE items 
            SET status = 'active' 
            WHERE item_id = ?
            AND NOT EXISTS (
              SELECT 1 FROM transactions 
              WHERE item_id = ? 
              AND status = 'pending'
              AND transaction_id != ?
            )
          ")->execute([$txn['item_id'], $txn['item_id'], $txn_id]);

          require_once 'includes/notify.php';
          // Notify the other party
          $notify_user = ($uid === (int)$txn['seller_id']) ? (int)$txn['buyer_id'] : (int)$txn['seller_id'];
          insertNotification(
            $pdo,
            $notify_user,
            $uid,
            'transaction',
            'A transaction has been cancelled.',
            'transactions.php'
          );
        }
      }
    }

    header("Location: transactions.php");
    exit;
  }

  // Handle create transaction POST
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_transaction'])) {

    $item_id = (int)($_POST['item_id'] ?? 0);
    $buyer_id = (int)($_POST['buyer_id'] ?? 0);

    // Basic validation
    if ($item_id <= 0 || $buyer_id <= 0) {
      exit;
    }

    // Prevent self-transaction
    if ($buyer_id === $uid) {
      exit;
    }

    $stmt = $pdo->prepare("
      SELECT * FROM items 
      WHERE item_id = ? 
      AND seller_id = ? 
      AND status = 'active'
    ");
    $stmt->execute([$item_id, $uid]);
    $item = $stmt->fetch();

    // If item doesn't exist or doesn't belong to seller then STOP
    if (!$item) {
      exit;
    }

    // Check theres no pending transaction already exists
    $stmt = $pdo->prepare("
      SELECT transaction_id FROM transactions
      WHERE item_id = ? AND status = 'pending'
      LIMIT 1
    ");
    $stmt->execute([$item_id]);

    if (!$stmt->fetch()) {

      // Check if there is a valid message between buyer and seller
      $stmt = $pdo->prepare("
        SELECT 1 FROM messages
        WHERE item_id = ?
        AND ((sender_id = ? AND receiver_id = ?)
          OR (sender_id = ? AND receiver_id = ?))
        LIMIT 1
      ");
      $stmt->execute([$item_id, $buyer_id, $uid, $uid, $buyer_id]);

      if ($stmt->fetch()) {

        // Insert transaction
        $pdo->prepare("
          INSERT INTO transactions (buyer_id, seller_id, item_id, amount, status)
          VALUES (?, ?, ?, ?, 'pending')
        ")->execute([$buyer_id, $uid, $item_id, $item['price']]);

        // Insert transaction
        $pdo->prepare("
          UPDATE items 
          SET status = 'reserved' 
          WHERE item_id = ?
          AND status = 'active'
        ")->execute([$item_id]);

        require_once 'includes/notify.php';
        insertNotification(
          $pdo,
          $buyer_id,
          $uid,
          'transaction',
          'A seller created a transaction with you. Check your transactions.',
          'transactions.php?view=buying&status=pending'
        );
      }
    }

    header("Location: transactions.php");
    exit;
  }

  // View toggle: am I looking at my purchases or my sales?
  $view = $_GET['view'] ?? 'buying';
  if (!in_array($view, ['buying', 'selling'])) $view = 'buying';

  // Filter by status
  $status_filter = $_GET['status'] ?? 'all';
  $allowed_statuses = ['all', 'pending', 'completed', 'cancelled'];
  if (!in_array($status_filter, $allowed_statuses)) $status_filter = 'all';

  // Build query
  if ($view === 'buying') {
    $where = $status_filter === 'all' ? "t.buyer_id = ?" : "t.buyer_id = ? AND t.status = ?";
    $params = $status_filter === 'all' ? [$uid] : [$uid, $status_filter];
  } else {
    $where = $status_filter === 'all' ? "t.seller_id = ?" : "t.seller_id = ? AND t.status = ?";
    $params = $status_filter === 'all' ? [$uid] : [$uid, $status_filter];
  }

  $stmt = $pdo->prepare("
    SELECT
      t.*,
      i.title AS item_title, i.image_path AS item_image, i.meetup_location,
      buyer.name AS buyer_name,
      seller.name AS seller_name
    FROM transactions t
    JOIN items i ON t.item_id = i.item_id
    JOIN users buyer ON t.buyer_id  = buyer.id
    JOIN users seller ON t.seller_id = seller.id
    WHERE $where
    ORDER BY t.created_at DESC
  ");
  $stmt->execute($params);
  $transactions = $stmt->fetchAll();

  // Count per status for the tabs
  if ($view === 'buying') {
    $countStmt = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM transactions WHERE buyer_id = ? GROUP BY status");
  } else {
    $countStmt = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM transactions WHERE seller_id = ? GROUP BY status");
  }
  $countStmt->execute([$uid]);
  $counts = ['all' => 0, 'pending' => 0, 'completed' => 0, 'cancelled' => 0];
  foreach ($countStmt->fetchAll() as $row) {
    $counts[$row['status']] = (int)$row['cnt'];
    $counts['all'] += (int)$row['cnt'];
  }
?>

<!-- PHP UI/UX Logic -->
<?php
  $activePage = 'transactions';
  $pageTitle = "Transactions";
  include "includes/header.php";
?>

<div class="transactions-page">

  <div class="transactions-header">
    <div>
      <h1>Transactions</h1>
      <p>Track your buying and selling activity.</p>
    </div>
  </div>

  <!-- Buying / Selling toggle -->
  <div class="txn-view-toggle">
    <a href="?view=buying&status=<?= $status_filter ?>" class="txn-view-btn <?= $view === 'buying' ? 'active' : '' ?>">My Purchases</a>
    <a href="?view=selling&status=<?= $status_filter ?>" class="txn-view-btn <?= $view === 'selling' ? 'active' : '' ?>">My Sales</a>
  </div>

  <!-- Status filter tabs -->
  <div class="listings-filter-tabs">
    <?php foreach (['all' => 'All', 'pending' => 'Pending', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $key => $label): ?>
      <a href="?view=<?= $view ?>&status=<?= $key ?>" class="filter-tab <?= $status_filter === $key ? 'active' : '' ?>">
        <?= $label ?> (<?= $counts[$key] ?>)
      </a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($transactions)): ?>
    <div class="empty-state" style="padding: 64px 24px;">
      <h3>No transactions found</h3>
      <p><?= $view === 'buying' ? "You haven't made any purchases yet." : "You don't have any sales yet." ?></p>
    </div>

  <?php else: ?>
    <div class="transactions-list">
      <?php foreach ($transactions as $txn): ?>
        <div class="txn-card">

          <!-- Item info -->
          <div class="txn-item-info">
            <div class="txn-item-img">
              <?php
                $txnImg = "uploads/" . $txn['item_image'];
                if (!empty($txn['item_image']) && file_exists($txnImg)):
              ?>
                <img src="<?= htmlspecialchars($txnImg) ?>" alt="" />
              <?php else: ?>
                <?= $imgNotAvailableIcon ?>
              <?php endif; ?>
            </div>
            <div>
              <a href="listing.php?id=<?= (int)$txn['item_id'] ?>" class="txn-item-title">
                <?= htmlspecialchars($txn['item_title']) ?>
              </a>
              <div class="txn-parties">
                <?php if ($view === 'buying'): ?>
                  Seller: <strong><?= htmlspecialchars($txn['seller_name']) ?></strong>
                <?php else: ?>
                  Buyer: <strong><?= htmlspecialchars($txn['buyer_name']) ?></strong>
                <?php endif; ?>
              </div>
              <?php if (!empty($txn['meetup_location'])): ?>
                <div class="txn-meetup"><?= $locationIcon ?> <?= htmlspecialchars($txn['meetup_location']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Amount & status -->
          <div class="txn-status-col">
            <div class="txn-amount">&#8369;<?= number_format($txn['amount'], 2) ?></div>
            <span class="txn-badge txn-badge-<?= $txn['status'] ?>">
              <?= ucfirst($txn['status']) ?>
            </span>
            <div class="txn-date"><?= date('M j, Y', strtotime($txn['created_at'])) ?></div>
            <?php if (!empty($txn['completed_at'])): ?>
              <div class="txn-date">Completed: <?= date('M j, Y', strtotime($txn['completed_at'])) ?></div>
            <?php endif; ?>
          </div>

          <!-- Action buttons (only on pending transactions) -->
          <?php if ($txn['status'] === 'pending'): ?>
            <div class="txn-actions">

              <?php if ($view === 'selling'): ?>
                <!-- Seller can mark as completed -->
                <form method="POST">
                  <input type="hidden" name="transaction_id" value="<?= (int)$txn['transaction_id'] ?>">
                  <input type="hidden" name="status" value="completed">
                  <button type="submit" name="update_status" class="btn-txn-complete" data-confirm="Mark this deal as completed? The item will be marked as sold." data-confirm-green>
                    Mark as Sold
                  </button>
                </form>
              <?php endif; ?>

              <!-- Both buyer and seller can cancel -->
              <form method="POST">
                <input type="hidden" name="transaction_id" value="<?= (int)$txn['transaction_id'] ?>">
                <input type="hidden" name="status" value="cancelled">
                <button type="submit" name="update_status" class="btn-txn-cancel" data-confirm="Cancel this transaction?">
                  Cancel
                </button>
              </form>

              <!-- Message link -->
              <?php
                $other_id = $view === 'buying' ? (int)$txn['seller_id'] : (int)$txn['buyer_id'];
              ?>
              <a href="messages.php?to=<?= $other_id ?>&item=<?= (int)$txn['item_id'] ?>" class="btn-txn-msg">
                Message
              </a>

            </div>
          <?php endif; ?>

        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>