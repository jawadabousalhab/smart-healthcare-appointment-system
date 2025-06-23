$(document).ready(function () {
    let currentPage = 1;
    let currentStatus = 'all';
    let currentSearch = '';

    // Load initial appointments
    loadAppointments();


    // Filter tabs click handler
    $(document).on('click', '.filter-tab', function () {
        $('.filter-tab').removeClass('active');
        $(this).addClass('active');
        currentStatus = $(this).data('status');
        currentPage = 1;
        loadAppointments();
    });

    $(document).on('click', '.btn-reschedule', function () {
        const appointmentId = $(this).data('id');

        if (confirm('Send reschedule request to patient? They will need to choose a new date/time.')) {
            $.ajax({
                url: 'appointment.php',
                type: 'POST',
                data: {
                    action: 'request_reschedule',
                    appointment_id: appointmentId
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        showMessage('Reschedule request sent to patient', 'success');
                        loadAppointments();
                    } else {
                        showMessage(response.message, 'error');
                    }
                },
                error: function () {
                    showMessage('Failed to send reschedule request', 'error');
                }
            });
        }
    });
    // Search handler
    $('#search-button').click(function () {
        currentSearch = $('#appointment-search').val();
        currentPage = 1;
        loadAppointments();
    });

    // Real-time search filter
    $('#appointment-search').on('input', function () {
        const filter = $(this).val().toLowerCase();
        $('.appointment-row').each(function () {
            const $row = $(this);
            const patientName = $row.find('.patient-name').text().toLowerCase();
            const clinicName = $row.find('.clinic-cell').text().toLowerCase();
            const date = $row.find('.date').text().toLowerCase();
            const time = $row.find('.time').text().toLowerCase();
            const status = $row.find('.status-badge').text().toLowerCase();

            if (patientName.includes(filter) ||
                clinicName.includes(filter) ||
                date.includes(filter) ||
                time.includes(filter) ||
                status.includes(filter)) {
                $row.show();
            } else {
                $row.hide();
            }
        });
    });

    // Handle Enter key in search
    $('#appointment-search').keypress(function (e) {
        if (e.which === 13) {
            $('#search-button').click();
        }
    });

    // Pagination click handler
    $(document).on('click', '.page-link', function (e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page) {
            currentPage = page;
            loadAppointments();
        }
    });

    // Appointment action handlers
    $(document).on('click', '.btn-approve', function () {
        const appointmentId = $(this).data('id');
        updateAppointmentStatus(appointmentId, 'approved');
    });

    $(document).on('click', '.btn-cancel', function () {
        const appointmentId = $(this).data('id');
        updateAppointmentStatus(appointmentId, 'cancelled');
    });

    $(document).on('click', '.btn-complete', function () {
        const appointmentId = $(this).data('id');
        updateAppointmentStatus(appointmentId, 'completed');
    });

    $(document).on('click', '.btn-view', function () {
        const appointmentId = $(this).data('id');
        viewAppointmentDetails(appointmentId);
    });

    // Modal close handler
    $('.close-modal').click(function () {
        $('#appointment-modal').hide();
    });

    // Close modal when clicking outside
    $(window).click(function (e) {
        if (e.target.id === 'appointment-modal') {
            $('#appointment-modal').hide();
        }
    });

    // Function to load appointments
    function loadAppointments() {
        // Show loading state but keep the header
        $('.appointments-list .appointment-row').remove();
        $('.appointments-list').append(`
            <div class="loading-row">
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading appointments...</p>
                </div>
            </div>
        `);

        $.ajax({
            url: 'appointment.php',
            type: 'GET',
            data: {
                action: 'get_appointments',
                status: currentStatus,
                page: currentPage,
                search: currentSearch
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    renderAppointments(response.data, response.pagination);
                } else {
                    showError(response.message);
                }
            },
            error: function () {
                // For demo purposes, load sample data
                loadSampleData();
            }
        });
    }

    // Function to load sample data (for testing)
    function loadSampleData() {
        const sampleAppointments = [
            {
                appointment_id: 1,
                patient_name: "John Doe",
                patient_email: "john@example.com",
                patient_photo: null,
                clinic_name: "Main Clinic",
                appointment_date: "2024-01-15",
                appointment_time: "10:30:00",
                status: "approved",
                reason: "Routine Checkup"
            },
            {
                appointment_id: 2,
                patient_name: "Jane Smith",
                patient_email: "jane@example.com",
                patient_photo: null,
                clinic_name: "Downtown Clinic",
                appointment_date: "2024-01-16",
                appointment_time: "14:00:00",
                status: "pending",
                reason: "Follow-up"
            },
            {
                appointment_id: 3,
                patient_name: "Mike Johnson",
                patient_email: "mike@example.com",
                patient_photo: null,
                clinic_name: "Main Clinic",
                appointment_date: "2024-01-17",
                appointment_time: "09:15:00",
                status: "completed",
                reason: "Consultation"
            }
        ];

        const pagination = {
            current_page: 1,
            last_page: 1,
            total: 3
        };

        renderAppointments(sampleAppointments, pagination);
    }

    // Function to render appointments using CSS Grid layout
    function renderAppointments(appointments, pagination) {
        // Remove loading/error states
        $('.loading-row, .no-appointments, .error-message').remove();
        $('.appointment-row').remove();

        if (appointments.length === 0) {
            $('.appointments-list').append(`
                <div class="no-appointments" style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem;"></i>
                    <p style="color: #64748b; font-size: 1.1rem;">No appointments found</p>
                </div>
            `);
            $('.pagination-container').empty();
            return;
        }

        // Filter appointments based on current status
        let filteredAppointments = appointments;
        if (currentStatus !== 'all') {
            filteredAppointments = appointments.filter(apt => apt.status === currentStatus);
        }

        // Render each appointment as a CSS Grid row
        filteredAppointments.forEach(appointment => {
            const date = new Date(appointment.appointment_date);
            const formattedDate = date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });

            const time = new Date('1970-01-01T' + appointment.appointment_time);
            const formattedTime = time.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            const appointmentRow = `
                <div class="appointment-row" data-id="${appointment.appointment_id}">
                    <div class="patient-cell" data-label="Patient">
                        <img src="${appointment.patient_photo ? '../../uploads/profiles/patients/' + appointment.patient_photo : '../../assets/images/default-profile.png'}" 
                             alt="${appointment.patient_name}" class="patient-photo">
                        <div class="patient-info">
                            <div class="patient-name">${appointment.patient_name}</div>
                            <div class="patient-email">${appointment.patient_email || ''}</div>
                        </div>
                    </div>
                    <div class="clinic-cell" data-label="Clinic">${appointment.clinic_name || 'Not specified'}</div>
                    <div class="date-cell" data-label="Date & Time">
                        <div class="date">${formattedDate}</div>
                        <div class="time">${formattedTime}</div>
                    </div>
                    <div class="status-cell" data-label="Status">
                        <span class="status-badge ${appointment.status.replace(/\s+/g, '_')}">${appointment.status.replace(/_/g, ' ')}</span>
                    </div>
                    <div class="actions-cell" data-label="Actions">
                        ${getActionButtons(appointment)}
                    </div>
                </div>
            `;

            $('.appointments-list').append(appointmentRow);
        });

        renderPagination(pagination);
    }

    // Function to get action buttons based on appointment status
    function getActionButtons(appointment) {
        let buttons = '';

        if (['pending', 'rescheduled'].includes(appointment.status)) {
            buttons += `
                <button class="btn-action btn-approve" data-id="${appointment.appointment_id}">
                    <i class="fas fa-check"></i> Approve
                </button>
                <button class="btn-action btn-cancel" data-id="${appointment.appointment_id}">
                    <i class="fas fa-times"></i> Cancel
                </button>
            `;
        }

        if (appointment.status === 'approved') {
            buttons += `
                <button class="btn-action btn-complete" data-id="${appointment.appointment_id}">
                    <i class="fas fa-check-circle"></i> Complete
                </button>
            `;
        }

        if (appointment.status === 'asked to reschedule') {
            buttons += `
                <button class="btn-action btn-reschedule" data-id="${appointment.appointment_id}">
                    <i class="fas fa-calendar-alt"></i> Reschedule
                </button>
            `;
        }

        // Always show view button
        buttons += `
            <button class="btn-action btn-view" data-id="${appointment.appointment_id}">
                <i class="fas fa-eye"></i> View
            </button>
        `;

        return buttons;
    }

    // Function to render pagination
    function renderPagination(pagination) {
        if (pagination.last_page <= 1) {
            $('.pagination-container').empty();
            return;
        }

        let html = '<div class="pagination">';

        // Previous button
        if (pagination.current_page > 1) {
            html += `<a href="#" class="page-link" data-page="${pagination.current_page - 1}">
                    <i class="fas fa-chevron-left"></i>
                </a>`;
        }

        // Page numbers
        for (let i = 1; i <= pagination.last_page; i++) {
            if (i === pagination.current_page) {
                html += `<span class="page-link active">${i}</span>`;
            } else {
                html += `<a href="#" class="page-link" data-page="${i}">${i}</a>`;
            }
        }

        // Next button
        if (pagination.current_page < pagination.last_page) {
            html += `<a href="#" class="page-link" data-page="${pagination.current_page + 1}">
                    <i class="fas fa-chevron-right"></i>
                </a>`;
        }

        html += '</div>';
        $('.pagination-container').html(html);
    }

    // Function to update appointment status
    function updateAppointmentStatus(appointmentId, status) {
        if (!confirm(`Are you sure you want to ${status} this appointment?`)) {
            return;
        }

        $.ajax({
            url: 'appointment.php',
            type: 'POST',
            data: {
                action: 'update_status',
                appointment_id: appointmentId,
                status: status
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showMessage(`Appointment ${status} successfully`, 'success');
                    loadAppointments();
                } else {
                    showMessage(response.message, 'error');
                }
            },
            error: function () {
                showMessage('Failed to update appointment status', 'error');
            }
        });
    }

    // Function to view appointment details
    function viewAppointmentDetails(appointmentId) {
        $('#modal-body').html(`
            <div class="modal-loading" style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #3b82f6;"></i>
                <p style="margin-top: 1rem; color: #64748b;">Loading appointment details...</p>
            </div>
        `);

        $('#appointment-modal').show();

        $.ajax({
            url: 'appointment.php',
            type: 'POST',
            data: {
                action: 'get_appointment_details',
                appointment_id: appointmentId
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const appointment = response.data;
                    renderAppointmentDetails(appointment);
                } else {
                    $('#modal-body').html(`
                        <div class="error-message" style="text-align: center; padding: 40px;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #ef4444;"></i>
                            <p style="margin-top: 1rem; color: #64748b;">${response.message}</p>
                        </div>
                    `);
                }
            },
            error: function () {
                // Show sample data for demo
                const sampleAppointment = {
                    patient_name: "John Doe",
                    patient_email: "john.doe@example.com",
                    patient_photo: null,
                    clinic_name: "Main Clinic",
                    appointment_date: "2024-01-15",
                    appointment_time: "10:30:00",
                    status: "approved",
                    reason: "Routine Checkup",
                    notes: "Patient requested morning appointment if possible."
                };
                renderAppointmentDetails(sampleAppointment);
            }
        });
    }

    // Function to render appointment details in modal
    function renderAppointmentDetails(appointment) {
        const date = new Date(appointment.appointment_date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        const time = new Date('1970-01-01T' + appointment.appointment_time + 'Z')
            .toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        $('#modal-body').html(`
            <h2 style="margin-bottom: 2rem; color: #1e293b;">Appointment Details</h2>
            <div class="detail-grid" style="display: grid; gap: 1.5rem;">
                <div class="detail-item" style="display: flex; align-items: center; gap: 1rem;">
                    <label style="font-weight: 600; color: #374151;">
                        Patient:
                    </label>
                    <div class="detail-value">
                        <img src="${appointment.patient_photo ? '../../uploads/profiles/patients/' + appointment.patient_photo : '../../assets/images/default-profile.png'}" 
                             alt="${appointment.patient_name}" class="patient-photo">
                        <span>${appointment.patient_name}</span>
                    </div>
                </div>
                <div class="detail-item" style="display: flex; align-items: center; gap: 1rem;">
                    <label style="font-weight: 600; color: #374151;">
                        Clinic:
                    </label>
                    <div class="detail-value">
                        <span>${appointment.clinic_name || 'Not specified'}</span>
                    </div>
                </div>
                <div class="detail-item" style="display: flex; align-items: center; gap: 1rem;">
                    <label style="font-weight: 600; color: #374151;">
                        Date:
                    </label>
                    <div class="detail-value">
                        <span>${date}</span>
                    </div>
                </div>
                <div class="detail-item" style="display: flex; align-items: center; gap: 1rem;">
                    <label style="font-weight: 600; color: #374151;">
                        Time:
                    </label>
                    <div class="detail-value">
                        <span>${time}</span>
                    </div>
                </div>
                <div class="detail-item" style="display: flex; align-items: center; gap: 1rem;">
                    <label style="font-weight: 600; color: #374151;">
                        Reason:
                    </label>
                    <div class="detail-value">
                        <span>${appointment.reason || 'Not specified'}</span>
                    </div>
                </div>
                <div class="detail-item" style="display: flex; align-items: center; gap: 1rem;">
                    <label style="font-weight: 600; color: #374151;">
                        Status:
                    </label>
                    <div class="detail-value">
                        <span class="status-badge ${appointment.status.replace(/\s+/g, '_')}">
  ${appointment.status}
</span>
                    </div>
                </div>
                <div class="detail-item" style="display: flex; align-items: center; gap: 1rem;">
                    <label style="font-weight: 600; color: #374151;">
                        Notes:
                    </label>
                    <div class="detail-value">
                        <p>${appointment.notes || 'No notes added.'}</p>
                    </div>
                </div>
            </div>
        `);
    }


    // Helper functions
    function showMessage(message, type) {
        const $message = $(`<div class="message ${type}">${message}</div>`);
        $('#appointments-container').prepend($message);
        setTimeout(() => $message.fadeOut(), 3000);
    }

    function showError(message) {
        $('.appointments-list').html(`
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <p>${message}</p>
                <button id="retry-btn" class="btn-retry">Retry</button>
            </div>
        `);

        $('#retry-btn').click(loadAppointments);
    }
});