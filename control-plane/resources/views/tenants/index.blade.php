@extends('layouts.app')

@section('title', 'Institutions')

@section('content')
    <div class="page-head">
        <div>
            <h1>Institutions</h1>
            <p class="lede">Every tenant on the platform, and the state of its deployment.</p>
        </div>
        <a href="{{ route('tenants.create') }}" class="btn">Add institution</a>
    </div>

    <div class="counts">
        <div class="count">
            <span class="n">{{ $counts['total'] }}</span>
            <span class="l">Total</span>
        </div>
        <div class="count is-active">
            <span class="n">{{ $counts['active'] }}</span>
            <span class="l">Active</span>
        </div>
        <div class="count">
            <span class="n">{{ $counts['provisioning'] }}</span>
            <span class="l">Provisioning</span>
        </div>
        <div class="count is-failed">
            <span class="n">{{ $counts['failed'] }}</span>
            <span class="l">Failed</span>
        </div>
    </div>

    <div class="table-wrap">
        @if ($tenants->isEmpty())
            <p class="empty">
                No institutions yet.
                <a href="{{ route('tenants.create') }}">Add the first one</a>.
            </p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Institution</th>
                        <th>Status</th>
                        <th>Domain</th>
                        <th>Health</th>
                        <th>Deployed</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tenants as $tenant)
                        <tr>
                            <td>
                                <a href="{{ route('tenants.show', $tenant) }}">{{ $tenant->name }}</a>
                                <span class="slug">{{ $tenant->slug }}</span>
                            </td>
                            <td>
                                <span class="pill pill-{{ $tenant->status->value }}">
                                    {{ $tenant->status->label() }}
                                </span>
                            </td>
                            <td class="mono">{{ $tenant->primaryDomain?->domain ?? '—' }}</td>
                            <td class="mono">{{ $tenant->deployment?->health ?? 'unknown' }}</td>
                            <td class="mono">
                                {{ $tenant->deployment?->last_deployed_at?->diffForHumans() ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
