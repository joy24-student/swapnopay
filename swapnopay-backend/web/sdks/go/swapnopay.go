package swapnopay

import (
	"bytes"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"math"
	"math/rand"
	"net/http"
	"strings"
	"time"
)

// Client Config Options
type Config struct {
	SecretKey   string
	SupabaseURL string
	Environment string // "sandbox" or "production"
	Timeout     time.Duration
	MaxRetries  int
	Debug       bool
}

type SwapnoPayClient struct {
	secretKey   string
	supabaseURL string
	environment string
	timeout     time.Duration
	maxRetries  int
	debug       bool
	httpClient  *http.Client
}

// Request & Response Data Transfer Objects (DTO)
type CreateOrderRequest struct {
	MerchantSecret string  `json:"merchantSecret"`
	TranID         string  `json:"tran_id"`
	Amount         float64 `json:"amount"`
	CusPhone       string  `json:"cus_phone"`
	CusEmail       string  `json:"cus_email,omitempty"`
	CallbackURL    string  `json:"callback_url,omitempty"`
	PaymentMethod  string  `json:"payment_method,omitempty"`
}

type CreateOrderResponse struct {
	Status         string `json:"status"`
	OrderID        string `json:"order_id"`
	MerchantNumber string `json:"merchantNumber"`
	ExpiresAt      string `json:"expiresAt"`
	Error          string `json:"error,omitempty"`
}

type ResolveAppealRequest struct {
	AppealID string  `json:"appeal_id"`
	Action   string  `json:"action"` // "APPROVED" or "REJECTED"
	OrderID  *string `json:"order_id,omitempty"`
}

type ResolveAppealResponse struct {
	Status  string `json:"status"`
	Message string `json:"message"`
	Error   string `json:"error,omitempty"`
}

func NewClient(cfg Config) (*SwapnoPayClient, error) {
	if cfg.SecretKey == "" {
		return nil, errors.New("merchant SecretKey is required")
	}
	if cfg.SupabaseURL == "" {
		return nil, errors.New("supabase project URL is required")
	}

	timeout := cfg.Timeout
	if timeout == 0 {
		timeout = 10 * time.Second
	}

	maxRetries := cfg.MaxRetries
	if maxRetries == 0 {
		maxRetries = 3
	}

	environment := cfg.Environment
	if environment == "" {
		environment = "production"
	}

	return &SwapnoPayClient{
		secretKey:   cfg.SecretKey,
		supabaseURL: strings.TrimRight(cfg.SupabaseURL, "/"),
		environment: environment,
		timeout:     timeout,
		maxRetries:  maxRetries,
		debug:       cfg.Debug,
		httpClient:  &http.Client{Timeout: timeout},
	}, nil
}

// Internal request dispatcher implementing Backoff with Jitter retries
func (c *SwapnoPayClient) request(path string, body interface{}, responseTarget interface{}) error {
	url := fmt.Sprintf("%s%s", c.supabaseURL, path)
	
	payloadBytes, err := json.Marshal(body)
	if err != nil {
		return fmt.Errorf("failed to marshal request request payload: %w", err)
	}

	var respBody []byte
	var httpStatus int

	for attempt := 0; attempt <= c.maxRetries; attempt++ {
		if c.debug {
			fmt.Printf("[SwapnoPay Debug] API Request: POST %s | Attempt: %d/%d\n", url, attempt, c.maxRetries)
		}

		req, err := http.NewRequest("POST", url, bytes.NewBuffer(payloadBytes))
		if err != nil {
			return fmt.Errorf("failed to construct request target: %w", err)
		}

		req.Header.Set("Content-Type", "application/json")
		req.Header.Set("apikey", c.secretKey)
		req.Header.Set("Authorization", fmt.Sprintf("Bearer %s", c.secretKey))

		resp, err := c.httpClient.Do(req)
		if err != nil {
			if attempt < c.maxRetries {
				// Backoff
				backoff := math.Pow(2, float64(attempt)) * 1000
				jitter := rand.Float64() * 200
				sleepDuration := time.Duration(backoff+jitter) * time.Millisecond
				if c.debug {
					fmt.Printf("[SwapnoPay Debug] Network error: %s. Retrying in %v...\n", err.Error(), sleepDuration)
				}
				time.Sleep(sleepDuration)
				continue
			}
			return fmt.Errorf("network client error: %w", err)
		}

		httpStatus = resp.StatusCode
		respBody, err = io.ReadAll(resp.Body)
		resp.Body.Close()
		if err != nil {
			return fmt.Errorf("failed to read response block body: %w", err)
		}

		if httpStatus >= 200 && httpStatus < 300 {
			return json.Unmarshal(respBody, responseTarget)
		}

		// Handle HTTP status errors
		var errorResponse struct {
			Error string `json:"error"`
		}
		json.Unmarshal(respBody, &errorResponse)
		if errorResponse.Error == "" {
			errorResponse.Error = fmt.Sprintf("HTTP Code %d", httpStatus)
		}
		return fmt.Errorf("API response error: %s", errorResponse.Error)
	}

	return fmt.Errorf("max retries exceeded with status %d", httpStatus)
}

// 1. CreateOrder creates a checkout order.
func (c *SwapnoPayClient) CreateOrder(req CreateOrderRequest) (*CreateOrderResponse, error) {
	req.MerchantSecret = c.secretKey
	var response CreateOrderResponse
	err := c.request("/functions/v1/create-order", req, &response)
	if err != nil {
		return nil, err
	}
	return &response, nil
}

// 2. ResolveAppeal manually resolves checkout dispute appeals.
func (c *SwapnoPayClient) ResolveAppeal(req ResolveAppealRequest) (*ResolveAppealResponse, error) {
	var response ResolveAppealResponse
	err := c.request("/functions/v1/resolve-appeal", req, &response)
	if err != nil {
		return nil, err
	}
	return &response, nil
}

// 3. VerifyWebhookSignature verifies the payload signature from webhooks.
func (c *SwapnoPayClient) VerifyWebhookSignature(payload []byte, signature string) bool {
	mac := hmac.New(sha256.New, []byte(c.secretKey))
	mac.Write(payload)
	expectedMAC := hex.EncodeToString(mac.Sum(nil))
	return hmac.Equal([]byte(expectedMAC), []byte(signature))
}
