// Real-time clock
function updateClock() {
  const now = new Date();
  const options = {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hour12: true,
  };
  const timeString = now.toLocaleDateString("en-US", options);
  document.getElementById("currentTime").textContent = timeString;
}

// Initialize clock
updateClock();
setInterval(updateClock, 1000);

// Sidebar toggle
document.addEventListener("DOMContentLoaded", function () {
  const sidebarToggle = document.getElementById("sidebarToggle");
  const sidebar = document.querySelector(".admin-sidebar");
  const mainContent = document.querySelector(".admin-main");

  if (sidebarToggle) {
    sidebarToggle.addEventListener("click", function () {
      sidebar.classList.toggle("collapsed");
      mainContent.classList.toggle("sidebar-collapsed");

      // Save state to localStorage
      const isCollapsed = sidebar.classList.contains("collapsed");
      localStorage.setItem("sidebarCollapsed", isCollapsed);
    });
  }

  // Load saved sidebar state
  const savedState = localStorage.getItem("sidebarCollapsed");
  if (savedState === "true") {
    sidebar.classList.add("collapsed");
    mainContent.classList.add("sidebar-collapsed");
  }
});

// Chart refresh function (if needed)
function refreshCharts() {
  console.log("Refreshing dashboard data...");
  // This could be extended to fetch new data via AJAX
}

// Auto-refresh every 5 minutes
setInterval(refreshCharts, 300000);

// Notification system
function showAdminNotification(message, type = "info") {
  const notification = document.createElement("div");
  notification.className = `admin-notification ${type}`;

  const icons = {
    success: "fa-check-circle",
    error: "fa-exclamation-circle",
    warning: "fa-exclamation-triangle",
    info: "fa-info-circle",
  };

  notification.innerHTML = `
        <i class="fa-solid ${icons[type] || icons.info}"></i>
        <span>${message}</span>
        <button class="close-notification"><i class="fa-solid fa-times"></i></button>
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
        gap: 12px;
        z-index: 9999;
        animation: slideInRight 0.3s ease;
        max-width: 400px;
        font-weight: 500;
    `;

  document.body.appendChild(notification);

  // Close button
  notification
    .querySelector(".close-notification")
    .addEventListener("click", function () {
      notification.remove();
    });

  // Auto remove after 5 seconds
  setTimeout(() => {
    if (notification.parentNode) {
      notification.style.animation = "slideOutRight 0.3s ease";
      setTimeout(() => {
        if (notification.parentNode) {
          notification.remove();
        }
      }, 300);
    }
  }, 5000);
}

function getNotificationColor(type) {
  const colors = {
    success: "var(--success-color)",
    error: "var(--error-color)",
    warning: "var(--warning-color)",
    info: "var(--primary-color)",
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
    
    .admin-sidebar.collapsed {
        width: 80px;
    }
    
    .admin-sidebar.collapsed .admin-details,
    .admin-sidebar.collapsed .nav-link span {
        display: none;
    }
    
    .admin-sidebar.collapsed .nav-link {
        justify-content: center;
        padding: 15px;
    }
    
    .admin-main.sidebar-collapsed {
        margin-left: 80px;
    }
    
    .close-notification {
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        padding: 0;
        margin-left: 10px;
        opacity: 0.8;
        transition: opacity 0.2s;
    }
    
    .close-notification:hover {
        opacity: 1;
    }
`;
document.head.appendChild(style);

// Export data function
function exportDashboardData() {
  const data = {
    timestamp: new Date().toISOString(),
    url: window.location.href,
  };

  const blob = new Blob([JSON.stringify(data, null, 2)], {
    type: "application/json",
  });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `dashboard-data-${new Date().toISOString().slice(0, 10)}.json`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);

  showAdminNotification("Dashboard data exported successfully!", "success");
}

// Keyboard shortcuts
document.addEventListener("keydown", function (e) {
  // Ctrl + R to refresh
  if (e.ctrlKey && e.key === "r") {
    e.preventDefault();
    refreshCharts();
    showAdminNotification("Dashboard refreshed!", "info");
  }

  // Ctrl + E to export
  if (e.ctrlKey && e.key === "e") {
    e.preventDefault();
    exportDashboardData();
  }

  // Ctrl + B to toggle sidebar
  if (e.ctrlKey && e.key === "b") {
    e.preventDefault();
    document.getElementById("sidebarToggle")?.click();
  }
});

// Initialize tooltips
function initTooltips() {
  const tooltipElements = document.querySelectorAll("[data-tooltip]");

  tooltipElements.forEach((element) => {
    element.addEventListener("mouseenter", function (e) {
      const tooltip = document.createElement("div");
      tooltip.className = "admin-tooltip";
      tooltip.textContent = this.getAttribute("data-tooltip");
      document.body.appendChild(tooltip);

      const rect = this.getBoundingClientRect();
      tooltip.style.left =
        rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + "px";
      tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + "px";

      this._tooltip = tooltip;
    });

    element.addEventListener("mouseleave", function () {
      if (this._tooltip) {
        this._tooltip.remove();
        this._tooltip = null;
      }
    });
  });
}

// Call init functions when DOM is loaded
document.addEventListener("DOMContentLoaded", function () {
  initTooltips();
  console.log("Admin Dashboard initialized successfully");
});
