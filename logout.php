<!-- PHP Logic (Authentication, Session, etc.) -->
<?php
  /*
    logout.php - Destroys the session and redirects to login.
    No UI needed - just a redirect target.
  */

  session_start();
  $_SESSION = [];
  session_destroy();

  header('Location: login.php?logged_out=1');
  exit;
?>