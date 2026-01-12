document.addEventListener("DOMContentLoaded", function () {
  console.log("Admin Rides JS loaded");

  // Elements
  const modal = document.getElementById("statusModal");
  const statusForm = document.getElementById("statusForm");
  const newStatusSelect = document.getElementById("new_status");
  const statusOptions = document.getElementById("statusOptions");
  const currentStatusDisplay = document.getElementById("currentStatusDisplay");
  const statusHelp = document.getElementById("statusHelp");
  const closeModalBtns = document.querySelectorAll(
    ".close-modal, .close-modal-btn"
  );
  const closeNotificationBtns = document.querySelectorAll(
    ".close-notification"
  );
  const searchForm = document.querySelector(".search-filter-form");
  const exportCsvBtn = document.getElementById("exportCsvBtn");
  const exportLoading = document.getElementById("exportLoading");

  // Status transition rules
  const statusTransitions = {
    available: [
      {
        value: "in_progress",
        label: "In Progress",
        description: "Ride has started",
        icon: "fa-play",
      },
      {
        value: "cancelled",
        label: "Cancelled",
        description: "Cancel this ride",
        icon: "fa-times",
      },
    ],
    in_progress: [
      {
        value: "completed",
        label: "Completed",
        description: "Ride finished successfully",
        icon: "fa-check",
      },
      {
        value: "cancelled",
        label: "Cancelled",
        description: "Cancel ongoing ride",
        icon: "fa-times",
      },
    ],
    completed: [
      {
        value: "in_progress",
        label: "In Progress",
        description: "Re-open ride (Undo completion)",
        icon: "fa-undo",
      },
      {
        value: "cancelled",
        label: "Cancelled",
        description: "Invalidate this completed ride",
        icon: "fa-times",
      },
    ],
    cancelled: [],
    expired: [],
  };

  // Action buttons using event delegation
  document.addEventListener("click", function (e) {
    // View button
    if (e.target.closest(".view-btn")) {
      const btn = e.target.closest(".view-btn");
      const rideId = btn.dataset.id;
      console.log("View clicked for ride:", rideId);
      alert("View ride details feature would show here.\nRide ID: " + rideId);
    }

    // Update status button
    if (e.target.closest(".update-btn")) {
      const btn = e.target.closest(".update-btn");
      const rideId = btn.dataset.id;
      const currentStatus = btn.dataset.currentStatus;
      console.log(
        "Update status clicked for ride:",
        rideId,
        "Current status:",
        currentStatus
      );
      showStatusModal(rideId, currentStatus);
    }

    // Bookings button
    if (e.target.closest(".bookings-btn")) {
      const btn = e.target.closest(".bookings-btn");
      const rideId = btn.dataset.id;
      console.log("Bookings clicked for ride:", rideId);
      // Redirect to bookings page filtered by this ride
      window.location.href = `admin-booking.php?ride_id=${rideId}`;
    }
  });

  // CSV Export functionality
  if (exportCsvBtn) {
    exportCsvBtn.addEventListener("click", function () {
      exportRidesToCSV();
    });
  }

  // Export CSV function
  function exportRidesToCSV() {
    console.log("Export CSV clicked");

    // Get current filter values
    const searchInput = document.querySelector('input[name="search"]');
    const statusSelect = document.querySelector('select[name="status"]');
    const dateSelect = document.querySelector('select[name="date"]');
    const femaleOnlySelect = document.querySelector(
      'select[name="female_only"]'
    );

    const searchTerm = searchInput ? searchInput.value : "";
    const statusFilter = statusSelect ? statusSelect.value : "";
    const dateFilter = dateSelect ? dateSelect.value : "";
    const femaleOnlyFilter = femaleOnlySelect ? femaleOnlySelect.value : "";

    // Build export URL with current filters
    let exportUrl = "admin-rides.php?export=csv";

    if (searchTerm) {
      exportUrl += `&search=${encodeURIComponent(searchTerm)}`;
    }
    if (statusFilter) {
      exportUrl += `&status=${encodeURIComponent(statusFilter)}`;
    }
    if (dateFilter) {
      exportUrl += `&date=${encodeURIComponent(dateFilter)}`;
    }
    if (femaleOnlyFilter) {
      exportUrl += `&female_only=${encodeURIComponent(femaleOnlyFilter)}`;
    }

    // Show loading indicator
    if (exportLoading) {
      exportLoading.style.display = "flex";
      document.body.style.overflow = "hidden";
    }

    // Disable export button during export
    if (exportCsvBtn) {
      exportCsvBtn.disabled = true;
      exportCsvBtn.classList.add("exporting");
      exportCsvBtn.innerHTML =
        '<i class="fa-solid fa-spinner"></i> Exporting...';
    }

    console.log("Export URL:", exportUrl);

    // Create hidden iframe for download
    const iframe = document.createElement("iframe");
    iframe.style.display = "none";
    iframe.src = exportUrl;
    document.body.appendChild(iframe);

    // Set timeout to clean up loading state (in case of error)
    const cleanupTimeout = setTimeout(() => {
      cleanupExportState();
      if (exportLoading) {
        exportLoading.style.display = "none";
        document.body.style.overflow = "";
      }
    }, 10000); // 10 seconds timeout

    // Listen for iframe load
    iframe.onload = function () {
      clearTimeout(cleanupTimeout);
      setTimeout(() => {
        cleanupExportState();
        if (exportLoading) {
          exportLoading.style.display = "none";
          document.body.style.overflow = "";
        }

        // Show success message
        showExportNotification("CSV export completed successfully!", "success");

        // Remove iframe
        if (iframe.parentNode) {
          iframe.parentNode.removeChild(iframe);
        }
      }, 1000);
    };

    iframe.onerror = function () {
      clearTimeout(cleanupTimeout);
      cleanupExportState();
      if (exportLoading) {
        exportLoading.style.display = "none";
        document.body.style.overflow = "";
      }

      // Show error message
      showExportNotification(
        "Failed to export CSV. Please try again.",
        "error"
      );

      // Remove iframe
      if (iframe.parentNode) {
        iframe.parentNode.removeChild(iframe);
      }
    };
  }

  // Cleanup export state
  function cleanupExportState() {
    if (exportCsvBtn) {
      exportCsvBtn.disabled = false;
      exportCsvBtn.classList.remove("exporting");
      exportCsvBtn.innerHTML =
        '<i class="fa-solid fa-file-export"></i> Export CSV';
    }
  }

  // Show export notification
  function showExportNotification(message, type) {
    // Remove existing export notifications
    const existingNotifications = document.querySelectorAll(
      ".notification.export-notification"
    );
    existingNotifications.forEach((notification) => notification.remove());

    // Create new notification
    const notification = document.createElement("div");
    notification.className = `notification ${type} export-notification`;
    notification.innerHTML = `
      <i class="fa-solid fa-${
        type === "success" ? "check-circle" : "exclamation-triangle"
      }"></i>
      <span>${message}</span>
      <button class="close-notification"><i class="fa-solid fa-times"></i></button>
    `;

    // Add to DOM
    const ridesSection = document.querySelector(".rides-section");
    if (ridesSection) {
      const firstNotification = ridesSection.querySelector(".notification");
      if (firstNotification) {
        firstNotification.parentNode.insertBefore(
          notification,
          firstNotification.nextSibling
        );
      } else {
        const sectionHeader = ridesSection.querySelector(".section-header");
        if (sectionHeader) {
          sectionHeader.parentNode.insertBefore(
            notification,
            sectionHeader.nextSibling
          );
        }
      }
    }

    // Add close functionality
    const closeBtn = notification.querySelector(".close-notification");
    if (closeBtn) {
      closeBtn.addEventListener("click", function () {
        notification.remove();
      });
    }

    // Auto-remove after 5 seconds
    setTimeout(() => {
      if (notification.parentNode) {
        notification.remove();
      }
    }, 5000);
  }

  // Show status modal
  function showStatusModal(rideId, currentStatus) {
    const modalRideId = document.getElementById("modalRideId");
    modalRideId.value = rideId;

    // Reset form
    statusForm.reset();
    statusOptions.innerHTML = "";
    newStatusSelect.innerHTML = '<option value="">-- Select Status --</option>';

    // Display current status
    currentStatusDisplay.textContent = currentStatus
      .replace("_", " ")
      .toUpperCase();
    currentStatusDisplay.className = `status-${currentStatus.replace(
      "_",
      "-"
    )}`;

    // Get available transitions
    const transitions = statusTransitions[currentStatus] || [];

    if (transitions.length === 0) {
      statusHelp.textContent = "This ride cannot be updated further.";
      statusHelp.style.color = "#dc3545";
      return;
    }

    // Add options to select
    transitions.forEach((transition) => {
      const option = document.createElement("option");
      option.value = transition.value;
      option.textContent = transition.label;
      newStatusSelect.appendChild(option);
    });

    // Create visual options
    transitions.forEach((transition) => {
      const optionDiv = document.createElement("div");
      optionDiv.className = "status-option";
      optionDiv.dataset.value = transition.value;

      optionDiv.innerHTML = `
                <div class="status-icon ${transition.value.replace("_", "-")}">
                    <i class="fa-solid ${transition.icon}"></i>
                </div>
                <div class="status-details">
                    <h4>${transition.label}</h4>
                    <p>${transition.description}</p>
                </div>
                <i class="fa-solid fa-chevron-right"></i>
            `;

      optionDiv.addEventListener("click", function () {
        // Update select
        newStatusSelect.value = transition.value;

        // Update visual selection
        document.querySelectorAll(".status-option").forEach((opt) => {
          opt.classList.remove("selected");
        });
        this.classList.add("selected");
      });

      statusOptions.appendChild(optionDiv);
    });

    // Select change handler
    newStatusSelect.addEventListener("change", function () {
      document.querySelectorAll(".status-option").forEach((opt) => {
        opt.classList.remove("selected");
        if (opt.dataset.value === this.value) {
          opt.classList.add("selected");
        }
      });
    });

    // Auto-select first option if available
    if (transitions.length > 0) {
      newStatusSelect.value = transitions[0].value;
      statusOptions.firstChild.classList.add("selected");
    }

    modal.classList.add("show");
    document.body.style.overflow = "hidden";
    console.log("Status modal shown");
  }

  // Close modal
  closeModalBtns.forEach((btn) => {
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      modal.classList.remove("show");
      document.body.style.overflow = "";
    });
  });

  // Close on outside click
  modal.addEventListener("click", function (e) {
    if (e.target === modal) {
      modal.classList.remove("show");
      document.body.style.overflow = "";
    }
  });

  // Form submission
  statusForm.addEventListener("submit", function (e) {
    console.log("Status form submission started");

    const newStatus = newStatusSelect.value;
    if (!newStatus) {
      e.preventDefault();
      alert("Please select a new status.");
      return false;
    }

    // Show loading state
    const submitBtn = this.querySelector(".btn-primary");
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML =
        '<i class="fa-solid fa-spinner loading"></i> Updating...';
    }

    console.log("Form submitting with new status:", newStatus);
    return true;
  });

  // Close notifications
  closeNotificationBtns.forEach((btn) => {
    btn.addEventListener("click", function () {
      const notification = this.closest(".notification");
      if (notification) {
        notification.style.animation = "slideOut 0.3s ease";
        setTimeout(() => {
          if (notification.parentNode) {
            notification.remove();
          }
        }, 300);
      }
    });
  });

  // Auto-close notifications
  const notifications = document.querySelectorAll(
    ".notification:not(.persistent)"
  );
  notifications.forEach((notification) => {
    setTimeout(() => {
      if (notification.parentNode) {
        notification.style.animation = "slideOut 0.3s ease";
        setTimeout(() => {
          if (notification.parentNode) {
            notification.remove();
          }
        }, 300);
      }
    }, 5000);
  });

  // Search form submission with validation
  if (searchForm) {
    searchForm.addEventListener("submit", function (e) {
      const searchInput = this.querySelector('input[name="search"]');
      if (
        searchInput.value.trim().length > 0 &&
        searchInput.value.trim().length < 2
      ) {
        e.preventDefault();
        alert("Please enter at least 2 characters to search.");
        searchInput.focus();
        return false;
      }
    });
  }

  // Keyboard shortcuts
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && modal.classList.contains("show")) {
      modal.classList.remove("show");
      document.body.style.overflow = "";
    }

    if (e.ctrlKey && e.key === "f") {
      e.preventDefault();
      const searchInput = document.querySelector(".search-box input");
      if (searchInput) {
        searchInput.focus();
      }
    }

    // Ctrl+E for export
    if (e.ctrlKey && e.key === "e") {
      e.preventDefault();
      if (exportCsvBtn && !exportCsvBtn.disabled) {
        exportCsvBtn.click();
      }
    }
  });

  // Add data-labels for responsive table
  if (window.innerWidth <= 768) {
    const tableCells = document.querySelectorAll(".rides-table td");
    const headers = [
      "Ride Details",
      "Driver & Vehicle",
      "Booking Stats",
      // "Date & Time",
      "Status",
      "Actions",
    ];

    tableCells.forEach((cell, index) => {
      const headerIndex = index % headers.length;
      cell.setAttribute("data-label", headers[headerIndex]);
    });
  }

  // Window resize handler for responsive table
  window.addEventListener("resize", function () {
    const tableCells = document.querySelectorAll(".rides-table td");
    const headers = [
      "Ride Details",
      "Driver & Vehicle",
      "Booking Stats",
      // "Date & Time",
      "Status",
      "Actions",
    ];

    if (window.innerWidth <= 768) {
      tableCells.forEach((cell, index) => {
        const headerIndex = index % headers.length;
        cell.setAttribute("data-label", headers[headerIndex]);
      });
    } else {
      tableCells.forEach((cell) => {
        cell.removeAttribute("data-label");
      });
    }
  });

  // Auto-refresh for in-progress rides (every 5 minutes)
  const refreshInterval = 5 * 60 * 1000; // 5 minutes
  setInterval(() => {
    const hasInProgressRides = document.querySelector(".status-in-progress");
    if (hasInProgressRides) {
      console.log("Auto-refreshing for in-progress rides...");
      // Optional: Add AJAX refresh instead of full page reload
      // location.reload();
    }
  }, refreshInterval);

  console.log("Ride management page initialized");
});
