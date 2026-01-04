document.addEventListener("DOMContentLoaded", function () {
  const signupForm = document.getElementById("signupForm");
  const signupBtn = document.getElementById("signupBtn");
  const togglePassword = document.getElementById("togglePassword");
  const progressFill = document.getElementById("progressFill");

  // Load verified student data from localStorage
  const verifiedMatric = localStorage.getItem("studentMatric");
  const verifiedIC = localStorage.getItem("studentIC") || "";
  const verifiedName = localStorage.getItem("studentName") || "";

  if (verifiedMatric) {
    const matricEl = document.getElementById("matricNo");
    const icEl = document.getElementById("icNo");
    const fullNameEl = document.getElementById("fullName");

    if (matricEl) {
      matricEl.value = verifiedMatric;
      matricEl.readOnly = true;
      matricEl.classList.add("readonly");
    }
    if (icEl) {
      icEl.value = verifiedIC;
      icEl.readOnly = true;
      icEl.classList.add("readonly");
    }
    if (verifiedName && fullNameEl) {
      fullNameEl.value = verifiedName;
      fullNameEl.readOnly = true;
      fullNameEl.classList.add("readonly");
    }
  }

  // Toggle password visibility
  togglePassword.addEventListener("click", function () {
    const passwordInput = document.getElementById("password");
    const icon = this.querySelector("i");

    if (passwordInput.type === "password") {
      passwordInput.type = "text";
      icon.classList.remove("fa-eye");
      icon.classList.add("fa-eye-slash");
    } else {
      passwordInput.type = "password";
      icon.classList.remove("fa-eye-slash");
      icon.classList.add("fa-eye");
    }
  });

  // Real-time form validation
  const inputs = signupForm.querySelectorAll("input, select");
  inputs.forEach((input) => {
    input.addEventListener("input", function () {
      validateField(this);
      updateProgress();
    });

    input.addEventListener("blur", function () {
      validateField(this);
    });
  });

  // Form submission
  signupForm.addEventListener("submit", function (e) {
    e.preventDefault();

    if (!validateForm()) {
      showNotification("Please fix the errors before submitting.", "error");
      return;
    }

    // Show loading state
    signupBtn.disabled = true;
    signupBtn.innerHTML =
      '<i class="fa-solid fa-spinner loading"></i> Creating Account...';

    // Prepare form data
    const formData = getFormData();

    // Send data to backend
    fetch("../database/signupdb.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(formData),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          showNotification(data.message, "success");
          // clear verification data
          localStorage.removeItem("studentMatric");
          localStorage.removeItem("studentIC");
          localStorage.removeItem("studentName");
          // redirect (use server-provided URL if present)
          setTimeout(() => {
            window.location.href =
              data.redirect_url || "../php/userdashboard.php";
          }, 1200);
        } else {
          throw new Error(data.message);
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        showNotification(
          error.message || "An error occurred during registration.",
          "error"
        );

        // Reset button state
        signupBtn.disabled = false;
        signupBtn.innerHTML =
          '<i class="fa-solid fa-user-plus"></i> Create Account';
      });
  });

  // Validation functions
  function validateField(field) {
    const value = field.value.trim();
    const errorElement =
      document.getElementById(field.id + "Error") || createErrorElement(field);

    let isValid = true;
    let errorMessage = "";

    switch (field.id) {
      case "fullName":
        isValid = /^[a-zA-Z\s]{2,50}$/.test(value);
        errorMessage = isValid
          ? ""
          : "Please enter a valid full name (2-50 characters, letters only)";
        break;

      case "username":
        isValid = /^[a-zA-Z0-9]{4,20}$/.test(value);
        errorMessage = isValid
          ? ""
          : "Username must be 4-20 characters (letters and numbers only)";
        break;

      case "email":
        isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        errorMessage = isValid ? "" : "Please enter a valid email address";
        break;

      case "phone":
        isValid = /^01[0-9]-?[0-9]{7,8}$/.test(value.replace(/\s/g, ""));
        errorMessage = isValid
          ? ""
          : "Please enter a valid Malaysian phone number";
        break;

      case "password":
        isValid = validatePassword(value);
        errorMessage = isValid
          ? ""
          : "Password must be at least 8 characters with uppercase, lowercase & number";
        updatePasswordStrength(value);
        break;

      case "confirmPassword":
        const password = document.getElementById("password").value;
        isValid = value === password && value.length > 0;
        errorMessage = isValid ? "" : "Passwords do not match";
        break;

      case "gender":
        isValid = value !== "";
        errorMessage = isValid ? "" : "Please select your gender";
        break;

      case "faculty":
        isValid = value !== "";
        errorMessage = isValid ? "" : "Please select your faculty";
        break;
    }

    // Update UI
    if (isValid) {
      field.classList.remove("error");
      field.classList.add("success");
      errorMessage = "";
    } else if (value.length > 0) {
      field.classList.add("error");
      field.classList.remove("success");
    } else {
      field.classList.remove("error", "success");
    }

    errorElement.textContent = errorMessage;
    return isValid;
  }

  function validatePassword(password) {
    const hasUpperCase = /[A-Z]/.test(password);
    const hasLowerCase = /[a-z]/.test(password);
    const hasNumbers = /\d/.test(password);
    const hasMinimumLength = password.length >= 8;

    return hasUpperCase && hasLowerCase && hasNumbers && hasMinimumLength;
  }

  function updatePasswordStrength(password) {
    const strengthBar =
      document.querySelector(".password-strength") ||
      createPasswordStrengthBar();
    const strengthFill = strengthBar.querySelector(".strength-fill");

    let strength = 0;
    if (password.length >= 8) strength += 1;
    if (/[a-z]/.test(password)) strength += 1;
    if (/[A-Z]/.test(password)) strength += 1;
    if (/\d/.test(password)) strength += 1;
    if (/[^a-zA-Z\d]/.test(password)) strength += 1;

    strengthBar.className = "password-strength";
    strengthFill.className = "strength-fill";

    if (password.length === 0) {
      strengthFill.style.width = "0%";
    } else if (strength <= 2) {
      strengthBar.classList.add("strength-weak");
      strengthFill.style.width = "33%";
    } else if (strength <= 4) {
      strengthBar.classList.add("strength-medium");
      strengthFill.style.width = "66%";
    } else {
      strengthBar.classList.add("strength-strong");
      strengthFill.style.width = "100%";
    }
  }

  function createPasswordStrengthBar() {
    const passwordGroup = document
      .getElementById("password")
      .closest(".form-group");
    const strengthBar = document.createElement("div");
    strengthBar.className = "password-strength";
    strengthBar.innerHTML = '<div class="strength-fill"></div>';
    passwordGroup.appendChild(strengthBar);
    return strengthBar;
  }

  function createErrorElement(field) {
    const errorElement = document.createElement("small");
    errorElement.className = "error-text";
    errorElement.id = field.id + "Error";
    field.closest(".form-group").appendChild(errorElement);
    return errorElement;
  }

  function validateForm() {
    let isValid = true;
    const requiredFields = signupForm.querySelectorAll("[required]");

    requiredFields.forEach((field) => {
      if (!validateField(field)) {
        isValid = false;
      }
    });

    const termsChecked = document.getElementById("terms").checked;
    if (!termsChecked) {
      isValid = false;
      showNotification("You must agree to the Terms of Service", "error");
    }

    return isValid;
  }

  function updateProgress() {
    const requiredFields = signupForm.querySelectorAll("[required]");
    let filledCount = 0;

    requiredFields.forEach((field) => {
      if (field.type === "checkbox") {
        if (field.checked) filledCount++;
      } else if (field.value.trim().length > 0 && validateField(field)) {
        filledCount++;
      }
    });

    const progress = (filledCount / requiredFields.length) * 100;
    progressFill.style.width = progress + "%";
  }

  function getFormData() {
    return {
      matricNo: document.getElementById("matricNo").value,
      icNo: document.getElementById("icNo").value,
      fullName: document.getElementById("fullName").value,
      faculty: document.getElementById("faculty").value,
      gender: document.getElementById("gender").value,
      username: document.getElementById("username").value,
      email: document.getElementById("email").value,
      phone: document.getElementById("phone").value,
      password: document.getElementById("password").value,
    };
  }

  function showNotification(message, type) {
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

    // Add styles
    notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${
              type === "success" ? "var(--success-color)" : "var(--error-color)"
            };
            color: white;
            padding: 15px 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1000;
            animation: slideIn 0.3s ease;
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

  // Initialize progress
  updateProgress();
});

// Add CSS animations
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
    
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 1000;
        animation: slideIn 0.3s ease;
    }
    
    .notification.success {
        background: var(--success-color);
        color: white;
    }
    
    .notification.error {
        background: var(--error-color);
        color: white;
    }
`;
document.head.appendChild(style);