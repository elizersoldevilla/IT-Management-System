<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
if (!has_role('admin')) {
    die('Access Denied. Admin only.');
}

try {
    
    $sql = "SHOW COLUMNS FROM users LIKE 'remember_token'";
    $stmt = $pdo->query($sql);
    
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN remember_token VARCHAR(255) DEFAULT NULL");
        echo "Successfully added 'remember_token' column to 'users' table.\n";
    } else {
        echo "'remember_token' column already exists.\n";
    }

    $sql = "SHOW COLUMNS FROM users LIKE 'privileges'";
    $stmt = $pdo->query($sql);
    
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN privileges JSON DEFAULT NULL");
        echo "Successfully added 'privileges' column to 'users' table.\n";
    } else {
        echo "'privileges' column already exists.\n";
    }


    
    $tracking_cols = [
        'last_password_change' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        'last_ip' => "VARCHAR(45) DEFAULT NULL",
        'last_user_agent' => "VARCHAR(255) DEFAULT NULL",
        'contact_no' => "TEXT DEFAULT NULL",
        'address' => "TEXT DEFAULT NULL"
    ];

    foreach ($tracking_cols as $col => $definition) {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE '$col'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN $col $definition");
            echo "Successfully added '$col' column.\n";
        }
    }

    
    $pdo->exec("CREATE TABLE IF NOT EXISTS consumables (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(255) NOT NULL,
        category ENUM('Ink', 'Cartridge', 'Toner', 'Drum') NOT NULL,
        brand VARCHAR(100),
        model_compatibility TEXT,
        quantity INT DEFAULT 0,
        min_quantity INT DEFAULT 5,
        unit VARCHAR(50) DEFAULT 'Piece',
        location VARCHAR(100),
        remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $pdo->exec("ALTER TABLE consumables MODIFY COLUMN category ENUM('Ink', 'Cartridge', 'Toner', 'Drum') NOT NULL");
    echo "Consumables table is ready.\n";

    
    $pdo->exec("CREATE TABLE IF NOT EXISTS consumable_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        consumable_id INT NOT NULL,
        user_id INT NOT NULL,
        action_type ENUM('ADD', 'DEDUCT') NOT NULL,
        quantity INT NOT NULL,
        department VARCHAR(100),
        remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (consumable_id) REFERENCES consumables(id) ON DELETE CASCADE
    )");
    echo "Consumable logs table is ready.\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        link VARCHAR(255) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "Notifications table is ready.\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        action VARCHAR(50) NOT NULL,
        details TEXT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY user_id (user_id),
        KEY created_at (created_at)
    )");
    echo "Audit logs table is ready.\n";

    $pdo->exec("ALTER TABLE assets ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
    echo "Assets deleted_at column ready.\n";

    $pdo->exec("ALTER TABLE consumables ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
    echo "Consumables deleted_at column ready.\n";

    $pdo->exec("ALTER TABLE users ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
    echo "Users deleted_at column ready.\n";

    $pdo->exec("ALTER TABLE assets ADD COLUMN accountability_due_date DATE NULL DEFAULT NULL");
    echo "Assets accountability_due_date column ready.\n";

    $pdo->exec("ALTER TABLE assets ADD COLUMN accountability_signed_by INT NULL DEFAULT NULL");
    echo "Assets accountability_signed_by column ready.\n";

    $pdo->exec("ALTER TABLE assets ADD COLUMN accountability_signed_at TIMESTAMP NULL DEFAULT NULL");
    echo "Assets accountability_signed_at column ready.\n";

    $deleted = $pdo->exec("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)");
    echo "Audit log retention: purged $deleted logs older than 1 year.\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS system_resources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        category ENUM('Driver','Tool','Firmware','Utility') NOT NULL,
        version VARCHAR(50) DEFAULT NULL,
        file_path VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        related_asset_id INT DEFAULT NULL,
        uploaded_by INT DEFAULT NULL,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_category (category),
        KEY idx_deleted_at (deleted_at),
        KEY idx_uploaded_by (uploaded_by),
        CONSTRAINT fk_resource_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_resource_asset FOREIGN KEY (related_asset_id) REFERENCES assets(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "System resources table is ready.\n";

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage() . "\n");
}
?>
