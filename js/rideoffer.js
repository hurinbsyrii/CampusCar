document.addEventListener("DOMContentLoaded", function () {
    const rideOfferForm = document.getElementById("rideOfferForm");
    const submitBtn = document.getElementById("submitBtn");
    
    // Google Maps variables
    let map;
    let directionsService;
    let directionsRenderer;
    let autocompleteFrom;
    let autocompleteTo;
    let fromPlace = null;
    let toPlace = null;
    let routeConfirmed = false;
    let fromMarker = null;
    let toMarker = null;

    // Set minimum date to today
    const today = new Date().toISOString().split("T")[0];
    document.getElementById("rideDate").min = today;

    // Load Google Maps API
    function loadGoogleMapsAPI() {
        // Replace with your actual Google Maps API Key
        const apiKey = 'AIzaSyB8-v0xhcv-9P3CaNexbRLNi0_oXIUu3tE'; // Get from Google Cloud Console
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places&callback=initMap`;
        script.async = true;
        script.defer = true;
        script.onerror = function() {
            showNotification('Failed to load Google Maps. Please check your internet connection.', 'error');
        };
        document.head.appendChild(script);
    }

    // Initialize Google Maps
    window.initMap = function() {
        if (typeof google === 'undefined') {
            showNotification('Google Maps API not loaded. Please refresh the page.', 'error');
            return;
        }

        // Create map centered on Malaysia
        map = new google.maps.Map(document.getElementById("map"), {
            center: { lat: 3.1390, lng: 101.6869 }, // Kuala Lumpur
            zoom: 12,
            mapTypeControl: true,
            streetViewControl: false,
            fullscreenControl: true,
            zoomControl: true,
            mapTypeId: google.maps.MapTypeId.ROADMAP
        });

        directionsService = new google.maps.DirectionsService();
        directionsRenderer = new google.maps.DirectionsRenderer({
            map: map,
            suppressMarkers: true, // We'll add custom markers
            polylineOptions: {
                strokeColor: '#3498db',
                strokeWeight: 5,
                strokeOpacity: 0.8
            },
            preserveViewport: false
        });

        // Initialize autocomplete for From location
        const fromInput = document.getElementById('fromLocation');
        autocompleteFrom = new google.maps.places.Autocomplete(fromInput, {
            fields: ['formatted_address', 'geometry', 'name', 'place_id'],
            types: ['establishment', 'geocode'],
            componentRestrictions: { country: 'my' } // Malaysia only
        });

        // Initialize autocomplete for To location
        const toInput = document.getElementById('toLocation');
        autocompleteTo = new google.maps.places.Autocomplete(toInput, {
            fields: ['formatted_address', 'geometry', 'name', 'place_id'],
            types: ['establishment', 'geocode'],
            componentRestrictions: { country: 'my' } // Malaysia only
        });

        // Listen for place selection - FROM
        autocompleteFrom.addListener('place_changed', () => {
    const place = autocompleteFrom.getPlace();
    if (place.geometry) {
        fromPlace = place;
        document.getElementById('fromLocationLat').value = place.geometry.location.lat();
        document.getElementById('fromLocationLng').value = place.geometry.location.lng();
        
        // Clear previous marker
        if (fromMarker) {
            fromMarker.setMap(null);
        }
        
        // Add custom marker
        fromMarker = new google.maps.Marker({
            position: place.geometry.location,
            map: map,
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 10,
                fillColor: '#2ecc71',
                fillOpacity: 1,
                strokeColor: '#ffffff',
                strokeWeight: 3
            },
            title: place.name || 'From Location',
            animation: google.maps.Animation.DROP
        });
        
        // FIXED: Prioritize place name over formatted address
        let displayText = place.name; // First try to use the place name
        
        if (!displayText && place.formatted_address) {
            // If no name, use formatted address
            displayText = place.formatted_address;
        }
        
        // Update input with the display text
        fromInput.value = displayText || 'Selected location';
        
        // Store both name and address in hidden fields for server
        document.getElementById('fromLocation').dataset.placeName = place.name || '';
        document.getElementById('fromLocation').dataset.placeAddress = place.formatted_address || '';
        
        // Validate the field
        validateField(fromInput);
        
        // If both places are selected, show route
        if (fromPlace && toPlace) {
            calculateAndDisplayRoute();
        }
    } else {
        showNotification('Please select a valid location from the suggestions', 'warning');
    }
});

        // Listen for place selection - TO
        autocompleteTo.addListener('place_changed', () => {
    const place = autocompleteTo.getPlace();
    if (place.geometry) {
        toPlace = place;
        document.getElementById('toLocationLat').value = place.geometry.location.lat();
        document.getElementById('toLocationLng').value = place.geometry.location.lng();
        
        // Clear previous marker
        if (toMarker) {
            toMarker.setMap(null);
        }
        
        // Add custom marker
        toMarker = new google.maps.Marker({
            position: place.geometry.location,
            map: map,
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 10,
                fillColor: '#e74c3c',
                fillOpacity: 1,
                strokeColor: '#ffffff',
                strokeWeight: 3
            },
            title: place.name || 'To Location',
            animation: google.maps.Animation.DROP
        });
        
        // FIXED: Prioritize place name over formatted address
        let displayText = place.name; // First try to use the place name
        
        if (!displayText && place.formatted_address) {
            // If no name, use formatted address
            displayText = place.formatted_address;
        }
        
        // Update input with the display text
        toInput.value = displayText || 'Selected location';
        
        // Store both name and address in hidden fields for server
        document.getElementById('toLocation').dataset.placeName = place.name || '';
        document.getElementById('toLocation').dataset.placeAddress = place.formatted_address || '';
        
        // Validate the field
        validateField(toInput);
        
        // If both places are selected, show route
        if (fromPlace && toPlace) {
            calculateAndDisplayRoute();
        }
    } else {
        showNotification('Please select a valid location from the suggestions', 'warning');
    }
});

        // Style autocomplete dropdowns
        styleAutocompleteDropdowns();
        
        console.log("Google Maps initialized successfully");
    };

    function styleAutocompleteDropdowns() {
        const observer = new MutationObserver(() => {
            const pacContainers = document.querySelectorAll('.pac-container');
            pacContainers.forEach(container => {
                container.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
                container.style.borderRadius = '0 0 8px 8px';
                container.style.border = '1px solid #ddd';
                container.style.borderTop = 'none';
                container.style.zIndex = '1000';
                container.style.marginTop = '2px';
                container.style.maxHeight = '250px';
                container.style.overflowY = 'auto';
                
                // Style individual items
                const items = container.querySelectorAll('.pac-item');
                items.forEach(item => {
                    item.style.padding = '10px 15px';
                    item.style.cursor = 'pointer';
                    item.style.transition = 'background 0.2s';
                    item.style.borderBottom = '1px solid #f0f0f0';
                    item.style.display = 'flex';
                    item.style.alignItems = 'center';
                    
                    item.addEventListener('mouseenter', () => {
                        item.style.background = '#f5f5f5';
                    });
                    
                    item.addEventListener('mouseleave', () => {
                        item.style.background = 'white';
                    });
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    function calculateAndDisplayRoute() {
        if (!fromPlace || !toPlace) return;

        // Show map container with animation
        const mapContainer = document.getElementById('mapPreviewContainer');
        mapContainer.style.display = 'block';
        setTimeout(() => {
            mapContainer.style.opacity = '1';
            mapContainer.style.transform = 'translateY(0)';
        }, 10);

        const request = {
            origin: fromPlace.geometry.location,
            destination: toPlace.geometry.location,
            travelMode: google.maps.TravelMode.DRIVING,
            provideRouteAlternatives: false,
            unitSystem: google.maps.UnitSystem.METRIC
        };

        directionsService.route(request, (result, status) => {
            if (status === 'OK') {
                directionsRenderer.setDirections(result);
                
                // Show route distance and duration
                const route = result.routes[0];
                const leg = route.legs[0];
                
                // Show confirm button
                const confirmBtn = document.getElementById('confirmRoute');
                confirmBtn.style.display = 'block';
                confirmBtn.innerHTML = `<i class="fa-solid fa-check"></i> Confirm Route (${leg.distance.text}, ${leg.duration.text})`;
                confirmBtn.disabled = false;
                confirmBtn.style.background = 'var(--primary-color)';
                
                // Fit map to route with padding
                const bounds = new google.maps.LatLngBounds();
                bounds.extend(fromPlace.geometry.location);
                bounds.extend(toPlace.geometry.location);
                map.fitBounds(bounds, { padding: 50 });
                
                // Show route info
                showNotification(`Route calculated: ${leg.distance.text}, ${leg.duration.text}`, 'info', 3000);
            } else {
                console.error('Directions request failed: ' + status);
                showNotification('Could not calculate route. Please check if locations are reachable.', 'error');
                
                // Still show markers even if route fails
                const bounds = new google.maps.LatLngBounds();
                bounds.extend(fromPlace.geometry.location);
                bounds.extend(toPlace.geometry.location);
                map.fitBounds(bounds, { padding: 50 });
            }
        });
    }

    // Confirm Route Button
    document.getElementById('confirmRoute').addEventListener('click', function() {
        routeConfirmed = true;
        this.innerHTML = '<i class="fa-solid fa-check-circle"></i> Route Confirmed';
        this.style.background = '#2ecc71';
        this.disabled = true;
        showNotification('Route confirmed successfully! You can now submit the form.', 'success');
        
        // Add checkmark animation
        const checkmark = document.createElement('div');
        checkmark.innerHTML = '✓';
        checkmark.style.cssText = `
            position: absolute;
            top: -5px;
            right: -5px;
            background: white;
            color: #2ecc71;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        `;
        this.style.position = 'relative';
        this.appendChild(checkmark);
    });

    // Start loading Google Maps
    loadGoogleMapsAPI();

    // Real-time form validation
    const requiredInputs = rideOfferForm.querySelectorAll("input[required]");
    requiredInputs.forEach((input) => {
        input.addEventListener("input", function () {
            validateField(this);
        });

        input.addEventListener("blur", function () {
            validateField(this);
        });
    });

    // Set minimum time for departure if date is today
    document.getElementById("rideDate").addEventListener("change", function () {
        const selectedDate = new Date(this.value);
        const today = new Date();

        if (selectedDate.toDateString() === today.toDateString()) {
            const now = new Date();
            const currentTime =
                now.getHours().toString().padStart(2, "0") +
                ":" +
                now.getMinutes().toString().padStart(2, "0");
            document.getElementById("departureTime").min = currentTime;

            // Show notification
            showNotification(
                "Please select a departure time later than current time for today's ride.",
                "info",
                5000
            );
        } else {
            document.getElementById("departureTime").removeAttribute("min");
        }

        validateField(this);
        
        // Check for schedule conflicts with existing rides on selected date
        checkScheduleConflicts();
    });

    // Validate time when changed
    document.getElementById("departureTime").addEventListener("change", function () {
        validateField(this);
        checkScheduleConflicts();
    });

    // Form submission
    rideOfferForm.addEventListener("submit", function (e) {
        e.preventDefault();

        if (!validateForm()) {
            showNotification("Please fix the errors before submitting.", "error");
            return;
        }

        // Check if both locations are selected with coordinates
        const fromLat = document.getElementById('fromLocationLat').value;
        const fromLng = document.getElementById('fromLocationLng').value;
        const toLat = document.getElementById('toLocationLat').value;
        const toLng = document.getElementById('toLocationLng').value;
        
        if (!fromLat || !fromLng || !toLat || !toLng) {
            showNotification('Please select valid locations from the suggestions. Click on a suggestion from the dropdown.', 'error');
            return;
        }

        // Optional route confirmation check (uncomment if required)
        /*
        if (!routeConfirmed) {
            showNotification('Please confirm the route on the map before submitting', 'warning');
            // Scroll to map
            document.getElementById('mapPreviewContainer').scrollIntoView({ behavior: 'smooth' });
            return;
        }
        */

        // Final schedule conflict check before submission
        if (hasScheduleConflict()) {
            showNotification(
                "You have a schedule conflict with an existing ride. Please choose a different time.",
                "error",
                5000
            );
            return;
        }

        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML =
            '<i class="fa-solid fa-spinner loading"></i> Offering Ride...';

        // Submit the form
        this.submit();
    });

    // Check for schedule conflicts
    function checkScheduleConflicts() {
        const selectedDate = document.getElementById("rideDate").value;
        const selectedTime = document.getElementById("departureTime").value;
        
        if (!selectedDate || !selectedTime) return;

        const existingRides = document.querySelectorAll('.existing-ride-item:not(.cancelled):not(.completed)');
        
        // You can implement client-side conflict checking here
        // This would require passing existing ride data from PHP to JavaScript
    }

    function hasScheduleConflict() {
        // This function can be extended to check for specific conflicts
        // Currently relying on server-side validation
        return false;
    }

    // Validation functions
    function validateField(field) {
        const value = field.value.trim();
        const errorElement = document.getElementById(field.id + "Error");
        let isValid = true;
        let errorMessage = "";

        switch (field.id) {
            case "fromLocation":
            case "toLocation":
                // Check if coordinates are set (meaning a valid place was selected)
                const latField = field.id === 'fromLocation' ? 'fromLocationLat' : 'toLocationLat';
                const lngField = field.id === 'fromLocation' ? 'fromLocationLng' : 'toLocationLng';
                const hasCoordinates = document.getElementById(latField).value && document.getElementById(lngField).value;
                
                isValid = value.length >= 2 && hasCoordinates;
                if (!hasCoordinates && value.length > 0) {
                    errorMessage = "Please select a location from the suggestions";
                } else if (value.length < 2) {
                    errorMessage = "Location must be at least 2 characters";
                }
                break;

            case "rideDate":
                const selectedDate = new Date(value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                isValid = value && selectedDate >= today;
                errorMessage = isValid ? "" : "Please select a future date";
                break;

            case "departureTime":
                const rideDate = new Date(document.getElementById("rideDate").value);
                const currentDate = new Date();

                if (rideDate.toDateString() === currentDate.toDateString()) {
                    const selectedTime = value.split(":");
                    const selectedDateTime = new Date();
                    selectedDateTime.setHours(
                        parseInt(selectedTime[0]),
                        parseInt(selectedTime[1]),
                        0,
                        0
                    );

                    isValid = selectedDateTime > currentDate;
                    errorMessage = isValid
                        ? ""
                        : "Departure time must be in the future for today's ride";
                } else {
                    isValid = value.length > 0;
                    errorMessage = isValid ? "" : "Departure time is required";
                }
                break;

            case "availableSeats":
                isValid = value >= 1 && value <= 7;
                errorMessage = isValid ? "" : "Seats must be between 1 and 7";
                break;

            case "pricePerSeat":
                isValid = value >= 1;
                errorMessage = isValid ? "" : "Price must be at least RM 1";
                break;
        }

        // Update UI
        if (isValid && value.length > 0) {
            field.classList.remove("error");
            field.classList.add("success");
        } else if (value.length > 0) {
            field.classList.add("error");
            field.classList.remove("success");
        } else {
            field.classList.remove("error", "success");
        }

        if (errorElement) {
            errorElement.textContent = errorMessage;
        }

        return isValid;
    }

    function validateForm() {
        let isValid = true;
        requiredInputs.forEach((field) => {
            if (!validateField(field)) {
                isValid = false;
            }
        });
        
        return isValid;
    }

    function showNotification(message, type = "success", duration = 5000) {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll(".custom-notification");
        existingNotifications.forEach((notification) => notification.remove());

        // Create notification element
        const notification = document.createElement("div");
        notification.className = `custom-notification ${type}`;

        const icons = {
            success: "fa-check",
            error: "fa-exclamation-triangle",
            info: "fa-info-circle",
            warning: "fa-exclamation-circle",
        };

        notification.innerHTML = `
            <i class="fa-solid ${icons[type] || "fa-info-circle"}"></i>
            <span>${message}</span>
            <button class="notification-close"><i class="fa-solid fa-times"></i></button>
        `;

        // Add styles
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#2ecc71' : 
                        type === 'error' ? '#e74c3c' : 
                        type === 'warning' ? '#f39c12' : '#3498db'};
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 9999;
            animation: slideIn 0.3s ease;
            max-width: 400px;
        `;

        // Add close button styles
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.style.cssText = `
            margin-left: auto;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            opacity: 0.8;
            padding: 2px;
        `;
        
        closeBtn.addEventListener('click', () => {
            notification.remove();
        });

        document.body.appendChild(notification);

        // Remove after duration
        setTimeout(() => {
            notification.style.animation = "slideOut 0.3s ease";
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }, duration);
    }

    console.log("Ride offer page loaded with Google Maps integration");
});

// Back function
function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = '../php/userdashboard.php';
    }
}

// Add CSS animations for notifications and map
const style = document.createElement("style");
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    #mapPreviewContainer {
        opacity: 0;
        transform: translateY(10px);
        transition: opacity 0.3s ease, transform 0.3s ease;
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        margin-top: 20px;
    }
    
    #map {
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .autocomplete-container {
        position: relative;
    }
    
    .input-group {
        position: relative;
    }
    
    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .alert-warning {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }
    
    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .alert i {
        margin-right: 10px;
        font-size: 1.2em;
    }
    
    .alert .close {
        margin-left: auto;
        background: none;
        border: none;
        color: inherit;
        cursor: pointer;
        opacity: 0.7;
    }
    
    .alert .close:hover {
        opacity: 1;
    }
    
    .loading {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .btn-small {
        padding: 10px 20px;
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9em;
        transition: all 0.3s;
        font-weight: 500;
    }
    
    .btn-small:hover {
        background: var(--primary-color-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .btn-small:disabled {
        background: #95a5a6;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
`;
document.head.appendChild(style);