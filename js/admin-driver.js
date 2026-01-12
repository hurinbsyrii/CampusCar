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
  const modalDriverId = document.getElementById("modalDriverId");
  const modalAction = document.getElementById("modalAction");
  const closeModalBtns = document.querySelectorAll(
    ".close-modal, .close-modal-btn"
  );

  // Initial Setup
  if (rejectionReasonTextarea)
    rejectionReasonTextarea.removeAttribute("required");

  // Event Delegation for Buttons
  document.addEventListener("click", function (e) {
    // Approve
    if (e.target.closest(".approve-btn")) {
      const driverId = e.target.closest(".approve-btn").dataset.id;
      showModal("approve", driverId);
    }
    // Reject
    if (e.target.closest(".reject-btn")) {
      const driverId = e.target.closest(".reject-btn").dataset.id;
      showModal("reject", driverId);
    }
    // Revoke (Updated Logic)
    if (e.target.closest(".revoke-btn")) {
      const btn = e.target.closest(".revoke-btn");
      const driverId = btn.dataset.id;

      // Disable button & show loading text sementara check database
      const originalText = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking...';

      checkDriverActivity(driverId, btn, originalText);
    }
  });

  // Function: Check Activity via AJAX
  function checkDriverActivity(driverId, btnElement, originalText) {
    const formData = new FormData();
    formData.append("action", "check_activity");
    formData.append("driver_id", driverId);

    fetch("admin-driver.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        // Reset button state
        btnElement.disabled = false;
        btnElement.innerHTML = originalText;

        // Show Revoke Modal with dynamic data
        showRevokeModal(driverId, data.active_rides, data.active_bookings);
      })
      .catch((error) => {
        console.error("Error:", error);
        btnElement.disabled = false;
        btnElement.innerHTML = originalText;
        alert("Failed to check driver activity. Please try again.");
      });
  }

  // Function: Show Revoke Modal Specific
  function showRevokeModal(driverId, activeRides, activeBookings) {
    modalDriverId.value = driverId;
    modalAction.value = "revoke";
    actionForm.reset();

    rejectionSection.style.display = "none";
    approvalSection.style.display = "block";
    rejectionReasonTextarea.removeAttribute("required");

    modalTitle.textContent = "Revoke Driver Access";
    modalSubmitBtn.textContent = "Confirm Revoke";
    modalSubmitBtn.className = "btn-primary";
    // Tukar warna button jadi merah sebab action bahaya
    modalSubmitBtn.style.backgroundColor = "#dc3545";
    modalSubmitBtn.style.borderColor = "#dc3545";

    let messageHtml = "";

    if (activeRides > 0) {
      // Warning Message kalau ada active ride/booking
      messageHtml = `
            <div class="confirmation-message">
                <i class="fa-solid fa-triangle-exclamation" style="color: #dc3545; font-size: 3rem; margin-bottom: 15px;"></i>
                <h4 style="color: #dc3545; margin-bottom: 10px;">Warning: Active Commitments!</h4>
                <p style="margin-bottom: 15px;">
                    This driver currently has <strong>${activeRides} active ride(s)</strong> 
                    and <strong>${activeBookings} active booking(s)</strong>.
                </p>
                <div style="background: #fff3cd; padding: 10px; border-radius: 6px; border: 1px solid #ffeeba; color: #856404; font-size: 0.9rem; text-align: left;">
                    <strong>If you proceed:</strong>
                    <ul style="margin-left: 20px; margin-top: 5px;">
                        <li>All active rides will be <strong>cancelled</strong>.</li>
                        <li>All bookings will be cancelled.</li>
                        <li>Affected passengers will receive a <strong>notification</strong>.</li>
                        <li>The driver will be removed (become regular user).</li>
                    </ul>
                </div>
                <p style="margin-top: 15px;">Are you sure you want to proceed?</p>
            </div>
        `;
    } else {
      // Safe Message kalau tak ada ride
      messageHtml = `
            <div class="confirmation-message">
                <i class="fa-solid fa-user-slash" style="color: #ffc107; font-size: 3rem; margin-bottom: 15px;"></i>
                <p>Are you sure you want to revoke this driver's access?</p>
                <p style="font-size: 0.9rem; color: #666;">They will be downgraded to a regular passenger and won't be able to offer rides anymore.</p>
            </div>
        `;
    }

    approvalSection.innerHTML = messageHtml;
    modal.classList.add("show");
    document.body.style.overflow = "hidden";
  }

  // Function: Show Standard Modal (Approve/Reject)
  function showModal(action, driverId) {
    modalDriverId.value = driverId;
    modalAction.value = action;
    actionForm.reset();

    rejectionSection.style.display = "none";
    approvalSection.style.display = "none";

    // Reset button style
    modalSubmitBtn.style.backgroundColor = "";
    modalSubmitBtn.style.borderColor = "";

    if (rejectionReasonTextarea)
      rejectionReasonTextarea.removeAttribute("required");

    if (action === "approve") {
      modalTitle.textContent = "Approve Driver";
      modalSubmitBtn.textContent = "Approve";
      modalSubmitBtn.className = "btn-primary";
      approvalSection.style.display = "block";
      approvalSection.innerHTML = `
        <div class="confirmation-message">
            <i class="fa-solid fa-check-circle" style="color: #28a745;"></i>
            <p>Approve this driver? They will be able to offer rides immediately.</p>
        </div>`;
    } else if (action === "reject") {
      modalTitle.textContent = "Reject Application";
      modalSubmitBtn.textContent = "Reject";
      modalSubmitBtn.className = "btn-primary";
      // Button merah untuk reject
      modalSubmitBtn.style.backgroundColor = "#dc3545";

      rejectionSection.style.display = "block";
      if (rejectionReasonTextarea)
        rejectionReasonTextarea.setAttribute("required", "required");
    }

    modal.classList.add("show");
    document.body.style.overflow = "hidden";
  }

  // Close Modal Logic
  closeModalBtns.forEach((btn) => {
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      modal.classList.remove("show");
      document.body.style.overflow = "";
    });
  });

  window.onclick = function (event) {
    if (event.target == modal) {
      modal.classList.remove("show");
      document.body.style.overflow = "";
    }
  };
});
