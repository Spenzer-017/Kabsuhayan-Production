</main> <!-- Main Closer -->

<footer>

    <?php
    $isLoggedIn = isset($_SESSION['user']);
    ?>

    <div class="footer-top">

        <!-- Brand column -->
        <div class="footer-brand">
            <a href="index.php" class="logo">
                <span>Kabsu<span class="logo-accent">hayan</span></span>
            </a>
            <p>Your campus marketplace - buy & sell within the CvSU community.</p>
        </div>

        <!-- Quick links -->
        <div class="footer-col">
            <h5>Marketplace</h5>
            <ul>
                <li><a <?= ($isLoggedIn) ? 'href="browse.php"' : 'href="login.php"' ?>>Browse Listings</a></li>
                <li><a <?= ($isLoggedIn) ? 'href="sell.php"' : 'href="login.php"' ?>>Post an Item</a></li>
                <li><a <?= ($isLoggedIn) ? 'href="transactions.php"' : 'href="login.php"' ?>>Transactions</a></li>
            </ul>
        </div>

        <!-- Account links -->
        <div class="footer-col">
            <h5>Account</h5>
            <ul>
                <li><a href="login.php">Login</a></li>
                <li><a href="signup.php">Sign Up</a></li>
                <li><a <?= ($isLoggedIn) ? 'href="profile.php"' : 'href="login.php"' ?>>My Profile</a></li>
            </ul>
        </div>

        <!-- Info links -->
        <div class="footer-col">
            <h5>Info</h5>
            <ul>
                <li><a href="info.php#about-us-section">About Us</a></li>
                <li><a href="info.php#faq-section">FAQ</a></li>
                <li><a href="info.php#contact-section">Contact</a></li>
            </ul>
        </div>

    </div>

    <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> Kabsuhayan - CvSU Student Marketplace. Made by Thumbtack's Team.</p>
    </div>

</footer>

<!-- Report Modal -->
<?php if (isset($_SESSION['user'])): ?>
<div class="report-modal-overlay" id="reportModal" role="dialog" aria-modal="true" aria-labelledby="reportModalTitle">
  <div class="report-modal-box">

    <div class="report-modal-header">
      <h2 class="report-modal-title" id="reportModalTitle">Report</h2>
      <button class="report-modal-close" id="reportModalClose" aria-label="Close">
        <?= $closeIcon ?>
      </button>
    </div>

    <!-- Step 1: Category selection -->
    <div id="reportStep1">
      <div class="report-modal-intro">
        <p class="report-modal-intro-title">Why are you reporting this?</p>
        <p class="report-modal-intro-sub">Your report helps keep Kabsuhayan safe for all students.</p>
      </div>
      <div class="report-reasons-list" id="reportReasonsList"></div>
    </div>

    <!-- Step 2: Details -->
    <div id="reportStep2" style="display:none">
      <button class="report-back-btn" id="reportBackBtn">
        <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px"><path d="M560-240 320-480l240-240 56 56-184 184 184 184-56 56Z"/></svg>
        Back
      </button>
      <div class="report-selected-reason" id="reportSelectedReason"></div>
      <div class="report-details-wrap">
        <label class="report-details-label">Additional details <span>(optional)</span></label>
        <textarea id="reportDetails" class="report-details-textarea" placeholder="Provide more context about your report..." maxlength="1000" rows="4"></textarea>
        <span class="report-details-hint">Max 1000 characters</span>
      </div>
      <div id="reportErrorMsg" class="report-error" style="display:none"></div>
      <button class="report-submit-btn" id="reportSubmitBtn">Submit Report</button>
    </div>

    <!-- Step 3: Success -->
    <div id="reportStep3" style="display:none">
      <div class="report-success">
        <?= $circleCheckIcon ?>
        <p>Your report has been submitted.</p>
        <p class="report-success-sub">Our team will review it shortly. Thank you for helping keep our community safe.</p>
      </div>
    </div>

  </div>
</div>
<?php endif; ?>

<!-- Confirmation Modals -->
<div class="modal-overlay" id="confirmModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-box">

        <div class="modal-icon" id="modalIcon">
            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#434343">
                <path d="M109-120q-11 0-20-5.5T75-140q-5-9-5.5-19.5T75-180l370-640q6-10 15.5-15t19.5-5q10 0 19.5 5t15.5 15l370 640q6 10 5.5 20.5T885-140q-5 9-14 14.5t-20 5.5H109Zm69-80h604L480-720 178-200Zm330.5-51.5Q520-263 520-280t-11.5-28.5Q497-320 480-320t-28.5 11.5Q440-297 440-280t11.5 28.5Q463-240 480-240t28.5-11.5Zm0-120Q520-383 520-400v-120q0-17-11.5-28.5T480-560q-17 0-28.5 11.5T440-520v120q0 17 11.5 28.5T480-360q17 0 28.5-11.5ZM480-460Z" />
            </svg>
        </div>

        <div class="modal-title" id="modalTitle">Are you sure?</div>
        <div class="modal-message" id="modalMessage"></div>

        <div class="modal-actions">
            <button class="modal-btn-cancel" id="modalCancel">Cancel</button>
            <button class="modal-btn-confirm" id="modalConfirm">Confirm</button>
        </div>

    </div>
</div>

<!-- Notification Dropdown -->
<?php if (isset($_SESSION['user'])): ?>
<div class="notif-dropdown" id="notifDropdown">
  <div class="notif-dropdown-header">
    <span class="notif-dropdown-title">Notifications</span>
    <div class="notif-dropdown-header-actions">
      <button class="notif-header-btn" id="notifMarkAll">Mark all read</button>
      <button class="notif-header-btn notif-header-btn--danger" id="notifClearAll">Clear all</button>
    </div>
  </div>
  <div class="notif-dropdown-tabs">
    <button class="notif-tab active" data-tab="all">All</button>
    <button class="notif-tab" data-tab="unread">Unread</button>
  </div>
  <div class="notif-dropdown-list" id="notifList">
    <div class="notif-loading">Loading...</div>
  </div>
  <a href="notifications.php" class="notif-see-all">See all notifications</a>
</div>
<?php endif; ?>

<?php
// Load page-specific JS (Uncomment if decided to put embedded scripts in external JS files)
// if (isset($activePage)) {
//     if ($activePage === 'dashboard') {
//         echo '<script src="js/dashboard.js"></script>';
//     } elseif ($activePage === 'browse') {
//         echo '<script src="js/browse.js"></script>';
//     } elseif ($activePage === 'home') {
//         echo '<script src="js/home.js"></script>';
//     } elseif ($activePage === 'transactions') {
//         echo '<script src="js/transactions.js"></script>';
//     } elseif ($activePage === 'sell') {
//         echo '<script src="js/sell.js"></script>';
//     } 
// }
?>

<script>
    (function() {
        const overlay = document.getElementById('confirmModal');
        const msgEl = document.getElementById('modalMessage');
        const iconEl = document.getElementById('modalIcon');
        const btnConfirm = document.getElementById('modalConfirm');
        const btnCancel = document.getElementById('modalCancel');

        let pendingAction = null;

        function openModal(message, onConfirm, isGreen) {
            msgEl.textContent = message;
            pendingAction = onConfirm;

            if (isGreen) {
                btnConfirm.classList.add('modal-btn-green');
                iconEl.classList.add('modal-icon-green');
            } else {
                btnConfirm.classList.remove('modal-btn-green');
                iconEl.classList.remove('modal-icon-green');
            }

            overlay.classList.add('modal-open');
            btnCancel.focus();
        }

        function closeModal() {
            overlay.classList.remove('modal-open');
            pendingAction = null;
        }

        btnConfirm.addEventListener('click', function() {
            const action = pendingAction;
            closeModal();
            if (action) action();
        });

        btnCancel.addEventListener('click', closeModal);

        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeModal();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && overlay.classList.contains('modal-open')) closeModal();
        });

        // Intercepts submit, shows modal, submits for real only on Confirm.
        document.addEventListener('submit', function(e) {
            const form = e.target;
            const msg = form.dataset.confirm;
            if (!msg) return;

            e.preventDefault();
            const isGreen = 'confirmGreen' in form.dataset;

            openModal(msg, function() {
                delete form.dataset.confirm;
                form.submit();
            }, isGreen);
        });

        // Intercepts click, shows modal, re-fires click only on Confirm.
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('button[data-confirm]');
            if (!btn) return;

            e.preventDefault();
            const msg = btn.dataset.confirm;
            const isGreen = 'confirmGreen' in btn.dataset;

            openModal(msg, function() {
                delete btn.dataset.confirm;
                btn.click();
            }, isGreen);
        });

        // Report Modal
        const reportModal    = document.getElementById('reportModal');
        const reportClose    = document.getElementById('reportModalClose');
        const reportStep1    = document.getElementById('reportStep1');
        const reportStep2    = document.getElementById('reportStep2');
        const reportStep3    = document.getElementById('reportStep3');
        const reportBackBtn  = document.getElementById('reportBackBtn');
        const reportSubmitBtn = document.getElementById('reportSubmitBtn');
        const reportReasonsList = document.getElementById('reportReasonsList');
        const reportSelectedReason = document.getElementById('reportSelectedReason');
        const reportDetails  = document.getElementById('reportDetails');
        const reportErrorMsg = document.getElementById('reportErrorMsg');

        const listingReasons = [
            'Fake or scam listing',
            'Wrong price or description',
            'Prohibited or inappropriate item',
            'Item already sold',
            'Spam or duplicate listing',
            'Other',
        ];

        const userReasons = [
            'Scam or fraud',
            'Harassment or bullying',
            'Fake or impersonation account',
            'Inappropriate behavior',
            'Did not show up to meetup',
            'Other',
        ];

        let reportData = {
            type: null,
            item_id: null,
            reported_user_id: null,
            reason: null,
        };

        function openReportModal(type, id, name) {
            if (!reportModal) return;

            reportData.type = type;
            reportData.item_id = type === 'listing' ? id : null;
            reportData.reported_user_id = type === 'user' ? id : null;
            reportData.reason = null;

            // Reset steps
            reportStep1.style.display = 'block';
            reportStep2.style.display = 'none';
            reportStep3.style.display = 'none';
            if (reportDetails) reportDetails.value = '';
            if (reportErrorMsg) reportErrorMsg.style.display = 'none';

            // Build reasons list
            const reasons = type === 'listing' ? listingReasons : userReasons;
            if (reportReasonsList) {
                reportReasonsList.innerHTML = reasons.map(function (r) {
                    return `<button class="report-reason-item" data-reason="${r}">
                        <span class="report-reason-text">${r}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px"><path d="M504-480 320-664l56-56 240 240-240 240-56-56 184-184Z"/></svg>
                    </button>`;
                }).join('');

                reportReasonsList.querySelectorAll('.report-reason-item').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        reportData.reason = btn.dataset.reason;
                        if (reportSelectedReason) reportSelectedReason.textContent = btn.dataset.reason;
                        reportStep1.style.display = 'none';
                        reportStep2.style.display = 'block';
                    });
                });
            }

            reportModal.classList.add('report-modal--open');
            document.body.style.overflow = 'hidden';
        }

        function closeReportModal() {
            if (!reportModal) return;
            reportModal.classList.remove('report-modal--open');
            document.body.style.overflow = '';
        }

        if (reportClose) reportClose.addEventListener('click', closeReportModal);

        if (reportModal) {
            reportModal.addEventListener('click', function (e) {
                if (e.target === reportModal) closeReportModal();
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && reportModal && reportModal.classList.contains('report-modal--open')) {
                closeReportModal();
            }
        });

        if (reportBackBtn) {
            reportBackBtn.addEventListener('click', function () {
                reportStep2.style.display = 'none';
                reportStep1.style.display = 'block';
            });
        }

        if (reportSubmitBtn) {
            reportSubmitBtn.addEventListener('click', function () {
                if (!reportData.reason) return;

                reportSubmitBtn.disabled = true;
                if (reportErrorMsg) reportErrorMsg.style.display = 'none';

                const fd = new FormData();
                fd.append('report_type', reportData.type);
                if (reportData.item_id)          fd.append('item_id', reportData.item_id);
                if (reportData.reported_user_id) fd.append('reported_user_id', reportData.reported_user_id);
                fd.append('reason', reportData.reason);
                fd.append('details', reportDetails ? reportDetails.value.trim() : '');

                fetch('api/report-submit.php', { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d.error) {
                            if (reportErrorMsg) {
                                reportErrorMsg.textContent = d.error;
                                reportErrorMsg.style.display = 'block';
                            }
                            reportSubmitBtn.disabled = false;
                            return;
                        }
                        reportStep2.style.display = 'none';
                        reportStep3.style.display = 'block';
                        setTimeout(closeReportModal, 3000);
                    })
                    .catch(function () {
                        if (reportErrorMsg) {
                            reportErrorMsg.textContent = 'Something went wrong. Please try again.';
                            reportErrorMsg.style.display = 'block';
                        }
                        reportSubmitBtn.disabled = false;
                    });
            });
        }

        // Expose globally so buttons on any page can call it
        window.openReportModal = openReportModal;

    })();
</script>

</body>

</html>