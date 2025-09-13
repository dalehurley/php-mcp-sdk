<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>{{ $title ?? 'MCP Dashboard' }} - {{ config('app.name') }}</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    
    @stack('styles')
</head>
<body class="bg-gray-100 antialiased">
    <!-- Navigation -->
    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">
                        {{ $title ?? 'MCP Dashboard' }}
                    </h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="{{ route('mcp.dashboard') }}" class="text-gray-700 hover:text-gray-900">Dashboard</a>
                    <a href="{{ route('mcp.health') }}" class="text-gray-700 hover:text-gray-900">Health</a>
                    @if(config('app.debug'))
                        <a href="{{ route('mcp.inspect') }}" class="text-gray-700 hover:text-gray-900">Inspect</a>
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>