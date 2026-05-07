@php
    $actions = $actions ?? [];
    $meta = $meta ?? [];
@endphp

<div class="admin-page-header">
    <div class="admin-page-header__copy">
        <h1 class="admin-page-title">{{ $title }}</h1>
        @if(!empty($subtitle))
            <p class="admin-page-subtitle">{{ $subtitle }}</p>
        @endif

        @if($meta !== [])
            <div class="admin-page-meta">
                @foreach($meta as $value)
                    <span class="admin-page-meta__item">{{ $value }}</span>
                @endforeach
            </div>
        @endif
    </div>

    @if($actions !== [])
        <div class="admin-page-actions">
            @foreach($actions as $action)
                @if(($action['type'] ?? 'link') === 'button')
                    <button
                        type="{{ $action['button_type'] ?? 'button' }}"
                        class="{{ $action['class'] ?? 'btn btn-outline-light btn-sm' }}"
                        @if(!empty($action['target'])) data-bs-target="{{ $action['target'] }}" @endif
                        @if(!empty($action['toggle'])) data-bs-toggle="{{ $action['toggle'] }}" @endif
                    >
                        {{ $action['label'] }}
                    </button>
                @else
                    <a
                        class="{{ $action['class'] ?? 'btn btn-outline-light btn-sm' }}"
                        href="{{ $action['href'] }}"
                        @if(!empty($action['target'])) target="{{ $action['target'] }}" @endif
                        @if(!empty($action['rel'])) rel="{{ $action['rel'] }}" @endif
                    >
                        {{ $action['label'] }}
                    </a>
                @endif
            @endforeach
        </div>
    @endif
</div>
