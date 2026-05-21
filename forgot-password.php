<?php
  /*
    forgot-password.php - Password Reset
    Step 1: Enter email
    Step 2: Enter OTP
    Step 3: Enter new password
  */

  session_start();
  date_default_timezone_set('Asia/Manila');

  // Reset progress on GET request (refresh or direct visit)
  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    unset($_SESSION['reset_step'], $_SESSION['reset_email'], $_SESSION['reset_verified']);
  }

  if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
  }

  require_once 'includes/db.php';
  require_once 'includes/mail.php';

  // Clean expired verifications
  $pdo->exec("DELETE FROM email_verifications WHERE expires_at < NOW()");

  $step = $_SESSION['reset_step'] ?? 1;
  $errors = [];
  $info = '';

  // Step 1: Email submission
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_email'])) {
    // Rate limit: max 3 attempts per 10 minutes
    $now = time();
    if (!isset($_SESSION['reset_request_count'])) {
      $_SESSION['reset_request_count'] = 0;
      $_SESSION['reset_request_time']  = $now;
    }

    if ($now - $_SESSION['reset_request_time'] > 600) {
      $_SESSION['reset_request_count'] = 0;
      $_SESSION['reset_request_time']  = $now;
    }

    if ($_SESSION['reset_request_count'] >= 3) {
      $errors[] = 'Too many reset attempts. Please wait 10 minutes before trying again.';
    } else {
      $email = strtolower(trim($_POST['email'] ?? ''));

      if ($email === '') {
        $errors[] = 'Email is required.';
      } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
      } else {
        $_SESSION['reset_request_count']++;

        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
          $errors[] = 'If that email is registered, a reset code has been sent.';
        } else {
          $result = sendPasswordResetEmail($pdo, $email, $user['name']);
          if ($result['success']) {
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_step'] = 2;
            $step = 2;
          } else {
            $errors[] = 'Could not send reset email. Please try again later.';
          }
        }
      }
    }
      
  }

  // Step 2: OTP verification
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_otp'])) {
    $email = $_SESSION['reset_email'] ?? '';
    $code_input = trim($_POST['otp'] ?? '');

    if ($code_input === '') {
      $errors[] = 'Please enter the verification code.';
    } elseif ($email === '') {
      $errors[] = 'Session expired. Please start over.';
      $_SESSION['reset_step'] = 1;
      $step = 1;
    } else {
      $stmt = $pdo->prepare("
        SELECT code, expires_at, attempts FROM email_verifications
        WHERE email = ? LIMIT 1
      ");
      $stmt->execute([$email]);
      $row = $stmt->fetch();

      if (!$row) {
        $errors[] = 'Code expired or not found. Please request a new one.';
        $_SESSION['reset_step'] = 1;
        $step = 1;
      } elseif ($row['attempts'] >= 5) {
        $pdo->prepare("DELETE FROM email_verifications WHERE email = ?")->execute([$email]);
        unset($_SESSION['reset_email'], $_SESSION['reset_step']);
        $errors[] = 'Too many incorrect attempts. Please request a new code.';
        $step = 1;
      } elseif (strtotime($row['expires_at']) < time()) {
        $pdo->prepare("DELETE FROM email_verifications WHERE email = ?")->execute([$email]);
        $errors[] = 'Code has expired. Please request a new one.';
        $_SESSION['reset_step'] = 1;
        $step = 1;
      } elseif (!hash_equals($row['code'], $code_input)) {
        $pdo->prepare("UPDATE email_verifications SET attempts = attempts + 1 WHERE email = ?")->execute([$email]);
        $errors[] = 'Incorrect code. Please try again.';
      } else {
        $pdo->prepare("DELETE FROM email_verifications WHERE email = ?")->execute([$email]);
        $_SESSION['reset_step']     = 3;
        $_SESSION['reset_verified'] = true;
        $step = 3;
      }
    }
  }

  // Step 3: New password
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_password'])) {
    $email = $_SESSION['reset_email'] ?? '';
    $verified = $_SESSION['reset_verified'] ?? false;
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!$verified || $email === '') {
      $errors[] = 'Session expired. Please start over.';
      $_SESSION['reset_step'] = 1;
      $step = 1;
    } else {
      if ($password === '') $errors[] = 'Password is required.';
      elseif (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
      if ($confirm === '') $errors[] = 'Please confirm your password.';
      elseif ($password !== $confirm) $errors[] = 'Passwords do not match.';

      if (empty($errors)) {
        // Fetch current password hash to check against
        $stmt = $pdo->prepare("SELECT password FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $currentUser = $stmt->fetch();

        if ($currentUser && password_verify($password, $currentUser['password'])) {
          $errors[] = 'Your new password cannot be the same as your old password.';
        } else {
          $hash = password_hash($password, PASSWORD_DEFAULT);
          $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")->execute([$hash, $email]);

          unset($_SESSION['reset_email'], $_SESSION['reset_step'], $_SESSION['reset_verified']);
          session_regenerate_id(true);
          header('Location: login.php?password_reset=1');
          exit;
        }
      }
    }
  }

  // Resend OTP
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp'])) {
    $now = time();
    if (!isset($_SESSION['reset_request_time'])) {
      $_SESSION['reset_request_time'] = $now;
      $_SESSION['reset_request_count'] = 0;
    }

    if ($now - $_SESSION['reset_request_time'] > 600) {
      $_SESSION['reset_request_count'] = 0;
      $_SESSION['reset_request_time'] = $now;
    }

    if ($_SESSION['reset_request_count'] >= 3) {
      $errors[] = 'Too many resend attempts. Please wait before trying again.';
      $step = 2;
    } else {
      $_SESSION['reset_request_count']++;
      $email = $_SESSION['reset_email'] ?? '';
      if ($email !== '') {
        $stmt = $pdo->prepare("SELECT name FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
          $result = sendPasswordResetEmail($pdo, $email, $user['name']);
          if ($result['success']) {
            $info = 'A new code has been sent to your email.';
          } else {
            $errors[] = 'Could not resend code. Please try again.';
          }
        }
      }
      $step = 2;
    }
  }

  $activePage = '';
  $pageTitle  = 'Reset Password';
  include 'includes/header.php';
?>

<div class="auth-page">
  <div class="auth-card">

    <div class="auth-header">
      <a href="index.php" class="logo">
        <span>Kabsu<span class="logo-accent">hayan</span></span>
        <img src="./assets/img/v3_logo.png" alt="website-logo" class="auth-logo-img">
      </a>
      <h1>Reset Password</h1>
      <p>
        <?php if ($step === 1): ?>We'll send a reset code to your email.
        <?php elseif ($step === 2): ?>Enter the 6-digit code sent to your email.
        <?php else: ?>Choose a new password for your account.
        <?php endif; ?>
      </p>
    </div>

    <!-- Step indicator -->
    <div class="reset-steps">
      <div class="reset-step <?= $step >= 1 ? 'active' : '' ?> <?= $step > 1 ? 'done' : '' ?>">
        <div class="reset-step-dot">
          <?php if ($step > 1): ?>
            <svg xmlns="http://www.w3.org/2000/svg" height="14px" viewBox="0 -960 960 960" width="14px"><path d="M382-240 154-468l57-57 171 171 367-367 57 57-424 424Z"/></svg>
          <?php else: ?>1<?php endif; ?>
        </div>
        <span>Email</span>
      </div>
      <div class="reset-step-line <?= $step > 1 ? 'done' : '' ?>"></div>
      <div class="reset-step <?= $step >= 2 ? 'active' : '' ?> <?= $step > 2 ? 'done' : '' ?>">
        <div class="reset-step-dot">
          <?php if ($step > 2): ?>
            <svg xmlns="http://www.w3.org/2000/svg" height="14px" viewBox="0 -960 960 960" width="14px"><path d="M382-240 154-468l57-57 171 171 367-367 57 57-424 424Z"/></svg>
          <?php else: ?>2<?php endif; ?>
        </div>
        <span>Verify</span>
      </div>
      <div class="reset-step-line <?= $step > 2 ? 'done' : '' ?>"></div>
      <div class="reset-step <?= $step >= 3 ? 'active' : '' ?>">
        <div class="reset-step-dot">3</div>
        <span>Password</span>
      </div>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($info !== ''): ?>
      <div class="alert alert-success"><?= htmlspecialchars($info) ?></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
      <!-- Step 1: Email -->
      <form method="POST" class="auth-form">
        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" placeholder="firstname.lastname@cvsu.edu.ph" required />
        </div>
        <button type="submit" name="submit_email" class="btn-submit">Send Reset Code</button>
      </form>

    <?php elseif ($step === 2): ?>
      <!-- Step 2: OTP -->
      <form method="POST" class="auth-form">
        <div class="form-group">
          <label for="otp">Verification Code</label>
          <input type="text" id="otp" name="otp" placeholder="Enter 6-digit code" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" required/>
          <span class="form-hint">Check your email for the reset code. It expires in 15 minutes.</span>
        </div>
        <button type="submit" name="submit_otp" class="btn-submit">Verify Code</button>
      </form>
      <form method="POST" class="reset-resend-form">
        <button type="submit" name="resend_otp" class="reset-resend-btn">Resend code</button>
      </form>

    <?php else: ?>
      <!-- Step 3: New Password -->
      <form method="POST" class="auth-form" id="resetPasswordForm">
        <div class="form-group">
          <label for="password">New Password</label>
          <div class="password-input">
            <input type="password" id="password" name="password" placeholder="At least 8 characters" required />
            <button type="button" class="toggle-password" onclick="togglePassword('password', this)" aria-label="Show password">
              <span class="icon-visibility-on"><?= $visibilityOnIcon ?></span>
              <span class="icon-visibility-off" style="display:none;"><?= $visibilityOffIcon ?></span>
            </button>
          </div>
        </div>
        <div class="form-group">
          <label for="confirm">Confirm New Password</label>
          <div class="password-input">
            <input type="password" id="confirm" name="confirm" placeholder="Repeat your password" required />
            <button type="button" class="toggle-password" onclick="togglePassword('confirm', this)" aria-label="Show password">
              <span class="icon-visibility-on"><?= $visibilityOnIcon ?></span>
              <span class="icon-visibility-off" style="display:none;"><?= $visibilityOffIcon ?></span>
            </button>
          </div>
          <span class="form-hint" id="matchHint"></span>
        </div>
        <button type="submit" name="submit_password" class="btn-submit">Reset Password</button>
      </form>
    <?php endif; ?>

    <p class="auth-switch">
      Remembered it? <a href="login.php">Back to Login</a>
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
      iconOn.style.display  = 'inline-flex';
      iconOff.style.display = 'none';
    }
  }

  // Password match hint on step 3
  const pw = document.getElementById('password');
  const confirm = document.getElementById('confirm');
  const hint = document.getElementById('matchHint');

  if (pw && confirm && hint) {
    function checkMatch() {
      if (!confirm.value) { hint.textContent = ''; return; }
      if (pw.value === confirm.value) {
        hint.textContent = 'Passwords match';
        hint.style.color = 'var(--primary)';
      } else {
        hint.textContent = 'Passwords do not match';
        hint.style.color = '#b94040';
      }
    }
    pw.addEventListener('input', checkMatch);
    confirm.addEventListener('input', checkMatch);
  }
</script>

<?php include 'includes/footer.php'; ?>