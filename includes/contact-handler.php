<?php
  /**
      includes/contact-handler.php 
      Handles the contact form from info.php.
      Validates input, composes an email, sends it, and redirects back.
   */

  session_start();
  require_once __DIR__ . '/mail.php';

  // Security Checks

  // Only process POST requests
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../info.php');
    exit;
  }

  // Get and sanitize inputs

  $user = $_SESSION['user'] ?? null;

  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $subject = trim($_POST['subject'] ?? '');
  $message = trim($_POST['message'] ?? '');

  // Allowed subjects
  $allowed_subjects = [
    'report-user',
    'report-listing',
    'account-issue',
    'bug-report',
    'suggestion',
    'other'
  ];

  // Validate fields
  $errors = [];

  if ($name === '') {
    $errors[] = 'Full name is required.';
  } elseif (strlen($name) > 80) {
    $errors[] = 'Name must be 80 characters or less.';
  }

  if ($email === '') {
    $errors[] = 'Email address is required.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please provide a valid email address.';
  }

  if (!in_array($subject, $allowed_subjects)) {
    $errors[] = 'Please select a valid subject.';
  }

  if ($message === '') {
    $errors[] = 'Message is required.';
  } elseif (strlen($message) > 3000) {
    $errors[] = 'Message must be 3000 characters or less.';
  }

  // If validation fails, redirect back with errors (stored in session)
  if (!empty($errors)) {
    $_SESSION['contact_errors'] = $errors;
    $_SESSION['contact_old'] = compact('name', 'email', 'subject', 'message');
    header('Location: ../info.php#contact-section');
    exit;
  }

  // Build the email

  // Destination
  $to = 'kabsuhayan@gmail.com';

  // Subject tag mapping
  $subject_tags = [
    'report-user' => '[REPORT USER]',
    'report-listing' => '[REPORT LISTING]',
    'account-issue' => '[ACCOUNT ISSUE]',
    'bug-report' => '[BUG REPORT]',
    'suggestion' => '[SUGGESTION / FEEDBACK]',
    'other' => '[OTHER]',
  ];
  $tag = $subject_tags[$subject] ?? '[OTHER]';

  // Email subject
  $email_subject = "$tag Contact from Kabsuhayan Website";

  // Timestamp
  $timestamp = date('F j, Y \a\t g:i A');

  // Compose the plain text body like a support ticket
  $body = "
  <h2>New Contact Message</h2>

  <p><strong>Name:</strong> $name<br>
  <strong>Email:</strong> $email<br>
  <strong>Category:</strong> $tag<br>
  <strong>Date:</strong> $timestamp</p>

  <hr>

  <p><strong>Message:</strong></p>
  <p>" . nl2br(htmlspecialchars($message)) . "</p>

  <hr>

  <p style='font-size:12px;color:gray;'>
  Sent from Kabsuhayan contact form
  </p>
  ";

  // Send the email
  $mail_sent = sendMail(
    $email,
    $name,
    $email_subject,
    $body
  );
 
  // Log failures for debugging
  if (!$mail_sent) {
    error_log("Contact form: Failed to send email from $email regarding $subject");
    $_SESSION['contact_errors'] = ['Sorry, your message could not be sent. Please try again later.'];
    header('Location: ../info.php#contact-section');
    exit;
  }

  // Redirect on success
  header('Location: ../info.php?sent=1#contact-section');
  exit;
?>