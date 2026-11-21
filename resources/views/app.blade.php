<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="google" content="notranslate">
        <meta name="robots" content="noindex, nofollow">
        <meta name="theme-color" content="#082f49">
        <meta name="description" content="Application interne de gestion du personnel de la Gendarmerie Nationale.">

        <title inertia>{{ config('app.name', 'Gestion Militaire - Gendarmerie') }}</title>

        <!-- Favicon Gendarmerie -->
        <link rel="icon" type="image/png" href="{{ asset('images/logo-gendarmerie-officiel.png?v=2026') }}">
        <link rel="shortcut icon" type="image/png" href="{{ asset('images/logo-gendarmerie-officiel.png?v=2026') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/logo-gendarmerie-officiel.png?v=2026') }}">
        
        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>