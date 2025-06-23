console.log($);  // Should log the jQuery object


$(document).ready(function () {
    let currentPage = 1;
    let currentSearch = '';

    // Load initial patients
    loadPatients();

    // Search handler
    $('#search-button').click(function () {
        currentSearch = $('#patients-search').val();
        currentPage = 1;
        loadPatients();
    });

    // Handle Enter key in search
    $('#patients-search').keypress(function (e) {
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
            loadPatients();
        }
    });
    // View report handler
    $(document).on('click', '.btn-view-report', function () {
        const reportId = $(this).data('id');
        viewReportDetails(reportId);
    });
    // View patient details handler
    $(document).on('click', '.btn-view', function () {
        const patientId = $(this).data('id');
        viewPatientDetails(patientId);
    });

    // Modal close handler
    $('.close-modal').click(function () {
        $('#patient-modal').hide();
    });

    // Close modal when clicking outside
    $(window).click(function (e) {
        if (e.target.id === 'patient-modal') {
            $('#patient-modal').hide();
        }
    });

    // Function to load patients
    function loadPatients() {
        $('.patients-list').html(`
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading patients...</p>
            </div>
        `);

        $.ajax({
            url: 'patients.php',
            type: 'GET',
            data: {
                action: 'get_patients',
                page: currentPage,
                search: currentSearch
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    renderPatients(response.data, response.pagination);
                } else {
                    showError(response.message);
                }
            },
            error: function () {
                showError('Failed to load patients. Please try again.');
            }
        });
    }

    // Function to render patients
    function renderPatients(patients, pagination) {
        if (patients.length === 0) {
            $('.patients-list').html(`
                <div class="no-patients">
                    <i class="fas fa-user-slash"></i>
                    <p>No patients found</p>
                </div>
            `);
            $('.pagination-container').empty();
            return;
        }

        let html = '<div class="patients-table">';
        html += `
            <div class="table-header">
                <div class="header-patient">Patient</div>
                <div class="header-contact">Contact</div>
                <div class="header-visits">Visits</div>
                <div class="header-last-visit">Last Visit</div>
                <div class="header-actions">Actions</div>
            </div>
        `;

        patients.forEach(patient => {
            const lastVisit = patient.last_visit ? new Date(patient.last_visit).toLocaleDateString() : 'Never';

            html += `
                <div class="patient-row" data-id="${patient.user_id}">
                    <div class="cell patient-cell">
                        <img src="${patient.profile_picture ? '../../uploads/profiles/patients/' + patient.profile_picture : '../../assets/images/default-profile.png'}" 
                             alt="${patient.name}" class="patient-photo">
                        <span>${patient.name}</span>
                    </div>
                    <div class="cell contact-cell">
                        <div class="email">${patient.email}</div>
                        <div class="phone">${patient.phone || 'No phone'}</div>
                    </div>
                    <div class="cell visits-cell">
                        ${patient.appointment_count}
                    </div>
                    <div class="cell last-visit-cell">
                        ${lastVisit}
                    </div>
                    <div class="cell actions-cell">
                        <button class="btn-action btn-view" data-id="${patient.user_id}">
                            <i class="fas fa-eye"></i> View
                        </button>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        $('.patients-list').html(html);
        renderPagination(pagination);
    }

    // Function to render pagination
    function renderPagination(pagination) {
        const paginationContainer = document.querySelector('.pagination-container');
        if (pagination.last_page <= 1) {
            paginationContainer.style.display = 'none'; // Hide pagination if only 1 page
            return;
        }

        let html = '<div class="pagination">';

        // Previous button
        if (pagination.current_page > 1) {
            html += `<button class="pagination-btn" data-page="${pagination.current_page - 1}">
                        <i class="fas fa-chevron-left"></i>
                    </button>`;
        }

        // Page numbers
        for (let i = 1; i <= pagination.last_page; i++) {
            if (i === pagination.current_page) {
                html += `<button class="pagination-btn active" data-page="${i}">${i}</button>`;
            } else {
                html += `<button class="pagination-btn" data-page="${i}">${i}</button>`;
            }
        }

        // Next button
        if (pagination.current_page < pagination.last_page) {
            html += `<button class="pagination-btn" data-page="${pagination.current_page + 1}">
                        <i class="fas fa-chevron-right"></i>
                    </button>`;
        }

        html += '</div>';
        paginationContainer.innerHTML = html;

        // Show pagination container when there are pages
        paginationContainer.style.display = 'flex';

        // Add click event listeners for pagination buttons
        const paginationButtons = paginationContainer.querySelectorAll('.pagination-btn');
        paginationButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                const page = parseInt(e.target.getAttribute('data-page'));
                loadPage(page); // Function to load the data for the specific page
            });
        });
    }

    // Function to view patient details
    function viewPatientDetails(patientId) {
        $('#modal-body').html(`
            <div class="modal-loading">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading patient details...</p>
            </div>
        `);

        $('#patient-modal').show();

        $.ajax({
            url: 'patients.php',
            type: 'GET',
            data: {
                action: 'get_patient_details',
                patient_id: patientId
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    renderPatientDetails(response.data);
                } else {
                    $('#modal-body').html(`
                        <div class="error-message">
                            <i class="fas fa-exclamation-triangle"></i>
                            <p>${response.message}</p>
                        </div>
                    `);
                }
            },
            error: function () {
                $('#modal-body').html(`
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Failed to load patient details</p>
                    </div>
                `);
            }
        });
    }

    // Function to render patient details
    function renderPatientDetails(data) {
        const patient = data.patient;
        const appointments = data.appointments;
        const reports = data.reports;

        let html = `
            <div class="patient-details">
                <div class="patient-header">
                    <img src="${patient.profile_picture ? '../../uploads/profiles/patients/' + patient.profile_picture : '../../assets/images/default-profile.png'}" 
                         alt="${patient.name}" class="patient-photo-large">
                    <div class="patient-info">
                        <h2>${patient.name}</h2>
                        <div class="contact-info">
                            <div><i class="fas fa-envelope"></i> ${patient.email}</div>
                            ${patient.phone ? `<div><i class="fas fa-phone"></i> ${patient.phone}</div>` : ''}
                        </div>
                    </div>
                </div>
                
                <div class="tabs">
                    <button class="tab-btn active" data-tab="appointments">Appointments (${appointments.length})</button>
                    <button class="tab-btn" data-tab="reports">Medical Reports (${reports.length})</button>
                </div>
                
                <div id="appointments-tab" class="tab-content active">
                    <h3><i class="fas fa-calendar-check"></i> Appointment History</h3>
        `;

        if (appointments.length > 0) {
            html += `<div class="appointments-list">`;
            appointments.forEach(appt => {
                const date = new Date(appt.appointment_date);
                const formattedDate = date.toLocaleDateString();
                const time = new Date('1970-01-01T' + appt.appointment_time + 'Z');
                const formattedTime = time.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                html += `
                    <div class="appointment-item">
                        <div class="appt-date">${formattedDate} at ${formattedTime}</div>
                        <div class="appt-status ${appt.status}">${appt.status}</div>
                        <div class="appt-reason">${appt.reason || 'No reason provided'}</div>
                    </div>
                `;
            });
            html += `</div>`;
        } else {
            html += `<div class="no-data">No appointment history found</div>`;
        }

        html += `
                </div>
                
                <div id="reports-tab" class="tab-content">
                    <h3><i class="fas fa-file-medical"></i> Medical Reports</h3>
        `;

        if (reports.length > 0) {
            html += `<div class="reports-list">`;
            reports.forEach(report => {
                const date = new Date(report.report_date);
                const formattedDate = date.toLocaleDateString();

                html += `
                    <div class="report-item">
                        <div class="report-date">${formattedDate}</div>
                        <div class="report-diagnosis">${report.diagnosis || 'No diagnosis'}</div>
                        <button class="btn-view-report" data-id="${report.report_id}">
                            <i class="fas fa-eye"></i> View
                        </button>
                    </div>
                `;
            });
            html += `</div>`;
        } else {
            html += `<div class="no-data">No medical reports found</div>`;
        }

        html += `
                </div>
            </div>
        `;

        $('#modal-body').html(html);


        // Tab switching
        $(document).on('click', '.tab-btn', function () {
            const tabId = $(this).data('tab');
            $('.tab-btn').removeClass('active');
            $(this).addClass('active');
            $('.tab-content').removeClass('active');
            $(`#${tabId}-tab`).addClass('active');
        });
    }

    // Helper functions
    function showError(message) {
        $('.patients-list').html(`
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <p>${message}</p>
                <button id="retry-btn" class="btn-retry">Retry</button>
            </div>
        `);

        $('#retry-btn').click(loadPatients);
    }
});
// Function to view report details
function viewReportDetails(reportId) {
    $('#modal-body').html(`
        <div class="modal-loading">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Loading report details...</p>
        </div>
    `);

    $.ajax({
        url: 'patients.php',
        type: 'GET',
        data: {
            action: 'get_report_details',
            report_id: reportId
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                renderReportDetails(response.data);
            } else {
                $('#modal-body').html(`
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>${response.message}</p>
                    </div>
                `);
            }
        },
        error: function () {
            $('#modal-body').html(`
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Failed to load report details</p>
                </div>
            `);
        }
    });
}

// Function to render report details
function renderReportDetails(report) {
    const date = new Date(report.report_date);
    const formattedDate = date.toLocaleDateString();

    let html = `
        <div class="report-details">
            <button class="btn-back" onclick="backToPatientDetails()">
                <i class="fas fa-arrow-left"></i> Back
            </button>
            <h2>Medical Report</h2>
            <div class="report-header">
                <div class="report-date">Date: ${formattedDate}</div>
                <div class="report-doctor">Doctor: ${report.doctor_name}</div>
            </div>
            
            <div class="report-section">
                <h3>Diagnosis</h3>
                <p>${report.diagnosis || 'No diagnosis provided'}</p>
            </div>
            
            <div class="report-section">
                <h3>Treatment</h3>
                <p>${report.treatment || 'No treatment details provided'}</p>
            </div>
            
            <div class="report-section">
                <h3>Notes</h3>
                <p>${report.notes || 'No additional notes'}</p>
            </div>
        </div>
    `;

    $('#modal-body').html(html);
}
// Function to go back to patient details
function backToPatientDetails() {
    const patientId = $('.patient-details').data('patient-id');
    viewPatientDetails(patientId);
}