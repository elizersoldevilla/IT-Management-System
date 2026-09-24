<?php
require_once __DIR__ . '/../views/header.php';
require_login();
?>

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
    <div>
        <h6 class="text-secondary text-uppercase mb-1 small fw-bold" style="letter-spacing: 0.1em;">Design System</h6>
        <h1 class="h2 mb-0 fw-bold text-white">Modern UI Components</h1>
        <p class="text-white-50 small mb-0">Glassmorphism, toasts, skeletons, and modern table styles</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="glass-card stats-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <div class="stat-icon text-primary bg-primary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill">Active</span>
                </div>
                <h3 class="fw-bold text-white mb-1">Glass Card</h3>
                <p class="text-white-50 small mb-0">Backdrop blur, subtle border, and hover lift effect</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-card stats-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <div class="stat-icon text-warning bg-warning bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="fas fa-bell"></i>
                    </div>
                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 rounded-pill">New</span>
                </div>
                <h3 class="fw-bold text-white mb-1">Toast Notifications</h3>
                <p class="text-white-50 small mb-0">Slide-in toasts with auto-dismiss and icons</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-card stats-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <div class="stat-icon text-info bg-info bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="fas fa-spinner"></i>
                    </div>
                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 rounded-pill">Loading</span>
                </div>
                <h3 class="fw-bold text-white mb-1">Skeleton Loaders</h3>
                <p class="text-white-50 small mb-0">Shimmer effect while data loads</p>
            </div>
        </div>
    </div>
</div>

<div class="card border-secondary border-opacity-10 bg-dark mb-4">
    <div class="card-header bg-darker border-secondary border-opacity-10 py-3">
        <h5 class="mb-0 text-white fw-bold"><i class="fas fa-mouse-pointer me-2 text-primary"></i>Toast Demo</h5>
    </div>
    <div class="card-body">
        <p class="text-white-50 small mb-3">Click the buttons below to see toast notifications in action.</p>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-success btn-sm" onclick="showToast('Operation completed successfully!', 'success')">
                <i class="fas fa-check me-2"></i>Success Toast
            </button>
            <button class="btn btn-danger btn-sm" onclick="showToast('Something went wrong. Please try again.', 'error')">
                <i class="fas fa-times me-2"></i>Error Toast
            </button>
            <button class="btn btn-warning btn-sm" onclick="showToast('Warning: Low stock detected.', 'warning')">
                <i class="fas fa-exclamation-triangle me-2"></i>Warning Toast
            </button>
            <button class="btn btn-info btn-sm" onclick="showToast('Heads up: New update available.', 'info')">
                <i class="fas fa-info-circle me-2"></i>Info Toast
            </button>
        </div>
    </div>
</div>

<div class="card border-secondary border-opacity-10 bg-dark mb-4">
    <div class="card-header bg-darker border-secondary border-opacity-10 py-3">
        <h5 class="mb-0 text-white fw-bold"><i class="fas fa-table me-2 text-primary"></i>Modern Table</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-0">
                <thead class="bg-darker">
                    <tr>
                        <th class="ps-4 py-3 text-white-50 small fw-bold">User</th>
                        <th class="py-3 text-white-50 small fw-bold">Role</th>
                        <th class="py-3 text-white-50 small fw-bold">Status</th>
                        <th class="py-3 text-white-50 small fw-bold">Last Active</th>
                        <th class="pe-4 py-3 text-end text-white-50 small fw-bold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="ps-4 py-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle bg-primary bg-opacity-10 text-primary">JD</div>
                                <div>
                                    <div class="text-white fw-medium">Juan Dela Cruz</div>
                                    <div class="text-white-50 small">juan@example.com</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3"><span class="badge bg-secondary bg-opacity-25 border border-secondary border-opacity-25 text-white-50">Admin</span></td>
                        <td class="py-3"><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Active</span></td>
                        <td class="py-3 text-white-50 small">2026-08-03 14:32</td>
                        <td class="pe-4 py-3 text-end action-btns">
                            <button class="btn btn-sm btn-icon btn-ghost-primary"><i class="fas fa-edit"></i></button>
                        </td>
                    </tr>
                    <tr>
                        <td class="ps-4 py-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle bg-success bg-opacity-10 text-success">MS</div>
                                <div>
                                    <div class="text-white fw-medium">Maria Santos</div>
                                    <div class="text-white-50 small">maria@example.com</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3"><span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">Technician</span></td>
                        <td class="py-3"><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Active</span></td>
                        <td class="py-3 text-white-50 small">2026-08-03 13:15</td>
                        <td class="pe-4 py-3 text-end action-btns">
                            <button class="btn btn-sm btn-icon btn-ghost-primary"><i class="fas fa-edit"></i></button>
                        </td>
                    </tr>
                    <tr>
                        <td class="ps-4 py-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle bg-warning bg-opacity-10 text-warning">RP</div>
                                <div>
                                    <div class="text-white fw-medium">Reyes, Pedro</div>
                                    <div class="text-white-50 small">pedro@example.com</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3"><span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Staff</span></td>
                        <td class="py-3"><span class="badge bg-secondary bg-opacity-25 border border-secondary border-opacity-25 text-white-50">Inactive</span></td>
                        <td class="py-3 text-white-50 small">2026-07-28 09:45</td>
                        <td class="pe-4 py-3 text-end action-btns">
                            <button class="btn btn-sm btn-icon btn-ghost-primary"><i class="fas fa-edit"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="glass-card border-secondary border-opacity-10 bg-dark h-100">
            <div class="card-header bg-darker border-secondary border-opacity-10 py-3">
                <h5 class="mb-0 text-white fw-bold"><i class="fas fa-circle-notch me-2 text-info"></i>Skeleton Loader</h5>
            </div>
            <div class="card-body">
                <div class="skeleton skeleton-heading"></div>
                <div class="skeleton skeleton-text w-75"></div>
                <div class="skeleton skeleton-text"></div>
                <div class="skeleton skeleton-text w-50"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="glass-card border-secondary border-opacity-10 bg-dark h-100">
            <div class="card-header bg-darker border-secondary border-opacity-10 py-3">
                <h5 class="mb-0 text-white fw-bold"><i class="fas fa-palette me-2 text-primary"></i>Design Tokens</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge bg-primary">Primary</span>
                    <span class="badge bg-success">Success</span>
                    <span class="badge bg-warning text-dark">Warning</span>
                    <span class="badge bg-danger">Danger</span>
                    <span class="badge bg-info">Info</span>
                    <span class="badge bg-secondary">Secondary</span>
                </div>
                <p class="text-white-50 small mb-0">Consistent colors, radii, and transitions are defined in <code>assets/css/modern-ui.css</code>.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
