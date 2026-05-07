@extends('layouts.layout')

@section('title', $title ?? 'GGWP Boost')
@section('body_theme', 'dark')

@section('content')
<div class="ggwp-page-shell ggwp-page-shell--wide ggwp-empty-workspace-shell">
  @if (! empty($links))
    <header class="ggwp-page-header">
      <div>
        <span class="ggwp-page-eyebrow">Workspace</span>
        <h1 class="h3 mb-1">{{ $heading ?? 'Page' }}</h1>
        <div class="text-secondary">{{ $description ?? 'This area has been removed.' }}</div>
      </div>
      <div class="ggwp-page-actions">
        @foreach ($links as $link)
          <a class="btn btn-outline-light{{ ! empty($link['active']) ? ' active' : '' }}" href="{{ route($link['route']) }}">{{ $link['label'] }}</a>
        @endforeach
      </div>
    </header>
  @endif

  <section class="app-card ggwp-empty-state-card">
    <div class="card-body p-5 text-center">
      <span class="ggwp-page-eyebrow">Workspace status</span>
      @if (empty($links))
        <h1 class="h4 mb-2">No Active Conversation Yet</h1>
      @else
        <h2 class="h4 mb-2">No Active Conversation Yet</h2>
      @endif
      <p class="text-secondary mb-0">This workspace is ready, but there is no eligible order conversation to show right now.</p>
    </div>
  </section>
</div>
@endsection
