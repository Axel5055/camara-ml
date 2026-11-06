<?php

namespace App\Console\Commands;

use App\Services\QzSigner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('qz:generate-certificate {--force : Reemplaza el certificado actual}')]
#[Description('Crea el certificado con el que la app firma las peticiones a QZ Tray')]
class GenerateQzCertificate extends Command
{
    public function handle(QzSigner $signer): int
    {
        if ($signer->isConfigured() && ! $this->option('force')) {
            $this->components->warn('Ya existe un certificado. Usa --force para reemplazarlo (hay que volver a importarlo en cada equipo).');

            return self::FAILURE;
        }

        $files = $signer->generate('ML Grupo Comercial');

        @mkdir(QzSigner::directory(), 0700, true);
        file_put_contents(QzSigner::certificatePath(), $files['certificate']);
        file_put_contents(QzSigner::privateKeyPath(), $files['private_key']);

        $this->components->info('Certificado creado en '.QzSigner::directory());

        return self::SUCCESS;
    }
}
