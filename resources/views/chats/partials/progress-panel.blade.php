@php
    use App\Support\BoostingCatalog;

    $overview = $overview ?? [];
    $progress = $progress ?? [];
    $footerMode = $footerMode ?? 'meta';
    $actionsDisabled = $actionsDisabled ?? false;
    $showFooterNote = $showFooterNote ?? true;
    $actionState = $actionState ?? [];
    $workspaceNotices = $workspaceNotices ?? [];
    $trackedCompletion = (int) ($trackedCompletion ?? ($progress['pct'] ?? 0));
    $completionProofTimestamp = $completionProofTimestamp ?? null;
    $canStartBoost = (bool) ($canStartBoost ?? false);
    $canCompleteOrder = (bool) ($canCompleteOrder ?? false);
    $showProgressControls = (bool) ($showProgressControls ?? false);
    $progressForm = $progressForm ?? [];
    $rankOptions = $rankOptions ?? [];
    $canExtend = ! $actionsDisabled && (bool) ($actionState['canExtend'] ?? ! $actionsDisabled);
    $canPauseToggle = ! $actionsDisabled && (bool) ($actionState['canPauseToggle'] ?? ! $actionsDisabled);
    $canTipBooster = ! $actionsDisabled && (bool) ($actionState['canTipBooster'] ?? ! $actionsDisabled);
    $canTipAdmin = ! $actionsDisabled && (bool) ($actionState['canTipAdmin'] ?? ! $actionsDisabled);
    $pauseLabel = (string) ($actionState['pauseLabel'] ?? 'Pause Boost');
    $viewerRole = $viewerRole ?? match ($footerMode) {
        'actions' => 'customer',
        'booster' => 'booster',
        default => 'super_admin',
    };
    $serviceKind = (string) ($overview['serviceKind'] ?? 'rank_boost');
    $usesRankJourney = in_array($serviceKind, ['rank_boost', 'ranked_wins', 'radiant_boost'], true);
    $showsTargetRank = in_array($serviceKind, ['rank_boost', 'radiant_boost'], true);
    $rankNodeCount = $showsTargetRank ? 3 : ($usesRankJourney ? 2 : 0);
    $showsWinsDone = $serviceKind === 'ranked_wins';
    $showsPlacementsPlayed = $serviceKind === 'placement_matches';
    $showsAdvancedRankMeta = in_array($viewerRole, ['super_admin', 'booster'], true)
        && in_array($serviceKind, ['rank_boost', 'radiant_boost'], true);

    $trackerDetails = [];

    if ($showsWinsDone) {
        $trackerDetails[] = [
            'label' => 'Wins Done',
            'value' => $progress['winsDone'] ?? $overview['winsDone'] ?? '0 / 0',
            'bind' => 'winsDone',
        ];
    }

    if ($showsPlacementsPlayed) {
        $trackerDetails[] = [
            'label' => 'Placements Played',
            'value' => $progress['placementsPlayed'] ?? $overview['placementsPlayed'] ?? '0 / 0',
            'bind' => 'placementsPlayed',
        ];
    }

    if ($showsAdvancedRankMeta) {
        $trackerDetails[] = [
            'label' => 'Average RR Gain',
            'value' => $overview['averageRR'] ?? 'Standard',
            'bind' => 'averageRR',
        ];
        $trackerDetails[] = [
            'label' => 'Update by',
            'value' => $progress['updatedBy'] ?? 'System',
            'bind' => 'progressUpdatedBy',
        ];
        $trackerDetails[] = [
            'label' => 'Update at',
            'value' => $progress['updatedAt'] ?? '-',
            'bind' => 'progressUpdatedAt',
        ];
    }
@endphp

<section class="card app-card chat-panel-card ggwp-progress-panel{{ $footerMode === 'actions' ? ' ggwp-progress-panel--actions' : '' }}{{ $footerMode === 'booster' ? ' ggwp-progress-panel--booster' : '' }}" data-rank-scope>
    <div class="card-body p-4">
        <div class="ggwp-progress-header d-flex justify-content-between align-items-center gap-2 mb-3">
            <div class="ggwp-progress-heading">
                <div class="small text-uppercase fw-semibold text-secondary ggwp-progress-eyebrow">Current progress</div>
                <h2 class="h5 mb-0 ggwp-progress-title">Rank tracker</h2>
            </div>
            <span class="badge text-bg-{{ $overview['statusTone'] ?? 'secondary' }} ggwp-progress-status" data-order-status-badge>
                <span data-order-bind="statusLabel">{{ $overview['status'] ?? 'Pending' }}</span>
            </span>
        </div>

        @if($rankNodeCount > 0)
            <div class="ggwp-rank-progress-grid ggwp-rank-progress-grid--{{ $rankNodeCount }} mb-3">
                <div class="ggwp-rank-node">
                    <span class="ggwp-rank-caption">From</span>
                    <img class="rank-logo ggwp-rank-node-icon" data-rank-bind="fromRank" src="{{ BoostingCatalog::rankIconUrl($overview['startRank'] ?? 'Unranked') }}" data-rank-fallback-src="{{ BoostingCatalog::rankIconUrl($overview['startRank'] ?? 'Unranked') }}" alt="From rank" width="54" height="54" loading="lazy" decoding="async">
                    <strong data-order-bind="fromRank">{{ $overview['startRank'] ?? 'Unranked' }}</strong>
                </div>
                <div class="ggwp-rank-node ggwp-rank-node-current">
                    <span class="ggwp-rank-caption">Current</span>
                    <img class="rank-logo ggwp-rank-node-icon" data-rank-bind="currentRank" src="{{ BoostingCatalog::rankIconUrl($overview['currentRank'] ?? 'Unranked') }}" data-rank-fallback-src="{{ BoostingCatalog::rankIconUrl($overview['currentRank'] ?? 'Unranked') }}" alt="Current rank" width="54" height="54" loading="lazy" decoding="async">
                    <strong data-order-bind="currentRank">{{ $overview['currentRank'] ?? 'Unranked' }}</strong>
                </div>
                @if($showsTargetRank)
                    <div class="ggwp-rank-node">
                        <span class="ggwp-rank-caption">Target</span>
                        <img class="rank-logo ggwp-rank-node-icon" data-rank-bind="desiredRank" src="{{ BoostingCatalog::rankIconUrl($overview['desiredRank'] ?? 'Unranked') }}" data-rank-fallback-src="{{ BoostingCatalog::rankIconUrl($overview['desiredRank'] ?? 'Unranked') }}" alt="Target rank" width="54" height="54" loading="lazy" decoding="async">
                        <strong data-order-bind="desiredRank">{{ $overview['desiredRank'] ?? 'Unranked' }}</strong>
                    </div>
                @endif
            </div>
        @endif

        <div class="ggwp-progress-summary d-flex justify-content-between align-items-center mb-2">
            <span class="small text-secondary">Progress completion</span>
            <strong data-order-bind="progressPct">{{ $progress['pct'] ?? 0 }}%</strong>
        </div>
        <div class="progress progress-dark ggwp-order-progress mb-3">
            <div
                class="progress-bar bg-danger"
                role="progressbar"
                data-progress-bar
                style="width: {{ $progress['pct'] ?? 0 }}%;"
                aria-valuenow="{{ $progress['pct'] ?? 0 }}"
                aria-valuemin="0"
                aria-valuemax="100"
            ></div>
        </div>

        @if(count($trackerDetails))
            <div class="ggwp-detail-list ggwp-detail-list-compact{{ $footerMode === 'booster' ? ' ggwp-progress-booster-meta' : '' }} mb-3">
                @foreach($trackerDetails as $detail)
                    <div class="ggwp-detail-item">
                        <span class="ggwp-detail-label">{{ $detail['label'] }}</span>
                        <span class="ggwp-detail-value" @if(!empty($detail['bind'])) data-order-bind="{{ $detail['bind'] }}" @endif>{{ $detail['value'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        @if($footerMode === 'actions')
            <div class="small text-uppercase fw-semibold text-secondary ggwp-progress-actions-label mb-2">Actions</div>
            <div class="ggwp-action-stack ggwp-progress-actions mb-3">
                <button
                    type="button"
                    class="btn btn-danger ggwp-progress-action-btn ggwp-progress-action-btn--primary"
                    @if($canExtend)
                        data-bs-toggle="modal"
                        data-bs-target="#extendBoostModal"
                    @endif
                    @disabled(! $canExtend)
                    aria-disabled="{{ $canExtend ? 'false' : 'true' }}"
                >
                    Extend Boost
                </button>
                <button
                    type="button"
                    class="btn btn-outline-light ggwp-progress-action-btn ggwp-progress-action-btn--secondary"
                    @if($canPauseToggle)
                        data-bs-toggle="modal"
                        data-bs-target="#pauseBoostModal"
                    @endif
                    @disabled(! $canPauseToggle)
                    aria-disabled="{{ $canPauseToggle ? 'false' : 'true' }}"
                >
                    {{ $pauseLabel }}
                </button>
                <button
                    type="button"
                    class="btn btn-outline-light ggwp-progress-action-btn ggwp-progress-action-btn--tertiary"
                    @if($canTipBooster)
                        data-bs-toggle="modal"
                        data-bs-target="#tipBoosterModal"
                    @endif
                    @disabled(! $canTipBooster)
                    aria-disabled="{{ $canTipBooster ? 'false' : 'true' }}"
                >
                    Tip Booster
                </button>
                <button
                    type="button"
                    class="btn btn-outline-light ggwp-progress-action-btn ggwp-progress-action-btn--tertiary"
                    @if($canTipAdmin)
                        data-bs-toggle="modal"
                        data-bs-target="#tipAdminModal"
                    @endif
                    @disabled(! $canTipAdmin)
                    aria-disabled="{{ $canTipAdmin ? 'false' : 'true' }}"
                >
                    Tip Admin
                </button>
            </div>
            @if($showFooterNote)
                <div class="ggwp-note-box">
                    @if($actionsDisabled)
                        Messaging and structured request actions are unavailable. The controls stay visible so the existing layout does not collapse.
                    @else
                        Extension, pause, and tip requests open as structured messages in your Customer &amp; Admin lane so the team can respond quickly.
                    @endif
                </div>
            @endif
        @elseif($footerMode === 'booster')
            @if(count($workspaceNotices))
                <div class="chat-workspace-notices ggwp-progress-booster-notices mb-3">
                    @foreach($workspaceNotices as $notice)
                        <div class="chat-workspace-notice{{ !empty($notice['pulse']) ? ' chat-workspace-notice--pulse' : '' }}">
                            <div class="chat-workspace-notice__message">{{ $notice['message'] ?? '' }}</div>
                            @if(!empty($notice['meta']))
                                <div class="chat-workspace-notice__meta">{{ $notice['meta'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="small text-uppercase fw-semibold text-secondary ggwp-progress-actions-label mb-2">Booster controls</div>

            @if($showProgressControls)
                <form method="POST" action="{{ route('orders.progress.update', ['order' => $order]) }}" class="ggwp-progress-booster-form mb-3">
                    @csrf
                    @method('PATCH')

                    <div class="ggwp-progress-booster-form-grid">
                        @if($progressForm['showCurrentRank'] ?? false)
                            <div class="ggwp-progress-booster-field">
                                <label class="form-label" for="boosterCurrentRank">Current Rank</label>
                                <select class="form-select @error('current_rank') is-invalid @enderror" id="boosterCurrentRank" name="current_rank">
                                    @foreach($rankOptions as $rankOption)
                                        <option value="{{ $rankOption }}" @selected(($progressForm['currentRank'] ?? '') === $rankOption)>{{ $rankOption }}</option>
                                    @endforeach
                                </select>
                                @error('current_rank')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif

                        @if($progressForm['showCurrentRr'] ?? false)
                            <div class="ggwp-progress-booster-field">
                                <label class="form-label" for="boosterCurrentRr">Current RR</label>
                                <input
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="form-control @error('current_rr') is-invalid @enderror"
                                    id="boosterCurrentRr"
                                    name="current_rr"
                                    value="{{ $progressForm['currentRR'] ?? 0 }}"
                                    placeholder="0-100"
                                >
                                @error('current_rr')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif

                        @if($progressForm['showCompletedWins'] ?? false)
                            <div class="ggwp-progress-booster-field">
                                <label class="form-label" for="boosterCompletedWins">Completed Wins</label>
                                <input
                                    type="number"
                                    min="0"
                                    max="{{ $progressForm['totalWins'] ?? 0 }}"
                                    class="form-control @error('completed_wins') is-invalid @enderror"
                                    id="boosterCompletedWins"
                                    name="completed_wins"
                                    value="{{ $progressForm['completedWins'] ?? 0 }}"
                                >
                                <div class="form-text">{{ $progressForm['totalWins'] ?? 0 }} wins purchased.</div>
                                @error('completed_wins')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif

                        @if($progressForm['showCompletedPlacements'] ?? false)
                            <div class="ggwp-progress-booster-field">
                                <label class="form-label" for="boosterCompletedPlacements">Completed Placement Matches</label>
                                <input
                                    type="number"
                                    min="0"
                                    max="{{ $progressForm['totalPlacements'] ?? 0 }}"
                                    class="form-control @error('completed_placements') is-invalid @enderror"
                                    id="boosterCompletedPlacements"
                                    name="completed_placements"
                                    value="{{ $progressForm['completedPlacements'] ?? 0 }}"
                                >
                                <div class="form-text">{{ $progressForm['totalPlacements'] ?? 0 }} placement matches purchased.</div>
                                @error('completed_placements')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif
                    </div>

                    <button type="submit" class="btn btn-danger w-100 ggwp-progress-action-btn ggwp-progress-booster-submit">Save Progress Update</button>
                </form>
            @else
                <div class="ggwp-note-box ggwp-progress-booster-note mb-3">
                    This order type syncs progress automatically and does not need a manual booster update right now.
                </div>
            @endif

            <div class="ggwp-action-stack ggwp-progress-actions ggwp-progress-actions--booster">
                @if($canStartBoost)
                    <form method="POST" action="{{ route('booster-orders.status', ['order' => $order]) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ \App\Support\OrderStatus::IN_PROGRESS }}">
                        <button type="submit" class="btn btn-danger ggwp-progress-action-btn ggwp-progress-action-btn--primary">Start Boost</button>
                    </form>
                @endif

                @if($canCompleteOrder)
                    <button
                        type="button"
                        class="btn btn-outline-light ggwp-progress-action-btn ggwp-progress-action-btn--secondary"
                        data-bs-toggle="modal"
                        data-bs-target="#boosterCompleteProofModal"
                    >
                        Mark as Completed
                    </button>
                @endif

                <button
                    type="button"
                    class="btn btn-outline-light ggwp-progress-action-btn ggwp-progress-action-btn--secondary"
                    data-bs-toggle="modal"
                    data-bs-target="#boosterDropConfirmModal"
                >
                    Drop Order
                </button>
            </div>

            @if($completionProofTimestamp)
                <div class="small text-secondary ggwp-progress-proof-meta mt-3">Latest proof uploaded {{ $completionProofTimestamp }}.</div>
            @endif
        @endif
    </div>
</section>
