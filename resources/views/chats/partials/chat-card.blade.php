@php
    $availableChannels = $availableChannels ?? [];
    $sendButtonLabel = $sendButtonLabel ?? 'Send';
    $sendButtonAriaLabel = $sendButtonAriaLabel ?? $sendButtonLabel;
    $sendButtonIcon = $sendButtonIcon ?? null;
    $cardEyebrow = $cardEyebrow ?? 'Order workspace';
    $chatCardClass = trim((string) ($chatCardClass ?? ''));
    $showContextStrip = $showContextStrip ?? true;
    $inlineCompose = $inlineCompose ?? false;
    $showComposeNotice = $showComposeNotice ?? true;
    $initialChannel = $availableChannels[0] ?? [
        'label' => 'Conversation',
        'hint' => '',
        'state' => 'Ready',
        'canSend' => false,
        'emptyTitle' => 'No Conversation yet.',
        'emptyCopy' => '',
    ];
    $chatCardClasses = trim('card app-card chat-main-card ' . $chatCardClass);
    $initialChannelLabel = $initialChannel['label'] ?? 'Conversation';
    $initialChannelTitleLabel = $initialChannel['titleLabel'] ?? $initialChannelLabel;
    $initialChannelHint = $initialChannel['hint'] ?? '';
    $initialChannelState = $initialChannel['state'] ?? 'Ready';
    $initialChannelCanSend = !empty($initialChannel['canSend']);
    $initialEmptyTitle = $initialChannel['emptyTitle'] ?? 'No Conversation yet.';
    $initialEmptyCopy = $initialChannel['emptyCopy'] ?? '';
    $emptyCopyClasses = blank($initialEmptyCopy) ? 'text-secondary mb-0 d-none' : 'text-secondary mb-0';
    $composePlaceholder = $initialChannelCanSend
        ? 'Type a message...'
        : 'This thread is read only for your role.';
    $composeDisabled = $initialChannelCanSend ? null : 'disabled';
    $composeAriaDisabled = $initialChannelCanSend ? 'false' : 'true';
    $composeNoticeText = $initialChannelCanSend
        ? ''
        : '';
@endphp

<section class="{{ $chatCardClasses }}">
    <div class="card-body p-4 d-flex flex-column gap-3 h-100">
        <div class="ggwp-chat-topbar d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="ggwp-chat-heading">
                <div class="small text-uppercase fw-semibold text-secondary">{{ $cardEyebrow }}</div>
                <h2 class="h5 mb-0" data-chat-channel-title>{{ $initialChannelTitleLabel }}</h2>
            </div>
            <div class="ggwp-chat-realtime d-flex align-items-center gap-2 small text-secondary" aria-live="polite">
                <span class="ggwp-live-dot"></span>
                <span data-chat-last-sync>Connecting</span>
            </div>
        </div>

        <div class="nav nav-tabs chat-channel-tabs" role="tablist" aria-label="Conversation switcher">
            @foreach($availableChannels as $index => $channel)
                @php
                    $channelIsActive = $index === 0;
                    $channelHint = $channel['hint'] ?? '';
                    $channelState = $channel['state'] ?? 'Ready';
                    $channelTitleLabel = $channel['titleLabel'] ?? $channel['label'];
                    $channelName = $channel['channelName'] ?? '';
                    $channelHistoryUrl = $channel['historyUrl'] ?? '';
                    $channelSendUrl = $channel['sendUrl'] ?? '';
                    $channelCanSend = !empty($channel['canSend']) ? '1' : '0';
                    $channelEmptyTitle = $channel['emptyTitle'] ?? 'No Conversation yet.';
                    $channelEmptyCopy = $channel['emptyCopy'] ?? '';
                @endphp
                <button
                    type="button"
                    class="nav-link{{ $channelIsActive ? ' active' : '' }}"
                    data-chat-channel="{{ $channel['key'] }}"
                    data-chat-channel-label="{{ $channel['label'] }}"
                    data-chat-channel-title-label="{{ $channelTitleLabel }}"
                    data-chat-channel-hint="{{ $channelHint }}"
                    data-chat-channel-state="{{ $channelState }}"
                    data-chat-channel-name="{{ $channelName }}"
                    data-chat-history-url="{{ $channelHistoryUrl }}"
                    data-chat-send-url="{{ $channelSendUrl }}"
                    data-chat-can-send="{{ $channelCanSend }}"
                    data-chat-empty-title="{{ $channelEmptyTitle }}"
                    data-chat-empty-copy="{{ $channelEmptyCopy }}"
                >
                    {{ $channel['label'] }}
                </button>
            @endforeach
        </div>

        @if($showContextStrip)
            <div class="chat-context-strip d-flex flex-wrap justify-content-between gap-2">
                <div class="text-secondary" data-chat-channel-hint>
                    {{ $initialChannelHint }}
                </div>
                <div class="small text-secondary" data-chat-channel-state>{{ $initialChannelState }}</div>
            </div>
        @endif

        <div class="ggwp-chat-stage">
            <div class="d-none" data-chat-load-older-wrap>
                <button type="button" class="btn btn-outline-light btn-sm w-100" data-chat-load-older>
                    Load earlier messages
                </button>
            </div>

            <div class="ggwp-chat-feed" data-chat-feed aria-live="polite"></div>
            <div class="ggwp-chat-empty" data-chat-empty>
                <div class="ggwp-chat-empty-icon" aria-hidden="true"></div>
                <h3 class="h6 mb-1" data-chat-empty-title>{{ $initialEmptyTitle }}</h3>
                <p class="{{ $emptyCopyClasses }}" data-chat-empty-copy>{{ $initialEmptyCopy }}</p>
            </div>
        </div>

        <form class="ggwp-chat-compose mt-auto" data-chat-compose-form>
            <div class="ggwp-chat-compose-inner">
                @if($inlineCompose)
                    @if($sendButtonIcon)
                        <div class="ggwp-chat-compose-field">
                            <textarea
                                class="form-control ggwp-chat-textarea ggwp-chat-textarea--icon"
                                name="body"
                                rows="2"
                                placeholder="{{ $composePlaceholder }}"
                                aria-label="Message"
                                maxlength="3000"
                                data-chat-autosize
                                {{ $composeDisabled }}
                                aria-disabled="{{ $composeAriaDisabled }}"
                            ></textarea>
                            <button
                                type="submit"
                                class="btn ggwp-chat-send-icon-btn ggwp-chat-send-icon-btn--embedded"
                                data-chat-send-button
                                aria-label="{{ $sendButtonAriaLabel }}"
                                title="{{ $sendButtonAriaLabel }}"
                                {{ $composeDisabled }}
                                aria-disabled="{{ $composeAriaDisabled }}"
                            >
                                <img src="{{ $sendButtonIcon }}" alt="" class="ggwp-chat-send-icon" aria-hidden="true">
                                <span class="visually-hidden">{{ $sendButtonLabel }}</span>
                            </button>
                        </div>
                    @else
                        <div class="ggwp-chat-compose-row">
                            <textarea
                                class="form-control ggwp-chat-textarea"
                                name="body"
                                rows="2"
                                placeholder="{{ $composePlaceholder }}"
                                aria-label="Message"
                                maxlength="3000"
                                data-chat-autosize
                                {{ $composeDisabled }}
                                aria-disabled="{{ $composeAriaDisabled }}"
                            ></textarea>
                            <button
                                type="submit"
                                class="btn btn-danger"
                                data-chat-send-button
                                aria-label="{{ $sendButtonAriaLabel }}"
                                title="{{ $sendButtonAriaLabel }}"
                                {{ $composeDisabled }}
                                aria-disabled="{{ $composeAriaDisabled }}"
                            >
                                {{ $sendButtonLabel }}
                            </button>
                        </div>
                    @endif
                    @if($showComposeNotice)
                        <div class="small text-secondary mt-2" data-compose-notice>
                            {{ $composeNoticeText }}
                        </div>
                    @endif
                @else
                    <div>
                        <textarea
                            class="form-control ggwp-chat-textarea"
                            name="body"
                            rows="2"
                            placeholder="{{ $composePlaceholder }}"
                            aria-label="Message"
                            maxlength="3000"
                            data-chat-autosize
                            {{ $composeDisabled }}
                            aria-disabled="{{ $composeAriaDisabled }}"
                        ></textarea>
                    </div>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                        @if($showComposeNotice)
                            <div class="small text-secondary" data-compose-notice>
                                {{ $composeNoticeText }}
                            </div>
                        @endif
                        <button
                            type="submit"
                            class="btn btn-danger"
                            data-chat-send-button
                            aria-label="{{ $sendButtonAriaLabel }}"
                            {{ $composeDisabled }}
                            aria-disabled="{{ $composeAriaDisabled }}"
                        >{{ $sendButtonLabel }}</button>
                    </div>
                @endif
            </div>
        </form>

        <div class="alert alert-info mb-0 d-none" data-chat-alert role="alert"></div>
    </div>
</section>
