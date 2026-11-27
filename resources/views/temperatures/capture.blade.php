<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => 'Registrar temperatura'])
    </head>
    <body class="min-h-screen bg-zinc-100 dark:bg-zinc-900">
        @include('partials.temperature-gate', ['standalone' => true])

        @fluxScripts
    </body>
</html>
