document.addEventListener("DOMContentLoaded", function () {
  console.log("Review page loaded");

  // Initialize star rating
  initializeStarRating();

  // Initialize character counter
  initializeCharCounter();

  // Setup form submission
  setupReviewForm();
});

function initializeStarRating() {
  const stars = document.querySelectorAll('.star-rating input[type="radio"]');
  const ratingText = document.getElementById("rating-text");
  const ratingDescs = document.querySelectorAll(".rating-desc");

  const ratingLabels = {
    1: "Poor",
    2: "Fair",
    3: "Good",
    4: "Very Good",
    5: "Excellent",
  };

  stars.forEach((star) => {
    star.addEventListener("change", function () {
      const rating = parseInt(this.value);
      if (ratingText) {
        ratingText.textContent = ratingLabels[rating] || "Excellent";
      }

      // Update rating descriptions
      ratingDescs.forEach((desc) => {
        if (parseInt(desc.dataset.rating) === rating) {
          desc.classList.add("active");
        } else {
          desc.classList.remove("active");
        }
      });
    });
  });

  // Initialize with default rating
  const defaultStar = document.querySelector(
    '.star-rating input[type="radio"]:checked'
  );
  if (defaultStar) {
    defaultStar.dispatchEvent(new Event("change"));
  }
}

function initializeCharCounter() {
  const textarea = document.getElementById("comment");
  const charCount = document.getElementById("char-count");

  if (textarea && charCount) {
    // Update count on input
    textarea.addEventListener("input", function () {
      const length = this.value.length;
      charCount.textContent = length;

      // Change color when approaching limit
      if (length > 450) {
        charCount.style.color = "#dc3545"; // Error color
      } else if (length > 400) {
        charCount.style.color = "#ffc107"; // Warning color
      } else {
        charCount.style.color = ""; // Reset
      }
    });

    // Initialize count
    charCount.textContent = textarea.value.length;
  }
}

function setupReviewForm() {
  const form = document.getElementById("reviewForm");
  if (!form) return;

  form.addEventListener("submit", function (e) {
    e.preventDefault();

    console.log("Form submission started");

    // Validate rating
    const rating = document.querySelector('input[name="rating"]:checked');
    if (!rating) {
      showNotification("Please select a rating", "error");
      return;
    }

    // Validate terms
    const terms = document.getElementById("terms");
    if (!terms || !terms.checked) {
      showNotification("Please confirm the review terms", "error");
      return;
    }

    // Validate comment length
    const comment = document.getElementById("comment");
    if (comment && comment.value.length > 500) {
      showNotification("Comment cannot exceed 500 characters", "error");
      return;
    }

    // Show loading
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
    submitBtn.disabled = true;

    // Create form data
    const formData = new FormData(form);

    // Submit via fetch
    fetch(form.action, {
      method: "POST",
      body: formData,
    })
      .then((response) => {
        console.log("Response status:", response.status);
        return response.json();
      })
      .then((data) => {
        console.log("Response data:", data);

        if (data.success) {
          showNotification(
            data.message || "Review submitted successfully!",
            "success"
          );

          // Redirect after 2 seconds
          setTimeout(() => {
            window.location.href = "mybookings.php?review=success";
          }, 2000);
        } else {
          showNotification(data.message || "Failed to submit review", "error");
          submitBtn.innerHTML = originalText;
          submitBtn.disabled = false;
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        showNotification("Network error. Please check your connection.", "error");
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
      });
  });
}

function showNotification(message, type = "info") {
  // Remove existing notifications
  const existingNotifications = document.querySelectorAll(".notification");
  existingNotifications.forEach((notification) => notification.remove());

  // Create notification element
  const notification = document.createElement("div");
  notification.className = `notification notification-${type}`;

  const icons = {
    success: "fa-check-circle",
    error: "fa-exclamation-triangle",
    info: "fa-info-circle",
  };

  notification.innerHTML = `
    <i class="fa-solid ${icons[type] || "fa-info-circle"}"></i>
    <span>${message}</span>
  `;

  // Add styles
  notification.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    background: ${getNotificationColor(type)};
    color: white;
    padding: 15px 20px;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    gap: 10px;
    z-index: 10000;
    animation: notificationSlideIn 0.3s ease;
    max-width: 400px;
    font-weight: 500;
  `;

  document.body.appendChild(notification);

  // Add CSS animation if not exists
  if (!document.querySelector("#notification-animations")) {
    const style = document.createElement("style");
    style.id = "notification-animations";
    style.textContent = `
      @keyframes notificationSlideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
      }
      
      @keyframes notificationSlideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
      }
    `;
    document.head.appendChild(style);
  }

  // Auto remove after 5 seconds
  setTimeout(() => {
    notification.style.animation = "notificationSlideOut 0.3s ease";
    setTimeout(() => {
      if (notification.parentNode) {
        notification.remove();
      }
    }, 300);
  }, 5000);
}

function getNotificationColor(type) {
  const colors = {
    success: "#28a745",
    error: "#dc3545",
    info: "#007bff",
  };
  return colors[type] || "#007bff";
}

// Debug: Check if elements exist
console.log("Review form:", document.getElementById("reviewForm"));
console.log("Comment textarea:", document.getElementById("comment"));
console.log("Star rating container:", document.getElementById("stars"));