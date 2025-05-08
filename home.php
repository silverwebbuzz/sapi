<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Home</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet" />
  </head>
  <style>
    @import url("https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap");
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
      font-weight: 500;
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
    a {
      color: #1d1d1d;
      text-decoration: none;
    }
    a:hover {
      color: #ff4c06;
      text-decoration: underline;
    }
    img {
      display: block;
      max-width: 100%;
    }
    h2 {
      font-size: 36px;
      line-height: 42px;
    }
    .container {
      max-width: 1280px;
      margin: 0 auto;
    }
    .hero_section,
    .customise_section,
    .feature_card,
    .cta_section,
    .slider_sec {
      padding: 50px 30px;
    }
    .hero_section .container,
    .customise_section .container {
      display: flex;
      gap: 20px;
      align-items: center;
    }
    .customise_left img {
      max-width: 400px;
    }
    .customise_section .container {
      gap: 100px;
    }
    .customise_right h2 {
      margin: 0 0 20px;
    }
    .hero_section .hero_left {
      width: calc(100% - 400px - 20px);
    }
    .hero_section .hero_right {
      width: 400px;
    }
    .hero_left h1 {
      font-size: 48px;
      line-height: 56px;
      font-weight: 700;
      color: #1d1d1d;
      margin: 0 0 20px;
    }
    .hero_left h1 span {
      color: #ff4c06;
    }
    .hero_points {
      list-style: none;
      display: flex;
      flex-direction: column;
      padding: 0;
      gap: 10px;
      margin: 20px 0 30px;
    }
    .hero_points li {
      display: flex;
      gap: 10px;
      align-items: center;
      font-size: 18px;
    }
    p {
      font-size: 20px;
      line-height: 26px;
      margin: 0;
    }
    .shop_app_btn {
      display: inline-block;
    }
    .shop_app_btn img {
      max-width: 220px;
    }
    .feature_card,
    .cta_section {
      background-color: #eddbd4;
    }
    .feature_card .container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 30px;
      text-align: center;
    }
    .feature_card .single_card {
      background: #fff;
      padding: 30px 20px;
      border-radius: 12px;
      cursor: pointer;
      box-shadow: rgba(100, 100, 111, 0.2) 0px 7px 29px 0px;
      transition: transform 0.3s;
    }
    .feature_card .single_card:hover {
      transform: translateY(-5px);
    }
    .feature_card .single_card svg {
      width: 36px;
      height: 36px;
      color: #ff4c06;
    }
    .feature_card .single_card h4 {
      font-size: 24px;
      font-weight: 500;
      margin: 20px 0 0;
    }
    .slider {
      max-width: 1280px;
      margin: 0 auto;
      overflow: hidden;
      position: relative;
      border-radius: 10px;
      box-shadow: rgba(100, 100, 111, 0.2) 0px 7px 29px 0px;
    }
    .slides {
      display: flex;
      transition: transform 0.5s ease;
    }
    .slide {
      flex: 0 0 100%;
      background-size: cover;
      background-position: center;
    }
    .arrow {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      background: rgba(0, 0, 0, 0.5);
      color: white;
      font-size: 2rem;
      border: none;
      padding: 5px 10px;
      cursor: pointer;
      z-index: 1;
    }
    .arrow.left {
      left: 10px;
    }
    .arrow.right {
      right: 10px;
    }
    .dots {
      text-align: center;
      margin-top: 10px;
    }
    .dot {
      display: inline-block;
      width: 10px;
      height: 10px;
      margin: 5px;
      background: #bbb;
      border-radius: 50%;
      cursor: pointer;
    }
    .dot.active {
      background: #333;
    }
    .cta_btn {
      background-color: #ff4c06;
      padding: 10px 20px;
      font-size: 18px;
      line-height: 24px;
      color: #fff;
      border: 1px solid #ff4c06;
      border-radius: 6px;
      display: inline-block;
      text-decoration: none;
      transition: 0.3s linear;
    }
    .cta_btn:hover {
      background-color: transparent;
      color: #ff4c06;
      text-decoration: none;
      border-color: #ff4c06;
    }
    .cta_section .container {
      display: flex;
      flex-direction: column;
      gap: 30px;
      align-items: center;
      text-align: center;
    }
    .cta_section h2 {
      margin: 0;
    }
    footer {
      padding: 50px 30px 20px;
    }
    footer h3 {
      font-size: 28px;
      line-height: 34px;
      margin: 0 0 20px;
    }
    footer a {
      font-size: 18px;
      line-height: 26px;
    }
    footer .copyright p {
      margin: 20px 0 0;
      text-align: center;
      font-size: 16px;
      line-height: 20px;
    }

    @media (max-width: 1024px) {
      .hero_left h1 {
        font-size: 38px;
        line-height: 44px;
      }
      p {
        font-size: 18px;
        line-height: 24px;
      }
      .feature_card .single_card h4 {
        font-size: 20px;
      }
      h2 {
        font-size: 32px;
        line-height: 38px;
      }
      .customise_section .container {
        gap: 50px;
      }
      .hero_section .hero_right {
        width: 300px;
      }
      .hero_section .hero_left {
        width: calc(100% - 300px - 20px);
      }
      .customise_left img {
        max-width: 300px;
      }
    }
    @media (max-width: 767px) {
      header {
        padding: 10px 20px;
      }
      header img {
        width: 150px;
      }
      .hero_section,
      .customise_section,
      .feature_card,
      .cta_section,
      .slider_sec {
        padding: 50px 20px;
      }
      .hero_section .container,
      .customise_section .container {
        flex-direction: column;
      }
      .hero_section .hero_left {
        width: 100%;
      }
      .hero_left h1 {
        font-size: 34px;
        line-height: 40px;
      }
      h2 {
        font-size: 28px;
        line-height: 34px;
      }
      p,
      .hero_points li {
        font-size: 16px;
        line-height: 22px;
      }
      .cta_section .container {
        gap: 20px;
      }
      footer {
        padding: 50px 20px 20px;
        text-align: center;
      }
      footer h3 {
        font-size: 24px;
        line-height: 30px;
      }
    }
    @media (max-width: 480px) {
      .header_container {
        flex-direction: column;
      }
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
              <a href="https://sapi.silverwebbuzz.com/privacy" target="_blank">Privacy policy</a>
            </li>
            <li>
              <a href="https://sapi.silverwebbuzz.com/faq" target="_blank">FAQs</a>
            </li>
          </ul>
        </div>
      </div>
    </header>
    <main>
      <section class="hero_section">
        <div class="container">
          <div class="hero_left">
            <h1><span>Streamline</span> invoicing with <br /><span>top-tier</span> support!</h1>
            <p>
              Take the hassle out of invoicing with a solution built for simplicity and speed. Whether you're a small business or scaling fast, generate professional invoices in seconds. Create stunning invoices in minutes with expertly crafted templates. Designed to reflect your brand and simplify
              your billing process.
            </p>
            <ul class="hero_points">
              <li>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text-icon lucide-file-text">
                  <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
                  <path d="M14 2v4a2 2 0 0 0 2 2h4" />
                  <path d="M10 9H8" />
                  <path d="M16 13H8" />
                  <path d="M16 17H8" />
                </svg>
                Auto & Manual Invoice Generation
              </li>
              <li>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sliders-horizontal-icon lucide-sliders-horizontal">
                  <line x1="21" x2="14" y1="4" y2="4" />
                  <line x1="10" x2="3" y1="4" y2="4" />
                  <line x1="21" x2="12" y1="12" y2="12" />
                  <line x1="8" x2="3" y1="12" y2="12" />
                  <line x1="21" x2="16" y1="20" y2="20" />
                  <line x1="12" x2="3" y1="20" y2="20" />
                  <line x1="14" x2="14" y1="2" y2="6" />
                  <line x1="8" x2="8" y1="10" y2="14" />
                  <line x1="16" x2="16" y1="18" y2="22" />
                </svg>
                Personalized Setup
              </li>
              <li>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-check-icon lucide-user-check">
                  <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                  <circle cx="9" cy="7" r="4" />
                  <polyline points="16 11 18 13 22 9" />
                </svg>
                No Technical Skills Required!
              </li>
            </ul>
            <a href="https://apps.shopify.com/swb-auto-pdf-invoices" target="_blank" class="shop_app_btn">
              <img src="https://sapi.silverwebbuzz.com/images/badge-shopify-app-store-dark.svg" alt="SAPI Shopify app store image" />
            </a>
          </div>
          <div class="hero_right">
            <img src="./images/invoice-stack-img.png" alt="invoices image" />
          </div>
        </div>
      </section>
      <section class="feature_card">
        <div class="container">
          <div class="single_card">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-template-icon lucide-layout-template">
              <rect width="18" height="7" x="3" y="3" rx="1" />
              <rect width="9" height="7" x="3" y="14" rx="1" />
              <rect width="5" height="7" x="16" y="14" rx="1" />
            </svg>
            <h4>Customizable Templates</h4>
          </div>
          <div class="single_card">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-zap-icon lucide-zap">
              <path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z" />
            </svg>
            <h4>Instant Invoice Delivery</h4>
          </div>
          <div class="single_card">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mail-icon lucide-mail">
              <rect width="20" height="16" x="2" y="4" rx="2" />
              <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
            </svg>
            <h4>Email Customer Support</h4>
          </div>
        </div>
      </section>
      <section class="slider_sec">
        <div class="slider">
          <div class="slides" id="slides">
            <div class="slide">
              <img src="https://sapi.silverwebbuzz.com/images/Main img - 1.png" alt="slide 1 image" />
            </div>
            <div class="slide">
              <img src="https://sapi.silverwebbuzz.com/images/Main img - 2.png" alt="slide 2 image" />
            </div>
            <div class="slide">
              <img src="https://sapi.silverwebbuzz.com/images/other images - customize invoice.png" alt="slide 3 image" />
            </div>
            <div class="slide">
              <img src="https://sapi.silverwebbuzz.com/images/other images - preview invoice.png" alt="slide 4 image" />
            </div>
          </div>
          <button class="arrow left" id="prev">&#10094;</button>
          <button class="arrow right" id="next">&#10095;</button>
        </div>
        <div class="dots" id="dots"></div>
      </section>
      <section class="customise_section">
        <div class="container">
          <div class="customise_left">
            <img src="https://sapi.silverwebbuzz.com/images/customise_invoice-vector.png" alt="customise invoice vector img" />
          </div>
          <div class="customise_right">
            <h2>Deliver a Consistent Brand Experience</h2>
            <p>Match your invoice style to your brand by adjusting <strong>colors, fonts,</strong> and layout options. Whether you're a minimalist or love bold designs, SWB Auto PDF Invoices lets you personalize every detail to match your website and product aesthetics.</p>
            <br />
            <p>Consistency builds trust—when your invoices mirror the professionalism of your brand, your customers recognize the care you put into every touchpoint. Impress clients and strengthen your brand identity with every invoice you send.</p>
          </div>
        </div>
      </section>
      <section class="cta_section">
        <div class="container">
          <h2>Get Started Today</h2>
          <p>Start using SWB Auto PDF Invoices and eliminate the stress of post-order paperwork. Empower your business with professional invoicing, customizable designs, and instant delivery. This app is your partner in seamless invoicing.</p>
          <a href="https://apps.shopify.com/swb-auto-pdf-invoices" target="_blank" class="shop_app_btn">
              <img src="https://sapi.silverwebbuzz.com/images/badge-shopify-app-store-dark.svg" alt="SAPI Shopify app store image" />
          </a>
        </div>
      </section>
      <footer>
        <div class="container">
          <div>
            <h3>SWB Auto PDF Invoices</h3>
            <a href="mailto:support.sapi@silverwebbuzz.com">support.sapi@silverwebbuzz.com</a>
          </div>
          <div class="copyright">
            <p>Copyright ©2025 Silverwebbuzz. All rights reserved.</p>
          </div>
        </div>
      </footer>
    </main>

    <!-- slider js -->
    <script>
      const slides = document.querySelectorAll(".slide");
      const slidesEl = document.getElementById("slides");
      const dotsEl = document.getElementById("dots");
      let index = 0,
        timer;

      const goToSlide = (i) => {
        index = (i + slides.length) % slides.length;
        slidesEl.style.transform = `translateX(-${index * 100}%)`;
        [...dotsEl.children].forEach((dot, d) => dot.classList.toggle("active", d === index));
      };

      const autoPlay = () => {
        clearInterval(timer);
        timer = setInterval(() => goToSlide(index + 1), 3000);
      };

      // Init dots
      slides.forEach((_, i) => {
        const dot = document.createElement("span");
        dot.className = "dot";
        dot.onclick = () => {
          goToSlide(i);
          autoPlay();
        };
        dotsEl.appendChild(dot);
      });

      document.getElementById("prev").onclick = () => {
        goToSlide(index - 1);
        autoPlay();
      };
      document.getElementById("next").onclick = () => {
        goToSlide(index + 1);
        autoPlay();
      };

      goToSlide(0);
      autoPlay();
    </script>
  </body>
</html>
