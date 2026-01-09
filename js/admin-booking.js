// Export CSV functionality
document.addEventListener("DOMContentLoaded", function () {
  console.log("Admin Booking JS loaded");

  // Export button
  const exportCsvBtn = document.getElementById("exportCsvBtn");
  const exportLoading = document.getElementById("exportLoading");
  const filterForm = document.getElementById("filterForm");

  if (exportCsvBtn) {
    exportCsvBtn.addEventListener("click", function () {
      exportBookingsToCSV();
    });
  }

  function exportBookingsToCSV() {
    if (!exportCsvBtn || !exportLoading) return;

    // Disable button and show loading
    exportCsvBtn.disabled = true;
    exportCsvBtn.classList.add("exporting");
    exportCsvBtn.innerHTML =
      '<i class="fa-solid fa-spinner fa-spin"></i> Exporting...';
    exportLoading.style.display = "block";

    // Get current filter parameters
    const formData = new FormData(filterForm);
    const params = new URLSearchParams();

    // Add all filter parameters
    for (const [key, value] of formData) {
      if (value) {
        params.append(key, value);
      }
    }

    // Create export form
    const exportForm = document.createElement("form");
    exportForm.method = "POST";
    exportForm.action = "admin-booking.php";
    exportForm.style.display = "none";

    // Add hidden inputs for filter parameters
    for (const [key, value] of params) {
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = key;
      input.value = value;
      exportForm.appendChild(input);
    }

    // Add export action
    const actionInput = document.createElement("input");
    actionInput.type = "hidden";
    actionInput.name = "action";
    actionInput.value = "export_csv";
    exportForm.appendChild(actionInput);

    // Add CSRF token if available (you might want to add this for security)
    const csrfToken = document.querySelector('input[name="csrf_token"]');
    if (csrfToken) {
      exportForm.appendChild(csrfToken.cloneNode());
    }

    document.body.appendChild(exportForm);

    // Submit form to trigger export
    exportForm.submit();

    // Remove form after submission
    setTimeout(() => {
      document.body.removeChild(exportForm);

      // Re-enable button after 3 seconds (in case of error)
      setTimeout(() => {
        if (exportCsvBtn) {
          exportCsvBtn.disabled = false;
          exportCsvBtn.classList.remove("exporting");
          exportCsvBtn.innerHTML =
            '<i class="fa-solid fa-file-export"></i> Export CSV';
          exportLoading.style.display = "none";
        }
      }, 3000);
    }, 100);
  }

  // Keyboard shortcut for export (Ctrl+E)
  document.addEventListener("keydown", function (e) {
    if (e.ctrlKey && e.key === "e") {
      e.preventDefault();
      if (exportCsvBtn && !exportCsvBtn.disabled) {
        exportBookingsToCSV();
      }
    }
  });

  // Existing code remains the same...
  // Elements
  const modal = document.getElementById("statusModal");
  const statusForm = document.getElementById("statusForm");
  const newStatusSelect = document.getElementById("new_status");
  const statusOptions = document.getElementById("statusOptions");
  const currentStatusDisplay = document.getElementById("currentStatusDisplay");
  const statusHelp = document.getElementById("statusHelp");
  const cancellationSection = document.getElementById("cancellationSection");
  const cancellationReasonTextarea = document.getElementById(
    "cancellation_reason"
  );
  const closeModalBtns = document.querySelectorAll(
    ".close-modal, .close-modal-btn"
  );
  const closeNotificationBtns = document.querySelectorAll(
    ".close-notification"
  );
  const searchForm = document.querySelector(".search-filter-form");

  // Status transition rules (existing code)
  const statusTransitions = {
    Pending: [
      {
        value: "Confirmed",
        label: "Confirmed",
        description: "Driver has accepted booking",
        icon: "fa-check",
      },
      {
        value: "Cancelled",
        label: "Cancelled",
        description: "Cancel this booking",
        icon: "fa-times",
      },
    ],
    Confirmed: [
      {
        value: "Paid",
        label: "Paid",
        description: "Payment has been received",
        icon: "fa-money-bill-wave",
      },
      {
        value: "Cancelled",
        label: "Cancelled",
        description: "Cancel confirmed booking",
        icon: "fa-times",
      },
    ],
    Paid: [
      {
        value: "Completed",
        label: "Completed",
        description: "Ride has been completed",
        icon: "fa-flag-checkered",
      },
      {
        value: "Cancelled",
        label: "Cancelled",
        description: "Cancel paid booking",
        icon: "fa-times",
      },
    ],
    Completed: [],
    Cancelled: [],
  };

  // Action buttons using event delegation (existing code)
  document.addEventListener("click", function (e) {
    // Update status button
    if (e.target.closest(".update-btn")) {
      const btn = e.target.closest(".update-btn");
      const bookingId = btn.dataset.id;
      const currentStatus = btn.dataset.currentStatus;
      console.log(
        "Update status clicked for booking:",
        bookingId,
        "Current status:",
        currentStatus
      );
      showStatusModal(bookingId, currentStatus, false);
    }
  });

  // Show status modal (existing code)
  function showStatusModal(bookingId, currentStatus, isCancelAction = false) {
    const modalBookingId = document.getElementById("modalBookingId");
    modalBookingId.value = bookingId;

    // Reset form
    statusForm.reset();
    statusOptions.innerHTML = "";
    newStatusSelect.innerHTML = '<option value="">-- Select Status --</option>';
    cancellationSection.style.display = "none";

    // Remove required attribute initially
    if (cancellationReasonTextarea) {
      cancellationReasonTextarea.removeAttribute("required");
    }

    // Display current status
    currentStatusDisplay.textContent = currentStatus;
    currentStatusDisplay.className = `status-${currentStatus.toLowerCase()}`;

    // Get available transitions
    const transitions = statusTransitions[currentStatus] || [];

    if (transitions.length === 0) {
      statusHelp.textContent = "This booking cannot be updated further.";
      statusHelp.style.color = "#dc3545";
      statusHelp.style.display = "block";
      return;
    }

    // Filter transitions if it's a cancel action
    let filteredTransitions = transitions;
    if (isCancelAction) {
      filteredTransitions = transitions.filter((t) => t.value === "Cancelled");
      if (filteredTransitions.length === 0) {
        statusHelp.textContent =
          "This booking cannot be cancelled from its current status.";
        statusHelp.style.color = "#dc3545";
        statusHelp.style.display = "block";
        return;
      }
    }

    // Add options to select
    filteredTransitions.forEach((transition) => {
      const option = document.createElement("option");
      option.value = transition.value;
      option.textContent = transition.label;
      newStatusSelect.appendChild(option);
    });

    // Create visual options
    filteredTransitions.forEach((transition) => {
      const optionDiv = document.createElement("div");
      optionDiv.className = "status-option";
      optionDiv.dataset.value = transition.value;

      optionDiv.innerHTML = `
                <div class="status-icon ${transition.value.toLowerCase()}">
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

        // Show/hide cancellation reason
        if (transition.value === "Cancelled") {
          cancellationSection.style.display = "block";
          if (cancellationReasonTextarea) {
            cancellationReasonTextarea.setAttribute("required", "required");
          }
        } else {
          cancellationSection.style.display = "none";
          if (cancellationReasonTextarea) {
            cancellationReasonTextarea.removeAttribute("required");
          }
        }
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

      // Show/hide cancellation reason
      if (this.value === "Cancelled") {
        cancellationSection.style.display = "block";
        if (cancellationReasonTextarea) {
          cancellationReasonTextarea.setAttribute("required", "required");
        }
      } else {
        cancellationSection.style.display = "none";
        if (cancellationReasonTextarea) {
          cancellationReasonTextarea.removeAttribute("required");
        }
      }
    });

    // Auto-select first option if available
    if (filteredTransitions.length > 0) {
      newStatusSelect.value = filteredTransitions[0].value;
      statusOptions.firstChild.classList.add("selected");

      // Show/hide cancellation reason for initial selection
      if (filteredTransitions[0].value === "Cancelled") {
        cancellationSection.style.display = "block";
        if (cancellationReasonTextarea) {
          cancellationReasonTextarea.setAttribute("required", "required");
        }
      }
    }

    modal.classList.add("show");
    document.body.style.overflow = "hidden";
    console.log("Status modal shown");
  }

  // Close modal (existing code)
  closeModalBtns.forEach((btn) => {
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      modal.classList.remove("show");
      document.body.style.overflow = "";

      // Reset required attribute
      if (cancellationReasonTextarea) {
        cancellationReasonTextarea.removeAttribute("required");
      }
    });
  });

  // Close on outside click (existing code)
  modal.addEventListener("click", function (e) {
    if (e.target === modal) {
      modal.classList.remove("show");
      document.body.style.overflow = "";

      // Reset required attribute
      if (cancellationReasonTextarea) {
        cancellationReasonTextarea.removeAttribute("required");
      }
    }
  });

  // Form submission (existing code)
  statusForm.addEventListener("submit", function (e) {
    console.log("Status form submission started");

    const newStatus = newStatusSelect.value;
    if (!newStatus) {
      e.preventDefault();
      alert("Please select a new status.");
      return false;
    }

    // Validate cancellation reason if cancelling
    if (newStatus === "Cancelled") {
      const reason = cancellationReasonTextarea.value.trim();
      if (!reason) {
        e.preventDefault();
        alert("Please provide a cancellation reason.");
        cancellationReasonTextarea.focus();
        return false;
      }

      if (reason.length < 10) {
        e.preventDefault();
        alert(
          "Please provide a more detailed cancellation reason (at least 10 characters)."
        );
        cancellationReasonTextarea.focus();
        return false;
      }
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

  // Close notifications (existing code)
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

  // Auto-close notifications (existing code)
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

  // Search form validation (existing code)
  if (searchForm) {
    searchForm.addEventListener("submit", function (e) {
      const searchInput = this.querySelector('input[name="search"]');
      const dateFrom = this.querySelector('input[name="date_from"]');
      const dateTo = this.querySelector('input[name="date_to"]');
      const minPrice = this.querySelector('input[name="min_price"]');
      const maxPrice = this.querySelector('input[name="max_price"]');

      // Validate search input
      if (
        searchInput.value.trim().length > 0 &&
        searchInput.value.trim().length < 2
      ) {
        e.preventDefault();
        alert("Please enter at least 2 characters to search.");
        searchInput.focus();
        return false;
      }

      // Validate date range
      if (dateFrom.value && dateTo.value) {
        const fromDate = new Date(dateFrom.value);
        const toDate = new Date(dateTo.value);

        if (fromDate > toDate) {
          e.preventDefault();
          alert("'From Date' cannot be after 'To Date'.");
          dateFrom.focus();
          return false;
        }
      }

      // Validate price range
      if (minPrice.value && maxPrice.value) {
        const min = parseFloat(minPrice.value);
        const max = parseFloat(maxPrice.value);

        if (isNaN(min) || isNaN(max)) {
          e.preventDefault();
          alert("Please enter valid numbers for price range.");
          minPrice.focus();
          return false;
        }

        if (min > max) {
          e.preventDefault();
          alert("Minimum price cannot be greater than maximum price.");
          minPrice.focus();
          return false;
        }

        if (min < 0 || max < 0) {
          e.preventDefault();
          alert("Price cannot be negative.");
          minPrice.focus();
          return false;
        }
      }

      return true;
    });
  }

  // Keyboard shortcuts (existing code)
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && modal.classList.contains("show")) {
      modal.classList.remove("show");
      document.body.style.overflow = "";

      // Reset required attribute
      if (cancellationReasonTextarea) {
        cancellationReasonTextarea.removeAttribute("required");
      }
    }

    if (e.ctrlKey && e.key === "f") {
      e.preventDefault();
      const searchInput = document.querySelector(".search-box input");
      if (searchInput) {
        searchInput.focus();
      }
    }
  });

  // Add data-labels for responsive table (existing code)
  if (window.innerWidth <= 768) {
    const tableCells = document.querySelectorAll(".bookings-table td");
    const headers = [
      "Booking & User",
      "Ride Details",
      "Payment Info",
      "Date & Time",
      "Status",
      "Actions",
    ];

    tableCells.forEach((cell, index) => {
      const headerIndex = index % headers.length;
      cell.setAttribute("data-label", headers[headerIndex]);
    });
  }

  // Window resize handler for responsive table (existing code)
  window.addEventListener("resize", function () {
    const tableCells = document.querySelectorAll(".bookings-table td");
    const headers = [
      "Booking & User",
      "Ride Details",
      "Payment Info",
      "Date & Time",
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

  // Auto-refresh recent bookings indicator (existing code)
  function updateRecentBookings() {
    const bookingTimes = document.querySelectorAll(".datetime-info .time");
    const currentTime = new Date();

    bookingTimes.forEach((timeElement, index) => {
      const row = timeElement.closest("tr");
      if (!row) return;

      const timeText = timeElement.textContent.trim();
      const dateText = row.querySelector(".date").textContent.trim();

      // Parse date and time (simplified - would need proper parsing)
      // For now, just remove 'recent' class after 1 hour
      if (row.classList.contains("recent-booking")) {
        // Check if it's still recent
        // In a real app, you'd compare actual times
        // For demo, we'll keep it simple
      }
    });
  }

  // Update every minute (existing code)
  setInterval(updateRecentBookings, 60000);

  // Payment proof viewer enhancement (existing code)
  document.addEventListener("click", function (e) {
    if (e.target.closest(".proof-link")) {
      e.preventDefault();
      const link = e.target.closest(".proof-link");
      const proofUrl = link.getAttribute("href");

      // Open in modal or new tab
      window.open(proofUrl, "_blank");
    }
  });

  console.log("Booking management page initialized with export functionality");
});
