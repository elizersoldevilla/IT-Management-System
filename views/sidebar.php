<aside class="sidebar d-flex flex-column flex-shrink-0">
    <div class="sidebar-header justify-content-between">
        <a href="dashboard.php" class="d-flex align-items-center text-white text-decoration-none">
            <div class="bg-primary rounded-3 d-flex align-items-center justify-content-center me-3"
                style="width: 36px; height: 36px; box-shadow: 0 0 15px rgba(59, 130, 246, 0.5);">
                <i class="fas fa-network-wired fa-sm text-white"></i>
            </div>
            <span class="fs-5 fw-bold tracking-tight">IT System</span>
        </a>
        <button class="btn btn-icon btn-sm btn-ghost-secondary d-md-none text-white opacity-50" id="sidebarClose">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="flex-grow-1 overflow-auto custom-scrollbar">
        <ul class="nav flex-column p-3 gap-1">
            <li class="nav-item">
                <a href="dashboard.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt w-6 text-center me-2 opacity-75"></i>
                    Dashboard
                </a>
            </li>

            <?php if (has_privilege('view_tickets') || has_privilege('manage_assets') || has_privilege('manage_consumables') || has_role('admin') || has_role('supervisor') || has_role('technician')): ?>
                <li class="nav-header text-uppercase small fw-bold text-light opacity-50 mt-3 mb-2 px-3"
                    style="font-size: 0.7rem; letter-spacing: 0.05em;">Operations</li>
                
                <?php if (has_privilege('view_tickets')): ?>
                <li class="nav-item">
                    <a href="tickets.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'tickets.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-ticket-alt w-6 text-center me-2"></i>
                        Tickets
                        <?php
                        $pending_count = get_pending_ticket_count($pdo);
                        if ($pending_count > 0):
                            ?>
                            <span class="badge bg-danger rounded-pill float-end ms-2"
                                style="font-size: 0.7em;"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (has_privilege('manage_assets')): ?>
                <li class="nav-item">
                    <a href="inventory.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-boxes w-6 text-center me-2"></i>
                        Inventory
                    </a>
                </li>
                <li class="nav-item">
                    <a href="vendors.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'vendors.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-building w-6 text-center me-2"></i>
                        Vendors
                    </a>
                </li>
                <?php endif; ?>

                <?php if (has_privilege('manage_assets') || has_privilege('view_tickets')): ?>
                <li class="nav-item">
                    <a href="tools_drivers.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'tools_drivers.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-microchip w-6 text-center me-2"></i>
                        Tools & Drivers
                    </a>
                </li>
                <?php endif; ?>

                <?php if (has_privilege('manage_consumables')): ?>
                <li class="nav-item">
                    <a href="consumables.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'consumables.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-fill-drip w-6 text-center me-2"></i>
                        Consumables
                    </a>
                </li>
                <?php endif; ?>

                <?php if (has_role('admin') || has_role('supervisor') || has_role('technician')): ?>
                <li class="nav-item">
                    <a href="chat.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-comments w-6 text-center me-2"></i>
                        Team Chat
                    </a>
                </li>
                <li class="nav-item">
                    <a href="calendar.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-calendar-alt w-6 text-center me-2"></i>
                        Calendar
                    </a>
                </li>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (has_role('admin') || has_role('supervisor') || has_privilege('view_reports')): ?>
                <li class="nav-header text-uppercase small fw-bold text-light opacity-50 mt-3 mb-2 px-3"
                    style="font-size: 0.7rem; letter-spacing: 0.05em;">Management</li>
                
                <?php if (has_role('admin') || has_role('supervisor')): ?>
                <li class="nav-item">
                    <a href="staff.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'staff.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-users-cog w-6 text-center me-2"></i>
                        Staff
                    </a>
                </li>
                <?php if (has_role('admin')): ?>
                <li class="nav-item">
                    <a href="backup.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'backup.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-database w-6 text-center me-2"></i>
                        Backup
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a href="inventory_categories.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'inventory_categories.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-tags w-6 text-center me-2"></i>
                        Asset Categories
                    </a>
                </li>
                <li class="nav-item">
                    <a href="accountability_tally.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'accountability_tally.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-clipboard-list w-6 text-center me-2"></i>
                        Accountability Tally
                    </a>
                </li>
                <li class="nav-item">
                    <a href="audit_logs.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'audit_logs.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-shield-alt w-6 text-center me-2"></i>
                        Audit Logs
                    </a>
                </li>
                <?php endif; ?>

                <?php if (has_privilege('view_reports')): ?>
                <li class="nav-item">
                    <a href="reports.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-chart-line w-6 text-center me-2"></i>
                        Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a href="ticket_aging.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'ticket_aging.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-hourglass-half w-6 text-center me-2"></i>
                        Ticket Aging
                    </a>
                </li>
                <li class="nav-item">
                    <a href="analytics.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-chart-pie w-6 text-center me-2"></i>
                        Analytics
                    </a>
                </li>
                <li class="nav-header text-uppercase small fw-bold text-light opacity-50 mt-2 mb-2 px-3"
                    style="font-size: 0.65rem; letter-spacing: 0.05em;">Consumables</li>
                <li class="nav-item">
                    <a href="consumables_report.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'consumables_report.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-file-invoice-dollar w-6 text-center me-2"></i>
                        Usage Report
                    </a>
                </li>
                <li class="nav-item">
                    <a href="consumables_analytics.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'consumables_analytics.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-microchip w-6 text-center me-2"></i>
                        Usage Trends
                    </a>
                </li>
                <li class="nav-item">
                    <a href="consumables_forecast.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'consumables_forecast.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-chart-line w-6 text-center me-2"></i>
                        Demand Forecast
                    </a>
                </li>
                <?php endif; ?>

                <?php if (has_role('admin') || has_role('supervisor')): ?>
                <li class="nav-item">
                    <a href="users.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-users w-6 text-center me-2"></i>
                        Users
                    </a>
                </li>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (has_role('staff')): ?>
                <li class="nav-header text-uppercase small fw-bold text-light opacity-50 mt-3 mb-2 px-3"
                    style="font-size: 0.7rem; letter-spacing: 0.05em;">Personal</li>
                <li class="nav-item">
                    <a href="my_tickets.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'my_tickets.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-ticket-alt w-6 text-center me-2"></i>
                        My Tickets
                    </a>
                </li>
                <li class="nav-item">
                    <a href="kb.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'kb.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-book w-6 text-center me-2"></i>
                        Knowledge Base
                    </a>
                </li>
                <li class="nav-item">
                    <a href="sessions.php"
                        class="nav-link text-white opacity-75 hover-opacity-100 <?php echo basename($_SERVER['PHP_SELF']) == 'sessions.php' ? 'active opacity-100' : ''; ?>">
                        <i class="fas fa-desktop w-6 text-center me-2"></i>
                        Sessions
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="p-3 border-top border-secondary border-opacity-10 mt-auto">
        <div class="dropdown">
            <a href="#"
                class="d-flex align-items-center text-white text-decoration-none dropdown-toggle px-2 py-1 rounded hover-bg-light-10"
                id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="avatar-circle bg-gradient-to-br from-blue-500 to-indigo-600 rounded-circle d-flex align-items-center justify-content-center text-white fw-bold me-2"
                    style="width: 32px; height: 32px; background: var(--bs-primary);">
                    <?php echo substr($_SESSION['full_name'] ?? 'U', 0, 1); ?>
                </div>
                <div class="d-flex flex-column" style="line-height: 1.2;">
                    <strong
                        class="small text-white"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></strong>
                    <span class="text-white-50 small"
                        style="font-size: 0.7rem;"><?php echo ucfirst($_SESSION['role'] ?? 'User'); ?></span>
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark text-small shadow border-secondary border-opacity-25"
                aria-labelledby="dropdownUser1">
                <li><a class="dropdown-item small" href="profile.php"><i class="fas fa-user me-2 opacity-50"></i>
                        Profile</a></li>
                <li>
                    <hr class="dropdown-divider border-secondary border-opacity-25">
                </li>
                <li><a class="dropdown-item small text-danger" href="logout.php"><i
                            class="fas fa-sign-out-alt me-2 opacity-50"></i> Sign out</a></li>
            </ul>
        </div>
    </div>
</aside>