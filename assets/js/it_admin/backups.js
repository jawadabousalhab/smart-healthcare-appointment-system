document.addEventListener('DOMContentLoaded', function () {
    // DOM Elements
    const backupsTable = document.getElementById('backups-table');
    const createBackupBtn = document.getElementById('create-backup-btn');
    const backupModal = document.getElementById('backup-modal');
    const closeBtn = document.querySelector('.close-btn');
    const cancelBtn = document.getElementById('cancel-backup');
    const backupForm = document.getElementById('backup-form');
    const prevPageBtn = document.getElementById('prev-page');
    const nextPageBtn = document.getElementById('next-page');
    const pageInfo = document.getElementById('page-info');
    const backupTypeSelect = document.getElementById('backup-type');
    const backupTypeModalSelect = document.getElementById('backup-type-modal');

    // State variables
    let currentPage = 1;
    let totalPages = 1;

    // Initialize
    loadBackups();

    // Event listeners
    createBackupBtn.addEventListener('click', () => {
        backupModal.style.display = 'block';
    });

    closeBtn.addEventListener('click', () => {
        backupModal.style.display = 'none';
    });

    cancelBtn.addEventListener('click', () => {
        backupModal.style.display = 'none';
    });

    window.addEventListener('click', (e) => {
        if (e.target === backupModal) {
            backupModal.style.display = 'none';
        }
    });

    prevPageBtn.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            loadBackups();
        }
    });

    nextPageBtn.addEventListener('click', () => {
        if (currentPage < totalPages) {
            currentPage++;
            loadBackups();
        }
    });

    backupForm.addEventListener('submit', (e) => {
        e.preventDefault();
        createBackup();
    });


    backupTypeSelect.addEventListener('change', () => {
        currentPage = 1;
        loadBackups();
    });

    // Functions
    function loadBackups() {
        const tbody = backupsTable.querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="6" class="loading">Loading backups...</td></tr>';

        const backupType = backupTypeSelect.value;
        const searchTerm = document.getElementById('backup-search').value;
        let url = `backups.php?action=get_backups&page=${currentPage}`;

        if (backupType) {
            url += `&type=${backupType}`;
        }

        if (searchTerm) {
            url += `&search=${encodeURIComponent(searchTerm)}`;
        }

        fetch(url)
            .then(response => response.json())
            .then(data => {
                totalPages = data.totalPages;
                updatePagination();

                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="no-data">No backups found</td></tr>';
                    return;
                }

                tbody.innerHTML = '';
                data.data.forEach(backup => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${backup.backup_id}</td>
                        <td>${backup.filename}</td>
                        <td>${backup.type.charAt(0).toUpperCase() + backup.type.slice(1)}</td>
                        <td>${backup.size}</td>
                        <td>${new Date(backup.created_at).toLocaleString()}</td>
                        <td class="actions">
                            <button class="download-btn" data-id="${backup.backup_id}">
                                <i class="fas fa-download"></i> Download
                            </button>
                            <button class="restore-btn" data-id="${backup.backup_id}">
                                <i class="fas fa-undo"></i> Restore
                            </button>
                            <button class="delete-btn" data-id="${backup.backup_id}">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });


                // Add event listeners to action buttons
                document.querySelectorAll('.download-btn').forEach(btn => {
                    btn.addEventListener('click', () => downloadBackup(btn.dataset.id));
                });

                document.querySelectorAll('.delete-btn').forEach(btn => {
                    btn.addEventListener('click', () => deleteBackup(btn.dataset.id));
                });
                document.querySelectorAll('.restore-btn').forEach(btn => {
                    btn.addEventListener('click', () => restoreBackupById(btn.dataset.id));
                });
            })
            .catch(error => {
                console.error('Error loading backups:', error);
                tbody.innerHTML = '<tr><td colspan="6" class="error">Failed to load backups. Please try again.</td></tr>';
            });

    }
    document.getElementById('backup-search').addEventListener('input', () => {
        currentPage = 1;
        loadBackups();
    });

    function restoreBackupById(backupId) {
        if (!confirm('Are you sure you want to restore this backup? This will overwrite current data.')) {
            return;
        }

        fetch(`backups.php?action=restore_backup`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${backupId}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadBackups();
                    alert('Backup restored successfully!');
                } else {
                    alert('Error restoring backup: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error restoring backup:', error);
                alert('Failed to restore backup');
            });
    }

    function updatePagination() {
        pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
        prevPageBtn.disabled = currentPage <= 1;
        nextPageBtn.disabled = currentPage >= totalPages;
    }

    function createBackup() {
        const name = document.getElementById('backup-name').value;
        const description = document.getElementById('backup-description').value;
        const type = document.getElementById('backup-type-modal').value;

        fetch('backups.php?action=create_backup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                name,
                description,
                type
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    backupModal.style.display = 'none';
                    backupForm.reset();
                    loadBackups();
                    alert('Backup created successfully!');
                } else {
                    alert('Error creating backup: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error creating backup:', error);
                alert('Failed to create backup');
            });
    }

    function downloadBackup(backupId) {
        window.location.href = `backups.php?action=download_backup&id=${backupId}`;
    }

    function deleteBackup(backupId) {
        if (!confirm('Are you sure you want to delete this backup? This action cannot be undone.')) {
            return;
        }

        fetch(`backups.php?action=delete_backup&id=${backupId}`, {
            method: 'DELETE'
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadBackups();
                    alert('Backup deleted successfully!');
                } else {
                    alert('Error deleting backup: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error deleting backup:', error);
                alert('Failed to delete backup');
            });
    }
});