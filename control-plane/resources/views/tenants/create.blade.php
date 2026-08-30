@extends('layouts.app')

@section('title', 'Add institution')

@section('content')
    <div class="page-head">
        <div>
            <h1>Add institution</h1>
            <p class="lede">
                Creates a database, runs migrations, seeds roles and templates,
                and starts a container. Takes about a minute.
            </p>
        </div>
    </div>

    <div class="card" style="max-width: 560px;">
        <form method="POST" action="{{ route('tenants.store') }}" class="form">
            @csrf

            <div class="form-row">
                <label for="name">Institution name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name') }}"
                    placeholder="Tribhuvan University"
                    required
                    autofocus
                >
                @error('name')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-row">
                <label for="slug">Identifier</label>
                <input
                    type="text"
                    id="slug"
                    name="slug"
                    value="{{ old('slug') }}"
                    placeholder="tu"
                >
                <p class="hint">
                    Becomes the subdomain, the database name and the container
                    name. Lowercase letters, digits and hyphens.
                    <strong>Cannot be changed later.</strong>
                    Leave blank to derive it from the name.
                </p>
                @error('slug')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-row">
                <label for="admin_email">First Coordinator's email</label>
                <input
                    type="email"
                    id="admin_email"
                    name="admin_email"
                    value="{{ old('admin_email') }}"
                    placeholder="coordinator@tu.edu.np"
                    required
                >
                <p class="hint">
                    Their password is generated during setup and printed once to
                    the <strong>queue worker's output</strong> &mdash; it is never
                    stored in plaintext and is not shown in this interface. Keep
                    that terminal where you can see it.
                </p>
                @error('admin_email')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Start provisioning</button>
                <a href="{{ route('tenants.index') }}" class="btn btn-quiet">Cancel</a>
            </div>
        </form>
    </div>
@endsection
