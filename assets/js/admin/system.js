let currentLogsPage = 1;
document.addEventListener('DOMContentLoaded', function () {
    // Initialize variables

    const logsPerPage = 10;




    // Event listeners
    document.getElementById('clearLogsBtn').addEventListener('click', openClearLogsModal);
    document.getElementById('systemSettingsForm').addEventListener('submit', saveSystemSettings);
    document.getElementById('clearLogsModal').addEventListener('click', confirmClearLogs);
    document.getElementById('prevPageBtn').addEventListener('click', goToPrevPage);
    document.getElementById('nextPageBtn').addEventListener('click', goToNextPage);

    // Auto-refresh system status every 5 minutes
    setInterval(loadSystemStatus, 300000);
    // Load initial data
    loadSystemStatus();
    loadSystemLogs(currentLogsPage, logsPerPage);
});


// Load system status
async function loadSystemStatus() {
    try {
        const response = await fetch('system.php?action=get_system_status');
        const data = await response.json();
        if (data.success) {
            updateStatusIndicator('dbStatus', data.data.database);
            updateStatusIndicator('diskStatus', data.data.disk_space);
            document.getElementById('diskUsage').textContent = `Disk Space (${data.data.disk_usage}% used)`;

            const maintenanceStatus = data.data.settings.maintenance_mode === 'enabled' ? 'Enabled' : 'Disabled';
            updateStatusIndicator('maintenanceStatus', maintenanceStatus.toLowerCase());

            // Update settings form
            const form = document.getElementById('systemSettingsForm');
            form.maintenance_mode.value = data.data.settings.maintenance_mode || 'disabled';
            form.backup_schedule.value = data.data.settings.backup_schedule || '';
        }
    } catch (error) {
        console.error('Error loading system status:', error);
        showMessage('Failed to load system status', 'error');
    }
}

// Update status indicator UI
function updateStatusIndicator(elementId, status) {
    const element = document.getElementById(elementId);
    if (!element) return;

    // Clear existing classes
    element.className = 'text-2xl font-bold mb-2';

    // Set status-specific classes and text
    if (status === 'healthy' || status === 'disabled') {
        element.classList.add('text-green-600');
        element.textContent = status === 'healthy' ? 'Healthy' : 'Disabled';
    } else if (status === 'warning') {
        element.classList.add('text-yellow-600');
        element.textContent = 'Warning';
    } else if (status === 'critical' || status === 'enabled') {
        element.classList.add('text-red-600');
        element.textContent = status === 'critical' ? 'Critical' : 'Enabled';
    } else {
        element.textContent = status;
    }
}



// Load system logs
async function loadSystemLogs(page, perPage) {
    try {
        const logsTable = document.getElementById('logsTable');
        if (!logsTable) {
            console.error('logsTable element not found.');
            return;
        }
        const response = await fetch(`system.php?action=get_system_logs&page=${page}&per_page=${perPage}`);
        const data = await response.json();
        if (data.success) {

            logsTable.innerHTML = data.data.map(log => `
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${log.action}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">${log.description}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${new Date(log.created_at).toLocaleString()}</td>
                </tr>
            `).join('');

            // Update pagination info
            const pagination = data.pagination;
            document.getElementById('logsPaginationInfo').textContent =
                `Showing ${((pagination.current_page - 1) * pagination.per_page) + 1} to 
                ${Math.min(pagination.current_page * pagination.per_page, pagination.total)} 
                of ${pagination.total} entries`;

            // Enable/disable pagination buttons
            document.getElementById('prevPageBtn').disabled = pagination.current_page === 1;
            document.getElementById('nextPageBtn').disabled = pagination.current_page === pagination.last_page;
        }
    } catch (error) {
        console.error('Error loading system logs:', error);
        showMessage('Failed to load system logs', 'error');
    }
}

// Open clear logs modal
function openClearLogsModal() {
    document.getElementById('clearLogsModal').classList.remove('hidden');
}

function closeClearLogsModal() {
    document.getElementById('clearLogsModal').classList.add('hidden');
}

// Confirm clear logs
async function confirmClearLogs() {
    const btn = document.getElementById('clearLogsBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Clearing...';

    try {
        const response = await fetch('system.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=clear_logs'
        });
        const data = await response.json();
        if (data.success) {
            showMessage(data.message, 'success');
            closeClearLogsModal();
            loadSystemLogs(1, 10); // Reload first page
        } else {
            showMessage(data.message || 'Failed to clear logs', 'error');
        }
    } catch (error) {
        console.error('Error clearing logs:', error);
        showMessage('Failed to clear logs', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-trash mr-1"></i> Clear Logs';
    }
}

// Save system settings
async function saveSystemSettings(e) {
    e.preventDefault();
    const form = document.getElementById('systemSettingsForm');
    const formData = new FormData(form);
    formData.append('action', 'update_system_settings');

    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Saving...';

    try {
        const response = await fetch('system.php', {
            method: 'POST',
            body: new URLSearchParams(formData)
        });
        const data = await response.json();
        if (data.success) {
            showMessage(data.message, 'success');
            loadSystemStatus(); // Refresh status to show updated settings
        } else {
            showMessage(data.message || 'Failed to save settings', 'error');
        }
    } catch (error) {
        console.error('Error saving settings:', error);
        showMessage('Failed to save settings', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save Settings';
    }
}

// Pagination functions
function goToPrevPage() {
    if (currentLogsPage > 1) {
        currentLogsPage--;
        loadSystemLogs(currentLogsPage, 10);
    }
}

function goToNextPage() {
    currentLogsPage++;
    loadSystemLogs(currentLogsPage, 10);
}

// Show message notification
function showMessage(message, type) {
    const container = document.getElementById('messageContainer');
    container.innerHTML = `
        <div class="${type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'} px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">${message}</span>
        </div>
    `;
    container.classList.remove('hidden');

    // Hide message after 5 seconds
    setTimeout(() => {
        container.classList.add('hidden');
    }, 5000);
}

