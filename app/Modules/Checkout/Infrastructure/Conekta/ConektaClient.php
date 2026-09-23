<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Infrastructure\Conekta;

use App\Config\ConektaConfig;
use App\Modules\Checkout\Domain\PaymentCapture;
use App\Modules\Checkout\Domain\PaymentCheckout;
use App\Modules\Checkout\Domain\PaymentOrder;

/**
 * Cliente REST de Conekta construido sobre cURL.
 *
 * Única clase que conoce la API HTTP de Conekta (Orders v1 con checkout
 * hospedado). Es la capa de infraestructura: los Use Cases y el Controller
 * nunca la consumen directamente, siempre mediante PaymentGatewayInterface.
 */
class ConektaClient
{
    private const TIMEOUT_SECONDS = 30;

    private const API_VERSION = 'application/vnd.conekta-v2.3.0+json';

    public function __construct(private ConektaConfig $config) {}

    /**
     * Crea una orden con checkout embebido (Integration) y devuelve el
     * checkoutRequestId necesario para inicializar Conekta.js en el
     * navegador del cliente, dentro de nuestro propio dominio.
     */
    public function createOrder(PaymentOrder $order): PaymentCheckout
    {
        $response = $this->request('POST', '/orders', $this->buildOrderPayload($order));

        $orderId = (string) ($response['id'] ?? '');
        $checkoutRequestId = (string) ($response['checkout']['id'] ?? '');

        if ($orderId === '' || $checkoutRequestId === '') {
            throw new ConektaException('La orden de Conekta no fue creada correctamente.');
        }

        return new PaymentCheckout(
            orderId: $orderId,
            checkoutRequestId: $checkoutRequestId,
        );
    }

    public function getOrder(string $orderId): PaymentCapture
    {
        $response = $this->request('GET', '/orders/' . rawurlencode($orderId));

        $amountCents = (int) ($response['amount'] ?? 0);

        return new PaymentCapture(
            orderId: (string) ($response['id'] ?? $orderId),
            status: strtolower(trim((string) ($response['payment_status'] ?? $response['status'] ?? ''))),
            amount: $amountCents / ConektaConfig::UNIT_PRICE_MULTIPLIER,
            currency: $this->config->currency(),
            captureId: $this->extractCaptureId($response),
            reference: $this->extractPaymentReference($response),
            paymentMethod: $this->extractPaymentMethod($response),
        );
    }

    /**
     * Extrae el método de pago del primer cargo (charges[0].payment_method.type):
     * 'card', 'cash' (OXXO) o 'bank_transfer' (SPEI).
     *
     * @param array<string, mixed> $response
     */
    private function extractPaymentMethod(array $response): ?string
    {
        $charges = $response['charges']['data'] ?? [];

        if (!is_array($charges) || empty($charges)) {
            return null;
        }

        $first = $charges[0];

        if (!is_array($first)) {
            return null;
        }

        $type = $first['payment_method']['type'] ?? null;

        if (!is_string($type) || trim($type) === '') {
            return null;
        }

        return trim($type);
    }

    /**
     * Extrae el identificador del primer cargo (charge.id) de la orden,
     * que es el identificador de captura devuelto por Conekta cuando el pago
     * se confirma (tarjeta pagada o cargo creado).
     *
     * @param array<string, mixed> $response
     */
    private function extractCaptureId(array $response): ?string
    {
        $charges = $response['charges']['data'] ?? [];

        if (!is_array($charges) || empty($charges)) {
            return null;
        }

        $first = $charges[0];

        if (!is_array($first)) {
            return null;
        }

        $chargeId = $first['id'] ?? null;

        if (!is_string($chargeId) || trim($chargeId) === '') {
            return null;
        }

        return trim($chargeId);
    }

    /**
     * Extrae la referencia del cargo (OXXO o SPEI CLABE) del primer charge
     * de la orden, si existe.
     *
     * @param array<string, mixed> $response
     */
    private function extractPaymentReference(array $response): ?string
    {
        $charges = $response['charges']['data'] ?? [];

        if (!is_array($charges) || empty($charges)) {
            return null;
        }

        $first = $charges[0];

        if (!is_array($first)) {
            return null;
        }

        $reference = $first['payment_method']['reference'] ?? null;

        if (!is_string($reference) || trim($reference) === '') {
            return null;
        }

        return trim($reference);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOrderPayload(PaymentOrder $order): array
    {
        $lines = [];

        foreach ($order->getItems() as $item) {
            $lines[] = [
                'name' => $item->getName(),
                'unit_price' => (int) round($item->getUnitAmount() * ConektaConfig::UNIT_PRICE_MULTIPLIER),
                'quantity' => $item->getQuantity(),
                'type' => 'sku',
            ];
        }

        $customerInfo = [];

        if ($order->getBuyer() !== null) {
            $customerInfo = [
                'name' => $order->getBuyer()->name(),
                'email' => $order->getBuyer()->email(),
            ];

            if ($order->getBuyer()->phone() !== null) {
                $customerInfo['phone'] = $order->getBuyer()->phone();
            }
        }

        $metadata = array_merge(
            ['reference' => $order->getReference()],
            $order->getMetadata(),
        );

        $payload = [
            'line_items' => $lines,
            'currency' => $order->getCurrency(),
            'customer_info' => $customerInfo,
            'metadata' => $metadata,
            'checkout' => [
                'type' => 'Integration',
                'allowed_payment_methods' => ['card', 'cash', 'bank_transfer'],
            ],
        ];

        $taxAmount = (int) round($order->getTax() * ConektaConfig::UNIT_PRICE_MULTIPLIER);

        if ($taxAmount > 0) {
            $payload['tax_lines'] = [[
                'description' => 'IVA',
                'amount' => $taxAmount,
            ]];
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $body = []): array
    {
        $url = $this->config->apiBaseUrl() . $path;

        $headers = [
            'Authorization' => 'Bearer ' . $this->config->secretKey(),
            'Content-Type' => 'application/json',
            'Accept' => self::API_VERSION,
            'Accept-Language' => 'es',
        ];

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($method === 'POST') {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE);

            if ($payload === false) {
                throw new ConektaException('No se pudo codificar el payload JSON a enviar a Conekta.');
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        error_log(sprintf(
            'CONEKTA request: %s %s | headers: %s | body: %s',
            $method,
            $url,
            json_encode($this->redactHeaders($headers), JSON_UNESCAPED_UNICODE),
            json_encode($body, JSON_UNESCAPED_UNICODE)
        ));

        $raw = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        error_log(sprintf(
            'CONEKTA response: HTTP %d | curl_errno=%d | curl_error=%s | body: %s',
            $statusCode,
            $curlErrno,
            $curlError,
            (string) $raw
        ));

        curl_close($ch);

        if ($curlError !== '') {
            throw new ConektaException('Error de conexión con Conekta: ' . $curlError, $statusCode);
        }

        $decoded = json_decode((string) $raw, true);

        if (!is_array($decoded)) {
            throw new ConektaException('La respuesta de Conekta no fue válida.', $statusCode);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new ConektaException(
                sprintf('Conekta HTTP %d en %s: %s', $statusCode, $url, (string) $raw),
                $statusCode
            );
        }

        return $decoded;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function redactHeaders(array $headers): array
    {
        if (isset($headers['Authorization'])) {
            $headers['Authorization'] = 'Bearer <redacted>';
        }

        return $headers;
    }

    /**
     * @param array<string, string> $headers
     * @return array<int, string>
     */
    private function formatHeaders(array $headers): array
    {
        $formatted = [];

        foreach ($headers as $name => $value) {
            $formatted[] = $name . ': ' . $value;
        }

        return $formatted;
    }
}
