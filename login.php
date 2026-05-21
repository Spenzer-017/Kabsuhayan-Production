<!-- PHP Logic (Authentication, Session, etc.) -->
<?php
  /*
    login.php - User Login
  */

  session_start();

  $errors = [];

  // Login Rate Limiting
  $max_attempts = 5;
  $lock_time = 60;
  $remaining = 0;
  $is_locked = false;

  if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
  }

  if ($_SESSION['login_attempts'] >= $max_attempts) {
    $time_passed = time() - $_SESSION['last_attempt_time'];

    if ($time_passed < $lock_time) {
      $is_locked = true;
      $remaining = $lock_time - $time_passed;
      $errors[] = "Too many login attempts.";
    } else {
      $_SESSION['login_attempts'] = 0;
    }
  }

  // If already logged in, redirect to dashboard
  if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
  }

  // DB connection
  require_once 'includes/db.php';

  $email = '';
?>

<!-- PHP Database Query -->
<?php
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if ($email === '') $errors[] = 'Email is required.';
    if ($password === '') $errors[] = 'Password is required.';

    if (empty($errors)) {
      // Look up user by email
      $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
      $stmt->execute([$email]);
      $user = $stmt->fetch();

      if (!$user || !password_verify($password, $user['password'])) {
        $_SESSION['login_attempts']++;
        $_SESSION['last_attempt_time'] = time();
        $errors[] = 'Incorrect email or password.';
      } else {
        // Login success - store user in session
        session_regenerate_id(true);

        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt_time'] = 0;

        $_SESSION['user'] = [
          'id' => $user['id'],
          'name' => $user['name'],
          'email' => $user['email'],
          'course' => $user['course'],
          'avatar' => $user['avatar'],
        ];
        header('Location: dashboard.php');
        exit;
      }
    }
  }
?>

<!-- PHP UI/UX Logic -->
<?php
  $activePage = '';
  $pageTitle = "Login";
  include 'includes/header.php';
?>

<div class="auth-page">
  <div class="auth-card">

    <div class="auth-header">
      <a href="index.php" class="logo">
        <span>Kabsu<span class="logo-accent">hayan</span></span>
        <img src="./assets/img/v3_logo.png" alt="website-logo" class="auth-logo-img">
      </a>
      <h1>Welcome back</h1>
      <p>Log in to your student account</p>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
          <?php endforeach; ?>
        </ul>

        <?php if ($is_locked): ?>
          <div id="lockTimer" data-time="<?= $remaining ?>"></div>
        <?php endif; ?>

      </div>
    <?php endif; ?>

    <?php if (isset($_GET['registered'])): ?>
      <div class="alert alert-success">
        Account created! You can now log in.
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['logged_out'])): ?>
      <div class="alert alert-success">
        You have been logged out.
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['password_reset'])): ?>
      <div class="alert alert-success">
        Password reset successfully. You can now log in with your new password.
      </div>
    <?php endif; ?>

    <form method="POST" class="auth-form">

      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" placeholder="firstname.lastname@cvsu.edu.ph" value="<?= htmlspecialchars($email) ?>" required/>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="password-input">
          <input type="password" id="password" name="password" placeholder="Enter your password" required/>
          <button type="button" class="toggle-password" onclick="togglePassword('password', this)" aria-label="Show password">
            <span class="icon-visibility-on"><?= $visibilityOnIcon ?></span>
            <span class="icon-visibility-off" style="display:none;"><?= $visibilityOffIcon ?></span>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-submit" <?= $is_locked ? 'disabled' : '' ?>>Log In</button>
      <div class="forgot-password-link">
        <a href="forgot-password.php">Forgot your password?</a>
      </div>

    </form>

    <p class="auth-switch">
      Don't have an account? <a href="signup.php">Sign up</a>
    </p>

  </div>
</div>

<script>
  function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const iconOn = btn.querySelector('.icon-visibility-on');
    const iconOff = btn.querySelector('.icon-visibility-off');

    if (input.type === 'password') {
      input.type = 'text';
      iconOn.style.display = 'none';
      iconOff.style.display = 'inline-flex';
    } else {
      input.type = 'password';
      iconOn.style.display = 'inline-flex';
      iconOff.style.display = 'none';
    }
  }

  const timerEl = document.getElementById('lockTimer');

  if (timerEl) {
    let timeLeft = parseInt(timerEl.dataset.time);

    function updateTimer() {
      if (timeLeft > 0) {
        timerEl.textContent = "Try again in " + timeLeft + "s";
        timeLeft--;
      } else {
        timerEl.textContent = "You can try logging in again.";
        setTimeout(() => location.reload(), 1000);
      }
    }

    updateTimer();
    setInterval(updateTimer, 1000);
  }
</script>

<?php include 'includes/footer.php'; ?>