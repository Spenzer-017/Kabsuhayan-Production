<?php
  /* 
    notifications.php - main page for users notifications
  */

  session_start();
  require_once 'includes/db.php';

  if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
  }

  $user_id = (int)$_SESSION['user']['id'];

  // Filter: all or unread
  $filter = $_GET['filter'] ?? 'all';
  if (!in_array($filter, ['all', 'unread'])) $filter = 'all';

  $where = $filter === 'unread' ? 'WHERE user_id = ? AND is_read = 0' : 'WHERE user_id = ?';
  $stmt = $pdo->prepare("SELECT * FROM notifications $where ORDER BY created_at DESC");
  $stmt->execute([$user_id]);
  $notifications = $stmt->fetchAll();

  $activePage = '';
  $pageTitle = 'Notifications';
  include 'includes/header.php';

  function formatNotifMessage(string $msg): string {
    return preg_replace_callback(
      '/\[reaction:([a-z]+)\]/',
      function($m) {
        $type = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
        return '<img src="assets/img/' . $type . '-junimo-no-bg.png" '
          . 'style="width:20px;height:20px;image-rendering:pixelated;'
          . 'vertical-align:middle;display:inline-block;" />';
      },
      $msg
    );
  }
?>

<div class="notif-page">

  <div class="notif-page-header">
    <h1>Notifications</h1>
    <div class="notif-page-actions">
      <button class="notif-page-mark-all" id="pageMarkAll">Mark all as read</button>
      <button class="notif-page-clear-all" id="pageClearAll">Clear all</button>
    </div>
  </div>

  <div class="notif-filter-tabs">
    <a href="?filter=all" class="notif-filter-tab <?= $filter === 'all' ? 'active' : '' ?>">All</a>
    <a href="?filter=unread" class="notif-filter-tab <?= $filter === 'unread' ? 'active' : '' ?>">Unread</a>
  </div>

  <?php if (empty($notifications)): ?>
    <div class="notif-page-empty">
      <svg xmlns="http://www.w3.org/2000/svg" height="48px" viewBox="0 -960 960 960" width="48px"><path d="M160-200v-80h80v-280q0-83 50-147.5T420-792v-28q0-25 17.5-42.5T480-880q25 0 42.5 17.5T540-820v28q80 20 130 84.5T720-560v280h80v80H160Zm320-300Zm0 420q-33 0-56.5-23.5T400-160h160q0 33-23.5 56.5T480-80ZM320-280h320v-280q0-66-47-113t-113-47q-66 0-113 47t-47 113v280Z"/></svg>
      <p>No notifications yet.</p>
    </div>
  <?php else: ?>
    <div class="notif-page-list" id="notifPageList">
      <?php foreach ($notifications as $n): ?>
        <div class="notif-page-item <?= $n['is_read'] ? '' : 'notif-unread' ?>" id="notif-page-<?= $n['notif_id'] ?>">
          <div class="notif-page-icon notif-icon--<?= htmlspecialchars($n['type']) ?>">
            <?php
              $icons = [
                'reaction' => '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#434343"><path d="m305-704 112-145q12-16 28.5-23.5T480-880q18 0 34.5 7.5T543-849l112 145 170 57q26 8 41 29.5t15 47.5q0 12-3.5 24T866-523L756-367l4 164q1 35-23 59t-56 24q-2 0-22-3l-179-50-179 50q-5 2-11 2.5t-11 .5q-32 0-56-24t-23-59l4-165L95-523q-8-11-11.5-23T80-570q0-25 14.5-46.5T135-647l170-57Zm49 69-194 64 124 179-4 191 200-55 200 56-4-192 124-177-194-66-126-165-126 165Zm126 135Z"/></svg>',
                'comment' => '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#434343"><path d="M280-400h400q17 0 28.5-11.5T720-440q0-17-11.5-28.5T680-480H280q-17 0-28.5 11.5T240-440q0 17 11.5 28.5T280-400Zm0-120h400q17 0 28.5-11.5T720-560q0-17-11.5-28.5T680-600H280q-17 0-28.5 11.5T240-560q0 17 11.5 28.5T280-520Zm0-120h400q17 0 28.5-11.5T720-680q0-17-11.5-28.5T680-720H280q-17 0-28.5 11.5T240-680q0 17 11.5 28.5T280-640ZM160-240q-33 0-56.5-23.5T80-320v-480q0-33 23.5-56.5T160-880h640q33 0 56.5 23.5T880-800v623q0 27-24.5 37.5T812-148l-92-92H160Zm594-80 46 45v-525H160v480h594Zm-594 0v-480 480Z"/></svg>',
                'reply' => '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#434343"><path d="m273-480 116 116q12 12 11.5 28T388-308q-12 11-28 11.5T332-308L148-492q-12-12-12-28t12-28l184-184q11-11 27.5-11t28.5 11q12 12 12 28.5T388-675L273-560h367q83 0 141.5 58.5T840-360v120q0 17-11.5 28.5T800-200q-17 0-28.5-11.5T760-240v-120q0-50-35-85t-85-35H273Z"/></svg>',
                'message' => '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#434343"><path d="M80-80v-720q0-33 23.5-56.5T160-880h640q33 0 56.5 23.5T880-800v480q0 33-23.5 56.5T800-240H240L80-80Zm126-240h594v-480H160v525l46-45Zm-46 0v-480 480Z"/></svg>',
                'transaction' => '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#434343"><path d="M475-160q4 0 8-2t6-4l328-328q12-12 17.5-27t5.5-30q0-16-5.5-30.5T817-607L647-777q-11-12-25.5-17.5T591-800q-15 0-30 5.5T534-777l-11 11 74 75q15 14 22 32t7 38q0 42-28.5 70.5T527-522q-20 0-38.5-7T456-550l-75-74-175 175q-3 3-4.5 6.5T200-435q0 8 6 14.5t14 6.5q4 0 8-2t6-4l136-136 56 56-135 136q-3 3-4.5 6.5T285-350q0 8 6 14t14 6q4 0 8-2t6-4l136-135 56 56-135 136q-3 2-4.5 6t-1.5 8q0 8 6 14t14 6q4 0 7.5-1.5t6.5-4.5l136-135 56 56-136 136q-3 3-4.5 6.5T454-180q0 8 6.5 14t14.5 6Zm-1 80q-37 0-65.5-24.5T375-166q-34-5-57-28t-28-57q-34-5-56.5-28.5T206-336q-38-5-62-33t-24-66q0-20 7.5-38.5T149-506l232-231 131 131q2 3 6 4.5t8 1.5q9 0 15-5.5t6-14.5q0-4-1.5-8t-4.5-6L398-777q-11-12-25.5-17.5T342-800q-15 0-30 5.5T285-777L144-635q-9 9-15 21t-8 24q-2 12 0 24.5t8 23.5l-58 58q-17-23-25-50.5T40-590q2-28 14-54.5T87-692l141-141q24-23 53.5-35t60.5-12q31 0 60.5 12t52.5 35l11 11 11-11q24-23 53.5-35t60.5-12q31 0 60.5 12t52.5 35l169 169q23 23 35 53t12 61q0 31-12 60.5T873-437L545-110q-14 14-32.5 22T474-80Zm-99-560Z"/></svg>',
              ];
              echo $icons[$n['type']] ?? $icons['comment'];
            ?>
          </div>
          <a href="<?= htmlspecialchars($n['link'] ?? '#', ENT_QUOTES, 'UTF-8') ?>" class="notif-page-content" data-id="<?= $n['notif_id'] ?>">
            <p class="notif-page-msg"><?= formatNotifMessage(htmlspecialchars($n['message'], ENT_QUOTES, 'UTF-8')) ?></p>
            <span class="notif-page-time"><?= date('M j, Y g:i A', strtotime($n['created_at'])) ?></span>
          </a>
          <?php if (!$n['is_read']): ?>
            <div class="notif-unread-dot"></div>
          <?php endif; ?>
          <button class="notif-page-delete-btn" data-id="<?= $n['notif_id'] ?>" title="Delete">
            <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px"><path d="m256-200-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z"/></svg>
          </button>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<script>
  (function () {
    function markOne(id) {
      const fd = new FormData();
      fd.append('mode', 'one');
      fd.append('notif_id', id);
      fetch('api/notifications-mark.php', { method: 'POST', body: fd }).catch(function(){});
    }

    function deleteOne(id, el) {
      const fd = new FormData();
      fd.append('mode', 'one');
      fd.append('notif_id', id);
      fetch('api/notifications-delete.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d){ if (d.ok) el.remove(); })
        .catch(function(){});
    }

    // Click notification link - mark as read then navigate
    document.querySelectorAll('.notif-page-content').forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        const id   = a.dataset.id;
        const href = a.getAttribute('href');
        const item = document.getElementById('notif-page-' + id);
        markOne(id);
        if (item) {
          item.classList.remove('notif-unread');
          const dot = item.querySelector('.notif-unread-dot');
          if (dot) dot.remove();
        }
        window.location.href = href;
      });
    });

    // Delete individual
    document.querySelectorAll('.notif-page-delete-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const id   = btn.dataset.id;
        const item = document.getElementById('notif-page-' + id);
        deleteOne(id, item);
      });
    });

    // Mark all as read
    const markAllBtn = document.getElementById('pageMarkAll');
    if (markAllBtn) {
      markAllBtn.addEventListener('click', function () {
        const fd = new FormData();
        fd.append('mode', 'all');
        fetch('api/notifications-mark.php', { method: 'POST', body: fd })
          .then(function(r){ return r.json(); })
          .then(function(d){
            if (d.ok) {
              document.querySelectorAll('.notif-page-item').forEach(function (el) {
                el.classList.remove('notif-unread');
                const dot = el.querySelector('.notif-unread-dot');
                if (dot) dot.remove();
              });
            }
          }).catch(function(){});
      });
    }

    // Clear all
    const clearAllBtn = document.getElementById('pageClearAll');
    if (clearAllBtn) {
      clearAllBtn.addEventListener('click', function () {
        if (!confirm('Clear all notifications?')) return;
        const fd = new FormData();
        fd.append('mode', 'all');
        fetch('api/notifications-delete.php', { method: 'POST', body: fd })
          .then(function(r){ return r.json(); })
          .then(function(d){
            if (d.ok) {
              const list = document.getElementById('notifPageList');
              if (list) list.innerHTML = '<p class="lp-no-comments" style="padding:24px 0">No notifications yet.</p>';
            }
          }).catch(function(){});
      });
    }
  })();

  
</script>

<?php include 'includes/footer.php'; ?>