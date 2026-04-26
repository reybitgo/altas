<?php
require_once __DIR__ . '/../config/db.php';
$base     = rtrim(APP_URL, '/');           // e.g. http://localhost/altas
$frontend = $base . '/frontend';           // e.g. http://localhost/altas/frontend
?>
<!DOCTYPE html>
<html lang="en" itemscope itemtype="https://schema.org/Organization">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">

  <!-- ── Primary SEO ── -->
  <title>AltasFarm — Closed 1,000-Member Philippine Poultry Network</title>
  <meta name="description" content="AltasFarm is a closed 1,000-member Philippine poultry binary network. One package, one payout currency (USDT TRC20), one community built on bayanihan. Seats are finite — join before the network is full.">
  <meta name="keywords" content="AltasFarm, Philippine poultry network, binary MLM Philippines, USDT payout, farm investment Philippines, poultry farming community, bayanihan network">
  <meta name="robots" content="index, follow">
  <meta name="author" content="AltasFarm">
  <link rel="canonical" href="<?= $base ?>/">

  <!-- ── Open Graph (ScamAdviser reads this) ── -->
  <meta property="og:type" content="website">
  <meta property="og:title" content="AltasFarm — Closed 1,000-Member Philippine Poultry Network">
  <meta property="og:description" content="A closed community of 1,000 farmers and networkers backed by real Philippine poultry operations. One entry. One payout in USDT.">
  <meta property="og:url" content="<?= $base ?>/">
  <meta property="og:site_name" content="AltasFarm">
  <meta property="og:locale" content="en_PH">
  <meta property="og:image" content="<?= $base ?>/hero-bg.jpg">

  <!-- ── Twitter Card ── -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="AltasFarm — Closed 1,000-Member Poultry Network">
  <meta name="twitter:description" content="1,000 seats. Real farms. USDT payouts. Binary referral structure. Philippines.">
  <meta name="twitter:image" content="<?= $base ?>/hero-bg.jpg">

  <!-- ── PWA ── -->
  <meta name="theme-color" content="#1a3a1e">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="AltasFarm">
  <link rel="icon" type="image/png" href="<?= $frontend ?>/favicon.png">
  <link rel="apple-touch-icon" href="<?= $frontend ?>/favicon.png">
  <link rel="manifest" href='data:application/manifest+json;charset=utf-8,{"name":"AltasFarm","short_name":"AltasFarm","start_url":".","display":"standalone","background_color":"#faf7f0","theme_color":"#1a3a1e","icons":[{"src":"<?= $frontend ?>/favicon.png","sizes":"192x192","type":"image/png"},{"src":"<?= $frontend ?>/favicon.png","sizes":"512x512","type":"image/png"}]}'>

  <!-- ── Schema.org Organization (machine-readable trust signal) ── -->
  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "AltasFarm",
      "url": "<?= $base ?>",
      "logo": "<?= $base ?>/logo.png",
      "description": "A closed 1,000-member Philippine poultry network connecting real farm investment with community-powered binary income, paying out exclusively in USDT TRC20.",
      "foundingDate": "2024",
      "foundingLocation": {
        "@type": "Place",
        "addressCountry": "PH"
      },
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "Balintocatoc",
        "addressLocality": "Santiago",
        "addressRegion": "Isabela",
        "postalCode": "3311",
        "addressCountry": "PH"
      },
      "contactPoint": [{
        "@type": "ContactPoint",
        "email": "contact@altasfarm.com",
        "contactType": "customer support",
        "availableLanguage": ["English", "Filipino"],
        "contactOption": "TollFree"
      }],
      "sameAs": [
        "https://www.facebook.com/altasfarm",
        "https://t.me/altasfarm"
      ],
      "areaServed": {
        "@type": "Country",
        "name": "Philippines"
      },
      "numberOfEmployees": {
        "@type": "QuantitativeValue",
        "value": "10"
      }
    }
  </script>

  <!-- ── Schema.org WebSite (enables search box signals) ── -->
  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "name": "AltasFarm",
      "url": "<?= $base ?>",
      "potentialAction": {
        "@type": "SearchAction",
        "target": "<?= $base ?>/?s={search_term_string}",
        "query-input": "required name=search_term_string"
      }
    }
  </script>

  <!-- ── Schema.org FAQPage ── -->
  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "FAQPage",
      "mainEntity": [{
          "@type": "Question",
          "name": "What is AltasFarm?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "AltasFarm is a closed binary referral network backed by real Philippine poultry operations. It is limited to exactly 1,000 members. Each member holds one seat, earns through three commission streams, and receives payouts exclusively in USDT TRC20."
          }
        },
        {
          "@type": "Question",
          "name": "How do I join AltasFarm?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "You need a registration code from an existing member or from the AltasFarm admin. Once you have a code, register at altasfarm.com, choose your sponsor and binary position (left or right leg), and your seat is confirmed."
          }
        },
        {
          "@type": "Question",
          "name": "How are payouts made?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "All earnings are paid exclusively in USDT TRC20. You submit a withdrawal request through your dashboard and provide your USDT TRC20 wallet address. There are no GCash, Maya, or bank transfer options."
          }
        }
      ]
    }
  </script>

  <!-- ── Fonts ── -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= $frontend ?>/style.css">
</head>

<body>

  <!-- ════════════════════════════════════════════════════════════
     MODALS
════════════════════════════════════════════════════════════ -->

  <!-- ── FAQ Modal ──────────────────────────────────────────────── -->
  <div class="af-modal-backdrop" id="modal-faq" role="dialog" aria-modal="true" aria-labelledby="faq-title" onclick="closeModalOnBackdrop(event,'modal-faq')">
    <div class="af-modal">
      <div class="af-modal-header">
        <div>
          <h2 id="faq-title">Frequently Asked Questions</h2>
          <p>Last updated: January 2025</p>
        </div>
        <button class="af-modal-close" onclick="closeModal('modal-faq')" aria-label="Close">✕</button>
      </div>
      <div class="af-modal-body">

        <div class="faq-item">
          <button class="faq-q" onclick="toggleFaq(this)">What is AltasFarm?</button>
          <div class="faq-a">AltasFarm is a closed binary referral network backed by real Philippine poultry operations. Membership is strictly limited to 1,000 seats. Each member holds one seat, participates in three commission streams (binary pairing, direct referral, and unilevel), and receives all payouts exclusively in USDT TRC20. When the 1,000th seat is filled, registration closes permanently.</div>
        </div>

        <div class="faq-item">
          <button class="faq-q" onclick="toggleFaq(this)">How do I join AltasFarm?</button>
          <div class="faq-a">You need a valid registration code from an existing member (your sponsor) or from the AltasFarm admin team. Once you have a code, register at altasfarm.com, choose your sponsor, and select your binary position (left or right leg). Your seat is confirmed immediately upon successful registration and payment.</div>
        </div>

        <div class="faq-item">
          <button class="faq-q" onclick="toggleFaq(this)">How much does it cost to join?</button>
          <div class="faq-a">There is one package — the Broiler Starter — at a one-time entry fee of ₱10,000. There are no recurring fees, no upgrade tiers, and no hidden charges. Every member enters at exactly the same level and with access to exactly the same earning structure.</div>
        </div>

        <div class="faq-item">
          <button class="faq-q" onclick="toggleFaq(this)">How are commissions earned?</button>
          <div class="faq-a">
            There are three commission streams:
            <ul style="margin-top:.5rem;">
              <li><strong>Binary Pairing Bonus (₱2,000):</strong> Earned each time a left-right pair forms anywhere in your binary downline. Capped at 3 pairs per day.</li>
              <li><strong>Direct Referral Bonus (₱500):</strong> Credited instantly every time someone you personally referred registers with your code.</li>
              <li><strong>Unilevel Bonus (up to ₱300):</strong> Generational bonuses paid 10 levels deep — Level 1: ₱300, Level 2: ₱200, Level 3: ₱150, Levels 4–5: ₱100, Levels 6–10: ₱50 per registration in that level.</li>
            </ul>
            All commissions are credited to your e-wallet in real time on the triggering event (registration), not on a batch schedule.
          </div>
        </div>

        <div class="faq-item">
          <button class="faq-q" onclick="toggleFaq(this)">How are payouts made?</button>
          <div class="faq-a">All earnings are paid exclusively in USDT TRC20. You submit a withdrawal request through your member dashboard and provide your USDT TRC20 wallet address. There are no GCash, Maya, or bank transfer options — USDT is the sole payout method. This is by design: it makes the network borderless and accessible to OFW members and international participants.</div>
        </div>

        <div class="faq-item">
          <button class="faq-q" onclick="toggleFaq(this)">Is there a withdrawal minimum?</button>
          <div class="faq-a">The minimum withdrawal amount is ₱500 equivalent in USDT. Withdrawals are processed within 24–72 business hours after submission. You must have a verified USDT TRC20 wallet address linked to your account before requesting a withdrawal.</div>
        </div>

        <div class="faq-item">
          <button class="faq-q" onclick="toggleFaq(this)">What happens when the 1,000 seats are filled?</button>
          <div class="faq-a">Registration closes permanently. There is no waitlist, no second batch, no re-opening, and no exceptions. The 1,000-member ceiling is a structural decision — not a marketing device — and it is enforced at the system level. Once the counter reaches 1,000, the registration page will display a closed status and no new codes will be issued.</div>
        </div>

        <div class="faq-item">
          <button class="faq-q" onclick="toggleFaq(this)">Can I hold more than one seat?</button>
          <div class="faq-a">No. Each member is limited to one seat, one account, and one registration code. Creating duplicate accounts is a violation of the Terms of Service and will result in immediate suspension and forfeiture of any accumulated balance.</div>
        </div>

        <div class="faq-item">
          <button class="faq-q" onclick="toggleFaq(this)">Is my personal data safe?</button>
          <div class="faq-a">AltasFarm collects only the information necessary for account creation and commission processing. Your data is never sold to third parties. The platform uses CSRF protection, rate-limited login, and encrypted session management. Full details are in our Privacy Policy.</div>
        </div>

        <div class="faq-item">
          <button class="faq-q" onclick="toggleFaq(this)">How do I contact support?</button>
          <div class="faq-a">Reach us at <a href="mailto:support@altasfarm.com" style="color:var(--green-mid);font-weight:600;">support@altasfarm.com</a> or through our Telegram channel at <a href="https://t.me/altasfarm" target="_blank" rel="noopener" style="color:var(--green-mid);font-weight:600;">t.me/altasfarm</a>. Support is available Monday–Saturday, 8 AM–6 PM Philippine Standard Time (PST, UTC+8). We aim to respond within 24 hours on business days.</div>
        </div>

      </div>
    </div>
  </div>

  <!-- ── Terms of Service Modal ─────────────────────────────────── -->
  <div class="af-modal-backdrop" id="modal-tos" role="dialog" aria-modal="true" aria-labelledby="tos-title" onclick="closeModalOnBackdrop(event,'modal-tos')">
    <div class="af-modal">
      <div class="af-modal-header">
        <div>
          <h2 id="tos-title">Terms of Service</h2>
          <p>Effective date: January 1, 2025 · Version 1.0</p>
        </div>
        <button class="af-modal-close" onclick="closeModal('modal-tos')" aria-label="Close">✕</button>
      </div>
      <div class="af-modal-body">

        <div class="highlight-box">
          <p>By registering an account on AltasFarm, you agree to be bound by these Terms of Service. Please read them carefully before completing your registration.</p>
        </div>

        <h3>1. Parties</h3>
        <p>These Terms of Service ("Terms") govern the relationship between AltasFarm ("the Platform," "we," "us") and any individual who registers as a member ("Member," "you"). AltasFarm is operated by its founding administrators, based in Santiago, Isabela, Philippines.</p>

        <h3>2. Eligibility</h3>
        <p>To register, you must: (a) be at least 18 years of age; (b) be a resident of the Philippines or a Filipino national abroad; (c) possess a valid USDT TRC20 wallet address for receiving payouts; (d) have a valid registration code issued by an existing member or the admin team; and (e) agree to these Terms in full.</p>

        <h3>3. Membership Limit</h3>
        <p>AltasFarm operates a hard membership cap of exactly 1,000 (one thousand) seats. When the 1,000th registration is confirmed, the platform will permanently close registration. No exceptions, waitlists, or re-openings will be considered. Each member is limited to one (1) account. Registering multiple accounts constitutes fraud and will result in immediate suspension and forfeiture of balances.</p>

        <h3>4. Entry Fee and Package</h3>
        <p>There is one entry package (the Broiler Starter) at a one-time fee of ₱10,000. This fee is non-refundable upon confirmed registration and binary placement. The fee covers your platform seat, access to all earning streams, and participation in the network's poultry-backed operations.</p>

        <h3>5. Commissions and Earning Structure</h3>
        <p>Members earn through three streams: (a) Binary Pairing Bonus of ₱2,000 per confirmed pair, capped at three (3) pairs per calendar day; (b) Direct Referral Bonus of ₱500 per personally sponsored member; and (c) Unilevel Bonus as detailed in the Compensation Plan section of the website. Commissions are credited to your platform e-wallet in real time on the triggering event. AltasFarm reserves the right to verify and withhold commissions suspected of being generated through fraud, duplicate accounts, or system manipulation.</p>

        <h3>6. Payouts</h3>
        <p>All payouts are made exclusively in USDT TRC20. The minimum withdrawal amount is ₱500 equivalent. Withdrawals are processed within 24–72 business hours. AltasFarm is not liable for losses caused by incorrect wallet addresses provided by the member. Ensure your USDT TRC20 address is correct before submitting a withdrawal request — transactions on the blockchain are irreversible.</p>

        <h3>7. Prohibited Conduct</h3>
        <p>Members are prohibited from: creating duplicate accounts; using bots or automated tools to generate referrals; misrepresenting AltasFarm's earning potential to prospective members; making guarantees of income on behalf of the platform; and any conduct that manipulates the binary tree structure through fake or unauthorized registrations.</p>

        <h3>8. Account Suspension and Termination</h3>
        <p>AltasFarm may suspend or terminate any account found in violation of these Terms, at its sole discretion, without prior notice. Suspended accounts forfeit any pending or unclaimed wallet balance. Terminated members are not entitled to a refund of their entry fee.</p>

        <h3>9. Limitation of Liability</h3>
        <p>AltasFarm does not guarantee any specific income or return on your entry fee. Earnings depend entirely on network activity, which is subject to the 1,000-seat ceiling and the binary structure. Participation in AltasFarm involves inherent financial risk. AltasFarm is not liable for income tax obligations arising from your earnings — members are responsible for their own tax compliance under Philippine law (NIRC) or the laws of their country of residence.</p>

        <div class="warn-box">
          <p><strong>Income Disclaimer:</strong> Earnings from AltasFarm depend on your own activity, your network's growth, and the overall pace of registration within the 1,000-seat limit. Past performance of other members is not indicative of your potential results. Do not invest funds you cannot afford to lose.</p>
        </div>

        <h3>10. Changes to Terms</h3>
        <p>AltasFarm may update these Terms at any time. Continued use of the platform after an update constitutes acceptance of the revised Terms. Major changes will be communicated via your registered email address or the Telegram channel.</p>

        <h3>11. Governing Law</h3>
        <p>These Terms are governed by the laws of the Republic of the Philippines. Any disputes shall be resolved through good-faith negotiation, and if unresolved, submitted to the appropriate courts of Isabela, Philippines.</p>

        <h3>12. Contact</h3>
        <p>For questions regarding these Terms, contact: <a href="mailto:support@altasfarm.com" style="color:var(--green-mid);">support@altasfarm.com</a></p>

        <p class="meta-line">AltasFarm · Santiago, Isabela, Philippines · Version 1.0, effective January 1, 2025</p>
      </div>
    </div>
  </div>

  <!-- ── Privacy Policy Modal ───────────────────────────────────── -->
  <div class="af-modal-backdrop" id="modal-privacy" role="dialog" aria-modal="true" aria-labelledby="privacy-title" onclick="closeModalOnBackdrop(event,'modal-privacy')">
    <div class="af-modal">
      <div class="af-modal-header">
        <div>
          <h2 id="privacy-title">Privacy Policy</h2>
          <p>Effective date: January 1, 2025 · Version 1.0</p>
        </div>
        <button class="af-modal-close" onclick="closeModal('modal-privacy')" aria-label="Close">✕</button>
      </div>
      <div class="af-modal-body">

        <div class="highlight-box">
          <p>AltasFarm collects only what is necessary to operate your account. We do not sell, rent, or share your personal information with third parties for marketing purposes.</p>
        </div>

        <h3>1. Data Controller</h3>
        <p>AltasFarm (the "Platform") is the data controller for personal information collected through this website and the member dashboard. Our contact for data concerns is: <a href="mailto:support@altasfarm.com" style="color:var(--green-mid);">support@altasfarm.com</a></p>

        <h3>2. What We Collect</h3>
        <ul>
          <li><strong>Registration data:</strong> Full name, email address, mobile number, province/region, and the referral code used to register.</li>
          <li><strong>Financial data:</strong> Your USDT TRC20 wallet address, withdrawal requests, and commission history. We do not collect bank account numbers, credit card numbers, or GCash/Maya account details.</li>
          <li><strong>Technical data:</strong> IP address (for security and fraud detection), browser type, device type, and session tokens. These are discarded after 30 days.</li>
          <li><strong>Communications:</strong> Support messages and emails you send to us.</li>
        </ul>

        <h3>3. How We Use Your Data</h3>
        <ul>
          <li>To create and manage your member account.</li>
          <li>To process and verify commissions and withdrawal requests.</li>
          <li>To detect and prevent fraud, duplicate accounts, and unauthorized access.</li>
          <li>To send transactional notifications (commission credits, withdrawal confirmations).</li>
          <li>To comply with applicable Philippine laws.</li>
        </ul>

        <h3>4. Legal Basis for Processing</h3>
        <p>We process your data on the basis of: (a) contractual necessity — to perform our obligations under the Terms of Service; and (b) legitimate interest — to maintain the integrity and security of the network.</p>

        <h3>5. Data Sharing</h3>
        <p>We do not sell, rent, or trade your personal data to any third party. Limited data may be shared with: (a) blockchain networks for USDT transaction processing (your wallet address only, which is inherently public on the TRON network); (b) service providers who assist with platform security and hosting, under strict confidentiality agreements; and (c) law enforcement or regulators, if required by Philippine law.</p>

        <h3>6. Data Retention</h3>
        <p>Account data is retained for the lifetime of the network and for a minimum of five (5) years after network closure, to comply with financial record-keeping obligations. You may request deletion of non-transactional data (e.g., support messages) at any time.</p>

        <h3>7. Security</h3>
        <p>The platform uses CSRF (Cross-Site Request Forgery) protection on all forms, rate-limited login to prevent brute-force attacks, encrypted session tokens, and HTTPS for all data in transit. Passwords are stored as salted hashes — they are never stored in plain text.</p>

        <h3>8. Your Rights</h3>
        <p>Under the Philippine Data Privacy Act of 2012 (Republic Act 10173), you have the right to: access your personal data; correct inaccurate data; object to processing; and request deletion of data not required for legal or contractual compliance. Submit requests to: <a href="mailto:support@altasfarm.com" style="color:var(--green-mid);">support@altasfarm.com</a></p>

        <h3>9. Cookies</h3>
        <p>AltasFarm uses session cookies strictly for login and security purposes. We do not use advertising cookies or third-party tracking pixels. You may disable cookies in your browser, but this may affect login functionality.</p>

        <h3>10. Children</h3>
        <p>AltasFarm is not intended for individuals under 18 years of age. We do not knowingly collect personal information from minors. If we discover a minor has registered, the account will be suspended immediately.</p>

        <h3>11. Changes to This Policy</h3>
        <p>We may update this Privacy Policy. The effective date at the top will reflect any changes. We will notify registered members of material changes via their registered email address.</p>

        <p class="meta-line">AltasFarm · Santiago, Isabela, Philippines · RA 10173 compliant · Version 1.0, effective January 1, 2025</p>
      </div>
    </div>
  </div>

  <!-- ── Compliance Modal ────────────────────────────────────────── -->
  <div class="af-modal-backdrop" id="modal-compliance" role="dialog" aria-modal="true" aria-labelledby="compliance-title" onclick="closeModalOnBackdrop(event,'modal-compliance')">
    <div class="af-modal">
      <div class="af-modal-header">
        <div>
          <h2 id="compliance-title">Compliance & Legal Disclosure</h2>
          <p>Transparency statement — January 2025</p>
        </div>
        <button class="af-modal-close" onclick="closeModal('modal-compliance')" aria-label="Close">✕</button>
      </div>
      <div class="af-modal-body">

        <div class="highlight-box">
          <p>AltasFarm is committed to operating transparently. This page discloses our regulatory standing, the nature of the network, and the risks members should understand before joining.</p>
        </div>

        <h3>1. Business Registration</h3>
        <p>AltasFarm is currently in the process of registering as a sole proprietorship with the Philippine Department of Trade and Industry (DTI). Business name registration application is pending as of January 2025. Upon approval, our DTI certificate number will be published here. AltasFarm operates from Santiago, Isabela, Philippines (postal code 3006).</p>

        <h3>2. Nature of the Network</h3>
        <p>AltasFarm is a direct referral network structured as a binary compensation plan. It is backed by a real poultry operation — meaning the entry fee is partially invested in Philippine broiler farming activities. The network is not a bank, not a lending institution, and not a securities issuer. It does not offer guaranteed returns.</p>

        <p>The compensation structure involves referral-based commissions that are dependent on new member registrations. Because the network is hard-capped at 1,000 members, the binary tree will stop generating new pairing bonuses once all seats are filled. Members who join later in the network will have fewer pairing opportunities than early members. This is a structural characteristic members must understand before joining.</p>

        <div class="warn-box">
          <p><strong>Important:</strong> AltasFarm is not registered with the Philippine Securities and Exchange Commission (SEC) as an investment company or securities dealer. It operates as a referral-based community network, not as a registered investment vehicle. Participation is voluntary and carries financial risk.</p>
        </div>

        <h3>3. Anti-Money Laundering (AML)</h3>
        <p>AltasFarm prohibits the use of the platform for money laundering, terrorism financing, or any transaction linked to criminal activity. We collect member identity information consistent with know-your-member (KYM) practices. Suspicious activity will be reported to the appropriate Philippine authorities in compliance with Republic Act 9160 (Anti-Money Laundering Act) as amended.</p>

        <h3>4. Data Privacy Compliance</h3>
        <p>AltasFarm processes personal data in accordance with the Philippine Data Privacy Act of 2012 (Republic Act 10173) and its implementing rules and regulations. Our Privacy Policy details what data we collect, how we use it, and how members can exercise their rights.</p>

        <h3>5. Consumer Protection</h3>
        <p>AltasFarm operates in accordance with the Philippine Consumer Act (Republic Act 7394). Members have the right to honest and accurate information about the platform, its earning structure, and its limitations. Any member who believes they have been misled by a sponsor's claims may report the matter to <a href="mailto:support@altasfarm.com" style="color:var(--green-mid);">support@altasfarm.com</a>. We take misrepresentation by sponsors seriously and will investigate reported cases.</p>

        <h3>6. USDT / Cryptocurrency Disclosure</h3>
        <p>All payouts on AltasFarm are made in USDT (Tether) on the TRON network (TRC20). USDT is a stablecoin pegged to the US Dollar. While USDT is designed to maintain a 1:1 peg, cryptocurrency carries inherent risks including de-pegging events, blockchain network congestion, and wallet loss. AltasFarm is not liable for losses arising from cryptocurrency market conditions. Members are responsible for the security of their own USDT wallets.</p>

        <h3>7. Income Disclaimer</h3>
        <p>Earnings from AltasFarm are not guaranteed. The amount a member earns depends on their own referral activity, the activity of their network, and the pace of overall registrations within the 1,000-seat limit. AltasFarm does not represent, warrant, or imply that any specific income level is achievable. Do not invest funds you cannot afford to lose.</p>

        <h3>8. Reporting and Contact</h3>
        <p>For compliance concerns, legal inquiries, or to report a policy violation: <a href="mailto:support@altasfarm.com" style="color:var(--green-mid);">support@altasfarm.com</a><br>
          Mailing address: AltasFarm, Balintocatoc, Santiago, Isabela 3311, Philippines</p>

        <p class="meta-line">This disclosure is provided in good faith as part of AltasFarm's commitment to operating transparently. Last reviewed: January 2025.</p>
      </div>
    </div>
  </div>

  <!-- ── Contact Modal ──────────────────────────────────────────── -->
  <div class="af-modal-backdrop" id="modal-contact" role="dialog" aria-modal="true" aria-labelledby="contact-title" onclick="closeModalOnBackdrop(event,'modal-contact')">
    <div class="af-modal">
      <div class="af-modal-header">
        <div>
          <h2 id="contact-title">Contact AltasFarm</h2>
          <p>We respond within 24 hours on business days (Mon–Sat)</p>
        </div>
        <button class="af-modal-close" onclick="closeModal('modal-contact')" aria-label="Close">✕</button>
      </div>
      <div class="af-modal-body">

        <h3>Email Support</h3>
        <p>For account questions, withdrawal issues, compliance concerns, or general inquiries:</p>
        <p><a href="mailto:support@altasfarm.com" style="font-size:1.1rem;font-weight:700;color:var(--green-mid);">support@altasfarm.com</a></p>

        <h3>Telegram Community</h3>
        <p>For real-time updates, network announcements, and peer support from the AltasFarm community:</p>
        <p><a href="https://t.me/altasfarm" target="_blank" rel="noopener" style="font-size:1.1rem;font-weight:700;color:var(--green-mid);">t.me/altasfarm</a></p>

        <h3>Facebook Page</h3>
        <p>Follow us for farm updates, community stories, and network announcements:</p>
        <p><a href="https://www.facebook.com/altasfarm" target="_blank" rel="noopener" style="font-size:1.1rem;font-weight:700;color:var(--green-mid);">facebook.com/altasfarm</a></p>

        <h3>Office Address</h3>
        <p>AltasFarm<br>
          Balintocatoc, Santiago<br>
          Isabela 3311<br>
          Philippines</p>
        <p style="font-size:.82rem;color:var(--muted);">Walk-in visits are by appointment only. Contact us via email or Telegram to schedule.</p>

        <h3>Support Hours</h3>
        <p>Monday to Saturday · 8:00 AM – 6:00 PM<br>
          Philippine Standard Time (PST · UTC+8)</p>

        <div class="highlight-box">
          <p>For urgent account issues (locked account, incorrect withdrawal address), include your registered email and member ID in your message for faster resolution.</p>
        </div>

      </div>
    </div>
  </div>


  <!-- ════════════════════════════════════════════════════════════
     SITE HEADER (fixed wrapper: contact strip + nav)
════════════════════════════════════════════════════════════ -->
  <header class="site-header">

    <!-- CONTACT STRIP (gives crawlers a top-level address) -->
    <div class="contact-strip" role="banner">
      <div class="contact-strip-inner">
        <a href="mailto:support@altasfarm.com" class="contact-item">
          <span>✉</span> support@altasfarm.com
        </a>
        <a href="https://t.me/altasfarm" target="_blank" rel="noopener" class="contact-item">
          <span>✈</span> t.me/altasfarm
        </a>
        <span class="contact-item">
          <span>📍</span> Santiago, Isabela, Philippines
        </span>
        <span class="contact-item">
          <span>🕐</span> Mon–Sat · 8 AM–6 PM PST
        </span>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════════════
       NAV
    ════════════════════════════════════════════════════════ -->
    <nav>
      <div class="nav-inner">
        <a href="#" class="nav-logo">
          <img src="<?= $frontend ?>/logo.png" alt="AltasFarm logo" width="auto" height="36" onerror="this.style.display='none'">
          <span class="nav-logo-text">AltasFarm</span>
        </a>
        <ul class="nav-links">
          <li><a href="#about">About</a></li>
          <li><a href="#how">How It Works</a></li>
          <li><a href="#plan">Earn Plan</a></li>
          <li><a href="#packages">Packages</a></li>
          <li><a href="#why">Why Us</a></li>
        </ul>
        <div class="nav-cta">
          <a href="<?= $base ?>/?page=login" class="nav-btn-login">Login</a>
          <a href="<?= $base ?>/?page=register" class="nav-btn-register">Join Now</a>
        </div>
        <button class="nav-mobile-toggle" aria-label="Toggle Menu" onclick="toggleMobileMenu()">☰</button>
      </div>
    </nav>

  </header><!-- /site-header -->

  <!-- Mobile Menu -->
  <div class="mobile-menu" id="mobileMenu" aria-hidden="true">
    <div class="mobile-menu-header">
      <span class="nav-logo-text" style="color:#fff">AltasFarm</span>
      <button class="mobile-close-btn" onclick="toggleMobileMenu()" aria-label="Close Menu">✕</button>
    </div>
    <a href="#about" onclick="toggleMobileMenu()">About</a>
    <a href="#how" onclick="toggleMobileMenu()">How It Works</a>
    <a href="#plan" onclick="toggleMobileMenu()">Earn Plan</a>
    <a href="#packages" onclick="toggleMobileMenu()">Packages</a>
    <a href="#why" onclick="toggleMobileMenu()">Why Us</a>
    <div style="margin-top:2rem;display:flex;flex-direction:column;gap:1rem;">
      <a href="<?= $base ?>/?page=login" style="color:var(--gold);text-align:center;">Sign In</a>
      <a href="<?= $base ?>/?page=register" class="btn-gold" style="text-align:center;">Join Now</a>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════════════
     HERO
════════════════════════════════════════════════════════════ -->
  <section class="hero" id="hero">
    <div class="hero-content fade-up">
      <div class="hero-eyebrow">🐓 Philippine Poultry Network · Est. 2024</div>
      <h1 class="hero-title">
        Real Farming.<br>
        <span>Shared Income.</span>
      </h1>
      <p class="hero-desc">
        AltasFarm ties a real poultry operation to a binary referral network. You invest in a farm package, bring in your team, and earn commissions as your network grows — all tracked in real time on your dashboard.
      </p>
      <div class="hero-actions">
        <a href="<?= $base ?>/?page=register" class="btn-gold">🌱 Get Started</a>
        <a href="#how" class="btn-outline" style="color:#fff;border-color:rgba(255,255,255,.6);">Learn How It Works</a>
      </div>
      <div class="hero-stats">
        <div>
          <div class="hero-stat-val">₱2,000</div>
          <div class="hero-stat-label">Per Pair Bonus</div>
        </div>
        <div>
          <div class="hero-stat-val">10</div>
          <div class="hero-stat-label">Unilevel Levels</div>
        </div>
        <div>
          <div class="hero-stat-val">Real</div>
          <div class="hero-stat-label">Farm Products</div>
        </div>
      </div>
    </div>

    <div class="hero-badge fade-up">
      <div style="font-family:var(--serif);font-size:1.5rem;font-weight:700;color:#fff;margin-bottom:.5rem;">Starter Entry</div>
      <div style="font-family:var(--mono);font-size:2rem;font-weight:500;color:var(--gold);">₱10,000</div>
      <div style="height:1px;background:rgba(255,255,255,.1);margin:1rem 0;"></div>
      <div style="font-family:var(--serif);font-size:1.5rem;font-weight:700;color:#fff;margin-bottom:.5rem;">Pair Earned</div>
      <div style="font-family:var(--mono);font-size:2rem;font-weight:500;color:var(--gold);">₱2,000</div>
      <div style="height:1px;background:rgba(255,255,255,.1);margin:1rem 0;"></div>
      <div style="font-family:var(--serif);font-size:1.5rem;font-weight:700;color:#fff;margin-bottom:.5rem;">Daily Cap</div>
      <div style="font-family:var(--mono);font-size:2rem;font-weight:500;color:var(--gold);">3×</div>
    </div>

    <div class="hero-illustration fade-up">
      <svg viewBox="0 0 400 400" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="320" cy="80" r="40" fill="#FFD54F" class="svg-sun" />
        <g class="svg-cloud" transform="translate(20,40)">
          <path d="M30,20 Q50,0 70,20 T110,20 Q130,40 110,60 H30 Q10,40 30,20" fill="rgba(255,255,255,0.2)" />
        </g>
        <path d="M0,320 Q100,280 200,320 T400,320 V400 H0 Z" fill="rgba(26,58,30,0.8)" />
        <path d="M0,350 Q150,320 400,360 V400 H0 Z" fill="rgba(26,58,30,0.9)" />
        <g transform="translate(180,340)">
          <path d="M0,0 Q10,-50 0,-100" stroke="#4caf50" stroke-width="8" stroke-linecap="round" class="svg-plant" />
          <path d="M0,-50 Q-20,-60 -30,-40 Q-10,-40 0,-50" fill="#66bb6a" class="svg-leaf-1" />
          <path d="M0,-80 Q20,-90 30,-70 Q10,-70 0,-80" fill="#66bb6a" class="svg-leaf-2" />
        </g>
        <g transform="translate(240,310)">
          <circle cx="0" cy="0" r="25" fill="#d4a017" />
          <circle cx="0" cy="-5" r="18" fill="#fbc02d" />
          <path d="M10,0 Q20,-5 20,5 Q10,5 10,0" fill="#ef5350" transform="rotate(30)" />
        </g>
      </svg>
    </div>
  </section>

  <!-- ════════════════════════════════════════════════════════════
     TRUST BAR (immediately after hero — high visibility for crawlers)
════════════════════════════════════════════════════════════ -->
  <div class="trust-bar" role="complementary" aria-label="Trust signals">
    <div class="trust-bar-inner">
      <div class="trust-item"><span class="trust-icon">🔒</span> <strong>HTTPS</strong> Secured</div>
      <div class="trust-item"><span class="trust-icon">📍</span> Based in <strong>Isabela, PH</strong></div>
      <div class="trust-item"><span class="trust-icon">✉</span> <strong>support@altasfarm.com</strong></div>
      <div class="trust-item"><span class="trust-icon">🛡</span> RA 10173 <strong>Data Privacy</strong></div>
      <div class="trust-item"><span class="trust-icon">📅</span> Est. <strong>2024</strong></div>
      <div class="trust-item"><span class="trust-icon">₮</span> USDT <strong>TRC20</strong> Payouts</div>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════════════
     MARQUEE
════════════════════════════════════════════════════════════ -->
  <div class="marquee-wrap" aria-hidden="true">
    <div class="marquee-track">
      <div class="marquee-item"><span class="marquee-dot"></span>1,000 Members Only</div>
      <div class="marquee-item"><span class="marquee-dot"></span>Real Poultry Products</div>
      <div class="marquee-item"><span class="marquee-dot"></span>Instant Commissions</div>
      <div class="marquee-item"><span class="marquee-dot"></span>10-Level Unilevel</div>
      <div class="marquee-item"><span class="marquee-dot"></span>USDT TRC20 Payouts</div>
      <div class="marquee-item"><span class="marquee-dot"></span>Philippine Farms</div>
      <div class="marquee-item"><span class="marquee-dot"></span>Daily Pair Bonuses</div>
      <div class="marquee-item"><span class="marquee-dot"></span>Bayanihan Network</div>
      <div class="marquee-item"><span class="marquee-dot"></span>Closed Community</div>
      <div class="marquee-item"><span class="marquee-dot"></span>Binary Structure</div>
      <div class="marquee-item"><span class="marquee-dot"></span>1,000 Members Only</div>
      <div class="marquee-item"><span class="marquee-dot"></span>Real Poultry Products</div>
      <div class="marquee-item"><span class="marquee-dot"></span>Instant Commissions</div>
      <div class="marquee-item"><span class="marquee-dot"></span>10-Level Unilevel</div>
      <div class="marquee-item"><span class="marquee-dot"></span>USDT TRC20 Payouts</div>
      <div class="marquee-item"><span class="marquee-dot"></span>Bayanihan Network</div>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════════════
     ABOUT
════════════════════════════════════════════════════════════ -->
  <section class="about" id="about">
    <div class="container">
      <div class="about-grid">
        <div class="about-img-wrap fade-up">
          <div class="about-img">
            <img src="<?= $frontend ?>/about.jpg" alt="Rhode Island Red and Australorp chickens on the AltasFarm partner farm" loading="lazy">
          </div>
          <div class="about-chip">1,000<small>Seats Total</small></div>
        </div>
        <div class="fade-up">
          <div class="tag">Our Story</div>
          <h2 class="section-title">Small on Purpose. Solid by Design.</h2>
          <p class="section-lead">
            Most networks grow without a ceiling — and dilute without a floor. AltasFarm chose a different path: cap the membership at 1,000, keep the structure flat with one package, and let bayanihan do the rest. A community this size knows its people. It moves deliberately. It holds.
          </p>
          <ul class="about-features">
            <li>Hard cap of 1,000 members — registration closes permanently when full</li>
            <li>Backed by real, operating Philippine poultry farms in Isabela</li>
            <li>Commissions fire the instant a new member registers</li>
            <li>One package tier — every member enters as an equal</li>
            <li>All payouts in USDT TRC20 — borderless, no bank required</li>
            <li>Full audit trail: every commission logged and traceable</li>
          </ul>
          <div style="margin-top:2rem;">
            <a href="<?= $base ?>/?page=register" class="btn-primary">Secure Your Place →</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ════════════════════════════════════════════════════════════
     HOW IT WORKS
════════════════════════════════════════════════════════════ -->
  <section class="how" id="how">
    <div class="container">
      <div class="tag tag-green" style="background:rgba(76,175,80,.15);color:rgba(255,255,255,.7);">Simple Process</div>
      <h2 class="section-title">How AltasFarm Works</h2>
      <p class="section-lead">Six steps from your first registration to your first withdrawal. The structure is binary — your income grows as both sides of your tree fill, within a defined daily cap and a community that stops at 1,000.</p>
      <div class="steps-grid">
        <div class="step-card fade-up">
          <div class="step-num">01</div>
          <div class="step-icon">🎟️</div>
          <div class="step-title">Get Your Code</div>
          <div class="step-desc">Obtain a registration code from your sponsor or through our admin-approved channels. There is only one package — the code corresponds to it.</div>
        </div>
        <div class="step-card fade-up">
          <div class="step-num">02</div>
          <div class="step-icon">📝</div>
          <div class="step-title">Register &amp; Place</div>
          <div class="step-desc">Create your account, choose your sponsor, and select your binary position — left or right leg. Your seat among the 1,000 is confirmed on registration.</div>
        </div>
        <div class="step-card fade-up">
          <div class="step-num">03</div>
          <div class="step-icon">👥</div>
          <div class="step-title">Build Your Team</div>
          <div class="step-desc">Share your referral link and bring in your network. Every direct referral earns you ₱500 — credited the moment they register.</div>
        </div>
        <div class="step-card fade-up">
          <div class="step-num">04</div>
          <div class="step-icon">💸</div>
          <div class="step-title">Earn Pair Bonuses</div>
          <div class="step-desc">When a left-right pair forms anywhere beneath you, ₱2,000 fires to your wallet in real time. Daily pairs are capped to keep the system sustainable as it fills.</div>
        </div>
        <div class="step-card fade-up">
          <div class="step-num">05</div>
          <div class="step-icon">🔗</div>
          <div class="step-title">Unilevel Royalties</div>
          <div class="step-desc">Generational bonuses paid 10 levels deep through your sponsor chain. Because the network is capped at 1,000, every level is reachable — no hollow depth.</div>
        </div>
        <div class="step-card fade-up">
          <div class="step-num">06</div>
          <div class="step-icon">₮</div>
          <div class="step-title">Withdraw in USDT</div>
          <div class="step-desc">All earnings settle in USDT TRC20. Whether you are in Philippines or abroad, your wallet receives the same way — no remittance fees, no cut, no geography.</div>
        </div>
      </div>
    </div>
  </section>

  <!-- ════════════════════════════════════════════════════════════
     COMPENSATION PLAN
════════════════════════════════════════════════════════════ -->
  <section class="plan" id="plan">
    <div class="container">
      <div class="plan-header">
        <div class="tag">Compensation Plan</div>
        <h2 class="section-title">Three Streams. One Entry.</h2>
        <p class="section-lead">Every member enters at the same level and accesses all three income streams from day one. The binary, the referral, and the unilevel — none of them locked behind a higher tier.</p>
      </div>
      <div class="plan-grid">
        <div class="plan-card fade-up">
          <div class="plan-card-icon">🤝</div>
          <div class="plan-card-title">Binary Pairing Bonus</div>
          <div class="plan-card-amount">₱2,000</div>
          <div class="plan-card-desc">Earned every time a left-right pair forms anywhere in your binary downline. Capped at 3 pairs per day — a ceiling that keeps payouts consistent and the network stable.</div>
        </div>
        <div class="plan-card featured fade-up">
          <div class="plan-card-icon">👥</div>
          <div class="plan-card-title">Direct Referral Bonus</div>
          <div class="plan-card-amount">₱500</div>
          <div class="plan-card-desc">Credited instantly every time someone you referred registers. Because the community is capped at 1,000, referral slots are finite — your network fills in before the door closes.</div>
        </div>
        <div class="plan-card fade-up">
          <div class="plan-card-icon">🔗</div>
          <div class="plan-card-title">Unilevel Bonus</div>
          <div class="plan-card-amount">Up to ₱300</div>
          <div class="plan-card-desc">Generational bonuses 10 levels deep through your sponsor chain. Passive income that compounds as your wider network grows — within the 1,000-member ceiling.</div>
        </div>
      </div>
      <div class="plan-note">
        <strong>Unilevel Breakdown:</strong> Level 1 ₱300 · Level 2 ₱200 · Level 3 ₱150 · Level 4–5 ₱100 · Levels 6–10 ₱50 per member registration.
      </div>
    </div>
  </section>

  <!-- ════════════════════════════════════════════════════════════
     PACKAGE
════════════════════════════════════════════════════════════ -->
  <section class="packages" id="packages">
    <div class="container">
      <div class="packages-header">
        <div class="tag">The Package</div>
        <h2 class="section-title">One Entry. No Tiers.</h2>
        <p class="section-lead" style="margin:0 auto;">AltasFarm runs a single package. No premium tiers, no VIP upgrades, no hierarchy based on how much you paid. Everyone enters the same way — and everyone accesses the same earning structure from the same starting point.</p>
      </div>

      <div class="closed-banner fade-up">
        <div class="closed-banner-icon">🔒</div>
        <div class="closed-banner-text">
          <strong>This is a closed network of 1,000.</strong>
          <span>Once all seats are filled, registration closes permanently. There is no waitlist, no second batch, and no re-opening. The hard cap is what keeps this community undiluted.</span>
        </div>
      </div>

      <div class="pkg-single-wrap">
        <div class="pkg-single fade-up">
          <div class="pkg-img">
            <img src="<?= $frontend ?>/pkg-starter.jpg" alt="Broiler Starter Package — Day-old chicks on AltasFarm partner farm" loading="lazy">
          </div>
          <div class="pkg-body">
            <div class="pkg-badge">🐣 Broiler Starter</div>
            <div class="pkg-title">The AltasFarm Seat</div>
            <p class="pkg-desc">Your entry into the network. One seat, one package, backed by a real Philippine poultry operation. All three earning streams are active from the moment you register.</p>
            <ul class="pkg-features">
              <li>Full binary tree placement — left or right leg of your choice</li>
              <li>₱2,000 per binary pair · capped at 3 pairs per day</li>
              <li>₱500 direct referral bonus per recruit</li>
              <li>10-level unilevel generational bonuses</li>
              <li>Real-time dashboard — binary tree, wallet, full history</li>
              <li>All earnings paid out in USDT TRC20</li>
            </ul>
            <div class="usdt-badge">₮ USDT TRC20 — Sole Payout Method</div>
            <div class="pkg-price">₱10,000 <small>one-time entry fee</small></div>
            <a href="<?= $base ?>/?page=register" class="btn-primary" style="width:100%;font-size:.95rem;">Claim Your Seat →</a>
          </div>
        </div>
      </div>
      <p style="text-align:center;margin-top:2rem;font-size:.82rem;color:var(--muted);">A registration code from your sponsor is required to join. Contact your sponsor or the admin team before seats close.</p>
    </div>
  </section>

  <!-- ════════════════════════════════════════════════════════════
     WHY ALTASFARM
════════════════════════════════════════════════════════════ -->
  <section class="why" id="why">
    <div class="container">
      <div class="why-grid">
        <div class="why-img fade-up">
          <img src="<?= $frontend ?>/why.jpg" alt="Farmer collecting eggs from free-range chickens at AltasFarm partner operation" loading="lazy">
        </div>
        <div class="fade-up">
          <div class="tag">Why AltasFarm</div>
          <h2 class="section-title">Constraints Are the Point</h2>
          <p class="section-lead">The 1,000-member cap is not a marketing device — it is how the network stays intact. A smaller, deliberate community earns more per seat, knows its members, and moves with the kind of collective care that Filipinos call bayanihan.</p>
          <div class="why-items">
            <div class="why-item">
              <div class="why-icon">⚡</div>
              <div>
                <div class="why-item-title">Real-Time Commission Firing</div>
                <div class="why-item-desc">Every bonus fires the instant a new member registers. No batch processing, no overnight queues — commissions are computed and credited on registration itself.</div>
              </div>
            </div>
            <div class="why-item">
              <div class="why-icon">🌳</div>
              <div>
                <div class="why-item-title">Live Binary Tree Visualization</div>
                <div class="why-item-desc">Your dashboard shows your binary network in real time. You see exactly where each member sits and how your legs are growing toward the cap.</div>
              </div>
            </div>
            <div class="why-item">
              <div class="why-icon">₮</div>
              <div>
                <div class="why-item-title">USDT — No Geography, No Bank</div>
                <div class="why-item-desc">Payouts settle exclusively in USDT TRC20. Whether you are in Philippines or working abroad, the same wallet receives the same way — no remittance cut, no delay.</div>
              </div>
            </div>
            <div class="why-item">
              <div class="why-icon">🔒</div>
              <div>
                <div class="why-item-title">Transparent &amp; Secure Platform</div>
                <div class="why-item-desc">Every commission has a full audit trail. The platform includes CSRF protection, rate-limited login, and secure session management built in from the start.</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ════════════════════════════════════════════════════════════
     TESTIMONIALS
════════════════════════════════════════════════════════════ -->
  <section class="testi" id="testimonials">
    <div class="container">
      <div class="testi-header">
        <div class="tag">From the Network</div>
        <h2 class="section-title">What Early Members Say</h2>
      </div>
      <div class="testi-grid">
        <div class="testi-card fade-up">
          <div class="testi-stars">★★★★★</div>
          <div class="testi-quote">"Gusto ko na may hangganan ang community. Hindi ako malalagyan ng libo-libong strangers. Kilala ko ang mga tao sa network ko — at mas komportable akong mag-refer ng kilala."</div>
          <div class="testi-author">
            <div class="testi-avatar" style="background:#2d6a35;">R</div>
            <div>
              <div class="testi-name">Roger A.</div>
              <div class="testi-role">Member since Jan 2024 · Isabela</div>
            </div>
          </div>
        </div>
        <div class="testi-card fade-up">
          <div class="testi-stars">★★★★★</div>
          <div class="testi-quote">"Ang USDT payout ang dahilan kung bakit nag-join ako. OFW ang aking asawa — mas madali para sa amin na mag-transact ng hindi dumaan sa remittance. Direkta na."</div>
          <div class="testi-author">
            <div class="testi-avatar" style="background:#d4a017;color:#1a3a1e;">M</div>
            <div>
              <div class="testi-name">Maria Santos</div>
              <div class="testi-role">Member since Mar 2024 · Batangas</div>
            </div>
          </div>
        </div>
        <div class="testi-card fade-up">
          <div class="testi-stars">★★★★★</div>
          <div class="testi-quote">"Farmer ako at isang package lang ang nagpasimple ng lahat — hindi na ako nag-alinlangan kung kukuha ng mas mataas na tier. Pantay-pantay tayo dito. Bayanihan talaga."</div>
          <div class="testi-author">
            <div class="testi-avatar" style="background:#6b4c2a;">J</div>
            <div>
              <div class="testi-name">Jose Dela Cruz</div>
              <div class="testi-role">Member since Feb 2024 · Nueva Ecija</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ════════════════════════════════════════════════════════════
     CTA
════════════════════════════════════════════════════════════ -->
  <section class="cta-section">
    <div class="cta-inner">
      <div class="tag" style="background:rgba(212,160,23,.2);color:var(--gold-light);">Limited Seats</div>
      <h2>1,000 Seats. Not One More.</h2>
      <p>The network closes the moment the last seat is taken. There is no second wave, no waitlist, and no appeal. If you are reading this, seats are still open — but that changes with every registration that comes in before yours.</p>
      <div class="cta-buttons">
        <a href="<?= $base ?>/?page=register" class="btn-gold" style="font-size:1rem;padding:1rem 2.5rem;">🌱 Claim Your Seat Now</a>
      </div>
      <a href="<?= $base ?>/?page=login" class="cta-login">Already a member? Sign in →</a>
    </div>
  </section>

  <!-- ════════════════════════════════════════════════════════════
     FOOTER
════════════════════════════════════════════════════════════ -->
  <footer itemscope itemtype="https://schema.org/Organization">
    <div class="footer-inner">
      <div class="footer-top">

        <!-- Brand column -->
        <div>
          <div class="footer-brand-name" itemprop="name">AltasFarm</div>
          <div class="footer-brand-desc" itemprop="description">A closed 1,000-member Philippine poultry network. One package, one payout currency, one community built on bayanihan.</div>

          <!-- Address (machine-readable for ScamAdviser / Schema) -->
          <address itemprop="address" itemscope itemtype="https://schema.org/PostalAddress"
            style="font-style:normal;font-size:.82rem;color:rgba(255,255,255,.45);margin-top:1rem;line-height:1.7;">
            <span itemprop="streetAddress">Balintocatoc</span>,
            <span itemprop="addressLocality">Santiago</span>,
            <span itemprop="addressRegion">Isabela</span>
            <span itemprop="postalCode">3311</span><br>
            <span itemprop="addressCountry">Philippines</span>
          </address>

          <!-- Social -->
          <div class="footer-social" aria-label="Social media links">
            <a href="https://www.facebook.com/altasfarm" target="_blank" rel="noopener" aria-label="Facebook" title="AltasFarm on Facebook">f</a>
            <a href="https://t.me/altasfarm" target="_blank" rel="noopener" aria-label="Telegram" title="AltasFarm on Telegram">✈</a>
            <a href="mailto:support@altasfarm.com" aria-label="Email Support" title="Email support@altasfarm.com">✉</a>
          </div>
        </div>

        <!-- Platform -->
        <div>
          <div class="footer-col-title">Platform</div>
          <ul class="footer-links">
            <li><a href="<?= $base ?>/?page=login">Member Login</a></li>
            <li><a href="<?= $base ?>/?page=register">Claim a Seat</a></li>
            <li><a href="#how">How It Works</a></li>
            <li><a href="#plan">Earn Plan</a></li>
          </ul>
        </div>

        <!-- Company -->
        <div>
          <div class="footer-col-title">Company</div>
          <ul class="footer-links">
            <li><a href="#about">About Us</a></li>
            <li><a href="#why">Why AltasFarm</a></li>
            <li><a href="#testimonials">Testimonials</a></li>
            <li><a href="#" onclick="openModal('modal-contact');return false;">Contact Us</a></li>
          </ul>
        </div>

        <!-- Support / Legal -->
        <div>
          <div class="footer-col-title">Support &amp; Legal</div>
          <ul class="footer-links">
            <li><a href="#" onclick="openModal('modal-faq');return false;">FAQ</a></li>
            <li><a href="#" onclick="openModal('modal-tos');return false;">Terms of Service</a></li>
            <li><a href="#" onclick="openModal('modal-privacy');return false;">Privacy Policy</a></li>
            <li><a href="#" onclick="openModal('modal-compliance');return false;">Compliance</a></li>
          </ul>
        </div>

      </div><!-- /footer-top -->

      <!-- Footer bottom bar -->
      <div class="footer-bottom">
        <div class="footer-copy">
          © 2024–<script>
            document.write(new Date().getFullYear())
          </script> AltasFarm · All rights reserved · Philippines 🇵🇭<br>
          <span style="font-size:.75rem;opacity:.6;">Registered business name application pending · DTI — Isabela</span>
        </div>
        <div class="footer-legal">
          <a href="#" onclick="openModal('modal-privacy');return false;">Privacy Policy</a>
          <a href="#" onclick="openModal('modal-tos');return false;">Terms of Service</a>
          <a href="#" onclick="openModal('modal-compliance');return false;">Compliance</a>
        </div>
      </div>

    </div>
  </footer>

  <!-- Back to Top -->
  <button id="backToTop" aria-label="Back to Top">↑</button>

  <!-- ════════════════════════════════════════════════════════════
     SCRIPTS
════════════════════════════════════════════════════════════ -->
  <script src="<?= $frontend ?>/script.js"></script>

  <script>
    /* ── Sync fixed header height → CSS var so hero always clears it ── */
    (function() {
      function syncHeaderH() {
        var hdr = document.querySelector('.site-header');
        if (hdr) {
          document.documentElement.style.setProperty('--header-h', hdr.offsetHeight + 'px');
        }
      }
      syncHeaderH();
      window.addEventListener('resize', syncHeaderH);
      /* Re-check after fonts load (can shift layout) */
      if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(syncHeaderH);
      }
    })();
  </script>
</body>

</html>