<?php






function clean_input($data, $type = 'string')
{
    $data = trim($data);
    $data = stripslashes($data);
    
    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_SANITIZE_EMAIL);
        case 'url':
            return filter_var($data, FILTER_SANITIZE_URL);
        case 'int':
            return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        default:
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}


function esc($data)
{
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}


function validate_in($value, array $allowed, $default = null)
{
    if (in_array($value, $allowed, true)) {
        return $value;
    }
    return $default !== null ? $default : $allowed[0];
}


function generate_base32_secret($length = 20)
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[random_int(0, 31)];
    }
    return $secret;
}


function base32_decode($b32)
{
    $b32 = strtoupper($b32);
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    for ($i = 0; $i < strlen($b32); $i++) {
        $val = strpos($chars, $b32[$i]);
        if ($val === false) continue;
        $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    for ($i = 0; $i < strlen($bits); $i += 8) {
        $byte = substr($bits, $i, 8);
        if (strlen($byte) == 8) {
            $bytes .= chr(bindec($byte));
        }
    }
    return $bytes;
}


function generate_totp($secret, $timeSlice = null)
{
    if ($timeSlice === null) {
        $timeSlice = floor(time() / 30);
    }
    $binSecret = base32_decode($secret);
    $timeBytes = pack('J', $timeSlice);
    $hash = hash_hmac('sha1', $timeBytes, $binSecret, true);
    $offset = ord($hash[19]) & 0x0F;
    $code = (ord($hash[$offset]) & 0x7F) << 24 |
            (ord($hash[$offset + 1]) & 0xFF) << 16 |
            (ord($hash[$offset + 2]) & 0xFF) << 8 |
            (ord($hash[$offset + 3]) & 0xFF);
    return str_pad($code % 1000000, 6, '0', STR_PAD_LEFT);
}


function verify_totp($secret, $code)
{
    if (strlen($code) != 6 || !ctype_digit($code)) {
        return false;
    }
    $current = generate_totp($secret);
    $previous = generate_totp($secret, floor(time() / 30) - 1);
    $next = generate_totp($secret, floor(time() / 30) + 1);
    return hash_equals($current, $code) || hash_equals($previous, $code) || hash_equals($next, $code);
}


function get_mfa_requirements($role = null)
{
    if ($role === null && isset($_SESSION['role'])) {
        $role = $_SESSION['role'];
    }
    return in_array($role, ['admin', 'supervisor']);
}


function encrypt_data($data, $key = APP_KEY)
{
    if (empty($data)) return $data;
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}


function decrypt_data($data, $key = APP_KEY)
{
    if (empty($data)) return $data;
    $data = base64_decode($data);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
}







function csrf_token()
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}


function csrf_field()
{
    echo '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
}


function verify_csrf()
{
    $token = $_POST['_csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('Security Error: Invalid request token. Please refresh the page and try again.');
    }
}


function get_password_algo()
{
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
}


function hash_password($password)
{
    $algo = get_password_algo();
    if ($algo === PASSWORD_ARGON2ID) {
        return password_hash($password, $algo, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 2
        ]);
    }
    return password_hash($password, $algo);
}


function needs_password_rehash($hash)
{
    return password_needs_rehash($hash, get_password_algo());
}







function is_rate_limited($pdo, $action_key, $max_attempts = 5, $window_secs = 900)
{
    try {
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action_key VARCHAR(255) NOT NULL,
            attempts INT DEFAULT 1,
            first_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_action_key (action_key)
        )");

        
        $pdo->prepare("DELETE FROM rate_limits WHERE last_attempt < DATE_SUB(NOW(), INTERVAL ? SECOND)")
            ->execute([$window_secs]);

        
        $stmt = $pdo->prepare("SELECT attempts, first_attempt FROM rate_limits WHERE action_key = ?");
        $stmt->execute([$action_key]);
        $record = $stmt->fetch();

        if (!$record) {
            
            $stmt = $pdo->prepare("INSERT INTO rate_limits (action_key, attempts) VALUES (?, 1)");
            $stmt->execute([$action_key]);
            return false;
        }

        if ($record['attempts'] >= $max_attempts) {
            return true; 
        }

        
        $stmt = $pdo->prepare("UPDATE rate_limits SET attempts = attempts + 1 WHERE action_key = ?");
        $stmt->execute([$action_key]);
        return false;

    } catch (Exception $e) {
        error_log("Rate Limit Error: " . $e->getMessage());
    return false;
}
}

function track_session($pdo, $user_id)
{
    $session_id = session_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE last_activity = CURRENT_TIMESTAMP, is_active = 1");
    $stmt->execute([$user_id, $session_id, $ip_address, $user_agent]);
}

function get_active_sessions($pdo, $user_id)
{
    $stmt = $pdo->prepare("SELECT * FROM user_sessions WHERE user_id = ? AND is_active = 1 ORDER BY last_activity DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function invalidate_session($pdo, $session_id)
{
    $stmt = $pdo->prepare("UPDATE user_sessions SET is_active = 0 WHERE session_id = ?");
    $stmt->execute([$session_id]);
}

function invalidate_all_other_sessions($pdo, $user_id, $current_session_id)
{
    $stmt = $pdo->prepare("UPDATE user_sessions SET is_active = 0 WHERE user_id = ? AND session_id != ?");
    $stmt->execute([$user_id, $current_session_id]);
}

function cleanup_inactive_sessions($pdo)
{
    $stmt = $pdo->prepare("UPDATE user_sessions SET is_active = 0 WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    $stmt->execute($stmt);
}

function clear_rate_limit($pdo, $action_key)
{
    try {
        $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE action_key = ?");
        $stmt->execute([$action_key]);
    } catch (Exception $e) {
        error_log("Rate Limit Clear Error: " . $e->getMessage());
    }
}







function check_session_timeout($timeout_minutes = 30)
{
    if (isset($_SESSION['user_id'])) {
        if (isset($_SESSION['_last_activity'])) {
            $elapsed = time() - $_SESSION['_last_activity'];
            if ($elapsed > ($timeout_minutes * 60)) {
                
                $_SESSION = [];
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
                }
                session_destroy();
                session_start();
                $_SESSION['_timeout_message'] = 'Your session has expired due to inactivity. Please log in again.';
                header('Location: login.php');
                exit;
            }
        }
        $_SESSION['_last_activity'] = time();
    }
}






function redirect($url)
{
    header("Location: " . $url);
    exit;
}

function set_toast($message, $type = 'info')
{
    $_SESSION['_toast'] = ['message' => $message, 'type' => $type];
}

function render_toasts()
{
    if (empty($_SESSION['_toast'])) return '';
    $toast = $_SESSION['_toast'];
    unset($_SESSION['_toast']);
    $type = htmlspecialchars($toast['type']);
    $message = htmlspecialchars($toast['message']);
    return "<div data-flash=\"$message\" data-flash-type=\"$type\" style=\"display:none;\"></div>";
}

function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

function has_role($role)
{
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function has_privilege($privilege)
{
    if (has_role('admin')) {
        return true;
    }
    $privs = $_SESSION['privileges'] ?? [];
    return in_array($privilege, $privs, true);
}


function is_password_strong($password)
{
    if (strlen($password) < 10) return false;
    if (!preg_match('/[A-Z]/', $password)) return false;
    if (!preg_match('/[a-z]/', $password)) return false;
    if (!preg_match('/[0-9]/', $password)) return false;
    if (!preg_match('/[^A-Za-z0-9]/', $password)) return false;
    return true;
}


function ip_in_range($ip, $range)
{
    if (strpos($range, '/') === false) {
        $range .= '/32';
    }
    list($range, $netmask) = explode('/', $range, 2);
    $range_dec = ip2long($range);
    $ip_dec = ip2long($ip);
    if ($range_dec === false || $ip_dec === false) return false;
    $wildcard_dec = pow(2, (32 - $netmask)) - 1;
    $netmask_dec = ~$wildcard_dec;
    return (($ip_dec & $netmask_dec) == ($range_dec & $netmask_dec));
}


function is_ip_whitelisted()
{
    $whitelist = [
        '127.0.0.1',
        '::1',
        '10.61.1.123',
        '10.61.0.0/20', 
    ];

    
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }

    
    if (in_array($ip, $whitelist)) return true;

    
    foreach ($whitelist as $range) {
        if (strpos($range, '.') !== false && ip_in_range($ip, $range)) {
            return true;
        }
    }

    return false;
}

function check_remember_me($pdo)
{
    if (isset($_COOKIE['remember_me'])) {
        $parts = explode(':', $_COOKIE['remember_me']);
        if (count($parts) !== 2) {
            
            setcookie('remember_me', '', time() - 3600, "/", "", false, true);
            return false;
        }

        list($id, $token) = $parts;

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND status = 'active'");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        if ($user && isset($user['remember_token']) && hash_equals($user['remember_token'], hash('sha256', $token))) {
            $current_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $current_ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

            if (isset($user['last_ip']) && $user['last_ip'] !== $current_ip) {
                setcookie('remember_me', '', time() - 3600, "/", "", false, true);
                return false;
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['privileges'] = json_decode($user['privileges'] ?? '[]', true) ?: [];
            $_SESSION['_last_activity'] = time();

            
            $new_token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $new_token);

            $stmt = $pdo->prepare("UPDATE users SET remember_token = :token WHERE id = :id");
            $stmt->execute(['token' => $token_hash, 'id' => $user['id']]);

            setcookie('remember_me', $user['id'] . ':' . $new_token, [
                'expires' => time() + (86400 * 30),
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            return true;
        } else {
            
            setcookie('remember_me', '', time() - 3600, "/", "", false, true);
        }
    }
    return false;
}

function require_login()
{
    if (!is_logged_in()) {
        global $pdo;
        if (check_remember_me($pdo)) {
            return;
        }
        redirect('login.php');
    }
}

function require_role($role)
{
    require_login();
    if (!has_role($role) && $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        die("Access Denied: You do not have permission to view this page.");
    }

    
    if (in_array($_SESSION['role'], ['admin', 'supervisor']) && !is_ip_whitelisted()) {
        http_response_code(403);
        die("Access Denied: Administrative access is restricted to authorized office networks only.");
    }
}

function format_date($date)
{
    return date('M d, Y h:i A', strtotime($date));
}

function time_ago($datetime)
{
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    return floor($diff / 86400) . ' days ago';
}

function get_status_badge($status)
{
    switch ($status) {
        case 'open':
            return 'bg-primary';
        case 'in_progress':
            return 'bg-warning';
        case 'resolved':
            return 'bg-success';
        case 'closed':
            return 'bg-secondary';
        case 'urgent':
            return 'bg-danger';
        case 'high':
            return 'bg-orange';
        default:
            return 'bg-secondary';
    }
}

function log_action($pdo, $user_id, $action, $details)
{
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $details, $ip]);
    } catch (Exception $e) {
        error_log("Audit Log Error: " . $e->getMessage());
    }
}

function create_notification($pdo, $user_id, $type, $title, $message, $link = null)
{
    try {
        $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)")
            ->execute([$user_id, $type, $title, $message, $link]);
    } catch (Exception $e) {
        error_log("Notification Error: " . $e->getMessage());
    }
}

function log_asset_history($pdo, $asset_id, $user_id, $action, $notes)
{
    try {
        $stmt = $pdo->prepare("INSERT INTO asset_history (asset_id, user_id, action, notes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$asset_id, $user_id, $action, $notes]);
    } catch (Exception $e) {
        error_log("Asset History Error: " . $e->getMessage());
    }
}

function send_email($to, $subject, $body)
{
    if (empty($to) || empty($subject) || empty($body)) {
        return false;
    }

    $to = filter_var($to, FILTER_SANITIZE_EMAIL);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_ADDRESS . '>',
        'Reply-To: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_ADDRESS . '>',
        'X-Mailer: PHP/' . phpversion()
    ];

    $log_entry = "[" . date('Y-m-d H:i:s') . "] To: $to | Subject: $subject | Body: " . str_replace(["\r", "\n"], ' ', $body) . PHP_EOL;
    file_put_contents(__DIR__ . '/../logs/email.log', $log_entry, FILE_APPEND);

    $sent = @mail($to, $subject, $body, implode("\r\n", $headers));
    if (!$sent) {
        error_log("Mail failed to send to $to");
    }
    return $sent;
}

function get_pending_ticket_count($pdo)
{
    if (has_role('admin') || has_role('supervisor') || has_role('technician')) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE status IN ('open', 'in_progress')");
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    return 0;
}


function asset_url($path)
{
    $base_path = '/IT Management System/public/';
    $full_path = $_SERVER['DOCUMENT_ROOT'] . $base_path . ltrim($path, '/');
    $version = file_exists($full_path) ? filemtime($full_path) : time();
    return $base_path . ltrim($path, '/') . '?v=' . $version;
}


function export_csv($filename, $headers, $rows)
{
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}


function get_dashboard_stats($pdo)
{
    $cache_file = __DIR__ . '/../cache/dashboard_stats.json';
    $cache_ttl = 300;

    if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_ttl)) {
        $stats = json_decode(file_get_contents($cache_file), true);
        if ($stats !== false) {
            return $stats;
        }
    }

    $open = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open','in_progress')")->fetchColumn();
    $urgent = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE priority='urgent' AND status NOT IN ('resolved','closed')")->fetchColumn();
    $staff_active = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin' AND status = 'active'")->fetchColumn();
    $assets_total = (int)$pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();

    $prev_open = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open','in_progress') AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $trend = $prev_open > 0 ? round((($open - $prev_open) / $prev_open) * 100, 1) : 0;

    $stats = [
        'tickets_open' => $open,
        'tickets_urgent' => $urgent,
        'staff_active' => $staff_active,
        'assets_total' => $assets_total,
        'trend' => $trend,
    ];

    @file_put_contents($cache_file, json_encode($stats));

    return $stats;
}

function invalidate_dashboard_cache()
{
    $cache_file = __DIR__ . '/../cache/dashboard_stats.json';
    @unlink($cache_file);
}

function get_low_stock_consumables($pdo)
{
    $deleted_at_exists = false;
    try {
        $col_check = $pdo->query("SHOW COLUMNS FROM consumables LIKE 'deleted_at'");
        $deleted_at_exists = $col_check->rowCount() > 0;
    } catch (Exception $e) {
        $deleted_at_exists = false;
    }

    $sql = "SELECT id, item_name, category, brand, quantity, min_quantity, unit FROM consumables WHERE quantity <= min_quantity";
    if ($deleted_at_exists) {
        $sql .= " AND deleted_at IS NULL";
    }
    $sql .= " ORDER BY quantity ASC LIMIT 10";

    return $pdo->query($sql)->fetchAll();
}

function get_recent_activity($pdo, $limit = 8)
{
    $stmt = $pdo->prepare("
        SELECT a.action, a.details, a.created_at, u.full_name 
        FROM audit_logs a 
        LEFT JOIN users u ON a.user_id = u.id 
        ORDER BY a.created_at DESC 
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_sla_threshold_seconds($priority)
{
    $thresholds = [
        'urgent' => 4 * 3600,
        'high' => 24 * 3600,
        'medium' => 3 * 24 * 3600,
        'low' => 7 * 24 * 3600,
    ];

    return $thresholds[$priority] ?? (7 * 24 * 3600);
}

function get_sla_stats($pdo)
{
    $stmt = $pdo->query("SELECT id, priority, status, created_at, updated_at FROM tickets WHERE status IN ('open', 'in_progress')");
    $tickets = $stmt->fetchAll();

    $now = time();
    $breached = 0;
    $total = count($tickets);

    foreach ($tickets as $ticket) {
        $threshold = get_sla_threshold_seconds($ticket['priority']);
        $created = strtotime($ticket['created_at']);
        $age = $now - $created;

        if ($age > $threshold) {
            $breached++;
        }
    }

    return [
        'total_open' => $total,
        'sla_breached' => $breached,
        'sla_ok' => $total - $breached,
        'sla_compliance' => $total > 0 ? round((($total - $breached) / $total) * 100, 1) : 100,
    ];
}

function get_recent_tickets($pdo, $limit = 5)
{
    $stmt = $pdo->prepare("SELECT t.*, u.full_name as creator FROM tickets t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT :limit");
    $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}


function get_asset_categories($pdo)
{
    if (!defined('CATEGORIES_INITIALIZED')) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS asset_categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $count = $pdo->query("SELECT COUNT(*) FROM asset_categories")->fetchColumn();
        if ($count == 0) {
            $defaults = ["Laptop", "Desktop", "Monitor", "Printer", "Networking", "Peripheral", "Other"];
            foreach ($defaults as $d) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO asset_categories (name) VALUES (?)");
                $stmt->execute([$d]);
            }
        }
        define('CATEGORIES_INITIALIZED', true);
    }
    return $pdo->query("SELECT name FROM asset_categories ORDER BY name")->fetchAll();
}







function secure_upload($file, $target_dir, $max_size = 5242880)
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    if ($file['size'] > $max_size) {
        return false;
    }

    $target_dir = __DIR__ . '/../uploads/';
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $safe_name = bin2hex(random_bytes(16)) . ($ext ? '.' . $ext : '');
    $target_file = $target_dir . $safe_name;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return $safe_name;
    }

    return false;
}
