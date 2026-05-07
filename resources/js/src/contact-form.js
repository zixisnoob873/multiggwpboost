export function initContactForm() {
  document.querySelectorAll('[data-contact-form]').forEach((form) => {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    const messageInput = form.querySelector('[data-character-count-input]');
    const counter = form.querySelector('[data-character-count-output]');

    if (!(messageInput instanceof HTMLTextAreaElement) || !(counter instanceof HTMLElement)) {
      return;
    }

    const maxLength = Number(messageInput.getAttribute('maxlength') || 600);

    const renderCount = () => {
      if (Number.isFinite(maxLength) && maxLength > 0 && messageInput.value.length > maxLength) {
        messageInput.value = messageInput.value.slice(0, maxLength);
      }

      const currentLength = messageInput.value.length;

      counter.textContent = `${currentLength} / ${maxLength}`;
      counter.classList.toggle('is-near-limit', maxLength > 0 && currentLength >= Math.max(450, maxLength - 60));
    };

    messageInput.addEventListener('input', renderCount);
    renderCount();
  });
}
