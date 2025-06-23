document.addEventListener('DOMContentLoaded', function () {
    const notificationsButton = document.getElementById('notifications-button');
    const notificationBadge = notificationsButton.querySelector('span');
    const notificationMenu = document.createElement('div');
    let expectedNotifications = 0;

    // Create notifications dropdown menu
    notificationMenu.className = 'hidden absolute right-0 mt-2 w-72 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50 divide-y divide-gray-100';
    notificationMenu.innerHTML = `
        <div class="px-4 py-3 text-sm text-gray-700 border-b border-gray-200">
            <p class="font-medium">Notifications</p>
        </div>
        <div class="max-h-96 overflow-y-auto" id="notifications-container">
            <div class="px-4 py-4 text-center text-gray-500">
                Loading notifications...
            </div>
        </div>
        <div class="px-4 py-2 text-center text-sm bg-gray-50">
            <a href="notifications.html" class="text-indigo-600 hover:text-indigo-900">View all</a>
        </div>
        
    `;

    notificationsButton.parentNode.appendChild(notificationMenu);



    // Track notification state
    let notifications = [];
    let unreadCount = 0;
    let isMenuOpen = false;
    let lastChecked = 0;

    // Check for new notifications every 30 seconds
    const checkInterval = setInterval(checkUnreadNotifications, 30000);


    // Initial load
    checkUnreadNotifications();
    loadNotifications();

    // Toggle menu visibility
    notificationsButton.addEventListener('click', function (e) {
        e.stopPropagation();
        isMenuOpen = !isMenuOpen;

        if (isMenuOpen) {
            notificationMenu.classList.remove('hidden');
            loadNotifications(); // Refresh when opening
            lastChecked = Date.now();
        } else {
            notificationMenu.classList.add('hidden');
        }
    });

    // Close when clicking outside
    document.addEventListener('click', function (e) {
        if (!notificationsButton.contains(e.target) && !notificationMenu.contains(e.target)) {
            notificationMenu.classList.add('hidden');
            isMenuOpen = false;
        }
    });

    // Check for unread notifications
    function checkUnreadNotifications() {
        fetch('notifications.php?action=get_unread_count')
            .then(response => response.json())
            .then(data => {
                console.log('Raw response:', data); // Debug

                if (data.success) {
                    //if (data.debug_user != UserId) {
                    //   console.error('User ID mismatch! Expected:', UserId, 'Got:', data.debug_user);
                    //}

                    unreadCount = data.count;
                    lastChecked = data.last_checked;
                    updateBadge();

                    // Force UI update if server says 0 but client expects more
                    if (unreadCount === 0 && expectedNotifications > 0) {
                        console.warn('Count mismatch - forcing refresh');
                        loadNotifications(true); // Force reload
                    }
                }
            })
            .catch(error => {
                console.error('Error checking notifications:', error);
                // Fallback to last known state
                updateBadge();
            });
    }

    // Load notifications list
    function loadNotifications() {
        const container = document.getElementById('notifications-container');
        container.innerHTML = '<div class="px-4 py-4 text-center text-gray-500">Loading notifications...</div>';

        fetch('notifications.php?action=get_notifications&limit=10')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    notifications = data.notifications;
                    renderNotifications();
                    checkUnreadNotifications(); // Update count after loading
                } else {
                    container.innerHTML = '<div class="px-4 py-4 text-center text-gray-500">Failed to load notifications</div>';
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                container.innerHTML = '<div class="px-4 py-4 text-center text-gray-500">Error loading notifications</div>';
            });
    }

    // Render notifications list
    function renderNotifications() {
        const container = document.getElementById('notifications-container');

        if (notifications.length === 0) {
            container.innerHTML = '<div class="px-4 py-4 text-center text-gray-500">No notifications</div>';
            return;
        }

        container.innerHTML = notifications.map(notification => `
            <div class="px-4 py-3 hover:bg-gray-50 ${notification.is_read ? 'bg-white' : 'bg-blue-50'}">
                <div class="flex items-start">
                    <div class="flex-shrink-0 pt-0.5">
                        <i class="fas ${getNotificationIcon(notification.type)} text-${getNotificationColor(notification.type)}"></i>
                    </div>
                    <div class="ml-3 w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900">${notification.title}</p>
                        <p class="text-sm text-gray-500">${notification.message}</p>
                        <p class="mt-1 text-xs text-gray-400">${formatTime(notification.created_at)}</p>
                    </div>
                </div>
            </div>
        `).join('');
    }

    // Update badge visibility
    function updateBadge() {
        if (unreadCount > 0) {
            notificationBadge.classList.remove('hidden');
            notificationBadge.textContent = unreadCount > 9 ? '9+' : unreadCount;
        } else {
            notificationBadge.classList.add('hidden');
        }
    }

    // Helper functions
    function getNotificationIcon(type) {
        const icons = {
            'alert': 'fa-exclamation-circle',
            'message': 'fa-envelope',
            'system': 'fa-server',
            'success': 'fa-check-circle',
            'warning': 'fa-exclamation-triangle'
        };
        return icons[type] || 'fa-bell';
    }

    function getNotificationColor(type) {
        const colors = {
            'alert': 'red-500',
            'message': 'blue-500',
            'system': 'gray-500',
            'success': 'green-500',
            'warning': 'yellow-500'
        };
        return colors[type] || 'gray-500';
    }

    function formatTime(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleString();
    }

});
