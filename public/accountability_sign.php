<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
if (!has_privilege('manage_assets')) {
    redirect('dashboard.php');
}

$accountability_columns_exist = false;
try {
    $col_check = $pdo->query("SHOW COLUMNS FROM assets LIKE 'accountability_due_date'");
    $accountability_columns_exist = $col_check->rowCount() > 0;
} catch (Exception $e) {
    $accountability_columns_exist = false;
}

if (!$accountability_columns_exist) {
    $_SESSION['accountability_error'] = "Accountability columns have not been set up yet. Please contact the administrator.";
    redirect('accountability_tally.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $user_id = (int)$_POST['user_id'];
    $due_date = clean_input($_POST['due_date']);
    $notes = clean_input($_POST['notes']);

    if (!$user_id || !$due_date) {
        $_SESSION['accountability_error'] = "Please select personnel and set a due date.";
        redirect('accountability_tally.php');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE assets 
            SET accountability_due_date = ?, 
                accountability_signed_by = ?, 
                accountability_signed_at = NOW(),
                received_by_user_id = ?
            WHERE received_by_user_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$due_date, $user_id, $user_id, $user_id]);
        $affected = $stmt->rowCount();

        if ($affected > 0) {
            $user = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
            $user->execute([$user_id]);
            $user_data = $user->fetch();

            $log_msg = "Signed for $affected assets with due date: $due_date";
            if ($notes) {
                $log_msg .= " (Notes: $notes)";
            }
            log_action($pdo, $_SESSION['user_id'], 'ACCOUNTABILITY_SIGN', $log_msg);

            if ($user_data && $user_data['email']) {
                $subject = "Asset Accountability Signed - Due Date: $due_date";
                $body = "Hello {$user_data['full_name']},\n\nYou have signed for $affected assets with a due date of $due_date.\n\nPlease ensure all assets are accounted for by the due date.\n\n";
                if ($notes) {
                    $body .= "Notes: $notes\n\n";
                }
                $body .= "IT Management System";
                send_email($user_data['email'], $subject, $body);
            }

            $_SESSION['accountability_success'] = "Successfully signed for $affected assets.";
        } else {
            $_SESSION['accountability_error'] = "No assets found assigned to the selected personnel.";
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['accountability_error'] = "An error occurred: " . $e->getMessage();
    }

    redirect('accountability_tally.php');
}

redirect('accountability_tally.php');
