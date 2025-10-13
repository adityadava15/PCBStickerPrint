<!-- logout.php -->
<?php
session_start();
session_destroy();
header("Location: Login1.php");
exit;
?>