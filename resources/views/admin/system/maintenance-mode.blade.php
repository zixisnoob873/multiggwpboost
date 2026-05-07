@extends('layouts.admin')

@section('title', 'GGWP Boost | Maintenance Mode')

@php
    $maintenanceModeEnabled = (bool) ($maintenanceModeEnabled ?? false);
@endphp

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide">
    @include('admin.partials.page-header', [
        'title' => 'Maintenance Mode',
        'subtitle' => 'Protected maintenance control with the secure multi-step confirmation flow moved out of the dashboard.',
        'actions' => [
            ['label' => 'System Settings', 'href' => route('admin-system.settings')],
            ['label' => 'Audit Logs', 'href' => route('admin-system.audit-logs')],
        ],
    ])

    <section class="card app-card admin-section-card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h2 class="h5 mb-1">Current State</h2>
                    <p class="text-secondary mb-0">Public traffic is redirected to the maintenance page when this is enabled. Admin, auth, and critical internal routes stay operational.</p>
                </div>

                <div
                    class="d-flex flex-column align-items-start align-items-md-end gap-2"
                    data-maintenance-mode-panel
                    data-challenge-url="{{ route('admin-maintenance-mode.challenge') }}"
                    data-confirm-url="{{ route('admin-maintenance-mode.confirm') }}"
                    data-captcha-url="{{ route('admin-maintenance-mode.captcha') }}"
                    data-password-url="{{ route('admin-maintenance-mode.password') }}"
                    data-update-url="{{ route('admin-maintenance-mode.update') }}"
                    data-enabled="{{ $maintenanceModeEnabled ? '1' : '0' }}"
                >
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge {{ $maintenanceModeEnabled ? 'text-bg-warning text-dark' : 'text-bg-success' }}" data-maintenance-mode-badge>
                            {{ $maintenanceModeEnabled ? 'ON' : 'OFF' }}
                        </span>
                        <label class="form-check form-switch m-0 border-0 bg-transparent p-0">
                            <input class="form-check-input" type="checkbox" role="switch" data-maintenance-mode-toggle {{ $maintenanceModeEnabled ? 'checked' : '' }}>
                        </label>
                    </div>

                    <div class="small {{ $maintenanceModeEnabled ? 'text-warning' : 'text-secondary' }}" data-maintenance-mode-copy>
                        {{ $maintenanceModeEnabled ? 'Maintenance mode is enabled.' : 'Maintenance mode is disabled.' }}
                    </div>
                    <div class="small d-none" data-maintenance-mode-feedback aria-live="polite"></div>
                </div>
            </div>
        </div>
    </section>
</main>

<div class="modal fade" id="maintenanceModeConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <div>
                    <h2 class="modal-title h5 mb-1">Secure Confirmation</h2>
                    <p class="small text-secondary mb-0" data-maintenance-modal-status>Starting confirmation flow...</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" data-maintenance-modal-dismiss></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" data-maintenance-modal-error></div>

                <section data-maintenance-step="1">
                    <label class="form-label" for="maintenanceConfirmText">Type CONFIRM</label>
                    <input class="form-control" id="maintenanceConfirmText" autocomplete="off" spellcheck="false" data-maintenance-field="confirmation_text">
                    <div class="invalid-feedback" data-maintenance-field-error="confirmation_text"></div>
                </section>

                <section class="d-none" data-maintenance-step="2">
                    <label class="form-label">Enter the 6-digit CAPTCHA</label>
                    <div class="rounded border border-secondary px-3 py-2 text-center fs-3 fw-semibold mb-3" data-maintenance-captcha-display>------</div>
                    <input class="form-control" inputmode="numeric" maxlength="6" autocomplete="off" data-maintenance-field="captcha">
                    <div class="invalid-feedback" data-maintenance-field-error="captcha"></div>
                </section>

                <section class="d-none" data-maintenance-step="3">
                    <label class="form-label" for="maintenancePassword">Enter your current password</label>
                    <input type="password" class="form-control" id="maintenancePassword" autocomplete="current-password" data-maintenance-field="current_password">
                    <div class="invalid-feedback" data-maintenance-field-error="current_password"></div>
                </section>

                <section class="d-none" data-maintenance-step="4">
                    <div class="rounded border border-secondary p-3">
                        <p class="mb-2 fw-semibold" data-maintenance-final-message>Are you sure you want to enable maintenance mode?</p>
                        <p class="small text-secondary mb-0">Confirm to execute the change, or cancel to leave the current state untouched.</p>
                    </div>
                </section>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal" data-maintenance-modal-dismiss>Cancel</button>
                <button type="button" class="btn btn-danger" data-maintenance-modal-next>Continue</button>
                <button type="button" class="btn btn-danger d-none" data-maintenance-modal-confirm>Confirm</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(() => {
  const panel = document.querySelector('[data-maintenance-mode-panel]');
  const modalElement = document.getElementById('maintenanceModeConfirmModal');

  if (!panel || !modalElement || !window.bootstrap?.Modal) return;

  const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
  const toggle = panel.querySelector('[data-maintenance-mode-toggle]');
  const badge = panel.querySelector('[data-maintenance-mode-badge]');
  const copy = panel.querySelector('[data-maintenance-mode-copy]');
  const feedback = panel.querySelector('[data-maintenance-mode-feedback]');
  const modalStatus = modalElement.querySelector('[data-maintenance-modal-status]');
  const modalError = modalElement.querySelector('[data-maintenance-modal-error]');
  const captchaDisplay = modalElement.querySelector('[data-maintenance-captcha-display]');
  const finalMessage = modalElement.querySelector('[data-maintenance-final-message]');
  const nextButton = modalElement.querySelector('[data-maintenance-modal-next]');
  const confirmButton = modalElement.querySelector('[data-maintenance-modal-confirm]');
  const dismissButtons = Array.from(modalElement.querySelectorAll('[data-maintenance-modal-dismiss]'));
  const stepSections = Array.from(modalElement.querySelectorAll('[data-maintenance-step]'));
  const fieldMap = {
    confirmation_text: modalElement.querySelector('[data-maintenance-field="confirmation_text"]'),
    captcha: modalElement.querySelector('[data-maintenance-field="captcha"]'),
    current_password: modalElement.querySelector('[data-maintenance-field="current_password"]'),
  };
  const fieldErrorMap = {
    confirmation_text: modalElement.querySelector('[data-maintenance-field-error="confirmation_text"]'),
    captcha: modalElement.querySelector('[data-maintenance-field-error="captcha"]'),
    current_password: modalElement.querySelector('[data-maintenance-field-error="current_password"]'),
  };

  const routes = {
    challenge: panel.dataset.challengeUrl,
    confirm: panel.dataset.confirmUrl,
    captcha: panel.dataset.captchaUrl,
    password: panel.dataset.passwordUrl,
    update: panel.dataset.updateUrl,
  };

  const state = {
    currentEnabled: panel.dataset.enabled === '1',
    pendingEnabled: null,
    flowToken: '',
    currentStep: 1,
    busy: false,
  };

  const actionVerb = (enabled) => enabled ? 'enable' : 'disable';

  const renderState = (enabled) => {
    state.currentEnabled = enabled;
    panel.dataset.enabled = enabled ? '1' : '0';
    toggle.checked = enabled;
    badge.textContent = enabled ? 'ON' : 'OFF';
    badge.className = `badge ${enabled ? 'text-bg-warning text-dark' : 'text-bg-success'}`;
    copy.textContent = enabled ? 'Maintenance mode is enabled.' : 'Maintenance mode is disabled.';
    copy.className = `small ${enabled ? 'text-warning' : 'text-secondary'}`;
  };

  const setFeedback = (message, isError = false) => {
    feedback.textContent = message;
    feedback.className = `small ${isError ? 'text-danger' : 'text-success'}`;
    feedback.classList.toggle('d-none', !message);
  };

  const clearFieldErrors = () => {
    Object.entries(fieldMap).forEach(([field, input]) => {
      input?.classList?.remove('is-invalid');
      fieldErrorMap[field].textContent = '';
    });
  };

  const setModalError = (message = '', errors = {}) => {
    clearFieldErrors();
    modalError.textContent = message;
    modalError.classList.toggle('d-none', !message);
    Object.entries(errors).forEach(([field, messages]) => {
      fieldMap[field]?.classList?.add('is-invalid');
      fieldErrorMap[field].textContent = Array.isArray(messages) ? (messages[0] || '') : '';
    });
  };

  const extractMessage = (payload, fallback) => {
    if (typeof payload?.message === 'string' && payload.message.trim() !== '') return payload.message;
    const firstField = payload?.errors ? Object.keys(payload.errors)[0] : null;
    const firstMessage = firstField ? payload.errors[firstField]?.[0] : null;
    return typeof firstMessage === 'string' && firstMessage.trim() !== '' ? firstMessage : fallback;
  };

  const requestJson = async (url, method, payload) => {
    const response = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': window.appState?.csrfToken ?? '',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(payload),
    });

    const data = await response.json().catch(() => ({}));
    return { response, data };
  };

  const setModalBusy = (busy, nextLabel = '') => {
    state.busy = busy;
    toggle.disabled = busy;
    nextButton.disabled = busy;
    confirmButton.disabled = busy;
    dismissButtons.forEach((button) => button.disabled = busy);
    modalElement.querySelectorAll('input').forEach((input) => input.disabled = busy);

    if (busy && nextLabel) {
      nextButton.dataset.originalText = nextButton.textContent || 'Continue';
      nextButton.textContent = nextLabel;
    } else if (!busy && nextButton.dataset.originalText) {
      nextButton.textContent = nextButton.dataset.originalText;
      delete nextButton.dataset.originalText;
    }

    if (busy && state.currentStep === 4) {
      confirmButton.dataset.originalText = confirmButton.textContent || 'Confirm';
      confirmButton.textContent = 'Confirming...';
    } else if (!busy && confirmButton.dataset.originalText) {
      confirmButton.textContent = confirmButton.dataset.originalText;
      delete confirmButton.dataset.originalText;
    }
  };

  const resetModalInputs = () => {
    Object.values(fieldMap).forEach((input) => {
      if (input instanceof HTMLInputElement) input.value = '';
    });
  };

  const setStep = (step) => {
    state.currentStep = step;
    stepSections.forEach((section) => {
      section.classList.toggle('d-none', Number(section.dataset.maintenanceStep) !== step);
    });
    nextButton.classList.toggle('d-none', step === 4);
    confirmButton.classList.toggle('d-none', step !== 4);

    if (step === 1) {
      modalStatus.textContent = `Step 1 of 4: type CONFIRM to ${actionVerb(state.pendingEnabled)} maintenance mode.`;
      nextButton.textContent = 'Verify CONFIRM';
    } else if (step === 2) {
      modalStatus.textContent = 'Step 2 of 4: solve the 6-digit CAPTCHA.';
      nextButton.textContent = 'Verify CAPTCHA';
    } else if (step === 3) {
      modalStatus.textContent = 'Step 3 of 4: verify your current password.';
      nextButton.textContent = 'Verify Password';
    } else {
      modalStatus.textContent = `Step 4 of 4: final confirmation to ${actionVerb(state.pendingEnabled)} maintenance mode.`;
      finalMessage.textContent = `Are you sure you want to ${actionVerb(state.pendingEnabled)} maintenance mode?`;
      confirmButton.textContent = `${state.pendingEnabled ? 'Enable' : 'Disable'} Maintenance Mode`;
    }
  };

  const restartFlow = (payload, fallback) => {
    state.flowToken = typeof payload?.flow?.token === 'string' ? payload.flow.token : '';
    captchaDisplay.textContent = '------';
    resetModalInputs();
    setModalError(extractMessage(payload, fallback));
    setStep(1);
  };

  const handleFailure = (payload, fallback) => {
    if (payload?.restart_required) {
      restartFlow(payload, fallback);
      return;
    }

    if (state.currentStep === 2 && typeof payload?.challenge?.captcha === 'string') {
      captchaDisplay.textContent = payload.challenge.captcha;
      if (fieldMap.captcha instanceof HTMLInputElement) fieldMap.captcha.value = '';
    }

    if (state.currentStep === 3 && fieldMap.current_password instanceof HTMLInputElement) {
      fieldMap.current_password.value = '';
    }

    setModalError(extractMessage(payload, fallback), payload?.errors || {});
  };

  const startFlow = async (enabled) => {
    state.pendingEnabled = enabled;
    state.flowToken = '';
    captchaDisplay.textContent = '------';
    resetModalInputs();
    setModalError('');
    setStep(1);
    modal.show();
    setModalBusy(true, 'Starting...');

    try {
      const { response, data } = await requestJson(routes.challenge, 'POST', { enabled });
      if (!response.ok) throw new Error(extractMessage(data, 'Unable to start secure confirmation.'));
      state.flowToken = typeof data?.flow?.token === 'string' ? data.flow.token : '';
    } catch (error) {
      modal.hide();
      setFeedback(error instanceof Error ? error.message : 'Unable to start secure confirmation.', true);
    } finally {
      setModalBusy(false);
      renderState(state.currentEnabled);
    }
  };

  const submitStep = async (url, payload, loadingText, failureText, successStep) => {
    setModalBusy(true, loadingText);
    try {
      const { response, data } = await requestJson(url, 'POST', payload);
      if (!response.ok) {
        handleFailure(data, failureText);
        return null;
      }

      setModalError('');
      if (successStep) setStep(successStep);
      return data;
    } finally {
      setModalBusy(false);
    }
  };

  nextButton.addEventListener('click', async () => {
    if (state.busy) return;

    if (state.currentStep === 1) {
      const data = await submitStep(routes.confirm, {
        enabled: state.pendingEnabled,
        flow_token: state.flowToken,
        confirmation_text: fieldMap.confirmation_text?.value || '',
      }, 'Verifying...', 'Unable to verify the confirmation phrase.', 2);

      if (data?.challenge?.captcha) captchaDisplay.textContent = data.challenge.captcha;
      return;
    }

    if (state.currentStep === 2) {
      await submitStep(routes.captcha, {
        enabled: state.pendingEnabled,
        flow_token: state.flowToken,
        captcha: fieldMap.captcha?.value || '',
      }, 'Checking...', 'Unable to verify the CAPTCHA.', 3);
      return;
    }

    if (state.currentStep === 3) {
      await submitStep(routes.password, {
        enabled: state.pendingEnabled,
        flow_token: state.flowToken,
        current_password: fieldMap.current_password?.value || '',
      }, 'Verifying...', 'Unable to verify your password.', 4);
    }
  });

  confirmButton.addEventListener('click', async () => {
    if (state.busy || state.currentStep !== 4) return;

    setModalBusy(true);
    setFeedback(state.pendingEnabled ? 'Turning maintenance mode ON...' : 'Turning maintenance mode OFF...');

    try {
      const { response, data } = await requestJson(routes.update, 'PATCH', {
        enabled: state.pendingEnabled,
        flow_token: state.flowToken,
        final_confirmation: true,
      });

      if (!response.ok) {
        handleFailure(data, 'Unable to update maintenance mode.');
        setFeedback(extractMessage(data, 'Unable to update maintenance mode.'), true);
        return;
      }

      renderState(Boolean(data.enabled));
      setFeedback(data.message || 'Maintenance mode updated.');
      modal.hide();
    } finally {
      setModalBusy(false);
    }
  });

  toggle.addEventListener('click', (event) => {
    event.preventDefault();
    if (state.busy) return;
    setFeedback('');
    startFlow(!state.currentEnabled);
  });

  modalElement.addEventListener('hidden.bs.modal', () => {
    setModalBusy(false);
    setModalError('');
    resetModalInputs();
    captchaDisplay.textContent = '------';
    state.pendingEnabled = null;
    state.flowToken = '';
    setStep(1);
    renderState(state.currentEnabled);
  });

  renderState(state.currentEnabled);
})();
</script>
@endpush
