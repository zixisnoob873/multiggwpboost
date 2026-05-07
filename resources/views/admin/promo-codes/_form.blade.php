@php
    $promoCode ??= null;
    $formAction = $promoCode
        ? route('admin-promo-codes.update', $promoCode)
        : route('admin-promo-codes.store');
    $submitLabel = $promoCode ? 'Save Promo Code' : 'Create Promo Code';
    $hasUnlimitedValidity = old('unlimited_validity', $promoCode ? ($promoCode->start_at === null && $promoCode->end_at === null) : true);
    $promoType = old('type', $promoCode?->type ?? \App\Models\PromoCode::TYPE_PERCENTAGE);
    $adminAddons = \App\Support\BoostingCatalog::addonSettingsForAdmin();
    $existingAddonRules = collect(old('addon_rules', $promoCode?->addonRules?->map(fn ($rule) => [
        'addon_slug' => $rule->addon_slug,
        'discount_type' => $rule->discount_type,
        'discount_value' => $rule->discount_value,
    ])->all() ?? []))->keyBy('addon_slug');
@endphp

<form method="POST" action="{{ $formAction }}" class="d-grid gap-3" data-loading-form data-validate-form data-dirty-form novalidate>
    @csrf
    @if($promoCode)
        @method('PATCH')
    @endif

    <div class="row g-2">
        <div class="col-md-4">
            <label class="form-label" for="code">Code</label>
            <input
                id="code"
                name="code"
                type="text"
                class="form-control @error('code') is-invalid @enderror"
                value="{{ old('code', $promoCode?->code) }}"
                placeholder="BOOST10"
                maxlength="64"
                required
            >
            @error('code')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label class="form-label" for="type">Type</label>
            <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                <option value="{{ \App\Models\PromoCode::TYPE_PERCENTAGE }}" @selected($promoType === \App\Models\PromoCode::TYPE_PERCENTAGE)>Percentage</option>
                <option value="{{ \App\Models\PromoCode::TYPE_FIXED }}" @selected($promoType === \App\Models\PromoCode::TYPE_FIXED)>Fixed Amount</option>
                <option value="{{ \App\Models\PromoCode::TYPE_ADDON_PROMOCODE }}" @selected($promoType === \App\Models\PromoCode::TYPE_ADDON_PROMOCODE)>Addon Promo</option>
            </select>
            @error('type')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4{{ $promoType === \App\Models\PromoCode::TYPE_ADDON_PROMOCODE ? ' d-none' : '' }}" id="promoValueWrap">
            <label class="form-label" for="value">Value</label>
            <input
                id="value"
                name="value"
                type="number"
                step="0.01"
                min="0"
                class="form-control @error('value') is-invalid @enderror"
                value="{{ old('value', $promoCode?->value) }}"
                placeholder="10"
                {{ $promoType === \App\Models\PromoCode::TYPE_ADDON_PROMOCODE ? 'disabled' : 'required' }}
            >
            @error('value')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label class="form-label" for="max_uses">Max Uses</label>
            <input
                id="max_uses"
                name="max_uses"
                type="number"
                min="1"
                class="form-control @error('max_uses') is-invalid @enderror"
                value="{{ old('max_uses', $promoCode?->max_uses) }}"
                placeholder="Unlimited"
            >
            @error('max_uses')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-12">
            <label class="form-check">
                <input
                    id="unlimited_validity"
                    class="form-check-input"
                    type="checkbox"
                    name="unlimited_validity"
                    value="1"
                    {{ $hasUnlimitedValidity ? 'checked' : '' }}
                >
                <span class="form-check-label">Unlimited validity</span>
            </label>
        </div>

        <div class="col-md-4">
            <label class="form-label" for="start_at">Start</label>
            <input
                id="start_at"
                name="start_at"
                type="datetime-local"
                class="form-control @error('start_at') is-invalid @enderror"
                value="{{ old('start_at', optional($promoCode?->start_at)->format('Y-m-d\\TH:i')) }}"
                {{ $hasUnlimitedValidity ? 'disabled' : '' }}
            >
            @error('start_at')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label class="form-label" for="end_at">End</label>
            <input
                id="end_at"
                name="end_at"
                type="datetime-local"
                class="form-control @error('end_at') is-invalid @enderror"
                value="{{ old('end_at', optional($promoCode?->end_at)->format('Y-m-d\\TH:i')) }}"
                {{ $hasUnlimitedValidity ? 'disabled' : '' }}
            >
            @error('end_at')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-12{{ $promoType === \App\Models\PromoCode::TYPE_ADDON_PROMOCODE ? '' : ' d-none' }}" id="addonPromoRulesPanel">
            <div class="card app-card border-0 bg-dark-subtle">
                <div class="card-body p-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h3 class="h6 mb-0">Addon Promo Rules</h3>
                        <span class="admin-chip">{{ count($adminAddons) }} addons</span>
                    </div>

                    @error('addon_rules')
                        <div class="alert alert-danger py-2 mb-3">{{ $message }}</div>
                    @enderror

                    <div class="d-grid gap-2">
                        @foreach($adminAddons as $index => $addon)
                            @php
                                $rule = $existingAddonRules->get($addon['slug'], []);
                                $isSelected = array_key_exists('addon_slug', $rule);
                                $discountType = $rule['discount_type'] ?? \App\Models\PromoCode::ADDON_DISCOUNT_TYPE_FREE;
                                $discountValue = $rule['discount_value'] ?? 0;
                            @endphp
                            <div class="border rounded-3 p-2" data-addon-rule-row>
                                <div class="row g-2 align-items-end">
                                    <div class="col-lg-5">
                                        <label class="form-check mb-0">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="addon_rules[{{ $index }}][selected]"
                                                value="1"
                                                data-addon-rule-toggle
                                                {{ $isSelected ? 'checked' : '' }}
                                            >
                                            <span class="form-check-label fw-semibold">{{ $addon['label'] }}</span>
                                        </label>
                                        <input
                                            type="hidden"
                                            name="addon_rules[{{ $index }}][addon_slug]"
                                            value="{{ $addon['slug'] }}"
                                            data-addon-rule-slug
                                        >
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <label class="form-label" for="addonRuleType{{ $index }}">Rule</label>
                                        <select
                                            id="addonRuleType{{ $index }}"
                                            class="form-select @error("addon_rules.{$index}.discount_type") is-invalid @enderror"
                                            name="addon_rules[{{ $index }}][discount_type]"
                                            data-addon-rule-type
                                        >
                                            <option value="{{ \App\Models\PromoCode::ADDON_DISCOUNT_TYPE_FREE }}" @selected($discountType === \App\Models\PromoCode::ADDON_DISCOUNT_TYPE_FREE)>Free</option>
                                            <option value="{{ \App\Models\PromoCode::ADDON_DISCOUNT_TYPE_PERCENTAGE }}" @selected($discountType === \App\Models\PromoCode::ADDON_DISCOUNT_TYPE_PERCENTAGE)>Percent Price</option>
                                            <option value="{{ \App\Models\PromoCode::ADDON_DISCOUNT_TYPE_FIXED }}" @selected($discountType === \App\Models\PromoCode::ADDON_DISCOUNT_TYPE_FIXED)>Fixed Price</option>
                                        </select>
                                        @error("addon_rules.{$index}.discount_type")
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <label class="form-label" for="addonRuleValue{{ $index }}">Value</label>
                                        <input
                                            id="addonRuleValue{{ $index }}"
                                            class="form-control @error("addon_rules.{$index}.discount_value") is-invalid @enderror"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            name="addon_rules[{{ $index }}][discount_value]"
                                            value="{{ $discountValue }}"
                                            placeholder="0.00"
                                            data-addon-rule-value
                                        >
                                        @error("addon_rules.{$index}.discount_value")
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <label class="form-check">
        <input
            class="form-check-input"
            type="checkbox"
            name="is_active"
            value="1"
            {{ old('is_active', $promoCode?->is_active ?? true) ? 'checked' : '' }}
        >
        <span class="form-check-label">Active</span>
    </label>

    @if($promoCode)
        <div class="d-flex flex-wrap gap-2 small text-secondary">
            <span>Used <strong class="text-light">{{ $promoCode->used_count }}</strong></span>
            <span>Limit <strong class="text-light">{{ $promoCode->max_uses ?? 'Unlimited' }}</strong></span>
            <span>Status <strong class="text-light">{{ $promoCode->is_active ? 'Active' : 'Inactive' }}</strong></span>
        </div>
    @endif

    <div class="d-flex justify-content-end">
        <button class="btn btn-danger" type="submit" data-busy-label="Saving...">{{ $submitLabel }}</button>
    </div>
</form>

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(() => {
  const toggle = document.getElementById('unlimited_validity');
  const dateInputs = [document.getElementById('start_at'), document.getElementById('end_at')].filter(Boolean);
  const typeInput = document.getElementById('type');
  const promoValueInput = document.getElementById('value');
  const promoValueWrap = document.getElementById('promoValueWrap');
  const addonPanel = document.getElementById('addonPromoRulesPanel');
  const addonRows = Array.from(document.querySelectorAll('[data-addon-rule-row]'));
  const addonPromoType = '{{ \App\Models\PromoCode::TYPE_ADDON_PROMOCODE }}';
  const freeType = '{{ \App\Models\PromoCode::ADDON_DISCOUNT_TYPE_FREE }}';
  const percentageType = '{{ \App\Models\PromoCode::ADDON_DISCOUNT_TYPE_PERCENTAGE }}';

  if (!toggle || !dateInputs.length) {
    return;
  }

  const syncValidityInputs = () => {
    dateInputs.forEach((input) => {
      input.disabled = toggle.checked;
    });
  };

  const syncAddonRow = (row, isAddonPromo) => {
    const checkbox = row.querySelector('[data-addon-rule-toggle]');
    const slugInput = row.querySelector('[data-addon-rule-slug]');
    const typeSelect = row.querySelector('[data-addon-rule-type]');
    const valueInput = row.querySelector('[data-addon-rule-value]');
    const isSelected = checkbox?.checked;
    const isFree = typeSelect?.value === freeType;
    const rowEnabled = isAddonPromo && isSelected;

    if (checkbox instanceof HTMLInputElement) {
      checkbox.disabled = !isAddonPromo;
    }

    if (slugInput instanceof HTMLInputElement) {
      slugInput.disabled = !isAddonPromo;
    }

    if (typeSelect instanceof HTMLSelectElement) {
      typeSelect.disabled = !rowEnabled;
    }

    if (valueInput instanceof HTMLInputElement) {
      valueInput.disabled = !rowEnabled || isFree;
      valueInput.placeholder = isFree ? '0.00' : (typeSelect?.value === percentageType ? '25' : '5.00');
    }

    row.classList.toggle('opacity-75', !rowEnabled);
  };

  const syncPromoType = () => {
    const isAddonPromo = typeInput?.value === addonPromoType;

    if (promoValueInput instanceof HTMLInputElement) {
      promoValueInput.disabled = isAddonPromo;
      promoValueInput.required = !isAddonPromo;
    }

    if (promoValueWrap instanceof HTMLElement) {
      promoValueWrap.classList.toggle('d-none', isAddonPromo);
    }

    if (addonPanel instanceof HTMLElement) {
      addonPanel.classList.toggle('d-none', !isAddonPromo);
    }

    addonRows.forEach((row) => syncAddonRow(row, isAddonPromo));
  };

  toggle.addEventListener('change', syncValidityInputs);
  typeInput?.addEventListener('change', syncPromoType);

  addonRows.forEach((row) => {
    row.querySelector('[data-addon-rule-toggle]')?.addEventListener('change', syncPromoType);
    row.querySelector('[data-addon-rule-type]')?.addEventListener('change', syncPromoType);
  });

  syncValidityInputs();
  syncPromoType();
})();
</script>
@endpush
