<?php
  function deleteItemWithImage(PDO $pdo, int $item_id, int $user_id): bool {
    
    // Get image path (and verify ownership)
    $stmt = $pdo->prepare("SELECT image_path FROM items WHERE item_id = ? AND seller_id = ?");
    $stmt->execute([$item_id, $user_id]);
    $item = $stmt->fetch();

    if (!$item) {
        return false;
    }

    // Delete image file if exists
    if (!empty($item['image_path'])) {
        $file = __DIR__ . "/../uploads/" . $item['image_path'];

        if (file_exists($file)) {
            unlink($file);
        }
    }

    // Delete item and cascade handles the rest
    $stmt = $pdo->prepare("DELETE FROM items WHERE item_id = ?");
    return $stmt->execute([$item_id]);
  }
?>