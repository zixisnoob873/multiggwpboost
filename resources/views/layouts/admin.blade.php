@extends('layouts.layout')

@section('hide_site_nav', '1')
@section('hide_site_footer', '1')
@section('main_classes', 'site-main site-main--admin')

@php
    $adminNavigation = app(\App\Support\Admin\AdminNavigation::class)->items(auth()->user());
    $adminUser = auth()->user();
    $canViewPricing = \App\Support\AdminPermission::userCan($adminUser, 'system.pricing.view');
@endphp

@section('content')
<div class="admin-shell">
    <aside class="admin-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="adminSidebar" aria-labelledby="adminSidebarLabel">
        <div class="admin-sidebar__brand">
            <a href="{{ route('admin-dashboard') }}" class="admin-sidebar__logo">GGWP</a>
            <div class="admin-sidebar__meta">
                <div class="admin-sidebar__label" id="adminSidebarLabel">Admin Panel</div>
                <div class="admin-sidebar__role">{{ $adminUser?->adminRoleLabel() ?? 'Admin' }}</div>
            </div>
            <button type="button" class="btn-close btn-close-white admin-sidebar__close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Close admin navigation"></button>
        </div>

        <nav class="admin-sidebar__nav" aria-label="Admin navigation">
            @foreach($adminNavigation as $item)
                @if(($item['type'] ?? '') === 'link')
                    <a class="admin-nav-link{{ !empty($item['active']) ? ' is-active' : '' }}" href="{{ route($item['route']) }}">
                        <span>{{ $item['label'] }}</span>
                    </a>
                @else
                    <section class="admin-nav-group{{ !empty($item['active']) ? ' is-active' : '' }}">
                        <div class="admin-nav-group__header">
                            @if(!empty($item['route']))
                                <a class="admin-nav-group__title" href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
                            @else
                                <span class="admin-nav-group__title">{{ $item['label'] }}</span>
                            @endif
                        </div>
                        <div class="admin-nav-group__items">
                            @foreach($item['items'] as $child)
                                <a class="admin-nav-sublink{{ !empty($child['active']) ? ' is-active' : '' }}" href="{{ route($child['route']) }}">
                                    <span>{{ $child['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach
        </nav>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <div class="admin-topbar__main">
                <button class="btn btn-outline-light btn-sm admin-sidebar-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar">
                    Menu
                </button>
                <div>
                    <div class="admin-topbar__eyebrow">Operations-first admin</div>
                    <div class="admin-topbar__identity">
                        <span>{{ $adminUser?->fullIdentity('Admin') ?? 'Admin' }}</span>
                        <span class="admin-topbar__dot"></span>
                        <span>{{ $adminUser?->email }}</span>
                    </div>
                </div>
            </div>

            <div class="admin-topbar__actions">
                @if($canViewPricing)
                    <a class="btn btn-danger btn-sm" href="{{ route('admin-pricing.index') }}">Pricing</a>
                @endif
                <a class="btn btn-outline-light btn-sm" href="{{ route('home') }}" target="_blank" rel="noopener">Open Site</a>
                <button class="btn btn-danger btn-sm" type="submit" form="logoutForm">Logout</button>
            </div>
        </header>

        <div class="admin-content">
            @include('admin.partials.flash')
            @yield('admin_content')
        </div>
    </div>
</div>
@endsection
