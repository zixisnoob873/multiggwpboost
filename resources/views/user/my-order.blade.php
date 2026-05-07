@extends('layouts.layout')

@section('title', 'GGWP Boost | Order Redirect')


@section('content')
<div class="ggwp-page-shell">
    <section class="card app-card">
        <div class="card-body">
            <span class="ggwp-page-eyebrow">Order workspace</span>
            <h1 class="h4 mb-2">Order page moved</h1>
            <p class="text-secondary mb-3">This legacy page no longer loads order data directly. Use the order workspace or your order list instead.</p>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-danger" href="{{ route('user-chats') }}">Open Order Workspace</a>
                <a class="btn btn-outline-light" href="{{ route('allorders') }}">View All Orders</a>
            </div>
        </div>
    </section>
</div>
@endsection
