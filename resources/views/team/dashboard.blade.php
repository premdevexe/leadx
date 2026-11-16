@extends('layouts.team')

@section('title', 'Dashboard')

@php
    $currencySymbol = \App\Models\Setting::currencySymbol();
@endphp

@section('content')
    <div class="dashboard-header">
        <h1 class="dashboard-header-title">Team Dashboard</h1>
        <p class="dashboard-header-desc">{{ $team->team_name }} ({{ $team->team_code }})</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <p class="stat-card-label">Total orders</p>
            <p class="stat-card-value tabular-nums">{{ $totalOrders }}</p>
        </div>
        <div class="stat-card">
            <p class="stat-card-label">Closing value</p>
            <p class="stat-card-value tabular-nums">{{ $currencySymbol }}{{ $closingValue }}</p>
        </div>
        <div class="stat-card stat-card-featured">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <p class="stat-card-label" style="color: rgba(255, 255, 255, 0.75);">Verified revenue</p>
                <span class="status-pip" style="background: var(--c-green-500);" title="Verified collections"></span>
            </div>
            <p class="stat-card-value tabular-nums" style="color: #ffffff;">{{ $currencySymbol }}{{ $verifiedRevenue }}</p>
        </div>
        <div class="stat-card">
            <p class="stat-card-label">Pending collections</p>
            <p class="stat-card-value tabular-nums">{{ $currencySymbol }}{{ $pendingAmount }}</p>
        </div>
        <div class="stat-card">
            <p class="stat-card-label">Active subscriptions</p>
            <p class="stat-card-value tabular-nums">{{ $activeSubscriptions }}</p>
        </div>
        <div class="stat-card">
            <p class="stat-card-label">Expired subscriptions</p>
            <p class="stat-card-value tabular-nums">{{ $expiredSubscriptions }}</p>
        </div>
        <div class="stat-card">
            <p class="stat-card-label">Expiring in 30 days</p>
            <p class="stat-card-value tabular-nums">{{ $expiringSubscriptions }}</p>
        </div>
        <div class="stat-card">
            <p class="stat-card-label">Team members</p>
            <p class="stat-card-value tabular-nums">{{ $memberCount }}</p>
        </div>
    </div>

    <div class="quick-actions">
        <a href="{{ route('team.orders.create') }}" class="btn btn-primary">
            Create Order
        </a>
        <a href="{{ route('team.orders') }}" class="btn btn-secondary">
            View Orders
        </a>
    </div>
@endsection
