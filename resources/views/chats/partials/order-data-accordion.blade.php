@php
    $accordionId = $accordionId ?? 'orderDataAccordion';
    $detailSections = $detailSections ?? [];
    $rawDetailRows = $rawDetailRows ?? [];
    $rawMetadataRows = $rawMetadataRows ?? [];
@endphp

<div class="accordion ggwp-accordion ggwp-chat-accordion" id="{{ $accordionId }}">
    @foreach($detailSections as $sectionIndex => $section)
        @php $collapseId = $accordionId . '-section-' . $sectionIndex; @endphp
        <div class="accordion-item">
            <h2 class="accordion-header" id="{{ $collapseId }}-heading">
                <button class="accordion-button {{ $sectionIndex > 0 ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="{{ $sectionIndex === 0 ? 'true' : 'false' }}" aria-controls="{{ $collapseId }}">
                    {{ $section['title'] ?? 'Section' }}
                </button>
            </h2>
            <div id="{{ $collapseId }}" class="accordion-collapse collapse {{ $sectionIndex === 0 ? 'show' : '' }}" aria-labelledby="{{ $collapseId }}-heading" data-bs-parent="#{{ $accordionId }}">
                <div class="accordion-body">
                    <div class="ggwp-detail-list">
                        @foreach(($section['rows'] ?? []) as $row)
                            <div class="ggwp-detail-item">
                                <span class="ggwp-detail-label">{{ $row['label'] ?? 'Field' }}</span>
                                <span class="ggwp-detail-value">{{ $row['value'] ?? '-' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    @if(count($rawDetailRows))
        @php $collapseId = $accordionId . '-raw-details'; @endphp
        <div class="accordion-item">
            <h2 class="accordion-header" id="{{ $collapseId }}-heading">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
                    Raw stored order data
                </button>
            </h2>
            <div id="{{ $collapseId }}" class="accordion-collapse collapse" aria-labelledby="{{ $collapseId }}-heading" data-bs-parent="#{{ $accordionId }}">
                <div class="accordion-body ggwp-raw-scroll">
                    <div class="ggwp-detail-list ggwp-detail-list-compact">
                        @foreach($rawDetailRows as $row)
                            <div class="ggwp-detail-item">
                                <span class="ggwp-detail-label">{{ $row['label'] ?? $row['key'] ?? 'Field' }}</span>
                                <span class="ggwp-detail-value">{{ $row['value'] ?? '-' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if(count($rawMetadataRows))
        @php $collapseId = $accordionId . '-raw-metadata'; @endphp
        <div class="accordion-item">
            <h2 class="accordion-header" id="{{ $collapseId }}-heading">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
                    Raw metadata
                </button>
            </h2>
            <div id="{{ $collapseId }}" class="accordion-collapse collapse" aria-labelledby="{{ $collapseId }}-heading" data-bs-parent="#{{ $accordionId }}">
                <div class="accordion-body ggwp-raw-scroll">
                    <div class="ggwp-detail-list ggwp-detail-list-compact">
                        @foreach($rawMetadataRows as $row)
                            <div class="ggwp-detail-item">
                                <span class="ggwp-detail-label">{{ $row['label'] ?? $row['key'] ?? 'Field' }}</span>
                                <span class="ggwp-detail-value">{{ $row['value'] ?? '-' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
