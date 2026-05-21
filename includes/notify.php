<?php
  /*
    includes/notify.php
    Call insertNotification() anywhere to create a notification.
    Never notify the user about their own actions.
  */

  function insertNotification(PDO $pdo, int $user_id, int $actor_id, string $type, string $message, string $link): void {
    // Never notify yourself
    if ($user_id === $actor_id) return;

    $pdo->prepare("
      INSERT INTO notifications (user_id, type, message, link, is_read, created_at)
      VALUES (?, ?, ?, ?, 0, NOW())
    ")->execute([$user_id, $type, $message, $link]);
  }
?>