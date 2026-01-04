document.addEventListener("DOMContentLoaded", function () {
  console.log("Logout page loaded - redirecting to home...");

  // Start the logout process
  startLogoutProcess();
});

function startLogoutProcess() {
  // Simulate some processing time for the animation
  setTimeout(() => {
    // Update loading text
    const loadingText = document.querySelector(".loading-text");
    if (loadingText) {
      loadingText.textContent = "Redirecting to home...";
    }

    // Redirect to home page after animation completes
    setTimeout(() => {
      window.location.href = "home.php";
    }, 1000);
  }, 3000); // Wait for 3 seconds to show the animation
}

// Add some interactive elements
document.addEventListener("click", function () {
  // If user clicks anywhere, redirect immediately
  window.location.href = "home.php";
});

// Also redirect if user presses any key
document.addEventListener("keydown", function () {
  window.location.href = "home.php";
});

// Optional: Add a skip button for impatient users
function addSkipButton() {
  const skipBtn = document.createElement("button");
  skipBtn.className = "skip-btn";
  skipBtn.innerHTML = '<i class="fa-solid fa-forward"></i> Skip';
  skipBtn.style.cssText = `
        position: absolute;
        top: 20px;
        right: 20px;
        background: rgba(255,255,255,0.9);
        border: none;
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 0.8rem;
        color: var(--text-color);
        cursor: pointer;
        transition: var(--transition);
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    `;

  skipBtn.addEventListener("mouseenter", function () {
    this.style.background = "var(--primary-color)";
    this.style.color = "white";
  });

  skipBtn.addEventListener("mouseleave", function () {
    this.style.background = "rgba(255,255,255,0.9)";
    this.style.color = "var(--text-color)";
  });

  skipBtn.addEventListener("click", function () {
    window.location.href = "home.php";
  });

  document.querySelector(".logout-card").style.position = "relative";
  document.querySelector(".logout-card").appendChild(skipBtn);
}

// Uncomment the line below if you want to add a skip button
// addSkipButton();
