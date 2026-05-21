<!-- PHP Logic (Authentication, Session, etc.) -->
<?php
/*
  edit-listing.php - Edit an Existing Listing
  Only the seller who owns the listing can access this page.
*/

  session_start();

  require_once "includes/db.php";

  if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
  }

  $loggedInUser = $_SESSION['user'];
  $item_id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

  if ($item_id <= 0) {
    header('Location: my-listings.php');
    exit;
  }
?>

<!-- PHP Database Query -->
<?php
  // Fetch the item - make sure it belongs to the logged-in user
  $stmt = $pdo->prepare("
    SELECT items.*, categories.name AS category_name
    FROM items
    JOIN categories ON items.category_id = categories.category_id
    WHERE items.item_id = ? AND items.seller_id = ?
    LIMIT 1
  ");
  $stmt->execute([$item_id, (int)$loggedInUser['id']]);
  $item = $stmt->fetch();

  // If not found or not the owner, redirect away
  if (!$item) {
    header('Location: my-listings.php');
    exit;
  }

  $success = false;
  $errors  = [];

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $condition = trim($_POST['condition'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $meetup = trim($_POST['meetup'] ?? '');
    $contact = trim($_POST['contact'] ?? '');

    // Validation
    if ($title === '') $errors[] = 'Item title is required.';
    if ($category === '') $errors[] = 'Please select a category.';
    if ($condition === '') $errors[] = 'Please select the item condition.';
    if ($price === '' || !is_numeric($price)) $errors[] = 'Please enter a valid price.';
    if ((float)$price < 0) $errors[] = 'Price cannot be negative.';
    if ($description === '') $errors[] = 'Description is required.';
    if ($meetup === '') $errors[] = 'Meetup location is required.';
    if ($contact === '') $errors[] = 'Contact info is required.';

    // Image upload
    $image_path = $item['image_path'];
    $new_image_uploaded = false;
    $new_filename = null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
      $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
      $max_size = 5 * 1024 * 1024;

      if (!in_array($_FILES['image']['type'], $allowed_types)) {
        $errors[] = 'Image must be JPG, PNG, or WebP.';
      } elseif ($_FILES['image']['size'] > $max_size) {
        $errors[] = 'Image must be smaller than 5MB.';
      } else {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid() . '.' . $ext;
        $target = 'uploads/' . $new_filename;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
          $image_path = $new_filename;
          $new_image_uploaded = true;
        } else {
          $errors[] = 'Failed to upload image.';
        }
      }
    }

    // Get category_id from category name
    $stmt = $pdo->prepare("SELECT category_id FROM categories WHERE name = ?");
    $stmt->execute([$category]);
    $catData = $stmt->fetch();

    if (!$catData) {
      $errors[] = 'Invalid category.';
    }

    if (empty($errors)) {
      try {
        $stmt = $pdo->prepare("
          UPDATE items
          SET title = ?, category_id = ?, condition_type = ?, price = ?,
              description = ?, meetup_location = ?, contact_info = ?, image_path = ?
          WHERE item_id = ? AND seller_id = ?
        ");
        $stmt->execute([
          $title,
          $catData['category_id'],
          $condition,
          $price,
          $description,
          $meetup,
          $contact,
          $image_path,
          $item_id,
          (int)$loggedInUser['id']
        ]);

        // Only delete old image after successful DB update
        if ($new_image_uploaded && $item['image_path'] !== $image_path && !empty($item['image_path']) && $item['image_path'] !== $new_filename) {
          $oldPath = 'uploads/' . $item['image_path'];

          if (file_exists($oldPath)) {
            unlink($oldPath);
          }
        }

        // Refresh item data to show updated values
        $stmt = $pdo->prepare("
          SELECT items.*, categories.name AS category_name
          FROM items
          JOIN categories ON items.category_id = categories.category_id
          WHERE items.item_id = ?
        ");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();

        $success = true;

      } catch (PDOException $e) {
        $errors[] = 'Something went wrong. Please try again.';
      }
    }
  }
?>

<!-- PHP UI/UX Logic -->
<?php
  $activePage = '';
  $pageTitle = "Edit Listing";
  include "includes/header.php";
?>

<div class="sell-page">

  <div class="sell-header">
    <div>
      <h1>Edit Listing</h1>
      <p>Update the details of your listing.</p>
    </div>
    <a href="my-listings.php" class="btn-back">Back to My Listings</a>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success">
      Listing updated! <a href="listing.php?id=<?= $item_id ?>">View listing</a>
    </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <strong>Please fix the following:</strong>
      <ul>
        <?php foreach ($errors as $err): ?>
          <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form class="sell-form" method="POST" enctype="multipart/form-data">

    <div class="sell-main">

      <div class="form-group">
        <label for="title">Item Name <span class="required">*</span></label>
        <input type="text" id="title" name="title" placeholder="e.g. Ethics Book" value="<?= htmlspecialchars($item['title']) ?>" maxlength="100" />
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="category">Category <span class="required">*</span></label>
          <select id="category" name="category">
            <option value="" disabled>Select category</option>
            <?php foreach (['Books','Electronics','Supplies','Clothing','Food','Accessories','Other'] as $cat): ?>
              <option value="<?= $cat ?>" <?= $item['category_name'] === $cat ? 'selected' : '' ?>><?= $cat ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="condition">Condition <span class="required">*</span></label>
          <select id="condition" name="condition">
            <option value="" disabled>Select condition</option>
            <?php foreach (['New','Like New','Good','Fair','N/A'] as $cond): ?>
              <option value="<?= $cond ?>" <?= $item['condition_type'] === $cond ? 'selected' : '' ?>><?= $cond ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label for="price">Price <span class="required">*</span></label>
        <div class="price-input">
          <span class="price-prefix">&#8369;</span>
          <input type="number" id="price" name="price" placeholder="0.00" min="0" step="1" value="<?= htmlspecialchars($item['price']) ?>" />
        </div>
      </div>

      <div class="form-group">
        <label for="description">Description <span class="required">*</span></label>
        <textarea id="description" name="description" rows="5" maxlength="200" placeholder="Write a description"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
        <span class="form-hint char-count">
          <span id="charCount"><?= strlen($item['description'] ?? '') ?></span> / 200 characters
        </span>
      </div>

    </div>

    <div class="sell-side">

      <div class="form-card">
        <h3 class="card-title">Item Photo</h3>

        <!-- Show current image if there is one -->
        <?php if (!empty($item['image_path']) && file_exists('uploads/' . $item['image_path'])): ?>
          <div style="margin-bottom: 12px;">
            <img src="uploads/<?= htmlspecialchars($item['image_path']) ?>" alt="Current image" style="width:100%; border-radius:8px; object-fit:cover; max-height:200px;" />
            <span class="form-hint">Current photo. Upload a new one to replace it.</span>
          </div>
        <?php endif; ?>

        <div class="upload-area" id="uploadArea">
          <div class="upload-placeholder" id="uploadPlaceholder">
            <div class="upload-icon"><?= $photoIcon ?></div>
            <p class="upload-label">Click to upload a new photo</p>
            <p class="upload-sub">JPG, PNG or WebP - Max 5MB</p>
          </div>
          <img id="imagePreview" class="image-preview" src="" alt="Preview" style="display:none;" />
          <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" />
        </div>
      </div>

      <div class="form-card">
        <h3 class="card-title">Meetup & Contact</h3>

        <div class="form-group">
          <label for="meetup">Preferred Meetup Spot <span class="required">*</span></label>
          <input type="text" id="meetup" name="meetup" placeholder="e.g. CEIT Bldg." value="<?= htmlspecialchars($item['meetup_location'] ?? '') ?>" maxlength="100" />
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label for="contact">Contact / Messenger <span class="required">*</span></label>
          <input type="text" id="contact" name="contact" placeholder="e.g. FB: Juan dela Cruz" value="<?= htmlspecialchars($item['contact_info'] ?? '') ?>" />
        </div>
      </div>

      <button type="submit" class="btn-submit">Save Changes</button>

      <a href="listing.php?id=<?= $item_id ?>" class="btn-back" style="display:block; text-align:center; margin-top:10px;">
        View Listing
      </a>

    </div>

  </form>

</div>

<script>
  const desc = document.getElementById('description');
  const charCount = document.getElementById('charCount');
  desc.addEventListener('input', () => { charCount.textContent = desc.value.length; });

  const fileInput = document.getElementById('image');
  const uploadArea = document.getElementById('uploadArea');
  const placeholder = document.getElementById('uploadPlaceholder');
  const imagePreview = document.getElementById('imagePreview');

  uploadArea.addEventListener('click', () => fileInput.click());
  fileInput.addEventListener('click', (e) => e.stopPropagation());

  fileInput.addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (e) => {
      imagePreview.src = e.target.result;
      imagePreview.style.display = 'block';
      placeholder.style.display  = 'none';
    };
    reader.readAsDataURL(file);
  });
</script>

<?php include 'includes/footer.php'; ?>