document.addEventListener('DOMContentLoaded', function () {
    // Mobile menu toggle
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const sidebar = document.querySelector('.hidden.md\\:flex');

    if (mobileMenuButton && sidebar) {
        mobileMenuButton.addEventListener('click', function () {
            sidebar.classList.toggle('hidden');
            sidebar.classList.toggle('mobile-menu-visible');
        });
    }
    // Initialize DataTable
    $('#assignmentsTable').DataTable({
        responsive: true,
        pageLength: 3,  // This sets the default page length to 3
        lengthChange: false,  // This hides the page length selection dropdown
        order: [[3, 'desc']],
        language: {
            paginate: {
                previous: "<i class='fas fa-chevron-left'></i>",
                next: "<i class='fas fa-chevron-right'></i>"
            }
        },
        drawCallback: function () {
            $('.paginate_button.previous').addClass('bg-gray-200 hover:bg-gray-300 text-gray-600 px-3 py-1 rounded-l');
            $('.paginate_button.next').addClass('bg-gray-200 hover:bg-gray-300 text-gray-600 px-3 py-1 rounded-r');
            $('.paginate_button.current').addClass('bg-blue-500 text-white px-3 py-1');
            $('.paginate_button:not(.previous):not(.next):not(.current)').addClass('bg-gray-200 hover:bg-gray-300 text-gray-600 px-3 py-1');
        }
    });
    // Map functionality




    // Load initial data
    loadStats();
    loadClinics();
    loadItAdmins();
    loadAssignments();

    // Form submission handlers
    document.getElementById('addClinicForm').addEventListener('submit', addClinic);
    document.getElementById('assignItAdminForm').addEventListener('submit', assignItAdmin);
    document.getElementById('unassignForm').addEventListener('submit', unassignItAdmin);
});

function loadStats() {
    fetch('clinics.php?action=get_stats')
        .then(response => response.json())
        .then(data => {
            document.getElementById('totalClinics').textContent = data.total_clinics;
            document.getElementById('assignedClinics').textContent = data.assigned_clinics;
            document.getElementById('unassignedClinics').textContent = data.unassigned_clinics;
            document.getElementById('activeItAdmins').textContent = data.active_it_admins;
        })
        .catch(error => console.error('Error loading stats:', error));
}

function loadClinics() {
    fetch('clinics.php?action=get_unassigned_clinics')
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('clinic_id');
            select.innerHTML = '<option value="">-- Select Clinic --</option>';

            data.forEach(clinic => {
                const option = document.createElement('option');
                option.value = clinic.clinic_id;
                option.textContent = `${clinic.name} (${clinic.location})`;
                select.appendChild(option);
            });
        })
        .catch(error => console.error('Error loading clinics:', error));
}

function loadItAdmins() {
    fetch('clinics.php?action=get_it_admins')
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('it_admin_id');
            select.innerHTML = '<option value="">-- Select IT Admin --</option>';

            data.forEach(admin => {
                const option = document.createElement('option');
                option.value = admin.user_id;
                option.textContent = `${admin.name} (${admin.email})`;
                select.appendChild(option);
            });
        })
        .catch(error => console.error('Error loading IT admins:', error));
}

function loadAssignments() {
    fetch('clinics.php?action=get_assignments')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('assignmentsTableBody');
            tbody.innerHTML = '';

            if (data.length === 0) {
                const row = document.createElement('tr');
                row.innerHTML = `
                      <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                          No assignments found
                      </td>
                  `;
                tbody.appendChild(row);
                return;
            }

            data.forEach(assignment => {
                const row = document.createElement('tr');

                row.innerHTML = `
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${assignment.clinic_name}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${assignment.admin_name}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${assignment.email}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${new Date(assignment.assigned_at).toLocaleString()}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          <button onclick="confirmUnassign(${assignment.clinic_id}, ${assignment.user_id})" class="text-red-600 hover:text-red-900">
                              <i class="fas fa-user-minus"></i> Unassign
                          </button>
                      </td>
                  `;

                tbody.appendChild(row);
            });

            // Reinitialize DataTable after loading new data
            $('#assignmentsTable').DataTable().destroy();
            $('#assignmentsTable').DataTable({
                responsive: true,
                pageLength: 3,
                lengthChange: false,
                order: [[3, 'desc']],
                language: {
                    paginate: {
                        previous: "<i class='fas fa-chevron-left'></i>",
                        next: "<i class='fas fa-chevron-right'></i>"
                    }
                },
                drawCallback: function () {
                    $('.paginate_button.previous').addClass('bg-gray-200 hover:bg-gray-300 text-gray-600 px-3 py-1 rounded-l');
                    $('.paginate_button.next').addClass('bg-gray-200 hover:bg-gray-300 text-gray-600 px-3 py-1 rounded-r');
                    $('.paginate_button.current').addClass('bg-blue-500 text-white px-3 py-1');
                    $('.paginate_button:not(.previous):not(.next):not(.current)').addClass('bg-gray-200 hover:bg-gray-300 text-gray-600 px-3 py-1');
                }
            });
        })
        .catch(error => console.error('Error loading assignments:', error));
}

function addClinic(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    formData.append('action', 'add_clinic');

    fetch('clinics.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Clinic added successfully');
                e.target.reset();
                loadStats();
                loadAssignments();
                loadClinics();
            } else {
                alert('Error adding clinic: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error adding clinic');
        });
}

function assignItAdmin(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    formData.append('action', 'assign_it_admin');

    fetch('clinics.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('IT Admin assigned successfully');
                e.target.reset();
                loadStats();
                loadClinics();
                loadAssignments();
            } else {
                alert('Error assigning IT admin: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error assigning IT admin');
        });
}

function confirmUnassign(clinicId, itAdminId) {
    document.getElementById('modalClinicId').value = clinicId;
    document.getElementById('modalItAdminId').value = itAdminId;
    document.getElementById('unassignModal').classList.remove('hidden');
}

function unassignItAdmin(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    formData.append('action', 'unassign_it_admin');

    fetch('clinics.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('IT Admin unassigned successfully');
                document.getElementById('unassignModal').classList.add('hidden');
                loadStats();
                loadClinics();
                loadAssignments();
            } else {
                alert('Error unassigning IT admin: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error unassigning IT admin');
        });
}
let map;
let marker;
let selectedCoordinates = null;
function placeMarker(location) {
    if (marker) {
        marker.setMap(null);
    }

    marker = new google.maps.Marker({
        position: location,
        map: map,
        draggable: true
    });

    selectedCoordinates = {
        lat: location.lat(),
        lng: location.lng()
    };

    // Update the coordinates display when marker is dragged
    marker.addListener('dragend', (e) => {
        selectedCoordinates = {
            lat: e.latLng.lat(),
            lng: e.latLng.lng()
        };
    });
}

document.getElementById('confirmLocationBtn').addEventListener('click', function () {
    if (selectedCoordinates) {
        document.getElementById('map_coordinates').value =
            `${selectedCoordinates.lat.toFixed(6)},${selectedCoordinates.lng.toFixed(6)}`;
        document.getElementById('mapModal').classList.add('hidden');
    }
});


document.getElementById('openMapBtn').addEventListener('click', function () {
    document.getElementById('mapModal').classList.remove('hidden');
    initMap();
});

function initMap() {
    // Initialize the map
    map = new google.maps.Map(document.getElementById('map'), {
        center: { lat: 0, lng: 0 }, // Default center
        zoom: 2
    });

    // Try to get user's current location
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const userLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };
                map.setCenter(userLocation);
                map.setZoom(14);

                // If we have existing coordinates, place a marker there
                const coordsInput = document.getElementById('map_coordinates').value;
                if (coordsInput) {
                    const [lat, lng] = coordsInput.split(',').map(Number);
                    if (!isNaN(lat) && !isNaN(lng)) {
                        placeMarker({ lat, lng });
                    }
                }
            },
            (error) => {
                console.error("Error getting location: ", error);
                // Default to a reasonable location if geolocation fails
                map.setCenter({ lat: 20, lng: 0 });
                map.setZoom(2);
            }
        );
    }

    // Add click listener to the map
    map.addListener('click', (e) => {
        placeMarker(e.latLng);
    });

    // Also allow searching for locations
    const searchBox = new google.maps.places.SearchBox(document.createElement('input'));
    map.controls[google.maps.ControlPosition.TOP_LEFT].push(searchBox);
    searchBox.addListener('places_changed', () => {
        const places = searchBox.getPlaces();
        if (places.length === 0) return;

        const place = places[0];
        if (place.geometry) {
            map.setCenter(place.geometry.location);
            placeMarker(place.geometry.location);
        }
    });
}