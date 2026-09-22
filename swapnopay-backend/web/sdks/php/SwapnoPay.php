<?php
/**
 * SwapnoPay Developer PHP SDK (Enterprise Edition)
 * Features: Auto-Retry with backoff, Sandbox/Production Environments, and Webhook Signature Verification.
 */

namespace SwapnoPay;

class SwapnoPayException extends \Exception {
    protected $statusCode;
    protected $details;

    public function __construct($message, $statusCode = null, $details = null) {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->details = $details;
    }

    public function getStatusCode() {
        return $this->statusCode;
    }

    public function getDetails() {
        return $this->details;
    }
}

class SwapnoPayClient {
    private $secretKey;
    private $supabaseUrl;
    private $environment;
    private $timeout;
    private $maxRetries;
    private $debug;

    /**
     * @param array $config
     * - secretKey (string): Merchant Secret Key
     * - supabaseUrl (string): Supabase Base URL
     * - environment (string): 'sandbox' or 'production'
     * - timeout (int): Connection timeout in seconds
     * - maxRetries (int): Maximum retry counts
     * - debug (bool): Enable debug logs
     */
    public function __construct(array $config) {
        if (empty($config['secretKey'])) {
            throw new SwapnoPayException('Merchant secretKey is required');
        }
        if (empty($config['supabaseUrl'])) {
            throw new SwapnoPayException('Supabase project URL is required');
        }

        $this->secretKey   = $config['secretKey'];
        $this->supabaseUrl = rtrim($config['supabaseUrl'], '/');
        $this->environment = isset($config['environment']) ? $config['environment'] : 'production';
        $this->timeout     = isset($config['timeout']) ? (int)$config['timeout'] : 10;
        $this->maxRetries  = isset($config['maxRetries']) ? (int)$config['maxRetries'] : 3;
        $this->debug       = !empty($config['debug']);
    }

    /**
     * Internal cURL wrapper executing requests with exponential retry backup logic.
     */
    private function _request($path, array $data = [], $retryCount = 0) {
        $url = $this->supabaseUrl . $path;
        $payload = json_encode($data);

        $headers = [
            'Content-Type: application/json',
            'apikey: ' . $this->secretKey,
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Length: ' . strlen($payload)
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($this->debug) {
            error_log(sprintf("[SwapnoPay Debug] API Request: POST %s | Retry: %d/%d", $url, $retryCount, $this->maxRetries));
        }

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Handle Curl network errors or connection failures
        if ($responseBody === false) {
            if ($retryCount < $this->maxRetries) {
                // Exponential Backoff with Jitter
                $delay = pow(2, $retryCount) * 1000000 + rand(0, 200000);
                if ($this->debug) {
                    error_log(sprintf("[SwapnoPay Debug] Curl error: %s. Retrying in %dms...", $curlError, $delay / 1000));
                }
                usleep($delay);
                return $this->_request($path, $data, $retryCount + 1);
            }
            throw new SwapnoPayException(sprintf('Network Connection Failure: %s', $curlError));
        }

        $parsedData = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $parsedData = ['raw' => $responseBody];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return $parsedData;
        }

        $errorMsg = isset($parsedData['error']) ? $parsedData['error'] : 'HTTP Error ' . $httpCode;
        throw new SwapnoPayException($errorMsg, $httpCode, $parsedData);
    }

    /**
     * 1. Register a checkout transaction order.
     */
    public function createOrder(array $params) {
        $payload = [
            'merchantSecret' => $this->secretKey,
            'tran_id'        => (string)$params['tran_id'],
            'amount'         => (float)$params['amount'],
            'cus_phone'      => (string)$params['cus_phone'],
            'cus_email'      => isset($params['cus_email']) ? (string)$params['cus_email'] : '',
            'callback_url'   => isset($params['callback_url']) ? (string)$params['callback_url'] : '',
            'payment_method' => isset($params['payment_method']) ? (string)$params['payment_method'] : ''
        ];
        return $this->_request('/functions/v1/create-order', $payload);
    }

    /**
     * 2. Manually verify dispute appeal status.
     */
    public function resolveAppeal($appealId, $action, $orderId = null) {
        $payload = [
            'appeal_id' => $appealId,
            'action'    => $action,
            'order_id'  => $orderId
        ];
        return $this->_request('/functions/v1/resolve-appeal', $payload);
    }

    /**
     * 3. Validate signature headers on incoming webhook requests.
     */
    public function verifyWebhookSignature($payload, $signature) {
        try {
            $bodyStr = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_SLASHES);
            $computed = hash_hmac('sha256', $bodyStr, $this->secretKey);
            return hash_equals($computed, $signature);
        } catch (\Exception $e) {
            if ($this->debug) {
                error_log('[SwapnoPay Debug] Signature verification error: ' . $e->getMessage());
            }
            return false;
        }
    }
}
