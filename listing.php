<?php
  // PHP Logic (Authentication, Session, etc.)
  /*
    listing.php - Individual Item Listing Page
    Shows full item details, seller info, comments, and actions.
  */
  session_start();
  require_once "includes/db.php";

  $loggedInUser = $_SESSION['user'] ?? null;

  // Database Query
  $item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  if ($item_id <= 0) {
    header('Location: browse.php'); exit;
  }

  $stmt = $pdo->prepare("
    SELECT items.*, categories.name AS category,
      users.id AS seller_id, users.name AS seller_name,
      users.avatar AS seller_avatar, users.course AS seller_course,
      users.bio AS seller_bio
    FROM items
    JOIN categories ON items.category_id = categories.category_id
    JOIN users ON items.seller_id = users.id
    WHERE items.item_id = ? LIMIT 1
  ");
  $stmt->execute([$item_id]);
  $item = $stmt->fetch();

  if (!$item) {
    header('Location: browse.php'); exit;
  }

  if ($loggedInUser && isset($_POST['delete_item_id']) && (int)$loggedInUser['id'] === (int)$item['seller_id']) {
    $delete_id = (int)$_POST['delete_item_id'];
    require_once "includes/delete-item.php";
    deleteItemWithImage($pdo, $delete_id, (int)$loggedInUser['id']);
    header("Location: my-listings.php"); exit;
  }

  if (!isset($_SESSION['viewed_items'])) $_SESSION['viewed_items'] = [];
  if (
    (!$loggedInUser || (int)$loggedInUser['id'] !== (int)$item['seller_id']) &&
    !in_array($item_id, $_SESSION['viewed_items'])
  ) {
    $pdo->prepare("UPDATE items SET views = views + 1 WHERE item_id = ?")->execute([$item_id]);
    $_SESSION['viewed_items'][] = $item_id;
  }

  $stmt = $pdo->prepare("
    SELECT transactions.*, users.name AS buyer_name
    FROM transactions JOIN users ON transactions.buyer_id = users.id
    WHERE transactions.item_id = ?
    ORDER BY CASE status WHEN 'pending' THEN 0 WHEN 'completed' THEN 1 ELSE 2 END, transactions.created_at DESC
    LIMIT 1
  ");
  $stmt->execute([$item_id]);
  $transaction = $stmt->fetch();
  $txn_status = $transaction ? $transaction['status'] : null;
  $txn_buyer_id = $transaction ? (int)$transaction['buyer_id'] : null;
  $txn_id = $transaction ? (int)$transaction['transaction_id'] : null;
  $is_saved = false;

  if ($loggedInUser) {
    $stmt = $pdo->prepare("SELECT 1 FROM saved_items WHERE user_id = ? AND item_id = ? LIMIT 1");
    $stmt->execute([(int)$loggedInUser['id'], $item_id]);
    $is_saved = (bool)$stmt->fetch();
  }

  if ($loggedInUser && isset($_POST['toggle_save'])) {
    if ($is_saved) {
      $pdo->prepare("DELETE FROM saved_items WHERE user_id = ? AND item_id = ?")->execute([(int)$loggedInUser['id'], $item_id]);
      $is_saved = false;
    } else {
      $pdo->prepare("INSERT IGNORE INTO saved_items (user_id, item_id) VALUES (?, ?)")->execute([(int)$loggedInUser['id'], $item_id]);
      $is_saved = true;
    }
  }

  $comment_error   = '';
  $comment_success = false;

  if ($loggedInUser && isset($_POST['submit_comment'])) {
    $comment_text = trim($_POST['comment'] ?? '');
    if ($comment_text === '') {
      $comment_error = 'Comment cannot be empty.';
    } elseif (strlen($comment_text) > 1000) {
      $comment_error = 'Comment must be 1000 characters or less.';
    } else {
      $pdo->prepare("INSERT INTO comments (commenter_id, item_id, comment) VALUES (?, ?, ?)")->execute([(int)$loggedInUser['id'], $item_id, $comment_text]);
      $comment_success = true;
    }
  }

  // Fetch top-level comments with replies
  $stmt = $pdo->prepare("
      SELECT
          c.comment_id, c.comment, c.created_at, c.updated_at,
          c.is_deleted, c.parent_id, c.commenter_id, c.reply_to_name,
          users.name AS commenter_name,
          users.avatar AS commenter_avatar
      FROM comments c
      JOIN users ON c.commenter_id = users.id
      WHERE c.item_id = ?
      ORDER BY c.parent_id ASC, c.created_at ASC
  ");
  $stmt->execute([$item_id]);
  $all_comments = $stmt->fetchAll();

  $top_comments = [];
  $replies_map  = [];
  foreach ($all_comments as $c) {
      if ($c['parent_id'] === null) {
          $top_comments[] = $c;
      } else {
          $replies_map[$c['parent_id']][] = $c;
      }
  }
  $comment_count = count($all_comments);

  $stmt = $pdo->prepare("
    SELECT items.item_id, items.title, items.price, items.image_path, categories.name AS category
    FROM items JOIN categories ON items.category_id = categories.category_id
    WHERE items.seller_id = ? AND items.item_id != ? AND items.status = 'active'
    ORDER BY items.created_at DESC LIMIT 4
  ");
  $stmt->execute([(int)$item['seller_id'], $item_id]);
  $seller_listings = $stmt->fetchAll();

  require_once "includes/reactions.php";
  $reaction_counts = getReactionCounts($pdo, $item_id);
  $user_reaction = $loggedInUser ? getUserReaction($pdo, $item_id, (int)$loggedInUser['id']) : null;

  $is_own_listing = $loggedInUser && (int)$loggedInUser['id'] === (int)$item['seller_id'];
  $is_buyer_in_txn = $loggedInUser && $txn_buyer_id === (int)$loggedInUser['id'];

  // PHP UI/UX Logic
  $activePage = '';
  $pageTitle = "Item Details";
  include "includes/header.php";
?>

<div class="lp-page">

  <a href="browse.php" class="lp-back">
    <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px"><path d="M560-240 320-480l240-240 56 56-184 184 184 184-56 56Z"/></svg>
    Back to Browse
  </a>

  <div class="lp-layout">

    <!-- LEFT: Image -->
    <div class="lp-image-col">
      <div class="lp-image-wrap">
        <?php
          $imgFile = "uploads/" . $item['image_path'];
          if (!empty($item['image_path']) && file_exists($imgFile)):
        ?>
          <img src="<?= htmlspecialchars($imgFile) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="lp-main-img" />
        <?php else: ?>
          <div class="lp-no-image"><?= $imgNotAvailableIcon ?></div>
        <?php endif; ?>

        <span class="lp-condition-badge"><?= htmlspecialchars($item['condition_type']) ?></span>

        <?php if ($item['status'] === 'sold'): ?>
          <div class="lp-overlay lp-overlay--sold">SOLD</div>
        <?php elseif ($txn_status === 'pending'): ?>
          <div class="lp-overlay lp-overlay--reserved">RESERVED</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- RIGHT: All details -->
    <div class="lp-detail-col">

      <!-- Title block -->
      <div class="lp-title-block">
        <span class="lp-category-tag"><?= htmlspecialchars($item['category']) ?></span>
        <h1 class="lp-title"><?= htmlspecialchars($item['title']) ?></h1>
        <div class="lp-price">&#8369;<?= number_format($item['price'], 2) ?></div>
        <div class="lp-meta">
          <span><?= $locationIcon ?><?= htmlspecialchars($item['meetup_location'] ?? 'Not specified') ?></span>
          <span><?= $visibilityOnIcon ?><?= (int)$item['views'] ?> views</span>
          <span>Posted <?= date('M j, Y', strtotime($item['created_at'])) ?></span>
        </div>
      </div>

      <!-- Action buttons row -->
      <div class="lp-actions">

        <?php if ($item['status'] === 'sold' && $txn_status !== 'pending'): ?>
          <div class="lp-status-badge lp-status--sold">Item Sold</div>

        <?php elseif ($is_own_listing): ?>

          <?php if ($txn_status === 'pending'): ?>
            <div class="lp-status-badge lp-status--pending">Transaction Pending</div>
            <div class="lp-txn-buyer">Buyer: <strong><?= htmlspecialchars($transaction['buyer_name']) ?></strong></div>
            <div class="lp-action-btns">
              <a href="messages.php?to=<?= $txn_buyer_id ?>&item=<?= $item_id ?>" class="lp-action-btn lp-action-btn--primary lp-action-btn--grow" title="Message Buyer">
                <?= $messageIcon ?>
                <span>Message Buyer</span>
              </a>
              <form method="POST" action="transactions.php" style="margin:0;flex:1 1 0">
                <input type="hidden" name="transaction_id" value="<?= $txn_id ?>">
                <input type="hidden" name="status" value="completed">
                <button type="submit" name="update_status" class="lp-action-btn lp-action-btn--success" style="width:100%" title="Mark as Sold" data-confirm="Mark this transaction as completed? The item will be marked as sold." data-confirm-green>
                  <?= $circleCheckIcon ?>
                  <span>Mark Sold</span>
                </button>
              </form>
              <form method="POST" action="transactions.php" style="margin:0;width:100%">
                <input type="hidden" name="transaction_id" value="<?= $txn_id ?>">
                <input type="hidden" name="status" value="cancelled">
                <button type="submit" name="update_status" class="lp-action-btn lp-action-btn--danger lp-action-btn--full" title="Cancel Transaction" data-confirm="Cancel this transaction? The item will be available again.">
                  <?= $circleCrossIcon ?>
                  <span>Cancel Transaction</span>
                </button>
              </form>
            </div>

          <?php else: ?>
            <div class="lp-action-btns">
              <a href="edit-listing.php?id=<?= $item_id ?>" class="lp-action-btn lp-action-btn--primary" title="Edit Listing">
                <?= $editIcon ?>
                <span>Edit Listing</span>
              </a>
              <form method="POST" style="margin:0">
                <input type="hidden" name="delete_item_id" value="<?= $item_id ?>">
                <button type="submit" class="lp-action-btn lp-action-btn--danger" title="Delete Listing" data-confirm="Delete this listing? This cannot be undone.">
                  <?= $deleteIcon ?>
                  <span>Delete</span>
                </button>
              </form>
            </div>
          <?php endif; ?>

        <?php elseif ($loggedInUser): ?>

          <?php if ($txn_status === 'pending' && $is_buyer_in_txn): ?>
            <div class="lp-status-badge lp-status--pending">Your Transaction is Pending</div>
            <div class="lp-action-btns">
              <a href="messages.php?to=<?= (int)$item['seller_id'] ?>&item=<?= $item_id ?>" class="lp-action-btn lp-action-btn--primary lp-action-btn--grow" title="Message Seller">
                <?= $messageIcon ?>
                <span>Message Seller</span>
              </a>
              <button type="button" class="lp-action-btn lp-action-btn--ghost" title="Report Listing" onclick="openReportModal('listing', <?= $item_id ?>, <?= htmlspecialchars(json_encode($item['title']), ENT_QUOTES, 'UTF-8') ?>)">
                <?= $reportIcon ?>
                <span>Report</span>
              </button>
              <form method="POST" action="transactions.php" style="margin:0;width:100%">
                <input type="hidden" name="transaction_id" value="<?= $txn_id ?>">
                <input type="hidden" name="status" value="cancelled">
                <button type="submit" name="update_status" class="lp-action-btn lp-action-btn--danger lp-action-btn--full" title="Cancel" data-confirm="Cancel this transaction?">
                  <?= $circleCrossIcon ?>
                  <span>Cancel Transaction</span>
                </button>
              </form>
            </div>

          <?php elseif ($txn_status === 'pending' && !$is_buyer_in_txn): ?>
            <div class="lp-status-badge lp-status--reserved">Reserved by Another Buyer</div>
            <div class="lp-action-btns">
              <form method="POST" style="margin:0">
                <button type="submit" name="toggle_save" class="lp-action-btn <?= $is_saved ? 'lp-action-btn--saved' : 'lp-action-btn--secondary' ?>" title="<?= $is_saved ? 'Remove from Saved' : 'Save Item' ?>">
                  <?= $bookmarkIcon ?>
                  <span><?= $is_saved ? 'Saved' : 'Save' ?></span>
                </button>
              </form>
              <button type="button" class="lp-action-btn lp-action-btn--ghost" title="Report Listing" onclick="openReportModal('listing', <?= $item_id ?>, <?= htmlspecialchars(json_encode($item['title']), ENT_QUOTES, 'UTF-8') ?>)">
                <?= $reportIcon ?>
                <span>Report</span>
              </button>
            </div>

          <?php elseif ($item['status'] === 'active'): ?>
            <div class="lp-action-btns">
              <a href="messages.php?to=<?= (int)$item['seller_id'] ?>&item=<?= $item_id ?>" class="lp-action-btn lp-action-btn--primary lp-action-btn--grow" title="Message Seller">
                <?= $messageIcon ?>
                <span>Message</span>
              </a>
              <form method="POST" style="margin:0">
                <button type="submit" name="toggle_save" class="lp-action-btn <?= $is_saved ? 'lp-action-btn--saved' : 'lp-action-btn--secondary' ?>" title="<?= $is_saved ? 'Remove from Saved' : 'Save Item' ?>">
                  <?= $bookmarkIcon ?>
                  <span><?= $is_saved ? 'Saved' : 'Save' ?></span>
                </button>
              </form>
              <button type="button" class="lp-action-btn lp-action-btn--ghost" title="Report Listing" onclick="openReportModal('listing', <?= $item_id ?>, <?= htmlspecialchars(json_encode($item['title']), ENT_QUOTES, 'UTF-8') ?>)">
                <?= $reportIcon ?>
                <span>Report</span>
              </button>
            </div>

          <?php else: ?>
            <div class="lp-status-badge lp-status--sold">Item Sold</div>
          <?php endif; ?>

        <?php else: ?>
          <div class="lp-action-btns">
            <a href="login.php" class="lp-action-btn lp-action-btn--primary" title="Log in to buy">
              <?= $loginIcon ?>
              <span>Log In to Buy</span>
            </a>
          </div>
          <p class="lp-guest-note">You need an account to message sellers or save items.</p>
        <?php endif; ?>

      </div>

      <!-- Reaction bar -->
      <div class="lp-reaction-section">
        <div class="lp-reaction-bar" data-item-id="<?= $item_id ?>">
          <div class="lp-reaction-trigger">
            <button class="lp-react-btn <?= $user_reaction ? 'lp-react-btn--active lp-react-btn--' . $user_reaction : '' ?>" id="reactionMainBtn" <?= !$loggedInUser ? 'data-guest="true"' : '' ?> aria-label="React">
              <?php if ($user_reaction): ?>
                <div class="lp-react-btn-emoji-wrap">
                  <img src="assets/img/<?= $user_reaction ?>-junimo-emoji.png" alt="<?= ucfirst($user_reaction) ?>" class="lp-react-btn-emoji" />
                </div>
              <?php else: ?>
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#434343"><path d="m305-704 112-145q12-16 28.5-23.5T480-880q18 0 34.5 7.5T543-849l112 145 170 57q26 8 41 29.5t15 47.5q0 12-3.5 24T866-523L756-367l4 164q1 35-23 59t-56 24q-2 0-22-3l-179-50-179 50q-5 2-11 2.5t-11 .5q-32 0-56-24t-23-59l4-165L95-523q-8-11-11.5-23T80-570q0-25 14.5-46.5T135-647l170-57Zm49 69-194 64 124 179-4 191 200-55 200 56-4-192 124-177-194-66-126-165-126 165Zm126 135Z"/></svg>
              <?php endif; ?>
            </button>

            <div class="lp-reaction-picker" id="reactionPicker" role="menu">
              <?php
                $emojis = ['like'=>'Like','heart'=>'Heart','laugh'=>'Laugh','wow'=>'Wow','cry'=>'Cry'];
                foreach ($emojis as $type => $label):
              ?>
                <button class="lp-reaction-opt <?= $user_reaction === $type ? 'lp-reaction-opt--active' : '' ?>" data-type="<?= $type ?>" aria-label="<?= $label ?>" role="menuitem">
                  <div class="lp-reaction-img-wrap">
                    <img src="assets/img/<?= $type ?>-junimo-emoji.png" alt="<?= $label ?>" class="lp-reaction-img" />
                  </div>
                  <span class="lp-reaction-opt-label"><?= $label ?></span>
                </button>
              <?php endforeach; ?>
            </div>
          </div>

          <?php $total_reactions = array_sum($reaction_counts); ?>
          <div class="lp-reaction-counts" id="reactionCounts" <?= $total_reactions === 0 ? 'style="display:none"' : '' ?>>
            <?php foreach ($reaction_counts as $type => $count): ?>
              <?php if ($count > 0): ?>
                <span class="lp-count-item" data-type="<?= $type ?>">
                  <div class="lp-count-emoji-wrap">
                    <img src="assets/img/<?= $type ?>-junimo-emoji.png" alt="<?= ucfirst($type) ?>" class="lp-count-emoji" />
                  </div>
                  <span class="lp-count-num"><?= $count ?></span>
                </span>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Description -->
      <?php if (!empty($item['description'])): ?>
        <div class="lp-section">
          <div class="lp-section-label">Description</div>
          <p class="lp-description-text"><?= nl2br(htmlspecialchars($item['description'])) ?></p>
        </div>
      <?php endif; ?>

      <?php if (!empty($item['contact_info'])): ?>
        <div class="lp-section">
          <div class="lp-section-label">Contact</div>
          <p class="lp-description-text"><?= htmlspecialchars($item['contact_info']) ?></p>
        </div>
      <?php endif; ?>

      <!-- Seller card -->
      <div class="lp-section">
        <div class="lp-section-label">Seller</div>
        <div class="lp-seller-card">
          <div class="lp-seller-avatar">
            <?php if (!empty($item['seller_avatar'])): ?>
              <img src="assets/img/<?= htmlspecialchars($item['seller_avatar']) ?>.png" alt="<?= htmlspecialchars($item['seller_name']) ?>" class="avatar-pixel-img"/>
            <?php else: ?>
              <?= strtoupper($item['seller_name'][0] ?? '?') ?>
            <?php endif; ?>
          </div>
          <div>
            <div class="lp-seller-name"><?= htmlspecialchars($item['seller_name']) ?></div>
            <?php if (!empty($item['seller_course'])): ?>
              <div class="lp-seller-course"><?= htmlspecialchars($item['seller_course']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- More from seller -->
      <?php if (!empty($seller_listings)): ?>
        <div class="lp-section">
          <div class="lp-section-label">More from this Seller</div>
          <div class="lp-more-listings">
            <?php foreach ($seller_listings as $other): ?>
              <a href="listing.php?id=<?= (int)$other['item_id'] ?>" class="lp-more-item">
                <div class="lp-more-img">
                  <?php
                    $otherImg = "uploads/" . $other['image_path'];
                    if (!empty($other['image_path']) && file_exists($otherImg)):
                  ?>
                    <img src="<?= htmlspecialchars($otherImg) ?>" alt="<?= htmlspecialchars($other['title']) ?>" />
                  <?php else: ?>
                    <?= $imgNotAvailableIcon ?>
                  <?php endif; ?>
                </div>
                <div class="lp-more-info">
                  <div class="lp-more-title"><?= htmlspecialchars($other['title']) ?></div>
                  <div class="lp-more-price">&#8369;<?= number_format($other['price'], 2) ?></div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Comments -->
      <div class="lp-section lp-comments-section">
        <div class="lp-section-label">Comments (<?= $comment_count ?>)</div>

        <?php if ($loggedInUser): ?>
          <form class="lp-comment-form" id="mainCommentForm">
            <input type="hidden" name="item_id" value="<?= $item_id ?>">
            <textarea name="comment" placeholder="<?= $is_own_listing ? 'Enter a comment on your listing' : 'Enter a comment' ?>" maxlength="5000" rows="3" required></textarea>
            <div class="lp-comment-form-footer">
              <span class="lp-form-hint">Max 5000 characters</span>
              <button type="submit" class="lp-btn-comment">Post Comment</button>
            </div>
          </form>
        <?php else: ?>
          <p class="lp-comment-login"><a href="login.php">Log in</a> to leave a comment.</p>
        <?php endif; ?>

        <div id="commentError" class="lp-alert lp-alert--error" style="display:none"></div>

        <p class="lp-no-comments" id="noCommentsMsg" <?= !empty($top_comments) ? 'style="display:none"' : '' ?>>
          No comments yet. Be the first to ask!
        </p>

        <div class="lp-comments-list" id="commentsList">
          <?php foreach ($top_comments as $c):
            $is_my_comment = $loggedInUser && (int)$c['commenter_id'] === (int)$loggedInUser['id'];
            $can_delete    = $loggedInUser && ($is_my_comment || $is_own_listing);
            $replies       = $replies_map[$c['comment_id']] ?? [];
          ?>
            <div class="lp-comment-item" id="comment-<?= $c['comment_id'] ?>">

              <!-- Avatar -->
              <div class="lp-comment-avatar">
                <?php if (!empty($c['commenter_avatar'])): ?>
                  <img src="assets/img/<?= htmlspecialchars($c['commenter_avatar']) ?>.png" alt="<?= htmlspecialchars($c['commenter_name']) ?>" class="lp-avatar-img" />
                <?php else: ?>
                  <?= strtoupper($c['commenter_name'][0] ?? '?') ?>
                <?php endif; ?>
              </div>

              <div class="lp-comment-body">
                <div class="lp-comment-bubble">
                  <span class="lp-comment-author"><?= htmlspecialchars($c['commenter_name']) ?></span>
                  <?php if ($c['is_deleted']): ?>
                    <p class="lp-comment-text lp-comment-deleted">This comment was deleted.</p>
                  <?php else: ?>
                    <p class="lp-comment-text" id="comment-text-<?= $c['comment_id'] ?>"><?= nl2br(htmlspecialchars($c['comment'], ENT_QUOTES, 'UTF-8')) ?></p>
                    <div class="lp-edit-form" id="edit-form-<?= $c['comment_id'] ?>" style="display:none">
                      <textarea class="lp-edit-textarea" maxlength="5000"><?= htmlspecialchars($c['comment'], ENT_QUOTES, 'UTF-8') ?></textarea>
                      <div class="lp-edit-actions">
                        <button type="button" class="lp-edit-cancel" data-id="<?= $c['comment_id'] ?>">Cancel</button>
                        <button type="button" class="lp-edit-save lp-btn-comment" data-id="<?= $c['comment_id'] ?>">Save</button>
                      </div>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="lp-comment-actions">
                  <span class="lp-comment-time"><?= date('M j, Y', strtotime($c['created_at'])) ?><?= $c['updated_at'] ? ' · edited' : '' ?></span>
                  <?php if ($loggedInUser && !$c['is_deleted']): ?>
                    <button class="lp-comment-action-btn lp-reply-btn" data-id="<?= $c['comment_id'] ?>" data-name="<?= htmlspecialchars($c['commenter_name'], ENT_QUOTES, 'UTF-8') ?>">Reply</button>
                  <?php endif; ?>
                  <?php if ($is_my_comment && !$c['is_deleted']): ?>
                    <button class="lp-comment-action-btn lp-edit-btn" data-id="<?= $c['comment_id'] ?>">Edit</button>
                  <?php endif; ?>
                  <?php if ($can_delete && !$c['is_deleted']): ?>
                    <button class="lp-comment-action-btn lp-delete-btn" data-id="<?= $c['comment_id'] ?>" data-item="<?= $item_id ?>">Delete</button>
                  <?php endif; ?>
                </div>

                <?php if ($loggedInUser): ?>
                  <div class="lp-reply-form-wrap" id="reply-form-<?= $c['comment_id'] ?>" style="display:none">
                    <form class="lp-reply-form">
                      <input type="hidden" name="item_id" value="<?= $item_id ?>">
                      <input type="hidden" name="parent_id" value="<?= $c['comment_id'] ?>">
                      <input type="hidden" name="reply_to_name" value="">
                      <textarea name="comment" placeholder="Write a reply" maxlength="5000" rows="2" required></textarea>
                      <div class="lp-edit-actions">
                        <button type="button" class="lp-edit-cancel lp-reply-cancel" data-id="<?= $c['comment_id'] ?>">Cancel</button>
                        <button type="submit" class="lp-btn-comment">Reply</button>
                      </div>
                    </form>
                  </div>
                <?php endif; ?>

                <div class="lp-replies-list" id="replies-<?= $c['comment_id'] ?>">
                  <?php foreach ($replies as $r):
                    $is_my_reply = $loggedInUser && (int)$r['commenter_id'] === (int)$loggedInUser['id'];
                    $can_del_reply = $loggedInUser && ($is_my_reply || $is_own_listing);
                  ?>
                    <div class="lp-comment-item lp-reply-item" id="comment-<?= $r['comment_id'] ?>">

                      <div class="lp-comment-avatar lp-reply-avatar">
                        <?php if (!empty($r['commenter_avatar'])): ?>
                          <img src="assets/img/<?= htmlspecialchars($r['commenter_avatar']) ?>.png" alt="<?= htmlspecialchars($r['commenter_name']) ?>" class="lp-avatar-img" />
                        <?php else: ?>
                          <?= strtoupper($r['commenter_name'][0] ?? '?') ?>
                        <?php endif; ?>
                      </div>

                      <div class="lp-comment-body">
                        <div class="lp-comment-bubble">
                          <span class="lp-comment-author"><?= htmlspecialchars($r['commenter_name']) ?></span>
                          <?php if (!empty($r['reply_to_name'])): ?>
                            <span class="lp-reply-to-tag">Replying to <?= htmlspecialchars($r['reply_to_name'], ENT_QUOTES, 'UTF-8') ?></span>
                          <?php endif; ?>
                          <?php if ($r['is_deleted']): ?>
                            <p class="lp-comment-text lp-comment-deleted">This comment was deleted.</p>
                          <?php else: ?>
                            <p class="lp-comment-text" id="comment-text-<?= $r['comment_id'] ?>"><?= nl2br(htmlspecialchars($r['comment'], ENT_QUOTES, 'UTF-8')) ?></p>
                            <div class="lp-edit-form" id="edit-form-<?= $r['comment_id'] ?>" style="display:none">
                              <textarea class="lp-edit-textarea" maxlength="5000"><?= htmlspecialchars($r['comment'], ENT_QUOTES, 'UTF-8') ?></textarea>
                              <div class="lp-edit-actions">
                                <button type="button" class="lp-edit-cancel" data-id="<?= $r['comment_id'] ?>">Cancel</button>
                                <button type="button" class="lp-edit-save lp-btn-comment" data-id="<?= $r['comment_id'] ?>">Save</button>
                              </div>
                            </div>
                          <?php endif; ?>
                        </div>

                        <div class="lp-comment-actions">
                          <span class="lp-comment-time"><?= date('M j, Y', strtotime($r['created_at'])) ?><?= $r['updated_at'] ? ' · edited' : '' ?></span>
                          <?php if ($loggedInUser && !$r['is_deleted']): ?>
                            <button class="lp-comment-action-btn lp-reply-btn" data-id="<?= $c['comment_id'] ?>" data-name="<?= htmlspecialchars($r['commenter_name'], ENT_QUOTES, 'UTF-8') ?>" data-replyto="<?= htmlspecialchars($r['commenter_name'], ENT_QUOTES, 'UTF-8') ?>">Reply</button>
                          <?php endif; ?>
                          <?php if ($is_my_reply && !$r['is_deleted']): ?>
                            <button class="lp-comment-action-btn lp-edit-btn" data-id="<?= $r['comment_id'] ?>">Edit</button>
                          <?php endif; ?>
                          <?php if ($can_del_reply && !$r['is_deleted']): ?>
                            <button class="lp-comment-action-btn lp-delete-btn" data-id="<?= $r['comment_id'] ?>" data-item="<?= $item_id ?>">Delete</button>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>

              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Comments Section Script -->
<script>
  (function () {
    const bar = document.querySelector('.lp-reaction-bar');
    if (!bar) return;
    const itemId = bar.dataset.itemId;
    const mainBtn = document.getElementById('reactionMainBtn');
    const picker = document.getElementById('reactionPicker');
    const countsEl = document.getElementById('reactionCounts');
    if (!mainBtn || !picker) return;
    let pickerOpen = false;
    let hoverIntent = null;

    if (mainBtn.dataset.guest === 'true') {
      mainBtn.addEventListener('click', function () { window.location.href = 'login.php'; });
      return;
    }

    function openPicker() {
      picker.classList.add('lp-picker--visible');
      pickerOpen = true;

      // Reset first
      picker.style.left = '';
      picker.style.right = '';
      picker.style.transform = '';

      const rect = picker.getBoundingClientRect();
      const viewportWidth = window.innerWidth;

      if (rect.left < 8) {
        // Clipping on the left
        picker.style.left = '0';
        picker.style.right = 'auto';
        picker.style.transform = 'translateY(0) scale(1)';
      } else if (rect.right > viewportWidth - 8) {
        // Clipping on the right
        picker.style.left = 'auto';
        picker.style.right = '0';
        picker.style.transform = 'translateY(0) scale(1)';
      } else {
        picker.style.transform = 'translateY(0) scale(1)';
      }
    }

    function closePicker() {
      picker.classList.remove('lp-picker--visible');
      pickerOpen = false;
      picker.style.left = '';
      picker.style.right = '';
      picker.style.transform = '';
    }

    mainBtn.addEventListener('mouseenter', function () { hoverIntent = setTimeout(openPicker, 180); });
    mainBtn.addEventListener('mouseleave', function () { clearTimeout(hoverIntent); hoverIntent = setTimeout(closePicker, 220); });
    picker.addEventListener('mouseenter', function () { clearTimeout(hoverIntent); });
    picker.addEventListener('mouseleave', function () { hoverIntent = setTimeout(closePicker, 200); });

    mainBtn.addEventListener('click', function () {
      const cur = mainBtn.dataset.currentReaction;
      if (cur && !pickerOpen) { submitReaction(cur); return; }
      pickerOpen ? closePicker() : openPicker();
    });

    document.addEventListener('click', function (e) { if (!bar.contains(e.target)) closePicker(); });

    picker.querySelectorAll('.lp-reaction-opt').forEach(function (btn) {
      btn.addEventListener('click', function () { submitReaction(btn.dataset.type); closePicker(); });
    });

    function submitReaction(type) {
      const fd = new FormData();
      fd.append('item_id', itemId);
      fd.append('reaction_type', type);
      fetch('api/react.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (!d.error) { updateBtn(d.reaction_type); updateCounts(d.counts); } })
        .catch(function () {});
    }

    function updateBtn(activeType) {
      ['like','heart','laugh','wow','cry'].forEach(function (t) { mainBtn.classList.remove('lp-react-btn--' + t); });
      if (activeType) {
        mainBtn.classList.add('lp-react-btn--active', 'lp-react-btn--' + activeType);
        mainBtn.dataset.currentReaction = activeType;
        mainBtn.innerHTML = '<div class="lp-react-btn-emoji-wrap"><img src="assets/img/' + activeType + '-junimo-emoji.png" alt="' + cap(activeType) + '" class="lp-react-btn-emoji" /></div>';
      } else {
        mainBtn.classList.remove('lp-react-btn--active');
        delete mainBtn.dataset.currentReaction;
        mainBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#434343"><path d="m305-704 112-145q12-16 28.5-23.5T480-880q18 0 34.5 7.5T543-849l112 145 170 57q26 8 41 29.5t15 47.5q0 12-3.5 24T866-523L756-367l4 164q1 35-23 59t-56 24q-2 0-22-3l-179-50-179 50q-5 2-11 2.5t-11 .5q-32 0-56-24t-23-59l4-165L95-523q-8-11-11.5-23T80-570q0-25 14.5-46.5T135-647l170-57Zm49 69-194 64 124 179-4 191 200-55 200 56-4-192 124-177-194-66-126-165-126 165Zm126 135Z"/></svg>';
      }
      picker.querySelectorAll('.lp-reaction-opt').forEach(function (o) {
        o.classList.toggle('lp-reaction-opt--active', o.dataset.type === activeType);
      });
    }

    function updateCounts(counts) {
      const types = ['like','heart','laugh','wow','cry'];
      let total = 0;
      types.forEach(function (type) {
        const count = counts[type] || 0;
        total += count;
        let item = countsEl.querySelector('[data-type="' + type + '"]');
        if (count > 0) {
          if (!item) {
            item = document.createElement('span');
            item.className = 'lp-count-item';
            item.dataset.type = type;
            item.innerHTML = '<div class="lp-count-emoji-wrap"><img src="assets/img/' + type + '-junimo-emoji.png" alt="' + cap(type) + '" class="lp-count-emoji" /></div><span class="lp-count-num">' + count + '</span>';
            countsEl.appendChild(item);
          } else {
            item.querySelector('.lp-count-num').textContent = count;
          }
        } else if (item) {
          item.remove();
        }
      });
      countsEl.style.display = total > 0 ? 'flex' : 'none';
    }

    function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

    const active = picker.querySelector('.lp-reaction-opt--active');
    if (active) mainBtn.dataset.currentReaction = active.dataset.type;
  })();
</script>

<!-- Reply System Script -->
<script>
(function () {
  const list = document.getElementById('commentsList');
  const noMsg = document.getElementById('noCommentsMsg');
  const errorEl = document.getElementById('commentError');
  const mainForm = document.getElementById('mainCommentForm');
  const itemId = <?= $item_id ?>;
  const loggedIn = <?= $loggedInUser ? 'true' : 'false' ?>;
  const currentUid = <?= $loggedInUser ? (int)$loggedInUser['id'] : 0 ?>;
  const isSeller = <?= $is_own_listing ? 'true' : 'false' ?>;

  function showError(msg) {
    errorEl.textContent = msg;
    errorEl.style.display = 'block';
    setTimeout(function () { errorEl.style.display = 'none'; }, 4000);
  }

  function esc(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(String(str)));
    return d.innerHTML;
  }

  function avatarHTML(name, avatar, isReply) {
    const cls = isReply ? 'lp-comment-avatar lp-reply-avatar' : 'lp-comment-avatar';
    if (avatar) {
      return `<div class="${cls}"><img src="assets/img/${esc(avatar)}.png" alt="${esc(name)}" class="lp-avatar-img"/></div>`;
    }
    return `<div class="${cls}">${esc(name.charAt(0).toUpperCase())}</div>`;
  }

  function buildReplyHTML(r) {
    const canDel = r.is_owner || isSeller;
    const canEdit = r.is_owner;
    const replyToTag = r.reply_to_name ? `<span class="lp-reply-to-tag">Replying to ${esc(r.reply_to_name)}</span>` : '';
    return `
      <div class="lp-comment-item lp-reply-item" id="comment-${r.comment_id}">
        ${avatarHTML(r.commenter_name, r.commenter_avatar || null, true)}
        <div class="lp-comment-body">
          <div class="lp-comment-bubble">
            <span class="lp-comment-author">${esc(r.commenter_name)}</span>
            ${replyToTag}
            <p class="lp-comment-text" id="comment-text-${r.comment_id}">${esc(r.comment).replace(/\n/g,'<br>')}</p>
            <div class="lp-edit-form" id="edit-form-${r.comment_id}" style="display:none">
              <textarea class="lp-edit-textarea" maxlength="5000">${esc(r.comment)}</textarea>
              <div class="lp-edit-actions">
                <button type="button" class="lp-edit-cancel" data-id="${r.comment_id}">Cancel</button>
                <button type="button" class="lp-edit-save lp-btn-comment" data-id="${r.comment_id}">Save</button>
              </div>
            </div>
          </div>
          <div class="lp-comment-actions">
            <span class="lp-comment-time">${esc(r.created_at)}</span>
            ${loggedIn ? `<button class="lp-comment-action-btn lp-reply-btn" data-id="${r.parent_id}" data-name="${esc(r.commenter_name)}" data-replyto="${esc(r.commenter_name)}">Reply</button>` : ''}
            ${canEdit ? `<button class="lp-comment-action-btn lp-edit-btn" data-id="${r.comment_id}">Edit</button>` : ''}
            ${canDel ? `<button class="lp-comment-action-btn lp-delete-btn" data-id="${r.comment_id}" data-item="${itemId}">Delete</button>` : ''}
          </div>
        </div>
      </div>`;
  }

  function buildCommentHTML(c) {
    const canDel = c.is_owner || isSeller;
    const canEdit = c.is_owner;
    return `
      <div class="lp-comment-item" id="comment-${c.comment_id}">
        ${avatarHTML(c.commenter_name, c.commenter_avatar || null, false)}
        <div class="lp-comment-body">
          <div class="lp-comment-bubble">
            <span class="lp-comment-author">${esc(c.commenter_name)}</span>
            <p class="lp-comment-text" id="comment-text-${c.comment_id}">${esc(c.comment).replace(/\n/g,'<br>')}</p>
            <div class="lp-edit-form" id="edit-form-${c.comment_id}" style="display:none">
              <textarea class="lp-edit-textarea" maxlength="5000">${esc(c.comment)}</textarea>
              <div class="lp-edit-actions">
                <button type="button" class="lp-edit-cancel" data-id="${c.comment_id}">Cancel</button>
                <button type="button" class="lp-edit-save lp-btn-comment" data-id="${c.comment_id}">Save</button>
              </div>
            </div>
          </div>
          <div class="lp-comment-actions">
            <span class="lp-comment-time">${esc(c.created_at)}</span>
            ${loggedIn ? `<button class="lp-comment-action-btn lp-reply-btn" data-id="${c.comment_id}" data-name="${esc(c.commenter_name)}" data-replyto="">Reply</button>` : ''}
            ${canEdit ? `<button class="lp-comment-action-btn lp-edit-btn" data-id="${c.comment_id}">Edit</button>` : ''}
            ${canDel ? `<button class="lp-comment-action-btn lp-delete-btn" data-id="${c.comment_id}" data-item="${itemId}">Delete</button>` : ''}
          </div>
          ${loggedIn ? `
            <div class="lp-reply-form-wrap" id="reply-form-${c.comment_id}" style="display:none">
              <form class="lp-reply-form">
                <input type="hidden" name="item_id" value="${itemId}">
                <input type="hidden" name="parent_id" value="${c.comment_id}">
                <input type="hidden" name="reply_to_name" value="">
                <textarea name="comment" placeholder="Write a reply" maxlength="5000" rows="2" required></textarea>
                <div class="lp-edit-actions">
                  <button type="button" class="lp-edit-cancel lp-reply-cancel" data-id="${c.comment_id}">Cancel</button>
                  <button type="submit" class="lp-btn-comment">Reply</button>
                </div>
              </form>
            </div>` : ''}
          <div class="lp-replies-list" id="replies-${c.comment_id}"></div>
        </div>
      </div>`;
  }

  // Post main comment
  if (mainForm) {
    mainForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const textarea = mainForm.querySelector('textarea');
      const text = textarea.value.trim();
      if (!text) return;

      const fd = new FormData();
      fd.append('item_id', itemId);
      fd.append('comment', text);

      fetch('api/comment-reply.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.error) { showError(d.error); return; }
          d.is_owner = true;
          d.commenter_avatar = <?= $loggedInUser ? json_encode($_SESSION['user']['avatar'] ?? null) : 'null' ?>;
          const html = buildCommentHTML(d);
          list.insertAdjacentHTML('afterbegin', html);
          noMsg.style.display = 'none';
          textarea.value = '';
          updateCount(1);
        })
        .catch(function () { showError('Something went wrong.'); });
    });
  }

  // Delegate all comment interactions
  document.addEventListener('click', function (e) {

    // Reply button
    const replyBtn = e.target.closest('.lp-reply-btn');
    if (replyBtn) {
      const topId = replyBtn.dataset.id;
      const replyTo = replyBtn.dataset.replyto || '';
      const wrap = document.getElementById('reply-form-' + topId);
      if (!wrap) return;
      const isVisible = wrap.style.display !== 'none';
      document.querySelectorAll('.lp-reply-form-wrap').forEach(function (w) { w.style.display = 'none'; });
      if (isVisible) return;
      // Set reply_to_name hidden input and placeholder
      const replyToInput = wrap.querySelector('[name="reply_to_name"]');
      const replyTextarea = wrap.querySelector('textarea');
      replyToInput.value = replyTo;
      replyTextarea.placeholder = replyTo ? 'Replying to ' + replyTo : 'Write a reply';
      wrap.style.display = 'block';
      replyTextarea.focus();
      return;
    }

    // Reply cancel
    const replyCancel = e.target.closest('.lp-reply-cancel');
    if (replyCancel) {
      document.getElementById('reply-form-' + replyCancel.dataset.id).style.display = 'none';
      return;
    }

    // Edit button
    const editBtn = e.target.closest('.lp-edit-btn');
    if (editBtn) {
      const id = editBtn.dataset.id;
      const textEl = document.getElementById('comment-text-' + id);
      const editForm = document.getElementById('edit-form-' + id);
      if (!textEl || !editForm) return;
      textEl.style.display = 'none';
      editForm.style.display = 'block';
      editForm.querySelector('textarea').focus();
      return;
    }

    // Edit cancel
    const editCancel = e.target.closest('.lp-edit-cancel:not(.lp-reply-cancel)');
    if (editCancel) {
      const id = editCancel.dataset.id;
      const textEl = document.getElementById('comment-text-' + id);
      const editForm = document.getElementById('edit-form-' + id);
      if (!textEl || !editForm) return;
      textEl.style.display   = 'block';
      editForm.style.display = 'none';
      return;
    }

    // Edit save
    const editSave = e.target.closest('.lp-edit-save');
    if (editSave) {
      const id = editSave.dataset.id;
      const editForm = document.getElementById('edit-form-' + id);
      const textarea = editForm.querySelector('textarea');
      const text = textarea.value.trim();
      if (!text) return;

      const fd = new FormData();
      fd.append('comment_id', id);
      fd.append('comment', text);

      fetch('api/comment-edit.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.error) { showError(d.error); return; }
          const textEl = document.getElementById('comment-text-' + id);
          textEl.innerHTML = esc(d.comment).replace(/\n/g, '<br>');
          textEl.style.display = 'block';
          editForm.style.display = 'none';
          textarea.value = d.comment;
          const timeEl = editForm.closest('.lp-comment-body').querySelector('.lp-comment-time');
          if (timeEl && !timeEl.textContent.includes('edited')) {
            timeEl.textContent += ' · edited';
          }
        })
        .catch(function () { showError('Something went wrong.'); });
      return;
    }

    // Delete button - use existing modal from footer.php
    const deleteBtn = e.target.closest('.lp-delete-btn');
    if (deleteBtn) {
      const id = deleteBtn.dataset.id;
      const itemId = deleteBtn.dataset.item;

      // Trigger the existing modal system from footer.php
      const overlay = document.getElementById('confirmModal');
      const msgEl = document.getElementById('modalMessage');
      const btnConfirm = document.getElementById('modalConfirm');
      const btnCancel = document.getElementById('modalCancel');

      msgEl.textContent = 'Delete this comment? This cannot be undone.';
      overlay.classList.add('modal-open');
      btnCancel.focus();

      function doDelete() {
        overlay.classList.remove('modal-open');
        cleanup();

        const fd = new FormData();
        fd.append('comment_id', id);
        fd.append('item_id', itemId);

        fetch('api/comment-delete.php', { method: 'POST', body: fd })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (d.error) { showError(d.error); return; }
            const commentEl = document.getElementById('comment-' + id);
            const bubble = commentEl.querySelector('.lp-comment-bubble');
            bubble.innerHTML = '<p class="lp-comment-text lp-comment-deleted">This comment was deleted.</p>';
            const actions = commentEl.querySelector('.lp-comment-actions');
            if (actions) {
              actions.querySelectorAll('.lp-edit-btn, .lp-delete-btn, .lp-reply-btn').forEach(function (b) { b.remove(); });
            }
            const editForm = document.getElementById('edit-form-' + id);
            if (editForm) editForm.remove();
            updateCount(-1);
          })
          .catch(function () { showError('Something went wrong.'); });
      }

      function cancelDelete() {
        overlay.classList.remove('modal-open');
        cleanup();
      }

      function cleanup() {
        btnConfirm.removeEventListener('click', doDelete);
        btnCancel.removeEventListener('click', cancelDelete);
      }

      btnConfirm.addEventListener('click', doDelete);
      btnCancel.addEventListener('click', cancelDelete);
      return;
    }
  });

  // Reply form submit
  document.addEventListener('submit', function (e) {
    const form = e.target.closest('.lp-reply-form');
    if (!form) return;
    e.preventDefault();

    const parentId = form.querySelector('[name="parent_id"]').value;
    const replyToName = form.querySelector('[name="reply_to_name"]').value;
    const textarea = form.querySelector('textarea');
    const text = textarea.value.trim();
    if (!text) return;

    const fd = new FormData();
    fd.append('item_id', itemId);
    fd.append('parent_id', parentId);
    fd.append('reply_to_name', replyToName);
    fd.append('comment', text);

    fetch('api/comment-reply.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.error) { showError(d.error); return; }
        d.is_owner = true;
        d.commenter_avatar = <?= $loggedInUser ? json_encode($_SESSION['user']['avatar'] ?? null) : 'null' ?>;
        d.parent_id = parentId;
        const repliesEl = document.getElementById('replies-' + parentId);
        repliesEl.insertAdjacentHTML('beforeend', buildReplyHTML(d));
        textarea.value = '';
        form.querySelector('[name="reply_to_name"]').value = '';
        document.getElementById('reply-form-' + parentId).style.display = 'none';
        noMsg.style.display = 'none';
        updateCount(1);
      })
      .catch(function () { showError('Something went wrong.'); });
  });

  function updateCount(delta) {
    const label = document.querySelector('.lp-section-label');
    if (!label) return;
    const match = label.textContent.match(/\d+/);
    if (!match) return;
    label.textContent = 'Comments (' + (parseInt(match[0]) + delta) + ')';
  }

})();
</script>

<?php include 'includes/footer.php'; ?>