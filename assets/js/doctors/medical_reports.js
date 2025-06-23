// Fixed and Optimized medical_reports.js

document.addEventListener('DOMContentLoaded', function () {
    // DOM Elements
    const patientSelect = document.getElementById('patient-select');
    const patientInfo = document.getElementById('patient-info');
    const patientName = document.getElementById('patient-name');
    const patientEmail = document.getElementById('patient-email');
    const reportsList = document.getElementById('reports-list');
    const newReportBtn = document.getElementById('new-report-btn');
    const reportModal = document.getElementById('report-modal');
    const closeModalBtns = document.querySelectorAll('.close-btn');
    const reportForm = document.getElementById('report-form');
    const appointmentSelect = document.getElementById('appointment-id');
    const modalPatientId = document.getElementById('modal-patient-id');
    const reportTypeSelect = document.getElementById('report-type');

    // Load patients for the current doctor
    function loadPatients() {
        fetch('medical_reports.php?action=get_patients')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    patientSelect.innerHTML = '<option value="">-- Select a patient --</option>';
                    data.data.forEach(patient => {
                        const option = document.createElement('option');
                        option.value = patient.user_id;
                        option.textContent = `${patient.name} (${patient.email})`;
                        patientSelect.appendChild(option);
                    });
                } else {
                    showError(data.message);
                }
            })
            .catch(error => {
                showError('Failed to load patients: ' + error.message);
            });
    }

    // Load patient's medical reports
    function loadPatientReports(patientId) {
        if (!patientId) {
            reportsList.innerHTML = '<p class="empty-message">Select a patient to view their medical reports.</p>';
            return;
        }

        fetch(`medical_reports.php?patient_id=${patientId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.data.length === 0) {
                        reportsList.innerHTML = '<p class="empty-message">No medical reports found for this patient.</p>';
                        return;
                    }

                    reportsList.innerHTML = '';
                    data.data.forEach(report => {
                        const reportCard = createReportCard(report);
                        reportsList.appendChild(reportCard);
                    });
                } else {
                    showError(data.message);
                }
            })
            .catch(error => {
                showError('Failed to load reports: ' + error.message);
            });
    }

    // Create a report card element
    function createReportCard(report) {
        const card = document.createElement('div');
        card.className = 'report-card';

        const reportDate = new Date(report.report_date).toLocaleDateString();
        const appointmentDate = report.appointment_date
            ? new Date(report.appointment_date).toLocaleDateString()
            : 'N/A';

        card.innerHTML = `
            <div class="report-header">
                <span class="report-title">${report.report_type ? formatReportType(report.report_type) : 'Medical Report'}</span>
                <span class="report-date">${reportDate}</span>
            </div>
            <div class="report-doctor">Doctor: ${report.doctor_name || 'N/A'}</div>
            <div class="report-appointment">Appointment: ${appointmentDate}</div>
            ${report.diagnosis ? `<div class="report-content"><strong>Diagnosis:</strong> ${report.diagnosis}</div>` : ''}
            ${report.prescription ? `<div class="report-content"><strong>Prescription:</strong> ${report.prescription}</div>` : ''}
            ${report.notes ? `<div class="report-content"><strong>Notes:</strong> ${report.notes}</div>` : ''}
            <div class="report-actions">
                ${report.file_path ? `<a href="${report.file_path}" download class="btn btn-primary"><i class="fas fa-file-download"></i> Download PDF</a>` : ''}
            </div>
        `;

        return card;
    }

    // Format report type for display
    function formatReportType(type) {
        const types = {
            'diagnosis': 'Diagnosis Report',
            'prescription': 'Prescription',
            'lab_result': 'Lab Result',
            'imaging': 'Imaging Report',
            'progress': 'Progress Note'
        };
        return types[type] || type;
    }

    // Show error message
    function showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        reportsList.innerHTML = '';
        reportsList.appendChild(errorDiv);
    }

    // Load patient's appointments for the modal
    function loadPatientAppointments(patientId) {
        if (!patientId) return;

        fetch(`get_patient_appointments.php?patient_id=${patientId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    appointmentSelect.innerHTML = '<option value="">-- Select appointment --</option>';
                    data.data.forEach(appointment => {
                        const option = document.createElement('option');
                        option.value = appointment.appointment_id;
                        const date = new Date(appointment.appointment_date).toLocaleDateString();
                        option.textContent = `${date} - ${appointment.reason || 'No reason provided'}`;
                        appointmentSelect.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Error loading appointments:', error);
            });
    }

    // Patient selection change
    patientSelect.addEventListener('change', function () {
        const patientId = this.value;

        if (patientId) {
            const selectedOption = this.options[this.selectedIndex];
            const patientText = selectedOption.textContent;
            const emailMatch = patientText.match(/\(([^)]+)\)/);
            const email = emailMatch ? emailMatch[1] : '';

            patientName.textContent = selectedOption.textContent.replace(/\([^)]+\)/, '').trim();
            patientEmail.textContent = email;
            patientInfo.style.display = 'block';

            modalPatientId.value = patientId;
            loadPatientReports(patientId);
            loadPatientAppointments(patientId);
        } else {
            patientInfo.style.display = 'none';
            reportsList.innerHTML = '<p class="empty-message">Select a patient to view their medical reports.</p>';
        }
    });

    // Open New Report Modal
    newReportBtn.addEventListener('click', function () {
        const patientId = patientSelect.value;
        if (!patientId) {
            alert('Please select a patient first.');
            return;
        }

        reportForm.reset();
        reportModal.style.display = 'flex';
    });

    // Close modal buttons
    closeModalBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            reportModal.style.display = 'none';
        });
    });

    // Close modal when clicking outside
    window.addEventListener('click', function (e) {
        if (e.target === reportModal) {
            reportModal.style.display = 'none';
        }
    });

    // Submit form
    reportForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        const patientId = patientSelect.value;

        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        fetch('medical_reports.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    reportModal.style.display = 'none';
                    reportForm.reset();
                    loadPatientReports(patientId);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error submitting form: ' + error.message);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Save Report';
            });
    });
    function loadPatientMedicalHistory(patientId) {
        if (!patientId) return;

        fetch(`get_patient_medical_history.php?patient_id=${patientId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const historyTextarea = document.getElementById('medical-history');
                    if (data.data.length > 0) {
                        let historyText = '';
                        data.data.forEach(record => {
                            historyText += `${record.date}: ${record.diagnosis}\n`;
                            if (record.prescription) {
                                historyText += `Prescription: ${record.prescription}\n`;
                            }
                            historyText += '\n';
                        });
                        historyTextarea.value = historyText;
                    } else {
                        historyTextarea.value = 'No medical history found for this patient.';
                    }
                }
            })
            .catch(error => {
                console.error('Error loading medical history:', error);
                document.getElementById('medical-history').value = 'Error loading medical history.';
            });
    }

    // Update the modal opening function
    newReportBtn.addEventListener('click', function () {
        const patientId = patientSelect.value;
        if (!patientId) {
            alert('Please select a patient first.');
            return;
        }

        reportForm.reset(); // Important: reset BEFORE setting values

        const selectedOption = patientSelect.options[patientSelect.selectedIndex];
        const patientText = selectedOption.textContent;
        const emailMatch = patientText.match(/\(([^)]+)\)/);
        const email = emailMatch ? emailMatch[1] : '';
        const extractedName = patientText.replace(/\([^)]+\)/, '').trim();

        document.getElementById('patient-name-display').value = extractedName;
        document.getElementById('patient-contact-display').value = email;
        document.getElementById('report-date').value = new Date().toISOString().split('T')[0];

        fetch('get_doctor_info.php')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('doctor-name-display').value = data.data.name;
                }
            });

        loadPatientAppointments(patientId);
        loadPatientMedicalHistory(patientId);

        reportModal.style.display = 'flex';
    });
    // Update the form submit handler to include medical_history
    reportForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        const patientId = patientSelect.value;

        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        fetch('medical_reports.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    reportModal.style.display = 'none';
                    reportForm.reset();
                    loadPatientReports(patientId);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error submitting form: ' + error.message);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Save Report';
            });
    });

    // Initialize the page
    loadPatients();
});
