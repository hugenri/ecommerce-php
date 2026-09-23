<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Infrastructure\Paypal;

use App\Config\PaypalConfig;
use App\Modules\Checkout\Domain\PaymentCapture;
use App\Modules\Checkout\Domain\PaymentOrder;

/**
 * Cliente REST de PayPal construido sobre cURL.
 *
 * Única clase que conoce la API HTTP de PayPal (OAuth2 y Checkout Orders v2).
 * Es la capa de infraestructura: los Use Cases y el Controller nunca la
 * consumen directamente, siempre mediante PaymentGatewayInterface.
 */
class PaypalClient
{
    private const TIMEOUT_SECONDS = 30;

    private ?PaypalAccessToken $accessToken = null;

    public function __construct(private PaypalConfig $config) {}

    public function getAccessToken(): PaypalAccessToken
    {
        if ($this->accessToken !== null && !$this->accessToken->isExpired()) {
            return $this->accessToken;
        }

        $tokenResponse = $this->request(
            method: 'POST',
            path: '/v1/oauth2/token',
            body: ['grant_type' => 'client_credentials'],
            headers: [
                'Authorization' => 'Basic ' . base64_encode($this->config->clientId() . ':' . $this->config->clientSecret()),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            withAuthorization: false,
        );

        if (!isset($tokenResponse['access_token'], $tokenResponse['expires_in'])) {
            throw new PaypalException('El token de acceso de PayPal no fue válido.');
        }

        $this->accessToken = PaypalAccessToken::issue(
            (string) $tokenResponse['access_token'],
            (int) $tokenResponse['expires_in']
        );

        return $this->accessToken;
    }

    public function createOrder(PaymentOrder $order): string
    {
        $items = [];

        foreach ($order->getItems() as $item) {
            $items[] = [
                'name' => $item->getName(),
                'quantity' => (string) $item->getQuantity(),
                'unit_amount' => [
                    'currency_code' => $order->getCurrency(),
                    'value' => number_format($item->getUnitAmount(), 2, '.', ''),
                ],
            ];
        }

        $amount = [
            'currency_code' => $order->getCurrency(),
            'value' => number_format($order->getAmount(), 2, '.', ''),
        ];

        if ($items !== []) {
            $amount['breakdown'] = [
                'item_total' => [
                    'currency_code' => $order->getCurrency(),
                    'value' => number_format($order->getSubtotal(), 2, '.', ''),
                ],
                'tax_total' => [
                    'currency_code' => $order->getCurrency(),
                    'value' => number_format($order->getTax(), 2, '.', ''),
                ],
            ];
        }

        $response = $this->request(
            'POST',
            '/v2/checkout/orders',
            [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => $order->getReference(),
                        'description' => $order->getDescription(),
                        'amount' => $amount,
                        'items' => $items,
                    ],
                ],
            ]
        );

        if (!isset($response['id'])) {
            throw new PaypalException('La orden de PayPal no fue creada correctamente.');
        }

        return (string) $response['id'];
    }

    public function captureOrder(string $orderId): PaymentCapture
    {
        $response = $this->request(
            'POST',
            '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture'
        );

        return $this->buildPaymentCapture($response);
    }

    public function getOrder(string $orderId): PaymentCapture
    {
        $response = $this->request('GET', '/v2/checkout/orders/' . rawurlencode($orderId));

        return $this->buildPaymentCapture($response);
    }

    private function buildPaymentCapture(array $response): PaymentCapture
    {
        $currency = $this->config->currency();
        $amount = 0.0;
        $captureId = null;

        if (isset($response['purchase_units'][0]['payments']['captures'][0])) {
            $capture = $response['purchase_units'][0]['payments']['captures'][0];
            $amount = (float) ($capture['amount']['value'] ?? 0);
            $currency = (string) ($capture['amount']['currency_code'] ?? $currency);
            $captureId = isset($capture['id']) ? (string) $capture['id'] : null;
        } elseif (isset($response['purchase_units'][0]['amount'])) {
            $amountSetting = $response['purchase_units'][0]['amount'];
            $amount = (float) ($amountSetting['value'] ?? 0);
            $currency = (string) ($amountSetting['currency_code'] ?? $currency);
        }

        return new PaymentCapture(
            orderId: (string) ($response['id'] ?? ''),
            status: (string) ($response['status'] ?? ''),
            amount: $amount,
            currency: $currency,
            captureId: $captureId,
            paymentMethod: 'paypal',
        );
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $path,
        array $body = [],
        array $headers = [],
        bool $withAuthorization = true,
    ): array {
        $url = $this->config->apiBaseUrl() . $path;

        if ($withAuthorization) {
            $headers = array_merge([
                'Authorization' => 'Bearer ' . $this->getAccessToken()->getToken(),
                'Content-Type' => 'application/json',
            ], $headers);
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        if ($body !== []) {
            $isForm = $this->isFormContent($headers);
            curl_setopt(
                $ch,
                CURLOPT_POSTFIELDS,
                $isForm
                    ? http_build_query($body)
                    : json_encode($body, JSON_UNESCAPED_UNICODE)
            );
        }

        $raw = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            throw new PaypalException('Error de conexión con PayPal: ' . $curlError, $statusCode);
        }

        $decoded = json_decode((string) $raw, true);

        if (!is_array($decoded)) {
            throw new PaypalException('La respuesta de PayPal no fue válida.', $statusCode);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new PaypalException(
                (string) ($decoded['message'] ?? 'La solicitud a PayPal falló.'),
                $statusCode
            );
        }

        return $decoded;
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

    /**
     * @param array<string, string> $headers
     */
    private function isFormContent(array $headers): bool
    {
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'content-type' && str_contains($value, 'x-www-form-urlencoded')) {
                return true;
            }
        }

        return false;
    }
}