<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Modules\Checkout\Domain\Sale;

// Fase 4: la expiración de un pedido pendiente es una regla de presentación
// evaluada en lectura (sin columna nueva y sin NUNCA escribir 'cancelled').

$expiryHours = 48;
$aDayAgo = (new \DateTimeImmutable())->modify('-1 day');
$threeDaysAgo = (new \DateTimeImmutable())->modify('-3 days');

$recentPending = new Sale(1, 'OC-000001', 1, 1, 'conekta', 'pending', $aDayAgo, 10.0, 1.6, 11.6, 'pending');
echo "Pending reciente (< 48h) NO esta expirado: " . ($recentPending->isPaymentExpired($expiryHours) ? 'FAIL' : 'PASS') . "\n";

$oldPending = new Sale(2, 'OC-000002', 1, 1, 'conekta', 'pending', $threeDaysAgo, 10.0, 1.6, 11.6, 'pending');
echo "Pending con mas de 48h SI esta expirado: " . ($oldPending->isPaymentExpired($expiryHours) ? 'PASS' : 'FAIL') . "\n";

// Un pago ya realizado nunca es "expirado" aunque la venta sea antigua.
$oldPaid = new Sale(3, 'OC-000003', 1, 1, 'conekta', 'paid', $threeDaysAgo, 10.0, 1.6, 11.6, 'delivered');
echo "Pagado antiguo NO esta expirado (payment_status paid): " . ($oldPaid->isPaymentExpired($expiryHours) ? 'FAIL' : 'PASS') . "\n";

// Una venta cancelada no se trata como expirada.
$oldCancelled = new Sale(4, 'OC-000004', 1, 1, 'conekta', 'pending', $threeDaysAgo, 10.0, 1.6, 11.6, 'cancelled');
echo "Cancelado NO esta expirado (status cancelled): " . ($oldCancelled->isPaymentExpired($expiryHours) ? 'FAIL' : 'PASS') . "\n";