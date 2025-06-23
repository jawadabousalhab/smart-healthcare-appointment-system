document.addEventListener('DOMContentLoaded', function () {
    // Initialize tooltips
    if (typeof tippy !== 'undefined') {
        tippy('[data-tippy-content]', {
            arrow: true,
            animation: 'shift-away',
            duration: 200,
        });
    }

    // Mobile menu toggle with animation
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    if (mobileMenuButton && sidebar && overlay) {
        mobileMenuButton.addEventListener('click', function () {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            overlay.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        });

        overlay.addEventListener('click', function () {
            sidebar.classList.add('-translate-x-full');
            sidebar.classList.remove('translate-x-0');
            overlay.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        });
    }


    // User menu dropdown
    const userMenuButton = document.getElementById('user-menu-button');
    const userMenu = document.getElementById('user-menu');

    if (userMenuButton && userMenu) {
        userMenuButton.addEventListener('click', function (e) {
            e.stopPropagation();
            userMenu.classList.toggle('hidden');
            userMenu.classList.toggle('opacity-0');
            userMenu.classList.toggle('opacity-100');
            userMenu.classList.toggle('scale-95');
            userMenu.classList.toggle('scale-100');
        });

        // Close when clicking outside or pressing Escape
        document.addEventListener('click', function (e) {
            if (!userMenu.contains(e.target) && e.target !== userMenuButton) {
                closeUserMenu();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeUserMenu();
            }
        });

        function closeUserMenu() {
            userMenu.classList.add('hidden', 'opacity-0', 'scale-95');
            userMenu.classList.remove('opacity-100', 'scale-100');
        }
    }


    // Notifications dropdown
    const notificationsButton = document.getElementById('notifications-button');
    const notificationsMenu = document.getElementById('notifications-menu');
    const notificationBadge = document.getElementById('notification-badge');

    if (notificationsButton) {
        notificationsButton.addEventListener('click', function (e) {
            e.stopPropagation();
            if (notificationsMenu) {
                notificationsMenu.classList.toggle('hidden');
            }

            // Clear badge when opening
            if (notificationBadge && (!notificationsMenu || !notificationsMenu.classList.contains('hidden'))) {
                notificationBadge.textContent = '0';
                notificationBadge.classList.add('hidden');
            }
        });

        // Close when clicking outside
        if (notificationsMenu) {
            document.addEventListener('click', function (e) {
                if (!notificationsMenu.contains(e.target) && e.target !== notificationsButton) {
                    notificationsMenu.classList.add('hidden');
                }
            });
        }
    }

    // Load dashboard data
    function loadDashboardData() {
        fetch('admin_dashboard.php?action=get_stats')
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                updateStatsCards(data.stats);
                updateRecentAdmins(data.recent_admins);
                updateRecentITAdmins(data.recent_it_admins);
                updateRecentClinics(data.recent_clinics);
                updateActivityLog(data.recent_logs);
                updateAdminProfile(data.admin);

                if (data.unread_notifications && notificationBadge) {
                    notificationBadge.textContent = data.unread_notifications;
                    notificationBadge.classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error loading dashboard data:', error);
                showErrorToast('Failed to load dashboard data. Please try again.');
            });
    }

    // Update stats cards
    function updateStatsCards(stats) {
        // Total Clinics
        const totalClinicsElement = document.querySelector('[data-stat="total_clinics"]');
        if (totalClinicsElement) {
            totalClinicsElement.textContent = stats.total_clinics || '0';
        }

        // Active Admins
        const activeAdminsElement = document.querySelector('[data-stat="active_admins"]');
        if (activeAdminsElement) {
            activeAdminsElement.textContent = stats.active_admins || '0';
        }

        // IT Admins
        const itAdminsElement = document.querySelector('[data-stat="it_admins"]');
        if (itAdminsElement) {
            itAdminsElement.textContent = stats.it_admins || '0';
        }

        // System Health
        const systemHealthElement = document.querySelector('[data-health="database"]');
        if (systemHealthElement && stats.system_health) {
            systemHealthElement.textContent = stats.system_health.database || 'unknown';
            systemHealthElement.className = `text-xs font-semibold px-2 py-1 rounded-full capitalize ${stats.system_health.database === 'healthy' ? 'bg-green-100 text-green-800' :
                stats.system_health.database === 'warning' ? 'bg-yellow-100 text-yellow-800' :
                    'bg-red-100 text-red-800'
                }`;
        }
    }

    // Update recent admins
    function updateRecentAdmins(admins) {
        const container = document.querySelector('#recent-admins-container');
        if (!container) return;

        if (!admins || admins.length === 0) {
            container.innerHTML = '<div class="px-6 py-4 text-center text-gray-500">No recent admins found</div>';
            return;
        }

        container.innerHTML = admins.map(admin => `
            <div class="px-6 py-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-purple-100 rounded-full p-2">
                        <i class="fas fa-user-shield text-purple-500"></i>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900">${admin.name || 'Unknown'}</div>
                        <div class="text-sm text-gray-500">${admin.email || 'No email'}</div>
                        <div class="text-xs text-gray-400 mt-1">
                            Added ${formatDate(admin.created_at)}
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    // Update recent IT admins
    function updateRecentITAdmins(itAdmins) {
        const container = document.querySelector('#recent-it-admins-container');
        if (!container) return;

        if (!itAdmins || itAdmins.length === 0) {
            container.innerHTML = '<div class="px-6 py-4 text-center text-gray-500">No recent IT admins found</div>';
            return;
        }

        container.innerHTML = itAdmins.map(admin => `
            <div class="px-6 py-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-100 rounded-full p-2">
                        <i class="fas fa-users-cog text-green-500"></i>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900">${admin.name || 'Unknown'}</div>
                        <div class="text-sm text-gray-500">${admin.email || 'No email'}</div>
                        <div class="text-xs text-gray-400 mt-1">
                            Added ${formatDate(admin.created_at)}
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    // Update recent clinics
    function updateRecentClinics(clinics) {
        const container = document.querySelector('#recent-clinics-container');
        if (!container) return;

        if (!clinics || clinics.length === 0) {
            container.innerHTML = '<div class="px-6 py-4 text-center text-gray-500">No recent clinics found</div>';
            return;
        }

        container.innerHTML = clinics.map(clinic => `
            <div class="px-6 py-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-blue-100 rounded-full p-2">
                        <i class="fas fa-clinic-medical text-blue-500"></i>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900">${clinic.name || 'Unknown Clinic'}</div>
                        <div class="text-sm text-gray-500">${clinic.location || 'No location'}</div>
                        <div class="text-xs text-gray-400 mt-1">
                            ${clinic.doctor_count || '0'} doctors
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    // Update activity log
    function updateActivityLog(logs) {
        const container = document.querySelector('#activity-log tbody');
        if (!container) return;

        if (!logs || logs.length === 0) {
            container.innerHTML = `
                <tr>
                    <td colspan="4" class="px-6 py-4 text-center text-gray-500">No recent activity found</td>
                </tr>
            `;
            return;
        }

        container.innerHTML = logs.map(log => `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    ${log.action || 'Unknown action'}
                </td>
                <td class="px-6 py-4 text-sm text-gray-500">
                    ${log.description || 'No description'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    System
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${formatDateTime(log.created_at)}
                </td>
            </tr>
        `).join('');
    }

    // Update admin profile in sidebar
    function updateAdminProfile(admin) {
        const profileName = document.querySelector('.sidebar .ml-3 p.text-sm');
        const profileImage = document.querySelector('.sidebar img.rounded-full');

        if (profileName) {
            profileName.textContent = admin.name || 'Admin';
        }

        if (profileImage && admin.profile_picture) {
            profileImage.src = admin.profile_picture;
        }
    }

    // Format date for display
    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString();
    }

    // Format datetime for display
    function formatDateTime(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleString();
    }

    // Show error toast notification
    function showErrorToast(message) {
        // You can implement a proper toast notification system here
        console.error('Error:', message);
        alert(message); // Temporary fallback
    }

    // Add containers for dynamic content if they don't exist
    function ensureContainersExist() {
        // Recent Admins
        const recentAdminsSection = document.querySelector('.divide-y.divide-gray-200:first-of-type');
        if (recentAdminsSection) {
            recentAdminsSection.innerHTML = `
                <div id="recent-admins-container" class="divide-y divide-gray-200"></div>
            `;
        }

        // Recent IT Admins
        const recentITAdminsSection = document.querySelectorAll('.divide-y.divide-gray-200')[1];
        if (recentITAdminsSection) {
            recentITAdminsSection.innerHTML = `
                <div id="recent-it-admins-container" class="divide-y divide-gray-200"></div>
            `;
        }

        // Recent Clinics
        const recentClinicsSection = document.querySelectorAll('.divide-y.divide-gray-200')[2];
        if (recentClinicsSection) {
            recentClinicsSection.innerHTML = `
                <div id="recent-clinics-container" class="divide-y divide-gray-200"></div>
            `;
        }
    }

    // Initialize the dashboard
    ensureContainersExist();
    loadDashboardData();

    // Refresh data every 2 minutes
    setInterval(loadDashboardData, 120000);

    // Make refresh function available globally if needed
    window.refreshDashboard = loadDashboardData;
});