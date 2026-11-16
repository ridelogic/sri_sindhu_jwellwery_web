<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Sign In · {{ config('site.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grain relative isolate min-h-screen bg-obsidian font-sans text-ivory antialiased">
    <main class="flex min-h-screen items-center justify-center px-5 py-12">
        <div class="w-full max-w-md">
            <div class="flex items-center justify-between">
                <x-logo variant="light" />
                <span class="border border-champagne/40 px-2.5 py-1 text-[0.6rem] font-semibold tracking-[0.3em] text-champagne uppercase">Admin</span>
            </div>

            <div class="mt-10 border border-ivory/10 bg-espresso/80 p-7 sm:p-10">
                <h1 class="font-serif text-4xl">Administration</h1>
                <p class="mt-2 text-sm text-stone">Sign in with your administrator account.</p>

                @if (session('status'))
                    <p class="mt-6 border-l-2 border-champagne bg-ivory/5 px-4 py-3 text-sm text-ivory/85" role="status">{{ session('status') }}</p>
                @endif

                @if ($errors->any())
                    <p class="mt-6 border-l-2 border-red-400 bg-red-500/10 px-4 py-3 text-sm text-red-200" role="alert">{{ $errors->first() }}</p>
                @endif

                <form method="POST" action="{{ route('admin.login.store') }}" class="mt-8 space-y-6" novalidate>
                    @csrf
                    <div>
                        <label for="email" class="block text-[0.66rem] font-semibold tracking-[0.24em] text-stone uppercase">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus
                               class="mt-3 block w-full border border-ivory/20 bg-transparent px-4 py-3.5 text-base text-ivory outline-none transition-colors focus:border-champagne">
                    </div>
                    <div>
                        <label for="password" class="block text-[0.66rem] font-semibold tracking-[0.24em] text-stone uppercase">Password</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                               class="mt-3 block w-full border border-ivory/20 bg-transparent px-4 py-3.5 text-base text-ivory outline-none transition-colors focus:border-champagne">
                    </div>
                    <button type="submit" class="btn btn-gold w-full">
                        Sign In
                        <x-icon name="arrow-right" class="size-4" />
                    </button>
                </form>
            </div>

            <p class="mt-6 text-center text-xs text-stone">
                Customers: please use the
                <a href="{{ route('login') }}" class="text-ivory underline decoration-ivory/30 underline-offset-4 hover:text-gold-bright">customer login</a>.
            </p>
        </div>
    </main>
</body>
</html>
