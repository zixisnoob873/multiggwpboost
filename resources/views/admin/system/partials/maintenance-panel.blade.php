@php
    $maintenanceCardTitle = $maintenanceCardTitle ?? 'Maintenance mode';
    $maintenanceCardDescription = $maintenanceCardDescription ?? 'Public web traffic is redirected to the maintenance page while admin, auth, blog, webhook, and payment flows remain available.';
    $runtimeSummary = is_array($runtimeSummary ?? null) ? $runtimeSummary : [];
@endphp

<section class="card app-card ggwp-panel-card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div class="min-w-0 flex-grow-1">
                <h2 class="h4 mb-1">{{ $maintenanceCardTitle }}</h2>
                <p class="text-secondary mb-0">{{ $maintenanceCardDescription }}</p>
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
                        <input
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                            aria-label="Toggle maintenance mode"
                            data-maintenance-mode-toggle
                            {{ $maintenanceModeEnabled ? 'checked' : '' }}
                        >
                    </label>
                </div>

                <div class="small {{ $maintenanceModeEnabled ? 'text-warning' : 'text-secondary' }}" data-maintenance-mode-copy>
                    {{ $maintenanceModeEnabled ? 'Maintenance mode is currently enabled.' : 'Maintenance mode is currently disabled.' }}
                </div>

                <div class="small d-none" data-maintenance-mode-feedback aria-live="polite"></div>
            </div>
        </div>

        @if(! empty($runtimeSummary) || filled($supportEmail ?? null) || filled($opsNotice ?? null) || isset($customerOrderEmailEnabled))
            <div class="admin-stat-grid mt-3">
                @if(filled($supportEmail ?? null))
                    <div class="admin-stat-box">
                        <span class="admin-stat-box__label">Support email</span>
                        <div class="admin-stat-box__value fs-6">{{ $supportEmail }}</div>
                    </div>
                @endif

                @if(isset($customerOrderEmailEnabled))
                    <div class="admin-stat-box">
                        <span class="admin-stat-box__label">Customer order email</span>
                        <div class="admin-stat-box__value fs-6">{{ $customerOrderEmailEnabled ? 'Enabled' : 'Disabled' }}</div>
                    </div>
                @endif

                @foreach($runtimeSummary as $label => $value)
                    <div class="admin-stat-box">
                        <span class="admin-stat-box__label">{{ str_replace('_', ' ', $label) }}</span>
                        <div class="admin-stat-box__value fs-6">{{ $value !== null && $value !== '' ? $value : '—' }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        @if(filled($opsNotice ?? null))
            <div class="alert alert-warning mt-3 mb-0" role="alert">
                <div class="fw-semibold mb-1">Operations notice</div>
                <div>{{ $opsNotice }}</div>
            </div>
        @endif
    </div>
</section>

@once
    <div class="modal fade" id="maintenanceModeConfirmModal" tabindex="-1" aria-labelledby="maintenanceModeConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-light border-secondary">
                <div class="modal-header border-secondary">
                    <div>
                        <h2 class="modal-title h5 mb-1" id="maintenanceModeConfirmModalLabel">Secure maintenance mode confirmation</h2>
                        <p class="small text-secondary mb-0" data-maintenance-modal-status>Starting confirmation flow...</p>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" data-maintenance-modal-dismiss></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-danger d-none" role="alert" data-maintenance-modal-error></div>

                    <section data-maintenance-step="1">
                        <label class="form-label fw-semibold" for="maintenanceConfirmText">Type CONFIRM to continue</label>
                        <input type="text" class="form-control" id="maintenanceConfirmText" autocomplete="off" spellcheck="false" data-maintenance-field="confirmation_text">
                        <div class="invalid-feedback" data-maintenance-field-error="confirmation_text"></div>
                        <p class="small text-secondary mb-0 mt-2">This prevents accidental toggles from the admin panel.</p>
                    </section>

                    <section class="d-none" data-maintenance-step="2">
                        <label class="form-label fw-semibold">Enter the 6-digit CAPTCHA</label>
                        <div class="rounded border border-secondary px-3 py-2 text-center fs-3 fw-semibold mb-3" data-maintenance-captcha-display>------</div>
                        <input type="text" class="form-control" id="maintenanceCaptcha" inputmode="numeric" maxlength="6" autocomplete="off" data-maintenance-field="captcha">
                        <div class="invalid-feedback" data-maintenance-field-error="captcha"></div>
                        <p class="small text-secondary mb-0 mt-2">If the code is wrong, a new CAPTCHA will be generated automatically.</p>
                    </section>

                    <section class="d-none" data-maintenance-step="3">
                        <label class="form-label fw-semibold" for="maintenancePassword">Enter your current password</label>
                        <input type="password" class="form-control" id="maintenancePassword" autocomplete="current-password" data-maintenance-field="current_password">
                        <div class="invalid-feedback" data-maintenance-field-error="current_password"></div>
                        <p class="small text-secondary mb-0 mt-2">Your password is verified securely against the stored account hash.</p>
                    </section>

                    <section class="d-none" data-maintenance-step="4">
                        <div class="rounded border border-secondary p-3">
                            <p class="mb-2 fw-semibold" data-maintenance-final-message>Are you sure you want to enable maintenance mode?</p>
                            <p class="small text-secondary mb-0">Click confirm to execute the change, or cancel to leave maintenance mode unchanged.</p>
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

    @push('scripts')
        <script nonce="{{ $cspNonce ?? '' }}">
        (() => {
          const panel = document.querySelector('[data-maintenance-mode-panel]');
          const modalElement = document.getElementById('maintenanceModeConfirmModal');

          if (!panel || !modalElement || !window.bootstrap?.Modal) {
            return;
          }

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
            challenge: panel.getAttribute('data-challenge-url'),
            confirm: panel.getAttribute('data-confirm-url'),
            captcha: panel.getAttribute('data-captcha-url'),
            password: panel.getAttribute('data-password-url'),
            update: panel.getAttribute('data-update-url'),
          };

          if (!toggle || !badge || !copy || !feedback || !modalStatus || !modalError || !captchaDisplay || !finalMessage || !nextButton || !confirmButton) {
            return;
          }

          const state = {
            currentEnabled: panel.getAttribute('data-enabled') === '1',
            pendingEnabled: null,
            flowToken: '',
            currentStep: 1,
            busy: false,
          };

          const actionVerb = (enabled) => enabled ? 'enable' : 'disable';

          const renderState = (enabled) => {
            state.currentEnabled = enabled;
            panel.setAttribute('data-enabled', enabled ? '1' : '0');
            toggle.checked = enabled;
            badge.textContent = enabled ? 'ON' : 'OFF';
            badge.className = `badge ${enabled ? 'text-bg-warning text-dark' : 'text-bg-success'}`;
            copy.textContent = enabled ? 'Maintenance mode is currently enabled.' : 'Maintenance mode is currently disabled.';
            copy.className = `small ${enabled ? 'text-warning' : 'text-secondary'}`;
          };

          const setFeedback = (message, isError = false) => {
            feedback.textContent = message;
            feedback.className = `small ${isError ? 'text-danger' : 'text-success'}`;
            feedback.classList.toggle('d-none', !message);
          };

          const extractMessage = (payload, fallback) => {
            if (typeof payload?.message === 'string' && payload.message.trim() !== '') {
              return payload.message;
            }

            if (payload?.errors && typeof payload.errors === 'object') {
              const firstField = Object.keys(payload.errors)[0];
              const firstMessage = Array.isArray(payload.errors[firstField]) ? payload.errors[firstField][0] : null;

              if (typeof firstMessage === 'string' && firstMessage.trim() !== '') {
                return firstMessage;
              }
            }

            return fallback;
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

          const clearFieldErrors = () => {
            Object.entries(fieldMap).forEach(([field, input]) => {
              if (input instanceof HTMLElement) {
                input.classList.remove('is-invalid');
              }

              const error = fieldErrorMap[field];

              if (error instanceof HTMLElement) {
                error.textContent = '';
              }
            });
          };

          const setModalError = (message = '', errors = {}) => {
            clearFieldErrors();

            if (message) {
              modalError.textContent = message;
              modalError.classList.remove('d-none');
            } else {
              modalError.textContent = '';
              modalError.classList.add('d-none');
            }

            Object.entries(errors).forEach(([field, messages]) => {
              const input = fieldMap[field];
              const error = fieldErrorMap[field];
              const firstMessage = Array.isArray(messages) ? messages[0] : '';

              if (input instanceof HTMLElement) {
                input.classList.add('is-invalid');
              }

              if (error instanceof HTMLElement) {
                error.textContent = typeof firstMessage === 'string' ? firstMessage : '';
              }
            });
          };

          const setModalBusy = (busy, nextLabel = '') => {
            state.busy = busy;
            toggle.disabled = busy;
            nextButton.disabled = busy;
            confirmButton.disabled = busy;
            dismissButtons.forEach((button) => {
              button.disabled = busy;
            });
            modalElement.querySelectorAll('input').forEach((input) => {
              input.disabled = busy;
            });

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
              if (input instanceof HTMLInputElement) {
                input.value = '';
              }
            });
          };

          const focusStepField = (step) => {
            const fieldByStep = {
              1: fieldMap.confirmation_text,
              2: fieldMap.captcha,
              3: fieldMap.current_password,
            };

            const target = fieldByStep[step];

            if (target instanceof HTMLElement) {
              window.setTimeout(() => target.focus(), 150);
            }
          };

          const setStep = (step) => {
            state.currentStep = step;

            stepSections.forEach((section) => {
              section.classList.toggle('d-none', Number(section.getAttribute('data-maintenance-step')) !== step);
            });

            nextButton.classList.toggle('d-none', step === 4);
            confirmButton.classList.toggle('d-none', step !== 4);

            if (step === 1) {
              modalStatus.textContent = `Step 1 of 4: type CONFIRM to ${actionVerb(state.pendingEnabled)} maintenance mode.`;
              nextButton.textContent = 'Verify CONFIRM';
            }

            if (step === 2) {
              modalStatus.textContent = 'Step 2 of 4: solve the 6-digit CAPTCHA.';
              nextButton.textContent = 'Verify CAPTCHA';
            }

            if (step === 3) {
              modalStatus.textContent = 'Step 3 of 4: verify your current password.';
              nextButton.textContent = 'Verify Password';
            }

            if (step === 4) {
              modalStatus.textContent = `Step 4 of 4: final confirmation to ${actionVerb(state.pendingEnabled)} maintenance mode.`;
              finalMessage.textContent = `Are you sure you want to ${actionVerb(state.pendingEnabled)} maintenance mode?`;
              confirmButton.textContent = `${state.pendingEnabled ? 'Enable' : 'Disable'} Maintenance Mode`;
            }

            focusStepField(step);
          };

          const restartFlowFromResponse = (payload, message) => {
            state.flowToken = typeof payload?.flow?.token === 'string' ? payload.flow.token : '';
            captchaDisplay.textContent = '------';
            resetModalInputs();
            setModalError(message || 'This confirmation session expired. Please start again.');
            setStep(1);
          };

          const handleRequestFailure = (payload, fallbackMessage) => {
            const message = extractMessage(payload, fallbackMessage);

            if (payload?.restart_required) {
              restartFlowFromResponse(payload, message);
              return;
            }

            if (state.currentStep === 2 && typeof payload?.challenge?.captcha === 'string') {
              captchaDisplay.textContent = payload.challenge.captcha;

              if (fieldMap.captcha instanceof HTMLInputElement) {
                fieldMap.captcha.value = '';
              }
            }

            if (state.currentStep === 3 && fieldMap.current_password instanceof HTMLInputElement) {
              fieldMap.current_password.value = '';
            }

            setModalError(message, payload?.errors || {});
          };

          const startFlow = async (nextState) => {
            state.pendingEnabled = nextState;
            state.flowToken = '';
            resetModalInputs();
            captchaDisplay.textContent = '------';
            setModalError('');
            setStep(1);
            modal.show();
            setModalBusy(true, 'Starting...');

            try {
              const { response, data } = await requestJson(routes.challenge, 'POST', { enabled: nextState });

              if (!response.ok) {
                throw new Error(extractMessage(data, 'Unable to start secure confirmation.'));
              }

              state.flowToken = typeof data?.flow?.token === 'string' ? data.flow.token : '';
              setModalError('');
              focusStepField(1);
            } catch (error) {
              modal.hide();
              setFeedback(error instanceof Error ? error.message : 'Unable to start secure confirmation.', true);
            } finally {
              setModalBusy(false);
              renderState(state.currentEnabled);
            }
          };

          const handleStepOne = async () => {
            setModalBusy(true, 'Verifying...');

            try {
              const { response, data } = await requestJson(routes.confirm, 'POST', {
                enabled: state.pendingEnabled,
                flow_token: state.flowToken,
                confirmation_text: fieldMap.confirmation_text?.value || '',
              });

              if (!response.ok) {
                handleRequestFailure(data, 'Unable to verify the confirmation phrase.');
                return;
              }

              captchaDisplay.textContent = typeof data?.challenge?.captcha === 'string' ? data.challenge.captcha : '------';

              if (fieldMap.confirmation_text instanceof HTMLInputElement) {
                fieldMap.confirmation_text.value = '';
              }

              setModalError('');
              setStep(2);
            } finally {
              setModalBusy(false);
            }
          };

          const handleStepTwo = async () => {
            setModalBusy(true, 'Checking...');

            try {
              const { response, data } = await requestJson(routes.captcha, 'POST', {
                enabled: state.pendingEnabled,
                flow_token: state.flowToken,
                captcha: fieldMap.captcha?.value || '',
              });

              if (!response.ok) {
                handleRequestFailure(data, 'Unable to verify the CAPTCHA.');
                return;
              }

              setModalError('');
              setStep(3);
            } finally {
              setModalBusy(false);
            }
          };

          const handleStepThree = async () => {
            setModalBusy(true, 'Verifying...');

            try {
              const { response, data } = await requestJson(routes.password, 'POST', {
                enabled: state.pendingEnabled,
                flow_token: state.flowToken,
                current_password: fieldMap.current_password?.value || '',
              });

              if (!response.ok) {
                handleRequestFailure(data, 'Unable to verify your password.');
                return;
              }

              setModalError('');
              setStep(4);
            } finally {
              setModalBusy(false);
            }
          };

          const handleFinalConfirmation = async () => {
            setModalBusy(true);
            setFeedback(state.pendingEnabled ? 'Turning maintenance mode ON...' : 'Turning maintenance mode OFF...');

            try {
              const { response, data } = await requestJson(routes.update, 'PATCH', {
                enabled: state.pendingEnabled,
                flow_token: state.flowToken,
                final_confirmation: true,
              });

              if (!response.ok) {
                handleRequestFailure(data, 'Unable to update maintenance mode.');
                setFeedback(extractMessage(data, 'Unable to update maintenance mode.'), true);
                return;
              }

              renderState(Boolean(data.enabled));
              setFeedback(data.message || 'Maintenance mode updated.');
              modal.hide();
            } catch (error) {
              setFeedback(error instanceof Error ? error.message : 'Unable to update maintenance mode.', true);
            } finally {
              setModalBusy(false);
            }
          };

          nextButton.addEventListener('click', () => {
            if (state.busy) {
              return;
            }

            if (state.currentStep === 1) {
              handleStepOne();
              return;
            }

            if (state.currentStep === 2) {
              handleStepTwo();
              return;
            }

            if (state.currentStep === 3) {
              handleStepThree();
            }
          });

          confirmButton.addEventListener('click', () => {
            if (state.busy || state.currentStep !== 4) {
              return;
            }

            handleFinalConfirmation();
          });

          modalElement.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' || state.busy) {
              return;
            }

            const target = event.target;

            if (!(target instanceof HTMLInputElement)) {
              return;
            }

            event.preventDefault();

            if (state.currentStep === 4) {
              handleFinalConfirmation();
              return;
            }

            nextButton.click();
          });

          toggle.addEventListener('click', (event) => {
            event.preventDefault();

            if (state.busy) {
              return;
            }

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
@endonce
