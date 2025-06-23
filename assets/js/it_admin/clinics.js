let googleMapsLoaded = false;

// Make initGoogleMaps a global function
function initGoogleMaps() {
    googleMapsLoaded = true;
    // You might want to call initMap() here if you want the map to load immediately upon API readiness
    // without waiting for the "open map" button click.
}

document.addEventListener('DOMContentLoaded', function () {
    // DOM Elements
    const clinicsTable = document.getElementById('clinics-table');
    const clinicSearch = document.getElementById('clinic-search');
    const searchBtn = document.getElementById('search-btn');
    const clinicModal = document.getElementById('clinic-modal');
    const closeBtn = document.querySelector('.close-btn'); // Close button for the main clinic modal
    const cancelBtn = document.getElementById('cancel-clinic'); // Cancel button for the main clinic modal form
    const clinicForm = document.getElementById('clinic-form');
    const prevPageBtn = document.getElementById('prev-page');
    const nextPageBtn = document.getElementById('next-page');
    const pageInfo = document.getElementById('page-info');

    // Map specific DOM elements
    const mapModal = document.getElementById('mapModal'); // The map selection modal
    const openMapBtn = document.getElementById('openMapBtn'); // Button to open the map modal
    const confirmLocationBtn = document.getElementById('confirmLocationBtn'); // Button to confirm location in map modal
    const clinicCoordinatesInput = document.getElementById('clinic-coordinates'); // Input field for coordinates

    // State variables
    let currentPage = 1;
    let totalPages = 1;
    let currentSearch = '';

    // Map functionality
    let map;
    let marker;
    let selectedCoordinates = null;

    /**
     * Places a marker on the map at the given location.
     * Handles both google.maps.LatLng objects and plain {lat, lng} objects.
     * @param {object} location - The location object, either LatLng or {lat: number, lng: number}.
     */
    async function placeMarker(location) {
        if (marker) {
            marker.setMap(null); // Remove existing marker
        }

        const { AdvancedMarkerElement } = await google.maps.importLibrary("marker");

        let latValue;
        let lngValue;

        // Determine actual lat and lng values based on the type of 'location'
        if (location && typeof location.lat === 'function' && typeof location.lng === 'function') {
            // It's a google.maps.LatLng object (e.g., from map click or Places API)
            latValue = location.lat();
            lngValue = location.lng();
        } else if (location && typeof location.lat === 'number' && typeof location.lng === 'number') {
            // It's a plain object {lat: number, lng: number} (e.g., from database load)
            latValue = location.lat;
            lngValue = location.lng;
        } else {
            console.error("Invalid location object passed to placeMarker:", location);
            return; // Exit if location is not in expected format
        }

        const position = { lat: latValue, lng: lngValue };

        marker = new AdvancedMarkerElement({
            position: position,
            map: map,
            gmpDraggable: true // Allow marker to be dragged
        });

        selectedCoordinates = {
            lat: latValue, // Use the extracted values
            lng: lngValue  // Use the extracted values
        };

        // Update the coordinates display when marker is dragged
        marker.addListener('dragend', (e) => {
            selectedCoordinates = {
                lat: e.latLng.lat(),
                lng: e.latLng.lng()
            };
            // Optionally update the input field in real-time as marker is dragged
            // clinicCoordinatesInput.value = `${selectedCoordinates.lat.toFixed(6)},${selectedCoordinates.lng.toFixed(6)}`;
        });
    }

    /**
     * Initializes the Google Map within the map modal.
     */
    function initMap() {
        if (!googleMapsLoaded) {
            console.error('Google Maps API not loaded yet');
            return;
        }

        // Initialize the map if it hasn't been initialized before
        if (!map) {
            map = new google.maps.Map(document.getElementById('map'), {
                center: { lat: 0, lng: 0 }, // Default center
                zoom: 2,
                mapId: '5bc374fd3e9a0259bfa55dab' // Your actual Map ID
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
                        map.setZoom(14); // Zoom in on user's location

                        // If we have existing coordinates from the clinic, place a marker there
                        const coordsInput = clinicCoordinatesInput.value;
                        if (coordsInput) {
                            // Attempt to parse existing coordinates (e.g., "33.48664° N, 35.79563° E" or "lat,lng")
                            const parts = coordsInput.match(/(-?\d+\.?\d*)°?\s*[NS],\s*(-?\d+\.?\d*)°?\s*[EW]/);
                            let lat, lng;
                            if (parts && parts.length === 3) {
                                lat = parseFloat(parts[1]);
                                lng = parseFloat(parts[2]);
                            } else {
                                // Fallback for simple "lat,lng" format
                                [lat, lng] = coordsInput.split(',').map(Number);
                            }

                            if (!isNaN(lat) && !isNaN(lng)) {
                                placeMarker({ lat, lng }); // Pass as plain object
                            }
                        }
                    },
                    (error) => {
                        console.error("Error getting user location:", error);
                        // Default to a reasonable location if geolocation fails
                        map.setCenter({ lat: 20, lng: 0 }); // Global view
                        map.setZoom(2);
                    }
                );
            } else {
                // Browser doesn't support Geolocation
                console.warn("Geolocation is not supported by this browser.");
                map.setCenter({ lat: 20, lng: 0 });
                map.setZoom(2);
            }

            // Add click listener to the map to place/move marker
            map.addListener('click', (e) => {
                placeMarker(e.latLng); // e.latLng is a google.maps.LatLng object
            });

            // Handle search input (SearchBox is deprecated for new customers)
            const searchInput = document.getElementById('map-search-input');
            if (searchInput) {
                // The google.maps.places.SearchBox is deprecated for new customers as of March 1st, 2025.
                // This code block is commented out to prevent errors.
                // If search functionality is critical, consider implementing AutocompleteService
                // or PlacesService.findPlaceFromQuery for new projects.
                /*
                const searchBox = new google.maps.places.SearchBox(searchInput);
                map.controls[google.maps.ControlPosition.TOP_LEFT].push(searchInput); // Add search input to map controls

                map.addListener('bounds_changed', () => {
                    searchBox.setBounds(map.getBounds());
                });

                searchBox.addListener('places_changed', () => {
                    const places = searchBox.getPlaces();
                    if (places.length === 0) {
                        console.warn("No places found for search query.");
                        return;
                    }

                    const place = places[0];
                    if (place.geometry && place.geometry.location) {
                        map.setCenter(place.geometry.location);
                        placeMarker(place.geometry.location);
                    } else {
                        console.error('Place has no geometry or location:', place);
                    }
                });
                */
                console.warn("Map search functionality (google.maps.places.SearchBox) is currently disabled due to API availability for new customers. Please refer to Google Maps Platform documentation for alternatives if needed.");
                // Optionally hide the search input if functionality is disabled
                searchInput.style.display = 'none';
            } else {
                console.warn("Map search input element with ID 'map-search-input' not found. Please ensure it's in clinics.html.");
            }
        }

        // Ensure the map resizes and recenters when the modal becomes visible
        google.maps.event.trigger(map, 'resize');
        if (selectedCoordinates) {
            map.setCenter(selectedCoordinates);
        } else {
            // If no coordinates selected, center on a default or current user location
            if (navigator.geolocation && map.getCenter().lat() === 0 && map.getCenter().lng() === 0) {
                // Only try to get location if map is still at default center
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        map.setCenter({ lat: position.coords.latitude, lng: position.coords.longitude });
                        map.setZoom(14);
                    },
                    () => {
                        map.setCenter({ lat: 20, lng: 0 }); // Fallback if geolocation fails
                        map.setZoom(2);
                    }
                );
            }
        }
    }


    // Event listeners for main clinic table and modal
    searchBtn.addEventListener('click', () => {
        currentSearch = clinicSearch.value;
        currentPage = 1;
        loadClinics();
    });

    clinicSearch.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            currentSearch = clinicSearch.value;
            currentPage = 1;
            loadClinics();
        }
    });

    // Close main clinic modal
    closeBtn.addEventListener('click', () => {
        clinicModal.style.display = 'none';
    });

    // Cancel main clinic modal form
    cancelBtn.addEventListener('click', () => {
        clinicModal.style.display = 'none';
    });

    // Close modal if clicking outside
    window.addEventListener('click', (e) => {
        if (e.target === clinicModal) {
            clinicModal.style.display = 'none';
        }
        if (e.target === mapModal) { // Also close map modal if clicking outside
            mapModal.classList.add('hidden');
        }
    });

    prevPageBtn.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            loadClinics();
        }
    });

    nextPageBtn.addEventListener('click', () => {
        if (currentPage < totalPages) {
            currentPage++;
            loadClinics();
        }
    });

    clinicForm.addEventListener('submit', (e) => {
        e.preventDefault(); // Prevent default form submission
        updateClinic();
    });

    // Event listeners for map modal buttons
    openMapBtn.addEventListener('click', () => {
        mapModal.classList.remove('hidden'); // Show the map modal
        initMap(); // Initialize or re-center the map
    });

    confirmLocationBtn.addEventListener('click', () => {
        if (selectedCoordinates) {
            // Update the main form's coordinates input
            clinicCoordinatesInput.value =
                `${selectedCoordinates.lat.toFixed(6)},${selectedCoordinates.lng.toFixed(6)}`;
        }
        mapModal.classList.add('hidden'); // Hide the map modal
    });


    // Functions
    function loadClinics() {
        const tbody = clinicsTable.querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="5" class="loading"><div class="spinner"></div> Loading assigned clinics...</td></tr>';

        let url = `clinics.php?action=get_assigned_clinics&page=${currentPage}`;
        if (currentSearch) {
            url += `&search=${encodeURIComponent(currentSearch)}`;
        }

        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                totalPages = data.totalPages;
                updatePagination();

                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="no-data">No assigned clinics found</td></tr>';
                    return;
                }

                tbody.innerHTML = '';
                data.data.forEach(clinic => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${clinic.clinic_id}</td>
                        <td>${clinic.name}</td>
                        <td>${clinic.location}</td>
                        <td>${clinic.phone_number || '-'}</td>
                        <td class="actions">
                            <button class="edit-btn px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 transition duration-150 ease-in-out" data-id="${clinic.clinic_id}">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });

                // Add event listeners to edit buttons
                document.querySelectorAll('.edit-btn').forEach(btn => {
                    btn.addEventListener('click', () => editClinic(btn.dataset.id));
                });
            })
            .catch(error => {
                console.error('Error loading clinics:', error);
                tbody.innerHTML = '<tr><td colspan="5" class="error">Failed to load clinics. Please try again.</td></tr>';
            });
    }

    function updatePagination() {
        pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
        prevPageBtn.disabled = currentPage <= 1;
        nextPageBtn.disabled = currentPage >= totalPages;
    }

    function editClinic(clinicId) {
        fetch(`clinics.php?action=get_clinic&id=${clinicId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(clinic => {
                document.getElementById('clinic-id').value = clinic.clinic_id;
                document.getElementById('clinic-name').value = clinic.name;
                document.getElementById('clinic-location').value = clinic.location;
                document.getElementById('clinic-phone').value = clinic.phone_number || '';
                clinicCoordinatesInput.value = clinic.map_coordinates || ''; // Use the correct input element

                // Reset selectedCoordinates for the map when opening for edit
                if (clinic.map_coordinates) {
                    const parts = clinic.map_coordinates.match(/(-?\d+\.?\d*)°?\s*[NS],\s*(-?\d+\.?\d*)°?\s*[EW]/);
                    let lat, lng;
                    if (parts && parts.length === 3) {
                        lat = parseFloat(parts[1]);
                        lng = parseFloat(parts[2]);
                    } else {
                        [lat, lng] = clinic.map_coordinates.split(',').map(Number);
                    }
                    if (!isNaN(lat) && !isNaN(lng)) {
                        selectedCoordinates = { lat, lng };
                    } else {
                        selectedCoordinates = null;
                    }
                } else {
                    selectedCoordinates = null;
                }

                clinicModal.style.display = 'block'; // Show the main clinic edit modal
            })
            .catch(error => {
                console.error('Error loading clinic for edit:', error);
                alert('Failed to load clinic data for editing. Please try again.');
            });
    }

    function updateClinic() {
        const submitBtn = clinicForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<div class="spinner small"></div> Saving...'; // Show spinner

        const clinicId = document.getElementById('clinic-id').value;
        const name = document.getElementById('clinic-name').value;
        const location = document.getElementById('clinic-location').value;
        const phone = document.getElementById('clinic-phone').value;
        const clinic_phone_code = document.getElementById('clinic-phone-cc').value; // Get phone code if needed

        const coordinates = clinicCoordinatesInput.value; // Get value from the correct input

        const data = {
            clinic_id: clinicId,
            name,
            location,
            phone: phone,
            phone_cc: clinic_phone_code, // Include phone code if needed
            coordinates
        };

        fetch('clinics.php?action=update_clinic', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    clinicModal.style.display = 'none'; // Hide modal on success
                    loadClinics(); // Reload clinics to show updated data
                    alert('Clinic updated successfully!'); // User feedback
                } else {
                    alert('Error updating clinic: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error updating clinic:', error);
                alert('Failed to update clinic. Please check console for details.');
            })
            .finally(() => {
                submitBtn.disabled = false; // Re-enable button
                submitBtn.textContent = originalText; // Restore button text
            });
    }
    // Initial load of clinics when the page loads
    loadClinics();
});
