document.addEventListener("DOMContentLoaded", function () {
  const bookingForm = document.getElementById("bookingForm");
  const submitBtn = document.getElementById("submitBtn");
  const numberOfSeats = document.getElementById("numberOfSeats");
  const pricePerSeat = document.getElementById("pricePerSeat");
  const totalPrice = document.getElementById("totalPrice");
  const seatsCount = document.getElementById("seatsCount");

  // Check if form is disabled (conflict exists)
  if (submitBtn.disabled) {
    console.log("Booking form is disabled due to scheduling conflict");
    return;
  }

  // Initialize price calculation
  updatePrice();

  // Real-time price calculation
  numberOfSeats.addEventListener("change", updatePrice);

  // Real-time form validation
  const requiredInputs = bookingForm.querySelectorAll("[required]");
  requiredInputs.forEach((input) => {
    input.addEventListener("input", function () {
      validateField(this);
    });

    input.addEventListener("blur", function () {
      validateField(this);
    });
  });

  // Phone number validation
  const phoneInput = document.getElementById("passengerPhone");
  phoneInput.addEventListener("input", function () {
    validatePhoneNumber(this);
  });

  // Form submission
  bookingForm.addEventListener("submit", function (e) {
    e.preventDefault();

    if (!validateForm()) {
      showNotification("Please fix the errors before submitting.", "error");
      return;
    }

    // Check for time conflict before submitting
    checkTimeConflict();
  });

  console.log("Booking page loaded");
});

function updatePrice() {
  const seats = parseInt(document.getElementById("numberOfSeats").value);
  const price = parseFloat(document.getElementById("pricePerSeat").textContent);
  const total = seats * price;

  document.getElementById("totalPrice").textContent = total.toFixed(2);
  document.getElementById("seatsCount").textContent = seats;
}

function validateField(field) {
  const value = field.value.trim();
  let isValid = true;
  let errorMessage = "";

  switch (field.id) {
    case "passengerPhone":
      isValid = validatePhoneNumber(field);
      errorMessage = isValid ? "" : "Please enter a valid phone number";
      break;

    case "numberOfSeats":
      const maxSeats = parseInt(field.max) || 7;
      isValid = value >= 1 && value <= maxSeats;
      errorMessage = isValid
        ? ""
        : `Please select between 1 and ${maxSeats} pax`;
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

  // Update error message
  const errorElement = document.getElementById(field.id + "Error");
  if (errorElement) {
    errorElement.textContent = errorMessage;
  }

  return isValid;
}

function validatePhoneNumber(field) {
  const value = field.value.trim();
  const phoneRegex = /^[0-9]{10,11}$/;
  return phoneRegex.test(value);
}

function validateForm() {
  let isValid = true;
  const requiredInputs = document.querySelectorAll("[required]");

  requiredInputs.forEach((field) => {
    if (!validateField(field)) {
      isValid = false;
    }
  });

  return isValid;
}

function getFormData() {
  return {
    rideID: document.getElementById("rideID").value,
    userID: document.getElementById("userID").value,
    passengerName: document.getElementById("passengerName").value,
    passengerPhone: document.getElementById("passengerPhone").value,
    numberOfSeats: document.getElementById("numberOfSeats").value,
    specialRequests: document.getElementById("specialRequests").value,
    totalPrice: document.getElementById("totalPrice").textContent,
  };
}

function checkTimeConflict() {
  const rideID = document.getElementById("rideID").value;
  const userID = document.getElementById("userID").value;

  // Show loading state
  const submitBtn = document.getElementById("submitBtn");
  submitBtn.disabled = true;
  submitBtn.innerHTML =
    '<i class="fa-solid fa-spinner loading"></i> Checking availability...';

  // First check for time conflicts
  fetch("../database/checkTimeConflict.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ rideID: rideID, userID: userID }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.hasConflict) {
        // If conflict exists, show error
        showNotification(data.message, "error");
        submitBtn.disabled = false;
        submitBtn.innerHTML =
          '<i class="fa-solid fa-credit-card"></i> Confirm Booking';
      } else {
        // If no conflict, proceed with booking
        submitBooking(getFormData());
      }
    })
    .catch((error) => {
      console.error("Error checking time conflict:", error);
      // If checking fails, proceed with booking (server will validate)
      submitBooking(getFormData());
    });
}

function submitBooking(formData) {
  console.log("Submitting booking:", formData);

  const submitBtn = document.getElementById("submitBtn");
  submitBtn.innerHTML =
    '<i class="fa-solid fa-spinner loading"></i> Processing...';

  const backendPath = "../database/bookingdb.php";

  fetch(backendPath, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(formData),
  })
    .then((response) => {
      console.log("Response status:", response.status, response.statusText);

      if (!response.ok) {
        return response.text().then((text) => {
          let errorMessage = `Server error: ${response.status}`;
          try {
            const errorData = JSON.parse(text);
            errorMessage = errorData.message || errorMessage;
          } catch (e) {
            errorMessage = text || errorMessage;
          }
          throw new Error(errorMessage);
        });
      }
      return response.json();
    })
    .then((data) => {
      console.log("Booking response:", data);

      if (data.success) {
        showNotification(data.message, "success");

        // Redirect to dashboard after successful booking
        setTimeout(() => {
          window.location.href = "userdashboard.php";
        }, 2000);
      } else {
        throw new Error(data.message);
      }
    })
    .catch((error) => {
      console.error("Booking Error:", error);

      let errorMessage = "An error occurred during booking.";

      if (error.message.includes("Failed to fetch")) {
        errorMessage =
          "Cannot connect to server. Please check your internet connection.";
      } else if (error.message.includes("404")) {
        errorMessage = "Booking service not found. Please try again later.";
      } else if (error.message.includes("500")) {
        errorMessage = "Server error. Please try again later.";
      } else {
        errorMessage = error.message;
      }

      showNotification(errorMessage, "error");

      // Reset button state
      submitBtn.disabled = false;
      submitBtn.innerHTML =
        '<i class="fa-solid fa-credit-card"></i> Confirm Booking';
    });
}

function showNotification(message, type = "success") {
  const existingNotifications = document.querySelectorAll(".notification");
  existingNotifications.forEach((notification) => notification.remove());

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

  setTimeout(() => {
    notification.style.animation = "slideOut 0.3s ease";
    setTimeout(() => {
      if (notification.parentNode) {
        notification.remove();
      }
    }, 300);
  }, 5000);
}

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
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .loading {
        animation: spin 1s linear infinite;
    }
`;
document.head.appendChild(style);