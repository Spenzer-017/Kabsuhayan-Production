<?php
  /*
    verify.php - Email Verification
    Wires backend logic into the existing UI.
  */

  session_start();

  date_default_timezone_set('Asia/Manila');

  // If already logged in, no reason to be here
  if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
  }

  // If no pending registration send back to signup
  if (empty($_SESSION['pending_user'])) {
    header('Location: signup.php');
    exit;
  }

  // DB connection
  require_once 'includes/db.php';

  // Deletes expired email verification data like codes, etc.
  $pdo->exec("DELETE FROM email_verifications WHERE expires_at < NOW()");

  $pending = $_SESSION['pending_user'];
  $pendingEmail = $pending['email'];

  $otpError = false;
  $errors = [];
  $info = '';

  // Verify submitted code
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify') {
    $submitted = trim($_POST['code'] ?? '');

    if ($submitted === '' || !ctype_digit($submitted) || strlen($submitted) !== 6) {
      $errors[] = 'Please enter the 6-digit code.';
    } else {
      $stmt = $pdo->prepare(
        'SELECT id, code, expires_at, attempts
         FROM email_verifications
         WHERE email = ? AND expires_at > NOW()
         ORDER BY created_at DESC
         LIMIT 1'
      );
      $stmt->execute([$pendingEmail]);
      $row = $stmt->fetch();

      if (!$row) {
        $errors[] = 'No verification code found. Please request a new one.';
      } elseif ($row['attempts'] >= 5) {
        $errors[] = 'Too many incorrect attempts. Please request a new code.';
        $otpError = true;
      } elseif (new DateTime() > new DateTime($row['expires_at'])) {
        $errors[] = 'Your code has expired. Please request a new one.';
        $otpError = true;
      } elseif (!hash_equals($row['code'], $submitted)) {
        $pdo->prepare('UPDATE email_verifications SET attempts = attempts + 1 WHERE id = ?')->execute([$row['id']]);
        $remaining = max(0, 5 - ($row['attempts'] + 1));
        $errors[] = 'Incorrect code. ' . ($remaining > 0 ? "{$remaining} attempt(s) remaining." : 'No attempts remaining - please request a new code.');
        $otpError = true;
      } else {
        // If valid create the user account
        try {
          $pdo->prepare(
            'INSERT INTO users (name, email, password, avatar, created_at)
            VALUES (?, ?, ?, ?, NOW())'
          )->execute([
            $pending['name'],
            $pending['email'],
            $pending['password_hash'],
            'junimo_0',
          ]);

          // Clean up verification row and pending session data
          $pdo->prepare('DELETE FROM email_verifications WHERE email = ?')->execute([$pendingEmail]);
          unset($_SESSION['pending_user']);

          header('Location: login.php?registered=1');
          exit;
        } catch (PDOException $e) {
          if ($e->getCode() == 23000) {
            $errors[] = 'An account with this email already exists.';
          } else {
            throw $e;
          }
        }
      }
    }
  }

  // Resend Code
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend') {
    $stmt = $pdo->prepare(
      'SELECT created_at FROM email_verifications WHERE email = ? ORDER BY created_at DESC LIMIT 1'
    );
    $stmt->execute([$pendingEmail]);
    $last = $stmt->fetchColumn();

    if ($last && (time() - strtotime($last)) < 60) {
      $wait = 60 - (time() - strtotime($last));
      $errors[] = "Please wait {$wait} second(s) before requesting a new code.";
    } else {
      require_once 'includes/mail.php';
      $result = sendVerificationCode($pdo, $pending['email'], $pending['name']);
      if ($result['success']) {
        $info = 'resent';
      } else {
        $errors[] = 'Failed to resend the code. Please try again later.';
      }
    }
  }

  // Page Setup
  $activePage = '';
  $pageTitle = "Email Verification";
  include 'includes/header.php';

  // Mask the email for display
  function maskEmail(string $email): string {
    $parts = explode('@', $email, 2);

    if (count($parts) < 2) return $email;

    [$local, $domain] = $parts;
    $visible = substr($local, 0, min(3, strlen($local)));

    return $visible . str_repeat('*', max(0, strlen($local) - 3)) . '@' . $domain;
  }

  $maskedEmail = maskEmail($pendingEmail);

  $resendCooldown = 60;

  $stmt = $pdo->prepare(
    'SELECT created_at
    FROM email_verifications
    WHERE email = ?
    ORDER BY created_at DESC
    LIMIT 1'
  );

  $stmt->execute([$pendingEmail]);

  $lastCreated = $stmt->fetchColumn();

  $remainingCooldown = 0;

  if ($lastCreated) {
    $elapsed = time() - strtotime($lastCreated);
    $remainingCooldown = max(0, $resendCooldown - $elapsed);
  }

  // Dev Mode Setup
  define('DEV_MODE', false);
  $devCode = null;

  if (DEV_MODE) {
    $stmt = $pdo->prepare(
      'SELECT code
      FROM email_verifications
      WHERE email = ?
      ORDER BY created_at DESC
      LIMIT 1'
    );

    $stmt->execute([$pendingEmail]);
    $devCode = $stmt->fetchColumn();
  }
?>

<div class="auth-page verify-page">
  <div class="auth-card verify-card">

    <!-- Logo -->
    <div class="auth-header">
      <a href="index.php" class="logo verify-logo">
        <span>Kabsu<span class="logo-accent">hayan</span></span>
        <img src="./assets/img/v3_logo.png" alt="Kabsuhayan logo" class="auth-logo-img verify-logo-img" style="image-rendering:pixelated; image-rendering:crisp-edges;">
      </a>
    </div>

    <!-- Heading -->
    <h1 class="verify-title">Check your email</h1>
    <p class="verify-subtitle">
      We sent a 6-digit verification code to<br>
      <strong class="verify-email"><?= htmlspecialchars($maskedEmail) ?></strong>
    </p>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($info === 'resent'): ?>
      <div class="alert alert-success">
        A new code has been sent to your email.
      </div>
    <?php endif; ?>

    <!-- Dev Mode Container -->
    <?php if (DEV_MODE): ?>
      <div class="dev-code-container">
        <code>DEV MODE ENABLED: <strong><?= htmlspecialchars($devCode) ?></strong></code>
      </div>
    <?php endif; ?>

    <!-- OTP Input Form -->
    <form method="POST" action="verify.php" class="verify-form" id="verifyForm" autocomplete="off">
      <input type="hidden" name="action" value="verify">

      <div class="otp-group" id="otpGroup" aria-label="Enter 6-digit code">
        <input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" name="d1" id="otp1" placeholder="·" aria-label="Digit 1" autocomplete="one-time-code">
        <input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" name="d2" id="otp2" placeholder="·" aria-label="Digit 2">
        <input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" name="d3" id="otp3" placeholder="·" aria-label="Digit 3">

        <span class="otp-dash" aria-hidden="true">—</span>

        <input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" name="d4" id="otp4" placeholder="·" aria-label="Digit 4">
        <input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" name="d5" id="otp5" placeholder="·" aria-label="Digit 5">
        <input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" name="d6" id="otp6" placeholder="·" aria-label="Digit 6">
      </div>

      <!-- Hidden full-code field populated by JS -->
      <input type="hidden" name="code" id="otpHidden">

      <button type="submit" class="btn-submit verify-submit" id="verifyBtn" disabled>
        Verify Email
      </button>

    </form>

    <!-- Resend -->
    <form method="POST" action="verify.php" id="resendForm">
      <input type="hidden" name="action" value="resend">
      <div class="verify-resend">
        <p>Didn't receive it?</p>
        <button type="submit" class="verify-resend-btn" id="resendBtn">
          Resend code
        </button>
        <span class="verify-timer" id="resendTimer">
          Resend in <strong id="countdown">60</strong>s
        </span>
      </div>
    </form>

    <!-- Wrong email? -->
    <p class="verify-wrong">
      Wrong email? <a href="signup.php">Go back</a>
    </p>

  </div>
</div>

<script>
  (function () {

    const inputs = Array.from(document.querySelectorAll('.otp-input'));
    const hidden = document.getElementById('otpHidden');
    const submitBtn = document.getElementById('verifyBtn');
    const resendBtn = document.getElementById('resendBtn');
    const timerEl = document.getElementById('resendTimer');
    const countdown = document.getElementById('countdown');

    /* OTP input logic */
    function syncHidden() {
      const val = inputs.map(i => i.value).join('');
      hidden.value = val;
      submitBtn.disabled = val.length < 6 || !/^\d{6}$/.test(val);
      inputs.forEach((inp) => {
        inp.classList.toggle('otp-filled', inp.value !== '');
      });
    }

    inputs.forEach((inp, idx) => {

      inp.addEventListener('focus', () => inp.select());

      inp.addEventListener('input', e => {
        inp.value = inp.value.replace(/\D/g, '').slice(-1);
        syncHidden();
        if (inp.value && idx < inputs.length - 1) {
          inputs[idx + 1].focus();
        }
      });

      inp.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !inp.value && idx > 0) {
          inputs[idx - 1].focus();
          inputs[idx - 1].value = '';
          syncHidden();
          e.preventDefault();
        }
        if (e.key === 'ArrowLeft'  && idx > 0) inputs[idx - 1].focus();
        if (e.key === 'ArrowRight' && idx < inputs.length - 1) inputs[idx + 1].focus();
      });

      inp.addEventListener('paste', e => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
        pasted.split('').forEach((ch, i) => {
          if (inputs[i]) inputs[i].value = ch;
        });
        syncHidden();
        const nextEmpty = inputs.find(i => !i.value);
        (nextEmpty || inputs[inputs.length - 1]).focus();
      });
    });

    inputs[0].focus();

    /* Resend cooldown timer */
    let timerInterval;

    function startTimer(initial) {
      let seconds = initial ?? 60;
      resendBtn.style.display = 'none';
      timerEl.style.display = 'inline';
      countdown.textContent = seconds;

      timerInterval = setInterval(() => {
        seconds--;
        countdown.textContent = seconds;
        if (seconds <= 0) {
          clearInterval(timerInterval);
          timerEl.style.display = 'none';
          resendBtn.style.display = 'inline';
        }
      }, 1000);
    }

    const remainingCooldown = <?= (int)$remainingCooldown ?>;

    if (remainingCooldown > 0) {
      startTimer(remainingCooldown);
    } else {
      timerEl.style.display = 'none';
      resendBtn.style.display = 'inline';
    }

    /* Shake animation on wrong code  */
    <?php if ($otpError): ?>
    (function () {
      const group = document.getElementById('otpGroup');
      group.classList.add('otp-shake');
      setTimeout(() => group.classList.remove('otp-shake'), 600);
      inputs.forEach(i => { i.value = ''; i.classList.remove('otp-filled'); });
      syncHidden();
      inputs[0].focus();
    })();
    <?php endif; ?>

  })();
</script>

<?php include 'includes/footer.php'; ?>