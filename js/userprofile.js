// User Profile JavaScript
document.addEventListener("DOMContentLoaded", function () {
  // Form elements
  const form = document.getElementById("profileForm");
  const phoneInput = document.getElementById("phone_number");
  const qrFileInput = document.getElementById("payment_qr_code");
  const qrLivePreview = document.getElementById("qrLivePreview");
  const selectedFileInfo = document.getElementById("selectedFileInfo");
  const selectedFileName = document.getElementById("selectedFileName");
  const selectedFileSize = document.getElementById("selectedFileSize");
  const removeSelectedFileBtn = document.getElementById("removeSelectedFile");
  const qrUploadBox = document.getElementById("qrUploadBox");
  const uploadText = document.getElementById("uploadText");
  const previewHint = document.getElementById("previewHint");
  const currentQRContainer = document.getElementById("currentQRContainer");
  const saveBtn = document.getElementById("saveBtn");

  // Initialize variables
  let selectedFile = null;

  // QR code upload functionality
  if (qrFileInput && qrLivePreview) {
    // Handle file selection
    qrFileInput.addEventListener("change", function (e) {
      if (this.files.length > 0) {
        handleFileSelection(this.files[0]);
      }
    });

    // Handle drag and drop
    qrUploadBox.addEventListener("dragover", function (e) {
      e.preventDefault();
      this.classList.add("dragover");
    });

    qrUploadBox.addEventListener("dragleave", function () {
      this.classList.remove("dragover");
    });

    qrUploadBox.addEventListener("drop", function (e) {
      e.preventDefault();
      this.classList.remove("dragover");

      if (e.dataTransfer.files.length) {
        const file = e.dataTransfer.files[0];
        handleFileSelection(file);
        qrFileInput.files = e.dataTransfer.files;
      }
    });

    // Remove selected file button
    if (removeSelectedFileBtn) {
      removeSelectedFileBtn.addEventListener("click", function () {
        clearFileSelection();
      });
    }

    // Click on upload box to trigger file input
    qrUploadBox.addEventListener("click", function (e) {
      if (!e.target.closest(".selected-file-content") && !e.target.closest(".qr-live-preview")) {
        qrFileInput.click();
      }
    });
  }

  // Handle file selection
  function handleFileSelection(file) {
    if (!file) return;

    // Validate file type
    const validTypes = [
      "image/jpeg",
      "image/jpg",
      "image/png",
      "image/gif",
      "image/webp",
    ];

    if (!validTypes.includes(file.type)) {
      showMessage(
        "Please upload a valid image file (JPEG, PNG, GIF, or WebP).",
        "error"
      );
      clearFileSelection();
      return;
    }

    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      showMessage("File size should not exceed 5MB.", "error");
      clearFileSelection();
      return;
    }

    // Store the file
    selectedFile = file;

    // Update UI
    updateFileDisplay(file);
    
    // Show preview
    showImagePreview(file);
    
    // Show preview hint
    if (previewHint) {
      previewHint.style.display = "block";
    }

    // Hide current QR if exists
    if (currentQRContainer) {
      currentQRContainer.style.opacity = "0.5";
      currentQRContainer.style.filter = "grayscale(50%)";
    }

    // Update upload text
    uploadText.textContent = "Change QR Code";
  }

  // Update file information display
  function updateFileDisplay(file) {
    selectedFileName.textContent = file.name;
    selectedFileSize.textContent = formatFileSize(file.size);
    selectedFileInfo.style.display = "block";
  }

  // Show image preview
  function showImagePreview(file) {
    const reader = new FileReader();
    reader.onload = function (e) {
      qrLivePreview.innerHTML = `
        <div class="live-preview-content">
          <div class="preview-header">
            <h4><i class="fa-solid fa-eye"></i> Preview</h4>
            <small>Selected image preview</small>
          </div>
          <div class="preview-image-container">
            <img src="${e.target.result}" alt="QR Code Preview" class="live-preview-image">
          </div>
          <div class="preview-info">
            <p><i class="fa-solid fa-info-circle"></i> This image will replace your current QR code when you save changes</p>
          </div>
        </div>
      `;
      
      // after creating the preview HTML or img element, ensure size attributes
      const imgEl = qrLivePreview.querySelector('img');
      if (imgEl) {
        imgEl.style.maxWidth = '200px';
        imgEl.style.maxHeight = '200px';
        imgEl.style.objectFit = 'contain';
      }

      qrLivePreview.style.display = "block";
      qrUploadBox.classList.add("has-preview");
    };
    reader.readAsDataURL(file);
  }

  // Clear file selection
  function clearFileSelection() {
    selectedFile = null;
    qrFileInput.value = "";
    qrLivePreview.innerHTML = "";
    qrLivePreview.style.display = "none";
    selectedFileInfo.style.display = "none";
    uploadText.textContent = currentQRContainer ? "Change QR Code" : "Upload QR Code";
    
    // Reset current QR display
    if (currentQRContainer) {
      currentQRContainer.style.opacity = "1";
      currentQRContainer.style.filter = "none";
    }
    
    // Reset upload box styling
    qrUploadBox.classList.remove("has-preview");
    
    // Hide preview hint
    if (previewHint) {
      previewHint.style.display = "none";
    }
  }

  // Format file size
  function formatFileSize(bytes) {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
  }

  // Phone number validation
  if (phoneInput) {
    phoneInput.addEventListener("input", function (e) {
      // Remove any non-digit characters
      this.value = this.value.replace(/[^\d]/g, "");

      // Validate length
      if (this.value.length > 11) {
        this.value = this.value.slice(0, 11);
      }

      // Real-time validation
      validatePhoneNumber(this);
    });

    phoneInput.addEventListener("blur", function () {
      validatePhoneNumber(this);
    });

    // Initial validation
    validatePhoneNumber(phoneInput);
  }

  function validatePhoneNumber(input) {
    if (input.value.length < 10 || input.value.length > 11) {
      showValidationError(input, "Phone number must be 10-11 digits");
      return false;
    } else {
      clearValidationError(input);
      return true;
    }
  }

  // Form submission handling
  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();

      let isValid = true;

      // Validate phone number
      if (phoneInput && !validatePhoneNumber(phoneInput)) {
        isValid = false;
      }

      // Validate driver fields if present
      const carModel = document.getElementById("car_model");
      const carPlate = document.getElementById("car_plate_number");

      if (carModel && carModel.value.trim() === "") {
        isValid = false;
        showValidationError(carModel, "Car model is required");
      } else {
        clearValidationError(carModel);
      }

      if (carPlate && carPlate.value.trim() === "") {
        isValid = false;
        showValidationError(carPlate, "Car plate number is required");
      } else {
        clearValidationError(carPlate);
      }

      // Validate QR file if selected
      if (qrFileInput && qrFileInput.files.length > 0) {
        const file = qrFileInput.files[0];
        const validTypes = [
          "image/jpeg",
          "image/jpg",
          "image/png",
          "image/gif",
          "image/webp",
        ];

        if (!validTypes.includes(file.type)) {
          isValid = false;
          showMessage(
            "Please upload a valid image file (JPEG, PNG, GIF, or WebP).",
            "error"
          );
        }

        if (file.size > 5 * 1024 * 1024) {
          isValid = false;
          showMessage("File size should not exceed 5MB.", "error");
        }
      }

      if (!isValid) {
        showMessage(
          "Please fix the validation errors before submitting.",
          "error"
        );
        // Scroll to first error
        const firstError = document.querySelector(".error-message");
        if (firstError) {
          firstError.scrollIntoView({ behavior: "smooth", block: "center" });
        }
        return;
      }

      // Show loading state
      if (saveBtn) {
        const originalText = saveBtn.innerHTML;
        saveBtn.innerHTML =
          '<i class="fa-solid fa-spinner fa-spin"></i> Saving Changes...';
        saveBtn.disabled = true;
        saveBtn.classList.add("loading");
        
        // Store original content for reset
        saveBtn.setAttribute("data-original", originalText);
      }

      // Submit the form
      form.submit();
    });
  }

  // Auto-hide messages after 5 seconds
  const messages = document.querySelectorAll(".message");
  messages.forEach((message) => {
    setTimeout(() => {
      message.style.opacity = "0";
      setTimeout(() => {
        if (message.parentNode) {
          message.parentNode.removeChild(message);
        }
      }, 300);
    }, 5000);
  });

  // Add confirmation for unsaved changes
  let formChanged = false;
  const formInputs = form
    ? form.querySelectorAll("input, select, textarea")
    : [];
  formInputs.forEach((input) => {
    const originalValue = input.value;
    input.addEventListener("input", () => {
      formChanged = input.value !== originalValue;
    });
  });

  window.addEventListener("beforeunload", (e) => {
    if (formChanged) {
      e.preventDefault();
      e.returnValue = "";
    }
  });
});

// Validation functions
function showValidationError(input, message) {
  if (!input) return;

  // Remove existing error message
  const existingError = input.parentElement.querySelector(".error-message");
  if (existingError) {
    existingError.remove();
  }

  // Add error styling
  input.parentElement.classList.add("error");

  // Create error message
  const errorElement = document.createElement("div");
  errorElement.className = "error-message";
  errorElement.innerHTML = `<i class="fa-solid fa-exclamation-circle"></i> ${message}`;

  input.parentElement.appendChild(errorElement);
}

function clearValidationError(input) {
  if (!input) return;

  input.parentElement.classList.remove("error");
  const errorElement = input.parentElement.querySelector(".error-message");
  if (errorElement) {
    errorElement.remove();
  }
}

function showMessage(message, type) {
  // Remove existing messages
  const existingMessages = document.querySelectorAll(
    ".message:not(.auto-remove)"
  );
  existingMessages.forEach((msg) => {
    if (!msg.classList.contains("persistent")) {
      msg.remove();
    }
  });

  // Create message element
  const messageElement = document.createElement("div");
  messageElement.className = `message ${type} auto-remove`;
  messageElement.innerHTML = `
        <i class="fa-solid fa-${
          type === "success" ? "check" : "exclamation"
        }-circle"></i>
        <span>${message}</span>
    `;

  // Insert at the top of profile content
  const profileContent = document.querySelector(".profile-content");
  if (profileContent) {
    const firstChild = profileContent.firstChild;
    if (
      firstChild &&
      firstChild.classList &&
      firstChild.classList.contains("message")
    ) {
      profileContent.insertBefore(messageElement, firstChild.nextSibling);
    } else {
      profileContent.insertBefore(messageElement, firstChild);
    }
  } else {
    // Fallback to body
    document.body.insertBefore(messageElement, document.body.firstChild);
  }

  // Auto-remove after 5 seconds
  setTimeout(() => {
    if (messageElement.parentNode) {
      messageElement.style.opacity = "0";
      setTimeout(() => {
        if (messageElement.parentNode) {
          messageElement.parentNode.removeChild(messageElement);
        }
      }, 300);
    }
  }, 5000);
}