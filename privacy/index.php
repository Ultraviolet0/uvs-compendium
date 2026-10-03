<?php
$page_title = "Privacy | UV's Compendium";
$page_description = "What UV's Compendium stores about visitors and members, and why.";
$base_path = '../';
$current_page = 'privacy';
$page_styles = ['guides/css/styles.css'];

require_once __DIR__ . '/../includes/public_header.php';
?>

<nav class="guide-breadcrumbs" aria-label="Breadcrumb">
  <ol class="guide-breadcrumb-list">
    <li><a href="<?= site_url() ?>">Home</a></li>
    <li aria-current="page">Privacy</li>
  </ol>
</nav>

<article class="section-panel flow-lg privacy-page" aria-labelledby="page-title">
  <header class="flow">
    <p class="eyebrow">Plain-language notice</p>
    <h1 id="page-title">Privacy</h1>
    <p class="hero-copy">UV's Compendium is a small, non-commercial fan site. This page describes what it stores and why. It is a factual description of how the site works, not a legal contract.</p>
  </header>

  <div class="guide-content flow-lg">
    <section class="guide-section">
      <h2>Reading the site</h2>
      <p>The calculators, guides, and reference documents work without an account. Browsing them does not set any cookies. If you choose the light or dark theme, that choice is saved in your browser's local storage on your own device. The web host keeps ordinary server logs (such as IP addresses and requested pages) for security and operation.</p>
    </section>

    <section class="guide-section">
      <h2>Member accounts</h2>
      <p>When you create an account, the site stores:</p>
      <ul class="guide-list">
        <li><strong>Username</strong> — public; shown on your profile and your guides.</li>
        <li><strong>Email address</strong> — private; never shown publicly. It is used to recognise your account, to send password-reset links, and for occasional account notices such as approval. It is not used for marketing.</li>
        <li><strong>Password</strong> — stored only as a one-way hash; nobody, including administrators, can read it.</li>
        <li><strong>Optional profile details</strong> you add: avatar, bio, preferred game, website, Discord name, and characters. These are public once your account is active.</li>
        <li><strong>Guides and images</strong> you write or upload, including their earlier revisions, which administrators use to review changes.</li>
        <li><strong>Account records</strong> such as join date, last sign-in time, approval status, an optional theme preference, and, if you enable it, an encrypted two-factor authentication secret.</li>
      </ul>
    </section>

    <section class="guide-section">
      <h2>Sign-in sessions</h2>
      <p>Signing in sets one essential session cookie, which keeps you signed in and protects forms against forged requests. It expires when you sign out, after a period of inactivity, or after a maximum session length. Forms that start a session before sign-in (such as the sign-in and sign-up pages) set the same cookie.</p>
    </section>

    <section class="guide-section">
      <h2>Anti-abuse measures</h2>
      <p>The sign-up and password-reset forms use <a href="https://www.cloudflare.com/products/turnstile/" rel="noopener noreferrer">Cloudflare Turnstile</a> to tell people from bots. Turnstile is loaded from Cloudflare only on those pages, and Cloudflare processes information about your browser and connection under <a href="https://www.cloudflare.com/turnstile-privacy-policy/" rel="noopener noreferrer">its own privacy policy</a>. To limit repeated attempts, the site counts recent sign-in and sign-up attempts using keyed, one-way hashes of IP addresses and account identifiers; these counters expire within a day.</p>
    </section>

    <section class="guide-section">
      <h2>Uploaded images</h2>
      <p>Uploaded avatars and guide images are re-processed on the server: they are resized, compressed, and stripped of embedded metadata such as camera details and GPS location. Avatars of active members and images in published guides are public. Images that are not used are removed after a few days.</p>
    </section>

    <section class="guide-section">
      <h2>Embedded videos</h2>
      <p>Guides may embed YouTube videos using YouTube's privacy-enhanced mode (youtube-nocookie.com). YouTube receives information about your visit when an embedded player loads.</p>
    </section>

    <section class="guide-section">
      <h2>Who can see your information</h2>
      <p>Site administrators can see account details, including email addresses, to review new accounts, moderate guides, and keep the site safe. Administrative actions are recorded in an internal audit log. Information is not sold or shared with advertisers; the site has no advertising or analytics trackers.</p>
    </section>

    <section class="guide-section">
      <h2>Keeping and deleting information</h2>
      <p>Your account and profile are kept while the account exists. You can edit or remove optional profile details, characters, and images at any time from your account pages. To delete your account entirely, contact the site administrator; deleting an account removes its profile, characters, and uploaded images. Published guides may remain on the site, credited to a former member, unless you ask for them to be removed as well. Server backups may keep copies for a limited time after deletion.</p>
    </section>
  </div>
</article>

<?php require_once __DIR__ . '/../includes/public_footer.php'; ?>
