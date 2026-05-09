import './bootstrap';
import {
  applyResponsiveTableLabels,
  initAutoUploadForms,
  initConfirmableSubmissions,
  initCookieConsentEnhancements,
  initLiveChatTriggers,
  initMobileNav,
  initResponsiveCarouselLayout,
  initResponsiveTableRegions,
  initTabsA11y,
  initTooltips,
  initValidatedForms,
  onReady,
} from './src/common';
import { normalizeDivisionName, valorantDivisionIcons } from './src/rank-icons';
import { initAnalyticsTracking } from './src/analytics';

window.normalizeDivisionName = normalizeDivisionName;
window.valorantDivisionIcons = valorantDivisionIcons;
window.ggwpApiBase = () => '';
window.ggwpApplyResponsiveTableLabels = applyResponsiveTableLabels;

const loadFeature = async (selector, loader, initializer = 'default') => {
  if (!document.querySelector(selector)) {
    return;
  }

  try {
    const module = await loader();
    const init = initializer === 'default' ? module.default : module[initializer];

    if (typeof init === 'function') {
      await init();
    }
  } catch (error) {
    console.error(`[ggwp] Failed to initialize feature: ${selector}`, error);
  }
};

onReady(() => {
  initAnalyticsTracking();
  initTooltips();
  initMobileNav();
  initLiveChatTriggers();
  initConfirmableSubmissions();
  initValidatedForms();
  initAutoUploadForms();
  initResponsiveCarouselLayout();
  initResponsiveTableRegions();
  initTabsA11y();
  initCookieConsentEnhancements();

  loadFeature('[data-home-root], .home-page, #serviceTabs, [data-marketplace-home]', () => import('./src/home-ui'), 'initHomeUi');
  loadFeature('[data-rank-picker], [data-rank-picker-modal], #rankPickerModal', () => import('./src/home-rank-picker'), 'initHomeRankPicker');
  loadFeature('[data-addon-rules], [data-addon-card], .addon-card', () => import('./src/addon-rules'), 'initAddonRules');
  loadFeature('[data-agent-selector], [data-agent-selectors-root], .agent-selector', () => import('./src/agent-selectors'), 'initAgentSelectors');
  loadFeature('[data-estimator], #boostEstimator, [data-pricing-preview]', () => import('./src/estimator'), 'initEstimator');
  loadFeature('[data-service-calculator], .service-calculator', () => import('./src/service-calculator'), 'initServiceCalculators');
  loadFeature('[data-checkout-form], #checkoutForm', () => import('./src/checkout'), 'initCheckoutFlow');
  loadFeature('[data-order-chat], .chat-main-card, .ggwp-chat-shell', () => import('./src/order-chat'), 'initOrderChatPage');
  loadFeature('[data-admin-shell], .admin-shell', () => import('./src/admin-ui'), 'initAdminUi');
  loadFeature('[data-admin-manual-order], #manualOrderForm', () => import('./src/admin-manual-order'), 'initAdminManualOrderPricing');
  loadFeature('[data-contact-form], #contactForm', () => import('./src/contact-form'), 'initContactForm');
  loadFeature('[data-public-social-proof]', () => import('./src/public-social-proof'), 'initPublicSocialProof');
  loadFeature('[data-nickname-fields], #nickname', () => import('./src/nickname-fields'), 'initNicknameFields');
});
