// Function to render calendar with AI data
function renderAICalendar(data) {
    // Create calendar container
    const calendarHTML = `
    <div class="ai-calendar">
        <div class="calendar-header">
            <h3>7-Day Availability Calendar</h3>
            <p class="ai-analysis">${data.analysis || ''}</p>
        </div>
        <div class="calendar-grid" id="ai-calendar-grid"></div>
    </div>`;

    $('#availability-prediction').html(calendarHTML);

    // Generate days
    const today = new Date();
    const grid = $('#ai-calendar-grid');

    // Create 7 days (today + next 6 days)
    for (let i = 0; i < 7; i++) {
        const date = new Date(today);
        date.setDate(today.getDate() + i);
        const dateStr = date.toISOString().split('T')[0];
        const dayName = date.toLocaleDateString('en-US', { weekday: 'short' });
        const dayNum = date.getDate();

        // Find appointments for this day
        const dayAppointments = data.appointments?.filter(a => a.date === dateStr) || [];
        const dayAvailability = data.availability?.filter(a => a.date === dateStr) || [];

        // Create day column
        const dayColumn = $(`
        <div class="calendar-day" data-date="${dateStr}">
            <div class="day-header">
                <div class="day-name">${dayName}</div>
                <div class="day-number">${dayNum}</div>
            </div>
            <div class="day-appointments"></div>
            <div class="day-availability"></div>
        </div>`);

        // Add appointments
        const appointmentsContainer = dayColumn.find('.day-appointments');
        dayAppointments.forEach(appt => {
            let sensitivityClass = '';
            if (appt.sensitivity === 'sensitive') sensitivityClass = 'sensitive';
            else if (appt.sensitivity === 'potentially_sensitive') sensitivityClass = 'potentially-sensitive';

            appointmentsContainer.append(`
            <div class="calendar-event appointment ${sensitivityClass}">
                <span class="event-time">${appt.time}</span>
                <span class="event-title">${appt.patient || 'Patient'}</span>
                <span class="event-reason">${appt.reason || ''}</span>
                ${appt.sensitivity === 'sensitive' ? '<i class="fas fa-exclamation-circle"></i>' : ''}
            </div>`);
        });

        // Add availability slots
        const availabilityContainer = dayColumn.find('.day-availability');
        if (dayAvailability.length > 0) {
            dayAvailability.forEach(slot => {
                availabilityContainer.append(`
                <div class="calendar-event available-slot">
                    <span class="event-time">${slot.start_time} - ${slot.end_time}</span>
                    <span class="event-title">Available</span>
                </div>`);
            });
        } else {
            availabilityContainer.append('<div class="calendar-event unavailable">No availability</div>');
        }

        grid.append(dayColumn);
    }
}