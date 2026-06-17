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
    private string $create_url;
    private string $status_url;

    public function __construct(string $user_token, string $api_base = 'https://api.imbpay.in')
    {
        $this->user_token = trim($user_token);
        $this->api_base   = rtrim(trim($api_base), '/');
        // Custom-checkout create-order lives at /v2 (returns phonepe_link etc.);
        // status check stays at /api. Both overridable for non-standard accounts.
        $this->create_url = (string) apply_filters('ww_imb_create_order_url', $this->api_base . '/v2/create-order', $this->api_base);
        $this->status_url = (string) apply_filters('ww_imb_status_url', $this->api_base . '/api/check-order-status', $this->api_base);
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

        $resp = wp_remote_post($this->create_url, [
            'timeout' => 25,
            'body'    => $body, // application/x-www-form-urlencoded (per IMB docs)
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
     *
     * IMB's docs show a raw-JSON body for this endpoint, but the rest of their
     * API is form-based. To be safe in production we try JSON first (as
     * documented) and fall back to form-encoded fields if that doesn't yield a
     * usable status — so auto-verification works regardless of how IMB parses
     * the request.
     */
    public function check_order_status(string $order_id): ?array
    {
        $url    = $this->status_url;
        $fields = ['user_token' => $this->user_token, 'order_id' => $order_id];

        // Attempt 1 — raw JSON body (as documented).
        $data = $this->parse_response(wp_remote_post($url, [
            'timeout' => 25,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($fields),
        ]));
        if ($this->has_status($data)) {
            return $data;
        }

        // Attempt 2 — form-encoded fields (fallback).
        $data2 = $this->parse_response(wp_remote_post($url, [
            'timeout' => 25,
            'body'    => $fields,
        ]));
        return $this->has_status($data2) ? $data2 : ($data ?? $data2);
    }

    /**
     * Does a decoded body carry a usable transaction status?
     */
    private function has_status(?array $d): bool
    {
        if (!is_array($d)) {
            return false;
        }
        if (isset($d['status'])) {
            return true;
        }
        $r = $d['result'] ?? null;
        return is_array($r) && (isset($r['status']) || isset($r['txnStatus']));
    }

    /**
     * Map IMB's status fields to one internal state.
     *
     * IMPORTANT: in this API the **top-level** `status` is the API-call flag
     * (`true` / `"false"` / `"success"`), NOT the transaction state. So we treat
     * only the nested `result.txnStatus` / `result.status` as authoritative for
     * payment state. The top-level field is honoured only when it is an
     * UNAMBIGUOUS transaction token (e.g. `COMPLETED`/`EXPIRED`) — never a bare
     * `success`/`true`/`ok`, which would otherwise mark an unpaid order as paid.
     */
    public function normalize_status(?array $payload): string
    {
        if (!is_array($payload)) {
            return self::STATUS_PENDING;
        }

        // Authoritative: nested transaction status fields only.
        $result = (isset($payload['result']) && is_array($payload['result'])) ? $payload['result'] : [];
        $nested = [];
        foreach (['txnStatus', 'status'] as $k) {
            if (isset($result[$k]) && is_string($result[$k])) {
                $nested[] = strtoupper(trim($result[$k]));
            }
        }
        if (array_intersect($nested, self::SUCCESS_TOKENS)) {
            return self::STATUS_SUCCESS;
        }
        if (array_intersect($nested, self::FAILED_TOKENS)) {
            return self::STATUS_FAILED;
        }

        // Fallback to top-level only for unambiguous transaction tokens.
        $top = (isset($payload['status']) && is_string($payload['status'])) ? strtoupper(trim($payload['status'])) : '';
        $top_success = ['COMPLETED', 'PAID']; // deliberately excludes ambiguous SUCCESS/TRUE/OK
        if (in_array($top, $top_success, true)) {
            return self::STATUS_SUCCESS;
        }
        if (in_array($top, self::FAILED_TOKENS, true)) {
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
