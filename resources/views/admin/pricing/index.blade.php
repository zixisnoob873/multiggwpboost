@extends('layouts.admin')

@section('title', 'GGWP Boost | Valorant Pricing')

@php
    $formBasePrices = old('base_prices', $basePrices);
    $oldSpecialRows = old('special_rank_boost_rows');

    if ($oldSpecialRows === null) {
        $oldSpecialRows = old('special_rank_boost_steps');
    }

    $formSpecialRows = is_array($oldSpecialRows)
        ? collect($oldSpecialRows)
            ->map(function (mixed $row, string|int $key): array {
                if (is_array($row)) {
                    return [
                        'from' => $row['from'] ?? '',
                        'to' => $row['to'] ?? '',
                        'price' => $row['price'] ?? '',
                    ];
                }

                [$fromRank, $toRank] = array_pad(array_map('trim', explode('->', (string) $key, 2)), 2, '');

                return [
                    'from' => $fromRank,
                    'to' => $toRank,
                    'price' => $row,
                ];
            })
            ->values()
            ->all()
        : $specialRankBoostRows;
    $formRrRules = old('rr_rules', $rrRules);
    $formAddons = old('addons', $addons);
    $formModifiers = old('modifiers', $modifiers);
    $formLabels = old('labels', $labels);
    $specialRowCount = max(count($formSpecialRows), 1);
    $rankIndexes = array_flip($rankOrder);
    $specialGeneralErrors = $errors->get('special_rank_boost_steps');
@endphp

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense">
    @include('admin.partials.page-header', [
        'title' => 'Valorant Pricing',
        'subtitle' => 'Edit the active pricing config used by calculator previews, checkout recalculation, promo previews, and manual order pricing.',
        'meta' => [
            'Version '.($pricingSnapshot['version'] ?? 0),
            'Source '.($pricingSnapshot['source'] ?? 'config'),
            'Checksum '.substr((string) ($pricingSnapshot['checksum'] ?? ''), 0, 12),
        ],
        'actions' => [
            ['label' => 'Audit Logs', 'href' => route('admin-system.audit-logs'), 'class' => 'btn btn-outline-light btn-sm'],
            ['label' => 'Public Config', 'href' => route('pricing.config'), 'class' => 'btn btn-outline-light btn-sm', 'target' => '_blank', 'rel' => 'noopener'],
        ],
    ])

    <form
        method="POST"
        action="{{ route('admin-pricing.update') }}"
        class="card app-card admin-section-card admin-pricing-editor"
        data-pricing-editor-form
        data-loading-form
        data-dirty-form
        data-validate-form
        novalidate
    >
        @csrf
        @method('PUT')

        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">Active Price Rules</h2>
                    <p class="text-secondary mb-0">Canonical services, rank order, regions, platforms, boost modes, and addon names are locked to the default config.</p>
                </div>
                <button class="btn btn-danger" type="submit" data-busy-label="Saving...">Save Pricing</button>
            </div>

            <ul class="nav nav-tabs admin-pricing-tabs mb-3" id="adminPricingTabs" role="tablist">
                @foreach([
                    'base' => 'Base Prices',
                    'special' => 'Special Rank Steps',
                    'rr' => 'RR Rules',
                    'addons' => 'Addons',
                    'modifiers' => 'Modifiers',
                    'labels' => 'Labels',
                ] as $tabKey => $tabLabel)
                    <li class="nav-item" role="presentation">
                        <button
                            class="nav-link{{ $loop->first ? ' active' : '' }}"
                            id="pricing-{{ $tabKey }}-tab"
                            data-bs-toggle="tab"
                            data-bs-target="#pricing-{{ $tabKey }}"
                            type="button"
                            role="tab"
                            aria-controls="pricing-{{ $tabKey }}"
                            aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                        >{{ $tabLabel }}</button>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content">
                <section class="tab-pane fade show active" id="pricing-base" role="tabpanel" aria-labelledby="pricing-base-tab" tabindex="0">
                    @foreach($basePrices as $service => $rankPrices)
                        <div class="admin-pricing-panel">
                            <h3 class="h6">{{ $service }}</h3>
                            <div class="table-responsive">
                                <table class="table table-striped align-middle mb-0 ggwp-data-table ggwp-data-table--wide">
                                    <caption class="visually-hidden">{{ $service }} base prices by rank</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Rank</th>
                                            <th scope="col">Base price USD</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($rankPrices as $rank => $price)
                                            @php
                                                $value = $formBasePrices[$service][$rank] ?? $price;
                                            @endphp
                                            <tr>
                                                <th scope="row">{{ $rank }}</th>
                                                <td>
                                                    <input
                                                        class="form-control"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        name="base_prices[{{ $service }}][{{ $rank }}]"
                                                        value="{{ $value }}"
                                                        data-pricing-number
                                                        data-min="0"
                                                        data-original-value="{{ $price }}"
                                                        aria-label="{{ $service }} {{ $rank }} base price"
                                                        required
                                                    >
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </section>

                <section class="tab-pane fade" id="pricing-special" role="tabpanel" aria-labelledby="pricing-special-tab" tabindex="0">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <h3 class="h6 mb-1">Special Rank Boost Steps</h3>
                            <p class="text-secondary mb-0">Only consecutive rank transitions are valid.</p>
                        </div>
                        <button class="btn btn-outline-light btn-sm" type="button" data-pricing-add-special-step>Add Step</button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0 ggwp-data-table ggwp-data-table--wide">
                            <caption class="visually-hidden">Special rank boost step prices</caption>
                            <thead>
                                <tr>
                                    <th scope="col">From</th>
                                    <th scope="col">To</th>
                                    <th scope="col">Price USD</th>
                                    <th scope="col" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody data-pricing-special-steps>
                                @for($rowIndex = 0; $rowIndex < $specialRowCount; $rowIndex++)
                                    @php
                                        $row = $formSpecialRows[$rowIndex] ?? ['from' => '', 'to' => '', 'price' => ''];
                                        $fromError = "special_rank_boost_rows.{$rowIndex}.from";
                                        $toError = "special_rank_boost_rows.{$rowIndex}.to";
                                        $priceError = "special_rank_boost_rows.{$rowIndex}.price";
                                        $fromErrorId = "specialStep{$rowIndex}FromError";
                                        $toErrorId = "specialStep{$rowIndex}ToError";
                                        $priceErrorId = "specialStep{$rowIndex}PriceError";
                                    @endphp
                                    <tr data-pricing-special-step>
                                        <td>
                                            <select
                                                @class(['form-select', 'is-invalid' => $errors->has($fromError)])
                                                name="special_rank_boost_rows[{{ $rowIndex }}][from]"
                                                data-special-from
                                                aria-label="Special step from rank"
                                                @error($fromError) aria-invalid="true" aria-describedby="{{ $fromErrorId }}" @enderror
                                            >
                                                <option value="">Select rank</option>
                                                @foreach($rankOrder as $rank)
                                                    <option value="{{ $rank }}" data-rank-index="{{ $rankIndexes[$rank] }}" @selected(($row['from'] ?? '') === $rank)>{{ $rank }}</option>
                                                @endforeach
                                            </select>
                                            @error($fromError)
                                                <div id="{{ $fromErrorId }}" class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td>
                                            <select
                                                @class(['form-select', 'is-invalid' => $errors->has($toError)])
                                                name="special_rank_boost_rows[{{ $rowIndex }}][to]"
                                                data-special-to
                                                aria-label="Special step to rank"
                                                @error($toError) aria-invalid="true" aria-describedby="{{ $toErrorId }}" @enderror
                                            >
                                                <option value="">Select rank</option>
                                                @foreach($rankOrder as $rank)
                                                    <option value="{{ $rank }}" data-rank-index="{{ $rankIndexes[$rank] }}" @selected(($row['to'] ?? '') === $rank)>{{ $rank }}</option>
                                                @endforeach
                                            </select>
                                            @error($toError)
                                                <div id="{{ $toErrorId }}" class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td>
                                            <input
                                                @class(['form-control', 'is-invalid' => $errors->has($priceError)])
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                name="special_rank_boost_rows[{{ $rowIndex }}][price]"
                                                value="{{ $row['price'] ?? '' }}"
                                                data-pricing-number
                                                data-min="0"
                                                data-original-value="{{ $row['price'] ?? '' }}"
                                                aria-label="Special step price"
                                                @error($priceError) aria-invalid="true" aria-describedby="{{ $priceErrorId }}" @enderror
                                            >
                                            @error($priceError)
                                                <div id="{{ $priceErrorId }}" class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-outline-light btn-sm" type="button" data-pricing-remove-special-step>Remove</button>
                                        </td>
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                    <p
                        class="text-danger small mb-0 mt-2{{ $specialGeneralErrors === [] ? ' d-none' : '' }}"
                        data-pricing-special-error
                        aria-live="polite"
                    >{{ implode(' ', $specialGeneralErrors) }}</p>

                    <template data-pricing-special-row-template>
                        <tr data-pricing-special-step>
                            <td>
                                <select class="form-select" name="special_rank_boost_rows[__INDEX__][from]" data-special-from aria-label="Special step from rank">
                                    <option value="">Select rank</option>
                                    @foreach($rankOrder as $rank)
                                        <option value="{{ $rank }}" data-rank-index="{{ $rankIndexes[$rank] }}">{{ $rank }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="form-select" name="special_rank_boost_rows[__INDEX__][to]" data-special-to aria-label="Special step to rank">
                                    <option value="">Select rank</option>
                                    @foreach($rankOrder as $rank)
                                        <option value="{{ $rank }}" data-rank-index="{{ $rankIndexes[$rank] }}">{{ $rank }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input class="form-control" type="number" step="0.01" min="0" name="special_rank_boost_rows[__INDEX__][price]" data-pricing-number data-min="0" data-original-value="" aria-label="Special step price">
                            </td>
                            <td class="text-end">
                                <button class="btn btn-outline-light btn-sm" type="button" data-pricing-remove-special-step>Remove</button>
                            </td>
                        </tr>
                    </template>
                </section>

                <section class="tab-pane fade" id="pricing-rr" role="tabpanel" aria-labelledby="pricing-rr-tab" tabindex="0">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="pricingCurrentRrThreshold">Current RR discount threshold</label>
                            <input id="pricingCurrentRrThreshold" class="form-control" type="number" step="1" min="0" max="100" name="rr_rules[current_rr_discount_threshold]" value="{{ $formRrRules['current_rr_discount_threshold'] ?? 50 }}" data-pricing-number data-min="0" data-max="100" data-integer="1" data-original-value="{{ $rrRules['current_rr_discount_threshold'] ?? 50 }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="pricingFirstStepDiscount">First step discount multiplier</label>
                            <input id="pricingFirstStepDiscount" class="form-control" type="number" step="0.01" min="0" max="5" name="rr_rules[first_step_discount_multiplier]" value="{{ $formRrRules['first_step_discount_multiplier'] ?? 0.5 }}" data-pricing-number data-min="0" data-max="5" data-original-value="{{ $rrRules['first_step_discount_multiplier'] ?? 0.5 }}" required>
                        </div>
                        @foreach(($rrRules['avg_rr_modifiers'] ?? []) as $key => $value)
                            <div class="col-md-4">
                                <label class="form-label" for="pricingAvgRr{{ $key }}">Average RR {{ $key }} multiplier</label>
                                <input id="pricingAvgRr{{ $key }}" class="form-control" type="number" step="0.01" min="0" max="5" name="rr_rules[avg_rr_modifiers][{{ $key }}]" value="{{ $formRrRules['avg_rr_modifiers'][$key] ?? $value }}" data-pricing-number data-min="0" data-max="5" data-original-value="{{ $value }}" required>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="tab-pane fade" id="pricing-addons" role="tabpanel" aria-labelledby="pricing-addons-tab" tabindex="0">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0 ggwp-data-table ggwp-data-table--wide">
                            <caption class="visually-hidden">Addon pricing rules</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Addon</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($addons as $label => $rule)
                                    @php
                                        $type = $formAddons[$label]['type'] ?? ($rule['type'] ?? 'free');
                                        $value = $formAddons[$label]['value'] ?? ($rule['value'] ?? 0);
                                    @endphp
                                    <tr>
                                        <th scope="row">{{ $label }}</th>
                                        <td>
                                            <select class="form-select" name="addons[{{ $label }}][type]" data-addon-type aria-label="{{ $label }} addon pricing type" required>
                                                <option value="free" @selected($type === 'free')>Free</option>
                                                <option value="percent" @selected($type === 'percent')>Percent</option>
                                                <option value="bonus_win" @selected($type === 'bonus_win')>Bonus win</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input class="form-control" type="number" step="0.01" min="0" max="5" name="addons[{{ $label }}][value]" value="{{ $value }}" data-pricing-number data-min="0" data-max="5" data-original-value="{{ $rule['value'] ?? 0 }}" aria-label="{{ $label }} addon value">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="tab-pane fade" id="pricing-modifiers" role="tabpanel" aria-labelledby="pricing-modifiers-tab" tabindex="0">
                    <div class="row g-3">
                        @foreach($modifiers as $group => $items)
                            <div class="col-lg-4">
                                <h3 class="h6">{{ \Illuminate\Support\Str::headline($group) }}</h3>
                                <div class="vstack gap-2">
                                    @foreach($items as $key => $value)
                                        <label class="form-label mb-0" for="pricingModifier{{ $group }}{{ $loop->index }}">{{ $key }}</label>
                                        <input id="pricingModifier{{ $group }}{{ $loop->index }}" class="form-control" type="number" step="0.01" min="0" max="5" name="modifiers[{{ $group }}][{{ $key }}]" value="{{ $formModifiers[$group][$key] ?? $value }}" data-pricing-number data-min="0" data-max="5" data-original-value="{{ $value }}" required>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="tab-pane fade" id="pricing-labels" role="tabpanel" aria-labelledby="pricing-labels-tab" tabindex="0">
                    <div class="row g-3">
                        @foreach($labels as $group => $items)
                            <div class="col-lg-6">
                                <h3 class="h6">{{ \Illuminate\Support\Str::headline($group) }}</h3>
                                <div class="vstack gap-2">
                                    @foreach($items as $key => $value)
                                        <label class="form-label mb-0" for="pricingLabel{{ $group }}{{ $loop->index }}">{{ $key }}</label>
                                        <input id="pricingLabel{{ $group }}{{ $loop->index }}" class="form-control" type="text" maxlength="80" name="labels[{{ $group }}][{{ $key }}]" value="{{ $formLabels[$group][$key] ?? $value }}" required>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('admin-pricing.reset') }}" class="card app-card admin-section-card" data-loading-form data-confirm-submit="Reset Valorant pricing to config/pricing.php defaults?">
        @csrf
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
                <div>
                    <h2 class="h6 mb-1">Reset to Defaults</h2>
                    <p class="text-secondary mb-0">Type RESET PRICING to replace the active DB config with config/pricing.php.</p>
                </div>
                <div class="admin-pricing-reset">
                    <label class="form-label" for="pricingResetConfirmation">Confirmation</label>
                    <input id="pricingResetConfirmation" class="form-control" type="text" name="confirmation" autocomplete="off" placeholder="RESET PRICING" required>
                </div>
                <button class="btn btn-outline-light" type="submit" data-busy-label="Resetting...">Reset Pricing</button>
            </div>
        </div>
    </form>
</main>
@endsection
