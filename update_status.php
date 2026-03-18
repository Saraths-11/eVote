<?php
session_start();
include 'config.php';

// Access Control: Admin Only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

/**
 * Helper to create a notification for students
 */
function create_notification($conn, $election_id, $title, $message, $type = 'info') {
    $stmt = $conn->prepare("INSERT INTO notifications (election_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $election_id, $title, $message, $type);
    return $stmt->execute();
}

if (isset($_GET['id']) && isset($_GET['type']) && isset($_GET['value'])) {
    $id = intval($_GET['id']);
    $type = $_GET['type'];
    $value = $_GET['value'];

    $check_stmt = $conn->prepare("SELECT status, registration_status, voting_status, nomination_status, registration_ended, nomination_ended, voting_ended, is_published FROM elections WHERE id = ?");
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $election = $check_stmt->get_result()->fetch_assoc();


    if (!$election) {
        die("Election not found.");
    }

    // Rule: Locked if Closed (Cannot change anything except publishing/closing itself which are final)
    if ($election['status'] === 'Closed' && $type !== 'close' && $type !== 'publish') {
        header("Location: manage_elections.php?error=election_closed_locked");
        exit();
    }

    // Logic for Registration Status
    if ($type === 'reg') {
        if (!in_array($value, ['open', 'closed'])) {
            die("Invalid value for registration");
        }

        // Rule: Permanent closure - cannot restart if it was already ended
        if ($value === 'open') {
            $ended_stmt = $conn->prepare("SELECT registration_ended FROM elections WHERE id = ?");
            $ended_stmt->bind_param("i", $id);
            $ended_stmt->execute();
            $already_ended = $ended_stmt->get_result()->fetch_assoc()['registration_ended'] ?? 0;

            if ($already_ended == 1) {
                header("Location: manage_elections.php?error=reg_already_ended");
                exit();
            }

            // Also check if voting has started
            if ($election['voting_status'] !== 'not_started') {
                header("Location: manage_elections.php?error=cannot_restart_reg");
                exit();
            }
        }

        $reg_ended_sql = ($value === 'closed') ? ", registration_ended = 1" : "";
        $stmt = $conn->prepare("UPDATE elections SET registration_status = ? $reg_ended_sql WHERE id = ?");
        $stmt->bind_param("si", $value, $id);

        if ($stmt->execute()) {
            // Also update main status for clarity
            $main_status = ($value === 'open') ? 'Registration Open' : 'Registration Ended';
            $conn->query("UPDATE elections SET status = '$main_status' WHERE id = $id AND status != 'Completed' AND status != 'Closed'");

            // Notification: Registration Start
            if ($value === 'open') {
                $e_title = $conn->query("SELECT title FROM elections WHERE id = $id")->fetch_assoc()['title'];
                create_notification($conn, $id, "Registration Started", "Participation registration for '$e_title' is now officially open. Register your profile now!", "info");
            }
        }
    }

    // Logic for Nomination Status
    if ($type === 'nomination') {
        if (!in_array($value, ['open', 'closed'])) {
            die("Invalid value for nomination");
        }

        // Rule: Nomination can only start after Participation Registration has ENDED
        if ($value === 'open' && $election['registration_ended'] == 0) {
            header("Location: manage_elections.php?error=nomination_needs_reg_end");
            exit();
        }

        // Rule: Nomination can only start if there's at least one approved participant
        if ($value === 'open') {
            $count_stmt = $conn->prepare("SELECT COUNT(*) as approved_count FROM participants WHERE election_id = ? AND status = 'Approved'");
            $count_stmt->bind_param("i", $id);
            $count_stmt->execute();
            $approved_count = $count_stmt->get_result()->fetch_assoc()['approved_count'];
            if ($approved_count == 0) {
                header("Location: manage_elections.php?error=no_approved_participants");
                exit();
            }

            if ($election['nomination_ended'] == 1) {
                header("Location: manage_elections.php?error=nomination_already_ended");
                exit();
            }
        }

        $nom_ended_sql = ($value === 'closed') ? ", nomination_ended = 1" : "";
        $stmt = $conn->prepare("UPDATE elections SET nomination_status = ? $nom_ended_sql WHERE id = ?");
        $stmt->bind_param("si", $value, $id);

        if ($stmt->execute()) {
            // Also update main status
            $main_status = ($value === 'open') ? 'Nomination Open' : 'Nomination Ended';
            $conn->query("UPDATE elections SET status = '$main_status' WHERE id = $id AND status != 'Completed' AND status != 'Closed'");

            // Notification: Nomination Start
            if ($value === 'open') {
                $e_title = $conn->query("SELECT title FROM elections WHERE id = $id")->fetch_assoc()['title'];
                create_notification($conn, $id, "Nomination Started", "The nomination phase for '$e_title' has begun. Approved candidates can now withdraw their participation if needed.", "success");
            }
        }
    }


    // Logic for Voting Status
    if ($type === 'vote') {
        if (!in_array($value, ['active', 'ended'])) {
            die("Invalid value for voting");
        }

        // Rule: Voting can only start if Participation Registration has ENDED (is closed)
        if ($value === 'active' && $election['registration_status'] === 'open') {
            header("Location: manage_elections.php?error=reg_not_ended");
            exit();
        }

        // Rule: Voting can only start if Nomination period has ENDED (is closed)
        if ($value === 'active' && $election['nomination_ended'] == 0) {
            header("Location: manage_elections.php?error=vote_needs_nomination_end");
            exit();
        }

        // Rule: Voting can only start if there is at least ONE approved candidate
        if ($value === 'active') {
            $count_stmt = $conn->prepare("SELECT COUNT(*) as approved_count FROM participants WHERE election_id = ? AND status = 'Approved'");
            $count_stmt->bind_param("i", $id);
            $count_stmt->execute();
            $approved_count = $count_stmt->get_result()->fetch_assoc()['approved_count'];
            if ($approved_count == 0) {
                header("Location: manage_elections.php?error=no_approved_candidates");
                exit();
            }
        }



        // Rule: Permanent closure - cannot restart if it was already ended
        if ($value === 'active') {
            if ($election['voting_ended'] == 1) {
                header("Location: manage_elections.php?error=vote_already_ended");
                exit();
            }
        }

        $vote_ended_sql = ($value === 'ended') ? ", voting_ended = 1" : "";
        $stmt = $conn->prepare("UPDATE elections SET voting_status = ? $vote_ended_sql WHERE id = ?");
        $stmt->bind_param("si", $value, $id);

        if ($stmt->execute()) {
            // Also update main status
            $main_status = ($value === 'active') ? 'active' : 'Completed';
            $conn->query("UPDATE elections SET status = '$main_status' WHERE id = $id AND status != 'Closed'");
            
            // Notification: Voting Start
            if ($value === 'active') {
                $e_title = $conn->query("SELECT title FROM elections WHERE id = $id")->fetch_assoc()['title'];
                create_notification($conn, $id, "Voting Is Now Live!", "The voting period for '$e_title' has officially started. Cast your vote carefully!", "warning");
            }
        }
    }

    // Logic for Closing Election
    if ($type === 'close') {
        if ($value !== '1') {
            die("Invalid value for close");
        }

        // Rule: Can only close if Voting has OFFICIALLY ENDED
        if ($election['voting_status'] !== 'ended') {
            header("Location: manage_elections.php?error=vote_not_ended_cannot_close");
            exit();
        }

        // Rule: Can only close after Publishing Results
        if ($election['is_published'] == 0) {
            header("Location: manage_elections.php?error=results_not_published");
            exit();
        }

        // Rule: Once closed, it stays closed.
        $stmt = $conn->prepare("UPDATE elections SET status = 'Closed' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }

    // Logic for Result Publication
    if ($type === 'publish') {
        if (!in_array($value, ['0', '1'])) {
            die("Invalid value for publish");
        }

        // Rule: Verify current publication status
        $pub_stmt = $conn->prepare("SELECT is_published FROM elections WHERE id = ?");
        $pub_stmt->bind_param("i", $id);
        $pub_stmt->execute();
        $is_already_published = $pub_stmt->get_result()->fetch_assoc()['is_published'] ?? 0;

        // Rule: Permanent Publication - Once published, it cannot be unpublished
        if ($value == '0' && $is_already_published == 1) {
            $err_redirect = isset($_GET['redirect']) && $_GET['redirect'] === 'view_results' ? "view_results.php?id=$id&error=already_published" : "manage_elections.php?error=already_published";
            header("Location: $err_redirect");
            exit();
        }

        // Rule: Can only publish if Voting has OFFICIALLY ENDED
        if ($value == '1' && $election['voting_status'] !== 'ended') {
            $err_redirect = isset($_GET['redirect']) && $_GET['redirect'] === 'view_results' ? "view_results.php?id=$id&error=election_not_ended" : "manage_elections.php?error=election_not_ended";
            header("Location: $err_redirect");
            exit();
        }

        $stmt = $conn->prepare("UPDATE elections SET is_published = ? WHERE id = ?");
        $stmt->bind_param("ii", $value, $id);

        if ($stmt->execute() && $value == '1') {
            // Notification: Result Published
            $e_title = $conn->query("SELECT title FROM elections WHERE id = $id")->fetch_assoc()['title'];
            create_notification($conn, $id, "Election Results Published", "The results for '$e_title' are now available. Check the results section to see the outcome!", "success");
        }
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