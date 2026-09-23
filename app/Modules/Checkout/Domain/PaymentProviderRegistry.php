<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Domain;

/**
 * Catálogo de métodos de pago disponibles.
 *
 * Fuente única de verdad para la vista y el backend. Agregar un nuevo
 * proveedor consiste en añadir su PaymentProvider aquí, sin tocar la vista,
 * las rutas ni el orquestador de JS.
 *
 * No lee base de datos ni variables de entorno: es un catálogo estático.
 */
final class PaymentProviderRegistry
{
    /**
     * @return array<int, PaymentProvider>
     */
    public function all(): array
    {
        return [
            $this->paypal(),
            $this->conekta(),
        ];
    }

    public function find(string $id): ?PaymentProvider
    {
        foreach ($this->all() as $provider) {
            if ($provider->id() === $id) {
                return $provider;
            }
        }

        return null;
    }

    public function supports(string $id): bool
    {
        return $this->find($id) !== null;
    }

    private function paypal(): PaymentProvider
    {
        return new PaymentProvider(
            id: 'paypal',
            name: 'PayPal',
            icon: 'bi-paypal',
            type: PaymentProvider::TYPE_GATEWAY,
            confirmationMode: PaymentProvider::CONFIRMATION_SYNC,
            requiresRedirect: false,
            createOrderEndpoint: '/pago/{sale_code}/create-order',
            captureEndpoint: '/pago/{sale_code}/confirm',
        );
    }

    private function conekta(): PaymentProvider
    {
        return new PaymentProvider(
            id: 'conekta',
            name: 'Conekta (Tarjeta / OXXO / SPEI)',
            icon: 'bi-credit-card',
            type: PaymentProvider::TYPE_GATEWAY,
            confirmationMode: PaymentProvider::CONFIRMATION_WEBHOOK,
            requiresRedirect: false,
            createOrderEndpoint: '/pago/{sale_code}/create-order',
            captureEndpoint: '/pago/{sale_code}/confirm',
        );
    }
}
