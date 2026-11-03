# Cámaras de refrigeración e inventario

Sistema administrativo (Laravel 13, Livewire 4, Flux, MySQL 8) para controlar inventario **por caja individual** en cámaras de refrigeración de varias sucursales.

## Puesta en marcha

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate   # MySQL 8.0.16+ (CHECK, columnas generadas y triggers)
php artisan migrate:fresh --seed                   # crea un super administrador, un almacenista y datos de ejemplo
npm run build && php artisan serve
```

Pruebas: `php artisan test --compact` (la base de pruebas es `camara_refrigeracion_test`). La suite `tests/Concurrency` lanza procesos PHP reales en paralelo contra esa base. Análisis estático: `vendor/bin/phpstan analyse`.

## Reglas de negocio

- **Sucursal** = lugar; **cámara** = almacén con inventario propio. Una sucursal puede tener 0..N cámaras. Una salida a una sucursal sin cámara no crea inventario en destino.
- **La tabla `boxes` es la fuente de verdad.** No hay existencias agregadas que puedan contradecirla; los reportes se calculan sobre las cajas.
- **Código de barras**: `PRODUCTO-AAAAMMDD-000001`. El consecutivo es global, nunca se reinicia, tiene mínimo 6 dígitos y crece sin límite. Se reserva por bloques en una transacción corta (puede haber huecos, nunca duplicados). Los códigos manuales exigen procedencia y no pueden usar el formato reservado. El código de un producto no cambia una vez que tiene cajas.
- **Dinero y peso**: MXN con 2 decimales, kilogramos con 3, siempre como cadenas con `bcmath` (`App\Support\Decimal`), nunca `float`. El costo de una caja es inmutable; el costo restante de una caja parcial es proporcional a su peso.
- **Entradas** (individual y masiva usan la misma lógica): uno o varios grupos con peso y costo propios; cada caja recibe su código y un movimiento.
- **Salidas**: se retiran las cajas exactas seleccionadas. Se bloquean con `SELECT … FOR UPDATE` en orden de id, se revalidan al confirmar y, si una falla, se revierte todo. Una caja completa no puede estar en dos salidas vigentes (índice único sobre una columna generada).
- **Transferencias**: una salida con cámara destino crea una transferencia *en tránsito*. Al recibirla, las mismas cajas (mismo código, peso y costo) pasan a la cámara destino como `transfer_in`, no como compra. Faltantes y diferencias de peso crean incidencias; una transferencia en tránsito puede cancelarse y las cajas regresan al origen.
- **Retiro parcial**: la caja conserva su código y el peso restante; queda `agotada` solo al llegar a 0 kg. Solo se retira hacia una sucursal, nunca hacia otra cámara.
- **Correcciones**: nunca se borra ni se edita un movimiento confirmado. Se usan reversiones (super administrador, o cancelación de transferencia en tránsito) y ajustes (solo super administrador, con motivo y antes/después).
- **Trazabilidad**: `box_movements` y `audit_logs` son solo-inserción; los documentos confirmados solo permiten cambiar columnas de estado. Lo hacen cumplir **triggers de MySQL**, no solo la aplicación.

## Roles y permisos

- **Super administrador**: catálogos, usuarios, todas las cámaras, costos e importes, reversiones, ajustes, incidencias, auditoría y reportes.
- **Administrador** (solo consulta): panel, existencias, registros de entradas y salidas (sin crear ni revertir; esas rutas y botones no existen para este rol), productos, categorías, sucursales y cámaras. Ve todas las cámaras y los costos; no accede a transferencias, usuarios, incidencias, ajustes, auditoría ni reportes. Se crea desde Administración → Usuarios.
- **Almacenista**: una sucursal y las cámaras que se le asignen; registra entradas, salidas y recepciones y consulta solo las cámaras que se le asignen. Ve el precio por caja únicamente en la tabla de existencias; los importes de entradas, salidas y reportes son solo de los administradores.
- Autorización en servidor: `Gate::before` (inactivos sin acceso, super administradores con acceso total y administradores solo con consulta), Policies por modelo, middleware `super-admin`/`active`, gates `operate-inventory`/`view-catalogs`/`view-costs`, y cada servicio de `app/Services/Inventory` vuelve a comprobar el acceso a la cámara. El registro público está desactivado.

## Arquitectura

- `app/Services/Inventory`: única vía para escribir inventario (`RegisterEntry`, `RegisterExit`, `ReceiveTransfer`, `CancelTransfer`, `WithdrawPartial`, `ReverseExit`, `AdjustInventory`). Los componentes Livewire (`app/Livewire`) solo validan, autorizan y llaman a estos servicios.
- Todas las operaciones son transacciones atómicas con reintento ante deadlock; `client_token` evita duplicados por doble envío.
- Reportes de administrador en `app/Livewire/Reports` con exportación CSV (`App\Support\CsvExport`, con protección contra inyección de fórmulas).
