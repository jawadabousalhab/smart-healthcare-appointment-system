document.addEventListener('DOMContentLoaded', function () {
    // DOM Elements

    const closeBtn = document.querySelector('.close-btn');


    // Load dashboard data
    loadDashboardData();



    // Function to load dashboard data
    function loadDashboardData() {
        fetch('it_admin_dashboard.php?action=get_stats')
            .then(response => response.json())
            .then(data => {
                // Update counts
                // Update counts
                document.getElementById('clinic-count').textContent = data.counts.clinics;
                document.getElementById('user-count').textContent = data.counts.doctors; // Changed from users to doctors

                document.getElementById('log-count').textContent = data.counts.activity_logs;

                // Update recent activity
                const activityList = document.getElementById('recent-activity-list');
                activityList.innerHTML = '';

                data.recent_activity.forEach(activity => {
                    const activityItem = document.createElement('div');
                    activityItem.className = 'activity-item';
                    activityItem.innerHTML = `
                        <div class="activity-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="activity-details">
                            <h4>${activity.user_name} <span>${activity.action}</span></h4>
                            <p>${activity.description}</p>
                            <small>${new Date(activity.timestamp).toLocaleString()}</small>
                        </div>
                    `;
                    activityList.appendChild(activityItem);
                });
            })
            .catch(error => {
                console.error('Error loading dashboard data:', error);
            });
    }





    // Simulate real-time updates
    setInterval(loadDashboardData, 30000); // Refresh every 30 seconds

    // Initialize any charts or additional UI components
    initializeSystemStatusChart();

    function initializeSystemStatusChart() {
        // In a real application, this would initialize charts using a library like Chart.js
        // For now, we'll just simulate status updates
        setInterval(() => {
            const indicators = document.querySelectorAll('.status-indicator');
            indicators.forEach(indicator => {
                // Randomly change status for demo purposes
                const status = Math.random() > 0.1 ? (Math.random() > 0.3 ? 'good' : 'warning') : 'error';
                indicator.className = 'status-indicator ' + status;
            });
        }, 5000);
    }
});