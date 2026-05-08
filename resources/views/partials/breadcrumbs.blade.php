@php
    $items = collect($breadcrumbs ?? [])
        ->map(fn ($crumb) => [
            'name' => trim((string) data_get($crumb, 'name')),
            'url' => trim((string) data_get($crumb, 'url')),
        ])
        ->filter(fn (array $crumb) => $crumb['name'] !== '')
        ->values();
@endphp

@if($items->count() > 1)
    <nav class="ggwp-breadcrumbs" aria-label="Breadcrumb">
        <ol class="ggwp-breadcrumbs__list">
            @foreach($items as $crumb)
                <li class="ggwp-breadcrumbs__item">
                    @if(! $loop->last && $crumb['url'] !== '')
                        <a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a>
                    @else
                        <span aria-current="page">{{ $crumb['name'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
