<?php
/**
 * IMB Payment Gateway API client (PHP).
 *
 * Mirrors backend/imb_gateway.py: create-order, check-order-status, status
 * normalization, and webhook parsing. Uses WordPress HTTP API (wp_remote_post).
 *
 * @package WeeWoo_IMB_Pay
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class WW_IMB_Client
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_FAILED  = 'FAILED';

    private const SUCCESS_TOKENS = ['COMPLETED', 'SUCCESS', 'PAID'];
    private const FAILED_TOKENS  = ['FAILED', 'FAILURE', 'EXPIRED', 'CANCELLED', 'CANCELED', 'DECLINED'];

    private string $user_token;
    private string $api_base;

    public function __construct(string $user_token, string $api_base = 'https://api.imbpay.in')
    {
        $this->user_token = trim($user_token);
        $this->api_base   = rtrim(trim($api_base), '/');
    }

    /**
     * Create a collection order.
     *
     * @return array{ok:bool, message:string, result:array}
     */
    public function create_order(array $args): array
    {
        $body = [
            'customer_mobile' => $args['customer_mobile'] ?? '',
            'user_token'      => $this->user_token,
            'amount'          => $args['amount'] ?? '',
            'order_id'        => $args['order_id'] ?? '',
            'redirect_url'    => $args['redirect_url'] ?? $this->api_base,
            'remark1'         => $args['remark1'] ?? '',
            'remark2'         => $args['remark2'] ?? '',
        ];

        $resp = wp_remote_post($this->api_base . '/api/create-order', [
            'timeout' => 25,
            'body'    => $body, // multipart/form-urlencoded form fields
        ]);

        $data = $this->parse_response($resp);
        if ($data === null) {
            return ['ok' => false, 'message' => 'No/invalid response from IMB', 'result' => []];
        }

        $ok = $this->truthy($data['status'] ?? false);
        return [
            'ok'      => $ok,
            'message' => (string) ($data['message'] ?? ($ok ? 'OK' : 'IMB create-order failed')),
            'result'  => is_array($data['result'] ?? null) ? $data['result'] : [],
        ];
    }

    /**
     * Query the status of an order. Returns the decoded JSON body (or null).
     */
    public function check_order_status(string $order_id): ?array
    {
        $resp = wp_remote_post($this->api_base . '/api/check-order-status', [
            'timeout' => 25,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode([
                'user_token' => $this->user_token,
                'order_id'   => $order_id,
            ]),
        ]);

        return $this->parse_response($resp);
    }

    /**
     * Map IMB's various status fields to one internal state.
     */
    public function normalize_status(?array $payload): string
    {
        if (!is_array($payload)) {
            return self::STATUS_PENDING;
        }
        $candidates = [];
        if (isset($payload['status']) && is_string($payload['status'])) {
            $candidates[] = $payload['status'];
        }
        $result = $payload['result'] ?? null;
        if (is_array($result)) {
            foreach (['status', 'txnStatus'] as $k) {
                if (isset($result[$k]) && is_string($result[$k])) {
                    $candidates[] = $result[$k];
                }
            }
        }
        $upper = array_map(static fn($c) => strtoupper(trim((string) $c)), $candidates);
        if (array_intersect($upper, self::SUCCESS_TOKENS)) {
            return self::STATUS_SUCCESS;
        }
        if (array_intersect($upper, self::FAILED_TOKENS)) {
            return self::STATUS_FAILED;
        }
        return self::STATUS_PENDING;
    }

    /**
     * Normalize an IMB webhook body. Handles result-as-JSON-string (form posts).
     *
     * @return array{order_id:string, paid:bool, amount:?float, utr:string, result:array}
     */
    public function parse_webhook(array $payload): array
    {
        $result = $payload['result'] ?? null;
        if (is_string($result)) {
            $decoded = json_decode($result, true);
            $result = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($result)) {
            $result = [];
        }

        $order_id = (string) (
            $payload['order_id']
            ?? $payload['orderId']
            ?? $result['orderId']
            ?? $result['order_id']
            ?? ''
        );

        $paid = strtoupper(trim((string) ($payload['status'] ?? ''))) === 'SUCCESS'
            && strtoupper(trim((string) ($result['txnStatus'] ?? ''))) === 'COMPLETED';

        $amount = null;
        if (isset($result['amount']) && is_numeric($result['amount'])) {
            $amount = (float) $result['amount'];
        }

        return [
            'order_id' => $order_id,
            'paid'     => $paid,
            'amount'   => $amount,
            'utr'      => (string) ($result['utr'] ?? ''),
            'result'   => $result,
        ];
    }

    private function parse_response($resp): ?array
    {
        if (is_wp_error($resp)) {
            return null;
        }
        $body = wp_remote_retrieve_body($resp);
        if ($body === '') {
            return null;
        }
        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }

    private function truthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['true', '1', 'success', 'completed', 'ok'], true);
        }
        return (bool) $value;
    }
}
