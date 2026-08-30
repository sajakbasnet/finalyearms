@php
    use App\Enums\JobState;
    use App\Enums\TenantStatus;

    // Only poll while work is actually in flight — a settled tenant should not
    // reload itself forever.
    $inFlight = $tenant->status === TenantStatus::Provisioning;
@endphp

@extends('layouts.app', $inFlight ? ['refresh' => 5] : [])

@section('title', $tenant->name)

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $tenant->name }}</h1>
            <p class="lede">
                <span class="pill pill-{{ $tenant->status->value }}">{{ $tenant->status->label() }}</span>
                @if ($inFlight)
                    <span class="hint">Refreshing every 5 seconds.</span>
                @endif
            </p>
        </div>

        @if ($tenant->status === TenantStatus::Failed)
            <form method="POST" action="{{ route('tenants.retry', $tenant) }}">
                @csrf
                <button type="submit" class="btn">Retry provisioning</button>
            </form>
        @endif
    </div>

    <h2>Details</h2>
    <dl class="facts">
        <div class="fact">
            <dt>Identifier</dt>
            <dd class="mono">{{ $tenant->slug }}</dd>
        </div>
        <div class="fact">
            <dt>Portal</dt>
            <dd>
                @if ($tenant->primaryDomain)
                    <a href="https://{{ $tenant->primaryDomain->domain }}" rel="noreferrer">
                        {{ $tenant->primaryDomain->domain }}
                    </a>
                @else
                    —
                @endif
            </dd>
        </div>
        <div class="fact">
            <dt>First Coordinator</dt>
            <dd class="mono">{{ $tenant->admin_email }}</dd>
        </div>
        <div class="fact">
            <dt>Database</dt>
            <dd class="mono">{{ $tenant->database?->database_name ?? '—' }}</dd>
        </div>
        <div class="fact">
            <dt>Image</dt>
            <dd class="mono">{{ $tenant->deployment?->image_tag ?? '—' }}</dd>
        </div>
        <div class="fact">
            <dt>Schema version</dt>
            <dd class="mono">{{ $tenant->deployment?->schema_version ?? '—' }}</dd>
        </div>
        <div class="fact">
            <dt>Health</dt>
            <dd class="mono">{{ $tenant->deployment?->health ?? 'unknown' }}</dd>
        </div>
        <div class="fact">
            <dt>Registered</dt>
            <dd>{{ $tenant->created_at?->format('j M Y, H:i') }}</dd>
        </div>
    </dl>

    {{--
        Credentials are deliberately absent. They live encrypted in
        tenant_databases and are injected straight into the tenant's container;
        there is no reason to render them into a browser.
    --}}

    @if ($inFlight)
        <div class="flash">
            The Coordinator's password is printed once to the queue worker's
            output while <code>seed_bootstrap</code> runs. It is never stored in
            plaintext and cannot be recovered from here &mdash; if you miss it,
            use password reset on the tenant portal.
        </div>
    @endif

    <h2>Provisioning</h2>
    <ol class="steps">
        @foreach ($steps as $index => $step)
            @php
                $job = $jobs->get($step->value);
                $state = $job?->state;
            @endphp
            <li class="step {{ $state === null ? 'is-pending' : '' }}">
                <span class="n">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                <span class="label">
                    {{ $step->label() }}
                    <span class="sub">{{ $step->value }}</span>
                </span>
                <span>
                    @if ($state === JobState::Succeeded)
                        <span class="pill pill-active">Done</span>
                    @elseif ($state === JobState::Running)
                        <span class="pill pill-provisioning">Running</span>
                    @elseif ($state === JobState::Failed)
                        <span class="pill pill-failed">Failed</span>
                    @else
                        <span class="pill pill-suspended">Waiting</span>
                    @endif
                </span>

                @if ($job?->last_error)
                    <p class="step-error">{{ $job->last_error }}</p>
                @endif
            </li>
        @endforeach
    </ol>

    @if ($tenant->status === TenantStatus::Failed)
        <p class="hint" style="margin-top: 12px;">
            Every step is idempotent — retrying resumes from the first one that
            has not succeeded rather than starting over. Fix the cause shown
            above first.
        </p>
    @endif
@endsection
