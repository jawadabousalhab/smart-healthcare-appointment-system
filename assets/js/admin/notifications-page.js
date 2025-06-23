document.addEventListener('DOMContentLoaded', function () {
    // Configuration
    const itemsPerPage = 10;
    let currentPage = 1;
    let totalNotifications = 0;
    let currentFilter = 'all';
    let currentTimeRange = 'all';

    // DOM Elements
    const container = document.getElementById("notifications-container");
    const prevBtn = $('#prev-page');
    const nextBtn = $('#next-page');
    const pageStart = $('#page-start');
    const pageEnd = $('#page-end');
    const totalItems = $('#total-items');
    const filterType = $('#filter-type');
    const filterTime = $('#filter-time');
    const markAllReadBtn = $('#mark-all-read');
    const refreshBtn = $('#refresh-notifications');

    // Modal Elements
    const modal = $('#notification-modal');
    const modalTitle = $('#modal-title');
    const modalTime = $('#modal-time');
    const modalContent = $('#modal-content');
    const modalActions = $('#modal-actions');

    // Initial load
    loadNotifications();

    // Event listeners
    prevBtn.on('click', () => {
        if (currentPage > 1) {
            currentPage--;
            loadNotifications();
        }
    });

    nextBtn.on('click', () => {
        if (currentPage * itemsPerPage < totalNotifications) {
            currentPage++;
            loadNotifications();
        }
    });

    filterType.on('change', function () {
        currentFilter = $(this).val();
        currentPage = 1;
        loadNotifications();
    });

    filterTime.on('change', function () {
        currentTimeRange = $(this).val();
        currentPage = 1;
        loadNotifications();
    });

    markAllReadBtn.on('click', markAllAsRead);
    refreshBtn.on('click', () => {
        currentPage = 1;
        loadNotifications();
    });

    $('#close-modal').on('click', () => {
        modal.addClass('hidden');
    });

    // Load notifications function
    function loadNotifications() {
        container.innerHTML = `
            <div class="p-8 text-center">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500 mb-2"></div>
                <p class="text-gray-600">Loading notifications...</p>
            </div>
        `;

        const params = new URLSearchParams({
            page: currentPage,
            per_page: itemsPerPage,
            filter: currentFilter,
            time_range: currentTimeRange
        });

        fetch(`notifications.php?action=get_notifications&${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    totalNotifications = data.total_count;
                    renderNotifications(data.notifications);
                    updatePagination();
                } else {
                    container.innerHTML = `
                        <div class="p-8 text-center text-red-500">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            ${data.message || 'Failed to load notifications'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                console.error('Error:', error);
                container.innerHTML = `
                    <div class="p-8 text-center text-red-500">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        Network error. Please try again.
                    </div>
                `;
            });
    }

    // Render notifications list
    function renderNotifications(notifications) {
        if (notifications.length === 0) {
            container.innerHTML = `
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-bell-slash text-2xl mb-2"></i>
                    <p>No notifications found</p>
                </div>
            `;
            return;
        }

        let html = '';
        notifications.forEach(notif => {
            const isUnread = notif.is_read === 0;
            const icon = getNotificationIcon(notif.type);
            const color = getNotificationColor(notif.type);
            const time = formatTime(notif.created_at);

            html += `
                <div class="notification-item ${isUnread ? 'unread' : ''} border-b border-gray-200 hover:bg-gray-50 cursor-pointer" 
                     data-id="${notif.id}" data-read="${notif.is_read}">
                    <div class="px-6 py-4" onclick="showNotificationDetail(${notif.id})">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 pt-1">
                                <i class="fas ${icon} text-${color} text-lg"></i>
                            </div>
                            <div class="ml-3 flex-1">
                                <div class="flex justify-between">
                                    <h3 class="text-sm font-medium text-gray-900">${notif.title}</h3>
                                    ${isUnread ? '<span class="notification-dot rounded-full bg-blue-500"></span>' : ''}
                                </div>
                                <p class="text-sm text-gray-500 mt-1">${notif.message.substring(0, 100)}${notif.message.length > 100 ? '...' : ''}</p>
                                <p class="text-xs text-gray-400 mt-2">${time}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    // Show notification detail modal
    window.showNotificationDetail = function (id) {
        fetch(`notifications.php?action=get_notification&id=${id}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const notif = data.notification;

                    // Update modal content
                    modalTitle.text(notif.title);
                    modalContent.text(notif.message);
                    modalTime.text(formatTime(notif.created_at, true));

                    // Clear previous actions
                    modalActions.empty();

                    // Add action buttons based on notification type
                    if (notif.type === 'appointment' && notif.related_id) {
                        modalActions.append(`
                            <a href="/appointments/view.php?id=${notif.related_id}" 
                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                                View Appointment
                            </a>
                        `);
                    } else if (notif.type === 'alert') {
                        modalActions.append(`
                            <button onclick="handleAlert(${notif.id})" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700">
                                Acknowledge Alert
                            </button>
                        `);
                    }

                    // Mark as read if unread
                    if (notif.is_read === 0) {
                        markAsRead(id);
                    }

                    // Show modal
                    modal.removeClass('hidden');
                } else {
                    console.error('Failed to load notification:', data.message);
                    alert('Failed to load notification details');
                }
            })
            .catch(error => {
                console.error('Error fetching notification:', error);
                alert('Error loading notification details');
            });
    };

    // Mark notification as read
    function markAsRead(id) {
        fetch('notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'mark_read',
                id: id
            })
        }).then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        }).then(data => {
            if (data.success) {
                $(`.notification-item[data-id="${id}"]`).removeClass('unread');
                $(`.notification-item[data-id="${id}"] .notification-dot`).remove();
            }
        }).catch(error => {
            console.error('Error marking as read:', error);
        });
    }

    // Mark all notifications as read
    function markAllAsRead() {
        fetch('notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'mark_all_read'
            })
        }).then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        }).then(data => {
            if (data.success) {
                $('.notification-item').removeClass('unread');
                $('.notification-dot').remove();
            }
        }).catch(error => {
            console.error('Error marking all as read:', error);
        });
    }

    // Update pagination controls
    function updatePagination() {
        const start = (currentPage - 1) * itemsPerPage + 1;
        const end = Math.min(currentPage * itemsPerPage, totalNotifications);

        pageStart.text(start);
        pageEnd.text(end);
        totalItems.text(totalNotifications);

        prevBtn.prop('disabled', currentPage === 1);
        nextBtn.prop('disabled', currentPage * itemsPerPage >= totalNotifications);
    }

    // Helper functions
    function getNotificationIcon(type) {
        const icons = {
            'alert': 'fa-exclamation-circle',
            'message': 'fa-envelope',
            'system': 'fa-server',
            'appointment': 'fa-calendar-check',
            'payment': 'fa-credit-card',
            'patient': 'fa-user'
        };
        return icons[type] || 'fa-bell';
    }

    function getNotificationColor(type) {
        const colors = {
            'alert': 'red-500',
            'message': 'blue-500',
            'system': 'gray-500',
            'appointment': 'green-500',
            'payment': 'purple-500',
            'patient': 'indigo-500'
        };
        return colors[type] || 'gray-500';
    }

    function formatTime(timestamp, full = false) {
        const date = new Date(timestamp);
        if (full) {
            return date.toLocaleString();
        }

        const now = new Date();
        const diff = now - date;
        const diffMinutes = Math.floor(diff / (1000 * 60));
        const diffHours = Math.floor(diff / (1000 * 60 * 60));
        const diffDays = Math.floor(diff / (1000 * 60 * 60 * 24));

        if (diffMinutes < 1) return 'Just now';
        if (diffMinutes < 60) return `${diffMinutes} min ago`;
        if (diffHours < 24) return `${diffHours} hour${diffHours !== 1 ? 's' : ''} ago`;
        if (diffDays < 7) return `${diffDays} day${diffDays !== 1 ? 's' : ''} ago`;

        return date.toLocaleDateString();
    }
});