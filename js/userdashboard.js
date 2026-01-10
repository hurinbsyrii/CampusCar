// userdashboard.js
function registerAsDriver() {
  window.location.href = "driverregistration.php";
}

function offerRide() {
  window.location.href = "rideoffer.php";
}

function bookRide(rideId) {
  // Redirect to booking page with ride ID
  window.location.href = `../php/booking.php?ride_id=${rideId}`;
}

// Function: Show error for own ride
function showOwnRideError() {
  showNotification("You cannot book your own ride.", "error");
}

// NEW FUNCTION: Show error for Girls Only ride when user is male
function showGirlsOnlyError() {
  showNotification("This ride is for female passengers only.", "error");
}

// Notification system
function showNotification(message, type) {
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
        z-index: 1000;
        animation: slideInRight 0.3s ease;
        max-width: 400px;
        font-weight: 500;
    `;

  document.body.appendChild(notification);

  // Remove after 5 seconds
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

// Add CSS animations for notifications
const style = document.createElement("style");
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

// Quick filter functions
function filterByDate(filterType) {
  // Clear any specific date input
  document.querySelector('input[name="ride_date"]').value = "";

  // Get existing search params
  const urlParams = new URLSearchParams(window.location.search);
  const fromLocation = urlParams.get("from_location") || "";
  const toLocation = urlParams.get("to_location") || "";

  // Build new URL with filter_type parameter
  let newUrl = "userdashboard.php?";

  // Add location filters if they exist
  if (fromLocation)
    newUrl += `from_location=${encodeURIComponent(fromLocation)}&`;
  if (toLocation) newUrl += `to_location=${encodeURIComponent(toLocation)}&`;

  // Add the filter type
  newUrl += `filter_type=${filterType}`;

  window.location.href = newUrl;
}

function clearFilters() {
  window.location.href = "userdashboard.php";
}

// Additional functionality
document.addEventListener("DOMContentLoaded", function () {
  console.log("CampusCar Dashboard loaded");

  // Sidebar toggle: persist state in localStorage
  const layout = document.querySelector(".dashboard-layout");
  const sidebarToggle = document.getElementById("sidebarToggle");
  const SIDEBAR_KEY = "sidebarHidden";

  function applySidebarState(hidden) {
    if (!layout) return;
    if (hidden) layout.classList.add("sidebar-hidden");
    else layout.classList.remove("sidebar-hidden");
  }

  // initialize from storage
  const saved = localStorage.getItem(SIDEBAR_KEY);
  applySidebarState(saved === "true");

  if (sidebarToggle) {
    sidebarToggle.addEventListener("click", function (e) {
      e.preventDefault();
      const nowHidden = layout.classList.toggle("sidebar-hidden");
      localStorage.setItem(SIDEBAR_KEY, nowHidden ? "true" : "false");
    });
  }

  // Set min date for date input to today
  const dateInput = document.querySelector('input[type="date"]');
  if (dateInput) {
    const today = new Date().toISOString().split("T")[0];
    dateInput.setAttribute("min", today);

    // If user selects a specific date, clear the filter_type
    dateInput.addEventListener("change", function () {
      if (this.value) {
        document.getElementById("filter_type").value = "";
      }
    });
  }

  // Add animation to search form
  const searchForm = document.querySelector(".search-container");
  if (searchForm) {
    setTimeout(() => {
      searchForm.style.transform = "translateY(0)";
      searchForm.style.opacity = "1";
    }, 300);
  }

  // Auto-focus first search input if filters are applied
  const urlParams = new URLSearchParams(window.location.search);
  if (
    urlParams.has("from_location") ||
    urlParams.has("to_location") ||
    urlParams.has("ride_date") ||
    urlParams.has("filter_type")
  ) {
    const firstInput = document.querySelector(".search-input");
    if (firstInput) {
      setTimeout(() => {
        firstInput.focus();
      }, 500);
    }
  }

  // Clear filter_type when typing in location inputs
  document
    .querySelectorAll('input[name="from_location"], input[name="to_location"]')
    .forEach((input) => {
      input.addEventListener("input", function () {
        if (this.value.trim() !== "") {
          document.getElementById("filter_type").value = "";
        }
      });
    });

  // Add smooth scrolling for better UX
  const links = document.querySelectorAll('a[href^="#"]');
  links.forEach((link) => {
    link.addEventListener("click", function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute("href"));
      if (target) {
        target.scrollIntoView({
          behavior: "smooth",
          block: "start",
        });
      }
    });
  });

  // Add loading states to buttons
  // Add loading states to buttons
  const buttons = document.querySelectorAll("button");
  buttons.forEach((button) => {
    button.addEventListener("click", function (e) {
      // Check if it's the search button
      if (this.classList.contains("search-btn")) {
        // UNTUK SEARCH BUTTON:
        // Jangan disable button, sebab ia akan halang form submission.
        // Cuma tukar UI sahaja. Page akan reload, jadi tak perlu revert.
        this.innerHTML =
          '<i class="fa-solid fa-spinner fa-spin"></i> Searching...';
        // PENTING: Jangan letak this.disabled = true di sini!
      } else if (
        this.classList.contains("btn-primary") ||
        this.classList.contains("btn-success") ||
        this.classList.contains("quick-filter-btn")
      ) {
        // UNTUK BUTTON LAIN (AJAX atau Link):
        // Boleh disable seperti biasa
        const originalText = this.innerHTML;
        this.innerHTML =
          '<i class="fa-solid fa-spinner fa-spin"></i> Loading...';
        this.disabled = true;

        // Revert after 3 seconds if still on page
        setTimeout(() => {
          this.innerHTML = originalText;
          this.disabled = false;
        }, 3000);
      }
    });
  });

  // Add tooltip for This Week filter
  const thisWeekBtn = document.querySelector(
    '.quick-filter-btn[onclick*="week"]'
  );
  if (thisWeekBtn) {
    thisWeekBtn.setAttribute(
      "title",
      "Show rides for current week (Monday to Sunday)"
    );
  }
});
