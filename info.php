<?php 
  /*
    info.php - CvSU Marketplace Info Page
    Visible to both guests and logged-in users.
  */

  session_start();
  require_once "includes/db.php";

  $user = $_SESSION['user'] ?? null; 
?>

<?php  
  $activePage = "info"; 
  $pageTitle = "Help Center";
  include "includes/header.php"; 
?>

<!-- Page Hero -->
<section class="info-hero">
  <div class="info-hero-inner">
    <span class="info-hero-badge">CvSU Marketplace</span>
    <h1>How can we help you?</h1>
    <p>Learn about the platform, find answers to common questions, or get in touch with us.</p>
    <div class="info-nav-pills">
      <a href="#about-us-section" class="info-pill">About Us</a>
      <a href="#faq-section" class="info-pill">FAQ</a>
      <a href="#contact-section" class="info-pill">Contact</a>
    </div>
  </div>
</section>

<!-- About Us Section -->
<div class="info-section-full" id="about-us-section">
  <div class="info-inner">

    <div class="info-section-label">About Us</div>
    <h2 class="info-section-title">Built by students, for students.</h2>
    <p class="info-section-sub">
      CvSU Marketplace is a campus-exclusive buy-and-sell platform designed to make it simple, safe, and free all within the Cavite State University community.
    </p>

    <div class="about-grid">
      <div class="about-card">
        <div class="about-icon">
          <?= $trustShieldIcon ?>
        </div>
        <h3>Safe & Trusted</h3>
        <p>Only verified CvSU students can sign up. Every seller and buyer is a fellow CvSU student you can trust.</p>
      </div>

      <div class="about-card">
        <div class="about-icon">
          <?= $handShakeIcon ?>
        </div>
        <h3>Community First</h3>
        <p>Everything stays on campus. Meet up at familiar spots, deal with people you see every day.</p>
      </div>

      <div class="about-card">
        <div class="about-icon">
          <?= $priceTagIcon ?>
        </div>
        <h3>Always Free</h3>
        <p>No listing fees, no commissions, no hidden fees. Post and buy as much as you want at zero cost.</p>
      </div>

      <div class="about-card">
        <div class="about-icon">
          <?= $sustainableIcon ?>
        </div>
        <h3>Sustainable Transaction</h3>
        <p>Give pre-loved items a second life. Sell old textbooks, gadgets, and supplies to those who need them.</p>
      </div>
    </div>

    <!-- Mission strip -->
    <div class="about-mission">
      <div class="about-mission-text">
        <div class="info-section-label" style="color: var(--accent); border-color: rgba(192,184,122,0.4);">Our Mission</div>
        <h3>Empowering student-to-student interaction at CvSU</h3>
        <p>
          We believe every student deserves access to affordable resources. CvSU Marketplace makes buying and selling easier by having no middlemen, no strangers, no risk so you can focus on what matters.
        </p>
      </div>
      <div class="about-mission-stat-col">
        <div class="mission-stat">
          <span class="mission-stat-value">100%</span>
          <span class="mission-stat-label">CvSU-Exclusive</span>
        </div>
        <div class="mission-stat">
          <span class="mission-stat-value">Free</span>
          <span class="mission-stat-label">Always, no fees</span>
        </div>
        <div class="mission-stat">
          <span class="mission-stat-value">Safe</span>
          <span class="mission-stat-label">Verified accounts</span>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- FAQ Section -->
<div class="info-section-bg" id="faq-section">
  <div class="info-inner">

    <div class="info-section-label">FAQ</div>
    <h2 class="info-section-title">Frequently Asked Questions</h2>
    <p class="info-section-sub">
      Got questions? We've got answers. If you can't find what you're looking for, feel free to reach out below.
    </p>

    <div class="faq-grid">

      <!-- Column 1 -->
      <div class="faq-col">
        <div class="faq-item">
          <button class="faq-question" aria-expanded="false">
            Who can use CvSU Marketplace?
            <svg class="faq-chevron" xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px"><path d="M480-345 240-585l56-56 184 184 184-184 56 56-240 240Z"/></svg>
          </button>
          <div class="faq-answer">
            <p>
              CvSU Marketplace is exclusively for currently enrolled Cavite State University students. Registration requires a valid CvSU student email address to ensure a safe and trusted community.
            </p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-question" aria-expanded="false">
            Is it free to post a listing?
            <svg class="faq-chevron" xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px"><path d="M480-345 240-585l56-56 184 184 184-184 56 56-240 240Z"/></svg>
          </button>
          <div class="faq-answer">
            <p>
              Yes, it's completely free. There are no listing fees, no transaction fees, and no premium tiers. The platform is and will always be free for all CvSU students.
            </p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-question" aria-expanded="false">
            What can I sell on the marketplace?
            <svg class="faq-chevron" xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px"><path d="M480-345 240-585l56-56 184 184 184-184 56 56-240 240Z"/></svg>
          </button>
          <div class="faq-answer">
            <p>
              You can sell books, school supplies, electronics, clothing, food, accessories, and more. Items must be legal, safe, and appropriate for a campus community.
            </p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-question" aria-expanded="false">
            How do meetups and payments work?
            <svg class="faq-chevron" xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px"><path d="M480-345 240-585l56-56 184 184 184-184 56 56-240 240Z"/></svg>
          </button>
          <div class="faq-answer">
            <p>
              Buyers and sellers coordinate directly through the built-in messaging system. All transactions happen in person on campus at a meetup spot agreed upon by both parties. We recommend meeting in public, well-lit campus areas.
            </p>
          </div>
        </div>
      </div>

      <!-- Column 2 -->
      <div class="faq-col">
        <div class="faq-item">
          <button class="faq-question" aria-expanded="false">
            How do I mark an item as sold?
            <svg class="faq-chevron" xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px"><path d="M480-345 240-585l56-56 184 184 184-184 56 56-240 240Z"/></svg>
          </button>
          <div class="faq-answer">
            <p>
              Once a transaction is completed, the seller can mark the transaction as complete from the Transactions page or from the chat window. This automatically updates the listing status so other buyers know it's no longer available.
            </p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-question" aria-expanded="false">
            Can I edit or delete my listing?
            <svg class="faq-chevron" xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px"><path d="M480-345 240-585l56-56 184 184 184-184 56 56-240 240Z"/></svg>
          </button>
          <div class="faq-answer">
            <p>
              Yes, you can edit or delete any of your active listings at any time from your Dashboard or My Listings page. Once a transaction is created for a listing, certain edits may be restricted until the transaction is resolved.
            </p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-question" aria-expanded="false">
            What should I do if a seller is unresponsive?
            <svg class="faq-chevron" xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px"><path d="M480-345 240-585l56-56 184 184 184-184 56 56-240 240Z"/></svg>
          </button>
          <div class="faq-answer">
            <p>
              If a seller is unresponsive, you can cancel the transaction from your Transactions page and move on. If you believe there is any suspicious or abusive behavior, please contact us using the form in the Contact section below.
            </p>
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-question" aria-expanded="false">
            Is my personal information safe?
            <svg class="faq-chevron" xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px"><path d="M480-345 240-585l56-56 184 184 184-184 56 56-240 240Z"/></svg>
          </button>
          <div class="faq-answer">
            <p>
              We only collect information necessary to run the platform (i.e. your name, student email, and course). Your data is never sold or shared with third parties. Messages are only visible to the two parties in a conversation.
            </p>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Contact Section -->
<div class="info-section-full" id="contact-section">
  <div class="info-inner">

    <?php
      // Retrieve old form data in case of error
      $old = $_SESSION['contact_old'] ?? [];
      unset($_SESSION['contact_old']);
    ?>

    <div class="info-section-label">Contact</div>
    <h2 class="info-section-title">Get in touch with us</h2>
    <p class="info-section-sub">
      Have a concern, report an issue, or just want to say hi? Fill out the form and we'll get back to you as soon as we can.
    </p>

    <div class="contact-layout">

      <!-- Contact Form -->
      <div class="contact-form-wrap">
        <?php if (isset($_GET['sent']) && $_GET['sent'] === '1'): ?>
          <div class="alert alert-success" style="margin-bottom: 20px;">
            Your message was sent successfully! We'll get back to you soon.
          </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['contact_errors'])): ?>
          <div class="alert alert-error" style="margin-bottom: 20px;">
              <ul>
                  <?php foreach ($_SESSION['contact_errors'] as $err): ?>
                      <li><?= htmlspecialchars($err) ?></li>
                  <?php endforeach; ?>
              </ul>
          </div>
          <?php unset($_SESSION['contact_errors']); ?>
        <?php endif; ?>

        <form class="contact-form" action="includes/contact-handler.php" method="POST">
          <div class="form-group">
            <label for="contact-name">Full Name <span class="required">*</span></label>
            <input type="text" id="contact-name" name="name" placeholder="e.g. Spenzer Lima" required value="<?= htmlspecialchars($_SESSION['contact_old']['name'] ?? ($user ? $user['name'] : '')) ?>" />
          </div>

          <div class="form-group">
            <label for="contact-email">Email Address <span class="required">*</span></label>
            <input type="email" id="contact-email" name="email" placeholder="e.g. kevinspenzer.lima@cvsu.edu.ph" required value="<?= htmlspecialchars($old['email'] ?? ($user ? $user['email'] : '')) ?>" <?= $user ? 'readonly' : '' ?>>
            <?php if ($user): ?>
              <span class="form-hint">Using your registered CvSU email.</span>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label for="contact-subject">Subject <span class="required">*</span></label>
            <select id="contact-subject" name="subject" required>
              <option value="" disabled <?= empty($old['subject']) ? 'selected' : '' ?>>Select a topic</option>
              <option value="report-user" <?= (isset($old['subject']) && $old['subject'] === 'report-user') ? 'selected' : '' ?>>Report a User</option>
              <option value="report-listing" <?= (isset($old['subject']) && $old['subject'] === 'report-listing') ? 'selected' : '' ?>>Report a Listing</option>
              <option value="account-issue" <?= (isset($old['subject']) && $old['subject'] === 'account-issue') ? 'selected' : '' ?>>Account Issue</option>
              <option value="bug-report" <?= (isset($old['subject']) && $old['subject'] === 'bug-report') ? 'selected' : '' ?>>Bug / Technical Problem</option>
              <option value="suggestion" <?= (isset($old['subject']) && $old['subject'] === 'suggestion') ? 'selected' : '' ?>>Suggestion or Feedback</option>
              <option value="other" <?= (isset($old['subject']) && $old['subject'] === 'other') ? 'selected' : '' ?>>Other</option>
            </select>
          </div>

          <div class="form-group">
            <label for="contact-message">Message <span class="required">*</span></label>
            <textarea id="contact-message" name="message" rows="5" placeholder="Describe your concern" required><?= htmlspecialchars($old['message'] ?? '') ?></textarea>
          </div>

          <button type="submit" class="btn-submit">Send Message</button>
        </form>
      </div>

      <!-- Contact Info Side -->
      <div class="contact-info-col">
        <div class="contact-info-card">
          <h3>Other ways to reach us</h3>

          <div class="contact-info-item">
            <div class="contact-info-icon">
              <?= $mailIcon ?>
            </div>
            <div>
              <div class="contact-info-label">Email</div>
              <div class="contact-info-value">kabsuhayan@gmail.com</div>
            </div>
          </div>

          <div class="contact-info-item">
            <div class="contact-info-icon">
              <?= $locationIcon ?>
            </div>
            <div>
              <div class="contact-info-label">Department</div>
              <div class="contact-info-value">CEIT & DIT</div>
            </div>
          </div>

          <div class="contact-info-item">
            <div class="contact-info-icon">
              <?= $supportIcon ?>
            </div>
            <div>
              <div class="contact-info-label">Support Hours</div>
              <div class="contact-info-value">Mon - Fri, 12:00 PM - 6:00 PM</div>
            </div>
          </div>
        </div>

        <div class="contact-info-card contact-response-note">
          <div class="response-icon"><?= $messageIcon ?></div>
          <p>
            We typically respond within <strong>1–3 business days</strong>. For urgent concerns, please include as much detail as possible in your message.
          </p>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
	// FAQ Accordion
	document.querySelectorAll('.faq-question').forEach(btn => {
		btn.addEventListener('click', () => {
			const answer = btn.nextElementSibling;
			const isOpen = btn.getAttribute('aria-expanded') === 'true';

			// Close all others
			document.querySelectorAll('.faq-question').forEach(other => {
				other.setAttribute('aria-expanded', 'false');
				other.nextElementSibling.classList.remove('open');
			});

			// Toggle clicked
			if (!isOpen) {
				btn.setAttribute('aria-expanded', 'true');
				answer.classList.add('open');
			}
		});
	});
</script>

<?php include 'includes/footer.php'; ?>