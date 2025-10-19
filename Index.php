<?php
// index.php - EasyCalf main landing page
// Redirect users to the calves page if logged in, or to login if not

session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: /app/modules/Calves/index.php");
    exit();
} else {
    header("Location: /app/modules/Users/login.php");
    exit();
}
?>
