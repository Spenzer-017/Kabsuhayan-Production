<!-- PHP Logic (Authentication, Session, etc.) -->
<?php
  /*
    profile.php - User Profile Editor
    Users can update their info and pick an avatar.
  */

  session_start();

  require_once "includes/db.php";

  if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
  }

  $user_id = $_SESSION['user']['id'];
?>

<!-- PHP UI/UX Logic -->
<?php
  $activePage = "profile";
  $pageTitle = "My Profile";
  include "includes/header.php";
?>

<!-- PHP Database Query -->
<?php 
  // User Database Query
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->execute([$user_id]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    die("User not found.");
  }

  // User Password
  $stored_hash = $user['password'];

  // Avatars
  $avatars = [
    'junimo_0' => 'Avatar 1',
    'junimo_1' => 'Avatar 2',
    'junimo_2' => 'Avatar 3',
    'junimo_3' => 'Avatar 4',
    'junimo_4' => 'Avatar 5',
    'junimo_5' => 'Avatar 6',
    'junimo_6' => 'Avatar 7',
    'junimo_7' => 'Avatar 8',
  ];

  // Courses
  $courses = [
    // Agriculture & Environment
    "Bachelor of Agricultural Entrepreneurship",
    "BS Agriculture",
    "BS Environmental Science",
    "BS Food Technology",

    // Arts and Sciences
    "BA English Language Studies",
    "BA Journalism",
    "BA Political Science",
    "BS Applied Mathematics",
    "BS Biology",
    "BS Psychology",
    "BS Social Work",

    // Criminal Justice
    "BS Criminology",
    "BS Industrial Security Management",

    // Economics, Management, Development
    "BS Accountancy",
    "BS Business Management",
    "BS Development Management",
    "BS Economics",
    "BS International Studies",
    "BS Office Administration",

    // Education
    "Bachelor of Early Childhood Education",
    "Bachelor of Elementary Education",
    "Bachelor of Secondary Education",
    "Bachelor of Special Needs Education",
    "Bachelor of Technology and Livelihood Education",
    "BS Hospitality Management",
    "BS Tourism Management",
    "Teacher Certificate Program",
    "Science High School",
    "Elementary Education",
    "Pre-Elementary Education",

    // Engineering & IT
    "BS Agricultural and Biosystems Engineering",
    "BS Architecture",
    "BS Civil Engineering",
    "BS Computer Engineering",
    "BS Computer Science",
    "BS Electrical Engineering",
    "BS Electronics Engineering",
    "BS Industrial Engineering",
    "BS Industrial Technology - Automotive Technology",
    "BS Industrial Technology - Electrical Technology",
    "BS Industrial Technology - Electronics Technology",
    "BS Information Technology",

    // Nursing & Health
    "BS Medical Technology",
    "BS Midwifery",
    "BS Nursing",
    "Diploma in Midwifery",

    // Sports
    "Bachelor of Physical Education",
    "Bachelor of Exercise and Sports Sciences",

    // Veterinary
    "Doctor of Veterinary Medicine"
  ];

  // Year Level
  $years = ['1st Year','2nd Year','3rd Year','4th Year','5th Year'];

  // Handle form submission
  $success = false;
  $errors  = [];

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // User Info
    $name = trim($_POST['name'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $avatar = trim($_POST['avatar'] ?? '');

    // Password Field
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Edit Profile Validation
    if ($name === '') $errors[] = 'Full name is required.';
    if ($course === '') $errors[] = 'Course is required.';
    if ($year === '') $errors[] = 'Year level is required.';
    if (!array_key_exists($avatar, $avatars)) $errors[] = 'Please select an avatar.';
    if (strlen($bio) > 200) $errors[] = 'Bio too long.';
    if (strlen($contact) > 80) $errors[] = 'Contact too long.';
    if (!isset($avatars[$avatar])) $errors[] = 'Invalid avatar.';

    // Password Validation
    if ($current_password || $new_password || $confirm_password) {
      if ($current_password === '' || $new_password === '' || $confirm_password === '') {
        $errors[] = 'Please fill all password fields.';
      }
      elseif ($new_password !== $confirm_password) {
        $errors[] = 'New passwords do not match.';
      }
      elseif (strlen($new_password) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
      }
      elseif (!password_verify($current_password, $stored_hash)) {
        $errors[] = 'Current password is incorrect.';
      }
    }

    // Save New Info
    if (empty($errors)) {

      // If Changing Password
      if ($new_password) {
        $stored_hash = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
          UPDATE users 
          SET name = ?, 
              course = ?, 
              year_level = ?, 
              bio = ?, 
              contact_info = ?, 
              avatar = ?, 
              password = ?
          WHERE id = ?
        ");

        $stmt->execute([
          $name,
          $course,
          $year,
          $bio,
          $contact,
          $avatar,
          $stored_hash,
          $user_id
        ]);

      } else {

        $stmt = $pdo->prepare("
          UPDATE users 
          SET name = ?, 
              course = ?, 
              year_level = ?, 
              bio = ?, 
              contact_info = ?, 
              avatar = ?
          WHERE id = ?
        ");

        $stmt->execute([
          $name,
          $course,
          $year,
          $bio,
          $contact,
          $avatar,
          $user_id
        ]);
      }

      // Refresh user data
      $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
      $stmt->execute([$user_id]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      // Update session
      $_SESSION['user']['name'] = $user['name'];
      $_SESSION['user']['avatar'] = $user['avatar'];
      $_SESSION['user']['course'] = $user['course'];

      $success = true;
    }
  }

  // User Stats

  // Active listings
  $stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM items 
    WHERE seller_id = ? AND status = 'active'
  ");
  $stmt->execute([$user_id]);
  $activeListings = $stmt->fetchColumn();

  // Items sold
  $stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM items 
    WHERE seller_id = ? AND status = 'sold'
  ");
  $stmt->execute([$user_id]);
  $soldItems = $stmt->fetchColumn();

  // Purchases
  $stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM transactions 
    WHERE buyer_id = ? AND status = 'completed'
  ");
  $stmt->execute([$user_id]);
  $purchases = $stmt->fetchColumn();

  // Function that returns an <img> tag pointing to the pixel art PNG.
  function get_avatar_img(string $id): string {
    return '<img
      src="assets/img/' . htmlspecialchars($id) . '.png"
      alt="' . htmlspecialchars($id) . '"
      class="avatar-pixel-img"
    />';
  }
?>

<div class="profile-page">

  <!-- Page header -->
  <div class="profile-page-header">
    <div>
      <h1>Edit Profile</h1>
      <p>Update your info and choose how you appear to other students.</p>
    </div>
    <a href="dashboard.php" class="btn-back">Back to Dashboard</a>
  </div>

  <!-- Success & error alerts -->
  <?php if ($success): ?>
    <div class="alert alert-success">Profile updated successfully!</div>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <strong>Please fix the following:</strong>
      <ul><?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form class="profile-form" method="POST">

    <!-- Left: Avatar picker & stats -->
    <div class="profile-left">

      <!-- Current avatar display -->
      <div class="current-avatar-card">
        <div class="current-avatar" id="currentAvatarDisplay">
          <?= get_avatar_img($user['avatar']) ?>
        </div>
        <div class="current-avatar-name" id="currentAvatarName">
          <?= htmlspecialchars($avatars[$user['avatar']]) ?>
        </div>
        <p class="current-avatar-hint">Click an avatar below to select</p>
      </div>

      <!-- Avatar grid picker -->
      <div class="avatar-picker-card">
        <h3 class="card-title">Choose Your Avatar</h3>
        <div class="avatar-grid">
          <?php foreach ($avatars as $id => $label): ?>
            <div class="avatar-option <?= $user['avatar'] === $id ? 'selected' : '' ?>" data-id="<?= $id ?>" data-label="<?= htmlspecialchars($label) ?>" title="<?= htmlspecialchars($label) ?>" onclick="selectAvatar(this)">
              <?= get_avatar_img($id) ?>
              <span><?= htmlspecialchars($label) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <!-- Hidden input that stores the chosen avatar id -->
        <input type="hidden" name="avatar" id="avatarInput" value="<?= htmlspecialchars($user['avatar']) ?>" />
      </div>

      <!-- Stats -->
      <div class="profile-stats-card">
        <h3 class="card-title">Your Stats</h3>
        <div class="profile-stats">
          <div class="profstat">
            <div class="profstat-value"><?= $activeListings ?></div>
            <div class="profstat-label">Active Listings</div>
          </div>
          <div class="profstat">
            <div class="profstat-value"><?= $soldItems ?></div>
            <div class="profstat-label">Items Sold</div>
          </div>
          <div class="profstat">
            <div class="profstat-value"><?= $purchases ?></div>
            <div class="profstat-label">Purchases</div>
          </div>
        </div>
        <p class="joined-note">Member since <?= date('F Y', strtotime($user['created_at'])) ?></p>
      </div>

    </div>

    <!-- Right: Edit fields -->
    <div class="profile-right">

      <div class="profile-fields-card">
        <h3 class="card-title">Personal Information</h3>

        <!-- Full name -->
        <div class="form-group">
          <label for="name">Full Name <span class="required">*</span></label>
          <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" placeholder="Your full name" maxlength="80" required/>
        </div>

        <!-- Email -->
        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" disabled/>
          <span class="form-hint">Email is linked to your CvSU account and cannot be changed here.</span>
        </div>

        <!-- Course & Year Level row -->
        <div class="form-row">
          <div class="form-group">
            <label for="course">Course <span class="required">*</span></label>
            <select id="course" name="course" required>
              <option value="" disabled>Select course</option>
              <?php foreach ($courses as $c): ?>
                <option value="<?= $c ?>" <?= $user['course'] === $c ? 'selected' : '' ?>>
                  <?= $c ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="year">Year Level <span class="required">*</span></label>
            <select id="year" name="year" required>
              <option value="" disabled>Select year</option>
              <?php foreach ($years as $y):?>
                <option value="<?= $y ?>" <?= $user['year_level'] === $y ? 'selected' : '' ?>>
                  <?= $y ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Bio -->
        <div class="form-group">
          <label for="bio">Bio</label>
          <textarea id="bio" name="bio" rows="3" maxlength="200" placeholder="Tell something about yourself…"><?= htmlspecialchars($user['bio']) ?></textarea>
          <span class="form-hint char-count">
            <span id="bioCount"><?= htmlspecialchars(strlen($user['bio'])) ?></span> / 200 characters
          </span>
        </div>

        <!-- Contact -->
        <div class="form-group" style="margin-bottom:0;">
          <label for="contact">Contact / Messenger</label>
          <input type="text" id="contact" name="contact" value="<?= htmlspecialchars($user['contact_info']) ?>" placeholder="e.g. FB: Spenzer Lima" maxlength="80"/>
        </div>

      </div>

      <!-- Change password section -->
      <div class="profile-fields-card">
        <h3 class="card-title">Change Password</h3>

        <div class="form-group">
          <label for="current_password">Current Password</label>
          <input type="password" id="current_password" name="current_password" placeholder="Enter current password" />
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" placeholder="New password" />
          </div>
          <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat new password" />
          </div>
        </div>

        <span class="form-hint" style="margin-top:-8px; display:block;">
          Leave all password fields empty if you don't want to change it.
        </span>

      </div>

      <!-- Save button -->
      <button type="submit" class="btn-submit">Save Changes</button>

      <p class="submit-note">
        Changes are saved immediately and visible to other students.
      </p>

    </div>

  </form>

</div>

<script>
  // Bio character counter
  const bio = document.getElementById('bio');
  const bioCount = document.getElementById('bioCount');
  bio.addEventListener('input', () => {
    bioCount.textContent = bio.value.length;
  });

  // Avatar picker
  function selectAvatar(element) {
    // Deselect all
    document.querySelectorAll('.avatar-option').forEach(opt => {
      opt.classList.remove('selected');
    });

    // Select clicked
    element.classList.add('selected');

    const id = element.dataset.id;
    const label = element.dataset.label;

    // Update hidden input
    document.getElementById('avatarInput').value = id;

    // Update large preview - just change the img src
    document.querySelector('#currentAvatarDisplay img').src =
      'assets/img/' + id + '.png';
    document.getElementById('currentAvatarName').textContent = label;
  }
</script>

<?php include 'includes/footer.php'; ?>