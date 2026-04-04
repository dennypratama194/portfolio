  <div id="project-modal" class="pmodal" aria-hidden="true" role="dialog" aria-modal="true" inert>
    <div class="pmodal-inner">
      <div class="pmodal-header">
        <img class="pmodal-logo" src="/assets/logo.png" alt="Denny Pratama"/>
        <button class="pmodal-close" id="pm-close" aria-label="Close">✕</button>
      </div>
      <div class="pmodal-body">

        <div class="pm-form" id="pm-form">
          <div class="pm-eyebrow">Let's work together</div>
          <h2 class="pm-title">Start a project.</h2>
          <div class="pm-fields">
            <div class="pm-field">
              <label class="pm-label" for="pm-name">Name</label>
              <input class="pm-input" type="text" id="pm-name" placeholder="Your name" autocomplete="name"/>
            </div>
            <div class="pm-field">
              <label class="pm-label" for="pm-email">Email</label>
              <input class="pm-input" type="email" id="pm-email" placeholder="hello@company.com" autocomplete="email"/>
            </div>
            <div class="pm-field">
              <label class="pm-label" for="pm-enquiry">Enquiry</label>
              <textarea class="pm-input pm-textarea" id="pm-enquiry" placeholder="Tell me about your project…"></textarea>
            </div>
          </div>
          <button class="pm-btn-send" id="pm-send">Send it →</button>
          <p class="pm-recaptcha-note">Protected by reCAPTCHA — <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Privacy</a> &amp; <a href="https://policies.google.com/terms" target="_blank" rel="noopener">Terms</a></p>
        </div>

        <div class="pm-success" id="pm-success">
          <div class="pm-success-check">✓</div>
          <h2 class="pm-title">Message sent.</h2>
          <p class="pm-success-sub">Thanks for your submission. I'll get back to you within 24 hours. Talk soon.</p>
          <button class="pm-success-back" id="pm-success-back">Back to portfolio →</button>
        </div>

      </div>
    </div>
  </div>
