document.addEventListener('DOMContentLoaded', function () {
    // DOM Elements
    const doctorsTable = document.getElementById('doctors-table');
    const doctorSearch = document.getElementById('doctor-search');
    const searchBtn = document.getElementById('search-btn');
    const clinicFilter = document.getElementById('clinic-filter');
    const addDoctorBtn = document.getElementById('add-doctor-btn');
    const doctorModal = document.getElementById('doctor-modal');
    const closeBtn = document.querySelector('.close-btn');
    const cancelBtn = document.getElementById('cancel-doctor');
    const doctorForm = document.getElementById('doctor-form');
    const prevPageBtn = document.getElementById('prev-page');
    const nextPageBtn = document.getElementById('next-page');
    const pageInfo = document.getElementById('page-info');

    // State variables
    let currentPage = 1;
    let totalPages = 1;
    let currentSearch = '';
    let currentClinic = '';

    // Initialize
    loadClinicsForFilter();
    loadDoctors();

    // Event listeners
    searchBtn.addEventListener('click', () => {
        currentSearch = doctorSearch.value;
        currentPage = 1;
        loadDoctors();
    });

    doctorSearch.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            currentSearch = doctorSearch.value;
            currentPage = 1;
            loadDoctors();
        }
    });

    clinicFilter.addEventListener('change', () => {
        currentClinic = clinicFilter.value;
        currentPage = 1;
        loadDoctors();
    });

    addDoctorBtn.addEventListener('click', async () => {
        const searchQuery = prompt("Enter doctor's name or email to search:");
        if (!searchQuery) return;

        try {
            const response = await fetch(`doctors.php?action=find_existing_doctor&query=${encodeURIComponent(searchQuery)}`);
            const result = await response.json();

            if (result && result.user_id) {
                showModal('Assign Existing Doctor to Clinics', result.user_id);
            } else {
                if (confirm("Doctor not found. Do you want to create a new doctor account?")) {
                    showModal('Add New Doctor');
                }
            }
        } catch (error) {
            console.error("Error searching doctor:", error);
            alert("Something went wrong while searching for the doctor.");
        }
    });


    closeBtn.addEventListener('click', () => {
        doctorModal.style.display = 'none';
    });

    cancelBtn.addEventListener('click', () => {
        doctorModal.style.display = 'none';
    });

    window.addEventListener('click', (e) => {
        if (e.target === doctorModal) {
            doctorModal.style.display = 'none';
        }
    });

    prevPageBtn.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            loadDoctors();
        }
    });

    nextPageBtn.addEventListener('click', () => {
        if (currentPage < totalPages) {
            currentPage++;
            loadDoctors();
        }
    });

    doctorForm.addEventListener('submit', (e) => {
        e.preventDefault();
        saveDoctor();
    });

    // Functions
    function loadClinicsForFilter() {
        fetch('doctors.php?action=get_assigned_clinics')
            .then(response => response.json())
            .then(clinics => {
                clinicFilter.innerHTML = '<option value="">All Clinics</option>';
                clinics.forEach(clinic => {
                    const option = document.createElement('option');
                    option.value = clinic.clinic_id;
                    option.textContent = clinic.name;
                    clinicFilter.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error loading clinics:', error);
            });
    }

    function loadDoctors() {
        const tbody = doctorsTable.querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="7" class="loading">Loading doctors...</td></tr>';

        let url = `doctors.php?action=get_doctors&page=${currentPage}`;
        if (currentSearch) {
            url += `&search=${encodeURIComponent(currentSearch)}`;
        }
        if (currentClinic) {
            url += `&clinic_id=${currentClinic}`;
        }

        fetch(url)
            .then(response => response.json())
            .then(data => {
                totalPages = data.totalPages;
                updatePagination();

                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="no-data">No doctors found</td></tr>';
                    return;
                }

                tbody.innerHTML = '';
                data.data.forEach(doctor => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${doctor.user_id}</td>
                        <td>${doctor.name}</td>
                        <td>${doctor.email}</td>
                        <td>${doctor.phone_number || '-'}</td>
                        <td>${doctor.specialization}</td>
                        <td>${doctor.assigned_clinics || 'Not assigned'}</td>
                        <td class="actions">
                            <button class="edit-btn" data-id="${doctor.user_id}">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });

                // Add event listeners to edit buttons
                document.querySelectorAll('.edit-btn').forEach(btn => {
                    btn.addEventListener('click', () => editDoctor(btn.dataset.id));
                });
            })
            .catch(error => {
                console.error('Error loading doctors:', error);
                tbody.innerHTML = '<tr><td colspan="7" class="error">Failed to load doctors. Please try again.</td></tr>';
            });
    }

    function updatePagination() {
        pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
        prevPageBtn.disabled = currentPage <= 1;
        nextPageBtn.disabled = currentPage >= totalPages;
    }

    function showModal(title, doctorId = null) {
        const modalTitle = doctorId ? 'Assign Existing Doctor to Clinics' : 'Add New Doctor';
        document.getElementById('modal-title').textContent = modalTitle;
        document.getElementById('modal-title').textContent = title;
        document.getElementById('doctor-id').value = doctorId || '';

        if (doctorId) {
            // Load doctor data for editing
            fetch(`doctors.php?action=get_doctor&id=${doctorId}`)
                .then(response => response.json())
                .then(doctor => {
                    document.getElementById('doctor-name').value = doctor.name;
                    document.getElementById('doctor-email').value = doctor.email;
                    document.getElementById('doctor-phone').value = doctor.phone_number || '';
                    document.getElementById('country-code').value = doctor.country_code || '';
                    document.getElementById('doctor-specialization').value = doctor.specialization;

                    // Load clinics checkboxes
                    loadClinicsForAssignment(doctor.assigned_clinics || []);
                })
                .catch(error => {
                    console.error('Error loading doctor:', error);
                    alert('Failed to load doctor data');
                });
        } else {
            // Reset form for new doctor
            doctorForm.reset();
            loadClinicsForAssignment([]);
        }

        doctorModal.style.display = 'block';
    }

    function loadClinicsForAssignment(assignedClinicIds) {
        const checkboxesContainer = document.getElementById('clinics-checkboxes');

        fetch('doctors.php?action=get_assigned_clinics')
            .then(response => response.json())
            .then(clinics => {
                if (clinics.length === 0) {
                    checkboxesContainer.innerHTML = '<div class="no-clinics">No clinics available</div>';
                    return;
                }

                checkboxesContainer.innerHTML = '';
                clinics.forEach(clinic => {
                    const div = document.createElement('div');
                    div.className = 'checkbox-group';
                    div.innerHTML = `
                        <input type="checkbox" id="clinic-${clinic.clinic_id}" 
                               name="clinics" value="${clinic.clinic_id}"
                               ${assignedClinicIds.includes(clinic.clinic_id.toString()) ? 'checked' : ''}>
                        <label for="clinic-${clinic.clinic_id}">${clinic.name}</label>
                    `;
                    checkboxesContainer.appendChild(div);
                });
            })
            .catch(error => {
                console.error('Error loading clinics:', error);
                checkboxesContainer.innerHTML = '<div class="error">Failed to load clinics</div>';
            });
    }

    function editDoctor(doctorId) {
        showModal('Edit Doctor', doctorId);
    }

    function saveDoctor() {
        const doctorId = document.getElementById('doctor-id').value;
        const name = document.getElementById('doctor-name').value;
        const email = document.getElementById('doctor-email').value;
        const phone = document.getElementById('doctor-phone').value;
        const countryCode = document.getElementById('country-code').value;
        const specialization = document.getElementById('doctor-specialization').value;

        const assignedClinics = [];
        document.querySelectorAll('input[name="clinics"]:checked').forEach(checkbox => {
            assignedClinics.push(checkbox.value);
        });

        const data = {
            doctor_id: doctorId || null,
            name,
            email,
            phone: phone,
            country_code: countryCode,
            specialization,
            assigned_clinics: assignedClinics
        };

        // For new doctors, generate a temporary password
        if (!doctorId) {
            data.password = generateTempPassword();
            if (!confirm(`A temporary password will be generated for this doctor: ${data.password}\n\nMake sure to provide this password to the doctor. Continue?`)) {
                return;
            }
        }

        fetch('doctors.php?action=save_doctor', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    doctorModal.style.display = 'none';
                    loadDoctors();
                    alert('Doctor saved successfully!' + (!doctorId ? `\n\nTemporary password: ${data.password}` : ''));
                } else {
                    alert('Error saving doctor: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error saving doctor:', error);
                alert('Failed to save doctor');
            });
    }

    function generateTempPassword() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let password = '';
        for (let i = 0; i < 10; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return password;
    }
});