<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Control Plane') · SupervisEx</title>
    @isset($refresh)
        {{-- Provisioning runs on the queue; refresh while it is in flight. --}}
        <meta http-equiv="refresh" content="{{ $refresh }}">
    @endisset
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
    <style>{!! file_get_contents(resource_path('css/control-plane.css')) !!}</style>
</head>
<body>

@auth
    <header class="bar">
        <a class="brand" href="{{ route('tenants.index') }}">
            <span class="brand-mark">SupervisEx</span>
            <span class="brand-sub">Control Plane</span>
        </a>
        <div class="bar-right">
            <span class="who">{{ auth()->user()->email }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-quiet">Sign out</button>
            </form>
        </div>
    </header>
@endauth

<main class="wrap @yield('wrap-class')">
    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif

    @yield('content')
</main>

</body>
</html>
