// Today's Ride JavaScript
document.addEventListener("DOMContentLoaded", function () {
  console.log("Today's Ride page loaded");

  // Initialize auto clock
  updateClock();
  setInterval(updateClock, 1000);

  // Keep the "time until" and status info updated
  updateTimeInfos();
  setInterval(updateTimeInfos, 60000); // update every minute

  // Load passengers for driver rides
  loadPassengersForDriverRides();

  // Start time check for ride start buttons
  checkRideStartTimes();
});

function updateClock() {
  const now = new Date();
  const opts = { hour: "numeric", minute: "2-digit", hour12: true };
  const el = document.getElementById("currentTime");
  if (el) el.textContent = now.toLocaleTimeString([], opts);
}

function updateTimeInfos() {
  const now = new Date();
  const rideCards = document.querySelectorAll(".ride-card");

  rideCards.forEach((card) => {
    const departure = card.dataset.departureTime; // e.g. "14:30:00" or "14:30"
    if (!departure) return;

    const parts = departure.split(":").map((p) => parseInt(p, 10));
    const depDate = new Date(
      now.getFullYear(),
      now.getMonth(),
      now.getDate(),
      parts[0] || 0,
      parts[1] || 0,
      parts[2] || 0,
      0
    );

    const diffMs = depDate - now;
    const diffMinutes = Math.max(0, Math.floor(diffMs / 60000));

    const timeInfoEl = card.querySelector(".ride-time .time-info");
    if (diffMinutes > 0) {
      const hours = Math.floor(diffMinutes / 60);
      const minutes = diffMinutes % 60;
      let txt = "(";
      if (hours > 0) txt += `${hours} hour${hours > 1 ? "s" : ""} `;
      txt += `${minutes} minute${minutes > 1 ? "s" : ""}`;
      txt += ")";
      if (timeInfoEl) {
        timeInfoEl.textContent =
          " (Starts in " + txt.replace(/[()]/g, "") + ")";
      } else {
        const rideTimeSpan = card.querySelector(".ride-time");
        if (rideTimeSpan) {
          const span = document.createElement("span");
          span.className = "time-info";
          span.textContent = " (Starts in " + txt.replace(/[()]/g, "") + ")";
          rideTimeSpan.appendChild(span);
        }
      }
    } else {
      if (timeInfoEl) timeInfoEl.remove();
    }

    const statusBadge = card.querySelector(".status-badge");
    const status = card.dataset.status;
    const startBtn = card.querySelector(".btn-start-ride");
    if (status === "available") {
      if (diffMinutes <= 0) {
        if (statusBadge) {
          statusBadge.className = "status-badge status-available";
          statusBadge.innerHTML =
            '<i class="fa-solid fa-circle"></i> Available';
        }
        if (startBtn) {
          startBtn.disabled = false;
          startBtn.classList.remove("disabled");
          startBtn.onclick = function () {
            startRide(card.dataset.rideId);
          };
          startBtn.title = "Start Ride";
        }
      } else {
        if (statusBadge) {
          statusBadge.className = "status-badge status-not-yet-started";
          statusBadge.innerHTML =
            '<i class="fa-solid fa-circle"></i> Not Yet Started';
        }
        if (startBtn) {
          startBtn.disabled = true;
          startBtn.classList.add("disabled");
          startBtn.title =
            "Ride starts at " +
            depDate.toLocaleTimeString([], {
              hour: "numeric",
              minute: "2-digit",
              hour12: true,
            });
          startBtn.onclick = null;
        }
      }
    }
  });

  checkRideStartTimes();
}

function checkRideStartTimes() {
  const rideCards = document.querySelectorAll(
    ".ride-card[data-status='available']"
  );

  rideCards.forEach((card) => {
    const departureTime = card.dataset.departureTime;
    const currentTime = new Date();
    const departureDateTime = new Date();

    const [hours, minutes] = departureTime.split(":");
    departureDateTime.setHours(parseInt(hours), parseInt(minutes), 0, 0);

    const startBtn = card.querySelector(".btn-start-ride.disabled");
    if (startBtn) {
      if (currentTime >= departureDateTime) {
        startBtn.disabled = false;
        startBtn.className = "btn btn-start-ride";
        startBtn.innerHTML = '<i class="fa-solid fa-play"></i> Start Ride';
        startBtn.onclick = function () {
          startRide(card.dataset.rideId);
        };

        const statusBadge = card.querySelector(".status-badge");
        if (
          statusBadge &&
          statusBadge.classList.contains("status-not-yet-started")
        ) {
          statusBadge.className = "status-badge status-available";
          statusBadge.innerHTML =
            '<i class="fa-solid fa-circle"></i> Available';
        }

        const timeInfo = card.querySelector(".time-info");
        if (timeInfo) {
          timeInfo.remove();
        }
      }
    }
  });
}

function startRide(rideId) {
  const rideCard = document.querySelector(`[data-ride-id="${rideId}"]`);
  if (!rideCard) return;

  const departureTime = rideCard.dataset.departureTime;
  const currentTime = new Date();
  const departureDateTime = new Date();

  const [hours, minutes] = departureTime.split(":");
  departureDateTime.setHours(parseInt(hours), parseInt(minutes), 0, 0);

  if (currentTime < departureDateTime) {
    showNotification(
      "Cannot start ride before scheduled departure time!",
      "error"
    );
    return;
  }

  if (!confirm("Are you sure you want to start this ride?")) {
    return;
  }

  const startBtn = rideCard.querySelector(".btn-start-ride");
  const endBtn = rideCard.querySelector(".btn-end-ride");
  const originalStartText = startBtn.innerHTML;

  startBtn.innerHTML =
    '<i class="fa-solid fa-spinner fa-spin"></i> Starting...';
  startBtn.disabled = true;

  const formData = new FormData();
  formData.append("action", "start_ride");
  formData.append("ride_id", rideId);

  fetch("../database/todaysridedb.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        startBtn.style.display = "none";
        endBtn.disabled = false;
        endBtn.style.display = "inline-flex";

        const statusBadge = rideCard.querySelector(".status-badge");
        if (statusBadge) {
          statusBadge.innerHTML =
            '<i class="fa-solid fa-circle"></i> In Progress';
          statusBadge.className = "status-badge status-in-progress";
        }

        rideCard.dataset.status = "in_progress";

        updatePassengerActionsForStartedRide(rideId);

        showNotification("Ride started successfully!", "success");

        const passengersSection = document.getElementById(
          `passengers-${rideId}`
        );
        if (passengersSection && passengersSection.style.display !== "none") {
          loadPassengers(rideId);
        }
      } else {
        showNotification(data.message || "Failed to start ride", "error");
        startBtn.innerHTML = originalStartText;
        startBtn.disabled = false;
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showNotification("An error occurred while starting the ride", "error");
      startBtn.innerHTML = originalStartText;
      startBtn.disabled = false;
    });
}

function loadPassengersForDriverRides() {
  const driverRides = document.querySelectorAll(".ride-card .driver-actions");

  driverRides.forEach((rideCard) => {
    const rideId = rideCard.closest(".ride-card").dataset.rideId;
    loadPassengers(rideId);
  });
}

function loadPassengers(rideId) {
  const passengersSection = document.getElementById(`passengers-${rideId}`);
  const passengersList = passengersSection?.querySelector(".passengers-list");

  if (!passengersList) return;

  passengersList.innerHTML = `
        <div class="loading-passengers">
            <i class="fa-solid fa-spinner fa-spin"></i>
            Loading passengers...
        </div>
    `;

  fetch(`../database/todaysridedb.php?action=get_passengers&ride_id=${rideId}`)
    .then((response) => response.json())
    .then((data) => {
      if (data.success && data.passengers.length > 0) {
        let passengersHTML = "";

        data.passengers.forEach((passenger) => {
          passengersHTML += `
                        <div class="passenger-item">
                            <div class="passenger-info">
                                <div class="passenger-avatar">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                                <div class="passenger-details">
                                    <h5>${passenger.PassengerName}</h5>
                                    <p>${passenger.Seats} seat${
            passenger.Seats > 1 ? "s" : ""
          }</p>
                                </div>
                            </div>
                            <span class="passenger-status ${
                              passenger.PaymentStatus === "paid"
                                ? "status-paid"
                                : "status-unpaid"
                            }">
                                ${
                                  passenger.PaymentStatus === "paid"
                                    ? "Paid"
                                    : "Unpaid"
                                }
                            </span>
                        </div>
                    `;
        });

        passengersList.innerHTML = passengersHTML;
      } else {
        passengersList.innerHTML = `
                    <div class="no-passengers">
                        <i class="fa-solid fa-users-slash"></i>
                        <p>No passengers yet</p>
                    </div>
                `;
      }
    })
    .catch((error) => {
      console.error("Error loading passengers:", error);
      passengersList.innerHTML = `
                <div class="error-loading">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                    <p>Failed to load passengers</p>
                </div>
            `;
    });
}

function updatePassengerActionsForStartedRide(rideId) {
  const rideCard = document.querySelector(`[data-ride-id="${rideId}"]`);
  if (!rideCard) return;

  const passengerActions = rideCard.querySelector(".passenger-actions");
  if (!passengerActions) return;

  const paymentBtn = passengerActions.querySelector(".btn-payment");
  if (paymentBtn) {
    paymentBtn.style.display = "none";
  }

  const hasConfirmedBooking =
    passengerActions.querySelector(".status-available-text") ||
    passengerActions.querySelector(".btn-payment");

  if (hasConfirmedBooking) {
    passengerActions.innerHTML = `
            <span class="status-in-progress-text">Ride in Progress</span>
            <button class="btn btn-outline" onclick="contactDriver('Driver')">
                <i class="fa-solid fa-phone"></i>
                Contact Driver
            </button>
        `;
  }
}

function endRide(rideId) {
  if (!confirm("Are you sure you want to end this ride?")) {
    return;
  }

  const rideCard = document.querySelector(`[data-ride-id="${rideId}"]`);
  if (!rideCard) return;

  const endBtn = rideCard.querySelector(".btn-end-ride");
  const originalEndText = endBtn.innerHTML;

  endBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Ending...';
  endBtn.disabled = true;

  const formData = new FormData();
  formData.append("action", "end_ride");
  formData.append("ride_id", rideId);

  fetch("../database/todaysridedb.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        endBtn.style.display = "none";

        const statusBadge = rideCard.querySelector(".status-badge");
        if (statusBadge) {
          statusBadge.innerHTML =
            '<i class="fa-solid fa-circle"></i> Completed';
          statusBadge.className = "status-badge status-completed";
        }

        rideCard.dataset.status = "completed";

        updateAllPassengerActionsForCompletedRide(rideId);

        showNotification(
          "Ride completed successfully! Passengers can now make payment.",
          "success"
        );

        const passengersSection = document.getElementById(
          `passengers-${rideId}`
        );
        if (passengersSection && passengersSection.style.display !== "none") {
          loadPassengers(rideId);
        }
      } else {
        showNotification(data.message || "Failed to end ride", "error");
        endBtn.innerHTML = originalEndText;
        endBtn.disabled = false;
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showNotification("An error occurred while ending the ride", "error");
      endBtn.innerHTML = originalEndText;
      endBtn.disabled = false;
    });
}

function updateAllPassengerActionsForCompletedRide(rideId) {
  const allRideCards = document.querySelectorAll(".ride-card");

  allRideCards.forEach((card) => {
    if (card.dataset.rideId === rideId.toString()) {
      const passengerActions = card.querySelector(".passenger-actions");
      if (passengerActions) {
        const bookingIdFromCard = card.dataset.bookingId;
        if (bookingIdFromCard) {
          passengerActions.innerHTML = `
            <span class="status-pending-payment">Payment Due</span>
            <a class="btn btn-payment" href="payment.php?booking_id=${bookingIdFromCard}">
              <i class="fa-solid fa-credit-card"></i>
              Make Payment
            </a>
            <button class="btn btn-outline" onclick="contactDriver('Driver')">
              <i class="fa-solid fa-phone"></i>
              Contact Driver
            </button>
          `;
        }
      }
    }
  });
}

function viewPassengers(rideId) {
  const passengersSection = document.getElementById(`passengers-${rideId}`);

  if (
    passengersSection.style.display === "none" ||
    !passengersSection.style.display
  ) {
    passengersSection.style.display = "block";
    loadPassengers(rideId);
  } else {
    passengersSection.style.display = "none";
  }
}

function bookRide(rideId) {
  if (confirm("Book this ride?")) {
    window.location.href = `../php/booking.php?ride_id=${rideId}`;
  }
}

function cancelBooking(bookingId) {
  if (confirm("Are you sure you want to cancel this booking request?")) {
    const formData = new FormData();
    formData.append("action", "cancel_booking");
    formData.append("booking_id", bookingId);

    fetch("../database/todaysridedb.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          window.location.reload();
          showNotification("Booking request cancelled!", "success");
        } else {
          showNotification(data.message || "Failed to cancel booking", "error");
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        showNotification("An error occurred while cancelling booking", "error");
      });
  }
}

function contactDriver(driverName) {
  alert(
    `Please contact driver "${driverName}" via their registered phone number. You can find their contact information in your booking details or dashboard.`
  );
}

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
    `;
  document.head.appendChild(style);
}

window.startRide = startRide;
window.endRide = endRide;
window.viewPassengers = viewPassengers;
window.bookRide = bookRide;
window.cancelBooking = cancelBooking;
window.contactDriver = contactDriver;
window.showNotification = showNotification;

setInterval(checkRideStartTimes, 60000);

console.log("Today's Ride JavaScript loaded successfully");
