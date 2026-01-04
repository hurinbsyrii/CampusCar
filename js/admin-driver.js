document.addEventListener("DOMContentLoaded", function () {
  console.log("Admin Driver JS loaded");

  // Elements
  const modal = document.getElementById("actionModal");
  const modalTitle = document.getElementById("modalTitle");
  const modalSubmitBtn = document.getElementById("modalSubmitBtn");
  const rejectionSection = document.getElementById("rejectionSection");
  const approvalSection = document.getElementById("approvalSection");
  const actionForm = document.getElementById("actionForm");
  const rejectionReasonTextarea = document.getElementById("rejection_reason");
  const refreshBtn = document.getElementById("refreshBtn");
  const filterBtns = document.querySelectorAll(".filter-btn");
  const closeModalBtns = document.querySelectorAll(
    ".close-modal, .close-modal-btn"
  );
  const closeNotificationBtns = document.querySelectorAll(
    ".close-notification"
  );

  // FIX: Remove required attribute from rejection_reason initially
  if (rejectionReasonTextarea) {
    rejectionReasonTextarea.removeAttribute("required");
  }

  // Filter functionality
  filterBtns.forEach((btn) => {
    btn.addEventListener("click", function () {
      console.log("Filter clicked:", this.dataset.filter);
      const filter = this.dataset.filter;

      filterBtns.forEach((b) => b.classList.remove("active"));
      this.classList.add("active");

      const rows = document.querySelectorAll(".driver-row");
      rows.forEach((row) => {
        if (filter === "all" || row.dataset.status === filter) {
          row.style.display = "";
        } else {
          row.style.display = "none";
        }
      });
    });
  });

  // Action buttons using event delegation
  document.addEventListener("click", function (e) {
    if (e.target.closest(".approve-btn")) {
      const btn = e.target.closest(".approve-btn");
      const driverId = btn.dataset.id;
      console.log("Approve clicked for driver:", driverId);
      showModal("approve", driverId);
    }

    if (e.target.closest(".reject-btn")) {
      const btn = e.target.closest(".reject-btn");
      const driverId = btn.dataset.id;
      console.log("Reject clicked for driver:", driverId);
      showModal("reject", driverId);
    }

    if (e.target.closest(".revoke-btn")) {
      const btn = e.target.closest(".revoke-btn");
      const driverId = btn.dataset.id;
      console.log("Revoke clicked for driver:", driverId);
      showModal("revoke", driverId);
    }

    if (e.target.closest(".view-btn")) {
      const btn = e.target.closest(".view-btn");
      const driverId = btn.dataset.id;
      console.log("View clicked for driver:", driverId);
      // Implement view functionality here
    }
  });

  // Show modal function
  function showModal(action, driverId) {
    console.log("Show modal called:", action, driverId);

    const modalDriverId = document.getElementById("modalDriverId");
    const modalAction = document.getElementById("modalAction");

    modalDriverId.value = driverId;
    modalAction.value = action;

    // Reset form
    actionForm.reset();
    rejectionSection.style.display = "none";
    approvalSection.style.display = "none";

    // FIX: Handle required attribute based on action
    if (rejectionReasonTextarea) {
      if (action === "reject") {
        rejectionReasonTextarea.setAttribute("required", "required");
        rejectionSection.style.display = "block";
      } else {
        rejectionReasonTextarea.removeAttribute("required");
      }
    }

    switch (action) {
      case "approve":
        modalTitle.textContent = "Approve Driver Registration";
        modalSubmitBtn.textContent = "Approve Driver";
        modalSubmitBtn.className = "btn-primary";
        approvalSection.style.display = "block";
        break;

      case "reject":
        modalTitle.textContent = "Reject Driver Registration";
        modalSubmitBtn.textContent = "Reject Application";
        modalSubmitBtn.className = "btn-primary";
        break;

      case "revoke":
        modalTitle.textContent = "Revoke Driver Status";
        modalSubmitBtn.textContent = "Revoke Access";
        modalSubmitBtn.className = "btn-primary";
        approvalSection.innerHTML = `
                    <div class="confirmation-message">
                        <i class="fa-solid fa-exclamation-triangle"></i>
                        <p>Are you sure you want to revoke this driver's access? They will no longer be able to offer rides.</p>
                    </div>
                `;
        approvalSection.style.display = "block";
        break;
    }

    modal.classList.add("show");
    document.body.style.overflow = "hidden";
    console.log("Modal shown");
  }

  // Close modal
  closeModalBtns.forEach((btn) => {
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      modal.classList.remove("show");
      document.body.style.overflow = "";

      // FIX: Reset required attribute when closing modal
      if (rejectionReasonTextarea) {
        rejectionReasonTextarea.removeAttribute("required");
      }
    });
  });

  // Close on outside click
  modal.addEventListener("click", function (e) {
    if (e.target === modal) {
      modal.classList.remove("show");
      document.body.style.overflow = "";

      // FIX: Reset required attribute when closing modal
      if (rejectionReasonTextarea) {
        rejectionReasonTextarea.removeAttribute("required");
      }
    }
  });

  // Form submission - FIXED: Better validation
  actionForm.addEventListener("submit", function (e) {
    console.log("Form submission started");

    const action = document.getElementById("modalAction").value;

    // Custom validation for reject action
    if (action === "reject") {
      const reason = document.getElementById("rejection_reason").value.trim();
      if (!reason) {
        e.preventDefault();
        alert("Please provide a rejection reason.");
        document.getElementById("rejection_reason").focus();
        return false;
      }

      if (reason.length < 10) {
        e.preventDefault();
        alert(
          "Please provide a more detailed rejection reason (at least 10 characters)."
        );
        document.getElementById("rejection_reason").focus();
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

  // Refresh button
  if (refreshBtn) {
    refreshBtn.addEventListener("click", function () {
      this.disabled = true;
      this.innerHTML =
        '<i class="fa-solid fa-spinner loading"></i> Refreshing...';

      setTimeout(() => {
        location.reload();
      }, 1000);
    });
  }

  // Keyboard shortcuts
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && modal.classList.contains("show")) {
      modal.classList.remove("show");
      document.body.style.overflow = "";

      // Reset required attribute
      if (rejectionReasonTextarea) {
        rejectionReasonTextarea.removeAttribute("required");
      }
    }

    if (e.ctrlKey && e.key === "f") {
      e.preventDefault();
      filterBtns[0]?.focus();
    }

    if (e.ctrlKey && e.key === "r") {
      e.preventDefault();
      refreshBtn?.click();
    }
  });

  console.log("Driver management page initialized");
});
