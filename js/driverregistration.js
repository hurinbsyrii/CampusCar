document.addEventListener("DOMContentLoaded", function () {
  const registrationForm = document.getElementById("driverRegistrationForm");
  const submitBtn = document.getElementById("submitBtn");
  const inputs = registrationForm.querySelectorAll(
    "input[required], select[required]"
  );

  // --- Helper Functions ---

  // Validate File
  function validateFile(file) {
    if (!file) return ""; // Optional field
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

  // Validate Bank Account (10-20 digits)
  function validateBankAccount(accountNumber) {
    const accountRegex = /^\d{10,20}$/;
    return accountRegex.test(accountNumber);
  }

  // --- Main Validation Function ---
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
        // Basic Malaysian car plate format validation
        const plateRegex = /^[A-Z]{1,3}\s?\d{1,4}\s?[A-Z]?$/i;
        isValid = plateRegex.test(value);
        errorMessage = isValid
          ? ""
          : "Please enter a valid car plate number (e.g., ABC1234)";
        break;

      case "accountNumber":
        // Logic diperbetulkan di sini
        if (value.length > 0) {
          isValid = validateBankAccount(value);
          errorMessage = isValid
            ? ""
            : "Invalid account number. Must be 10-20 digits.";
        } else {
          // Kerana field ini required, jika kosong ia invalid
          isValid = false;
          errorMessage = "Account number is required";
        }
        break;

      case "accountName":
        isValid = value.length >= 3;
        errorMessage = isValid
          ? ""
          : "Account name must be at least 3 characters";
        break;

      case "bankName":
        isValid = value !== "";
        errorMessage = isValid ? "" : "Please select a bank";
        break;

      case "paymentQR":
        if (field.files.length > 0) {
          const file = field.files[0];
          const fileError = validateFile(file);
          if (fileError) {
            isValid = false;
            errorMessage = fileError;
          }
        }
        break;
    }

    // Update UI (Visual Feedback)
    // Kita remove class success jika kosong supaya tak nampak hijau kalau user tak isi apa-apa
    if (isValid && value.length > 0) {
      field.classList.remove("error");
      field.classList.add("success");
    } else if (!isValid && value.length > 0) {
      // Jika ada error dan user dah taip sesuatu
      field.classList.add("error");
      field.classList.remove("success");
    } else {
      // Reset jika kosong (tapi error text mungkin kekal jika trigger dari submit)
      field.classList.remove("success");
      if (!isValid && field.hasAttribute("required")) {
        field.classList.add("error");
      } else {
        field.classList.remove("error");
      }
    }

    if (errorElement) {
      errorElement.textContent = errorMessage;
    }

    return isValid;
  }

  function validateForm() {
    let isValid = true;
    inputs.forEach((field) => {
      // Semak setiap field, jika satu gagal, form tidak valid
      if (!validateField(field)) {
        isValid = false;
      }
    });

    // Check Terms separately
    const agreeTerms = document.getElementById("agreeTerms");
    if (agreeTerms && !agreeTerms.checked) {
      isValid = false;
      showNotification("Please agree to the terms and conditions", "error");
    }

    return isValid;
  }

  // --- Event Listeners ---

  // Real-time validation on input and blur
  inputs.forEach((input) => {
    input.addEventListener("input", function () {
      validateField(this);
    });

    input.addEventListener("blur", function () {
      validateField(this);
    });
  });

  // Handle File Input Change (QR Preview)
  const qrInput = document.getElementById("paymentQR");
  if (qrInput) {
    qrInput.addEventListener("change", function (e) {
      validateField(this); // Validate file size/type immediately

      // Preview logic
      /* // Uncomment jika anda mahu preview gambar
            const file = e.target.files[0];
            if (file && file.type.match("image.*")) {
                // Logic preview gambar di sini
            }
            */
    });
  }

  // Form Submission
  registrationForm.addEventListener("submit", function (e) {
    e.preventDefault();

    if (!validateForm()) {
      showNotification("Please fix the errors before submitting.", "error");

      // Scroll to the first error
      const firstError = document.querySelector(".error");
      if (firstError) {
        firstError.scrollIntoView({ behavior: "smooth", block: "center" });
        firstError.focus();
      }
      return;
    }

    // Show loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML =
      '<i class="fa-solid fa-spinner fa-spin"></i> Registering...';

    // Submit the form
    this.submit();
  });

  // Notification System
  function showNotification(message, type = "success") {
    const existingNotifications = document.querySelectorAll(".notification");
    existingNotifications.forEach((notification) => notification.remove());

    const notification = document.createElement("div");
    notification.className = `notification ${type}`;
    notification.innerHTML = `
            <i class="fa-solid fa-${
              type === "success" ? "check" : "exclamation-triangle"
            }"></i>
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
});

// Function outside DOMContentLoaded for onclick="goBack()" in HTML
function goBack() {
  window.history.back();
}

// Add CSS animations dynamically
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
    .fa-spin {
        animation: fa-spin 2s infinite linear;
    }
    @keyframes fa-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);
