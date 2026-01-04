// Global Variables
let currentDriverId = null;
let currentPage = 1;
let itemsPerPage = 10;

// Initialize Dashboard
document.addEventListener("DOMContentLoaded", function () {
  // Get driver ID from session (in real app, this would come from PHP session)
  currentDriverId =
    document.querySelector(".driver-id")?.textContent.split(": ")[1] || 1;

  // Initialize navigation
  initNavigation();

  // Load overview data
  loadOverviewData();

  // Initialize charts
  initializeCharts();

  // Load notifications count
  loadNotificationsCount();

  // Setup modal
  setupModal();
});

// Navigation
function initNavigation() {
  const navItems = document.querySelectorAll(".nav-item");

  navItems.forEach((item) => {
    item.addEventListener("click", function () {
      // Remove active class from all items
      navItems.forEach((i) => i.classList.remove("active"));

      // Add active class to clicked item
      this.classList.add("active");

      // Get target section
      const targetId = this.getAttribute("data-target");

      // Hide all sections
      document.querySelectorAll(".dashboard-section").forEach((section) => {
        section.classList.remove("active");
      });

      // Show target section
      document.getElementById(targetId).classList.add("active");

      // Update page title
      updatePageTitle(targetId);

      // Load section data
      switch (targetId) {
        case "overview":
          loadOverviewData();
          break;
        case "rides":
          loadRideHistory();
          break;
        case "earnings":
          loadEarningsData();
          break;
        case "notifications":
          loadNotifications();
          break;
        case "profile":
          loadProfileData();
          break;
      }
    });
  });
}

function updatePageTitle(sectionId) {
  const titles = {
    overview: "Dashboard Overview",
    rides: "Ride History",
    earnings: "Earnings & Payments",
    notifications: "Notifications",
    profile: "Driver Profile",
  };

  document.getElementById("page-title").textContent =
    titles[sectionId] || "Dashboard";
}

// Modal Functions
function setupModal() {
  const createBtn = document.getElementById("createRideBtn");
  const modal = document.getElementById("createRideModal");
  const form = document.getElementById("createRideForm");

  if (createBtn) {
    createBtn.addEventListener("click", () => {
      modal.style.display = "flex";
      // Set minimum date to today
      document.getElementById("rideDate").min = new Date()
        .toISOString()
        .split("T")[0];
    });
  }

  // Close modal when clicking outside
  window.addEventListener("click", (e) => {
    if (e.target === modal) {
      closeModal();
    }
  });

  // Handle form submission
  if (form) {
    form.addEventListener("submit", handleCreateRide);
  }
}

function closeModal() {
  document.getElementById("createRideModal").style.display = "none";
  document.getElementById("createRideForm").reset();
}

async function handleCreateRide(e) {
  e.preventDefault();

  const formData = {
    driverId: currentDriverId,
    fromLocation: document.getElementById("fromLocation").value,
    toLocation: document.getElementById("toLocation").value,
    rideDate: document.getElementById("rideDate").value,
    departureTime: document.getElementById("departureTime").value,
    availableSeats: document.getElementById("availableSeats").value,
    pricePerSeat: document.getElementById("pricePerSeat").value,
    description: document.getElementById("rideDescription").value,
    femaleOnly: document.getElementById("femaleOnly").checked ? 1 : 0,
  };

  try {
    const response = await fetch("driverdashboarddb.php?action=createRide", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(formData),
    });

    const result = await response.json();

    if (result.success) {
      alert("Ride created successfully!");
      closeModal();
      loadOverviewData(); // Refresh data
      loadRideHistory(); // Refresh ride history
    } else {
      alert("Error creating ride: " + result.message);
    }
  } catch (error) {
    console.error("Error:", error);
    alert("An error occurred while creating the ride.");
  }
}

// Data Loading Functions
async function loadOverviewData() {
  try {
    const response = await fetch(
      `driverdashboarddb.php?action=overview&driverId=${currentDriverId}`
    );
    const data = await response.json();

    if (data.success) {
      // Update stats
      document.getElementById("total-rides").textContent =
        data.stats.totalRides;
      document.getElementById("active-rides").textContent =
        data.stats.activeRides;
      document.getElementById("completed-rides").textContent =
        data.stats.completedRides;
      document.getElementById(
        "total-earnings"
      ).textContent = `RM ${data.stats.totalEarnings.toFixed(2)}`;

      // Update charts
      updateCharts(data.charts);

      // Update recent activities
      updateRecentActivities(data.recentActivities);
    }
  } catch (error) {
    console.error("Error loading overview data:", error);
  }
}

async function loadRideHistory(page = 1) {
  const status = document.getElementById("status-filter").value;
  const date = document.getElementById("date-filter").value;

  try {
    const response = await fetch(
      `driverdashboarddb.php?action=rideHistory&driverId=${currentDriverId}&page=${page}&status=${status}&date=${date}`
    );
    const data = await response.json();

    if (data.success) {
      updateRideHistoryTable(data.rides);
      updatePagination(data.totalPages, page);
    }
  } catch (error) {
    console.error("Error loading ride history:", error);
  }
}

async function loadEarningsData() {
  try {
    const response = await fetch(
      `driverdashboarddb.php?action=earnings&driverId=${currentDriverId}`
    );
    const data = await response.json();

    if (data.success) {
      // Update earnings summary
      document.getElementById(
        "total-earnings-summary"
      ).textContent = `RM ${data.summary.totalEarnings.toFixed(2)}`;
      document.getElementById(
        "monthly-earnings"
      ).textContent = `RM ${data.summary.monthlyEarnings.toFixed(2)}`;
      document.getElementById(
        "average-earnings"
      ).textContent = `RM ${data.summary.averagePerRide.toFixed(2)}`;

      // Update payments table
      updatePaymentsTable(data.recentPayments);

      // Update earnings trend chart
      updateEarningsTrendChart(data.earningsTrend);
    }
  } catch (error) {
    console.error("Error loading earnings data:", error);
  }
}

async function loadNotifications() {
  try {
    const response = await fetch(
      `driverdashboarddb.php?action=notifications&driverId=${currentDriverId}`
    );
    const data = await response.json();

    if (data.success) {
      updateNotificationsList(data.notifications);
      updateNotificationCount(data.unreadCount);
    }
  } catch (error) {
    console.error("Error loading notifications:", error);
  }
}

async function loadNotificationsCount() {
  try {
    const response = await fetch(
      `driverdashboarddb.php?action=notificationCount&driverId=${currentDriverId}`
    );
    const data = await response.json();

    if (data.success) {
      updateNotificationCount(data.count);
    }
  } catch (error) {
    console.error("Error loading notifications count:", error);
  }
}

async function loadProfileData() {
  try {
    const response = await fetch(
      `driverdashboarddb.php?action=profile&driverId=${currentDriverId}`
    );
    const data = await response.json();

    if (data.success) {
      updateProfileInfo(data.profile);
    }
  } catch (error) {
    console.error("Error loading profile data:", error);
  }
}

// Chart Functions
function initializeCharts() {
  // Earnings Chart
  window.earningsChart = new Chart(
    document.getElementById("earningsChart").getContext("2d"),
    {
      type: "line",
      data: {
        labels: [],
        datasets: [
          {
            label: "Earnings (RM)",
            data: [],
            borderColor: "#3498db",
            backgroundColor: "rgba(52, 152, 219, 0.1)",
            tension: 0.4,
            fill: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
      },
    }
  );

  // Gender Distribution Chart
  window.genderChart = new Chart(
    document.getElementById("genderChart").getContext("2d"),
    {
      type: "doughnut",
      data: {
        labels: ["Male", "Female"],
        datasets: [
          {
            data: [50, 50],
            backgroundColor: ["#3498db", "#e74c3c"],
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
      },
    }
  );

  // Faculty Distribution Chart
  window.facultyChart = new Chart(
    document.getElementById("facultyChart").getContext("2d"),
    {
      type: "bar",
      data: {
        labels: [],
        datasets: [
          {
            label: "Passengers",
            data: [],
            backgroundColor: "#2ecc71",
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
      },
    }
  );

  // Payment Methods Chart
  window.paymentChart = new Chart(
    document.getElementById("paymentChart").getContext("2d"),
    {
      type: "pie",
      data: {
        labels: [],
        datasets: [
          {
            data: [],
            backgroundColor: ["#9b59b6", "#3498db", "#e74c3c", "#f1c40f"],
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
      },
    }
  );
}

function updateCharts(chartData) {
  // Update earnings chart
  if (chartData.earnings && earningsChart) {
    earningsChart.data.labels = chartData.earnings.labels;
    earningsChart.data.datasets[0].data = chartData.earnings.data;
    earningsChart.update();
  }

  // Update gender chart
  if (chartData.gender && genderChart) {
    genderChart.data.datasets[0].data = chartData.gender;
    genderChart.update();
  }

  // Update faculty chart
  if (chartData.faculty && facultyChart) {
    facultyChart.data.labels = chartData.faculty.labels;
    facultyChart.data.datasets[0].data = chartData.faculty.data;
    facultyChart.update();
  }

  // Update payment chart
  if (chartData.payment && paymentChart) {
    paymentChart.data.labels = chartData.payment.labels;
    paymentChart.data.datasets[0].data = chartData.payment.data;
    paymentChart.update();
  }
}

function updateEarningsTrendChart(trendData) {
  // This would use ApexCharts for a more advanced visualization
  const options = {
    series: [
      {
        name: "Earnings",
        data: trendData.data || [],
      },
    ],
    chart: {
      height: 350,
      type: "line",
      zoom: {
        enabled: false,
      },
    },
    dataLabels: {
      enabled: false,
    },
    stroke: {
      curve: "smooth",
    },
    xaxis: {
      categories: trendData.labels || [],
    },
    colors: ["#3498db"],
  };

  const chart = new ApexCharts(
    document.querySelector("#earningsTrendChart"),
    options
  );
  chart.render();
}

// Update UI Functions
function updateRecentActivities(activities) {
  const container = document.getElementById("recent-activities");
  if (!container) return;

  if (activities.length === 0) {
    container.innerHTML = '<p class="no-data">No recent activities</p>';
    return;
  }

  container.innerHTML = activities
    .map(
      (activity) => `
        <div class="activity-item">
            <div class="activity-icon">
                <i class="fas ${getActivityIcon(activity.type)}"></i>
            </div>
            <div class="activity-content">
                <p class="activity-text">${activity.message}</p>
                <span class="activity-time">${activity.time}</span>
            </div>
        </div>
    `
    )
    .join("");
}

function updateRideHistoryTable(rides) {
  const tbody = document.getElementById("rides-table-body");
  if (!tbody) return;

  if (rides.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="7" class="no-data">No rides found</td></tr>';
    return;
  }

  tbody.innerHTML = rides
    .map(
      (ride) => `
        <tr>
            <td>${ride.date} ${ride.time}</td>
            <td>${ride.from}</td>
            <td>${ride.to}</td>
            <td>${ride.passengers}</td>
            <td>RM ${ride.earnings}</td>
            <td><span class="status-badge ${ride.status}">${
        ride.status
      }</span></td>
            <td>
                <button class="action-btn view-btn" onclick="viewRideDetails(${
                  ride.id
                })">
                    <i class="fas fa-eye"></i>
                </button>
                ${
                  ride.status === "available"
                    ? `
                <button class="action-btn edit-btn" onclick="editRide(${ride.id})">
                    <i class="fas fa-edit"></i>
                </button>
                `
                    : ""
                }
            </td>
        </tr>
    `
    )
    .join("");
}

function updatePaymentsTable(payments) {
  const tbody = document.getElementById("payments-table-body");
  if (!tbody) return;

  if (payments.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="5" class="no-data">No payments found</td></tr>';
    return;
  }

  tbody.innerHTML = payments
    .map(
      (payment) => `
        <tr>
            <td>${payment.date}</td>
            <td>#${payment.bookingId}</td>
            <td>RM ${payment.amount}</td>
            <td>${payment.method}</td>
            <td><span class="status-badge ${payment.status}">${payment.status}</span></td>
        </tr>
    `
    )
    .join("");
}

function updateNotificationsList(notifications) {
  const container = document.getElementById("notifications-list");
  if (!container) return;

  if (notifications.length === 0) {
    container.innerHTML = '<p class="no-data">No notifications</p>';
    return;
  }

  container.innerHTML = notifications
    .map(
      (notification) => `
        <div class="notification-item ${
          notification.unread ? "unread" : ""
        }" onclick="viewNotification(${notification.id})">
            <div class="notification-icon">
                <i class="fas ${getNotificationIcon(notification.type)}"></i>
            </div>
            <div class="notification-content">
                <h4>${notification.title}</h4>
                <p>${notification.message}</p>
                <span class="notification-time">${notification.time}</span>
            </div>
            ${notification.unread ? '<span class="unread-dot"></span>' : ""}
        </div>
    `
    )
    .join("");
}

function updateNotificationCount(count) {
  const badge = document.getElementById("notification-count");
  if (badge) {
    badge.textContent = count;
    badge.style.display = count > 0 ? "block" : "none";
  }
}

function updateProfileInfo(profile) {
  const container = document.getElementById("driver-details");
  if (!container) return;

  container.innerHTML = `
        <div class="detail-item">
            <span class="detail-label">License Number:</span>
            <span class="detail-value">${profile.licenseNumber}</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Car Model:</span>
            <span class="detail-value">${profile.carModel}</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Car Plate:</span>
            <span class="detail-value">${profile.carPlate}</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Bank Account:</span>
            <span class="detail-value">${profile.bankName} - ${profile.accountNumber}</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Email:</span>
            <span class="detail-value">${profile.email}</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Phone:</span>
            <span class="detail-value">${profile.phone}</span>
        </div>
    `;
}

function updatePagination(totalPages, currentPage) {
  const container = document.getElementById("pagination-controls");
  if (!container) return;

  let html = '<div class="pagination-buttons">';

  if (currentPage > 1) {
    html += `<button onclick="loadRideHistory(${
      currentPage - 1
    })">Previous</button>`;
  }

  for (let i = 1; i <= totalPages; i++) {
    if (i === currentPage) {
      html += `<button class="active">${i}</button>`;
    } else {
      html += `<button onclick="loadRideHistory(${i})">${i}</button>`;
    }
  }

  if (currentPage < totalPages) {
    html += `<button onclick="loadRideHistory(${
      currentPage + 1
    })">Next</button>`;
  }

  html += "</div>";
  container.innerHTML = html;
}

// Helper Functions
function getActivityIcon(type) {
  const icons = {
    booking: "fa-calendar-check",
    payment: "fa-money-bill-wave",
    ride: "fa-car",
    notification: "fa-bell",
    default: "fa-circle",
  };
  return icons[type] || icons.default;
}

function getNotificationIcon(type) {
  const icons = {
    success: "fa-check-circle",
    info: "fa-info-circle",
    warning: "fa-exclamation-triangle",
    error: "fa-exclamation-circle",
    default: "fa-bell",
  };
  return icons[type] || icons.default;
}

function applyFilters() {
  loadRideHistory(1);
}

function resetFilters() {
  document.getElementById("status-filter").value = "all";
  document.getElementById("date-filter").value = "";
  loadRideHistory(1);
}

function markAllAsRead() {
  fetch(
    `driverdashboarddb.php?action=markAllRead&driverId=${currentDriverId}`,
    {
      method: "POST",
    }
  )
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        loadNotifications();
        loadNotificationsCount();
      }
    });
}

function viewRideDetails(rideId) {
  // Implementation for viewing ride details
  alert(`View ride details for ID: ${rideId}`);
}

function editRide(rideId) {
  // Implementation for editing a ride
  alert(`Edit ride with ID: ${rideId}`);
}

function viewNotification(notificationId) {
  // Implementation for viewing notification
  fetch(
    `driverdashboarddb.php?action=markAsRead&notificationId=${notificationId}`,
    {
      method: "POST",
    }
  ).then(() => {
    loadNotifications();
    loadNotificationsCount();
  });
}

function editProfile() {
  // Implementation for editing profile
  alert("Edit profile functionality would go here");
}

function changePassword() {
  // Implementation for changing password
  alert("Change password functionality would go here");
}

function logout() {
  if (confirm("Are you sure you want to logout?")) {
    window.location.href = "logout.php";
  }
}
