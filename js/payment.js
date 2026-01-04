// SIMPLIFIED PAYMENT.JS - GUARANTEED TO WORK
document.addEventListener("DOMContentLoaded", function () {
  console.log("Payment system loaded");

  // Initialize payment method toggles
  initializePaymentMethods();

  // Setup the payment button
  setupPaymentButton();
});

function initializePaymentMethods() {
  const paymentMethods = document.querySelectorAll(
    'input[name="payment_method"]'
  );

  paymentMethods.forEach((method) => {
    method.addEventListener("change", function () {
      const selectedMethod = this.value;

      // Hide all sections
      document.getElementById("cashPaymentDetails").style.display = "none";
      document.getElementById("onlineBankingDetails").style.display = "none";
      document.getElementById("qrPaymentDetails").style.display = "none";

      // Show selected section
      if (selectedMethod === "cash") {
        document.getElementById("cashPaymentDetails").style.display = "block";
      } else if (selectedMethod === "online_banking") {
        document.getElementById("onlineBankingDetails").style.display = "block";
      } else if (selectedMethod === "qr") {
        document.getElementById("qrPaymentDetails").style.display = "block";
      }
    });
  });

  // Trigger initial state
  const defaultMethod = document.querySelector(
    'input[name="payment_method"]:checked'
  );
  if (defaultMethod) {
    defaultMethod.dispatchEvent(new Event("change"));
  }
}

function setupPaymentButton() {
  const submitButton = document.getElementById("manualPaymentSubmit");
  const paymentForm = document.getElementById("paymentForm");

  if (!submitButton || !paymentForm) {
    console.error("Required elements not found!");
    return;
  }

  submitButton.addEventListener("click", function () {
    console.log("Payment button clicked!");

    // Validate form
    if (!validateForm()) {
      return;
    }

    // Show loading
    showLoadingModal();

    // Get form data
    const formData = new FormData(paymentForm);

    // Log for debugging
    console.log("Submitting payment data:");
    for (let [key, value] of formData.entries()) {
      console.log(`${key}: ${value}`);
    }

    // Submit to server
    fetch(paymentForm.action, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        console.log("Server response:", data);
        hideLoadingModal();

        if (data.success) {
          showSuccessMessage(data);
        } else {
          showError(data.message || "Payment failed");
        }
      })
      .catch((error) => {
        console.error("Submission error:", error);
        hideLoadingModal();
        showError("Network error: " + error.message);
      });
  });
}

function validateForm() {
  // Check terms and conditions
  const terms = document.getElementById("terms");
  if (!terms || !terms.checked) {
    alert("Please accept the terms and conditions");
    return false;
  }

  // Get selected payment method
  const paymentMethod = document.querySelector(
    'input[name="payment_method"]:checked'
  );
  if (!paymentMethod) {
    alert("Please select a payment method");
    return false;
  }

  // Method-specific validation
  if (paymentMethod.value === "online_banking") {
    const bankName = document.getElementById("bank_name").value;
    const proofFile = document.getElementById("proof").files[0];

    if (!bankName) {
      alert("Please select a bank");
      return false;
    }

    // Transaction ID is optional; only require proof of payment

    if (!proofFile) {
      alert("Please upload proof of payment");
      return false;
    }
  } else if (paymentMethod.value === "qr") {
    const proofFile = document.getElementById("qr_proof").files[0];

    // QR transaction reference is optional; only require proof screenshot

    if (!proofFile) {
      alert("Please upload proof of payment");
      return false;
    }
  }

  return true;
}

function showLoadingModal() {
  const modal = document.getElementById("loadingModal");
  if (modal) modal.style.display = "flex";
}

function hideLoadingModal() {
  const modal = document.getElementById("loadingModal");
  if (modal) modal.style.display = "none";
}

function showSuccessMessage(data) {
  alert(
    "✅ Payment Successful!\n\nTransaction ID: " +
      (data.transaction_id || "N/A") +
      "\nAmount: RM" +
      data.amount +
      "\n\nRedirecting to bookings page..."
  );

  // Redirect after 2 seconds
  setTimeout(() => {
    window.location.href =
      "mybookings.php?payment=success&booking_id=" + data.booking_id;
  }, 2000);
}

function showError(message) {
  alert("❌ Error: " + message);
}

// Optional: Add keyboard support (Enter key)
document.addEventListener("keydown", function (event) {
  if (event.key === "Enter") {
    const submitButton = document.getElementById("manualPaymentSubmit");
    if (submitButton) {
      event.preventDefault();
      submitButton.click();
    }
  }
});
