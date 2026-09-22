# SwapnoPay Developer Python SDK (Enterprise Edition)
# Features: Auto-Retry with backoff, Sandbox/Production Environments, and Webhook Signature Verification.

import json
import hmac
import hashlib
import time
import urllib.request
import urllib.error
import random

class SwapnoPayException(Exception):
    def __init__(self, message, status_code=None, details=None):
        super().__init__(message)
        self.status_code = status_code
        self.details = details

class SwapnoPayClient:
    def __init__(self, secret_key, supabase_url, environment="production", timeout=10, max_retries=3, debug=False):
        if not secret_key:
            raise SwapnoPayException("Merchant secret_key is required")
        if not supabase_url:
            raise SwapnoPayException("Supabase project URL is required")

        self.secret_key = secret_key
        self.supabase_url = supabase_url.rstrip('/')
        self.environment = environment
        self.timeout = timeout
        self.max_retries = max_retries
        self.debug = debug

    def _request(self, path, data=None, retry_count=0):
        url = f"{self.supabase_url}{path}"
        payload = json.dumps(data or {}).encode('utf-8')

        headers = {
            'Content-Type': 'application/json',
            'apikey': self.secret_key,
            'Authorization': f"Bearer {self.secret_key}"
        }

        if self.debug:
            print(f"[SwapnoPay Debug] API Request: POST {url} | Retry: {retry_count}/{self.max_retries}")

        req = urllib.request.Request(url, data=payload, headers=headers, method='POST')

        try:
            with urllib.request.urlopen(req, timeout=self.timeout) as response:
                body = response.read().decode('utf-8')
                try:
                    return json.loads(body)
                except ValueError:
                    return {"raw": body}
        except urllib.error.HTTPError as e:
            body = e.read().decode('utf-8')
            try:
                parsed_err = json.loads(body)
            except ValueError:
                parsed_err = {"raw": body}
            
            error_msg = parsed_err.get("error", f"HTTP Error {e.code}")
            raise SwapnoPayException(error_msg, e.code, parsed_err)
        except (urllib.error.URLError, Exception) as e:
            if retry_count < self.max_retries:
                # Exponential Backoff with Jitter
                delay = (2 ** retry_count) + random.uniform(0, 0.2)
                if self.debug:
                    print(f"[SwapnoPay Debug] Connection error: {e}. Retrying in {delay:.2f}s...")
                time.sleep(delay)
                return self._request(path, data, retry_count + 1)
            raise SwapnoPayException(f"Network Connection Failure: {e}")

    def create_order(self, tran_id, amount, cus_phone, cus_email=None, callback_url=None, payment_method=None):
        """
        1. Register checkout orders.
        """
        payload = {
            "merchantSecret": self.secret_key,
            "tran_id": str(tran_id),
            "amount": float(amount),
            "cus_phone": str(cus_phone),
            "cus_email": cus_email or "",
            "callback_url": callback_url or "",
            "payment_method": payment_method or ""
        }
        return self._request("/functions/v1/create-order", payload)

    def resolve_appeal(self, appeal_id, action, order_id=None):
        """
        2. Manually verify appeals disputes.
        """
        payload = {
            "appeal_id": appeal_id,
            "action": action,
            "order_id": order_id
        }
        return self._request("/functions/v1/resolve-appeal", payload)

    def verify_webhook_signature(self, payload, signature):
        """
        3. Webhooks signature verification tool.
        """
        try:
            body_str = payload if isinstance(payload, str) else json.dumps(payload, separators=(',', ':'))
            computed = hmac.new(
                self.secret_key.encode('utf-8'),
                body_str.encode('utf-8'),
                hashlib.sha256
            ).hexdigest()

            return hmac.compare_digest(computed, signature)
        except Exception as e:
            if self.debug:
                print(f"[SwapnoPay Debug] Webhook signature verification error: {e}")
            return False
