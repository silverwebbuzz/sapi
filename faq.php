<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>FAQs</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
  </head>
  <style>
    body {
      font-family: "Inter", sans-serif;
      background: #fff;
      color: #1d1d1d;
      margin: 0;
      padding: 0;
    }
    header {
      padding: 10px 30px;
      border-bottom: 1px solid #dddada;
      box-shadow: rgba(100, 100, 111, 0.2) 0px 7px 29px 0px;
    }
    .header_container {
      max-width: 1280px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      gap: 10px;
      justify-content: space-between;
    }
    header .menu ul li {
      list-style: none;
    }
    header .menu ul {
      display: flex;
      gap: 20px;
      align-items: center;
      padding: 0;
    }
    header img {
      width: 180px;
      max-width: 100%;
    }
    .faq-container {
      max-width: 1280px;
      margin: 50px auto;
      padding: 30px;
      background: #f9f9f9;
      border-radius: 10px;
    }
    h1 {
      font-size: 28px;
      font-weight: 600;
      margin: 0 0 30px;
      text-align: center;
    }
    .accordion {
      max-width: 900px;
      margin: 0 auto;
    }
    .accordion-item {
      border-top: 1px solid #e0e0e0;
      padding: 20px 0;
    }
    .accordion-item:first-child {
      border-top: 0 !important;
    }
    .accordion-header {
      position: relative;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 20px;
      font-weight: 600;
      padding: 16px 0;
      cursor: pointer;
      border: none;
      background: none;
      width: 100%;
      font-family: "Inter", sans-serif;
    }
    .accordion-header .icon {
      font-size: 22px;
      transition: transform 0.3s ease;
      margin-left: 10px;
      color: #1d1d1d;
    }
    .accordion-header:hover .icon {
      color: #ff4c06;
    }
    .accordion-item.active .accordion-header .icon {
      content: "−";
    }
    .accordion-header:hover {
      color: #ff4c06;
    }
    .accordion-body {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.2s ease;
    }
    .accordion-body p,
    .accordion-body ul {
      margin: 10px 0 20px;
    }
    .accordion-body ul {
      padding-left: 20px;
    }
    .accordion-item.active .accordion-body {
      max-height: 500px;
    }
    a {
      color: #1d1d1d;
      text-decoration: none;
    }
    a:hover {
      color: #ff4c06;
      text-decoration: underline;
    }
  </style>
  <body>
    <header>
      <div class="header_container">
        <div>
          <a href="https://sapi.silverwebbuzz.com/" target="_blank">
            <img src="https://sapi.silverwebbuzz.com/images/SAPI-logo-black.svg" alt="sapi logo img" />
          </a>
        </div>
        <div class="menu">
          <ul>
            <li>
              <a href="https://sapi.silverwebbuzz.com/" target="_blank">Home</a>
            </li>
            <li>
              <a href="https://sapi.silverwebbuzz.com/privacy" target="_blank">Privacy Policy</a>
            </li>
            <li>
              <a href="https://sapi.silverwebbuzz.com/faq" target="_blank">FAQs</a>
            </li>
          </ul>
        </div>
      </div>
    </header>
    <div class="faq-container">
      <h1>Frequently Asked Questions</h1>
      <div class="accordion">
        <div class="accordion-item">
          <button class="accordion-header">1. What does SWB Auto PDF Invoices do? <span class="icon">+</span></button>
          <div class="accordion-body">
            <p>SWB Auto PDF Invoices automatically generates professional PDF invoices based on your Shopify orders. It helps you streamline post-order documentation and saves valuable time.</p>
          </div>
        </div>
        <div class="accordion-item">
          <button class="accordion-header">2. Are invoices sent to customers automatically? <span class="icon">+</span></button>
          <div class="accordion-body">
            <p>Yes! You can enable automatic email delivery of PDF invoices after every order. You also have the option to send copies to your own email as a merchant.</p>
          </div>
        </div>
        <div class="accordion-item">
          <button class="accordion-header">3. Can I customize my invoice template? <span class="icon">+</span></button>
          <div class="accordion-body">
            <p>Absolutely. You can select from pre-designed templates and customize them with your:</p>
            <ul>
              <li>Logo</li>
              <li>Brand colors</li>
              <li>Fonts</li>
              <li>Contact details</li>
            </ul>
            <p>This ensures that your invoices match your brand identity.</p>
          </div>
        </div>
        <div class="accordion-item">
          <button class="accordion-header">4. Does the app support multi-currency stores? <span class="icon">+</span></button>
          <div class="accordion-body">
            <p>Yes, the app supports multiple currencies based on your store's settings.</p>
          </div>
        </div>
        <div class="accordion-item">
          <button class="accordion-header">5. Can I download or print invoices? <span class="icon">+</span></button>
          <div class="accordion-body">
            <p>Yes, you can download or print invoices directly from your dashboard with just a few clicks.</p>
          </div>
        </div>
        <div class="accordion-item">
          <button class="accordion-header">6. Can I resend an invoice to a customer? <span class="icon">+</span></button>
          <div class="accordion-body">
            <p>Yes, you can easily resend any invoice from your list of generated invoices.</p>
          </div>
        </div>
        <div class="accordion-item">
          <button class="accordion-header">7. Is my store data safe? <span class="icon">+</span></button>
          <div class="accordion-body">
            <p>Absolutely. We use industry-standard security practices. All your data is securely stored and is never shared or sold.</p>
          </div>
        </div>
        <div class="accordion-item">
          <button class="accordion-header">8. What happens if I uninstall the app? <span class="icon">+</span></button>
          <div class="accordion-body">
            <p>If you uninstall the app, your data will be securely deleted from our servers within 30 days. You can reinstall anytime and continue from where you left off.</p>
          </div>
        </div>
        <div class="accordion-item">
          <button class="accordion-header">9. How can I contact support? <span class="icon">+</span></button>
          <div class="accordion-body">
            <p>You can reach us via email at <a href="mailto:support.sapi@silverwebbuzz.com">support.sapi@silverwebbuzz.com</a>. We're happy to assist you with setup, troubleshooting, or customization.</p>
          </div>
        </div>
      </div>
    </div>

    <script>
      const items = document.querySelectorAll(".accordion-item");
      items.forEach((item) => {
        const header = item.querySelector(".accordion-header");
        const icon = header.querySelector(".icon");
        header.addEventListener("click", () => {
          const openItem = document.querySelector(".accordion-item.active");
          if (openItem && openItem !== item) {
            openItem.classList.remove("active");
            openItem.querySelector(".icon").textContent = "+";
          }
          const isActive = item.classList.contains("active");
          item.classList.toggle("active");
          icon.textContent = isActive ? "+" : "−";
        });
      });
    </script>
  </body>
</html>
