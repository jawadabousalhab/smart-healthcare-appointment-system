let currentLogsPage = 1;
document.addEventListener('DOMContentLoaded', function () {
    // Initialize DataTables
    const backupsTable = $('#backupsTable').DataTable({
        responsive: true,
        order: [[2, 'desc']],
        columns: [
            { data: 'filename' },
            {
                data: 'size',
                render: function (data) {
                    return formatFileSize(data);
                }
            },
            {
                data: 'created_at',
                render: function (data) {
                    return new Date(data).toLocaleString();
                }
            },
            {
                data: 'backup_id',
                render: function (data, type, row) {
                    return `
                        <button onclick="confirmRestore(${data})" class="action-btn text-blue-600 hover:text-blue-900 mr-2">
                            <i class="fas fa-undo"></i> Restore
                        </button>
                        <button onclick="downloadBackup('${row.filename}')" class="action-btn text-green-600 hover:text-green-900">
                            <i class="fas fa-download"></i> Download
                        </button>
                    `;
                }
            }
        ]
    });
    loadBackups();
    document.getElementById('createBackupBtn').addEventListener('click', createBackup);
    document.getElementById('restoreForm').addEventListener('submit', restoreBackup);
    document.getElementById('prevPageBtn').addEventListener('click', goToPrevPage);
    document.getElementById('nextPageBtn').addEventListener('click', goToNextPage);

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    async function loadBackups() {
        try {
            const response = await fetch('index.php?action=get_backups');
            const data = await response.json();
            if (data.success) {
                const table = $('#backupsTable').DataTable();
                table.clear();
                table.rows.add(data.data).draw();
            }
        } catch (error) {
            console.error('Error loading backups:', error);
            showMessage('Failed to load backups', 'error');
        }
    }
    async function createBackup() {
        const btn = document.getElementById('createBackupBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Creating...';

        try {
            const response = await fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=create_backup',
            });
            const responseText = await response.text();
            console.log(responseText);
            try {
                const data = JSON.parse(responseText);
                console.log(data);
                btn.innerHTML = '<i class="fas fa-plus-circle mr-1"></i> Create Backup';
                if (data.success) {
                    loadBackups();
                    showMessage(data.message || 'Backup created successfully!', 'success');
                } else {
                    showMessage(data.message || 'Backup creation failed', 'error');
                }
            } catch (err) {
                console.error('Failed to parse JSON:', err);
                showMessage('Failed to parse response from server. Please try again.', 'error');
            }
        } catch (error) {
            console.error('Error creating backup:', error);
            showMessage('Failed to create backup. Please check your connection or try again later.', 'error');
        }
    }

    // Confirm restore backup
    function confirmRestore(backupId) {
        document.getElementById('restoreBackupId').value = backupId;
        document.getElementById('restoreModal').classList.remove('hidden');
    }

    function closeRestoreModal() {
        document.getElementById('restoreModal').classList.add('hidden');
    }

    // Restore backup
    async function restoreBackup(e) {
        e.preventDefault();
        const form = document.getElementById('restoreForm');
        const formData = new FormData(form);
        formData.append('action', 'restore_backup');

        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Restoring...';

        try {
            const response = await fetch('index.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            });
            const data = await response.json();
            if (data.success) {
                showMessage(data.message, 'success');
                closeRestoreModal();
                loadSystemStatus();
            } else {
                showMessage(data.message || 'Restore failed', 'error');
            }
        } catch (error) {
            console.error('Error restoring backup:', error);
            showMessage('Failed to restore backup', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Restore';
        }
    }

    // Download backup
    function downloadBackup(filename) {
        const url = `index.php?action=download_backup&filename=${encodeURIComponent(filename)}`;
        const downloadWindow = window.open(url, '_blank');
        if (!downloadWindow) {
            showMessage('Failed to open the download window. Please check for pop-up blockers.', 'error');
        }
    }
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
});