<!-- PHP Logic (Authentication, Session, etc.) -->
<?php
  /*
    signup.php - User Registration
  */

  session_start();

  date_default_timezone_set('Asia/Manila');

  // If already logged in, redirect to dashboard
  if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
  }

  // DB connection
  require_once 'includes/db.php';

  // Deletes expired email verification data like codes, etc.
  $pdo->exec("DELETE FROM email_verifications WHERE expires_at < NOW()");

  $errors = [];
  $name = '';
  $email = '';
?>

<!-- PHP Database Query -->
<?php
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm = trim($_POST['confirm'] ?? '');
    $agreed = isset($_POST['agreed_tnc']);

    // Validation
    if ($name === '') $errors[] = 'Full name is required.';
    if (strlen($name) > 80) $errors[] = 'Name must be 80 characters or less.';

    if ($email === '') $errors[] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    elseif (!str_ends_with($email, '@cvsu.edu.ph')) $errors[] = 'Only CvSU email addresses are allowed.';

    if ($password === '') $errors[] = 'Password is required.';
    elseif (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

    if ($confirm === '') $errors[] = 'Please confirm your password.';
    elseif ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (empty($_POST['agreed_tnc'])) $errors[] = 'You must agree to the Terms & Conditions to register.';

    if (empty($errors)) {
      $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
      $stmt->execute([$email]);
      if ($stmt->fetch()) $errors[] = 'An account with that email already exists.';
    }

    // All checks passed — store pending data and send OTP
    if (empty($errors)) {
      require_once 'includes/mail.php';

      $_SESSION['pending_user'] = [
        'name' => $name,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
      ];

      $result = sendVerificationCode($pdo, $email, $name);

      if ($result['success']) {
        header('Location: verify.php');
        exit;
      } else {
        unset($_SESSION['pending_user']);
        $errors[] = 'We could not send a verification email. Please try again later.';
      }
    }
  }
?>

<!-- PHP UI/UX Logic -->
<?php
  $activePage = '';
  $pageTitle = "Create Account";
  include 'includes/header.php';
?>

<!-- T&C Modal -->
<div class="tnc-backdrop" id="tncBackdrop" aria-hidden="true">
  <div class="tnc-modal" role="dialog" aria-modal="true" aria-labelledby="tncTitle">

    <div class="tnc-modal-header">
      <h2 id="tncTitle">Terms &amp; Conditions</h2>
      <button class="tnc-close-btn" id="tncCloseBtn" aria-label="Close">
        <?= $closeIcon ?>
      </button>
    </div>

    <div class="tnc-modal-body" id="tncBody">

      <p class="tnc-updated">Last updated: <?= date('F j, Y') ?></p>

      <p>Welcome to <strong>Kabsuhayan</strong>, the student marketplace of Cavite State University (CvSU). By creating an account and using this platform, you agree to the following terms.</p>

      <h3>1. Eligibility</h3>
      <p>This platform is exclusively for currently enrolled students of CvSU. You must use your official <code>@cvsu.edu.ph</code> email address to register. Accounts created with false information will be suspended.</p>

      <h3>2. Account Responsibility</h3>
      <p>You are responsible for maintaining the confidentiality of your login credentials. Any activity conducted through your account is your responsibility. Report suspected unauthorized access immediately.</p>

      <h3>3. Listings &amp; Transactions</h3>
      <p>All items listed on Kabsuhayan must be legal, accurately described, and appropriate for a school community. The following are strictly prohibited:</p>
      <ul>
        <li>Counterfeit or stolen goods</li>
        <li>Prohibited substances or weapons of any kind</li>
        <li>Adult or explicit content</li>
        <li>Items that violate CvSU's student code of conduct</li>
      </ul>
      <p>Kabsuhayan acts as a venue only. We do not mediate disputes, guarantee transactions, or hold funds. All transactions are directly between buyer and seller.</p>

      <h3>4. Conduct &amp; Community Standards</h3>
      <p>You agree to treat all users with respect. Harassment, threats, hate speech, spam, and any form of discrimination will result in immediate account suspension. We reserve the right to remove any content that violates community standards without prior notice.</p>

      <h3>5. Privacy &amp; Data</h3>
      <p>We collect only the information necessary to operate the platform (name, email, listings, messages). Your data is not sold to third parties. By registering, you consent to receiving platform-related notifications via your registered email.</p>

      <h3>6. Intellectual Property</h3>
      <p>Images and content you upload remain yours, but you grant Kabsuhayan a non-exclusive license to display them on the platform. Do not upload content that infringes on another person's copyright or intellectual property.</p>

      <h3>7. Limitation of Liability</h3>
      <p>Kabsuhayan is provided as-is by CvSU students. We are not liable for any loss, damage, or dispute arising from transactions conducted through the platform. Use at your own discretion.</p>

      <h3>8. Modifications</h3>
      <p>We may update these terms at any time. Continued use of the platform after changes are posted constitutes acceptance of the updated terms. Significant changes will be communicated via email.</p>

      <h3>9. Governing Rules</h3>
      <p>This platform operates within the academic and disciplinary policies of Cavite State University. Any violations may also be subject to university disciplinary action in addition to platform-level consequences.</p>

      <p>If you have questions about these terms, contact the platform administrators through the support page.</p>

    </div>

    <div class="tnc-modal-footer">
      <button class="tnc-btn-decline" id="tncDeclineBtn">Decline</button>
      <button class="tnc-btn-agree" id="tncAgreeBtn">I Agree</button>
    </div>

  </div>
</div>

<!-- Auth Page Card -->
<div class="auth-page">
  <div class="auth-card">

    <div class="auth-header">
      <a href="index.php" class="logo">
        <span>Kabsu<span class="logo-accent">hayan</span></span>
        <img src="./assets/img/v3_logo.png" alt="website-logo" class="auth-logo-img">
      </a>
      <h1>Create an account</h1>
      <p>Join the CvSU student marketplace for free</p>
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

    <form method="POST" class="auth-form" id="signupForm">

      <div class="form-group">
        <label for="name">Full Name <span class="required">*</span></label>
        <input type="text" id="name" name="name" placeholder="e.g. Spenzer Lima" value="<?= htmlspecialchars($name) ?>" maxlength="80" required/>
      </div>

      <div class="form-group">
        <label for="email">Email Address <span class="required">*</span></label>
        <input type="email" id="email" name="email" placeholder="firstname.lastname@cvsu.edu.ph" value="<?= htmlspecialchars($email) ?>" required/>
      </div>

      <div class="form-group">
        <label for="password">Password <span class="required">*</span></label>
        <div class="password-input">
          <input type="password" id="password" name="password" placeholder="At least 8 characters" required/>
          <button type="button" class="toggle-password" onclick="togglePassword('password', this)" aria-label="Show password">
            <span class="icon-visibility-on"><?= $visibilityOnIcon ?></span>
            <span class="icon-visibility-off" style="display:none;"><?= $visibilityOffIcon ?></span>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label for="confirm">Confirm Password <span class="required">*</span></label>
        <div class="password-input">
          <input type="password" id="confirm" name="confirm" placeholder="Repeat your password" required/>
          <button type="button" class="toggle-password" onclick="togglePassword('confirm', this)" aria-label="Show password">
            <span class="icon-visibility-on"><?= $visibilityOnIcon ?></span>
            <span class="icon-visibility-off" style="display:none;"><?= $visibilityOffIcon ?></span>
          </button>
        </div>
        <span class="form-hint" id="matchHint"></span>
      </div>

      <!-- T&C Checkbox -->
      <div class="form-group tnc-row">
        <div class="tnc-checkbox-label">

          <input type="checkbox" name="agreed_tnc" id="agreedTnc" <?= (isset($_POST['agreed_tnc'])) ? 'checked' : '' ?> required>

          <label for="agreedTnc" class="tnc-text">
            I have read and agree to the
          </label>

          <button type="button" class="tnc-link" id="tncOpenBtn">
            Terms &amp; Conditions
          </button>

        </div>
      </div>

      <button type="submit" class="btn-submit" id="submitBtn">Create Account</button>

    </form>

    <p class="auth-switch">
      Already have an account? <a href="login.php">Log in</a>
    </p>

  </div>
</div>

<script>
  /* Password visibility toggle */
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

  /* Password match hint */
  const password = document.getElementById('password');
  const confirm = document.getElementById('confirm');
  const hint = document.getElementById('matchHint');

  function checkMatch() {
    if (!confirm.value) {
      hint.textContent = '';
      hint.style.color = '';
      return;
    }
    if (password.value === confirm.value) {
      hint.textContent = 'Passwords match';
      hint.style.color = 'var(--primary)';
    } else {
      hint.textContent = 'Passwords do not match';
      hint.style.color = '#b94040';
    }
  }

  password.addEventListener('input', checkMatch);
  confirm.addEventListener('input', checkMatch);

  /* T&C Modal */
  const backdrop = document.getElementById('tncBackdrop');
  const body = document.getElementById('tncBody');
  const openBtn = document.getElementById('tncOpenBtn');
  const closeBtn = document.getElementById('tncCloseBtn');
  const declineBtn = document.getElementById('tncDeclineBtn');
  const agreeBtn = document.getElementById('tncAgreeBtn');
  const checkbox = document.getElementById('agreedTnc');

  function openModal() {
    backdrop.classList.add('tnc-open');
    backdrop.setAttribute('aria-hidden', 'false');
    body.scrollTop = 0;
    document.body.style.overflow = 'hidden';
    
    setTimeout(() => {
      closeBtn.focus();
    }, 100);
  }

  function closeModal() {
    backdrop.classList.remove('tnc-open');
    backdrop.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    openBtn.focus();
  }

  openBtn.addEventListener('click', openModal);
  closeBtn.addEventListener('click', closeModal);

  declineBtn.addEventListener('click', () => {
    checkbox.checked = false;
    closeModal();
  });

  agreeBtn.addEventListener('click', () => {
    checkbox.checked = true;
    closeModal();
  });

  /* Close on backdrop click */
  backdrop.addEventListener('click', (e) => {
    if (e.target === backdrop) closeModal();
  });

  /* Close on Escape */
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && backdrop.classList.contains('tnc-open')) closeModal();
  });

</script>

<?php include 'includes/footer.php'; ?>