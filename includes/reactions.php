<?php
  /*
    includes/reactions.php
    Handles all reaction logic: add, change, remove, fetch counts, fetch user reaction.
    Designed to support future notifications.
  */

  /*
    Get reaction counts for an item, keyed by reaction type.
    Returns array like ['like' => 3, 'heart' => 1, 'laugh' => 0, 'wow' => 0, 'cry' => 0]
  */
  function getReactionCounts(PDO $pdo, int $item_id): array {
    $types = ['like', 'heart', 'laugh', 'wow', 'cry'];
    $counts = array_fill_keys($types, 0);

    $stmt = $pdo->prepare("
      SELECT reaction_type, COUNT(*) AS total
      FROM item_reactions
      WHERE item_id = ?
      GROUP BY reaction_type
    ");
    $stmt->execute([$item_id]);

    foreach ($stmt->fetchAll() as $row) {
      $counts[$row['reaction_type']] = (int)$row['total'];
    }

    return $counts;
  }

  /*
    Get the current logged-in user's reaction on an item.
    Returns the reaction_type string or null if no reaction.
  */
  function getUserReaction(PDO $pdo, int $item_id, int $user_id): ?string {
    $stmt = $pdo->prepare("
      SELECT reaction_type FROM item_reactions
      WHERE item_id = ? AND user_id = ?
      LIMIT 1
    ");
    $stmt->execute([$item_id, $user_id]);
    $row = $stmt->fetch();
    return $row ? $row['reaction_type'] : null;
  }

  /*
    Handle a reaction action from POST.
    - If user has no reaction: insert the new one.
    - If user reacts with the same type: remove it (toggle off).
    - If user reacts with a different type: update to new type.

    Returns array with 'action' (added/changed/removed) and 'reaction_type'.
    Structured for future notification hooks.
  */
  function handleReaction(PDO $pdo, int $item_id, int $user_id, string $reaction_type): array {
    $valid = ['like', 'heart', 'laugh', 'wow', 'cry'];
    if (!in_array($reaction_type, $valid, true)) {
      return ['action' => 'invalid', 'reaction_type' => null];
    }

    $current = getUserReaction($pdo, $item_id, $user_id);

    if ($current === null) {
      $pdo->prepare("
        INSERT INTO item_reactions (user_id, item_id, reaction_type)
        VALUES (?, ?, ?)
      ")->execute([$user_id, $item_id, $reaction_type]);

      return ['action' => 'added', 'reaction_type' => $reaction_type];
    }

    if ($current === $reaction_type) {
      $pdo->prepare("
        DELETE FROM item_reactions WHERE user_id = ? AND item_id = ?
      ")->execute([$user_id, $item_id]);

      return ['action' => 'removed', 'reaction_type' => null];
    }

    $pdo->prepare("
      UPDATE item_reactions SET reaction_type = ?, created_at = NOW()
      WHERE user_id = ? AND item_id = ?
    ")->execute([$reaction_type, $user_id, $item_id]);

    return ['action' => 'changed', 'reaction_type' => $reaction_type];
  }
?>