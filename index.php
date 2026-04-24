<?php
include "db.php";
session_start();

if (isset($_SESSION["role"]) && $_SESSION["role"] == "teacher") {
    echo "<script>window.location.href='teacher.php';</script>";
    exit;
}

if (isset($_SESSION["role"]) && $_SESSION["role"] == "student") {
    echo "<script>window.location.href='student.php';</script>";
    exit;
}

$_SESSION["login_message"] = "Please login first.";
echo "<script>window.location.href='login.php';</script>";
exit;
?>