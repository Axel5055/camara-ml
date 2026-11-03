<?php

namespace App\Enums;

enum UserRole: string
{
    /** Full control: catalogs, users, adjustments, reversals, audit, reports and every chamber. */
    case SuperAdmin = 'super_admin';

    /** Read-only management: panel, stock, entry and exit records, and the catalogs. */
    case Admin = 'admin';

    /** Works in the chambers assigned to them. */
    case Warehouse = 'warehouse';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super administrador',
            self::Admin => 'Administrador',
            self::Warehouse => 'Almacenista',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Acceso total al sistema.',
            self::Admin => 'Solo consulta: panel, existencias, registros de entradas y salidas, productos, categorías, sucursales y cámaras.',
            self::Warehouse => 'Registra entradas, salidas y recepciones en las cámaras que se le asignen.',
        };
    }
}
