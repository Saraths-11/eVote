<?php
session_start();
include 'config.php';

// Access Control: Admin Only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id']) && isset($_GET['type']) && isset($_GET['value'])) {
    $id = intval($_GET['id']);
    $type = $_GET['type'];
    $value = $_GET['value'];

    // Validate inputs
    if (!in_array($type, ['reg', 'vote', 'publish'])) {
        die("Invalid type");
    }

    // Logic for Registration Status
    if ($type === 'reg') {
        if (!in_array($value, ['open', 'closed'])) {
            die("Invalid value for registration");
        }

        $stmt = $conn->prepare("UPDATE elections SET registration_status = ? WHERE id = ?");
        $stmt->bind_param("si", $value, $id);

        if ($stmt->execute()) {
            // Also update main status for clarity
            $main_status = ($value === 'open') ? 'Registration Open' : 'Registration Ended';
            $conn->query("UPDATE elections SET status = '$main_status' WHERE id = $id AND status != 'Completed'");
        }
    }

    // Logic for Voting Status
    if ($type === 'vote') {
        if (!in_array($value, ['active', 'ended'])) {
            die("Invalid value for voting");
        }

        $stmt = $conn->prepare("UPDATE elections SET voting_status = ? WHERE id = ?");
        $stmt->bind_param("si", $value, $id);

        if ($stmt->execute()) {
            // Also update main status
            $main_status = ($value === 'active') ? 'Voting Active' : 'Completed';
            $conn->query("UPDATE elections SET status = '$main_status' WHERE id = $id");
        }
    }

    // Logic for Result Publication
    if ($type === 'publish') {
        if (!in_array($value, ['0', '1'])) {
            die("Invalid value for publish");
        }

        $stmt = $conn->prepare("UPDATE elections SET is_published = ? WHERE id = ?");
        $stmt->bind_param("ii", $value, $id);
        $stmt->execute();
    }

    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'manage_elections.php';
    if ($redirect === 'view_results') {
        header("Location: view_results.php?id=" . $id . "&msg=updated");
    } else {
        header("Location: manage_elections.php?msg=updated");
    }
    exit();
} else {
    header("Location: manage_elections.php");
    exit();
}
?>