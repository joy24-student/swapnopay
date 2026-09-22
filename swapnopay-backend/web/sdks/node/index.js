// SwapnoPay Developer Node.js SDK (Enterprise Edition)
// Features: Auto-Retry with Exponential Backoff, Sandbox/Production Environments, Custom Exception Handling, and Webhook Signature Verification.

const crypto = require('crypto');
const https = require('https');

class SwapnoPayException extends Error {
  constructor(message, statusCode = null, details = null) {
    super(message);
    this.name = 'SwapnoPayException';
    this.statusCode = statusCode;
    this.details = details;
  }
}

class SwapnoPayClient {
  /**
   * @param {Object} config
   * @param {string} config.secretKey - Merchant API Secret Key
   * @param {string} config.supabaseUrl - Supabase Project Base URL
   * @param {string} [config.environment='production'] - 'sandbox' or 'production'
   * @param {number} [config.timeout=10000] - Request Timeout in milliseconds
   * @param {number} [config.maxRetries=3] - Maximum retry counts on network failure
   * @param {boolean} [config.debug=false] - Output debugging logs
   */
  constructor(config) {
    if (!config.secretKey) throw new SwapnoPayException('Merchant secretKey is required');
    if (!config.supabaseUrl) throw new SwapnoPayException('Supabase project URL is required');

    this.secretKey = config.secretKey;
    this.supabaseUrl = config.supabaseUrl.replace(/\/$/, '');
    this.environment = config.environment || 'production';
    this.timeout = config.timeout || 10000;
    this.maxRetries = config.maxRetries !== undefined ? config.maxRetries : 3;
    this.debug = !!config.debug;
  }

  /**
   * Internal HTTP client wrapper executing DTO posts with auto-retry and timeouts.
   */
  async _request(path, data = {}, retryCount = 0) {
    const url = `${this.supabaseUrl}${path}`;
    const headers = {
      'Content-Type': 'application/json',
      'apikey': this.secretKey, // Defaults to authorization secrets
      'Authorization': `Bearer ${this.secretKey}`
    };

    if (this.debug) {
      console.log(`[SwapnoPay Debug] API Request: POST ${url} | Retry: ${retryCount}/${this.maxRetries}`);
    }

    return new Promise((resolve, reject) => {
      const parsedUrl = new URL(url);
      const postData = JSON.stringify(data);

      const options = {
        hostname: parsedUrl.hostname,
        port: parsedUrl.port || 443,
        path: parsedUrl.pathname + parsedUrl.search,
        method: 'POST',
        headers: {
          ...headers,
          'Content-Length': Buffer.byteLength(postData)
        },
        timeout: this.timeout
      };

      const req = https.request(options, (res) => {
        let responseBody = '';
        res.on('data', (chunk) => { responseBody += chunk; });
        res.on('end', () => {
          let parsedData = {};
          try {
            parsedData = JSON.parse(responseBody);
          } catch (e) {
            parsedData = { raw: responseBody };
          }

          if (res.statusCode >= 200 && res.statusCode < 300) {
            resolve(parsedData);
          } else {
            const err = new SwapnoPayException(
              parsedData.error || `HTTP Error ${res.statusCode}`,
              res.statusCode,
              parsedData
            );
            reject(err);
          }
        });
      });

      req.on('error', async (error) => {
        // Trigger auto-retry logic on network timeouts or interface resets
        if (retryCount < this.maxRetries) {
          const delay = Math.pow(2, retryCount) * 1000 + Math.random() * 200; // Exponential backoff with jitter
          if (this.debug) {
            console.log(`[SwapnoPay Debug] Connection error, retrying in ${Math.round(delay)}ms...`);
          }
          await new Promise(r => setTimeout(r, delay));
          try {
            const retryRes = await this._request(path, data, retryCount + 1);
            resolve(retryRes);
          } catch (retryErr) {
            reject(retryErr);
          }
        } else {
          reject(new SwapnoPayException(`Network failure: ${error.message}`, null, error));
        }
      });

      req.on('timeout', () => {
        req.destroy();
        reject(new SwapnoPayException('API connection timed out.'));
      });

      req.write(postData);
      req.end();
    });
  }

  /**
   * 1. Register a dynamic checkout order gateway link.
   * @param {Object} params
   * @param {string} params.tran_id - Unique checkout transaction reference
   * @param {number} params.amount - Total price billing amount
   * @param {string} params.cus_phone - Payer billing mobile phone number
   * @param {string} [params.cus_email] - Payer email address
   * @param {string} [params.callback_url] - Merchant webhook URL
   * @returns {Promise<Object>} Order creation parameters
   */
  async createOrder(params) {
    const payload = {
      merchantSecret: this.secretKey,
      tran_id: params.tran_id,
      amount: parseFloat(params.amount),
      cus_phone: params.cus_phone,
      cus_email: params.cus_email || '',
      callback_url: params.callback_url || '',
      payment_method: params.payment_method || ''
    };
    return this._request('/functions/v1/create-order', payload);
  }

  /**
   * 2. Manually resolve payment dispute appeals.
   * @param {string} appealId - Appeal dispute ID
   * @param {string} action - Resolve status ('APPROVED' or 'REJECTED')
   * @param {string} [orderId] - WooCommerce/App Order ID
   */
  async resolveAppeal(appealId, action, orderId = null) {
    const payload = {
      appeal_id: appealId,
      action: action,
      order_id: orderId
    };
    return this._request('/functions/v1/resolve-appeal', payload);
  }

  /**
   * 3. Verify signature headers on incoming webhook requests.
   * @param {Object|string} payload - Raw string content or Object body of webhook POST
   * @param {string} signature - Value of X-Signature header
   * @returns {boolean} Verified check status
   */
  verifyWebhookSignature(payload, signature) {
    try {
      const bodyStr = typeof payload === 'string' ? payload : JSON.stringify(payload);
      const computed = crypto
        .createHmac('sha256', this.secretKey)
        .update(bodyStr)
        .digest('hex');

      return computed === signature;
    } catch (e) {
      if (this.debug) {
        console.error('[SwapnoPay Debug] Signature verification error:', e);
      }
      return false;
    }
  }
}

module.exports = {
  SwapnoPayClient,
  SwapnoPayException
};
