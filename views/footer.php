</div>
</div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarClose = document.getElementById('sidebarClose');
        const sidebarBackdrop = document.getElementById('sidebarBackdrop');
        const body = document.body;

        function toggleSidebar() {
            body.classList.toggle('sidebar-open');
        }

        if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
        if (sidebarClose) sidebarClose.addEventListener('click', toggleSidebar);
        if (sidebarBackdrop) sidebarBackdrop.addEventListener('click', toggleSidebar);

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) {
                body.classList.remove('sidebar-open');
            }
        });

        const privacyModalEl = document.getElementById('privacyNoticeModal');
        if (privacyModalEl && <?php echo !empty($_SESSION['show_privacy_notice']) ? 'true' : 'false'; ?>) {
            const privacyModal = new bootstrap.Modal(privacyModalEl);
            privacyModal.show();
        }
    });
    <?php if (!empty($_SESSION['show_privacy_notice'])) { unset($_SESSION['show_privacy_notice']); } ?>
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const notifDropdown = document.getElementById('notificationDropdown');
        const notifList = document.getElementById('notif-list');
        const notifBadge = document.getElementById('notif-badge');
        const markAllBtn = document.getElementById('markAllRead');

        if (notifDropdown && notifList) {
            const loadNotifications = () => {
                fetch('/IT Management System/public/api/notifications.php?action=fetch')
                    .then(r => r.json())
                    .then(data => {
                        if (data.error) return;
                        const list = data.notifications || [];
                        if (notifBadge) {
                            const countEl = document.getElementById('notif-count');
                            if (data.unread_count > 0) {
                                notifBadge.classList.remove('d-none');
                                if (countEl) countEl.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                            } else {
                                notifBadge.classList.add('d-none');
                                if (countEl) countEl.textContent = '';
                            }
                        }
                        if (list.length === 0) {
                            notifList.innerHTML = '<div class="p-4 text-center"><div class="text-secondary opacity-25 mb-2"><i class="fas fa-bell-slash fa-2x"></i></div><p class="text-secondary small mb-0">No new notifications</p></div>';
                            return;
                        }
                        notifList.innerHTML = list.map(n => {
                            const activeClass = n.is_read ? '' : 'bg-secondary bg-opacity-10';
                            return `<div class="dropdown-item small py-2 px-3 rounded mb-1 ${activeClass}" data-id="${n.id}" style="cursor:pointer;">
                                <div class="text-white fw-medium">${n.title}</div>
                                <div class="text-white-50 small" style="font-size:0.7rem;">${n.message}</div>
                                <div class="text-secondary mt-1" style="font-size:0.65rem;">${n.time_ago}</div>
                            </div>`;
                        }).join('');

                        notifList.querySelectorAll('[data-id]').forEach(el => {
                            el.addEventListener('click', () => {
                                const id = el.getAttribute('data-id');
                                fetch('/IT Management System/public/api/notifications.php?action=mark_read', {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/json'},
                                    body: JSON.stringify({id})
                                }).then(() => {
                                    el.classList.remove('bg-secondary', 'bg-opacity-10');
                                    if (notifBadge) {
                                        const countEl = document.getElementById('notif-count');
                                        const current = parseInt(countEl ? countEl.textContent : '0') || 0;
                                        if (current > 1) {
                                            if (countEl) countEl.textContent = current - 1;
                                        } else {
                                            notifBadge.classList.add('d-none');
                                            if (countEl) countEl.textContent = '';
                                        }
                                    }
                                });
                            });
                        });
                    });
            };

            notifDropdown.addEventListener('show.bs.dropdown', loadNotifications);

            if (markAllBtn) {
                markAllBtn.addEventListener('click', () => {
                    fetch('/IT Management System/public/api/notifications.php?action=mark_all_read', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'}
                    }).then(() => {
                        if (notifBadge) notifBadge.classList.add('d-none');
                        loadNotifications();
                    });
                });
            }
        }
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>