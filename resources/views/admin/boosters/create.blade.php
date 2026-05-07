@extends('layouts.admin')

@section('title', 'GGWP Boost | Add Booster')



@section('admin_content')
<main class="ggwp-page-shell">
  @include('admin.partials.page-header', [
    'title' => 'Add Booster',
    'subtitle' => 'Create a booster account directly or convert an approved application into a live profile.',
    'actions' => [
      ['label' => 'Back To Boosters', 'href' => route('admin-boosters.index')],
      ['label' => 'Applications', 'href' => route('admin-booster-applications')],
    ],
  ])

  @if($sourceApplication)
    <div class="alert alert-info mb-3">
      Prefilling this booster from application by <strong>{{ $sourceApplication->name }}</strong> ({{ $sourceApplication->email }}).
    </div>
  @endif

  <section class="card app-card admin-section-card">
    <div class="card-body">
      <form action="{{ route('admin-boosters.store') }}" method="POST" class="row g-3" data-loading-form data-dirty-form>
        @csrf
        @if($sourceApplication)
          <input type="hidden" name="application_id" value="{{ $sourceApplication->id }}">
        @endif
        @include('admin.boosters.partials.form-fields', [
          'submitLabel' => 'Create Booster',
          'passwordLabel' => 'Password',
          'passwordRequired' => true,
          'passwordHelp' => null,
          'cancelUrl' => route('admin-boosters.index'),
          'sourceApplication' => $sourceApplication ?? null,
        ])
      </form>
    </div>
  </section>
</main>
@endsection
