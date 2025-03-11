<?php
session_start();
session_unset();    // Remove all session variables
session_destroy();  // Destroy the session
header("Location: Barbers Solution.html#login"); // Redirect to login page
exit();
?>
