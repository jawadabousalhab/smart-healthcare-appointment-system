document.addEventListener('DOMContentLoaded', function () {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const sidebar = document.querySelector('.hidden.md\\:flex');
    window.currentPage = 1;
    window.itemsPerPage = 10;
    window.allAdmins = [];
    const input = document.querySelector("#phone_number");
    const codeInput = document.querySelector("#phone_number_code");

    // Make iti accessible from other scripts
    window.iti = window.intlTelInput(input, {
        initialCountry: "auto",
        geoIpLookup: function (callback) {
            fetch("https://ipapi.co/json")
                .then(res => res.json())
                .then(data => callback(data.country_code))
                .catch(() => callback("us"));
        },
        utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.17/js/utils.js"
    });

    // Set initial country code
    window.addEventListener("load", function () {
        const countryData = iti.getSelectedCountryData();
        codeInput.value = '+' + countryData.dialCode;
    });

    input.addEventListener("countrychange", function () {
        const countryData = iti.getSelectedCountryData();
        codeInput.value = '+' + countryData.dialCode;
    });


    if (mobileMenuButton && sidebar) {
        mobileMenuButton.addEventListener('click', function () {
            sidebar.classList.toggle('hidden');
            sidebar.classList.toggle('mobile-menu-visible');
        });
    }

    loadItAdmins();



    document.getElementById('searchInput').addEventListener('input', () => renderItAdmins());
    document.getElementById('filterSelect').addEventListener('change', () => renderItAdmins());

    document.getElementById('addItAdminBtn').addEventListener('click', function () {
        openModal('Add IT Admin');
    });

    document.getElementById('itAdminForm').addEventListener('submit', function (e) {
        e.preventDefault();
        saveItAdmin();
    });

    document.getElementById('deleteForm').addEventListener('submit', function (e) {
        e.preventDefault();
        deleteItAdmin();
    });

    document.getElementById('prevPageBtn').addEventListener('click', () => {
        if (window.currentPage > 1) {
            window.currentPage--;
            renderItAdmins();
        }
    });

    document.getElementById('nextPageBtn').addEventListener('click', () => {
        const maxPage = Math.ceil(window.allAdmins.length / window.itemsPerPage);
        if (window.currentPage < maxPage) {
            window.currentPage++;
            renderItAdmins();
        }
    });
});

function loadItAdmins() {
    console.log("Trying to fetch IT Admins...");
    fetch('it_admins.php?action=get_it_admins')
        .then(res => res.json())
        .then(data => {
            console.log('Raw response from PHP:', data); // DEBUG
            if (data.success && Array.isArray(data.data)) {
                window.allAdmins = data.data;
                renderItAdmins();
            } else {
                console.error('Unexpected response structure:', data);
            }
        })
        .catch(err => console.error('Error fetching IT Admins:', err));
}
function renderItAdmins() {
    const tbody = document.getElementById('itAdminsBody');
    const search = document.getElementById('searchInput').value.toLowerCase();
    const filter = document.getElementById('filterSelect').value;

    let filtered = window.allAdmins;

    if (search) {
        filtered = filtered.filter(a =>
            a.name.toLowerCase().includes(search) ||
            a.email.toLowerCase().includes(search) ||
            (a.phone_number && a.phone_number.toLowerCase().includes(search))
        );
    }

    if (filter === 'recent') {
        // You'll need to implement recent filter logic if needed
    }

    const totalItems = filtered.length;
    const start = (window.currentPage - 1) * window.itemsPerPage;
    const paginated = filtered.slice(start, start + window.itemsPerPage);

    tbody.innerHTML = '';

    paginated.forEach((admin) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">${admin.name}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">${admin.email}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">${admin.phone_number || 'N/A'}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">${admin.created_at}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                <button class="fas fa-edit text-indigo-600 hover:text-indigo-900" onclick="editItAdmin(${admin.user_id})"></button>
                <button class="fas fa-trash text-red-600 hover:text-red-900 ml-2" onclick="confirmDelete(${admin.user_id})"></button>
            </td>
        `;
        tbody.appendChild(row);
    });

    updatePaginationInfo(totalItems);
}

function updatePaginationInfo(totalItems) {
    const startItem = (currentPage - 1) * itemsPerPage + 1;
    const endItem = Math.min(currentPage * itemsPerPage, totalItems);

    const paginationText = document.getElementById('paginationInfoText');
    const prevBtn = document.getElementById('prevPageBtn');
    const nextBtn = document.getElementById('nextPageBtn');

    if (paginationText) {
        paginationText.textContent = `Showing ${startItem} to ${endItem} of ${totalItems} entries`;
    }

    if (prevBtn) prevBtn.disabled = currentPage === 1;
    if (nextBtn) nextBtn.disabled = currentPage * itemsPerPage >= totalItems;
}

function openModal(title = "Add IT Admin", admin = null) {
    document.getElementById('modalTitle').textContent = title;

    const phoneInput = document.getElementById('phone_number');

    if (admin) {
        document.getElementById('userId').value = admin.user_id;
        document.getElementById('name').value = admin.full_name;
        document.getElementById('email').value = admin.email;
        phoneInput.value = admin.phone_number || '';
    } else {
        document.getElementById('itAdminForm').reset();
    }

    document.getElementById('itAdminModal').classList.remove('hidden');

}


function closeModal() {
    document.getElementById('itAdminModal').classList.add('hidden');
}

function confirmDelete(id) {
    document.getElementById('deleteUserId').value = id;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

function deleteItAdmin() {
    const form = document.getElementById('deleteForm');
    const formData = new FormData(form);
    formData.append('action', 'delete_it_admin');

    fetch('it_admins.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            showMessage(data.message, data.success ? 'success' : 'error');
            closeDeleteModal();
            loadItAdmins();
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('An error occurred', 'error');
        });
}

function saveItAdmin() {
    const phoneInput = document.getElementById('phone_number');
    const phoneCodeInput = document.getElementById('phone_number_code');

    if (!window.iti || typeof window.iti.getSelectedCountryData !== 'function') {
        showMessage('Phone input not initialized', 'error');
        console.error('❌ iti is undefined or not ready');
        return;
    }

    // 1. Get country code and set it into the #phone_number_code input
    const countryCode = '+' + window.iti.getSelectedCountryData().dialCode;
    phoneCodeInput.value = countryCode;

    // 2. Basic validation
    const localNumber = phoneInput.value.trim();
    const fullNumber = countryCode + localNumber;

    const phoneRegex = /^\+\d{7,20}$/;
    if (!phoneRegex.test(fullNumber)) {
        showMessage('Invalid phone number. Use international format.', 'error');
        phoneInput.classList.add('border-red-500');
        phoneInput.focus();
        return;
    }

    // 3. Submit
    const form = document.getElementById('itAdminForm');
    const formData = new FormData(form);
    const userId = formData.get('user_id');
    formData.append('action', userId ? 'update_it_admin' : 'add_it_admin');

    // DEBUG
    console.log("📤 Sending to PHP:");
    for (const [k, v] of formData.entries()) {
        console.log(`${k}: ${v}`);
    }

    fetch('it_admins.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => {
                    console.error('❌ Server error:', err);
                    throw new Error(err.message || `HTTP Error: ${response.status}`);
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('✅ Server returned:', data);
            showMessage(data.message, data.success ? 'success' : 'error');
            if (data.success) {
                closeModal();
                loadItAdmins();
            }
        })
        .catch(error => {

            showMessage('An error occurred: ' + error.message, 'error');
        });

    return false;
}



function editItAdmin(id) {
    const admin = window.allAdmins.find(a => a.user_id == id);
    if (admin) {
        openModal('Edit IT Admin', {
            user_id: admin.user_id,
            full_name: admin.name,  // Map name to full_name
            email: admin.email,
            phone_number: admin.phone_number
        });
    }
}

function showMessage(message, type) {
    const container = document.getElementById('messageContainer');
    container.innerHTML = `
        <div class="${type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'} px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">${message}</span>
        </div>
    `;
    container.classList.remove('hidden');
    setTimeout(() => {
        container.classList.add('hidden');
    }, 5000);
}
