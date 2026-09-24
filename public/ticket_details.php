<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$id = $_GET['id'] ?? null;
if (!$id) {
    redirect('dashboard.php');
}

$stmt = $pdo->prepare("
    SELECT t.*, u.full_name as creator, a.full_name as assignee 
    FROM tickets t 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN users a ON t.assigned_to = a.id 
    WHERE t.id = ?
");
$stmt->execute([$id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    $error = "Ticket not found.";
} elseif ($_SESSION['role'] === 'staff' && !has_privilege('view_tickets') && $ticket['user_id'] != $_SESSION['user_id']) {
    $error = "Access Denied.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    verify_csrf();
    if (has_role('admin') || has_role('supervisor') || has_role('technician')) {
        $new_status = validate_in($_POST['status'] ?? '', ['open', 'in_progress', 'on_hold', 'resolved', 'closed']);
        $assigned_to = isset($_POST['assigned_to']) ? (int) $_POST['assigned_to'] : (int) $ticket['assigned_to'];
        $now = date('Y-m-d H:i:s');

        $update_fields = ["status = ?", "assigned_to = ?", "updated_by = ?"];
        $update_params = [$new_status, $assigned_to, $_SESSION['user_id']];

        if ($new_status === 'resolved' && $ticket['status'] !== 'resolved') {
            $update_fields[] = "resolved_by = ?";
            $update_params[] = $_SESSION['user_id'];
        }
        if ($new_status === 'closed' && $ticket['status'] !== 'closed') {
            $update_fields[] = "closed_by = ?";
            $update_params[] = $_SESSION['user_id'];
        }

        $update_params[] = $id;
        $stmt = $pdo->prepare("UPDATE tickets SET " . implode(', ', $update_fields) . " WHERE id = ?");
        $stmt->execute($update_params);
        
        $log_msg = "Updated status to " . ucfirst($new_status);
        if ($assigned_to != $ticket['assigned_to']) {
            $log_msg .= " and assigned to user ID " . $assigned_to;
        }
        $stmt = $pdo->prepare("INSERT INTO ticket_updates (ticket_id, user_id, message, type) VALUES (?, ?, ?, 'status_change')");
        $stmt->execute([$id, $_SESSION['user_id'], $log_msg]);
        
        log_action($pdo, $_SESSION['user_id'], 'UPDATE_TICKET', "Updated Ticket #$id: $log_msg");
        invalidate_dashboard_cache();

        if ($assigned_to && $assigned_to != $ticket['user_id'] && $assigned_to != $_SESSION['user_id']) {
            create_notification($pdo, $assigned_to, 'ticket', 'Ticket Assigned', "You were assigned to Ticket #$id: {$ticket['subject']}", "ticket_details.php?id=$id");
        }
        if ($new_status == 'resolved' || $new_status == 'closed') {
            if ($ticket['user_id'] != $_SESSION['user_id']) {
                create_notification($pdo, $ticket['user_id'], 'ticket', 'Ticket ' . ucfirst($new_status), "Ticket #$id has been marked as $new_status", "ticket_details.php?id=$id");
            }
        }

        if (($assigned_to == $_SESSION['user_id'] && $assigned_to != $ticket['assigned_to']) || ($new_status == 'in_progress' && $ticket['status'] != 'in_progress')) {
            $notif_msg = $_SESSION['full_name'] . " accepted Ticket #" . $id . ": " . $ticket['subject'];
            $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, message, type) VALUES (?, ?, 'notification')");
            $stmt->execute([$_SESSION['user_id'], $notif_msg]);
        }

        if ($assigned_to && $assigned_to != $ticket['assigned_to']) {
            $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $assignee_email = $stmt->fetchColumn();
            if ($assignee_email) {
                send_email($assignee_email, "Assigned Ticket #$id", "You have been assigned to ticket #$id: {$ticket['subject']}");
            }
        }
        
        if ($new_status == 'resolved' || $new_status == 'closed') {
            $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $creator_email = $stmt->fetchColumn();
            if ($creator_email) {
                send_email($creator_email, "Ticket #$id Resolved", "Your ticket #$id has been marked as $new_status.\n\nSubject: {$ticket['subject']}");
            }
        }
        
        header("Location: ticket_details.php?id=" . $id);
        exit;
    }
}

require_once __DIR__ . '/../views/header.php';

$stmt = $pdo->prepare("SELECT * FROM attachments WHERE ticket_id = ?");
$stmt->execute([$id]);
$attachments = $stmt->fetchAll();

$staff_members = $pdo->query("SELECT id, full_name FROM users WHERE role != 'staff' AND status = 'active'")->fetchAll();
?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger d-flex align-items-center" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="row align-items-end mb-4">
    <div class="col-md-8">
        <div class="d-flex align-items-center mb-2">
            <a href="<?php echo $_SESSION['role'] === 'staff' ? 'my_tickets.php' : 'tickets.php'; ?>" class="btn btn-sm btn-icon btn-ghost-secondary me-2"><i class="fas fa-arrow-left"></i></a>
            <span class="text-light opacity-75 small text-uppercase fw-bold" style="letter-spacing: 0.1em;">Ticket #<?php echo $ticket['id']; ?></span>
        </div>
        <h1 class="h2 mb-0 fw-bold text-white"><?php echo htmlspecialchars($ticket['subject']); ?></h1>
    </div>
    <div class="col-md-4 text-md-end">
        <span class="badge <?php echo get_status_badge($ticket['status']); ?> px-3 py-2 rounded-pill text-uppercase" style="letter-spacing: 0.05em;"><?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?></span>
    </div>
</div>

<div class="row g-4">
    
    <div class="col-lg-8">
        
        <div class="card border-secondary border-opacity-10 bg-surface-dark mb-4">
            <div class="card-header bg-darker border-bottom border-secondary border-opacity-10 py-3">
                <div class="d-flex align-items-center">
                    <div class="avatar-circle bg-primary bg-opacity-25 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width:40px;height:40px;">
                        <?php echo substr($ticket['creator'], 0, 1); ?>
                    </div>
                    <div>
                        <div class="text-white fw-medium"><?php echo htmlspecialchars($ticket['creator']); ?></div>
                        <div class="text-white-50 small">Reported on <?php echo date('M d, Y \a\t h:i A', strtotime($ticket['created_at'])); ?></div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <p class="text-light opacity-75 mb-0" style="white-space: pre-wrap; line-height: 1.6;"><?php echo htmlspecialchars($ticket['description']); ?></p>
            </div>
            <?php if (!empty($attachments)): ?>
            <div class="card-footer bg-darker border-top border-secondary border-opacity-10 py-3">
                <h6 class="small fw-bold text-white mb-2">Attachments</h6>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($attachments as $att): ?>
                        <a href="serve_file.php?file=<?php echo urlencode($att['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary bg-dark border-secondary border-opacity-25 text-secondary hover-text-white">
                            <i class="fas fa-paperclip me-2"></i><?php echo htmlspecialchars($att['file_path']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        
        <div class="card border-secondary border-opacity-10 bg-surface-dark" style="min-height: 500px; display: flex; flex-direction: column;">
            <div class="card-header bg-darker border-bottom border-secondary border-opacity-10 py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 text-white"><i class="fas fa-comments me-2 text-primary opacity-75"></i>Discussion</h6>
                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Live Updates</span>
            </div>
            
            <div class="card-body p-0 d-flex flex-column flex-grow-1">
                
                <div id="chat-history" class="p-4 flex-grow-1 custom-scrollbar" style="max-height: 500px; overflow-y: auto; background-color: rgba(0,0,0,0.2);">
                    <div class="text-center text-white-50 opacity-75 py-5"><i class="fas fa-spinner fa-spin fa-2x mb-3"></i><br>Loading conversation...</div>
                </div>

                
                <div class="p-3 bg-darker border-top border-secondary border-opacity-10">
                    <form id="reply-form" class="position-relative">
                        <textarea id="message-input" class="form-control bg-dark border-secondary border-opacity-25 text-light placeholder-secondary pe-5" rows="2" placeholder="Type your message..." style="resize: none;" required></textarea>
                        <button type="submit" class="btn btn-primary btn-icon btn-sm position-absolute bottom-0 end-0 m-2 rounded-circle shadow-sm" style="width: 32px; height: 32px;"><i class="fas fa-paper-plane small"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    
    <div class="col-lg-4">
        
        <div class="card border-secondary border-opacity-10 bg-surface-dark mb-4">
            <div class="card-header bg-darker border-bottom border-secondary border-opacity-10 py-3">
                <h6 class="mb-0 text-white">Ticket Details</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="small text-light opacity-75 text-uppercase fw-bold mb-1" style="font-size: 0.7rem;">Priority</label>
                    <?php 
                        $priorityClass = 'bg-secondary text-secondary';
                        $priorityIcon = 'fa-minus';
                        if($ticket['priority'] == 'high') { $priorityClass = 'bg-danger text-danger'; $priorityIcon = 'fa-arrow-up'; }
                        if($ticket['priority'] == 'medium') { $priorityClass = 'bg-warning text-warning'; $priorityIcon = 'fa-arrow-right'; }
                        if($ticket['priority'] == 'low') { $priorityClass = 'bg-success text-success'; $priorityIcon = 'fa-arrow-down'; }
                    ?>
                    <div class="d-flex align-items-center <?php echo str_replace('bg-', 'text-', $priorityClass); ?> opacity-75">
                        <i class="fas <?php echo $priorityIcon; ?> me-2"></i>
                        <span class="fw-bold text-uppercase"><?php echo ucfirst($ticket['priority']); ?></span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="small text-light opacity-75 text-uppercase fw-bold mb-1" style="font-size: 0.7rem;">Assigned Agent</label>
                     <div class="d-flex align-items-center mt-1">
                        <?php if ($ticket['assignee']): ?>
                            <div class="avatar-circle flex-shrink-0 bg-dark border border-secondary border-opacity-25 text-secondary rounded-circle d-flex align-items-center justify-content-center me-2" style="width:32px;height:32px;">
                                <?php echo substr($ticket['assignee'], 0, 1); ?>
                            </div>
                            <span class="text-white small fw-medium"><?php echo htmlspecialchars($ticket['assignee']); ?></span>
                        <?php else: ?>
                            <span class="text-white-50 small fst-italic border border-dashed border-secondary border-opacity-25 px-2 py-1 rounded">Unassigned</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <label class="small text-light opacity-75 text-uppercase fw-bold mb-1" style="font-size: 0.7rem;">Ticket ID</label>
                    <div class="font-monospace text-white-50 small">#<?php echo str_pad($ticket['id'], 6, '0', STR_PAD_LEFT); ?></div>
                </div>
            </div>
        </div>

        
        <?php if (has_role('admin') || has_role('supervisor') || has_role('technician')): ?>
        <div class="card border-secondary border-opacity-10 bg-surface-dark">
            <div class="card-header bg-darker border-bottom border-secondary border-opacity-10 py-3">
                <h6 class="mb-0 text-white">Management</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="update_status" value="1">
                    
                    <div class="mb-3">
                        <label class="small text-light opacity-75 text-uppercase fw-bold mb-1" style="font-size: 0.7rem;">Update Status</label>
                        <select name="status" class="form-select form-select-sm bg-dark border-secondary border-opacity-25 text-white">
                            <option value="open" <?php echo $ticket['status'] == 'open' ? 'selected' : ''; ?>>Open</option>
                            <option value="in_progress" <?php echo $ticket['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="on_hold" <?php echo $ticket['status'] == 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                            <option value="resolved" <?php echo $ticket['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            <option value="closed" <?php echo $ticket['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="small text-light opacity-75 text-uppercase fw-bold mb-1" style="font-size: 0.7rem;">Assign To</label>
                        <select name="assigned_to" class="form-select form-select-sm bg-dark border-secondary border-opacity-25 text-white">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($staff_members as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>" <?php echo $ticket['assigned_to'] == $staff['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($staff['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm w-100 shadow-sm">Update Ticket</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const ticketId = <?php echo $id; ?>;
const currentUserId = <?php echo $_SESSION['user_id']; ?>;
const chatHistory = document.getElementById('chat-history');
const replyForm = document.getElementById('reply-form');
const messageInput = document.getElementById('message-input');

function fetchUpdates() {
    fetch(`api/ticket_updates.php?action=fetch&ticket_id=${ticketId}`)
        .then(response => response.json())
        .then(data => {
            if (data.updates) {
                chatHistory.innerHTML = '';
                if (data.updates.length === 0) {
                    chatHistory.innerHTML = '<div class="text-center text-secondary opacity-50 py-5"><i class="fas fa-comment-slash fa-2x mb-3"></i><br>No messages yet. Start the conversation!</div>';
                } else {
                    let lastDate = '';
                    
                    data.updates.forEach(update => {
                        const isMe = update.user_id == currentUserId;
                        const isSystem = update.type === 'status_change';
                        
                        let html = '';

                        if (isSystem) {
                            html = `
                                <div class="d-flex justify-content-center mb-4">
                                    <div class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-10 fw-normal px-3 py-1">
                                        <i class="fas fa-info-circle me-1"></i> ${update.message}
                                        <span class="opacity-50 ms-1" style="font-size: 0.7em;">${update.formatted_date}</span>
                                    </div>
                                </div>
                            `;
                        } else {
                            const alignClass = isMe ? 'justify-content-end' : 'justify-content-start';
                            const bubbleColor = isMe ? 'bg-primary text-white' : 'bg-dark border border-secondary border-opacity-25 text-light';
                            const metaAlign = isMe ? 'text-end' : 'text-start';
                            const initial = update.full_name.charAt(0);
                            
                            html = `
                                <div class="d-flex ${alignClass} mb-3">
                                    ${!isMe ? `<div class="avatar-circle flex-shrink-0 bg-secondary bg-opacity-25 text-secondary rounded-circle d-flex align-items-center justify-content-center me-2 mt-1" style="width:32px;height:32px;font-size:0.75rem;">${initial}</div>` : ''}
                                    <div style="max-width: 75%;">
                                        <div class="p-3 rounded-3 ${bubbleColor} shadow-sm">
                                            <div class="small" style="line-height:1.5;">${update.message}</div>
                                        </div>
                                        <div class="text-secondary opacity-50 small mt-1 ${metaAlign}" style="font-size: 0.75rem;">${isMe ? 'You' : update.full_name} • ${update.formatted_date}</div>
                                    </div>
                                </div>
                            `;
                        }
                        
                        chatHistory.insertAdjacentHTML('beforeend', html);
                    });
                }
                chatHistory.scrollTop = chatHistory.scrollHeight;
            }
        });
}

fetchUpdates();

let ticketPollInterval = null;
const TICKET_POLL_MS = 10000;

function startTicketPolling() {
    if (ticketPollInterval) return;
    ticketPollInterval = setInterval(fetchUpdates, TICKET_POLL_MS);
}

function stopTicketPolling() {
    if (ticketPollInterval) {
        clearInterval(ticketPollInterval);
        ticketPollInterval = null;
    }
}

document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        stopTicketPolling();
    } else {
        fetchUpdates();
        startTicketPolling();
    }
});

startTicketPolling();

replyForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const message = messageInput.value.trim();
    if (!message) return;

    fetch(`api/ticket_updates.php?action=post&ticket_id=${ticketId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ message: message })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageInput.value = '';
            fetchUpdates(); 
        } else {
            alert('Error sending message');
        }
    });
});

function disableDuringSubmit(form) {
    const btn = form.querySelector('button[type="submit"]');
    if (!btn) return;
    btn.disabled = true;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';
    btn.dataset.originalText = originalText;
}

function enableAfterSubmit(form) {
    const btn = form.querySelector('button[type="submit"]');
    if (!btn || !btn.dataset.originalText) return;
    btn.disabled = false;
    btn.innerHTML = btn.dataset.originalText;
}

document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        disableDuringSubmit(this);
    });
});
</script>

<?php require_once __DIR__ . '/../views/footer.php'; ?>

