let currentMonthDate = new Date();
document.addEventListener('DOMContentLoaded', function () {
    // DOM Elements
    const scheduleContainer = document.getElementById('schedule-container');
    const weekGrid = document.querySelector('#week-view .week-grid');
    const currentWeekRange = document.getElementById('current-week-range');
    const prevWeekBtn = document.getElementById('prev-week');
    const nextWeekBtn = document.getElementById('next-week');
    const todayBtn = document.getElementById('today-btn');
    const addAvailabilityBtn = document.getElementById('add-availability-btn');
    const viewCalendarBtn = document.getElementById('view-calendar-btn');
    const availabilityModal = document.getElementById('availability-modal');
    const editAvailabilityModal = document.getElementById('edit-availability-modal');
    const closeModalBtns = document.querySelectorAll('.close-modal');
    const availabilityForm = document.getElementById('availability-form');
    const editAvailabilityForm = document.getElementById('edit-availability-form');
    const deleteAvailabilityBtn = document.getElementById('delete-availability-btn');
    const repeatOption = document.getElementById('repeat-option');
    const repeatEndContainer = document.getElementById('repeat-end-container');
    const globalSearch = document.getElementById('global-search');
    const searchBtn = document.getElementById('search-btn');
    // Track the month being shown
    const prevMonthBtn = document.getElementById('prev-month');
    const nextMonthBtn = document.getElementById('next-month');

    prevMonthBtn.addEventListener('click', () => {
        currentMonthDate.setMonth(currentMonthDate.getMonth() - 1);
        loadMonthView();
    });

    nextMonthBtn.addEventListener('click', () => {
        currentMonthDate.setMonth(currentMonthDate.getMonth() + 1);
        loadMonthView();
    });

    // Flatpickr Initialization
    const availabilityDatePicker = flatpickr("#availability-date", {
        dateFormat: "Y-m-d",
        minDate: "today"
    });


    const editAvailabilityDatePicker = flatpickr("#edit-availability-date", {
        dateFormat: "Y-m-d",
        minDate: "today"
    });

    const endDatePicker = flatpickr("#repeat-end-date", {
        dateFormat: "Y-m-d",
        minDate: "today"
    });

    const startTimePicker = flatpickr("#start-time", {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        time_24hr: true,
        defaultDate: "09:00"
    });

    const endTimePicker = flatpickr("#end-time", {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        time_24hr: true,
        defaultDate: "17:00"
    });

    const editStartTimePicker = flatpickr("#edit-start-time", {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        time_24hr: true
    });

    const editEndTimePicker = flatpickr("#edit-end-time", {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        time_24hr: true
    });

    // Current state
    let currentDate = new Date();
    let currentWeekStart = getWeekStart(currentDate);
    let availabilities = {};
    let appointments = {};
    let clinics = [];
    let currentClinicFilter = 'all';

    // Initialize the page
    loadSchedule();

    // Event Listeners
    prevWeekBtn.addEventListener('click', () => {
        currentWeekStart.setDate(currentWeekStart.getDate() - 7);
        loadSchedule();
    });

    nextWeekBtn.addEventListener('click', () => {
        currentWeekStart.setDate(currentWeekStart.getDate() + 7);
        loadSchedule();
    });

    todayBtn.addEventListener('click', () => {
        currentDate = new Date();
        currentWeekStart = getWeekStart(currentDate);
        loadSchedule();
    });
    function setActiveNavItem(element) {
        $('.sidebar nav ul li').removeClass('active');
        $(element).parent().addClass('active');
    }
    $('#dashboard-link').click(function (e) {

        setActiveNavItem(this);
    });
    addAvailabilityBtn.addEventListener('click', openAddAvailabilityModal);

    closeModalBtns.forEach(btn => {
        btn.addEventListener('click', closeAllModals);
    });

    availabilityForm.addEventListener('submit', saveAvailability);
    editAvailabilityForm.addEventListener('submit', updateAvailability);
    deleteAvailabilityBtn.addEventListener('click', deleteAvailability);

    repeatOption.addEventListener('change', function () {
        repeatEndContainer.style.display = this.value !== 'none' ? 'block' : 'none';
    });


    searchBtn.addEventListener('click', function () {
        const searchTerm = globalSearch.value.trim().toLowerCase();
        filterScheduleItems(searchTerm);
    });

    globalSearch.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            const searchTerm = globalSearch.value.trim().toLowerCase();
            filterScheduleItems(searchTerm);
        }
    });

    // Close modal when clicking outside
    window.addEventListener('click', function (event) {
        if (event.target === availabilityModal) {
            closeAllModals();
        }
        if (event.target === editAvailabilityModal) {
            closeAllModals();
        }
    });

    // Functions
    function getWeekStart(date) {
        const day = date.getDay();
        const diff = date.getDate() - day + (day === 0 ? -6 : 1); // Adjust for Sunday
        return new Date(date.setDate(diff));
    }

    function formatDate(date) {
        return date.toISOString().split('T')[0];
    }

    function formatTime(timeStr) {
        if (!timeStr) return '';
        const [hours, minutes] = timeStr.split(':');
        const hour = parseInt(hours) % 12 || 12;
        const ampm = parseInt(hours) < 12 ? 'AM' : 'PM';
        return `${hour}:${minutes} ${ampm}`;
    }

    function updateWeekRangeDisplay() {
        const weekEnd = new Date(currentWeekStart);
        weekEnd.setDate(weekEnd.getDate() + 6);

        const options = { month: 'short', day: 'numeric' };
        const startStr = currentWeekStart.toLocaleDateString('en-US', options);
        const endStr = weekEnd.toLocaleDateString('en-US', options);

        currentWeekRange.textContent = `${startStr} - ${endStr}, ${currentWeekStart.getFullYear()}`;
    }

    function loadSchedule() {
        const weekEnd = new Date(currentWeekStart);
        weekEnd.setDate(weekEnd.getDate() + 6);

        const startDate = formatDate(currentWeekStart);
        const endDate = formatDate(weekEnd);

        weekGrid.innerHTML = '';
        scheduleContainer.querySelector('.loading-spinner').style.display = 'flex';

        let url = `schedule.php?start_date=${startDate}&end_date=${endDate}`;
        if (currentClinicFilter !== 'all') {
            url += `&clinic_id=${currentClinicFilter}`;
        }

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    clinics = data.clinics || [];
                    availabilities = data.availability || {};
                    appointments = data.appointments || {};

                    // Initialize clinic selector if not already present
                    if (!document.getElementById('clinic-selector') && clinics.length > 0) {
                        createClinicSelector();
                    }

                    renderWeekView();
                } else {
                    showError(data.message);
                }
            })
            .catch(error => {
                showError('Failed to load schedule: ' + error.message);
            })
            .finally(() => {
                scheduleContainer.querySelector('.loading-spinner').style.display = 'none';
            });

        updateWeekRangeDisplay();
    }

    function createClinicSelector() {
        const scheduleHeader = document.querySelector('.schedule-header');
        const existingSelector = document.getElementById('clinic-selector');

        if (existingSelector) {
            existingSelector.remove();
        }

        const selectorContainer = document.createElement('div');
        selectorContainer.className = 'clinic-selector-container';

        const label = document.createElement('label');
        label.htmlFor = 'clinic-selector';
        label.textContent = 'Clinic:';

        const select = document.createElement('select');
        select.id = 'clinic-selector';
        select.className = 'clinic-selector';

        // Add "All Clinics" option
        const allOption = document.createElement('option');
        allOption.value = 'all';
        allOption.textContent = 'All Clinics';
        allOption.selected = currentClinicFilter === 'all';
        select.appendChild(allOption);

        // Add clinic options
        clinics.forEach(clinic => {
            const option = document.createElement('option');
            option.value = clinic.clinic_id;
            option.textContent = clinic.name;
            option.selected = currentClinicFilter === clinic.clinic_id.toString();
            select.appendChild(option);
        });

        select.addEventListener('change', function () {
            currentClinicFilter = this.value;
            loadSchedule();
        });

        selectorContainer.appendChild(label);
        selectorContainer.appendChild(select);
        scheduleHeader.insertBefore(selectorContainer, scheduleHeader.querySelector('.schedule-actions'));
    }

    function renderWeekView() {
        weekGrid.innerHTML = '';

        for (let i = 0; i < 7; i++) {
            const currentDate = new Date(currentWeekStart);
            currentDate.setDate(currentDate.getDate() + i);
            const dateStr = formatDate(currentDate);

            const dayColumn = document.createElement('div');
            dayColumn.className = 'day-column';

            const dayDate = document.createElement('div');
            dayDate.className = 'day-date';
            dayDate.textContent = currentDate.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });

            const dayEvents = document.createElement('div');
            dayEvents.className = 'day-events';

            // Process availabilities for this day
            const allAvailabilities = [];
            for (const clinicId in availabilities) {
                const clinicAvailabilities = availabilities[clinicId].filter(a => a.date === dateStr);
                allAvailabilities.push(...clinicAvailabilities.map(a => ({ ...a, isAvailability: true })));
            }

            // Process appointments for this day
            const allAppointments = [];
            for (const clinicId in appointments) {
                const clinicAppointments = appointments[clinicId].filter(a => a.appointment_date === dateStr);
                allAppointments.push(...clinicAppointments.map(a => ({ ...a, isAvailability: false })));
            }

            // Combine and sort by time
            const allEvents = [...allAvailabilities, ...allAppointments].sort((a, b) => {
                const timeA = a.isAvailability ? a.start_time : a.appointment_time;
                const timeB = b.isAvailability ? b.start_time : b.appointment_time;
                return timeA.localeCompare(timeB);
            });

            // Group by clinic for display
            const eventsByClinic = {};
            allEvents.forEach(event => {
                const clinicId = event.clinic_id || 'none';
                if (!eventsByClinic[clinicId]) {
                    eventsByClinic[clinicId] = [];
                }
                eventsByClinic[clinicId].push(event);
            });

            // Render events by clinic
            for (const clinicId in eventsByClinic) {
                const clinicEvents = eventsByClinic[clinicId];
                if (clinicEvents.length > 0) {
                    // Add clinic header
                    const clinicHeader = document.createElement('div');
                    clinicHeader.className = 'clinic-header';

                    // Find clinic name
                    const clinic = clinics.find(c => c.clinic_id == clinicId);
                    clinicHeader.textContent = clinic ? clinic.name : 'General Availability';

                    dayEvents.appendChild(clinicHeader);

                    // Add events
                    clinicEvents.forEach(event => {
                        if (event.isAvailability) {
                            // Availability item
                            const availabilityItem = document.createElement('div');
                            availabilityItem.className = `availability-item ${event.status}`;
                            availabilityItem.dataset.id = event.availability_id;

                            const timeElement = document.createElement('div');
                            timeElement.className = 'availability-time';
                            timeElement.textContent = `${formatTime(event.start_time)} - ${formatTime(event.end_time)}`;

                            availabilityItem.appendChild(timeElement);
                            availabilityItem.addEventListener('click', () => openEditAvailabilityModal(event));

                            dayEvents.appendChild(availabilityItem);
                        } else {
                            // Appointment item
                            const appointmentItem = document.createElement('div');
                            appointmentItem.className = 'appointment-item';

                            const timeElement = document.createElement('div');
                            timeElement.className = 'appointment-time';
                            timeElement.textContent = `${formatTime(event.appointment_time)} - ${event.patient_name}`;

                            appointmentItem.appendChild(timeElement);
                            dayEvents.appendChild(appointmentItem);
                        }
                    });
                }
            }

            dayColumn.appendChild(dayDate);
            dayColumn.appendChild(dayEvents);
            weekGrid.appendChild(dayColumn);
        }
    }


    function filterScheduleItems(searchTerm) {
        if (!searchTerm) {
            // If search is empty, show all items normally
            const allItems = weekGrid.querySelectorAll('.availability-item, .appointment-item');
            allItems.forEach(item => {
                item.style.display = 'flex';
            });
            return;
        }

        const allItems = weekGrid.querySelectorAll('.availability-item, .appointment-item');
        allItems.forEach(item => {
            const textContent = item.textContent.toLowerCase();
            if (textContent.includes(searchTerm)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    function openAddAvailabilityModal() {
        // Reset form
        availabilityForm.reset();
        repeatOption.value = 'none';
        repeatEndContainer.style.display = 'none';
        availabilityDatePicker.setDate(new Date());

        // Add clinic selector to modal if not already present
        if (!document.getElementById('availability-clinic')) {
            const formGroup = document.createElement('div');
            formGroup.className = 'form-group';

            const label = document.createElement('label');
            label.setAttribute('for', 'availability-clinic');
            label.textContent = 'Clinic';

            const select = document.createElement('select');
            select.id = 'availability-clinic';
            select.className = 'form-control';
            select.required = true;

            // Add clinic options
            clinics.forEach(clinic => {
                const option = document.createElement('option');
                option.value = clinic.clinic_id;
                option.textContent = clinic.name;
                select.appendChild(option);
            });

            formGroup.appendChild(label);
            formGroup.appendChild(select);
            availabilityForm.insertBefore(formGroup, availabilityForm.firstChild);
        }

        availabilityModal.style.display = 'flex';
    }

    function openEditAvailabilityModal(availability) {
        // Set form values
        document.getElementById('edit-availability-id').value = availability.availability_id;
        editAvailabilityDatePicker.setDate(availability.date);
        editStartTimePicker.setDate(availability.start_time, true, 'H:i');
        editEndTimePicker.setDate(availability.end_time, true, 'H:i');

        // Set status radio button
        if (availability.status === 'available') {
            document.getElementById('edit-status-available').checked = true;
        } else {
            document.getElementById('edit-status-unavailable').checked = true;
        }

        // Add clinic selector to edit modal if not already present
        if (!document.getElementById('edit-availability-clinic')) {
            const formGroup = document.createElement('div');
            formGroup.className = 'form-group';

            const label = document.createElement('label');
            label.setAttribute('for', 'edit-availability-clinic');
            label.textContent = 'Clinic';

            const select = document.createElement('select');
            select.id = 'edit-availability-clinic';
            select.className = 'form-control';
            select.required = true;

            // Add clinic options
            clinics.forEach(clinic => {
                const option = document.createElement('option');
                option.value = clinic.clinic_id;
                option.textContent = clinic.name;
                if (clinic.clinic_id == availability.clinic_id) {
                    option.selected = true;
                }
                select.appendChild(option);
            });

            formGroup.appendChild(label);
            formGroup.appendChild(select);
            editAvailabilityForm.insertBefore(formGroup, editAvailabilityForm.firstChild);
        } else {
            // Update existing selector
            document.getElementById('edit-availability-clinic').value = availability.clinic_id;
        }

        editAvailabilityModal.style.display = 'flex';
    }

    function closeAllModals() {
        availabilityModal.style.display = 'none';
        editAvailabilityModal.style.display = 'none';
    }

    function saveAvailability(e) {
        e.preventDefault();

        const formData = {
            date: document.getElementById('availability-date').value,
            start_time: document.getElementById('start-time').value,
            end_time: document.getElementById('end-time').value,
            clinic_id: document.getElementById('availability-clinic').value,
            status: 'available',
            repeat_pattern: repeatOption.value,
            end_date: document.getElementById('repeat-end-date').value
        };

        if (!formData.date || !formData.start_time || !formData.end_time || !formData.clinic_id) {
            alert('Please fill in all required fields');
            return;
        }

        let url = 'schedule.php';
        let method = 'POST';

        if (formData.repeat_pattern !== 'none') {
            if (!formData.end_date) {
                alert('Please specify an end date for the recurring availability');
                return;
            }

            formData.start_date = formData.date;
            delete formData.date;
            url += '?recurring=true';
        }

        fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    closeAllModals();
                    loadSchedule();
                    showSuccess(data.message || 'Availability saved successfully');
                } else {
                    showError(data.message || 'Error saving availability');
                }
            })
            .catch(err => {
                showError('Error: ' + err.message);
            });
    }

    function updateAvailability(e) {
        e.preventDefault();

        const formData = {
            availability_id: document.getElementById('edit-availability-id').value,
            date: document.getElementById('edit-availability-date').value,
            start_time: document.getElementById('edit-start-time').value,
            end_time: document.getElementById('edit-end-time').value,
            clinic_id: document.getElementById('edit-availability-clinic').value,
            status: document.querySelector('input[name="edit-status"]:checked').value
        };

        if (!formData.date || !formData.start_time || !formData.end_time || !formData.clinic_id) {
            alert('Please fill in all required fields');
            return;
        }

        fetch('schedule.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    closeAllModals();
                    loadSchedule();
                    showSuccess(data.message || 'Availability updated successfully');
                } else {
                    showError(data.message || 'Error updating availability');
                }
            })
            .catch(error => {
                showError('Error: ' + error.message);
            });
    }

    function deleteAvailability() {
        const availabilityId = document.getElementById('edit-availability-id').value;

        if (!availabilityId) {
            alert('No availability selected to delete');
            return;
        }

        if (!confirm('Are you sure you want to delete this availability?')) {
            return;
        }

        fetch(`schedule.php?id=${availabilityId}`, {
            method: 'DELETE'
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    closeAllModals();
                    loadSchedule();
                    showSuccess(data.message || 'Availability deleted successfully');
                } else {
                    showError(data.message || 'Error deleting availability');
                }
            })
            .catch(error => {
                showError('Error: ' + error.message);
            });
    }

    function showError(message) {
        const errorElement = document.createElement('div');
        errorElement.className = 'alert alert-error';
        errorElement.textContent = message;

        // Remove any existing alerts first
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => alert.remove());

        scheduleContainer.insertBefore(errorElement, scheduleContainer.firstChild);

        setTimeout(() => {
            errorElement.remove();
        }, 5000);
    }

    function showSuccess(message) {
        const successElement = document.createElement('div');
        successElement.className = 'alert alert-success';
        successElement.textContent = message;

        // Remove any existing alerts first
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => alert.remove());

        scheduleContainer.insertBefore(successElement, scheduleContainer.firstChild);

        setTimeout(() => {
            successElement.remove();
        }, 3000);
    }
});

// Helper function to format time for display
function formatTimeForDisplay(timeStr) {
    if (!timeStr) return '';
    const [hours, minutes] = timeStr.split(':');
    const hour = parseInt(hours) % 12 || 12;
    const ampm = parseInt(hours) < 12 ? 'AM' : 'PM';
    return `${hour}:${minutes} ${ampm}`;
}

// Helper function to check if two time ranges overlap
function checkTimeOverlap(start1, end1, start2, end2) {
    return (start1 < end2) && (end1 > start2);
}

// Helper function to validate time range
function validateTimeRange(startTime, endTime) {
    if (startTime >= endTime) {
        alert('End time must be after start time');
        return false;
    }
    return true;
}
function loadMonthView() {
    const monthGrid = document.getElementById('month-grid');
    if (!monthGrid) {
        console.error("month-grid element not found.");
        return;
    }


    // Remove any existing month header
    const existingHeader = monthGrid.parentNode.querySelector('.month-name');
    if (existingHeader) {
        existingHeader.remove();
    }

    monthGrid.innerHTML = '';

    const year = currentMonthDate.getFullYear();
    const month = currentMonthDate.getMonth();

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startingDay = firstDay.getDay(); // 0 (Sunday) to 6 (Saturday)

    // Create and insert month header
    const monthHeader = document.createElement('div');
    monthHeader.className = 'month-name';
    monthHeader.textContent = firstDay.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    monthGrid.parentNode.insertBefore(monthHeader, monthGrid);

    // Empty cells before the first day
    for (let i = 0; i < startingDay; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'month-day empty';
        monthGrid.appendChild(emptyDay);
    }

    // Prepare date range
    const startDate = `${year}-${String(month + 1).padStart(2, '0')}-01`;
    const endDate = `${year}-${String(month + 1).padStart(2, '0')}-${String(daysInMonth).padStart(2, '0')}`;

    // Fetch data and render day cells
    fetch(`schedule.php?start_date=${startDate}&end_date=${endDate}&view=month`)
        .then(response => response.json())
        .then(data => {
            const dayStats = data.day_stats || {};

            for (let day = 1; day <= daysInMonth; day++) {
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const dayElement = document.createElement('div');
                dayElement.className = 'month-day';
                dayElement.textContent = day;

                // Highlight today
                const today = new Date();
                if (
                    day === today.getDate() &&
                    month === today.getMonth() &&
                    year === today.getFullYear()
                ) {
                    dayElement.classList.add('today');
                }

                const stats = dayStats[dateStr];
                if (stats) {
                    const available_minutes = Number(stats.available_minutes || 0);
                    const booked_minutes = Number(stats.booked_minutes || 0);
                    console.log(dateStr, 'Available:', available_minutes, 'Booked:', booked_minutes);
                    if (available_minutes === 0) {
                        dayElement.classList.add('yellow'); // No availability
                    }
                    else if (booked_minutes === 0) {
                        dayElement.classList.add('blue'); // Available, no bookings
                    }
                    else if (booked_minutes === available_minutes) {
                        dayElement.classList.add('red'); // Fully booked
                    }
                    else if (booked_minutes < available_minutes) {
                        dayElement.classList.add('green'); // Partial booking
                    }

                } else {
                    dayElement.classList.add('yellow'); // No availability
                }

                monthGrid.appendChild(dayElement);
            }
        })
        .catch(error => {
            console.error('Error loading month view:', error);
        });
}


// Update the toggle view button event listener to use the new function

const toggleViewBtn = document.getElementById('toggle-view-btn');
const weekView = document.getElementById('week-view');
const monthView = document.getElementById('month-view');

toggleViewBtn.addEventListener('click', () => {
    const isMonthView = monthView.style.display === 'block';

    if (isMonthView) {
        monthView.style.display = 'none';
        weekView.style.display = 'block';
        toggleViewBtn.innerHTML = `<i class="fas fa-calendar"></i> Month View`;
    } else {
        weekView.style.display = 'none';
        monthView.style.display = 'block';
        toggleViewBtn.innerHTML = `<i class="fas fa-calendar-week"></i> Week View`;
        loadMonthView();
    }
});

