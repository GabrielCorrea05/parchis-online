<!DOCTYPE html>
<html lang="es" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @php
            $broadcastConfig = [
                'connection' => config('broadcasting.default'),
                'pusher' => [
                    'key' => config('broadcasting.connections.pusher.key'),
                    'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                    'host' => config('broadcasting.connections.pusher.options.host'),
                    'port' => config('broadcasting.connections.pusher.options.port'),
                    'scheme' => config('broadcasting.connections.pusher.options.scheme'),
                ],
                'reverb' => [
                    'key' => config('broadcasting.connections.reverb.key'),
                    'host' => config('broadcasting.connections.reverb.options.host'),
                    'port' => config('broadcasting.connections.reverb.options.port'),
                    'scheme' => config('broadcasting.connections.reverb.options.scheme'),
                ],
            ];
        @endphp
        <script>
            window.__PARCHIS_BROADCAST__ = @json($broadcastConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
