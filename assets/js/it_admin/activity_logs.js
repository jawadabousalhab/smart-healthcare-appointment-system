document.addEventListener('DOMContentLoaded', function () {
    // DOM Elements
    const logsTable = document.getElementById('logs-table');
    const logSearch = document.getElementById('log-search');
    const searchBtn = document.getElementById('search-btn');
    const logTypeFilter = document.getElementById('log-type');
    const logUserFilter = document.getElementById('log-user');
    const prevPageBtn = document.getElementById('prev-page');
    const nextPageBtn = document.getElementById('next-page');
    const pageInfo = document.getElementById('page-info');

    // State variables
    let currentPage = 1;
    let totalPages = 1;
    let currentSearch = '';
    let currentType = '';
    let currentUser = '';

    // Initialize
    loadUsersForFilter();
    loadActivityLogs();

    // Event listeners
    searchBtn.addEventListener('click', () => {
        currentSearch = logSearch.value;
        currentPage = 1;
        loadActivityLogs();
    });

    logSearch.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            currentSearch = logSearch.value;
            currentPage = 1;
            loadActivityLogs();
        }
    });

    logTypeFilter.addEventListener('change', () => {
        currentType = logTypeFilter.value;
        currentPage = 1;
        loadActivityLogs();
    });

    logUserFilter.addEventListener('change', () => {
        currentUser = logUserFilter.value;
        currentPage = 1;
        loadActivityLogs();
    });

    prevPageBtn.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            loadActivityLogs();
        }
    });

    nextPageBtn.addEventListener('click', () => {
        if (currentPage < totalPages) {
            currentPage++;
            loadActivityLogs();
        }
    });

    // Functions
    function loadUsersForFilter() {
        fetch('activity_logs.php?action=get_users')
            .then(response => response.json())
            .then(users => {
                logUserFilter.innerHTML = '<option value="">All Users</option>';
                users.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.user_id;
                    option.textContent = `${user.name} (${user.role})`;
                    logUserFilter.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error loading users:', error);
            });
    }

    function loadActivityLogs() {
        const tbody = logsTable.querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="6" class="loading">Loading activity logs...</td></tr>';

        let url = `activity_logs.php?action=get_logs&page=${currentPage}`;

        if (currentSearch) {
            url += `&search=${encodeURIComponent(currentSearch)}`;
        }

        if (currentType) {
            url += `&type=${currentType}`;
        }

        if (currentUser) {
            url += `&user_id=${currentUser}`;
        }

        fetch(url)
            .then(response => response.json())
            .then(data => {
                totalPages = data.totalPages;
                updatePagination();

                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="no-data">No activity logs found</td></tr>';
                    return;
                }

                tbody.innerHTML = '';
                data.data.forEach(log => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${log.log_id}</td>
                        <td><span class="action-badge ${getActionClass(log.action)}">${formatAction(log.action)}</span></td>
                        <td>${log.description}</td>
                        <td>${log.user_name} (${log.user_role})</td>
                        <td>${log.ip_address}</td>
                        <td>${log.created_at}</td>
                    `;
                    tbody.appendChild(row);
                });
            })
            .catch(error => {
                console.error('Error loading activity logs:', error);
                tbody.innerHTML = '<tr><td colspan="6" class="error">Failed to load activity logs. Please try again.</td></tr>';
            });
    }

    function updatePagination() {
        pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
        prevPageBtn.disabled = currentPage <= 1;
        nextPageBtn.disabled = currentPage >= totalPages;
    }

    function formatAction(action) {
        // Convert action names to more readable format
        return action.replace(/_/g, ' ')
            .replace(/\b\w/g, l => l.toUpperCase());
    }

    function getActionClass(action) {
        // Return CSS class based on action type
        if (action.startsWith('user')) return 'user-action';
        if (action.startsWith('clinic')) return 'clinic-action';
        if (action.startsWith('doctor')) return 'doctor-action';
        if (action.startsWith('backup')) return 'backup-action';
        return 'default-action';
    }
});