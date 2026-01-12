// My Bookings JavaScript
document.addEventListener("DOMContentLoaded", function () {
  console.log("My Bookings page loaded");

  initializeBookingTabs();
  initializeBookingActions();
});

// Initialize tab functionality
function initializeBookingTabs() {
  const tabBtns = document.querySelectorAll(".tab-btn");
  const tabContents = document.querySelectorAll(".tab-content");

  console.log(
    `Found ${tabBtns.length} tab buttons and ${tabContents.length} tab contents`
  );

  tabBtns.forEach((btn) => {
    btn.addEventListener("click", function () {
      const targetTab = this.getAttribute("data-tab");
      console.log(`Switching to tab: ${targetTab}`);

      // Remove active class from all tabs and contents
      tabBtns.forEach((b) => b.classList.remove("active"));
      tabContents.forEach((c) => c.classList.remove("active"));

      // Add active class to current tab and content
      this.classList.add("active");
      const targetContent = document.getElementById(targetTab);
      if (targetContent) {
        targetContent.classList.add("active");
      } else {
        console.error(`Tab content with id '${targetTab}' not found`);
      }
    });
  });
}

// Initialize booking action buttons
function initializeBookingActions() {
  // Add loading states to action buttons
  const actionButtons = document.querySelectorAll(".booking-actions .btn");
  actionButtons.forEach((button) => {
    button.addEventListener("click", function (e) {
      if (
        this.classList.contains("btn-cancel") ||
        this.classList.contains("btn-success") ||
        this.classList.contains("btn-danger")
      ) {
        // Let the specific functions handle the actions
        return;
      }

      // For contact buttons, add loading state
      if (
        this.classList.contains("btn-primary") ||
        this.classList.contains("btn-outline")
      ) {
        const originalText = this.innerHTML;
        this.innerHTML =
          '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
        this.disabled = true;

        // Revert after 2 seconds if no response
        setTimeout(() => {
          this.innerHTML = originalText;
          this.disabled = false;
        }, 2000);
      }
    });
  });
}

// Cancel booking function
function cancelBooking(bookingId) {
  if (
    !confirm(
      "Are you sure you want to cancel this booking? This action cannot be undone."
    )
  ) {
    return;
  }

  const bookingCard = document.querySelector(
    `[data-booking-id="${bookingId}"]`
  );
  if (!bookingCard) return;

  // Check if this is a pending booking (should affect the count)
  const statusBadge = bookingCard.querySelector(".status-badge");
  const isPending = statusBadge && statusBadge.textContent.trim() === "Pending";

  // Show loading state
  bookingCard.classList.add("loading");
  const cancelBtn = bookingCard.querySelector(".btn-cancel");
  const originalText = cancelBtn ? cancelBtn.innerHTML : "";

  if (cancelBtn) {
    cancelBtn.innerHTML =
      '<i class="fa-solid fa-spinner fa-spin"></i> Cancelling...';
    cancelBtn.disabled = true;
  }

  // Make AJAX call to cancel booking
  const formData = new FormData();
  formData.append("action", "cancel_booking");
  formData.append("booking_id", bookingId);

  fetch("../database/mybookingsdb.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      bookingCard.classList.remove("loading");

      if (data.success) {
        // Update UI
        const statusBadge = bookingCard.querySelector(".status-badge");
        if (statusBadge) {
          statusBadge.textContent = "CANCELLED";
          statusBadge.className = "status-badge status-cancelled";
        }

        if (cancelBtn) {
          cancelBtn.remove();
        }

        // Show success notification
        showNotification(
          data.message || "Booking cancelled successfully!",
          "success"
        );

        // Update tab count only if it was a pending booking
        if (isPending) {
          updateTabCount("passenger-bookings", -1);
        }

        // IMPORTANT: Show message about Today's Rides
        showNotification(
          "This booking will no longer appear in Today's Rides.",
          "info"
        );
      } else {
        // Show error message
        showNotification(data.message || "Failed to cancel booking", "error");

        // Reset button
        if (cancelBtn) {
          cancelBtn.innerHTML = originalText;
          cancelBtn.disabled = false;
        }
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      bookingCard.classList.remove("loading");
      showNotification(
        "An error occurred while cancelling the booking",
        "error"
      );

      // Reset button
      if (cancelBtn) {
        cancelBtn.innerHTML = originalText;
        cancelBtn.disabled = false;
      }
    });
}

// Update booking status (for drivers)
function updateBookingStatus(bookingId, newStatus) {
  const action = newStatus === "Confirmed" ? "confirm" : "reject";
  if (!confirm(`Are you sure you want to ${action} this booking request?`)) {
    return;
  }

  const bookingCard = document.querySelector(
    `[data-booking-id="${bookingId}"]`
  );
  if (!bookingCard) return;

  // Check if this is a pending booking (should affect the count)
  const statusBadge = bookingCard.querySelector(".status-badge");
  const isPending = statusBadge && statusBadge.textContent.trim() === "Pending";

  // Show loading state
  bookingCard.classList.add("loading");

  // Disable all action buttons
  const actionButtons = bookingCard.querySelectorAll(
    ".btn-success, .btn-danger, .btn-outline"
  );
  const originalTexts = {};
  actionButtons.forEach((btn) => {
    originalTexts[btn.className] = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
    btn.disabled = true;
  });

  // Make AJAX call to update booking status
  const formData = new FormData();
  formData.append("action", "update_booking_status");
  formData.append("booking_id", bookingId);
  formData.append("status", newStatus);

  fetch("../database/mybookingsdb.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      bookingCard.classList.remove("loading");

      if (data.success) {
        // Update UI
        const statusBadge = bookingCard.querySelector(".status-badge");
        if (statusBadge) {
          statusBadge.textContent = newStatus.toUpperCase();
          statusBadge.className = `status-badge status-${newStatus.toLowerCase()}`;
        }

        // Remove action buttons for Pending status
        const confirmRejectButtons = bookingCard.querySelectorAll(
          ".btn-success, .btn-danger"
        );
        confirmRejectButtons.forEach((btn) => btn.remove());

        // Re-enable contact button
        const contactBtn = bookingCard.querySelector(".btn-outline");
        if (contactBtn) {
          contactBtn.innerHTML =
            originalTexts["btn btn-outline"] ||
            '<i class="fa-solid fa-phone"></i> Contact';
          contactBtn.disabled = false;
        }

        // Show success notification
        const message =
          data.message ||
          (newStatus === "Confirmed"
            ? "Booking confirmed successfully!"
            : "Booking request rejected!");
        showNotification(message, "success");

        // Update tab count only if it was a pending booking
        if (isPending) {
          updateTabCount("driver-bookings", -1);
        }
      } else {
        // Show error message
        showNotification(
          data.message || `Failed to ${action} booking`,
          "error"
        );

        // Reset buttons
        actionButtons.forEach((btn) => {
          const btnClass = Array.from(btn.classList)
            .filter((c) => c.startsWith("btn-"))
            .join(" ");
          btn.innerHTML = originalTexts[btnClass] || "Error";
          btn.disabled = false;
        });
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      bookingCard.classList.remove("loading");
      showNotification(
        "An error occurred while processing your request",
        "error"
      );

      // Reset buttons
      actionButtons.forEach((btn) => {
        const btnClass = Array.from(btn.classList)
          .filter((c) => c.startsWith("btn-"))
          .join(" ");
        btn.innerHTML = originalTexts[btnClass] || "Error";
        btn.disabled = false;
      });
    });
}

// Contact functions
function contactDriver(phoneNumber) {
  if (confirm(`Call driver at ${phoneNumber}?`)) {
    window.location.href = `tel:${phoneNumber}`;
  }
}

function contactPassenger(phoneNumber) {
  if (confirm(`Call passenger at ${phoneNumber}?`)) {
    window.location.href = `tel:${phoneNumber}`;
  }
}

// Update tab count
function updateTabCount(tabId, change) {
  const tabBtn = document.querySelector(`[data-tab="${tabId}"]`);
  if (!tabBtn) return;

  const countElement = tabBtn.querySelector(".tab-count");
  if (countElement) {
    let currentCount = parseInt(countElement.textContent) || 0;
    currentCount += change;

    // Ensure count doesn't go below 0
    currentCount = Math.max(0, currentCount);

    if (currentCount <= 0) {
      countElement.style.display = "none";
      countElement.textContent = "0";
    } else {
      countElement.style.display = "inline-block";
      countElement.textContent = currentCount;
    }
  }
}

// Notification system
function showNotification(message, type = "info") {
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
        z-index: 1000;
        animation: slideInRight 0.3s ease;
        max-width: 400px;
        font-weight: 500;
    `;

  document.body.appendChild(notification);

  setTimeout(() => {
    notification.style.animation = "slideOutRight 0.3s ease";
    setTimeout(() => {
      if (notification.parentNode) {
        notification.remove();
      }
    }, 300);
  }, 5000);
}

function getNotificationColor(type) {
  const colors = {
    success: "var(--success-color)",
    error: "var(--error-color)",
    info: "var(--primary-color)",
    warning: "var(--warning-color)",
  };
  return colors[type] || "var(--primary-color)";
}

// Add CSS animations for notifications if not already present
if (!document.querySelector("#notification-styles")) {
  const style = document.createElement("style");
  style.id = "notification-styles";
  style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 1000;
        animation: slideInRight 0.3s ease;
        max-width: 400px;
        font-weight: 500;
        color: white;
    }
    
    .notification.success {
        background: var(--success-color);
    }
    
    .notification.error {
        background: var(--error-color);
    }
    
    .notification.info {
        background: var(--primary-color);
    }
    
    .notification.warning {
        background: var(--warning-color);
    }
  `;
  document.head.appendChild(style);
}

// Debug helper function
function debugTabs() {
  console.log("=== Debugging Tabs ===");
  const tabBtns = document.querySelectorAll(".tab-btn");
  const tabContents = document.querySelectorAll(".tab-content");

  console.log("Tab buttons:", tabBtns);
  console.log("Tab contents:", tabContents);

  tabBtns.forEach((btn, index) => {
    console.log(`Tab ${index}:`, {
      text: btn.textContent,
      dataTab: btn.getAttribute("data-tab"),
      isActive: btn.classList.contains("active"),
      onClick: btn.onclick,
    });
  });

  tabContents.forEach((content, index) => {
    console.log(`Content ${index}:`, {
      id: content.id,
      isActive: content.classList.contains("active"),
      display: content.style.display,
    });
  });
}

// Export functions for global access
window.cancelBooking = cancelBooking;
window.updateBookingStatus = updateBookingStatus;
window.contactDriver = contactDriver;
window.contactPassenger = contactPassenger;
window.showNotification = showNotification;
window.debugTabs = debugTabs;

console.log("My Bookings JavaScript loaded successfully");
