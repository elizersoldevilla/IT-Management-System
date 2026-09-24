<?php
require_once __DIR__ . '/../views/header.php';
require_login();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $subject = clean_input($_POST['subject']);
    $description = clean_input($_POST['description']);
    $priority = validate_in($_POST['priority'] ?? '', ['low', 'medium', 'high', 'urgent']);
    $category = validate_in($_POST['category'] ?? '', ['Hardware', 'Software', 'Network', 'Access/Login', 'Email', 'Other']);
    $user_id = $_SESSION['user_id'];

    if (empty($subject) || empty($description)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            $pdo->beginTransaction();

            $due_date = date('Y-m-d H:i:s');
            switch ($priority) {
                case 'urgent':
                    $due_date = date('Y-m-d H:i:s', strtotime('+4 hours'));
                    break;
                case 'high':
                    $due_date = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    break;
                case 'medium':
                    $due_date = date('Y-m-d H:i:s', strtotime('+48 hours'));
                    break;
                case 'low':
                    $due_date = date('Y-m-d H:i:s', strtotime('+72 hours'));
                    break;
            }

            $stmt = $pdo->prepare("INSERT INTO tickets (user_id, category, priority, subject, description, due_date, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $category, $priority, $subject, $description, $due_date, $user_id]);
            $ticket_id = $pdo->lastInsertId();

            log_action($pdo, $user_id, 'CREATE_TICKET', "Created Ticket #$ticket_id: $subject");
            invalidate_dashboard_cache();

            $admins_and_supervisors = $pdo->query("SELECT id FROM users WHERE role IN ('admin', 'supervisor', 'technician') AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins_and_supervisors as $admin_id) {
                if ($admin_id != $user_id) {
                    create_notification($pdo, $admin_id, 'ticket', 'New Ticket Assigned', "Ticket #$ticket_id: $subject", "ticket_details.php?id=$ticket_id");
                }
            }

            
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
                $target_dir = __DIR__ . "/../uploads/";
                $saved_name = secure_upload($_FILES['attachment'], $target_dir);
                
                if ($saved_name) {
                    $stmt = $pdo->prepare("INSERT INTO attachments (ticket_id, file_path, uploaded_by) VALUES (?, ?, ?)");
                    $stmt->execute([$ticket_id, $saved_name, $user_id]);
                } else {
                    
                    error_log("File upload rejected for ticket #$ticket_id");
                }
            }

            $admins = $pdo->query("SELECT email FROM users WHERE role IN ('admin', 'supervisor')")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins as $admin_email) {
                send_email($admin_email, "New Ticket #$ticket_id: $subject", "A new ticket has been created by User #$user_id.\n\nPriority: $priority\nCategory: $category\n\n$description");
            }

            $pdo->commit();
            $success = "Ticket submitted successfully!";
            
            echo "<script>setTimeout(function(){ window.location.href = 'ticket_details.php?id=$ticket_id'; }, 1000);</script>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Ticket Create Error: " . $e->getMessage());
            $error = "An error occurred while creating the ticket. Please try again.";
        }
    }
}
?>

<div class="mb-4">
    <h1 class="h3 mb-0">Submit New Ticket</h1>
</div>

<div class="glass-panel p-4" style="max-width: 800px; margin: 0 auto;">
    <?php if ($error): ?>
        <div class="bg-danger text-white p-3 rounded mb-4"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="bg-success text-white p-3 rounded mb-4"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <?php csrf_field(); ?>
        <div class="mb-3">
            <label class="block mb-2 font-medium">Subject *</label>
            <input type="text" name="subject" class="form-control" required placeholder="Brief summary of the issue">
        </div>
        
        <div class="grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1rem;">
            <div class="mb-3">
                <label class="block mb-2 font-medium">Category *</label>
                <select name="category" class="form-control" required>
                    <option value="Hardware">Hardware</option>
                    <option value="Software">Software</option>
                    <option value="Network">Network</option>
                    <option value="Access/Login">Access/Login</option>
                    <option value="Email">Email</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="block mb-2 font-medium">Priority *</label>
                <select name="priority" class="form-control" required>
                    <option value="low">Low - Minor issue, not urgent</option>
                    <option value="medium">Medium - Affects work, but workaround exists</option>
                    <option value="high">High - Cannot work, needs attention</option>
                    <option value="urgent">Urgent - System down, critical failure</option>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="block mb-2 font-medium">Description *</label>
            <textarea name="description" class="form-control" rows="6" required placeholder="Please describe the issue in detail..."></textarea>
        </div>

        <div class="mb-3">
            <label class="block mb-2 font-medium">Attachment (Optional)</label>
            <input type="file" name="attachment" class="form-control">
            <small class="text-muted">Screenshots or error logs help us resolve issues faster.</small>
        </div>
        
        <div class="mt-4 text-right" style="margin-top: 1.5rem; text-align: right;">
            <a href="my_tickets.php" class="btn btn-secondary mr-2" style="margin-right: 0.5rem; background: #6b7280; color: white;">Cancel</a>
            <button type="submit" class="btn btn-primary">Submit Ticket</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
