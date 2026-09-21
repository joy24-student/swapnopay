-- ==============================================================================
-- SWAPNOPAY PLATFORM ADMIN — COMPLETE DATABASE SCHEMA & REPAIR SCRIPT
-- Project: tldubojeokgyoclxnzkb.supabase.co
-- Run this directly in the Supabase SQL Editor.
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- 1. FIX AUTH.USERS CORRUPTION (Fixes "500: Database error querying schema")
-- ------------------------------------------------------------------------------
-- GoTrue crashes when string columns in auth.users are NULL instead of empty strings.
-- Note: 'confirmed_at' is a generated column in modern Supabase, so only update email_confirmed_at.
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

-- ------------------------------------------------------------------------------
-- 2. ADMIN USERS TABLE & SUPER ADMIN REGISTRATION
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
  id          UUID PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
  email       TEXT NOT NULL UNIQUE,
  role        TEXT NOT NULL DEFAULT 'admin' CHECK (role IN ('super_admin','admin','viewer')),
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Seed existing auth user (admin@swapnopay.top) as super_admin
INSERT INTO admin_users (id, email, role)
SELECT id, email, 'super_admin'
FROM auth.users
WHERE email = 'admin@swapnopay.top'
ON CONFLICT (id) DO UPDATE SET role = 'super_admin';

-- ------------------------------------------------------------------------------
-- 3. GATEWAY CONFIGURATION TABLE (SINGLETON)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS gateway_config (
  id                          UUID PRIMARY KEY DEFAULT '00000000-0000-0000-0000-000000000001',
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
  updated_by                  UUID REFERENCES auth.users(id)
);

-- Seed singleton gateway config row
INSERT INTO gateway_config (
  id,
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

-- ------------------------------------------------------------------------------
-- 4. PLATFORM API KEYS TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS platform_api_keys (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id     TEXT NOT NULL,
  merchant_name   TEXT NOT NULL,
  label           TEXT NOT NULL,
  key_preview     TEXT NOT NULL,
  secret_hash     TEXT NOT NULL,
  revoked         BOOLEAN NOT NULL DEFAULT false,
  revoked_at      TIMESTAMPTZ,
  revoked_by      UUID REFERENCES auth.users(id),
  created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
  created_by      UUID REFERENCES auth.users(id)
);
CREATE INDEX IF NOT EXISTS platform_api_keys_merchant_idx ON platform_api_keys(merchant_id);

-- ------------------------------------------------------------------------------
-- 5. PAYMENT EVENTS (PLATFORM AUDIT / ANALYTICS)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payment_events (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  event_type      TEXT NOT NULL,
  order_id        TEXT NOT NULL,
  merchant_id     TEXT NOT NULL,
  amount          NUMERIC NOT NULL,
  method          TEXT NOT NULL,
  trx_id          TEXT,
  sender_number   TEXT,
  status          TEXT NOT NULL,
  failure_reason  TEXT,
  metadata        JSONB DEFAULT '{}'::jsonb,
  recorded_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS payment_events_merchant_idx ON payment_events(merchant_id, recorded_at DESC);
CREATE INDEX IF NOT EXISTS payment_events_order_idx ON payment_events(order_id);
CREATE INDEX IF NOT EXISTS payment_events_status_idx ON payment_events(status, recorded_at DESC);

-- ------------------------------------------------------------------------------
-- 6. SHOWCASE CONFIG & RADYMATE GALLERY
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS showcase_config (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  key         TEXT UNIQUE NOT NULL,
  value       JSONB NOT NULL,
  updated_at  TIMESTAMPTZ DEFAULT now()
);

-- Seed default showcase & system config
INSERT INTO showcase_config (key, value)
VALUES 
(
  'system_config',
  '{
    "developer_portal_url": "https://pay.swapnopay.top/portal.html",
    "developer_docs_url": "https://pay.swapnopay.top/docs.html",
    "api_portal_url": "https://pay.swapnopay.top/portal.html#credentials",
    "webhook_docs_url": "https://pay.swapnopay.top/docs.html#webhooks",
    "support_hotline": "+8801700000000",
    "support_email": "admin@swapnopay.top",
    "support_whatsapp": "+8801700000000",
    "video_tutorials": []
  }'::jsonb
),
(
  'radymate_gallery',
  '{
    "items": [
      {
        "title": "Launch Dashboard",
        "caption": "Storefront overview and order performance",
        "image": "https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1200&q=80",
        "source": "fallback"
      },
      {
        "title": "Premium Storefront",
        "caption": "Professional eCommerce front-end showcase",
        "image": "https://images.unsplash.com/photo-1556740749-887f6717d7e4?auto=format&fit=crop&w=1200&q=80",
        "source": "fallback"
      },
      {
        "title": "Checkout Flow",
        "caption": "Fast and trusted conversion experience",
        "image": "https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1200&q=80",
        "source": "fallback"
      },
      {
        "title": "Merchant Console",
        "caption": "Insights and analytics dashboard for growth",
        "image": "https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1200&q=80",
        "source": "fallback"
      }
    ]
  }'::jsonb
)
ON CONFLICT (key) DO NOTHING;

-- ------------------------------------------------------------------------------
-- 7. MFS REGEX PATTERNS TABLE (SMS PARSING)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mfs_regex_patterns (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  mfs_name      TEXT NOT NULL CHECK (mfs_name IN ('bKash','Nagad','Rocket','Upay')),
  pattern_name  TEXT NOT NULL,
  regex_pattern TEXT NOT NULL,
  description   TEXT,
  active        BOOLEAN NOT NULL DEFAULT true,
  created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Seed default regex patterns
INSERT INTO mfs_regex_patterns (id, mfs_name, pattern_name, regex_pattern, description)
VALUES
  ('11111111-1111-1111-1111-111111111111', 'bKash', 'bKash Cash In / Payment Received', 'You have received Tk ([0-9,.]+) from ([0-9]+)\. TrxID ([A-Z0-9]+)', 'Standard bKash notification SMS'),
  ('22222222-2222-2222-2222-222222222222', 'Nagad', 'Nagad Payment Received', 'Received Tk ([0-9,.]+) from ([0-9]+)\. TxnID: ([A-Z0-9]+)', 'Standard Nagad notification SMS'),
  ('33333333-3333-3333-3333-333333333333', 'Rocket', 'Rocket Cash In', 'Tk([0-9,.]+) received from ([0-9]+)\. Ref: ([A-Z0-9]+)', 'Standard DBBL Rocket notification SMS'),
  ('44444444-4444-4444-4444-444444444444', 'Upay', 'Upay Payment Received', 'Received Tk ([0-9,.]+) from ([0-9]+)\. TxnID ([A-Z0-9]+)', 'Standard Upay notification SMS')
ON CONFLICT (id) DO NOTHING;

-- ------------------------------------------------------------------------------
-- 8. MERCHANT CONNECTIONS & GATEWAY SETTINGS
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS merchant_connections (
  id                   UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id          TEXT NOT NULL UNIQUE,
  supabase_project_url TEXT,
  supabase_anon_key    TEXT,
  supabase_service_key TEXT,
  created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at           TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS merchant_gateway_settings (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id      TEXT NOT NULL UNIQUE,
  enabled_methods  JSONB DEFAULT '{"bKash":true,"Nagad":true,"Rocket":true,"Upay":true}'::jsonb,
  min_amount       NUMERIC DEFAULT 10,
  max_amount       NUMERIC DEFAULT 500000,
  fee_bearer       TEXT DEFAULT 'MERCHANT',
  updated_at       TIMESTAMPTZ DEFAULT now()
);

-- ------------------------------------------------------------------------------
-- 9. ADMIN NOTIFICATIONS (REALTIME TOASTS)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_notifications (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  title       TEXT NOT NULL,
  message     TEXT NOT NULL,
  type        TEXT NOT NULL CHECK (type IN ('PAYMENT_EVENT', 'TICKET_CREATED', 'SYSTEM_ALERT')),
  read        BOOLEAN NOT NULL DEFAULT false,
  metadata    JSONB DEFAULT '{}'::jsonb,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS admin_notifications_read_idx ON admin_notifications(read, created_at DESC);

-- ------------------------------------------------------------------------------
-- 10. SUPPORT HELPDESK (TICKETS, FEATURE REQUESTS, LIVE CHAT)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS support_tickets (
  id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id    TEXT,
  business_name  TEXT,
  email          TEXT,
  phone          TEXT,
  category       TEXT NOT NULL,
  subject        TEXT NOT NULL,
  description    TEXT NOT NULL,
  status         TEXT NOT NULL DEFAULT 'OPEN' CHECK (status IN ('OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED')),
  admin_reply    TEXT,
  resolved_at    TIMESTAMPTZ,
  created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS support_tickets_status_idx ON support_tickets(status, created_at DESC);

CREATE TABLE IF NOT EXISTS feature_requests (
  id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id    TEXT,
  business_name  TEXT,
  email          TEXT,
  phone          TEXT,
  title          TEXT NOT NULL,
  category       TEXT NOT NULL,
  description    TEXT NOT NULL,
  priority       TEXT NOT NULL DEFAULT 'MEDIUM' CHECK (priority IN ('LOW', 'MEDIUM', 'HIGH', 'CRITICAL')),
  status         TEXT NOT NULL DEFAULT 'PENDING' CHECK (status IN ('PENDING', 'UNDER_REVIEW', 'PLANNED', 'IN_PROGRESS', 'COMPLETED', 'REJECTED')),
  admin_notes    TEXT,
  created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS live_chat_messages (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id  TEXT NOT NULL,
  sender       TEXT NOT NULL CHECK (sender IN ('MERCHANT', 'PLATFORM_OWNER', 'AI_SUPPORT')),
  message      TEXT NOT NULL,
  created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS live_chat_messages_merchant_idx ON live_chat_messages(merchant_id, created_at ASC);

-- ------------------------------------------------------------------------------
-- 11. WEBHOOK SECRETS & AUDIT LOGS & DEVICE ALERTS
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS webhook_secrets (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name        TEXT NOT NULL UNIQUE,
  secret_hash TEXT NOT NULL,
  active      BOOLEAN NOT NULL DEFAULT true,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
  rotated_at  TIMESTAMPTZ
);

CREATE TABLE IF NOT EXISTS audit_logs (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  actor_email TEXT,
  action      TEXT NOT NULL,
  details     JSONB DEFAULT '{}'::jsonb,
  ip_address  TEXT,
  user_agent  TEXT,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS audit_logs_action_idx ON audit_logs(action, created_at DESC);

CREATE TABLE IF NOT EXISTS gateway_device_alerts (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id     TEXT NOT NULL,
  customer_email  TEXT NOT NULL,
  order_id        TEXT,
  amount          NUMERIC,
  checkout_url    TEXT NOT NULL,
  status          TEXT NOT NULL DEFAULT 'PENDING' CHECK (status IN ('PENDING', 'NOTIFIED', 'CANCELLED')),
  notified_at     TIMESTAMPTZ,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ------------------------------------------------------------------------------
-- 12. STORAGE BUCKET FOR RADYMATE GALLERY
-- ------------------------------------------------------------------------------
INSERT INTO storage.buckets (id, name, public)
VALUES ('radymate-gallery', 'radymate-gallery', true)
ON CONFLICT (id) DO NOTHING;

-- ------------------------------------------------------------------------------
-- 13. ENABLE ROW LEVEL SECURITY (RLS) ON ALL TABLES
-- ------------------------------------------------------------------------------
ALTER TABLE admin_users ENABLE ROW LEVEL SECURITY;
ALTER TABLE gateway_config ENABLE ROW LEVEL SECURITY;
ALTER TABLE platform_api_keys ENABLE ROW LEVEL SECURITY;
ALTER TABLE payment_events ENABLE ROW LEVEL SECURITY;
ALTER TABLE showcase_config ENABLE ROW LEVEL SECURITY;
ALTER TABLE mfs_regex_patterns ENABLE ROW LEVEL SECURITY;
ALTER TABLE merchant_connections ENABLE ROW LEVEL SECURITY;
ALTER TABLE merchant_gateway_settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE admin_notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE support_tickets ENABLE ROW LEVEL SECURITY;
ALTER TABLE feature_requests ENABLE ROW LEVEL SECURITY;
ALTER TABLE live_chat_messages ENABLE ROW LEVEL SECURITY;
ALTER TABLE webhook_secrets ENABLE ROW LEVEL SECURITY;
ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE gateway_device_alerts ENABLE ROW LEVEL SECURITY;

-- ------------------------------------------------------------------------------
-- 14. POLICIES: SERVICE ROLE (Full backend access bypass)
-- ------------------------------------------------------------------------------
DO $$
DECLARE
  tbl text;
  tables text[] := ARRAY[
    'admin_users', 'gateway_config', 'platform_api_keys', 'payment_events',
    'showcase_config', 'mfs_regex_patterns', 'merchant_connections',
    'merchant_gateway_settings', 'admin_notifications', 'support_tickets',
    'feature_requests', 'live_chat_messages', 'webhook_secrets', 'audit_logs',
    'gateway_device_alerts'
  ];
BEGIN
  FOREACH tbl IN ARRAY tables LOOP
    EXECUTE format('DROP POLICY IF EXISTS "Service Role full access on %I" ON %I;', tbl, tbl);
    EXECUTE format('CREATE POLICY "Service Role full access on %I" ON %I FOR ALL USING (auth.role() = ''service_role'');', tbl, tbl);
  END LOOP;
END $$;

-- ------------------------------------------------------------------------------
-- 15. POLICIES: PLATFORM ADMIN ACCESS
-- ------------------------------------------------------------------------------
-- Admin users table self-check policy
DROP POLICY IF EXISTS "Authenticated users can read their own admin record" ON admin_users;
CREATE POLICY "Authenticated users can read their own admin record"
  ON admin_users FOR SELECT
  TO authenticated
  USING (id = auth.uid());

DROP POLICY IF EXISTS "Super admins can manage all admin users" ON admin_users;
DROP POLICY IF EXISTS "Super admin manage admins" ON admin_users;
DROP POLICY IF EXISTS "Super admins can manage all admin records" ON admin_users;
CREATE POLICY "Super admins can manage all admin records"
  ON admin_users FOR ALL
  TO authenticated
  USING (public.is_super_admin(auth.uid()))
  WITH CHECK (public.is_super_admin(auth.uid()));

-- Admin policies on all operational tables
DO $$
DECLARE
  tbl text;
  admin_tables text[] := ARRAY[
    'gateway_config', 'platform_api_keys', 'payment_events',
    'showcase_config', 'mfs_regex_patterns', 'merchant_connections',
    'merchant_gateway_settings', 'admin_notifications', 'support_tickets',
    'feature_requests', 'live_chat_messages', 'webhook_secrets', 'audit_logs',
    'gateway_device_alerts'
  ];
BEGIN
  FOREACH tbl IN ARRAY admin_tables LOOP
    EXECUTE format('DROP POLICY IF EXISTS "Admin full access on %I" ON %I;', tbl, tbl);
    EXECUTE format('CREATE POLICY "Admin full access on %I" ON %I FOR ALL TO authenticated USING (public.is_admin(auth.uid())) WITH CHECK (public.is_admin(auth.uid()));', tbl, tbl);
  END LOOP;
END $$;

-- Storage policies for radymate-gallery
DROP POLICY IF EXISTS "Public read radymate-gallery" ON storage.objects;
CREATE POLICY "Public read radymate-gallery"
  ON storage.objects FOR SELECT
  TO public
  USING (bucket_id = 'radymate-gallery');

DROP POLICY IF EXISTS "Admins can upload to radymate-gallery" ON storage.objects;
CREATE POLICY "Admins can upload to radymate-gallery"
  ON storage.objects FOR INSERT
  TO authenticated
  WITH CHECK (
    bucket_id = 'radymate-gallery' AND
    EXISTS (SELECT 1 FROM admin_users WHERE id = auth.uid())
  );

-- ------------------------------------------------------------------------------
-- 16. REALTIME REPLICATION ENABLEMENT
-- ------------------------------------------------------------------------------
DO $$
BEGIN
  ALTER PUBLICATION supabase_realtime ADD TABLE admin_notifications;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
  ALTER PUBLICATION supabase_realtime ADD TABLE support_tickets;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
  ALTER PUBLICATION supabase_realtime ADD TABLE feature_requests;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
  ALTER PUBLICATION supabase_realtime ADD TABLE live_chat_messages;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
