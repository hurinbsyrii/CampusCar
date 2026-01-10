document.addEventListener("DOMContentLoaded", function () {
  const registrationForm = document.getElementById("driverRegistrationForm");
  const submitBtn = document.getElementById("submitBtn");

  // Real-time form validation
  const inputs = registrationForm.querySelectorAll("input[required]");
  inputs.forEach((input) => {
    input.addEventListener("input", function () {
      validateField(this);
    });

    input.addEventListener("blur", function () {
      validateField(this);
    });
  });

  // Form submission
  registrationForm.addEventListener("submit", function (e) {
    e.preventDefault();

    if (!validateForm()) {
      showNotification("Please fix the errors before submitting.", "error");
      return;
    }

    // Show loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML =
      '<i class="fa-solid fa-spinner loading"></i> Registering...';

    // Submit the form
    this.submit();
  });

  // Validation functions
  function validateField(field) {
    const value = field.value.trim();
    const errorElement = document.getElementById(field.id + "Error");
    let isValid = true;
    let errorMessage = "";

    switch (field.id) {
      case "licenseNumber":
        isValid = value.length >= 5;
        errorMessage = isValid
          ? ""
          : "License number must be at least 5 characters";
        break;

      case "carModel":
        isValid = value.length >= 2;
        errorMessage = isValid ? "" : "Car model must be at least 2 characters";
        break;

      case "carPlateNumber":
        // Malaysian car plate format validation (basic)
        const plateRegex = /^[A-Z]{1,3}\s?\d{1,4}\s?[A-Z]?$/i;
        isValid = plateRegex.test(value);
        errorMessage = isValid
          ? ""
          : "Please enter a valid car plate number (e.g., ABC1234)";
        break;
    }

    // Update UI
    if (isValid) {
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
    inputs.forEach((field) => {
      if (!validateField(field)) {
        isValid = false;
      }
    });
    return isValid;
  }

  function showNotification(message, type = "success") {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll(".notification");
    existingNotifications.forEach((notification) => notification.remove());

    // Create notification element
    const notification = document.createElement("div");
    notification.className = `notification ${type}`;
    notification.innerHTML = `
            <i class="fa-solid fa-${
              type === "success" ? "check" : "exclamation-triangle"
            }"></i>
            <span>${message}</span>
        `;

    document.body.appendChild(notification);

    // Remove after 5 seconds
    setTimeout(() => {
      notification.style.animation = "slideOut 0.3s ease";
      setTimeout(() => {
        if (notification.parentNode) {
          notification.remove();
        }
      }, 300);
    }, 5000);
  }
});

// Add these to the existing JavaScript file

// Bank account number validation
function validateBankAccount(accountNumber) {
  // Malaysian bank account numbers are typically 10-16 digits
  const accountRegex = /^\d{10,16}$/;
  return accountRegex.test(accountNumber);
}

// File validation for QR code
function validateFile(file) {
  if (!file) return true; // Optional field

  const allowedTypes = ["image/jpeg", "image/png", "image/jpg"];
  const maxSize = 2 * 1024 * 1024; // 2MB

  if (!allowedTypes.includes(file.type)) {
    return "Only JPG, JPEG, and PNG files are allowed";
  }

  if (file.size > maxSize) {
    return "File size must be less than 2MB";
  }

  return "";
}

// Update the validateField function
function validateField(field) {
  const value = field.value.trim();
  const errorElement = document.getElementById(field.id + "Error");
  let isValid = true;
  let errorMessage = "";

  switch (field.id) {
    case "licenseNumber":
      isValid = value.length >= 5;
      errorMessage = isValid
        ? ""
        : "License number must be at least 5 characters";
      break;

    case "carModel":
      isValid = value.length >= 2;
      errorMessage = isValid ? "" : "Car model must be at least 2 characters";
      break;

    case "carPlateNumber":
      const plateRegex = /^[A-Z]{1,3}\s?\d{1,4}\s?[A-Z]?$/i;
      isValid = plateRegex.test(value);
      errorMessage = isValid
        ? ""
        : "Please enter a valid car plate number (e.g., ABC1234)";
      break;

    case "accountNumber":
      if (value.length > 0) {
        isValid = validateBankAccount(value);
        errorMessage = isValid
          ? ""
          : "Please enter a valid bank account number (10-16 digits)";
      }
      break;

    case "accountName":
      isValid = value.length >= 3;
      errorMessage = isValid
        ? ""
        : "Account name must be at least 3 characters";
      break;

    case "paymentQR":
      const fileInput = field;
      if (fileInput.files.length > 0) {
        const file = fileInput.files[0];
        const fileError = validateFile(file);
        if (fileError) {
          isValid = false;
          errorMessage = fileError;
        }
      }
      break;
  }

  // Update UI
  if (isValid || value.length === 0) {
    field.classList.remove("error");
    if (value.length > 0 && isValid) {
      field.classList.add("success");
    } else {
      field.classList.remove("success");
    }
  } else {
    field.classList.add("error");
    field.classList.remove("success");
  }

  if (errorElement) {
    errorElement.textContent = errorMessage;
  }

  return isValid;
}

// Preview QR code image
document.getElementById("paymentQR")?.addEventListener("change", function (e) {
  const file = e.target.files[0];
  const preview = document.getElementById("qrPreview");

  if (!preview) {
    const previewContainer = document.createElement("div");
    previewContainer.id = "qrPreviewContainer";
    previewContainer.className = "qr-preview";
    this.parentNode.appendChild(previewContainer);

    const img = document.createElement("img");
    img.id = "qrPreview";
    previewContainer.appendChild(img);
  }

  if (file && file.type.match("image.*")) {
    const reader = new FileReader();
    reader.onload = function (e) {
      document.getElementById("qrPreview").src = e.target.result;
    };
    reader.readAsDataURL(file);
  }
});

// Check terms agreement before submission
registrationForm.addEventListener("submit", function (e) {
  const agreeTerms = document.getElementById("agreeTerms");
  if (agreeTerms && !agreeTerms.checked) {
    e.preventDefault();
    showNotification("Please agree to the terms and conditions", "error");
    agreeTerms.focus();
    return;
  }
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
`;
document.head.appendChild(style);
