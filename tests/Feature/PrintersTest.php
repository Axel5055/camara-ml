<?php

use App\Models\User;
use App\Services\QzSigner;

it('shows the printers page to every signed-in role', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route('printers'))
        ->assertOk()
        ->assertSee('Impresoras')
        ->assertSee('data-test="printers-page"', false)
        ->assertSee(asset('js/ticket-printer.js'), false);
})->with(['superAdmin', 'admin', 'warehouse']);

it('requires signing in to open the printers page', function () {
    $this->get(route('printers'))->assertRedirect(route('login'));
});

it('falls back to the browser dialog while no printer is saved', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('printers'))
        ->assertSee('window.ticketPrinter?.get()', false)
        ->assertSee('printWithBrowser', false);
});

it('signs QZ Tray requests with a key that matches the published certificate', function () {
    $created = ! app(QzSigner::class)->isConfigured();

    if ($created) {
        $files = app(QzSigner::class)->generate('Pruebas');
        @mkdir(QzSigner::directory(), 0700, true);
        file_put_contents(QzSigner::certificatePath(), $files['certificate']);
        file_put_contents(QzSigner::privateKeyPath(), $files['private_key']);
    }

    try {
        $user = User::factory()->warehouse()->create();

        $certificate = $this->actingAs($user)->get(route('qz.certificate'))->assertOk()->getContent();
        $signature = $this->actingAs($user)->post(route('qz.sign'), ['request' => 'texto-a-firmar'])->assertOk()->getContent();

        expect(openssl_verify('texto-a-firmar', base64_decode($signature), $certificate, OPENSSL_ALGO_SHA512))->toBe(1);
    } finally {
        if ($created) {
            @unlink(QzSigner::certificatePath());
            @unlink(QzSigner::privateKeyPath());
        }
    }
});

it('does not sign for guests or invalid requests', function () {
    $this->post(route('qz.sign'), ['request' => 'x'])->assertRedirect(route('login'));
    $this->get(route('qz.certificate'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->warehouse()->create())
        ->post(route('qz.sign'), [])
        ->assertSessionHasErrors('request');
});

it('includes the print status dialog with the success and troubleshooting messages', function () {
    $this->actingAs(User::factory()->warehouse()->create())
        ->get(route('printers'))
        ->assertSee('data-test="print-status"', false)
        ->assertSee('Imprimiendo ticket', false)
        ->assertSee('Ticket impreso correctamente', false)
        ->assertSee('misma red', false)
        ->assertSee('QZ Tray', false);
});
