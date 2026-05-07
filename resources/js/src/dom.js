export function byId(id) {
  return document.getElementById(id);
}

export function query(selector, scope = document) {
  return scope.querySelector(selector);
}

export function queryAll(selector, scope = document) {
  return Array.from(scope.querySelectorAll(selector));
}

export function on(element, eventName, handler, options) {
  if (!element) {
    return;
  }

  element.addEventListener(eventName, handler, options);
}

export function setText(target, value) {
  const element = typeof target === 'string' ? byId(target) : target;
  if (!element) {
    return;
  }

  element.textContent = value;
}

export function toggleClass(target, className, force) {
  const element = typeof target === 'string' ? byId(target) : target;
  if (!element) {
    return;
  }

  element.classList.toggle(className, force);
}

export function toggleRequired(target, required) {
  const element = typeof target === 'string' ? byId(target) : target;
  if (!element) {
    return;
  }

  if (required) {
    element.setAttribute('required', 'required');
    return;
  }

  element.removeAttribute('required');
}
