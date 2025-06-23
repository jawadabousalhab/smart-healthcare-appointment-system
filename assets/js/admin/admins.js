
let currentPage = 1;
let itemsPerPage = 10;
document.addEventListener('DOMContentLoaded', function () {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const sidebar = document.querySelector('.hidden.md\\:flex');
    const overlay = document.createElement('div');

    // Create overlay for mobile menu
    overlay.className = 'fixed inset-0 bg-black bg-opacity-50 z-40 hidden';
    document.body.appendChild(overlay);

    if (mobileMenuButton && sidebar) {
        mobileMenuButton.addEventListener('click', function () {
            console.log('Mobile menu button clicked'); // For debugging
            sidebar.classList.toggle('hidden');
            sidebar.classList.toggle('mobile-menu-visible');
            overlay.classList.toggle('hidden');
            document.body.classList.toggle('overflow-hidden');
        });

        // Close menu when clicking overlay
        overlay.addEventListener('click', function () {
            sidebar.classList.add('hidden');
            sidebar.classList.remove('mobile-menu-visible');
            overlay.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        });
    }

    // Initialize DataTable
    const dataTable = $('#adminsTable').DataTable({
        responsive: true,
        order: [[3, 'desc']],
        columns: [
            { data: 'name' },
            { data: 'email' },
            { data: 'phone_number' },
            {
                data: 'created_at',
                render: function (data) {
                    return new Date(data).toLocaleDateString();
                }
            },
            {
                data: 'user_id',
                render: function (data, type, row) {
                    return `
                        <div class="flex space-x-2">
                            <button onclick="editAdmin(${data})" class="text-indigo-600 hover:text-indigo-900">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="confirmDelete(${data})" class="text-red-600 hover:text-red-900">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ]
    });

    // Load initial data
    loadAdmins();

    // Search functionality

    // Filter functionality
    document.getElementById('filterSelect').addEventListener('change', function () {
        const searchValue = document.getElementById('searchInput').value;
        const filterValue = this.value;
        searchAdmins(searchValue, filterValue);
    });

    // Add Admin button
    document.getElementById('addAdminBtn').addEventListener('click', function () {
        openModal('add');
    });

    // Form submission
    document.getElementById('adminForm').addEventListener('submit', function (e) {
        e.preventDefault();
        saveAdmin();
    });

    // Delete form submission
    document.getElementById('deleteForm').addEventListener('submit', function (e) {
        e.preventDefault();
        deleteAdmin();
    });
});

function loadAdmins() {
    fetch('admins.php?action=get_admins')

        .then(response => response.json())
        .then(data => {
            const table = $('#adminsTable').DataTable();
            table.clear().rows.add(data).draw();
            updatePaginationInfo(data.length);
        })
        .catch(error => {
            console.error('Error loading admins:', error);

        });

}
function updatePaginationInfo(totalItems) {
    const startItem = (currentPage - 1) * itemsPerPage + 1;
    const endItem = Math.min(currentPage * itemsPerPage, totalItems);

    document.getElementById('logsPaginationInfo').textContent =
        `Showing ${startItem} to ${endItem} of ${totalItems} entries`;

    document.getElementById('prevPageBtn').disabled = currentPage === 1;
    document.getElementById('nextPageBtn').disabled =
        currentPage * itemsPerPage >= totalItems;
}

function goToPrevPage() {
    if (currentPage > 1) {
        currentPage--;
        searchAdmins(
            document.getElementById('searchInput').value,
            document.getElementById('filterSelect').value
        );
    }
}

function goToNextPage() {
    currentPage++;
    searchAdmins(
        document.getElementById('searchInput').value,
        document.getElementById('filterSelect').value
    );
}
function searchAdmins(search, filter) {
    const params = new URLSearchParams();
    params.append('action', 'search_admins');
    if (search) params.append('search', search);
    if (filter) params.append('filter', filter);
    params.append('page', currentPage);
    params.append('per_page', itemsPerPage);

    fetch(`admins.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            const table = $('#adminsTable').DataTable();
            table.clear().rows.add(data.admins).draw();
            updatePaginationInfo(data.total);
        })
        .catch(error => {
            console.error('Error searching admins:', error);
            showMessage('Failed to search admins', 'error');
        });
}

function openModal(action, id = null) {
    const modal = document.getElementById('adminModal');
    const title = document.getElementById('modalTitle');
    const form = document.getElementById('adminForm');

    if (action === 'add') {
        title.textContent = 'Add Admin';
        form.reset();
        document.getElementById('userId').value = '';
    } else if (action === 'edit' && id) {
        title.textContent = 'Edit Admin';
        fetchAdmin(id);
    }

    modal.classList.remove('hidden');
}

function closeModal() {
    document.getElementById('adminModal').classList.add('hidden');
}

function fetchAdmin(id) {
    fetch('admins.php?action=get_admins')
        .then(response => response.json())
        .then(data => {
            const admin = data.find(a => a.user_id == id);
            if (admin) {
                document.getElementById('userId').value = admin.user_id;
                document.getElementById('name').value = admin.name;
                document.getElementById('email').value = admin.email;
                document.getElementById('phone_number').value = admin.phone_number || '';
            }
        })
        .catch(error => {
            console.error('Error fetching admin:', error);
            showMessage('Failed to load admin details', 'error');
        });
}
function saveAdmin() {
    const form = document.getElementById('adminForm');

    const phoneInput = document.querySelector("#phone_number");
    const codeInput = document.querySelector("#phone_number_code");

    const countryData = window.iti.getSelectedCountryData();
    codeInput.value = '+' + countryData.dialCode;

    const formData = new FormData(form);
    const userId = formData.get('user_id');
    const action = userId ? 'update_admin' : 'add_admin';
    formData.append('action', action);

    fetch('admins.php', {
        method: 'POST',
        body: new URLSearchParams(formData).toString(),
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    })
        .then(response => response.text()) // Always get text first
        .then(raw => {
            console.log('📦 Full raw response:', JSON.stringify(raw));
            try {
                const data = JSON.parse(raw);

                if (data.success) {
                    showMessage(data.message, 'success');
                    closeModal();
                    loadAdmins();
                } else {
                    showMessage(data.message || 'Operation failed', 'error');
                }
            } catch (e) {
                console.error('❌ Failed to parse JSON:', e.message);
                showMessage('Server returned invalid JSON', 'error');
            }
        })
        .catch(error => {
            console.error('❌ Request failed:', error);
            showMessage('Request failed. Please check console.', 'error');
        });
}


function editAdmin(id) {
    openModal('edit', id);
}

function confirmDelete(id) {
    document.getElementById('deleteUserId').value = id;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

function deleteAdmin() {
    const form = document.getElementById('deleteForm');
    const formData = new FormData(form);
    formData.append('action', 'delete_admin');

    fetch('admins.php', {
        method: 'POST',
        body: new URLSearchParams(formData).toString(),
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message, 'success');
                closeDeleteModal();
                loadAdmins();
            } else {
                showMessage(data.message || 'Deletion failed', 'error');
                closeDeleteModal();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('An error occurred. Please try again.', 'error');
        });
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