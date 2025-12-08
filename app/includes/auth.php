<?php
session_start();

function requireLogin()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../index.php");
        exit();
    }
}

function requireAdmin()
{
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header("Location: ../no_permission.php");
        exit();
    }
}

function requireStudent()
{
    requireLogin();
    if ($_SESSION['role'] !== 'student') {
        header("Location: ../no_permission.php");
        exit();
    }
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}
?>