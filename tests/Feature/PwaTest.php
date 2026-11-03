<?php

use App\Http\Controllers\AppVersionController;
use App\Models\User;

it('ships a valid web app manifest with installable icons and its own identity', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)->toMatchArray(['display' => 'standalone', 'start_url' => '/', 'scope' => '/', 'id' => '/camaras-ml'])
        ->and($manifest['name'])->not->toBeEmpty()
        ->and($manifest['short_name'])->not->toBeEmpty();

    $icons = collect($manifest['icons'])->map(fn (array $icon): string => $icon['sizes'].':'.$icon['purpose'])->all();
    expect($icons)->toContain('192x192:any', '512x512:any', '512x512:maskable');

    foreach ($manifest['icons'] as $icon) {
        $path = public_path(ltrim($icon['src'], '/'));
        expect($path)->toBeFile();
        expect(getimagesize($path)[0])->toBe((int) explode('x', $icon['sizes'])[0]);
    }

    expect(public_path('icons/apple-touch-icon.png'))->toBeFile();
});

it('ships a service worker that never caches pages or data and an offline page', function () {
    $worker = file_get_contents(public_path('sw.js'));

    expect(public_path('offline.html'))->toBeFile()
        ->and($worker)->toContain("request.method !== 'GET'")->toContain('/offline.html')
        ->and($worker)->toContain('(build|images|icons)')
        ->and($worker)->not->toContain('/livewire');
});

it('links the manifest and registers the service worker on every page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('rel="manifest" href="/manifest.webmanifest"', false)
        ->assertSee('name="theme-color"', false)
        ->assertSee('beforeinstallprompt', false)
        ->assertSee("navigator.serviceWorker.register('/sw.js')", false);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertOk()
        ->assertSee('rel="manifest"', false);
});

it('offers the install button and the update link below the login button, in Spanish', function () {
    $html = $this->get(route('login'))->assertOk()->getContent();

    expect($html)->toContain('Instalar aplicación')->toContain('Actualizar aplicación')->toContain('Iniciar sesión')->toContain('data-insecure')
        ->and(strpos($html, 'data-test="login-button"'))->toBeLessThan(strpos($html, '<div data-install-block'))
        ->and(strpos($html, '<div data-install-block'))->toBeLessThan(strpos($html, 'data-test="update-app-link"'));
});

it('reports the app version without cache and matches the version of the page', function () {
    $response = $this->get(route('app-version'))->assertOk();
    $version = $response->json('version');

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($version)->toBe(AppVersionController::version())->not->toBeEmpty();

    $this->get(route('login'))->assertSee('<meta name="app-version" content="'.$version.'">', false);
});

it('opens the panel when a signed in user starts the app from its start URL', function () {
    $this->get('/')->assertOk()->assertSee('Iniciar sesión');

    $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('dashboard'));
});
