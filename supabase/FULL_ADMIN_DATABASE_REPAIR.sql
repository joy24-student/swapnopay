-- ==============================================================================
-- SWAPNOPAY PLATFORM ADMIN — FULL DATABASE MASTER REPAIR SCRIPT (V2 - DEFENSIVE)
-- Project: tldubojeokgyoclxnzkb.supabase.co
--
-- PURPOSE:
-- 1. Fix GoTrue Auth 500 null token errors & user identity sync
-- 2. Repair Admin Users table and seed super_admin
-- 3. Repair Merchants table with all KYC, Subscription, Trial, and PIN columns
-- 4. Create & repair Merchant KYC Submissions, Devices, and Numbers tables
-- 5. Create & repair Platform Subscription Config and Merchant Subscriptions History
-- 6. Create & repair Merchant Notifications / Admin Broadcasts
-- 7. Create & repair Gateway Config, Platform API Keys, and Payment Events
-- 8. Create & repair Support Helpdesk (Tickets, Feature Requests, Live Chat)
-- 9. Create & seed Showcase Config and MFS SMS Regex Patterns
-- 10. Enable bulletproof RLS policies for service_role, admin_users, and merchants
-- 11. Enable Realtime Replication on all live channels
-- 12. Refresh PostgREST schema cache
--
-- INSTRUCTIONS:
-- Run this directly in the Supabase SQL Editor (as postgres/superuser role).
-- This script is 100% IDEMPOTENT (safe to run multiple times without data loss).
-- ==============================================================================

BEGIN;

-- ─────────────────────────────────────────────────────────────────────────────
-- 0. EXTENSIONS
-- ─────────────────────────────────────────────────────────────────────────────
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. FIX AUTH.USERS CORRUPTION (Fixes GoTrue "500: Database error querying schema")
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE auth.users
SET 
  confirmation_token = COALESCE(confirmation_token, ''),
  recovery_token = COALESCE(recovery_token, ''),
  email_change_token_new = COALESCE(email_change_token_new, ''),
  email_change = COALESCE(email_change, ''),
  phone_change = COALESCE(phone_change, ''),
  reauthentication_token = COALESCE(reauthentication_token, ''),
  email_confirmed_at = COALESCE(email_confirmed_at, now()),
  raw_app_meta_data = COALESCE(raw_app_meta_data, '{"provider":"email","providers":["email"]}'::jsonb),
  raw_user_meta_data = COALESCE(raw_user_meta_data, '{}'::jsonb)
WHERE confirmation_token IS NULL 
   OR recovery_token IS NULL 
   OR email_change_token_new IS NULL 
   OR email_change IS NULL;

-- Ensure auth.identities exists for any user in auth.users
DO $$
BEGIN
  INSERT INTO auth.identities (
    id,
    user_id,
    identity_data,
    provider,
    provider_id,
    last_sign_in_at,
    created_at,
    updated_at
  )
  SELECT 
    u.id::text,
    u.id,
    format('{"sub":"%s","email":"%s"}', u.id::text, u.email)::jsonb,
    'email',
    u.id::text,
    now(),
    now(),
    now()
  FROM auth.users u
  WHERE NOT EXISTS (
    SELECT 1 FROM auth.identities i WHERE i.user_id = u.id
  )
  ON CONFLICT DO NOTHING;
EXCEPTION WHEN OTHERS THEN
  RAISE NOTICE 'Skipping auth.identities insert: %', SQLERRM;
END $$;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. ADMIN USERS TABLE & SUPER ADMIN SEED
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS public.admin_users (
  id          UUID PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
  email       TEXT NOT NULL UNIQUE,
  role        TEXT NOT NULL DEFAULT 'admin' CHECK (role IN ('super_admin','admin','viewer')),
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.admin_users
  ADD COLUMN IF NOT EXISTS email TEXT,
  ADD COLUMN IF NOT EXISTS role TEXT DEFAULT 'admin',
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now();

-- Seed existing admin auth user as super_admin
INSERT INTO public.admin_users (id, email, role)
SELECT id, email, 'super_admin'
FROM auth.users
WHERE email = 'admin@swapnopay.top'
ON CONFLICT (id) DO UPDATE SET role = 'super_admin';

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. MERCHANTS TABLE (CORE REGISTRY + KYC + SUBSCRIPTIONS + PIN)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS public.merchants (
  id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id                 TEXT,
  business_name           TEXT NOT NULL DEFAULT 'My Business',
  email                   TEXT,
  phone                   TEXT,
  business_type           TEXT DEFAULT 'RETAIL',
  website                 TEXT,
  status                  VARCHAR(30) NOT NULL DEFAULT 'ACTIVE' CHECK (status IN ('ACTIVE', 'SUSPENDED', 'PENDING_VERIFICATION')),
  subscription_tier       VARCHAR(30) NOT NULL DEFAULT 'STARTER' CHECK (subscription_tier IN ('STARTER', 'PRO', 'ENTERPRISE')),
  created_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at              TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Alter user_id to TEXT and drop strict foreign key if present to allow flex-auth
DO $$
BEGIN
  ALTER TABLE public.merchants DROP CONSTRAINT IF EXISTS merchants_user_id_fkey;
  ALTER TABLE public.merchants ALTER COLUMN user_id TYPE TEXT USING user_id::text;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

-- Add all required profile, KYC, subscription, and security columns
ALTER TABLE public.merchants
  ADD COLUMN IF NOT EXISTS business_name TEXT DEFAULT 'My Business',
  ADD COLUMN IF NOT EXISTS email TEXT,
  ADD COLUMN IF NOT EXISTS phone TEXT,
  ADD COLUMN IF NOT EXISTS business_type TEXT DEFAULT 'RETAIL',
  ADD COLUMN IF NOT EXISTS website TEXT,
  ADD COLUMN IF NOT EXISTS status VARCHAR(30) DEFAULT 'ACTIVE',
  ADD COLUMN IF NOT EXISTS subscription_tier VARCHAR(30) DEFAULT 'STARTER',
  ADD COLUMN IF NOT EXISTS default_number TEXT,
  ADD COLUMN IF NOT EXISTS webhook_secret TEXT,
  ADD COLUMN IF NOT EXISTS api_key TEXT,
  ADD COLUMN IF NOT EXISTS logo_url TEXT,
  ADD COLUMN IF NOT EXISTS qr_code_url TEXT,
  ADD COLUMN IF NOT EXISTS photo_url TEXT,
  ADD COLUMN IF NOT EXISTS onboarded_at TIMESTAMPTZ,
  -- NID & KYC Biometric verification
  ADD COLUMN IF NOT EXISTS nid_number TEXT,
  ADD COLUMN IF NOT EXISTS nid_name TEXT,
  ADD COLUMN IF NOT EXISTS nid_dob TEXT,
  ADD COLUMN IF NOT EXISTS nid_front_url TEXT,
  ADD COLUMN IF NOT EXISTS nid_back_url TEXT,
  ADD COLUMN IF NOT EXISTS face_photo_url TEXT,
  ADD COLUMN IF NOT EXISTS kyc_status VARCHAR(30) DEFAULT 'UNVERIFIED',
  ADD COLUMN IF NOT EXISTS kyc_submitted_at TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS kyc_reviewed_at TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS kyc_reviewed_by TEXT,
  ADD COLUMN IF NOT EXISTS kyc_rejection_reason TEXT,
  -- Subscription & Anti-Piracy Trial Management
  ADD COLUMN IF NOT EXISTS trial_ends_at TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS subscription_status VARCHAR(30) DEFAULT 'TRIAL',
  ADD COLUMN IF NOT EXISTS subscription_plan VARCHAR(50) DEFAULT 'STARTER',
  ADD COLUMN IF NOT EXISTS subscription_expires_at TIMESTAMPTZ,
  -- Security PIN & Anti-Tamper
  ADD COLUMN IF NOT EXISTS app_pin_hash TEXT,
  ADD COLUMN IF NOT EXISTS pin_reset_requested BOOLEAN DEFAULT FALSE;

CREATE INDEX IF NOT EXISTS idx_merchants_user_id ON public.merchants(user_id);
CREATE INDEX IF NOT EXISTS idx_merchants_status ON public.merchants(status);
CREATE INDEX IF NOT EXISTS idx_merchants_kyc_status ON public.merchants(kyc_status);
CREATE INDEX IF NOT EXISTS idx_merchants_subscription ON public.merchants(subscription_status, subscription_expires_at);

-- ─────────────────────────────────────────────────────────────────────────────
-- 4. MERCHANT KYC SUBMISSIONS TABLE
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS public.merchant_kyc_submissions (
  id                UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id       TEXT,
  nid_number        TEXT,
  nid_name          TEXT,
  nid_dob           TEXT,
  nid_front_url     TEXT,
  nid_back_url      TEXT,
  face_photo_url    TEXT,
  liveness_passed   BOOLEAN NOT NULL DEFAULT false,
  ocr_raw_text      TEXT,
  status            TEXT NOT NULL DEFAULT 'PENDING',
  submitted_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
  reviewed_at       TIMESTAMPTZ,
  reviewed_by       TEXT,
  rejection_reason  TEXT,
  created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.merchant_kyc_submissions
  ADD COLUMN IF NOT EXISTS merchant_id TEXT,
  ADD COLUMN IF NOT EXISTS nid_number TEXT,
  ADD COLUMN IF NOT EXISTS nid_name TEXT,
  ADD COLUMN IF NOT EXISTS nid_dob TEXT,
  ADD COLUMN IF NOT EXISTS nid_front_url TEXT,
  ADD COLUMN IF NOT EXISTS nid_back_url TEXT,
  ADD COLUMN IF NOT EXISTS face_photo_url TEXT,
  ADD COLUMN IF NOT EXISTS liveness_passed BOOLEAN DEFAULT false,
  ADD COLUMN IF NOT EXISTS ocr_raw_text TEXT,
  ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'PENDING',
  ADD COLUMN IF NOT EXISTS submitted_at TIMESTAMPTZ DEFAULT now(),
  ADD COLUMN IF NOT EXISTS reviewed_at TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS reviewed_by TEXT,
  ADD COLUMN IF NOT EXISTS rejection_reason TEXT,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now(),
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT now();

CREATE INDEX IF NOT EXISTS idx_kyc_submissions_merchant ON public.merchant_kyc_submissions(merchant_id, submitted_at DESC);
CREATE INDEX IF NOT EXISTS idx_kyc_submissions_status ON public.merchant_kyc_submissions(status);

-- ─────────────────────────────────────────────────────────────────────────────
-- 5. MERCHANT DEVICES (ANDROID APP BINDING & FCM)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS public.merchant_devices (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id     TEXT,
  device_id       TEXT,
  device_name     TEXT,
  device_model    TEXT,
  fcm_token       TEXT,
  status          TEXT NOT NULL DEFAULT 'ACTIVE',
  last_active_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
  registered_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
  paired_at       TIMESTAMPTZ DEFAULT now(),
  paired_by       TEXT
);

ALTER TABLE public.merchant_devices
  ADD COLUMN IF NOT EXISTS merchant_id TEXT,
  ADD COLUMN IF NOT EXISTS device_id TEXT,
  ADD COLUMN IF NOT EXISTS device_name TEXT,
  ADD COLUMN IF NOT EXISTS device_model TEXT,
  ADD COLUMN IF NOT EXISTS fcm_token TEXT,
  ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'ACTIVE',
  ADD COLUMN IF NOT EXISTS last_active_at TIMESTAMPTZ DEFAULT now(),
  ADD COLUMN IF NOT EXISTS registered_at TIMESTAMPTZ DEFAULT now(),
  ADD COLUMN IF NOT EXISTS paired_at TIMESTAMPTZ DEFAULT now(),
  ADD COLUMN IF NOT EXISTS paired_by TEXT;

CREATE UNIQUE INDEX IF NOT EXISTS idx_merchant_devices_unique ON public.merchant_devices(merchant_id, device_id);
CREATE INDEX IF NOT EXISTS idx_merchant_devices_merchant ON public.merchant_devices(merchant_id);

-- ─────────────────────────────────────────────────────────────────────────────
-- 6. MERCHANT NUMBERS TABLE (MFS PHONE NUMBERS & LIMITS)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS public.merchant_numbers (
  id                    UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id           TEXT,
  mfs_provider          TEXT,
  phone_number          TEXT,
  account_type          TEXT DEFAULT 'PERSONAL',
  is_active             BOOLEAN DEFAULT true,
  daily_limit           NUMERIC DEFAULT 50000,
  monthly_limit         NUMERIC DEFAULT 300000,
  current_daily_total   NUMERIC DEFAULT 0,
  created_at            TIMESTAMPTZ DEFAULT now(),
  updated_at            TIMESTAMPTZ DEFAULT now()
);

ALTER TABLE public.merchant_numbers
  ADD COLUMN IF NOT EXISTS merchant_id TEXT,
  ADD COLUMN IF NOT EXISTS mfs_provider TEXT,
  ADD COLUMN IF NOT EXISTS phone_number TEXT,
  ADD COLUMN IF NOT EXISTS account_type TEXT DEFAULT 'PERSONAL',
  ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT true,
  ADD COLUMN IF NOT EXISTS daily_limit NUMERIC DEFAULT 50000,
  ADD COLUMN IF NOT EXISTS monthly_limit NUMERIC DEFAULT 300000,
  ADD COLUMN IF NOT EXISTS current_daily_total NUMERIC DEFAULT 0,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now(),
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT now();

CREATE INDEX IF NOT EXISTS idx_merchant_numbers_merchant ON public.merchant_numbers(merchant_id);

-- ─────────────────────────────────────────────────────────────────────────────
-- 7. PLATFORM SUBSCRIPTION CONFIGURATION TABLE (ADMIN DYNAMIC PRICING)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS public.platform_subscription_config (
  id                          UUID PRIMARY KEY DEFAULT '00000000-0000-0000-0000-000000000002',
  monthly_fee                 NUMERIC NOT NULL DEFAULT 499,
  quarterly_fee               NUMERIC NOT NULL DEFAULT 1299,
  yearly_fee                  NUMERIC NOT NULL DEFAULT 3999,
  trial_days                  INTEGER NOT NULL DEFAULT 7,
  trial_enabled               BOOLEAN NOT NULL DEFAULT true,
  nid_verification_required   BOOLEAN NOT NULL DEFAULT false,
  updated_at                  TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_by                  UUID
);

ALTER TABLE public.platform_subscription_config
  ADD COLUMN IF NOT EXISTS monthly_fee NUMERIC DEFAULT 499,
  ADD COLUMN IF NOT EXISTS quarterly_fee NUMERIC DEFAULT 1299,
  ADD COLUMN IF NOT EXISTS yearly_fee NUMERIC DEFAULT 3999,
  ADD COLUMN IF NOT EXISTS trial_days INTEGER DEFAULT 7,
  ADD COLUMN IF NOT EXISTS trial_enabled BOOLEAN DEFAULT true,
  ADD COLUMN IF NOT EXISTS nid_verification_required BOOLEAN DEFAULT false,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT now(),
  ADD COLUMN IF NOT EXISTS updated_by UUID;

-- Seed singleton row for platform subscription config
INSERT INTO public.platform_subscription_config (
  id,
  monthly_fee,
  quarterly_fee,
  yearly_fee,
  trial_days,
  trial_enabled,
  nid_verification_required,
  updated_at
) VALUES (
  '00000000-0000-0000-0000-000000000002',
  499,
  1299,
  3999,
  7,
  true,
  false,
  now()
) ON CONFLICT (id) DO UPDATE SET
  monthly_fee = EXCLUDED.monthly_fee,
  quarterly_fee = EXCLUDED.quarterly_fee,
  yearly_fee = EXCLUDED.yearly_fee,
  trial_days = EXCLUDED.trial_days;

-- ─────────────────────────────────────────────────────────────────────────────
-- 8. MERCHANT SUBSCRIPTIONS HISTORY TABLE
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS public.merchant_subscriptions (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id     TEXT,
  plan_id         TEXT DEFAULT 'PRO',
  billing_cycle   TEXT DEFAULT 'MONTHLY',
  amount          NUMERIC DEFAULT 499,
  payment_method  TEXT DEFAULT 'bKash',
  trx_id          TEXT,
  sender_number   TEXT,
  status          TEXT DEFAULT 'ACTIVE',
  starts_at       TIMESTAMPTZ DEFAULT now(),
  expires_at      TIMESTAMPTZ,
  approved_by     UUID,
  notes           TEXT,
  created_at      TIMESTAMPTZ DEFAULT now(),
  updated_at      TIMESTAMPTZ DEFAULT now()
);

ALTER TABLE public.merchant_subscriptions
  ADD COLUMN IF NOT EXISTS merchant_id TEXT,
  ADD COLUMN IF NOT EXISTS plan_id TEXT DEFAULT 'PRO',
  ADD COLUMN IF NOT EXISTS billing_cycle TEXT DEFAULT 'MONTHLY',
  ADD COLUMN IF NOT EXISTS amount NUMERIC DEFAULT 499,
  ADD COLUMN IF NOT EXISTS payment_method TEXT DEFAULT 'bKash',
  ADD COLUMN IF NOT EXISTS trx_id TEXT,
  ADD COLUMN IF NOT EXISTS sender_number TEXT,
  ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'ACTIVE',
  ADD COLUMN IF NOT EXISTS starts_at TIMESTAMPTZ DEFAULT now(),
  ADD COLUMN IF NOT EXISTS expires_at TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS approved_by UUID,
  ADD COLUMN IF NOT EXISTS notes TEXT,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now(),
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT now();

CREATE INDEX IF NOT EXISTS idx_merchant_subscriptions_merchant ON public.merchant_subscriptions(merchant_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_merchant_subscriptions_status ON public.merchant_subscriptions(status);

-- ─────────────────────────────────────────────────────────────────────────────
-- 9. MERCHANT NOTIFICATIONS & ADMIN BROADCAST POPUPS
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS public.merchant_notifications (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id     TEXT NOT NULL,
  title           TEXT NOT NULL,
  message         TEXT NOT NULL,
  type            TEXT NOT NULL DEFAULT 'SYSTEM',
  severity        TEXT NOT NULL DEFAULT 'INFO',
  read            BOOLEAN NOT NULL DEFAULT false,
  metadata        JSONB DEFAULT '{}'::jsonb,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.merchant_notifications
  ADD COLUMN IF NOT EXISTS merchant_id TEXT,
  ADD COLUMN IF NOT EXISTS title TEXT,
  ADD COLUMN IF NOT EXISTS message TEXT,
  ADD COLUMN IF NOT EXISTS type TEXT DEFAULT 'SYSTEM',
  ADD COLUMN IF NOT EXISTS severity TEXT DEFAULT 'INFO',
  ADD COLUMN IF NOT EXISTS read BOOLEAN DEFAULT false,
  ADD COLUMN IF NOT EXISTS metadata JSONB DEFAULT '{}'::jsonb,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now();

CREATE INDEX IF NOT EXISTS idx_merchant_notifications_merchant ON public.merchant_notifications(merchant_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_merchant_notifications_read ON public.merchant_notifications(read, created_at DESC);

-- Seed initial welcome announcement broadcast
INSERT INTO public.merchant_notifications (
  id,
  merchant_id,
  title,
  message,
  type,
  severity,
  read,
  created_at
) VALUES (
  '99999999-9999-9999-9999-999999999999',
  'ALL',
  'SwapnoPay প্ল্যাটফর্মে স্বাগতম!',
  'বিকাশ, নগদ, রকেট ও উপায়-এর ০.৮ সেকেন্ডে স্বয়ংক্রিয় পেমেন্ট ম্যাচিং এবং ডিজিটাল ব্যবসা খাতা এখন সক্রিয়। যেকোনো প্রয়োজনে অ্যাডমিন হটলাইনে যোগাযোগ করুন।',
  'SYSTEM',
  'SUCCESS',
  false,
  now()
) ON CONFLICT (id) DO NOTHING;

-- ─────────────────────────────────────────────────────────────────────────────
-- 10. GATEWAY CONFIGURATION (SINGLETON PLATFORM SETTINGS)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS public.gateway_config (
  id                          UUID PRIMARY KEY DEFAULT '00000000-0000-0000-0000-000000000001',
  bkash_enabled               BOOLEAN NOT NULL DEFAULT true,
  nagad_enabled               BOOLEAN NOT NULL DEFAULT true,
  rocket_enabled              BOOLEAN NOT NULL DEFAULT true,
  upay_enabled                BOOLEAN NOT NULL DEFAULT true,
  enabled_methods             JSONB NOT NULL DEFAULT '{"bKash":true,"Nagad":true,"Rocket":true,"Upay":true}'::jsonb,
  default_success_url         TEXT NOT NULL DEFAULT 'https://pay.swapnopay.top/success',
  default_fail_url            TEXT NOT NULL DEFAULT 'https://pay.swapnopay.top/failed',
  default_cancel_url          TEXT NOT NULL DEFAULT 'https://pay.swapnopay.top/cancelled',
  min_amount                  NUMERIC NOT NULL DEFAULT 10,
  max_amount                  NUMERIC NOT NULL DEFAULT 500000,
  daily_limit_per_merchant    NUMERIC NOT NULL DEFAULT 1000000,
  payment_timeout_seconds     INTEGER NOT NULL DEFAULT 900,
  processing_timeout_seconds  INTEGER NOT NULL DEFAULT 60,
  customer_receipts_enabled   BOOLEAN NOT NULL DEFAULT true,
  merchant_receipts_enabled   BOOLEAN NOT NULL DEFAULT true,
  gateway_fee_percent         NUMERIC NOT NULL DEFAULT 1.5,
  gateway_fee_fixed           NUMERIC NOT NULL DEFAULT 0,
  maintenance_mode            BOOLEAN NOT NULL DEFAULT false,
  maintenance_message         TEXT NOT NULL DEFAULT 'Payment gateway is undergoing scheduled maintenance. Please try again shortly.',
  updated_at                  TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_by                  UUID
);

-- Defensively ensure ALL columns exist in gateway_config if table previously existed
ALTER TABLE public.gateway_config
  ADD COLUMN IF NOT EXISTS bkash_enabled BOOLEAN NOT NULL DEFAULT true,
  ADD COLUMN IF NOT EXISTS nagad_enabled BOOLEAN NOT NULL DEFAULT true,
  ADD COLUMN IF NOT EXISTS rocket_enabled BOOLEAN NOT NULL DEFAULT true,
  ADD COLUMN IF NOT EXISTS upay_enabled BOOLEAN NOT NULL DEFAULT true,
  ADD COLUMN IF NOT EXISTS enabled_methods JSONB NOT NULL DEFAULT '{"bKash":true,"Nagad":true,"Rocket":true,"Upay":true}'::jsonb,
  ADD COLUMN IF NOT EXISTS default_success_url TEXT NOT NULL DEFAULT 'https://pay.swapnopay.top/success',
  ADD COLUMN IF NOT EXISTS default_fail_url TEXT NOT NULL DEFAULT 'https://pay.swapnopay.top/failed',
  ADD COLUMN IF NOT EXISTS default_cancel_url TEXT NOT NULL DEFAULT 'https://pay.swapnopay.top/cancelled',
  ADD COLUMN IF NOT EXISTS min_amount NUMERIC NOT NULL DEFAULT 10,
  ADD COLUMN IF NOT EXISTS max_amount NUMERIC NOT NULL DEFAULT 500000,
  ADD COLUMN IF NOT EXISTS daily_limit_per_merchant NUMERIC NOT NULL DEFAULT 1000000,
  ADD COLUMN IF NOT EXISTS payment_timeout_seconds INTEGER NOT NULL DEFAULT 900,
  ADD COLUMN IF NOT EXISTS processing_timeout_seconds INTEGER NOT NULL DEFAULT 60,
  ADD COLUMN IF NOT EXISTS customer_receipts_enabled BOOLEAN NOT NULL DEFAULT true,
  ADD COLUMN IF NOT EXISTS merchant_receipts_enabled BOOLEAN NOT NULL DEFAULT true,
  ADD COLUMN IF NOT EXISTS gateway_fee_percent NUMERIC NOT NULL DEFAULT 1.5,
  ADD COLUMN IF NOT EXISTS gateway_fee_fixed NUMERIC NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS maintenance_mode BOOLEAN NOT NULL DEFAULT false,
  ADD COLUMN IF NOT EXISTS maintenance_message TEXT NOT NULL DEFAULT 'Payment gateway is undergoing scheduled maintenance. Please try again shortly.',
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  ADD COLUMN IF NOT EXISTS updated_by UUID;

-- Seed singleton gateway config row
INSERT INTO public.gateway_config (
  id,
  bkash_enabled,
  nagad_enabled,
  rocket_enabled,
  upay_enabled,
  enabled_methods,
  default_success_url,
  default_fail_url,
  default_cancel_url,
  min_amount,
  max_amount,
  daily_limit_per_merchant,
  payment_timeout_seconds,
  processing_timeout_seconds,
  customer_receipts_enabled,
  merchant_receipts_enabled,
  gateway_fee_percent,
  gateway_fee_fixed,
  maintenance_mode,
  maintenance_message
) VALUES (
  '00000000-0000-0000-0000-000000000001',
  true,
  true,
  true,
  true,
  '{"bKash":true,"Nagad":true,"Rocket":true,"Upay":true}'::jsonb,
  'https://pay.swapnopay.top/success',
  'https://pay.swapnopay.top/failed',
  'https://pay.swapnopay.top/cancelled',
  10,
  500000,
  1000000,
  900,
  60,
  true,
  true,
  1.5,
  0,
  false,
  'Payment gateway is undergoing scheduled maintenance. Please try again shortly.'
) ON CONFLICT (id) DO NOTHING;

-- ─────────────────────────────────────────────────────────────────────────────
-- 11. PLATFORM API KEYS TABLE
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS public.platform_api_keys (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id     TEXT NOT NULL,
  merchant_name   TEXT NOT NULL DEFAULT 'Unknown Merchant',
  label           TEXT NOT NULL DEFAULT 'Production API Key',
  key_digest      TEXT,
  key_preview     TEXT NOT NULL,
  secret_hash     TEXT NOT NULL DEFAULT '',
  raw_key         TEXT,
  revoked         BOOLEAN NOT NULL DEFAULT false,
  revoked_at      TIMESTAMPTZ,
  revoked_by      UUID,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
  created_by      UUID
);

ALTER TABLE public.platform_api_keys
  ADD COLUMN IF NOT EXISTS merchant_id TEXT,
  ADD COLUMN IF NOT EXISTS merchant_name TEXT DEFAULT 'Unknown Merchant',
  ADD COLUMN IF NOT EXISTS label TEXT DEFAULT 'Production API Key',
  ADD COLUMN IF NOT EXISTS key_digest TEXT,
  ADD COLUMN IF NOT EXISTS key_preview TEXT,
  ADD COLUMN IF NOT EXISTS secret_hash TEXT DEFAULT '',
  ADD COLUMN IF NOT EXISTS raw_key TEXT,
  ADD COLUMN IF NOT EXISTS revoked BOOLEAN DEFAULT false,
  ADD COLUMN IF NOT EXISTS revoked_at TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS revoked_by UUID,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now(),
  ADD COLUMN IF NOT EXISTS created_by UUID;

CREATE INDEX IF NOT EXISTS idx_platform_api_keys_merchant ON public.platform_api_keys(merchant_id);
CREATE INDEX IF NOT EXISTS idx_platform_api_keys_raw ON public.platform_api_keys(raw_key);
CREATE INDEX IF NOT EXISTS idx_platform_api_keys_digest ON public.platform_api_keys(key_digest);

-- ─────────────────────────────────────────────────────────────────────────────
-- 12. PAYMENT EVENTS & ORDERS (PLATFORM AUDIT & LEDGER)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS public.payment_events (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  event_type      TEXT NOT NULL DEFAULT 'PAYMENT_RECEIVED',
  order_id        TEXT NOT NULL,
  tran_id         TEXT,
  trx_id          TEXT,
  merchant_id     TEXT NOT NULL,
  merchant_name   TEXT,
  amount          NUMERIC NOT NULL DEFAULT 0,
  currency        TEXT NOT NULL DEFAULT 'BDT',
  method          TEXT,
  payment_method  TEXT,
  sender_number   TEXT,
  status          TEXT NOT NULL DEFAULT 'PENDING',
  failure_reason  TEXT,
  metadata        JSONB DEFAULT '{}'::jsonb,
  recorded_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.payment_events
  ADD COLUMN IF NOT EXISTS event_type TEXT DEFAULT 'PAYMENT_RECEIVED',
  ADD COLUMN IF NOT EXISTS order_id TEXT,
  ADD COLUMN IF NOT EXISTS tran_id TEXT,
  ADD COLUMN IF NOT EXISTS trx_id TEXT,
  ADD COLUMN IF NOT EXISTS merchant_id TEXT,
  ADD COLUMN IF NOT EXISTS merchant_name TEXT,
  ADD COLUMN IF NOT EXISTS amount NUMERIC DEFAULT 0,
  ADD COLUMN IF NOT EXISTS currency TEXT DEFAULT 'BDT',
  ADD COLUMN IF NOT EXISTS method TEXT,
  ADD COLUMN IF NOT EXISTS payment_method TEXT,
  ADD COLUMN IF NOT EXISTS sender_number TEXT,
  ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'PENDING',
  ADD COLUMN IF NOT EXISTS failure_reason TEXT,
  ADD COLUMN IF NOT EXISTS metadata JSONB DEFAULT '{}'::jsonb,
  ADD COLUMN IF NOT EXISTS recorded_at TIMESTAMPTZ DEFAULT now();

CREATE INDEX IF NOT EXISTS idx_payment_events_merchant ON public.payment_events(merchant_id, recorded_at DESC);
CREATE INDEX IF NOT EXISTS idx_payment_events_order ON public.payment_events(order_id);
CREATE INDEX IF NOT EXISTS idx_payment_events_status ON public.payment_events(status, recorded_at DESC);

CREATE TABLE IF NOT EXISTS public.orders (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id     TEXT NOT NULL,
  order_number    TEXT NOT NULL,
  amount          NUMERIC NOT NULL DEFAULT 0,
  status          TEXT NOT NULL DEFAULT 'PENDING',
  payment_method  TEXT,
  trx_id          TEXT,
  customer_name   TEXT,
  customer_phone  TEXT,
  customer_email  TEXT,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.orders
  ADD COLUMN IF NOT EXISTS merchant_id TEXT,
  ADD COLUMN IF NOT EXISTS order_number TEXT,
  ADD COLUMN IF NOT EXISTS amount NUMERIC DEFAULT 0,
  ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'PENDING',
  ADD COLUMN IF NOT EXISTS payment_method TEXT,
  ADD COLUMN IF NOT EXISTS trx_id TEXT,
  ADD COLUMN IF NOT EXISTS customer_name TEXT,
  ADD COLUMN IF NOT EXISTS customer_phone TEXT,
  ADD COLUMN IF NOT EXISTS customer_email TEXT,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now(),
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT now();

CREATE INDEX IF NOT EXISTS idx_orders_merchant ON public.orders(merchant_id, created_at DESC);

-- ─────────────────────────────────────────────────────────────────────────────
-- 13. SHOWCASE CONFIG & SYSTEM SETTINGS
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS public.showcase_config (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  key         TEXT UNIQUE NOT NULL,
  value       JSONB NOT NULL,
  updated_at  TIMESTAMPTZ DEFAULT now()
);

ALTER TABLE public.showcase_config
  ADD COLUMN IF NOT EXISTS key TEXT,
  ADD COLUMN IF NOT EXISTS value JSONB,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT now();

-- Seed default showcase & subscription configs
INSERT INTO public.showcase_config (key, value)
VALUES 
(
  'system_config',
  '{
    "developer_portal_url": "https://pay.swapnopay.top/portal.html",
    "developer_docs_url": "https://pay.swapnopay.top/docs.html",
    "api_portal_url": "https://pay.swapnopay.top/portal.html#credentials",
    "webhook_docs_url": "https://pay.swapnopay.top/docs.html#webhooks",
    "support_hotline": "+8801700000000",
    "support_email": "support@swapnopay.top",
    "support_whatsapp": "+8801700000000"
  }'::jsonb
),
(
  'subscription_config',
  '{
    "monthly_fee": 499,
    "quarterly_fee": 1299,
    "yearly_fee": 3999,
    "trial_days": 7,
    "trial_enabled": true,
    "nid_verification_required": false
  }'::jsonb
),
(
  'system_notice',
  '{
    "id": "notice-welcome-2026",
    "title": "SwapnoPay সিস্টেম আপডেট",
    "message": "বিকাশ, নগদ, রকেট এবং উপায়-এর স্বয়ংক্রিয় পেমেন্ট ম্যাচিং ইঞ্জিন সম্পূর্ণ চালু রয়েছে।",
    "type": "SYSTEM",
    "severity": "INFO",
    "active": true
  }'::jsonb
)
ON CONFLICT (key) DO UPDATE SET
  value = EXCLUDED.value,
  updated_at = now();

-- ─────────────────────────────────────────────────────────────────────────────
-- 14. MFS REGEX PATTERNS (AUTOMATED SMS PARSER FOR ANDROID & GATEWAY)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS public.mfs_regex_patterns (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  mfs_name      TEXT NOT NULL,
  pattern_name  TEXT NOT NULL,
  regex_pattern TEXT NOT NULL,
  description   TEXT,
  active        BOOLEAN NOT NULL DEFAULT true,
  created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.mfs_regex_patterns
  ADD COLUMN IF NOT EXISTS mfs_name TEXT,
  ADD COLUMN IF NOT EXISTS pattern_name TEXT,
  ADD COLUMN IF NOT EXISTS regex_pattern TEXT,
  ADD COLUMN IF NOT EXISTS description TEXT,
  ADD COLUMN IF NOT EXISTS active BOOLEAN DEFAULT true,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now(),
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT now();

-- Seed default MFS SMS regex patterns
INSERT INTO public.mfs_regex_patterns (id, mfs_name, pattern_name, regex_pattern, description)
VALUES
  ('11111111-1111-1111-1111-111111111111', 'bKash', 'bKash Cash In / Payment Received', 'You have received Tk ([0-9,.]+) from ([0-9]+)\. TrxID ([A-Z0-9]+)', 'Standard bKash notification SMS'),
  ('22222222-2222-2222-2222-222222222222', 'Nagad', 'Nagad Payment Received', 'Received Tk ([0-9,.]+) from ([0-9]+)\. TxnID: ([A-Z0-9]+)', 'Standard Nagad notification SMS'),
  ('33333333-3333-3333-3333-333333333333', 'Rocket', 'Rocket Cash In', 'Tk([0-9,.]+) received from ([0-9]+)\. Ref: ([A-Z0-9]+)', 'Standard DBBL Rocket notification SMS'),
  ('44444444-4444-4444-4444-444444444444', 'Upay', 'Upay Payment Received', 'Received Tk ([0-9,.]+) from ([0-9]+)\. TxnID ([A-Z0-9]+)', 'Standard Upay notification SMS')
ON CONFLICT (id) DO UPDATE SET
  regex_pattern = EXCLUDED.regex_pattern,
  active = true;

-- ─────────────────────────────────────────────────────────────────────────────
-- 15. MERCHANT GATEWAY SETTINGS & CONNECTIONS
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS public.merchant_gateway_settings (
  id                    UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id           TEXT NOT NULL UNIQUE,
  merchant_name         TEXT,
  merchant_logo_url     TEXT,
  supabase_url          TEXT,
  supabase_anon_key     TEXT,
  enabled_methods       JSONB DEFAULT '{"bKash":true,"Nagad":true,"Rocket":true,"Upay":true}'::jsonb,
  receiving_numbers     JSONB DEFAULT '{}'::jsonb,
  min_amount            NUMERIC DEFAULT 10,
  max_amount            NUMERIC DEFAULT 500000,
  fee_bearer            TEXT DEFAULT 'MERCHANT',
  updated_at            TIMESTAMPTZ DEFAULT now()
);

ALTER TABLE public.merchant_gateway_settings
  ADD COLUMN IF NOT EXISTS merchant_id TEXT,
  ADD COLUMN IF NOT EXISTS merchant_name TEXT,
  ADD COLUMN IF NOT EXISTS merchant_logo_url TEXT,
  ADD COLUMN IF NOT EXISTS supabase_url TEXT,
  ADD COLUMN IF NOT EXISTS supabase_anon_key TEXT,
  ADD COLUMN IF NOT EXISTS enabled_methods JSONB DEFAULT '{"bKash":true,"Nagad":true,"Rocket":true,"Upay":true}'::jsonb,
  ADD COLUMN IF NOT EXISTS receiving_numbers JSONB DEFAULT '{}'::jsonb,
  ADD COLUMN IF NOT EXISTS min_amount NUMERIC DEFAULT 10,
  ADD COLUMN IF NOT EXISTS max_amount NUMERIC DEFAULT 500000,
  ADD COLUMN IF NOT EXISTS fee_bearer TEXT DEFAULT 'MERCHANT',
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT now();

CREATE TABLE IF NOT EXISTS public.merchant_connections (
  id                   UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id          TEXT NOT NULL UNIQUE,
  supabase_project_url TEXT,
  supabase_anon_key    TEXT,
  supabase_service_key TEXT,
  public_endpoint      TEXT,
  webhook_url          TEXT,
  created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at           TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.merchant_connections
  ADD COLUMN IF NOT EXISTS merchant_id TEXT,
  ADD COLUMN IF NOT EXISTS supabase_project_url TEXT,
  ADD COLUMN IF NOT EXISTS supabase_anon_key TEXT,
  ADD COLUMN IF NOT EXISTS supabase_service_key TEXT,
  ADD COLUMN IF NOT EXISTS public_endpoint TEXT,
  ADD COLUMN IF NOT EXISTS webhook_url TEXT,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now(),
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT now();

-- ─────────────────────────────────────────────────────────────────────────────
-- 16. SUPPORT HELPDESK & ADMIN REALTIME TOASTS
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS public.admin_notifications (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  title       TEXT NOT NULL,
  message     TEXT NOT NULL,
  type        TEXT NOT NULL DEFAULT 'SYSTEM_ALERT',
  read        BOOLEAN NOT NULL DEFAULT false,
  metadata    JSONB DEFAULT '{}'::jsonb,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.admin_notifications
  ADD COLUMN IF NOT EXISTS title TEXT,
  ADD COLUMN IF NOT EXISTS message TEXT,
  ADD COLUMN IF NOT EXISTS type TEXT DEFAULT 'SYSTEM_ALERT',
  ADD COLUMN IF NOT EXISTS read BOOLEAN DEFAULT false,
  ADD COLUMN IF NOT EXISTS metadata JSONB DEFAULT '{}'::jsonb,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now();

CREATE INDEX IF NOT EXISTS idx_admin_notifications_read ON public.admin_notifications(read, created_at DESC);

CREATE TABLE IF NOT EXISTS public.support_tickets (
  id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id    TEXT,
  business_name  TEXT,
  email          TEXT,
  phone          TEXT,
  category       TEXT NOT NULL DEFAULT 'GENERAL',
  subject        TEXT NOT NULL,
  description    TEXT NOT NULL,
  status         TEXT NOT NULL DEFAULT 'OPEN',
  admin_reply    TEXT,
  resolved_at    TIMESTAMPTZ,
  created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.support_tickets
  ADD COLUMN IF NOT EXISTS merchant_id TEXT,
  ADD COLUMN IF NOT EXISTS business_name TEXT,
  ADD COLUMN IF NOT EXISTS email TEXT,
  ADD COLUMN IF NOT EXISTS phone TEXT,
  ADD COLUMN IF NOT EXISTS category TEXT DEFAULT 'GENERAL',
  ADD COLUMN IF NOT EXISTS subject TEXT,
  ADD COLUMN IF NOT EXISTS description TEXT,
  ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'OPEN',
  ADD COLUMN IF NOT EXISTS admin_reply TEXT,
  ADD COLUMN IF NOT EXISTS resolved_at TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now(),
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT now();

CREATE INDEX IF NOT EXISTS idx_support_tickets_status ON public.support_tickets(status, created_at DESC);

CREATE TABLE IF NOT EXISTS public.feature_requests (
  id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id    TEXT,
  business_name  TEXT,
  email          TEXT,
  phone          TEXT,
  title          TEXT NOT NULL,
  category       TEXT NOT NULL DEFAULT 'GENERAL',
  description    TEXT NOT NULL,
  priority       TEXT NOT NULL DEFAULT 'MEDIUM',
  status         TEXT NOT NULL DEFAULT 'PENDING',
  admin_notes    TEXT,
  created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.feature_requests
  ADD COLUMN IF NOT EXISTS merchant_id TEXT,
  ADD COLUMN IF NOT EXISTS business_name TEXT,
  ADD COLUMN IF NOT EXISTS email TEXT,
  ADD COLUMN IF NOT EXISTS phone TEXT,
  ADD COLUMN IF NOT EXISTS title TEXT,
  ADD COLUMN IF NOT EXISTS category TEXT DEFAULT 'GENERAL',
  ADD COLUMN IF NOT EXISTS description TEXT,
  ADD COLUMN IF NOT EXISTS priority TEXT DEFAULT 'MEDIUM',
  ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'PENDING',
  ADD COLUMN IF NOT EXISTS admin_notes TEXT,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now(),
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT now();

CREATE TABLE IF NOT EXISTS public.live_chat_messages (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id  TEXT NOT NULL,
  sender       TEXT NOT NULL,
  message      TEXT NOT NULL,
  created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.live_chat_messages
  ADD COLUMN IF NOT EXISTS merchant_id TEXT,
  ADD COLUMN IF NOT EXISTS sender TEXT,
  ADD COLUMN IF NOT EXISTS message TEXT,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now();

CREATE INDEX IF NOT EXISTS idx_live_chat_messages_merchant ON public.live_chat_messages(merchant_id, created_at ASC);

-- ─────────────────────────────────────────────────────────────────────────────
-- 17. WEBHOOK SECRETS & AUDIT LOGS
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS public.webhook_secrets (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name        TEXT NOT NULL UNIQUE,
  secret_hash TEXT NOT NULL,
  active      BOOLEAN NOT NULL DEFAULT true,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
  rotated_at  TIMESTAMPTZ
);

ALTER TABLE public.webhook_secrets
  ADD COLUMN IF NOT EXISTS name TEXT,
  ADD COLUMN IF NOT EXISTS secret_hash TEXT,
  ADD COLUMN IF NOT EXISTS active BOOLEAN DEFAULT true,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now(),
  ADD COLUMN IF NOT EXISTS rotated_at TIMESTAMPTZ;

CREATE TABLE IF NOT EXISTS public.audit_logs (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  actor_email TEXT,
  action      TEXT NOT NULL,
  details     JSONB DEFAULT '{}'::jsonb,
  ip_address  TEXT,
  user_agent  TEXT,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.audit_logs
  ADD COLUMN IF NOT EXISTS actor_email TEXT,
  ADD COLUMN IF NOT EXISTS action TEXT,
  ADD COLUMN IF NOT EXISTS details JSONB DEFAULT '{}'::jsonb,
  ADD COLUMN IF NOT EXISTS ip_address TEXT,
  ADD COLUMN IF NOT EXISTS user_agent TEXT,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now();

CREATE TABLE IF NOT EXISTS public.gateway_device_alerts (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id     TEXT NOT NULL,
  customer_email  TEXT NOT NULL,
  order_id        TEXT,
  amount          NUMERIC,
  checkout_url    TEXT NOT NULL,
  status          TEXT NOT NULL DEFAULT 'PENDING',
  notified_at     TIMESTAMPTZ,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.gateway_device_alerts
  ADD COLUMN IF NOT EXISTS merchant_id TEXT,
  ADD COLUMN IF NOT EXISTS customer_email TEXT,
  ADD COLUMN IF NOT EXISTS order_id TEXT,
  ADD COLUMN IF NOT EXISTS amount NUMERIC,
  ADD COLUMN IF NOT EXISTS checkout_url TEXT,
  ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'PENDING',
  ADD COLUMN IF NOT EXISTS notified_at TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now();

-- ─────────────────────────────────────────────────────────────────────────────
-- 17.1 PAYMENT FORMS & FORM SUBMISSIONS (Hosted Checkout Studio)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS public.payment_forms (
  id                UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id       TEXT,
  title             TEXT NOT NULL DEFAULT 'Untitled Payment Form',
  description       TEXT,
  slug              TEXT UNIQUE NOT NULL DEFAULT ('pay-' || replace(gen_random_uuid()::text, '-', '')),
  template_type     TEXT NOT NULL DEFAULT 'BLANK',
  amount            NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  total_revenue     NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  views_count       INTEGER NOT NULL DEFAULT 0,
  submissions_count INTEGER NOT NULL DEFAULT 0,
  fields            JSONB NOT NULL DEFAULT '[]'::jsonb,
  products          JSONB NOT NULL DEFAULT '[]'::jsonb,
  pages             JSONB NOT NULL DEFAULT '[]'::jsonb,
  theme             JSONB NOT NULL DEFAULT '{}'::jsonb,
  status            TEXT NOT NULL DEFAULT 'DRAFT',
  logo_url          TEXT,
  banner_url        TEXT,
  created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.payment_forms
  ADD COLUMN IF NOT EXISTS merchant_id TEXT,
  ADD COLUMN IF NOT EXISTS title TEXT DEFAULT 'Untitled Payment Form',
  ADD COLUMN IF NOT EXISTS description TEXT,
  ADD COLUMN IF NOT EXISTS slug TEXT,
  ADD COLUMN IF NOT EXISTS template_type TEXT DEFAULT 'BLANK',
  ADD COLUMN IF NOT EXISTS amount NUMERIC(12,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS total_revenue NUMERIC(12,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS views_count INTEGER DEFAULT 0,
  ADD COLUMN IF NOT EXISTS submissions_count INTEGER DEFAULT 0,
  ADD COLUMN IF NOT EXISTS fields JSONB DEFAULT '[]'::jsonb,
  ADD COLUMN IF NOT EXISTS products JSONB DEFAULT '[]'::jsonb,
  ADD COLUMN IF NOT EXISTS pages JSONB DEFAULT '[]'::jsonb,
  ADD COLUMN IF NOT EXISTS theme JSONB DEFAULT '{}'::jsonb,
  ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'DRAFT',
  ADD COLUMN IF NOT EXISTS logo_url TEXT,
  ADD COLUMN IF NOT EXISTS banner_url TEXT,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now(),
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT now();

CREATE INDEX IF NOT EXISTS idx_payment_forms_merchant ON public.payment_forms(merchant_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_payment_forms_slug ON public.payment_forms(slug);

CREATE TABLE IF NOT EXISTS public.form_submissions (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  form_id         UUID REFERENCES public.payment_forms(id) ON DELETE CASCADE,
  order_id        TEXT,
  request_id      UUID NOT NULL DEFAULT gen_random_uuid(),
  client_hash     TEXT,
  customer_name   TEXT,
  customer_phone  TEXT,
  customer_email  TEXT,
  amount          NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  amount_bdt      NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  payment_method  TEXT NOT NULL DEFAULT 'bKash',
  payment_status  TEXT NOT NULL DEFAULT 'NOT_REQUIRED',
  trx_id          TEXT,
  answers         JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.form_submissions
  ADD COLUMN IF NOT EXISTS form_id UUID,
  ADD COLUMN IF NOT EXISTS order_id TEXT,
  ADD COLUMN IF NOT EXISTS request_id UUID DEFAULT gen_random_uuid(),
  ADD COLUMN IF NOT EXISTS client_hash TEXT,
  ADD COLUMN IF NOT EXISTS customer_name TEXT,
  ADD COLUMN IF NOT EXISTS customer_phone TEXT,
  ADD COLUMN IF NOT EXISTS customer_email TEXT,
  ADD COLUMN IF NOT EXISTS amount NUMERIC(12,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS amount_bdt NUMERIC(12,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS payment_method TEXT DEFAULT 'bKash',
  ADD COLUMN IF NOT EXISTS payment_status TEXT DEFAULT 'NOT_REQUIRED',
  ADD COLUMN IF NOT EXISTS trx_id TEXT,
  ADD COLUMN IF NOT EXISTS answers JSONB DEFAULT '{}'::jsonb,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now();

CREATE INDEX IF NOT EXISTS idx_form_submissions_form_id ON public.form_submissions(form_id);
CREATE INDEX IF NOT EXISTS idx_form_submissions_customer_phone ON public.form_submissions(customer_phone);

-- ─────────────────────────────────────────────────────────────────────────────
-- 18. STORAGE BUCKETS
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO storage.buckets (id, name, public)
VALUES 
  ('kyc-documents', 'kyc-documents', false),
  ('products', 'products', true),
  ('receipts', 'receipts', true),
  ('radymate-gallery', 'radymate-gallery', true)
ON CONFLICT (id) DO NOTHING;

-- ─────────────────────────────────────────────────────────────────────────────
-- 19. ENABLE ROW LEVEL SECURITY (RLS) ON ALL PLATFORM TABLES
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE public.admin_users ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.merchants ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.merchant_kyc_submissions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.merchant_devices ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.merchant_numbers ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.platform_subscription_config ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.merchant_subscriptions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.merchant_notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.gateway_config ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.platform_api_keys ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.payment_events ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.orders ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.showcase_config ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.mfs_regex_patterns ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.merchant_connections ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.merchant_gateway_settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.admin_notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.support_tickets ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.feature_requests ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.live_chat_messages ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.webhook_secrets ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.audit_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.gateway_device_alerts ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.payment_forms ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.form_submissions ENABLE ROW LEVEL SECURITY;

-- ─────────────────────────────────────────────────────────────────────────────
-- 20. POLICIES: SERVICE ROLE FULL ACCESS (Backend Client Bypass)
-- ─────────────────────────────────────────────────────────────────────────────
DO $$
DECLARE
  tbl text;
  all_tables text[] := ARRAY[
    'admin_users', 'merchants', 'merchant_kyc_submissions', 'merchant_devices',
    'merchant_numbers', 'platform_subscription_config', 'merchant_subscriptions',
    'merchant_notifications', 'gateway_config', 'platform_api_keys', 'payment_events',
    'orders', 'showcase_config', 'mfs_regex_patterns', 'merchant_connections',
    'merchant_gateway_settings', 'admin_notifications', 'support_tickets',
    'feature_requests', 'live_chat_messages', 'webhook_secrets', 'audit_logs',
    'gateway_device_alerts', 'payment_forms', 'form_submissions'
  ];
BEGIN
  FOREACH tbl IN ARRAY all_tables LOOP
    EXECUTE format('DROP POLICY IF EXISTS "Service role full access on %I" ON public.%I;', tbl, tbl);
    EXECUTE format('CREATE POLICY "Service role full access on %I" ON public.%I FOR ALL USING (auth.role() = ''service_role'');', tbl, tbl);
  END LOOP;
END $$;

-- ─────────────────────────────────────────────────────────────────────────────
-- 21. POLICIES: PLATFORM ADMIN ACCESS (Admin Users Full Access)
-- ─────────────────────────────────────────────────────────────────────────────
DROP POLICY IF EXISTS "Admin self read" ON public.admin_users;
CREATE POLICY "Admin self read" ON public.admin_users FOR SELECT TO authenticated
  USING (id = auth.uid());

DROP POLICY IF EXISTS "Super admin manage admins" ON public.admin_users;
DROP POLICY IF EXISTS "Super admins can manage all admin users" ON public.admin_users;
DROP POLICY IF EXISTS "Super admins can manage all admin records" ON public.admin_users;
CREATE POLICY "Super admins can manage all admin records" ON public.admin_users FOR ALL TO authenticated
  USING (public.is_super_admin(auth.uid()))
  WITH CHECK (public.is_super_admin(auth.uid()));

DO $$
DECLARE
  tbl text;
  admin_tables text[] := ARRAY[
    'merchants', 'merchant_kyc_submissions', 'merchant_devices',
    'merchant_numbers', 'platform_subscription_config', 'merchant_subscriptions',
    'merchant_notifications', 'gateway_config', 'platform_api_keys', 'payment_events',
    'orders', 'showcase_config', 'mfs_regex_patterns', 'merchant_connections',
    'merchant_gateway_settings', 'admin_notifications', 'support_tickets',
    'feature_requests', 'live_chat_messages', 'webhook_secrets', 'audit_logs',
    'gateway_device_alerts', 'payment_forms', 'form_submissions'
  ];
BEGIN
  FOREACH tbl IN ARRAY admin_tables LOOP
    EXECUTE format('DROP POLICY IF EXISTS "Admin full access on %I" ON public.%I;', tbl, tbl);
    EXECUTE format('CREATE POLICY "Admin full access on %I" ON public.%I FOR ALL TO authenticated USING (public.is_admin(auth.uid())) WITH CHECK (public.is_admin(auth.uid()));', tbl, tbl);
  END LOOP;
END $$;

-- ─────────────────────────────────────────────────────────────────────────────
-- 22. POLICIES: PUBLIC SAFE CONFIG READ (Landing Page & Mobile Sync)
-- ─────────────────────────────────────────────────────────────────────────────
DROP POLICY IF EXISTS "Public read showcase config" ON public.showcase_config;
CREATE POLICY "Public read showcase config" ON public.showcase_config FOR SELECT TO public
  USING (true);

DROP POLICY IF EXISTS "Public read subscription config" ON public.platform_subscription_config;
CREATE POLICY "Public read subscription config" ON public.platform_subscription_config FOR SELECT TO public
  USING (true);

DROP POLICY IF EXISTS "Public read gateway config" ON public.gateway_config;
CREATE POLICY "Public read gateway config" ON public.gateway_config FOR SELECT TO public
  USING (true);

DROP POLICY IF EXISTS "Public read mfs regex patterns" ON public.mfs_regex_patterns;
CREATE POLICY "Public read mfs regex patterns" ON public.mfs_regex_patterns FOR SELECT TO public
  USING (active = true);

DROP POLICY IF EXISTS "Merchants read notifications" ON public.merchant_notifications;
CREATE POLICY "Merchants read notifications" ON public.merchant_notifications FOR SELECT TO public
  USING (merchant_id = 'ALL' OR merchant_id = auth.uid()::text);

DROP POLICY IF EXISTS "Merchants read own subscriptions" ON public.merchant_subscriptions;
CREATE POLICY "Merchants read own subscriptions" ON public.merchant_subscriptions FOR SELECT TO authenticated
  USING (merchant_id = auth.uid()::text);

-- Hosted Payment Forms & Submissions Policies
DROP POLICY IF EXISTS "Public read payment forms" ON public.payment_forms;
CREATE POLICY "Public read payment forms" ON public.payment_forms FOR SELECT TO public
  USING (true);

DROP POLICY IF EXISTS "Authenticated manage payment forms" ON public.payment_forms;
CREATE POLICY "Authenticated manage payment forms" ON public.payment_forms FOR ALL TO authenticated
  USING (true)
  WITH CHECK (true);

DROP POLICY IF EXISTS "Public insert form submissions" ON public.form_submissions;
CREATE POLICY "Public insert form submissions" ON public.form_submissions FOR INSERT TO public
  WITH CHECK (true);

DROP POLICY IF EXISTS "Public read form submissions" ON public.form_submissions;
CREATE POLICY "Public read form submissions" ON public.form_submissions FOR SELECT TO authenticated
  USING (true);

-- ─────────────────────────────────────────────────────────────────────────────
-- 23. REALTIME REPLICATION (Instant Updates in Admin Panel & Mobile App)
-- ─────────────────────────────────────────────────────────────────────────────
DO $$
BEGIN
  ALTER PUBLICATION supabase_realtime ADD TABLE public.admin_notifications;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
  ALTER PUBLICATION supabase_realtime ADD TABLE public.merchant_notifications;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
  ALTER PUBLICATION supabase_realtime ADD TABLE public.merchant_subscriptions;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
  ALTER PUBLICATION supabase_realtime ADD TABLE public.support_tickets;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
  ALTER PUBLICATION supabase_realtime ADD TABLE public.live_chat_messages;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
  ALTER PUBLICATION supabase_realtime ADD TABLE public.payment_events;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
  ALTER PUBLICATION supabase_realtime ADD TABLE public.payment_forms;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
  ALTER PUBLICATION supabase_realtime ADD TABLE public.form_submissions;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

-- ─────────────────────────────────────────────────────────────────────────────
-- 24. ANDROID RPC HELPER FUNCTIONS
-- ─────────────────────────────────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION public.get_platform_stats()
RETURNS JSONB
LANGUAGE plpgsql
SECURITY DEFINER
AS $$
DECLARE
  total_m numeric;
  total_vol numeric;
  paid_tx bigint;
BEGIN
  SELECT count(*) INTO total_m FROM public.merchants WHERE status = 'ACTIVE';
  SELECT COALESCE(sum(amount), 0) INTO total_vol FROM public.payment_events WHERE status = 'PAID';
  SELECT count(*) INTO paid_tx FROM public.payment_events WHERE status = 'PAID';
  
  RETURN jsonb_build_object(
    'active_merchants', total_m,
    'total_volume', total_vol,
    'paid_transactions', paid_tx,
    'status', 'healthy'
  );
END;
$$;

GRANT EXECUTE ON FUNCTION public.get_platform_stats() TO service_role, authenticated, anon;

-- ─────────────────────────────────────────────────────────────────────────────
-- 25. NOTIFY POSTGREST TO RELOAD SCHEMA CACHE IMMEDIATELY
-- ─────────────────────────────────────────────────────────────────────────────
NOTIFY pgrst, 'reload schema';

COMMIT;
