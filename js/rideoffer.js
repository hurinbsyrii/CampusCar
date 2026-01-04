document.addEventListener("DOMContentLoaded", function () {
  const rideOfferForm = document.getElementById("rideOfferForm");
  const submitBtn = document.getElementById("submitBtn");

  // Set minimum date to today
  const today = new Date().toISOString().split("T")[0];
  document.getElementById("rideDate").min = today;

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
  document
    .getElementById("departureTime")
    .addEventListener("change", function () {
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
    
    existingRides.forEach(ride => {
      // You could implement client-side conflict checking here
      // by parsing the existing ride times from the page
    });
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
        isValid = value.length >= 2;
        errorMessage = isValid ? "" : "Location must be at least 2 characters";
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
    const existingNotifications = document.querySelectorAll(".notification");
    existingNotifications.forEach((notification) => notification.remove());

    // Create notification element
    const notification = document.createElement("div");
    notification.className = `notification ${type}`;

    const icons = {
      success: "fa-check",
      error: "fa-exclamation-triangle",
      info: "fa-info-circle",
      warning: "fa-exclamation-circle",
    };

    notification.innerHTML = `
            <i class="fa-solid ${icons[type] || "fa-info-circle"}"></i>
            <span>${message}</span>
        `;

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

  console.log("Ride offer page loaded with schedule conflict prevention");
});

function goBack() {
  window.history.back();
}

// Add CSS animations for notifications
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
`;
document.head.appendChild(style);