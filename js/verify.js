document.addEventListener("DOMContentLoaded", function () {
  const verificationForm = document.getElementById("verificationForm");
  const resultMessage = document.getElementById("resultMessage");
  const resultTitle = document.getElementById("resultTitle");
  const resultText = document.getElementById("resultText");
  const tryAgainBtn = document.getElementById("tryAgainBtn");
  const verifyBtn = document.getElementById("verifyBtn");

  // Format IC number input (12 digits without dashes)
  const icNoInput = document.getElementById("icNo");
  icNoInput.addEventListener("input", function (e) {
    let value = e.target.value.replace(/\D/g, "");
    if (value.length > 12) {
      value = value.substring(0, 12);
    }
    e.target.value = value;
    validateICNo(value);
  });

  // Format Matric number input (1 letter + 9 numbers for UTEM)
  const matricNoInput = document.getElementById("matricNo");
  matricNoInput.addEventListener("input", function (e) {
    let value = e.target.value.toUpperCase();
    value = value.replace(/[^A-Z0-9]/g, "");
    if (value.length > 10) {
      value = value.substring(0, 10);
    }
    e.target.value = value;
    validateMatricNo(value);
  });

  // Form submission
  verificationForm.addEventListener("submit", function (e) {
    e.preventDefault();

    const matricNo = document.getElementById("matricNo").value.trim();
    const icNo = document.getElementById("icNo").value.trim();
    const consent = document.getElementById("consent").checked;

    console.log("Form submitted:", { matricNo, icNo, consent });

    // Validate inputs
    if (!validateMatricNo(matricNo, true)) {
      showResult(
        "error",
        "Invalid Matric Number",
        "Please enter a valid matric number in the format: 1 letter followed by 9 numbers (e.g., D032310209)"
      );
      return;
    }

    if (!validateICNo(icNo, true)) {
      showResult(
        "error",
        "Invalid IC Number",
        "Please enter a valid 12-digit IC number without dashes"
      );
      return;
    }

    if (!consent) {
      showResult(
        "error",
        "Consent Required",
        "You must consent to the verification process to continue"
      );
      return;
    }

    // Show loading state
    verifyBtn.disabled = true;
    verifyBtn.innerHTML =
      '<i class="fa-solid fa-spinner loading"></i> Verifying...';

    // Send verification request to PHP backend
    verifyStudentWithDatabase(matricNo, icNo);
  });

  // Try again button
  tryAgainBtn.addEventListener("click", function () {
    resetForm();
  });

  // Validation functions
  function validateMatricNo(matricNo, showError = false) {
    // UTEM format: 1 letter + 9 numbers (e.g., D032310209)
    const matricRegex = /^[A-Z]{1}\d{9}$/;
    const isValid = matricRegex.test(matricNo);

    if (showError) {
      const errorElement = document.getElementById("matricError");
      if (!isValid && matricNo.length > 0) {
        errorElement.textContent =
          "Format: 1 letter + 9 numbers (e.g., D032310209)";
        matricNoInput.classList.add("error");
        matricNoInput.classList.remove("success");
      } else if (isValid) {
        errorElement.textContent = "";
        matricNoInput.classList.remove("error");
        matricNoInput.classList.add("success");
      } else {
        errorElement.textContent = "";
        matricNoInput.classList.remove("error", "success");
      }
    }

    return isValid;
  }

  function validateICNo(icNo, showError = false) {
    const icRegex = /^\d{12}$/;
    const isValid = icRegex.test(icNo);

    if (showError) {
      const errorElement = document.getElementById("icError");
      if (!isValid && icNo.length > 0) {
        errorElement.textContent = "Please enter a valid 12-digit IC number";
        icNoInput.classList.add("error");
        icNoInput.classList.remove("success");
      } else if (isValid) {
        errorElement.textContent = "";
        icNoInput.classList.remove("error");
        icNoInput.classList.add("success");
      } else {
        errorElement.textContent = "";
        icNoInput.classList.remove("error", "success");
      }
    }

    return isValid;
  }

  // Database Verification Function
  // Database Verification Function
  function verifyStudentWithDatabase(matricNo, icNo) {
    console.log("Sending verification request:", { matricNo, icNo });

    fetch("../database/verifydb.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        matricNo: matricNo,
        icNo: icNo,
      }),
    })
      .then((response) => {
        console.log("Response status:", response.status);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        console.log("Full response data:", data);
        console.log("Student object:", data.student);
        console.log("Full name property:", data.student?.fullName);
        console.log("Debug info:", data.debug);

        if (data.success) {
          // Enhanced success handler with student name
          const studentName = data.student.fullName;
          const matricNo = data.student.matricNo;

          // Extract first name (take first word from full name)
          const firstName = studentName.split(" ")[0];

          // Show success message
          const welcomeMessages = [
            `Welcome aboard, <strong>${firstName}</strong>! 🚗`,
            `Great to have you, <strong>${firstName}</strong>! CampusCar is ready for you!`,
            `Hello <strong>${firstName}</strong>! Your campus rides await!`,
            `Welcome <strong>${firstName}</strong>! Let's get you on the road!`,
          ];

          const randomWelcome =
            welcomeMessages[Math.floor(Math.random() * welcomeMessages.length)];

          showResult(
            "success",
            "✅ Verification Successful!",
            `${randomWelcome}<br><br>
           <strong>Matric No:</strong> ${matricNo}<br>
           <strong>Full Name:</strong> ${studentName}<br><br>
           <em>Redirecting to signup in 3 seconds...</em>`
          );

          // Store verification status and user data
          localStorage.setItem("studentIC", icNo); // store the original IC the user entered so signup can prefill it
          localStorage.setItem("studentVerified", "true");
          localStorage.setItem("studentMatric", matricNo);
          localStorage.setItem("studentName", studentName);
          localStorage.setItem("studentFirstName", firstName);

          // Redirect to signup.php after 5 seconds
          setTimeout(() => {
            window.location.href = "../php/signup.php";
          }, 3000);
        } else {
          // Failed verification
          console.log(
            "Verification failed, checking for student data:",
            data.student
          );
          const studentName =
            data.student && data.student.fullName
              ? data.student.fullName
              : null;
          if (studentName) {
            showResult(
              "error",
              "Verification Failed",
              `${data.message}<br><br><strong>Full Name:</strong> ${studentName}`
            );
          } else {
            showResult("error", "Verification Failed", data.message);
          }
        }
      })
      .catch((error) => {
        console.error("Fetch Error:", error);
        showResult(
          "error",
          "Connection Error",
          `Unable to connect to verification service. Error: ${error.message}`
        );
      })
      .finally(() => {
        // Reset button state
        verifyBtn.disabled = false;
        verifyBtn.innerHTML =
          '<i class="fa-solid fa-shield-check"></i> Verify Student Status';
      });
  }

  // Show result function
  function showResult(type, title, text) {
    resultMessage.className = "result-message";
    document.querySelectorAll(".result-icon i").forEach((icon) => {
      icon.style.display = "none";
    });

    if (type === "success") {
      document.querySelector(".result-icon .success").style.display = "block";
      resultMessage.classList.add("success");
    } else if (type === "error") {
      document.querySelector(".result-icon .error").style.display = "block";
      resultMessage.classList.add("error");
    }

    resultTitle.textContent = title;
    // Allow HTML for successful messages (server-provided HTML like <strong>, <br>)
    // Use textContent for error/warning to avoid injecting HTML from untrusted sources
    if (type === "success") {
      resultText.innerHTML = text;
    } else {
      resultText.textContent = text;
    }

    resultMessage.classList.remove("hidden");
    resultMessage.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }

  // Reset form function
  function resetForm() {
    verificationForm.reset();
    resultMessage.classList.add("hidden");
    matricNoInput.classList.remove("error", "success");
    icNoInput.classList.remove("error", "success");
    document.getElementById("matricError").textContent = "";
    document.getElementById("icError").textContent = "";
    verificationForm.scrollIntoView({ behavior: "smooth", block: "start" });
  }
});
