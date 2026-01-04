const modeToggle = document.getElementById("modeToggle");
const body = document.body;
const icon = modeToggle.querySelector("i");

// Load preference
if (localStorage.getItem("theme") === "dark") {
  body.classList.add("dark");
  icon.classList.replace("fa-moon", "fa-sun");
}

modeToggle.addEventListener("click", () => {
  body.classList.toggle("dark");
  const isDark = body.classList.contains("dark");

  if (isDark) {
    icon.classList.replace("fa-moon", "fa-sun");
    localStorage.setItem("theme", "dark");
  } else {
    icon.classList.replace("fa-sun", "fa-moon");
    localStorage.setItem("theme", "light");
  }
});

// Add scroll animation for elements
document.addEventListener("DOMContentLoaded", function () {
  // Add intersection observer for fade-in animations
  const observerOptions = {
    threshold: 0.1,
    rootMargin: "0px 0px -50px 0px",
  };

  const observer = new IntersectionObserver(function (entries) {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add("fade-in");
      }
    });
  }, observerOptions);

  // Observe elements for animation
  const elementsToAnimate = document.querySelectorAll(
    ".feature-box, .stat-box, .safety-stats > div"
  );
  elementsToAnimate.forEach((el) => {
    observer.observe(el);
  });

  // Add CSS for fade-in animation
  const style = document.createElement("style");
  style.textContent = `
        .feature-box, .stat-box, .safety-stats > div {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }
        
        .fade-in {
            opacity: 1;
            transform: translateY(0);
        }
    `;
  document.head.appendChild(style);
});
