document.addEventListener("DOMContentLoaded", function () {
  const loginForm = document.getElementById("loginForm");
  const loginBtn = document.getElementById("loginBtn");
  const togglePassword = document.getElementById("togglePassword");

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
  const inputs = loginForm.querySelectorAll("input");
  inputs.forEach((input) => {
    input.addEventListener("input", function () {
      validateField(this);
    });

    input.addEventListener("blur", function () {
      validateField(this);
    });
  });

  // Form submission
  loginForm.addEventListener("submit", function (e) {
    e.preventDefault();

    if (!validateForm()) {
      showNotification("Please fix the errors before submitting.", "error");
      return;
    }

    // Show loading state
    loginBtn.disabled = true;
    loginBtn.innerHTML =
      '<i class="fa-solid fa-spinner loading"></i> Signing In...';

    // Prepare form data
    const formData = getFormData();

    console.log("Attempting login with:", formData.username);

    // Use absolute path to backend
    const backendPath = "../database/logindb.php";

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
          // If response is not OK, try to get error message
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
        console.log("Login response:", data);

        if (data.success) {
          showNotification(data.message, "success");

          // Redirect to home page
          setTimeout(() => {
            window.location.href =
              data.redirect_url || "../php/userdashboard.php";
          }, 1500);
        } else {
          throw new Error(data.message);
        }
      })
      .catch((error) => {
        console.error("Login Error:", error);

        let errorMessage = "An error occurred during login.";

        if (error.message.includes("Failed to fetch")) {
          errorMessage = `Cannot connect to backend service. Please check if the server is running. Tried: ${backendPath}`;
        } else if (error.message.includes("404")) {
          errorMessage = `Login service not found at: ${backendPath}. Please check file location.`;
        } else if (error.message.includes("500")) {
          errorMessage =
            "Server error. Please check the backend PHP file for errors.";
        } else {
          errorMessage = error.message;
        }

        showNotification(errorMessage, "error");

        // Reset button state
        loginBtn.disabled = false;
        loginBtn.innerHTML =
          '<i class="fa-solid fa-right-to-bracket"></i> Sign In';
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
      case "username":
        isValid = value.length >= 3;
        errorMessage = isValid ? "" : "Username must be at least 3 characters";
        break;

      case "password":
        isValid = value.length >= 1;
        errorMessage = isValid ? "" : "Password is required";
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

  function createErrorElement(field) {
    const errorElement = document.createElement("small");
    errorElement.className = "error-text";
    errorElement.id = field.id + "Error";
    field.closest(".form-group").appendChild(errorElement);
    return errorElement;
  }

  function validateForm() {
    let isValid = true;
    const requiredFields = loginForm.querySelectorAll("[required]");

    requiredFields.forEach((field) => {
      if (!validateField(field)) {
        isValid = false;
      }
    });

    return isValid;
  }

  function getFormData() {
    return {
      username: document.getElementById("username").value,
      password: document.getElementById("password").value,
      // remember: document.getElementById("remember").checked,
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
`;
document.head.appendChild(style);
