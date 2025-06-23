// Rebuilt patient_dashboard.js with simple action triggers and AI integration

window.addEventListener('load', function () {
    let currentPatient = {};
    let patientLocation = null;

    const appointmentModal = new bootstrap.Modal(document.getElementById('appointmentModal'));
    const aiChatbotContainer = document.getElementById('ai-chatbot-container');
    const aiChatbotLauncher = document.getElementById('ai-chatbot-launcher');
    const aiChatbotToggle = document.getElementById('ai-chatbot-toggle');

    // Safely bind event
    function bind(id, event, handler) {
        const el = document.getElementById(id);
        if (el) el.addEventListener(event, handler);
    }

    bind('logout-btn', 'click', () => window.location.href = '../auth/logout.php');
    bind('new-appointment-btn', 'click', showAppointmentModal);
    bind('search-doctors-btn', 'click', searchDoctors);
    bind('doctor-search', 'keypress', e => { if (e.key === 'Enter') searchDoctors(); });
    bind('appointment-form', 'submit', e => { e.preventDefault(); saveAppointment(); });
    bind('settings-form', 'submit', e => { e.preventDefault(); updatePatientInfo(); });
    bind('password-form', 'submit', e => { e.preventDefault(); changePassword(); });
    bind('ai-chatbot-send-btn', 'click', sendAIChatbotMessage);
    bind('ai-chatbot-user-input', 'keypress', e => { if (e.key === 'Enter') sendAIChatbotMessage(); });
    bind('ai-chatbot-launcher', 'click', toggleChatbot);
    bind('ai-chatbot-toggle', 'click', toggleChatbot);
    bind('profile-picture-form', 'submit', e => { e.preventDefault(); updateProfilePicture(); });
    bind('profile-picture-input', 'change', handlePicturePreview);

    const profilePic = document.querySelector('.profile-pic');
    if (profilePic) profilePic.addEventListener('click', () => new bootstrap.Modal(document.getElementById('profilePictureModal')).show());

    document.querySelectorAll('.sidebar nav a').forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const section = link.getAttribute('data-section');
            showSection(section);
            document.querySelectorAll('.sidebar nav li').forEach(li => li.classList.remove('active'));
            link.parentElement.classList.add('active');
        });
    });
    const btn = document.createElement('button');
    btn.textContent = 'Share My Location';
    btn.className = 'btn btn-primary';
    btn.onclick = getPatientLocation;
    document.getElementById('ai-chatbot-messages').appendChild(btn);


    function initDashboard() {
        fetchPatientInfo();
        loadAppointments();
        loadMedicalRecords(); // Add this line
        loadLocations();
        loadDoctorsForAppointment();
        loadStats();
        getPatientLocation();

        // Show appointments section by default
        showSection('appointments');
    }

    initDashboard();

    function toggleChatbot() {
        aiChatbotContainer.classList.toggle('active');
        aiChatbotLauncher.style.display = aiChatbotContainer.classList.contains('active') ? 'none' : 'block';
    }

    function sendAIChatbotMessage() {
        const input = document.getElementById('ai-chatbot-user-input');
        const message = input.value.trim();
        if (!message) return;
        input.value = '';
        addAIChatbotMessage(message, 'user');
        checkForQuickCommand(message);
    }

    function addAIChatbotMessage(msg, sender) {
        const container = document.getElementById('ai-chatbot-messages');
        const div = document.createElement('div');
        div.className = `ai-message ${sender}`;
        div.innerHTML = `<p>${msg}</p>`;
        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
    }

    function checkForQuickCommand(message) {
        const cleaned = message.trim().toLowerCase().replace(/\s+/g, ' ');

        if (cleaned.includes('find doctor') || cleaned.includes('nearest doctor')) {
            handleFindDoctorRequest();
        } else if (cleaned.includes('book appointment') || cleaned.includes('make appointment')) {
            handleBookAppointmentRequest();
        } else {
            fetch(`patient_dashboard.php?action=ai_assistant&message=${encodeURIComponent(message)}`)
                .then(res => res.json())
                .then(data => addAIChatbotMessage(data.response || 'Sorry, I did not understand that.', 'bot'))
                .catch(() => addAIChatbotMessage('Error contacting AI.', 'bot'));
        }
    }

    function handleFindDoctorRequest() {
        if (!patientLocation) {
            addAIChatbotMessage("To find the nearest doctors, please enable location services or set your address in settings.", 'bot');
            return;
        }

        // Ask for specialization
        addAIChatbotMessage("What specialization are you looking for? (e.g., cardiologist, pediatrician, general)", 'bot');

        // Create input for specialization
        const inputDiv = document.createElement('div');
        inputDiv.className = 'ai-message bot';
        inputDiv.innerHTML = `
        <input type="text" id="ai-specialization-input" placeholder="Enter specialization" class="form-control mb-2">
        <button id="ai-find-doctors-btn" class="btn btn-sm btn-primary">Find Doctors</button>
    `;
        document.getElementById('ai-chatbot-messages').appendChild(inputDiv);

        document.getElementById('ai-find-doctors-btn').addEventListener('click', () => {
            const specialization = document.getElementById('ai-specialization-input').value || 'general';
            findAndDisplayDoctors(specialization);
        });
    }
    function findAndDisplayDoctors(specialization) {
        fetch(`patient_dashboard.php?action=find_nearest_doctors&specialization=${encodeURIComponent(specialization)}`)
            .then(res => res.json())
            .then(doctors => {
                if (!doctors.length) {
                    addAIChatbotMessage('No doctors found with that specialization.', 'bot');
                    return;
                }

                addAIChatbotMessage(`Here are ${doctors.length} ${specialization} doctors near you:`, 'bot');

                doctors.slice(0, 3).forEach(doc => {
                    const msg = document.createElement('div');
                    msg.className = 'ai-message bot';
                    msg.innerHTML = `
                    <div class="doctor-card mb-3 p-3 border rounded">
                        <h5>Dr. ${doc.name}</h5>
                        <p class="text-muted">${doc.specialization}</p>
                        <p><i class="fas fa-map-marker-alt"></i> ${doc.clinic_name} - ${doc.location}</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <button class="btn btn-sm btn-outline-primary view-location" data-clinic-id="${doc.clinic_id}">
                                <i class="fas fa-map"></i> View Location
                            </button>
                            <button class="btn btn-sm btn-success book-appointment" 
                                data-doctor-id="${doc.doctor_id}" 
                                data-clinic-id="${doc.clinic_id}">
                                <i class="fas fa-calendar-plus"></i> Book Appointment
                            </button>
                        </div>
                    </div>
                `;
                    document.getElementById('ai-chatbot-messages').appendChild(msg);
                });

                // Add event listeners
                document.querySelectorAll('.view-location').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const clinicId = this.getAttribute('data-clinic-id');
                        showClinicLocation(clinicId);
                    });
                });

                document.querySelectorAll('.book-appointment').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const doctorId = this.getAttribute('data-doctor-id');
                        const clinicId = this.getAttribute('data-clinic-id');
                        showAppointmentModal(doctorId, clinicId);
                    });
                });
            })
            .catch(() => addAIChatbotMessage('Error searching doctors.', 'bot'));
    }
    function showClinicLocation(clinicId) {
        fetch(`patient_dashboard.php?action=get_clinic_location&id=${clinicId}`)
            .then(res => res.json())
            .then(clinic => {
                if (clinic.map_coordinates) {
                    const [lat, lng] = clinic.map_coordinates.split(',');
                    showMapModal(lat, lng, clinic.name);
                } else {
                    addAIChatbotMessage(`Location for ${clinic.name}: ${clinic.location}`, 'bot');
                }
            });
    }

    function handleBookAppointmentRequest() {
        addAIChatbotMessage('Opening appointment form...', 'bot');
        showAppointmentModal();
    }

    function getPatientLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                pos => patientLocation = {
                    lat: pos.coords.latitude,
                    lng: pos.coords.longitude
                },
                err => console.warn('Location denied')
            );
        }
    }
    function showMapModal(lat, lng, clinicName) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'mapModal';
        modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">${clinicName} Location</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="clinic-map" style="height: 400px; width: 100%;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    `;

        document.body.appendChild(modal);
        const mapModal = new bootstrap.Modal(modal);
        mapModal.show();

        // FIX: delay to ensure modal is fully visible
        modal.addEventListener('shown.bs.modal', () => {
            setTimeout(() => {
                const map = new google.maps.Map(document.getElementById('clinic-map'), {
                    center: { lat: parseFloat(lat), lng: parseFloat(lng) },
                    zoom: 15
                });

                new google.maps.Marker({
                    position: { lat: parseFloat(lat), lng: parseFloat(lng) },
                    map: map,
                    title: clinicName
                });
            }, 100); // delay helps prevent render issues
        });

        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    }

    function handlePicturePreview(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = e => document.getElementById('profile-picture-preview').src = e.target.result;
            reader.readAsDataURL(file);
        }
    }

    function updateProfilePicture() {
        const file = document.getElementById('profile-picture-input').files[0];
        if (!file) return alert('Choose a picture');
        const fd = new FormData();
        fd.append('profile_picture', file);

        fetch('patient_dashboard.php?action=update_profile_picture', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Profile updated');
                    document.querySelector('.profile-pic').src = data.new_picture;
                } else alert(data.error);
            });
    }
    function loadMedicalRecords() {
        fetch('patient_dashboard.php?action=get_medical_records')
            .then(res => res.json())
            .then(records => {
                const list = document.getElementById('records-list');
                list.innerHTML = records.length ? '' : '<p class="text-muted">No medical records found.</p>';

                records.forEach(record => {
                    const card = document.createElement('div');
                    card.className = 'medical-record-card';

                    card.innerHTML = `
                    <div class="record-header">
                        <h5>${record.report_type || 'Medical Report'}</h5>
                        <span class="record-date">${record.report_date}</span>
                    </div>
                    <div class="record-body">
                        <p><strong>Doctor:</strong> Dr. ${record.doctor_name}</p>
                        ${record.clinic_name ? `<p><strong>Clinic:</strong> ${record.clinic_name}</p>` : ''}
                        <p><strong>Diagnosis:</strong> ${record.diagnosis || 'Not specified'}</p>
                        ${record.prescription ? `<p><strong>Prescription:</strong> ${record.prescription}</p>` : ''}
                        ${record.notes ? `<p><strong>Notes:</strong> ${record.notes}</p>` : ''}
                    </div>
                    ${record.file_path ? `
                    <div class="record-actions">
                        <a href="${record.file_path}" class="btn btn-sm btn-primary" target="_blank">
                            <i class="fas fa-file-download"></i> Download Report
                        </a>
                    </div>
                    ` : ''}
                `;

                    list.appendChild(card);
                });
            })
            .catch(error => {
                console.error('Error fetching medical records:', error);
                document.getElementById('records-list').innerHTML = '<p class="text-danger">Error loading medical records. Please try again.</p>';
            });
    }

    function fetchPatientInfo() {

        fetch('patient_dashboard.php?action=get_patient_info')
            .then(res => res.json())
            .then(data => {
                currentPatient = data;
                document.getElementById('patient-name').textContent = data.name;
                document.getElementById('patient-email').textContent = data.email;

                // Update settings form
                if (document.getElementById('settings-section')) {
                    document.getElementById('name').value = data.name || '';
                    document.getElementById('email').value = data.email || '';
                    document.getElementById('phone').value = data.phone_number || '';
                    document.getElementById('address').value = data.address || '';

                    // Update profile picture
                    if (data.profile_picture) {
                        const profilePic = document.querySelector('.profile-pic');
                        if (profilePic) {
                            profilePic.src = data.profile_picture.includes('http') ?
                                data.profile_picture :
                                '../../uploads/profiles/patients/' + data.profile_picture;
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching patient info:', error);
            });
    }


    function loadStats() {
        fetch('patient_dashboard.php?action=get_stats')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('total-appointments').textContent = data.appointments;
                    document.getElementById('total-records').textContent = data.records;
                    document.getElementById('total-doctors').textContent = data.doctors;
                }
            });
    }

    function loadAppointments() {
        fetch('patient_dashboard.php?action=get_appointments')
            .then(res => res.json())
            .then(data => {
                const list = document.getElementById('appointments-list');
                list.innerHTML = data.length ? '' : '<p>No appointments found.</p>';

                data.forEach(appointment => {
                    const card = document.createElement('div');
                    card.className = 'appointment-card';

                    const statusClass = `status-${appointment.status.toLowerCase()}`;

                    card.innerHTML = `
                    <div class="appointment-header">
                        <h4>Appointment with Dr. ${appointment.doctor_name}</h4>
                        <span class="status-badge ${statusClass}">${appointment.status}</span>
                    </div>
                    <div class="appointment-body">
                        <p><i class="fas fa-clinic-medical"></i> ${appointment.clinic_name}</p>
                        <p><i class="fas fa-calendar-day"></i> ${appointment.appointment_date} at ${appointment.appointment_time}</p>
                        <p><i class="fas fa-stethoscope"></i> ${appointment.reason || 'No reason provided'}</p>
                        ${appointment.notes ? `<p><i class="fas fa-notes-medical"></i> ${appointment.notes}</p>` : ''}
                    </div>
                    ${appointment.status === 'pending' || appointment.status === 'confirmed' ? `
                    <div class="appointment-actions">
                        <button class="btn btn-danger btn-sm cancel-appointment" data-id="${appointment.appointment_id}">
                            Cancel Appointment
                        </button>
                    </div>
                    ` : ''}
                `;

                    list.appendChild(card);
                });

                // Add event listeners to cancel buttons
                document.querySelectorAll('.cancel-appointment').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const appointmentId = this.getAttribute('data-id');
                        cancelAppointment(appointmentId);
                    });
                });
            });
    }

    function cancelAppointment(appointmentId) {
        if (confirm('Are you sure you want to cancel this appointment?')) {
            fetch(`patient_dashboard.php?action=cancel_appointment&id=${appointmentId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('Appointment cancelled successfully');
                        loadAppointments();
                    } else {
                        alert('Error cancelling appointment');
                    }
                });
        }
    }

    function showSection(section) {
        document.querySelectorAll('.content-section').forEach(s => s.classList.add('hidden'));
        const el = document.getElementById(`${section}-section`);
        if (el) {
            el.classList.remove('hidden');

            // Load content when section is shown
            if (section === 'find-doctors' && document.getElementById('doctors-list').children.length === 0) {
                searchDoctors();
            }
            if (section === 'medical-records' && document.getElementById('records-list').children.length === 0) {
                loadMedicalRecords();
            }
        }
    }

    function showAppointmentModal(doctorId = null, clinicId = null) {
        document.getElementById('appointment-form').reset();
        if (doctorId) document.getElementById('appointment-doctor').value = doctorId;
        if (clinicId) updateClinicsForDoctor(doctorId, clinicId);
        appointmentModal.show();
        const dateInput = document.getElementById('appointment-date');
        dateInput.onchange = function () {
            const selectedDoctor = document.getElementById('appointment-doctor').value;
            const selectedClinic = document.getElementById('appointment-clinic').value;
            const selectedDate = this.value;
            if (selectedDoctor && selectedClinic && selectedDate) {
                fetch(`patient_dashboard.php?action=get_doctor_availability&doctor_id=${selectedDoctor}&clinic_id=${selectedClinic}&date=${selectedDate}`)
                    .then(res => res.json())
                    .then(data => {
                        const timeSelect = document.getElementById('appointment-time');
                        timeSelect.innerHTML = '';
                        if (data.available_slots && data.available_slots.length > 0) {
                            data.available_slots.forEach(time => {
                                const opt = document.createElement('option');
                                opt.value = time;
                                opt.textContent = time;
                                timeSelect.appendChild(opt);
                            });
                        } else {
                            const opt = document.createElement('option');
                            opt.value = '';
                            opt.textContent = 'No available times';
                            timeSelect.appendChild(opt);
                        }
                    });
            }
        };
    }

    function updateClinicsForDoctor(doctorId, preSelectClinicId) {
        fetch(`patient_dashboard.php?action=get_clinics_for_doctor&doctor_id=${doctorId}`)
            .then(res => res.json())
            .then(clinics => {
                const select = document.getElementById('appointment-clinic');
                select.innerHTML = '<option>Select clinic</option>';
                clinics.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.clinic_id;
                    opt.textContent = c.name;
                    if (preSelectClinicId && c.clinic_id == preSelectClinicId) opt.selected = true;
                    select.appendChild(opt);
                });
            });
    }

    function saveAppointment() {
        const data = {
            doctor_id: document.getElementById('appointment-doctor').value,
            clinic_id: document.getElementById('appointment-clinic').value,
            date: document.getElementById('appointment-date').value,
            time: document.getElementById('appointment-time').value,
            reason: document.getElementById('appointment-reason').value,
            notes: document.getElementById('appointment-notes').value
        };

        fetch('patient_dashboard.php?action=book_appointment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    alert('Appointment booked');
                    appointmentModal.hide();
                    loadAppointments();
                } else alert(res.error);
            });
    }

    function updatePatientInfo() {
        const data = {
            name: document.getElementById('name').value,
            country_code: document.getElementById('country_code').value,
            phone: document.getElementById('phone').value,
            address: document.getElementById('address').value
        };

        fetch('patient_dashboard.php?action=update_patient_info', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(res => res.json())
            .then(res => alert(res.success ? 'Profile updated' : res.error));
    }

    function changePassword() {
        const data = {
            current_password: document.getElementById('current-password').value,
            new_password: document.getElementById('new-password').value,
            confirm_password: document.getElementById('confirm-password').value
        };

        fetch('patient_dashboard.php?action=change_password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    alert('Password changed');
                    document.getElementById('password-form').reset();
                } else alert(res.error);
            });
    }

    function loadDoctorsForAppointment() {
        fetch('patient_dashboard.php?action=get_doctors')
            .then(res => res.json())
            .then(doctors => {
                const select = document.getElementById('appointment-doctor');
                select.innerHTML = '<option>Select doctor</option>';
                doctors.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d.doctor_id;
                    opt.textContent = `Dr. ${d.name}`;
                    select.appendChild(opt);
                });
            });
    }

    function loadLocations() {
        const options = ['Downtown', 'Uptown', 'Eastside'];
        const select = document.getElementById('location-filter');
        options.forEach(loc => {
            const opt = document.createElement('option');
            opt.value = loc;
            opt.textContent = loc;
            select.appendChild(opt);
        });
    }

    function searchDoctors() {

        const name = document.getElementById('doctor-search').value;
        const location = document.getElementById('location-filter').value;

        fetch(`patient_dashboard.php?action=get_doctors&search=${name}&location=${location}`)
            .then(res => res.json())
            .then(doctors => {
                const list = document.getElementById('doctors-list');
                list.innerHTML = doctors.length ? '' : '<p class="text-muted">No doctors found matching your criteria.</p>';

                doctors.forEach(doctor => {
                    const card = document.createElement('div');
                    card.className = 'doctor-card';

                    card.innerHTML = `
                    <div class="doctor-image">
                        <img src="../../uploads/profiles/doctors/${doctor.profile_picture || '../../../assets/images/default-profile.png'}" alt="${doctor.name}">
                    </div>
                    <div class="doctor-info">
                        <h4>Dr. ${doctor.name}</h4>
                        <p class="specialization">${doctor.specialization || 'General Practitioner'}</p>
                        <p class="clinic"><i class="fas fa-clinic-medical"></i> ${doctor.clinic_name}</p>
                        <p class="location"><i class="fas fa-map-marker-alt"></i> ${doctor.location}</p>
                        <div class="doctor-actions">
                            <button class="btn btn-primary btn-sm view-location" data-clinic-id="${doctor.clinic_id}">
                                <i class="fas fa-map"></i> View Location
                            </button>
                            <button class="btn btn-success btn-sm book-btn" 
                                data-doctor-id="${doctor.doctor_id}" 
                                data-clinic-id="${doctor.clinic_id}">
                                <i class="fas fa-calendar-plus"></i> Book Appointment
                            </button>
                        </div>
                    </div>
                `;

                    list.appendChild(card);
                });

                // Add event listeners to buttons
                document.querySelectorAll('.view-location').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const clinicId = this.getAttribute('data-clinic-id');
                        showClinicLocation(clinicId);
                    });
                });

                document.querySelectorAll('.book-btn').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const doctorId = this.getAttribute('data-doctor-id');
                        const clinicId = this.getAttribute('data-clinic-id');
                        showAppointmentModal(doctorId, clinicId);
                    });
                });
            })
            .catch(error => {
                console.error('Error fetching doctors:', error);
                document.getElementById('doctors-list').innerHTML = '<p class="text-danger">Error loading doctors. Please try again.</p>';
            });
    }
});
