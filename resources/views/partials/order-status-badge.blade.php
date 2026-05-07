@php
    $statusValue = $status ?? \App\Support\OrderStatus::PENDING;
@endphp

<span class="badge {{ \App\Support\OrderStatus::badgeClass($statusValue) }}">
    {{ \App\Support\OrderStatus::label($statusValue) }}
</span>
