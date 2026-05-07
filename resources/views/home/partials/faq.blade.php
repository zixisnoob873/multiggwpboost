<section id="home-faq" class="section-block ggwp-section-anchor ggwp-home-faq" aria-labelledby="homeFaqHeading">
  <div class="ggwp-home-section-header">
    <div>
      <span class="ggwp-home-section-kicker">Questions</span>
      <h2 id="homeFaqHeading" class="h1 mb-2">{{ $gameShortName ?? 'VALORANT' }} Boosting FAQ</h2>
      <p class="text-secondary mb-0">Quick answers for safety, pricing, delivery, and support before you order.</p>
    </div>
  </div>
  <div class="small text-secondary mb-3" data-faq-loading aria-live="polite">Loading {{ $gameShortName ?? 'VALORANT' }} boosting FAQs...</div>
  <div class="alert alert-warning d-none mb-3" data-faq-error role="alert">
    <div data-faq-error-message>Could not load {{ $gameShortName ?? 'VALORANT' }} boosting FAQs right now.</div>
    <button type="button" class="btn btn-outline-light btn-sm mt-2" data-faq-retry>Retry</button>
  </div>
  <div class="accordion ggwp-accordion d-none" id="faqAccordion"></div>
</section>
