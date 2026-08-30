@extends('layouts.app')

@section('title', 'Sign in')
@section('wrap-class', 'narrow')

@section('content')
    <div class="login-head">
        <span class="brand-mark">SupervisEx</span>
        <span class="brand-sub">Control Plane</span>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('login') }}" class="form">
            @csrf

            <div class="form-row">
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                >
                @error('email')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-row">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                >
                @error('password')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Sign in</button>
                <span class="hint">Operators only.</span>
            </div>
        </form>
    </div>
@endsection
