document.addEventListener("DOMContentLoaded", function () {
  console.log("Admin User JS loaded");

  // Elements
  const modal = document.getElementById("actionModal");
  const modalTitle = document.getElementById("modalTitle");
  const modalSubmitBtn = document.getElementById("modalSubmitBtn");
  const toggleSection = document.getElementById("toggleSection");
  const deleteSection = document.getElementById("deleteSection");
  const toggleMessage = document.getElementById("toggleMessage");
  const deleteMessage = document.getElementById("deleteMessage");
  const actionForm = document.getElementById("actionForm");
  const refreshBtn = document.getElementById("refreshBtn");
  const closeModalBtns = document.querySelectorAll(
    ".close-modal, .close-modal-btn"
  );
  const closeNotificationBtns = document.querySelectorAll(
    ".close-notification"
  );
  const searchForm = document.querySelector(".search-filter-form");

  // Action buttons using event delegation
  document.addEventListener("click", function (e) {
    // View button
    if (e.target.closest(".view-btn")) {
      const btn = e.target.closest(".view-btn");
      const userId = btn.dataset.id;
      console.log("View clicked for user:", userId);
      alert("View user details feature would show here.\nUser ID: " + userId);
    }

    // Toggle role button
    if (e.target.closest(".toggle-btn")) {
      const btn = e.target.closest(".toggle-btn");
      const userId = btn.dataset.id;
      const currentRole = btn.dataset.currentRole;
      const userName = btn.dataset.name;
      console.log(
        "Toggle clicked for user:",
        userId,
        "Current role:",
        currentRole
      );
      showToggleModal(userId, currentRole, userName);
    }

    // --- UPDATED: Delete button logic ---
    if (e.target.closest(".delete-btn")) {
      const btn = e.target.closest(".delete-btn");
      const userId = btn.dataset.id;
      const userName = btn.dataset.name;

      console.log("Delete clicked for user:", userId);

      // Call the NEW function to check status before showing modal
      checkAndShowDeleteModal(userId, userName);
    }
  });

  // Show toggle modal
  function showToggleModal(userId, currentRole, userName) {
    const modalDriverId = document.getElementById("modalUserId");
    const modalAction = document.getElementById("modalAction");

    modalDriverId.value = userId;
    modalAction.value = "toggle_status";

    // Reset form
    actionForm.reset();
    toggleSection.style.display = "block";
    deleteSection.style.display = "none";

    const newRole = currentRole === "admin" ? "user" : "admin";
    const newRoleDisplay =
      newRole === "admin" ? "Administrator" : "Regular User";

    modalTitle.textContent = "Change User Role";
    toggleMessage.textContent = `Are you sure you want to change ${userName}'s role from ${currentRole} to ${newRoleDisplay}?`;
    modalSubmitBtn.textContent = `Change to ${newRoleDisplay}`;
    modalSubmitBtn.className = "btn-primary";

    modal.classList.add("show");
    document.body.style.overflow = "hidden";
    console.log("Toggle modal shown");
  }

  // --- NEW: Check Active Status & Show Modal (Replaces old showDeleteModal) ---
  function checkAndShowDeleteModal(userId, userName) {
    // Set basic modal values first
    document.getElementById("modalUserId").value = userId;
    document.getElementById("modalAction").value = "delete";

    // Show loading state on modal title while checking
    modalTitle.textContent = "Checking User Status...";

    // Prepare Form Data for AJAX
    const formData = new FormData();
    formData.append("action", "check_activity");
    formData.append("user_id", userId);

    fetch("admin-user.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        // Configure Modal UI
        toggleSection.style.display = "none";
        deleteSection.style.display = "block";
        modalSubmitBtn.className = "btn-primary";
        modalSubmitBtn.style.backgroundColor = "var(--rejected-color)";
        modalTitle.textContent = "Delete User";

        if (data.total_active > 0) {
          // WARNING STATE: User has active bookings/rides
          deleteMessage.innerHTML = `
                    <strong style="color: #dc3545; display:block; margin-bottom:10px;">Warning: Active Items Detected!</strong>
                    ${userName} has <strong>${data.active_rides} active rides</strong> (as driver) and <strong>${data.active_bookings} active bookings</strong>.<br><br>
                    Deleting this user will automatically <strong>CANCEL</strong> these rides/bookings and send notifications to the affected passengers.<br><br>
                    Are you sure you want to proceed?
                `;
        } else {
          // NORMAL STATE: Safe to delete
          deleteMessage.textContent = `Are you sure you want to permanently delete ${userName}? This action cannot be undone.`;
        }

        modalSubmitBtn.textContent = "Confirm Delete";
        modal.classList.add("show");
        document.body.style.overflow = "hidden";
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("Failed to check user status. Please try again.");
      });
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
  actionForm.addEventListener("submit", function (e) {
    console.log("Form submission started");

    const action = document.getElementById("modalAction").value;

    if (action === "delete") {
      const confirmCheck = document.querySelector(
        'input[name="confirm_delete"]'
      );
      if (!confirmCheck.checked) {
        e.preventDefault();
        alert("Please confirm deletion by checking the checkbox.");
        return false;
      }
    }

    // Show loading state
    modalSubmitBtn.disabled = true;
    modalSubmitBtn.innerHTML =
      '<i class="fa-solid fa-spinner loading"></i> Processing...';
    console.log("Form submitting with action:", action);

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
  });

  // Add data-labels for responsive table
  if (window.innerWidth <= 768) {
    const tableCells = document.querySelectorAll(".users-table td");
    const headers = [
      "User Info",
      "Contact Details",
      "Academic Info",
      "Role",
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
    const tableCells = document.querySelectorAll(".users-table td");
    const headers = [
      "User Info",
      "Contact Details",
      "Academic Info",
      "Role",
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

  console.log("User management page initialized");
});
