const MAX_NICKNAME_LENGTH = 25;

function sanitizeNickname(value) {
  return String(value || '')
    .replace(/[^A-Za-z0-9]+/g, '')
    .slice(0, MAX_NICKNAME_LENGTH);
}

function applyValidity(input) {
  const value = String(input.value || '');
  const isValid = value.length > 0 && /^[A-Za-z0-9]+$/.test(value) && value.length <= MAX_NICKNAME_LENGTH;

  input.setCustomValidity(isValid ? '' : 'Use only letters and numbers, with no spaces or symbols.');
}

export function initNicknameFields(root = document) {
  root.querySelectorAll('[data-nickname-input]').forEach((input) => {
    const sync = () => {
      const sanitized = sanitizeNickname(input.value);

      if (sanitized !== input.value) {
        input.value = sanitized;
      }

      applyValidity(input);
    };

    input.setAttribute('maxlength', String(MAX_NICKNAME_LENGTH));
    input.setAttribute('pattern', '[A-Za-z0-9]+');

    input.addEventListener('input', sync);
    input.addEventListener('blur', sync);
    sync();
  });
}
