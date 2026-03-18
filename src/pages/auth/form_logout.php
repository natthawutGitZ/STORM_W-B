<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
unset($_SESSION['google_form_user']);
header("Location: form_login.php");
exit;
?>
