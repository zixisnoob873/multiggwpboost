<section class="admin-empty-state">
    <div class="admin-empty-state__title">{{ $title }}</div>
    <p class="admin-empty-state__copy">{{ $copy }}</p>
    @if(!empty($action))
        <a class="{{ $action['class'] ?? 'btn btn-danger btn-sm' }}" href="{{ $action['href'] }}">{{ $action['label'] }}</a>
    @endif
</section>
