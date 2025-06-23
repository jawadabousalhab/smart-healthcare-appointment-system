$(document).ready(function () {
    // Load initial dashboard data
    loadDashboardData();
    let predictedAvailability = [];

    // Modal logic
    $('#ai-modal-btn').click(() => $('#aiModal').show());
    $('.close').click(() => $('#aiModal').hide());
    $(window).click(e => { if (e.target.id === 'aiModal') $('#aiModal').hide(); });
    $(document).ready(function () {
        const modal = $('#ai-modal');
        const openBtn = $('#ai-modal-btn');
        const closeBtn = $('.close');

        openBtn.click(function () {
            modal.show();
        });

        closeBtn.click(function () {
            modal.hide();
        });

        $(window).click(function (e) {
            if ($(e.target).is(modal)) {
                modal.hide();
            }
        });
    });

    // Navigation click handlers
    $('#dashboard-link').click(function (e) {
        e.preventDefault();
        loadDashboardData();
        setActiveNavItem(this);
    });
    $('#predict-availability').click(function () {
        const range = $('#prediction-range').val() || 7;

        $('#predict-availability').prop('disabled', true);
        $('#availability-prediction').html('<i class="fas fa-spinner fa-spin"></i> Predicting...');

        $.ajax({
            url: `../../views/doctor/ai/doctor_ai_insights.php?range=${range}`,
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.predicted_availability) {
                    window.currentPredictions = response.predicted_availability;
                    renderPredictedCalendar(response.predicted_availability, response.clinics || []);
                } else {
                    $('#availability-prediction').html(response.error || 'No predictions returned.');
                }
            },
            error: function (xhr, status, error) {
                $('#availability-prediction').html('Prediction failed: ' + error);
            },
            complete: function () {
                $('#predict-availability').prop('disabled', false);
            }
        });
    });

    $('#detect-sensitive').click(function () {
        const prompt = "Analyze upcoming appointments and identify any that might be sensitive or require immediate attention (e.g., imminent childbirth, severe symptoms). List them with reasons for prioritization.";

        $(this).prop('disabled', true);
        $('#sensitive-appointments').html('<li><i class="fas fa-spinner fa-spin"></i> Analyzing appointments...</li>');

        getAIPrediction(prompt, $('#doctor-id').val())
            .done(function (response) {
                try {
                    const data = JSON.parse(response.response);
                    $('#sensitive-appointments').empty();

                    if (data.appointments && data.appointments.length > 0) {
                        const sensitiveApps = data.appointments.filter(a =>
                            a.sensitivity === 'sensitive' || a.sensitivity === 'potentially_sensitive'
                        );

                        if (sensitiveApps.length > 0) {
                            sensitiveApps.forEach(item => {
                                let sensitivityClass = '';
                                if (item.sensitivity === 'sensitive') {
                                    sensitivityClass = 'sensitive';
                                } else if (item.sensitivity === 'potentially_sensitive') {
                                    sensitivityClass = 'potentially-sensitive';
                                }

                                $('#sensitive-appointments').append(
                                    `<li class="${sensitivityClass}">
                            <strong>${item.date} ${item.time}</strong>: ${item.reason}
                            ${item.sensitivity === 'sensitive' ?
                                        '<i class="fas fa-exclamation-triangle"></i>' :
                                        '<i class="fas fa-exclamation-circle"></i>'}
                        </li>`
                                );
                            });
                        } else {
                            $('#sensitive-appointments').html('<li>No sensitive appointments detected.</li>');
                        }
                    } else {
                        $('#sensitive-appointments').html('<li>No sensitive appointments detected.</li>');
                    }

                    // Fallback to plain text display
                    if (response.response && typeof response.response === 'string') {
                        const items = response.response.split('\n').filter(item => item.trim() !== '');
                        $('#sensitive-appointments').empty();
                        if (items.length > 0) {
                            items.forEach(item => {
                                $('#sensitive-appointments').append('<li>' + item + '</li>');
                            });
                        } else {
                            $('#sensitive-appointments').html('<li>No sensitive appointments detected.</li>');
                        }
                    } else {
                        $('#sensitive-appointments').html('<li>AI did not return a valid response.</li>');
                    }
                } catch (error) {
                    $('#sensitive-appointments').html('<li>Error processing AI response.</li>');
                }
            });
    });

    $('#appointments-link').click(function (e) {
        e.preventDefault();
        // In a complete implementation, this would load appointments view
        console.log('Appointments link clicked');
        setActiveNavItem(this);
    });

    // Add similar handlers for other navigation items...

    // Profile link handler
    $('#profile-link').click(function (e) {
        e.preventDefault();
        // In a complete implementation, this would load profile view
        console.log('Profile link clicked');
    });

    // Search button handler
    $('#search-btn').click(function () {
        const searchTerm = $('#global-search').val();
        if (searchTerm.trim() !== '') {
            // In a complete implementation, this would trigger a search
            console.log('Searching for:', searchTerm);
        }
    });

    // Handle Enter key in search
    $('#global-search').keypress(function (e) {
        if (e.which === 13) {
            $('#search-btn').click();
        }
    });



    // Function to load dashboard data
    function loadDashboardData() {
        showLoadingSpinner();

        $.ajax({
            url: 'doctors_dashboard.php?action=get_dashboard_data',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    renderDashboard(response.data);
                } else {
                    showError('Failed to load dashboard data: ' + response.message);
                }
            },
            error: function (xhr, status, error) {
                showError('Failed to load dashboard data. Please try again.');
                console.error('Error:', error);
            }
        });
    }
    function renderPredictedCalendar(predictedList, clinics) {
        window.currentPredictions = predictedList;

        let html = '<div class="calendar-prediction">';


        const clinicMap = {};
        clinics.forEach(clinic => clinicMap[clinic.clinic_id] = clinic.name);

        predictedList.forEach((item, idx) => {
            const clinicName = clinicMap[item.clinic_id] || 'Unknown Clinic';
            html += `
            <div class="calendar-slot">
                <input type="checkbox" class="slot-checkbox" data-index="${idx}" checked>
                <strong>${item.date}</strong><br>
                ${item.start_time} - ${item.end_time}<br>
                <small>${clinicName}</small>
            </div>
        `;
        });

        html += '</div>';
        $('#availability-prediction').html(html);
    }



    // Function to render dashboard content
    function renderDashboard(data) {
        const doctor = data.doctor;
        const stats = data.stats;
        const appointments = data.upcoming_appointments;

        // Update profile info
        $('#doctor-name').text('Dr. ' + doctor.name);
        if (doctor.profile_picture_path) {
            $('#profile-pic').attr('src', doctor.profile_picture_path);
        } else {
            $('#profile-pic').attr('src', '../../assets/images/default-profile.png');
        }

        // Create dashboard HTML
        let html = `
            <section class="dashboard-overview">
                <h1>Welcome, Dr. ${doctor.name}</h1>
                
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Today's Appointments</h3>
                            <p>${stats.today_appointments}</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Total Patients</h3>
                            <p>${stats.total_patients}</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Next Appointment</h3>
                            <p>${stats.next_appointment}</p>
                            ${stats.next_patient ? `<small>With ${stats.next_patient}</small>` : ''}
                        </div>
                    </div>
                </div>
            </section>
            
            <section class="appointments-section">
                <div class="section-header">
                    <h2><i class="fas fa-calendar-alt"></i> Upcoming Appointments</h2>
                    <a href="#" id="view-all-appointments" class="view-all">View All</a>
                </div>
                
                <div class="appointments-list" id="upcoming-appointments">
        `;

        if (appointments.length > 0) {
            appointments.forEach(appointment => {
                const time = new Date('1970-01-01T' + appointment.appointment_time + 'Z');
                const formattedTime = time.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                const date = new Date(appointment.appointment_date);
                const formattedDate = date.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });

                html += `
                    <div class="appointment-card" data-id="${appointment.appointment_id}">
                        <div class="appointment-time">
                            <span class="time">${formattedTime}</span>
                            <span class="date">${formattedDate}</span>
                        </div>
                        <div class="appointment-details">
                            <h3>${appointment.patient_name}</h3>
                            <p class="reason">${appointment.reason || 'No reason provided'}</p>
                        </div>
                        <div class="appointment-actions">
                            <button class="btn-start" data-id="${appointment.appointment_id}">
                                <i class="fas fa-play"></i> Start
                            </button>
                            <button class="btn-details" data-id="${appointment.appointment_id}">
                                <i class="fas fa-info-circle"></i> Details
                            </button>
                        </div>
                    </div>
                `;
            });
        } else {
            html += `
                <div class="no-appointments">
                    <i class="fas fa-calendar-times"></i>
                    <p>No upcoming appointments</p>
                </div>
            `;
        }


        $('#dashboard-content').html(html);

        // Add event listeners to dynamically created elements
        $('.btn-start').click(function () {
            const appointmentId = $(this).data('id');
            startAppointment(appointmentId);
        });

        $('.btn-details').click(function () {
            const appointmentId = $(this).data('id');
            viewAppointmentDetails(appointmentId);
        });

        // Add similar handlers for other dynamic elements...
    }

    // Helper functions
    function showLoadingSpinner() {
        $('#dashboard-content').html(`
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading dashboard...</p>
            </div>
        `);
    }
    function getAIPrediction(prompt, doctorId) {
        return $.ajax({
            url: '../../views/doctor/ai/detect_sensitive_appointments.php',
            method: 'POST',
            data: {
                prompt: prompt,
                doctor_id: doctorId
            },
            dataType: 'json'
        });
    }
    document.getElementById('accept-predictions-btn').addEventListener('click', acceptPredictedAvailability);
    async function acceptPredictedAvailability() {
        // Get the current predictions from the window object where we stored them
        if (!window.currentPredictions || window.currentPredictions.length === 0) {
            alert('No predicted availability slots found. Please generate predictions first.');
            return;
        }

        const selectedIndexes = $('.slot-checkbox:checked').map(function () {
            return parseInt($(this).data('index'));
        }).get();

        const selectedSlots = selectedIndexes.map(i => window.currentPredictions[i]);

        if (selectedSlots.length === 0) {
            alert('Please select at least one slot.');
            return;
        }

        try {
            const response = await fetch('schedule.php?action=accept_predictions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ slots: selectedSlots })
            });

            const result = await response.json();

            if (result.status === 'success') {
                alert(`Successfully saved ${result.saved_count} new slot(s).`);

                if (result.errors && result.errors.length > 0) {
                    // Extract duplicates
                    const duplicateErrors = result.errors.filter(msg => msg.toLowerCase().includes('duplicate'));
                    const otherErrors = result.errors.filter(msg => !msg.toLowerCase().includes('duplicate'));

                    if (duplicateErrors.length > 0) {
                        alert(`⚠️ ${duplicateErrors.length} slot(s) were skipped due to duplicate time entries:\n\n` + duplicateErrors.join('\n'));
                    }

                    if (otherErrors.length > 0) {
                        alert(`Some slots failed due to other issues:\n\n` + otherErrors.join('\n'));
                    }

                    console.error('Skipped slots:', result.errors);
                }

                loadDashboardData();
                $('#aiModal').hide();
            } else {
                let errorMessage = `❌ ${result.message}`;
                if (Array.isArray(result.errors) && result.errors.length > 0) {
                    const duplicateErrors = result.errors.filter(msg => msg.includes('[DUPLICATE]'));
                    if (duplicateErrors.length > 0) {
                        errorMessage += `\n\n⚠️ ${duplicateErrors.length} slot(s) were duplicates and skipped:\n${duplicateErrors.join('\n')}`;
                    }

                    const otherErrors = result.errors.filter(msg => !msg.includes('[DUPLICATE]'));
                    if (otherErrors.length > 0) {
                        errorMessage += `\n\n❗ Other issues:\n${otherErrors.join('\n')}`;
                    }
                }

                alert(errorMessage);
                console.error('Failed to save slots:', result.errors);
            }



        } catch (error) {
            console.error('Error:', error);
            alert('Failed to save predictions. Please try again.');
        }
    }


    function showError(message) {
        $('#dashboard-content').html(`
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <p>${message}</p>
                <button id="retry-btn" class="btn-retry">Retry</button>
            </div>
        `);

        $('#retry-btn').click(loadDashboardData);
    }

    function setActiveNavItem(element) {
        $('.sidebar nav ul li').removeClass('active');
        $(element).parent().addClass('active');
    }

    function startAppointment(appointmentId) {
        console.log('Starting appointment:', appointmentId);
        // In a complete implementation, this would redirect to appointment page
    }

    function viewAppointmentDetails(appointmentId) {
        console.log('Viewing appointment details:', appointmentId);
        // In a complete implementation, this would show appointment details
    }

    // Update dashboard data every 5 minutes
    setInterval(loadDashboardData, 5 * 60 * 1000);
});