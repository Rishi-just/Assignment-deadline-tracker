<?php
include "db.php";

session_destroy();

echo "<script>window.location.href='login.php';</script>";
exit;
?>