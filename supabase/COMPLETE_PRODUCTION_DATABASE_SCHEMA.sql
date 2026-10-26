-- ============================================================================
-- SWAPNOPAY FULL MASTER PRODUCTION SUPABASE DATABASE SCHEMA
-- Consolidated Migrations 01 through 21 + Storage Buckets + Realtime
-- Fully idempotent: can be run safely multiple times.
-- ============================================================================

-- 0. Enable PostgreSQL Extensions
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- 1. Merchants Table
CREATE TABLE IF NOT EXISTS public.merchants (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES auth.users,
  business_name TEXT NOT NULL DEFAULT 'My Store',
  email TEXT,
  phone TEXT,
  business_type TEXT,
  website TEXT,
  default_number TEXT,
  webhook_secret TEXT NOT NULL DEFAULT encode(gen_random_bytes(32), 'hex'),
  created_at TIMESTAMPTZ DEFAULT now()
);
CREATE UNIQUE INDEX IF NOT EXISTS merchants_user_id_unique ON public.merchants(user_id);
CREATE UNIQUE INDEX IF NOT EXISTS merchants_webhook_secret_unique ON public.merchants(webhook_secret);

-- Helper function: Resolve current authenticated user's merchant ID
CREATE OR REPLACE FUNCTION public.current_merchant_id()
RETURNS UUID LANGUAGE plpgsql STABLE SECURITY DEFINER SET search_path = public AS $$
DECLARE
  v_mid UUID;
  v_hdr TEXT;
BEGIN
  -- 1. Try resolving via auth.uid()
  IF auth.uid() IS NOT NULL THEN
    SELECT id INTO v_mid FROM public.merchants WHERE user_id = auth.uid() LIMIT 1;
    IF v_mid IS NOT NULL THEN
      RETURN v_mid;
    END IF;
  END IF;

  -- 2. Try resolving via custom header 'x-merchant-id' if supplied
  BEGIN
    v_hdr := current_setting('request.headers', true)::json ->> 'x-merchant-id';
    IF v_hdr IS NOT NULL AND v_hdr ~ '^[0-9a-fA-F-]{36}$' THEN
      SELECT id INTO v_mid FROM public.merchants WHERE id = v_hdr::uuid LIMIT 1;
      IF v_mid IS NOT NULL THEN
        RETURN v_mid;
      END IF;
    END IF;
  EXCEPTION WHEN OTHERS THEN
    NULL;
  END;

  -- 3. Fallback: On dedicated tenant database, return primary merchant
  SELECT id INTO v_mid FROM public.merchants ORDER BY created_at ASC LIMIT 1;
  RETURN COALESCE(v_mid, '00000000-0000-0000-0000-000000000001'::uuid);
END;
$$;
GRANT EXECUTE ON FUNCTION public.current_merchant_id() TO authenticated, service_role, anon;

-- Trigger: Automatically provision a merchant profile when a new user signs up in auth.users
CREATE OR REPLACE FUNCTION public.handle_new_merchant_user()
RETURNS TRIGGER LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
BEGIN
  INSERT INTO public.merchants (user_id, business_name, email, phone)
  VALUES (
    NEW.id,
    COALESCE(NEW.raw_user_meta_data ->> 'business_name', 'My Business'),
    NEW.email,
    NULLIF(NEW.raw_user_meta_data ->> 'phone', '')
  )
  ON CONFLICT (user_id) DO NOTHING;
  RETURN NEW;
END;
$$;
DROP TRIGGER IF EXISTS on_auth_user_create_merchant ON auth.users;
CREATE TRIGGER on_auth_user_create_merchant AFTER INSERT ON auth.users
FOR EACH ROW EXECUTE FUNCTION public.handle_new_merchant_user();

-- Seed existing auth.users into merchants if missing
INSERT INTO public.merchants (user_id, business_name, email, phone)
SELECT u.id, COALESCE(u.raw_user_meta_data ->> 'business_name', 'My Business'),
  u.email, NULLIF(u.raw_user_meta_data ->> 'phone', '')
FROM auth.users u
WHERE NOT EXISTS (SELECT 1 FROM public.merchants m WHERE m.user_id = u.id);

-- Ensure a fallback default merchant exists on dedicated instance even without auth.users
INSERT INTO public.merchants (id, business_name, email, phone)
SELECT '00000000-0000-0000-0000-000000000001'::uuid, 'My Store', 'merchant@store.local', '01700000000'
WHERE NOT EXISTS (SELECT 1 FROM public.merchants);

-- 2. Merchant Payment Numbers
CREATE TABLE IF NOT EXISTS public.merchant_numbers (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID REFERENCES public.merchants ON DELETE CASCADE NOT NULL DEFAULT public.current_merchant_id(),
  number TEXT NOT NULL,
  type TEXT NOT NULL CHECK (type IN ('bKash','Nagad','Rocket','Upay')),
  account_type TEXT NOT NULL DEFAULT 'Personal',
  is_default BOOLEAN DEFAULT false,
  active BOOLEAN DEFAULT true,
  created_at TIMESTAMPTZ DEFAULT now()
);
CREATE UNIQUE INDEX IF NOT EXISTS merchant_numbers_merchant_number_unique ON public.merchant_numbers(merchant_id, number);

-- 3. Orders Table
CREATE TABLE IF NOT EXISTS public.orders (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID REFERENCES public.merchants ON DELETE CASCADE NOT NULL DEFAULT public.current_merchant_id(),
  tran_id TEXT NOT NULL,
  amount NUMERIC(12,2) NOT NULL,
  currency TEXT DEFAULT 'BDT',
  cus_phone TEXT NOT NULL,
  cus_email TEXT,
  cus_name TEXT,
  status TEXT NOT NULL DEFAULT 'PENDING' CHECK (status IN ('PENDING','PAID','EXPIRED','CANCELLED')),
  product_name TEXT,
  product_category TEXT,
  callback_url TEXT,
  success_url TEXT,
  fail_url TEXT,
  cancel_url TEXT,
  metadata JSONB DEFAULT '{}',
  expires_at TIMESTAMPTZ NOT NULL,
  paid_at TIMESTAMPTZ,
  payment_method TEXT,
  sender_number TEXT,
  matched_trx_id TEXT,
  manual_match BOOLEAN DEFAULT false,
  order_number TEXT,
  shipping_address TEXT,
  shipping_city TEXT,
  subtotal NUMERIC(12,2) DEFAULT 0.00,
  shipping_cost NUMERIC(12,2) DEFAULT 0.00,
  discount_amount NUMERIC(12,2) DEFAULT 0.00,
  total_amount NUMERIC(12,2) DEFAULT 0.00,
  order_status TEXT DEFAULT 'PENDING' CHECK (order_status IN ('PENDING', 'CONFIRMED', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'CANCELLED')),
  customer_note TEXT,
  created_at TIMESTAMPTZ DEFAULT now()
);
CREATE UNIQUE INDEX IF NOT EXISTS orders_merchant_tran_id_unique ON public.orders(merchant_id, tran_id);
CREATE INDEX IF NOT EXISTS idx_orders_order_number ON public.orders(order_number);

-- Payment Gateway Settings
CREATE TABLE IF NOT EXISTS public.payment_gateway_settings (
  merchant_id UUID PRIMARY KEY REFERENCES public.merchants(id) ON DELETE CASCADE,
  gateway_enabled BOOLEAN NOT NULL DEFAULT true,
  min_amount NUMERIC(12,2) NOT NULL DEFAULT 10.00,
  max_amount NUMERIC(12,2) NOT NULL DEFAULT 100000.00,
  daily_limit NUMERIC(14,2) NOT NULL DEFAULT 500000.00,
  receipt_retry_limit INTEGER NOT NULL DEFAULT 5 CHECK (receipt_retry_limit BETWEEN 1 AND 10),
  auto_receipt_retry BOOLEAN NOT NULL DEFAULT true,
  customer_receipts_enabled BOOLEAN NOT NULL DEFAULT true,
  merchant_receipts_enabled BOOLEAN NOT NULL DEFAULT true,
  merchant_receipt_email TEXT,
  sms_notifications_enabled BOOLEAN NOT NULL DEFAULT false,
  notification_phone TEXT,
  success_callback_url TEXT,
  failure_callback_url TEXT,
  cancel_callback_url TEXT,
  qr_codes JSONB DEFAULT '{}'::jsonb,
  receiving_numbers JSONB DEFAULT '{}'::jsonb,
  merchant_logo_url TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.payment_receipt_outbox (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID NOT NULL REFERENCES public.merchants(id) ON DELETE CASCADE,
  order_id UUID NOT NULL REFERENCES public.orders(id) ON DELETE CASCADE,
  event_type TEXT NOT NULL DEFAULT 'PAYMENT_PAID' CHECK (event_type = 'PAYMENT_PAID'),
  status TEXT NOT NULL DEFAULT 'PENDING' CHECK (status IN ('PENDING','PROCESSING','SENT','FAILED')),
  attempts INTEGER NOT NULL DEFAULT 0 CHECK (attempts BETWEEN 0 AND 20),
  next_attempt_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  claimed_at TIMESTAMPTZ,
  delivered_at TIMESTAMPTZ,
  last_error TEXT,
  provider_response JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE(order_id, event_type)
);
CREATE INDEX IF NOT EXISTS payment_receipt_outbox_dispatch_idx
  ON public.payment_receipt_outbox(status, next_attempt_at, created_at)
  WHERE status IN ('PENDING','PROCESSING','FAILED');

INSERT INTO public.payment_gateway_settings(merchant_id)
SELECT id FROM public.merchants ON CONFLICT(merchant_id) DO NOTHING;

CREATE OR REPLACE FUNCTION public.ensure_payment_gateway_settings()
RETURNS TRIGGER LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
BEGIN
  INSERT INTO public.payment_gateway_settings(merchant_id, merchant_receipt_email)
  VALUES(NEW.id, NULLIF(trim(NEW.email),''))
  ON CONFLICT(merchant_id) DO NOTHING;
  RETURN NEW;
END;
$$;
DROP TRIGGER IF EXISTS ensure_payment_gateway_settings_trigger ON public.merchants;
CREATE TRIGGER ensure_payment_gateway_settings_trigger AFTER INSERT ON public.merchants
FOR EACH ROW EXECUTE FUNCTION public.ensure_payment_gateway_settings();

-- 4. Payments Table
CREATE TABLE IF NOT EXISTS public.payments (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID REFERENCES public.merchants ON DELETE CASCADE NOT NULL DEFAULT public.current_merchant_id(),
  trx_id TEXT,
  amount NUMERIC(12,2) NOT NULL,
  sender_number TEXT,
  merchant_number TEXT,
  sms_timestamp TIMESTAMPTZ,
  sms_hash TEXT UNIQUE NOT NULL,
  status TEXT NOT NULL DEFAULT 'UNMATCHED' CHECK (status IN ('MATCHED','UNMATCHED','DUPLICATE')),
  matched_order_id UUID REFERENCES public.orders,
  created_at TIMESTAMPTZ DEFAULT now()
);

-- 5. SMS Logs Table
CREATE TABLE IF NOT EXISTS public.sms_logs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  device_id TEXT NOT NULL,
  merchant_id UUID REFERENCES public.merchants ON DELETE CASCADE NOT NULL DEFAULT public.current_merchant_id(),
  raw_sms TEXT NOT NULL,
  parsed_amount NUMERIC(12,2),
  parsed_sender TEXT,
  parsed_trx_id TEXT,
  parsed_timestamp TIMESTAMPTZ,
  sms_hash TEXT UNIQUE NOT NULL,
  processed BOOLEAN DEFAULT false,
  status TEXT DEFAULT 'unprocessed' CHECK (status IN ('unprocessed','matched','unmatched','duplicate')),
  created_at TIMESTAMPTZ DEFAULT now()
);

-- 6. Devices Table
CREATE TABLE IF NOT EXISTS public.devices (
  id TEXT PRIMARY KEY,
  user_id UUID REFERENCES auth.users,
  merchant_id UUID REFERENCES public.merchants ON DELETE CASCADE DEFAULT public.current_merchant_id(),
  fcm_token TEXT,
  device_model TEXT,
  os_version TEXT,
  battery_level INTEGER,
  online BOOLEAN DEFAULT true,
  last_sync TIMESTAMPTZ DEFAULT now(),
  disabled BOOLEAN DEFAULT false,
  created_at TIMESTAMPTZ DEFAULT now()
);

-- 7. Appeals Table
CREATE TABLE IF NOT EXISTS public.appeals (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID REFERENCES public.merchants(id),
  trx_id TEXT NOT NULL,
  cus_phone TEXT,
  order_id UUID REFERENCES public.orders,
  note TEXT,
  screenshot_url TEXT,
  status TEXT DEFAULT 'PENDING_REVIEW' CHECK (status IN ('PENDING_REVIEW','APPROVED','REJECTED')),
  resolved_by UUID REFERENCES auth.users,
  resolved_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ DEFAULT now()
);

-- Ensure merchant_id column and performance indices exist if table already created
ALTER TABLE public.appeals ADD COLUMN IF NOT EXISTS merchant_id UUID REFERENCES public.merchants(id);
CREATE INDEX IF NOT EXISTS idx_appeals_merchant_id ON public.appeals(merchant_id);
CREATE INDEX IF NOT EXISTS idx_appeals_order_id ON public.appeals(order_id);
CREATE INDEX IF NOT EXISTS idx_appeals_trx_id ON public.appeals(trx_id);
CREATE INDEX IF NOT EXISTS idx_appeals_status ON public.appeals(status);

-- 8. Notifications Table
CREATE TABLE IF NOT EXISTS public.notifications (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES auth.users,
  type TEXT,
  title TEXT,
  body TEXT,
  read BOOLEAN DEFAULT false,
  created_at TIMESTAMPTZ DEFAULT now()
);

-- Merchant Notifications Table (Durable feed)
CREATE TABLE IF NOT EXISTS public.merchant_notifications (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID NOT NULL DEFAULT public.current_merchant_id() REFERENCES public.merchants(id) ON DELETE CASCADE,
  type TEXT NOT NULL,
  title TEXT NOT NULL,
  message TEXT NOT NULL,
  severity TEXT NOT NULL DEFAULT 'INFO' CHECK (severity IN ('INFO','SUCCESS','WARNING','ERROR')),
  entity_type TEXT,
  entity_id UUID,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  read_at TIMESTAMPTZ
);
CREATE INDEX IF NOT EXISTS merchant_notifications_feed_idx ON public.merchant_notifications(merchant_id, created_at desc);

-- 9. Security Logs & Settings
CREATE TABLE IF NOT EXISTS public.security_logs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES auth.users,
  merchant_id UUID REFERENCES public.merchants ON DELETE CASCADE DEFAULT public.current_merchant_id(),
  event TEXT NOT NULL,
  details JSONB,
  ip_address TEXT,
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.security_settings (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES auth.users ON DELETE CASCADE DEFAULT auth.uid(),
  biometric_enabled BOOLEAN NOT NULL DEFAULT false,
  updated_at BIGINT NOT NULL DEFAULT (extract(epoch from now()) * 1000)::bigint
);

CREATE TABLE IF NOT EXISTS public.order_rate_limits (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  merchant_id UUID NOT NULL REFERENCES public.merchants ON DELETE CASCADE,
  client_hash TEXT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS order_rate_limits_lookup_idx ON public.order_rate_limits(merchant_id, client_hash, created_at desc);

-- 10. MFS Regex Patterns Table
CREATE TABLE IF NOT EXISTS public.mfs_regex_patterns (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  mfs_name TEXT NOT NULL CHECK (mfs_name IN ('bKash','Nagad','Rocket','Upay')),
  pattern_name TEXT NOT NULL,
  regex_pattern TEXT NOT NULL,
  active BOOLEAN DEFAULT true,
  created_at TIMESTAMPTZ DEFAULT now()
);

-- Seed default MFS Regex Patterns
INSERT INTO public.mfs_regex_patterns (mfs_name, pattern_name, regex_pattern) VALUES
  ('bKash', 'traditional', 'You have received Tk (\d+\.?\d*).*?from (\d+).*?TrxID (\w+) at (\d{2}/\d{2}/\d{4} \d{2}:\d{2})'),
  ('bKash', 'cash-in', 'Cash In Tk ([\d,]+\.?\d*).*?from (\d+).*?TrxID (\w+) at (\d{2}/\d{2}/\d{4} \d{2}:\d{2})'),
  ('Nagad', 'received', 'Money Received\..*?Amount:\s*Tk (\d+\.?\d*).*?Sender:\s*(\d+).*?TxnID:\s*(\w+).*?(\d{2}/\d{2}/\d{4} \d{2}:\d{2})'),
  ('Rocket', 'cash-in', 'Cash-In from A/C:\s*\*+\d+\s*Tk([\d,]+\.?\d*)[\s\S]*?TxnId:(\d+)\s+Date:(\d{2}-[A-Z]{3}-\d{2}\s+\d{2}:\d{2}:\d{2}\s*[ap]m)'),
  ('Upay', 'received', '(?:Money Received|Received Taka)[\s\S]*?Taka\s*([\d,]+\.?\d*)[\s\S]*?from\s*(\d+)[\s\S]*?TrxID\s*(\w+)[\s\S]*?(\d{2}/\d{2}/\d{4} \d{2}:\d{2})')
ON CONFLICT DO NOTHING;

-- 11. Payment Forms Table
CREATE TABLE IF NOT EXISTS public.payment_forms (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID REFERENCES public.merchants ON DELETE CASCADE DEFAULT public.current_merchant_id(),
  title TEXT NOT NULL DEFAULT 'Untitled Payment Form',
  description TEXT,
  slug TEXT UNIQUE NOT NULL DEFAULT ('pay-' || replace(gen_random_uuid()::text, '-', '')),
  template_type TEXT NOT NULL DEFAULT 'BLANK',
  fields JSONB NOT NULL DEFAULT '[]'::jsonb,
  products JSONB NOT NULL DEFAULT '[]'::jsonb,
  pages JSONB NOT NULL DEFAULT '[]'::jsonb,
  theme JSONB NOT NULL DEFAULT '{}'::jsonb,
  status TEXT NOT NULL DEFAULT 'DRAFT' CHECK (status IN ('DRAFT', 'PUBLISHED', 'ARCHIVED')),
  views_count INTEGER DEFAULT 0,
  submissions_count INTEGER DEFAULT 0,
  total_revenue NUMERIC(12,2) DEFAULT 0.00,
  logo_url TEXT,
  banner_url TEXT,
  created_at TIMESTAMPTZ DEFAULT now(),
  updated_at TIMESTAMPTZ DEFAULT now()
);

-- 12. Form Submissions Table
CREATE TABLE IF NOT EXISTS public.form_submissions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  form_id UUID REFERENCES public.payment_forms ON DELETE CASCADE,
  order_id UUID REFERENCES public.orders ON DELETE SET NULL,
  request_id UUID NOT NULL DEFAULT gen_random_uuid(),
  client_hash TEXT,
  customer_name TEXT,
  customer_phone TEXT,
  customer_email TEXT,
  amount_bdt NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  payment_method TEXT NOT NULL DEFAULT 'bKash',
  payment_status TEXT NOT NULL DEFAULT 'NOT_REQUIRED' CHECK (payment_status IN ('PAID', 'PENDING', 'FAILED', 'REFUNDED', 'NOT_REQUIRED')),
  trx_id TEXT,
  answers JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ DEFAULT now()
);
CREATE UNIQUE INDEX IF NOT EXISTS form_submissions_form_request_unique ON public.form_submissions(form_id, request_id);

-- 13. Customers Table
CREATE TABLE IF NOT EXISTS public.customers (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID REFERENCES public.merchants ON DELETE CASCADE NOT NULL DEFAULT public.current_merchant_id(),
  name TEXT NOT NULL,
  phone TEXT NOT NULL,
  email TEXT,
  address TEXT,
  opening_balance NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  current_balance NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  status TEXT NOT NULL DEFAULT 'VIP' CHECK (status IN ('VIP', 'Risk', 'Inactive', 'Potential')),
  created_at TIMESTAMPTZ DEFAULT now()
);

-- 14. Suppliers Table
CREATE TABLE IF NOT EXISTS public.suppliers (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID REFERENCES public.merchants ON DELETE CASCADE NOT NULL DEFAULT public.current_merchant_id(),
  name TEXT NOT NULL,
  phone TEXT NOT NULL,
  email TEXT,
  address TEXT,
  opening_balance NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  current_balance NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMPTZ DEFAULT now()
);

-- 15. Ledger Transactions Table
CREATE TABLE IF NOT EXISTS public.ledger_transactions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID REFERENCES public.merchants ON DELETE CASCADE NOT NULL DEFAULT public.current_merchant_id(),
  customer_id UUID REFERENCES public.customers ON DELETE CASCADE,
  supplier_id UUID REFERENCES public.suppliers ON DELETE CASCADE,
  type TEXT NOT NULL CHECK (type IN ('credit', 'payment')),
  amount NUMERIC(12,2) NOT NULL,
  date TIMESTAMPTZ NOT NULL DEFAULT now(),
  note TEXT,
  product_details JSONB DEFAULT '[]',
  is_voice_entry BOOLEAN DEFAULT false,
  attachment_url TEXT,
  payment_method TEXT DEFAULT 'Cash',
  invoice_no TEXT,
  created_at TIMESTAMPTZ DEFAULT now()
);

-- 16. Products Table
CREATE TABLE IF NOT EXISTS public.products (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID REFERENCES public.merchants ON DELETE CASCADE NOT NULL DEFAULT public.current_merchant_id(),
  name TEXT NOT NULL,
  code TEXT,
  category TEXT DEFAULT 'General',
  purchase_price NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  sale_price NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  cost_price NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  asking_price NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  stock_quantity NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  min_stock_threshold NUMERIC(12,2) NOT NULL DEFAULT 5.00,
  unit TEXT NOT NULL DEFAULT 'pcs',
  qr_code TEXT,
  image_url TEXT,
  storefront_details JSONB NOT NULL DEFAULT '{}'::jsonb,
  slug TEXT,
  category_id UUID,
  featured_image TEXT,
  short_description TEXT,
  description TEXT,
  is_featured BOOLEAN DEFAULT false,
  is_active BOOLEAN DEFAULT true,
  total_views BIGINT DEFAULT 0,
  created_at TIMESTAMPTZ DEFAULT now()
);
CREATE UNIQUE INDEX IF NOT EXISTS products_merchant_code_unique ON public.products(merchant_id, lower(code)) WHERE code IS NOT NULL AND code <> '';
CREATE UNIQUE INDEX IF NOT EXISTS products_merchant_qr_unique ON public.products(merchant_id, lower(qr_code)) WHERE qr_code IS NOT NULL AND qr_code <> '';

-- 17. Product Variants Table
CREATE TABLE IF NOT EXISTS public.product_variants (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID REFERENCES public.merchants ON DELETE CASCADE NOT NULL DEFAULT public.current_merchant_id(),
  product_id UUID REFERENCES public.products ON DELETE CASCADE NOT NULL,
  variant_name TEXT NOT NULL DEFAULT 'Standard',
  supplier_id UUID REFERENCES public.suppliers ON DELETE SET NULL,
  qr_code TEXT UNIQUE NOT NULL,
  cost_price NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  asking_price NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  sale_price NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  stock_quantity NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMPTZ DEFAULT now()
);

-- 18. Stock Transactions Table
CREATE TABLE IF NOT EXISTS public.stock_transactions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID REFERENCES public.merchants ON DELETE CASCADE NOT NULL DEFAULT public.current_merchant_id(),
  product_id UUID REFERENCES public.products ON DELETE CASCADE NOT NULL,
  variant_id UUID REFERENCES public.product_variants ON DELETE SET NULL,
  type TEXT NOT NULL CHECK (type IN ('in', 'out')),
  quantity NUMERIC(12,2) NOT NULL,
  price NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  customer_id UUID REFERENCES public.customers ON DELETE SET NULL,
  supplier_id UUID REFERENCES public.suppliers ON DELETE SET NULL,
  reference_note TEXT,
  created_at TIMESTAMPTZ DEFAULT now()
);

-- 19. Expenses Table
CREATE TABLE IF NOT EXISTS public.expenses (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID REFERENCES public.merchants ON DELETE CASCADE NOT NULL DEFAULT public.current_merchant_id(),
  category TEXT NOT NULL CHECK (category IN ('Utilities', 'Rent', 'Transport', 'Operating', 'Payroll', 'Others')),
  amount NUMERIC(12,2) NOT NULL,
  date TIMESTAMPTZ NOT NULL DEFAULT now(),
  description TEXT,
  receipt_image_url TEXT,
  payment_method TEXT DEFAULT 'Cash',
  is_recurring BOOLEAN DEFAULT false,
  created_at TIMESTAMPTZ DEFAULT now()
);

-- 20. Business Loans Table
CREATE TABLE IF NOT EXISTS public.loans (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID REFERENCES public.merchants ON DELETE CASCADE NOT NULL DEFAULT public.current_merchant_id(),
  principal_amount NUMERIC(12,2) NOT NULL,
  interest_rate NUMERIC(5,2) NOT NULL,
  interest_type TEXT NOT NULL CHECK (interest_type IN ('Flat', 'Reducing')),
  duration_months INTEGER NOT NULL,
  monthly_installment NUMERIC(12,2) NOT NULL,
  status TEXT NOT NULL DEFAULT 'applied' CHECK (status IN ('applied', 'approved', 'disbursed', 'repaid')),
  provider_name TEXT NOT NULL DEFAULT '',
  account_reference TEXT NOT NULL DEFAULT '',
  start_date TIMESTAMPTZ,
  applied_at TIMESTAMPTZ DEFAULT now(),
  disbursed_at TIMESTAMPTZ
);
CREATE UNIQUE INDEX IF NOT EXISTS loans_merchant_reference_unique ON public.loans(merchant_id, lower(account_reference)) WHERE account_reference <> '';

-- 21. DPS Accounts & Finance Installments
CREATE TABLE IF NOT EXISTS public.dps_accounts (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID NOT NULL DEFAULT public.current_merchant_id() REFERENCES public.merchants(id) ON DELETE CASCADE,
  provider_name TEXT NOT NULL CHECK (length(trim(provider_name)) BETWEEN 1 AND 160),
  account_reference TEXT NOT NULL CHECK (length(trim(account_reference)) BETWEEN 1 AND 160),
  monthly_deposit NUMERIC(14,2) NOT NULL CHECK (monthly_deposit > 0),
  interest_rate NUMERIC(7,4) NOT NULL DEFAULT 0 CHECK (interest_rate >= 0 AND interest_rate <= 100),
  duration_months INTEGER NOT NULL CHECK (duration_months BETWEEN 1 AND 600),
  start_date TIMESTAMPTZ NOT NULL,
  maturity_date TIMESTAMPTZ NOT NULL,
  status TEXT NOT NULL DEFAULT 'ACTIVE' CHECK (status IN ('ACTIVE','MATURED','CLOSED')),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (merchant_id, account_reference)
);

CREATE TABLE IF NOT EXISTS public.finance_installments (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID NOT NULL DEFAULT public.current_merchant_id() REFERENCES public.merchants(id) ON DELETE CASCADE,
  account_type TEXT NOT NULL CHECK (account_type IN ('DPS','LOAN')),
  account_id UUID NOT NULL,
  installment_number INTEGER NOT NULL CHECK (installment_number > 0),
  due_date TIMESTAMPTZ NOT NULL,
  principal_amount NUMERIC(14,2) NOT NULL CHECK (principal_amount >= 0),
  interest_amount NUMERIC(14,2) NOT NULL DEFAULT 0 CHECK (interest_amount >= 0),
  total_amount NUMERIC(14,2) NOT NULL CHECK (total_amount > 0),
  status TEXT NOT NULL DEFAULT 'PENDING' CHECK (status IN ('PENDING','PAID','OVERDUE','WAIVED')),
  paid_at TIMESTAMPTZ,
  payment_method TEXT,
  payment_reference TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (merchant_id, account_type, account_id, installment_number)
);
CREATE UNIQUE INDEX IF NOT EXISTS finance_installment_payment_reference_unique
  ON public.finance_installments(merchant_id, payment_reference)
  WHERE payment_reference IS NOT NULL AND payment_reference <> '';
CREATE INDEX IF NOT EXISTS finance_installments_due_idx
  ON public.finance_installments(merchant_id, status, due_date);

-- 22. POS Sales Invoices Table
CREATE TABLE IF NOT EXISTS public.pos_sales (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID REFERENCES public.merchants ON DELETE CASCADE NOT NULL DEFAULT public.current_merchant_id(),
  invoice_no TEXT NOT NULL,
  customer_id UUID REFERENCES public.customers ON DELETE SET NULL,
  customer_name TEXT NOT NULL DEFAULT 'Walk-in Customer',
  customer_phone TEXT DEFAULT '',
  subtotal NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  discount NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  net_total NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  cash_received NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  change_due NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  payment_method TEXT NOT NULL DEFAULT 'Cash' CHECK (payment_method IN ('Cash', 'bKash', 'Nagad', 'Rocket', 'Upay', 'MFS', 'Card', 'Bank', 'Due')),
  payment_status TEXT NOT NULL DEFAULT 'PAID' CHECK (payment_status IN ('PAID', 'PARTIAL', 'DUE')),
  item_count INTEGER NOT NULL DEFAULT 1,
  cart_items JSONB DEFAULT '[]',
  timestamp TIMESTAMPTZ DEFAULT now(),
  created_at TIMESTAMPTZ DEFAULT now()
);

-- 23. Business Analytics Summary Table
CREATE TABLE IF NOT EXISTS public.business_analytics (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID REFERENCES public.merchants ON DELETE CASCADE NOT NULL DEFAULT public.current_merchant_id(),
  total_revenue NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  cash_received NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  total_dues NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  total_payables NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  total_expenses NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  net_profit NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  last_updated TIMESTAMPTZ DEFAULT now()
);

-- 24. Employees Table
CREATE TABLE IF NOT EXISTS public.employees (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID NOT NULL DEFAULT public.current_merchant_id() REFERENCES public.merchants ON DELETE CASCADE,
  name TEXT NOT NULL,
  designation TEXT NOT NULL,
  role TEXT NOT NULL,
  email TEXT NOT NULL,
  phone TEXT NOT NULL,
  department TEXT NOT NULL DEFAULT 'General',
  status TEXT NOT NULL DEFAULT 'Active' CHECK (status IN ('Active','Inactive')),
  avatar_url TEXT,
  permissions JSONB NOT NULL DEFAULT '[]'::jsonb,
  joined_date DATE NOT NULL DEFAULT current_date,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS employees_merchant_id_idx ON public.employees(merchant_id);

-- 25. Storefront & Web Shop Tables
CREATE TABLE IF NOT EXISTS public.store_settings (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID REFERENCES public.merchants(id) ON DELETE CASCADE NOT NULL,
  store_name TEXT NOT NULL DEFAULT 'My Online Store',
  store_slug TEXT UNIQUE NOT NULL,
  tagline TEXT,
  logo_url TEXT,
  favicon_url TEXT,
  banner_url TEXT,
  currency_code TEXT NOT NULL DEFAULT 'BDT',
  currency_symbol TEXT NOT NULL DEFAULT '৳',
  contact_email TEXT,
  contact_phone TEXT,
  address TEXT,
  meta_title TEXT,
  meta_description TEXT,
  meta_keywords TEXT,
  facebook_url TEXT,
  instagram_url TEXT,
  whatsapp_number TEXT,
  cod_enabled BOOLEAN NOT NULL DEFAULT true,
  bkash_enabled BOOLEAN NOT NULL DEFAULT true,
  nagad_enabled BOOLEAN NOT NULL DEFAULT true,
  rocket_enabled BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_store_settings_slug ON public.store_settings(store_slug);

CREATE TABLE IF NOT EXISTS public.categories (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID REFERENCES public.merchants(id) ON DELETE CASCADE NOT NULL,
  parent_id UUID REFERENCES public.categories(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  slug TEXT NOT NULL,
  photo_url TEXT,
  display_order INT NOT NULL DEFAULT 0,
  is_active BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE(merchant_id, slug)
);

CREATE TABLE IF NOT EXISTS public.product_photos (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  product_id UUID REFERENCES public.products(id) ON DELETE CASCADE NOT NULL,
  photo_url TEXT NOT NULL,
  display_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.order_items (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id UUID REFERENCES public.orders(id) ON DELETE CASCADE NOT NULL,
  product_id UUID REFERENCES public.products(id) ON DELETE SET NULL,
  variant_id UUID REFERENCES public.product_variants(id) ON DELETE SET NULL,
  product_name TEXT NOT NULL,
  size TEXT,
  color TEXT,
  quantity INT NOT NULL DEFAULT 1,
  unit_price NUMERIC(12,2) NOT NULL,
  total_price NUMERIC(12,2) NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.customer_carts (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID REFERENCES public.merchants(id) ON DELETE CASCADE NOT NULL,
  session_id TEXT NOT NULL,
  customer_id UUID REFERENCES public.customers(id) ON DELETE CASCADE,
  product_id UUID REFERENCES public.products(id) ON DELETE CASCADE NOT NULL,
  variant_id UUID REFERENCES public.product_variants(id) ON DELETE SET NULL,
  quantity INT NOT NULL DEFAULT 1,
  unit_price NUMERIC(12,2) NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.coupons (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID REFERENCES public.merchants(id) ON DELETE CASCADE NOT NULL,
  code TEXT NOT NULL,
  discount_type TEXT NOT NULL CHECK (discount_type IN ('percentage', 'fixed')),
  discount_value NUMERIC(12,2) NOT NULL,
  minimum_order NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  usage_limit INT NOT NULL DEFAULT 0,
  used_count INT NOT NULL DEFAULT 0,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE(merchant_id, code)
);

CREATE TABLE IF NOT EXISTS public.shipping_methods (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  merchant_id UUID REFERENCES public.merchants(id) ON DELETE CASCADE NOT NULL,
  name TEXT NOT NULL,
  cost NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  estimated_delivery_days TEXT NOT NULL DEFAULT '2-3 Days',
  is_active BOOLEAN NOT NULL DEFAULT true
);

CREATE TABLE IF NOT EXISTS public.product_reviews (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  product_id UUID REFERENCES public.products(id) ON DELETE CASCADE NOT NULL,
  customer_name TEXT NOT NULL,
  rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  review_text TEXT,
  is_approved BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ============================================================================
-- STORED PROCEDURES & ATOMIC SECURITY FUNCTIONS
-- ============================================================================

-- Atomic POS Checkout
CREATE OR REPLACE FUNCTION public.checkout_pos_atomic(p_sale jsonb, p_items jsonb)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
DECLARE
  v_merchant uuid := coalesce(nullif(p_sale->>'merchant_id','')::uuid, public.current_merchant_id());
  v_sale uuid := (p_sale->>'id')::uuid;
  v_item jsonb; v_product uuid; v_variant uuid; v_qty numeric; v_price numeric;
  v_customer uuid := nullif(p_sale->>'customer_id','')::uuid;
BEGIN
  IF v_merchant IS NULL OR v_sale IS NULL OR jsonb_typeof(p_items)<>'array' OR jsonb_array_length(p_items)=0 THEN
    RAISE EXCEPTION 'Authenticated merchant, sale and items required';
  END IF;
  PERFORM pg_advisory_xact_lock(hashtextextended(v_merchant::text || ':pos:' || v_sale::text,0));
  IF EXISTS(SELECT 1 FROM public.pos_sales WHERE id=v_sale AND merchant_id=v_merchant) THEN
    RETURN jsonb_build_object('id', v_sale, 'idempotent', true);
  END IF;
  FOR v_item IN SELECT value FROM jsonb_array_elements(p_items) LOOP
    v_product := (v_item->>'product_id')::uuid; v_variant := nullif(v_item->>'variant_id','')::uuid;
    v_qty := (v_item->>'quantity')::numeric; v_price := (v_item->>'unit_price')::numeric;
    IF v_qty<=0 OR v_price<0 THEN RAISE EXCEPTION 'Invalid cart quantity or price'; END IF;
    IF v_variant IS NOT NULL THEN
      UPDATE public.product_variants SET stock_quantity=stock_quantity-v_qty
        WHERE id=v_variant AND product_id=v_product AND merchant_id=v_merchant AND stock_quantity>=v_qty;
      IF NOT FOUND THEN RAISE EXCEPTION 'Insufficient variant stock'; END IF;
    END IF;
    UPDATE public.products SET stock_quantity=stock_quantity-v_qty
      WHERE id=v_product AND merchant_id=v_merchant AND stock_quantity>=v_qty;
    IF NOT FOUND THEN RAISE EXCEPTION 'Insufficient product stock'; END IF;
    INSERT INTO public.stock_transactions(merchant_id,product_id,variant_id,type,quantity,price,customer_id,reference_note)
      VALUES(v_merchant,v_product,v_variant,'out',v_qty,v_price,v_customer,'POS '||(p_sale->>'invoice_no'));
  END LOOP;
  INSERT INTO public.pos_sales(id,merchant_id,invoice_no,customer_id,customer_name,customer_phone,subtotal,
    discount,net_total,cash_received,change_due,payment_method,payment_status,item_count,cart_items,"timestamp")
  VALUES(v_sale,v_merchant,p_sale->>'invoice_no',v_customer,coalesce(p_sale->>'customer_name','Walk-in Customer'),
    coalesce(p_sale->>'customer_phone',''),(p_sale->>'subtotal')::numeric,(p_sale->>'discount')::numeric,
    (p_sale->>'net_total')::numeric,(p_sale->>'cash_received')::numeric,(p_sale->>'change_due')::numeric,
    p_sale->>'payment_method',p_sale->>'payment_status',(p_sale->>'item_count')::integer,
    coalesce(p_sale->'cart_items',p_items),now());
  IF p_sale->>'payment_status'='DUE' THEN
    IF v_customer IS NULL THEN RAISE EXCEPTION 'A due sale requires a customer'; END IF;
    INSERT INTO public.ledger_transactions(merchant_id,customer_id,type,amount,note,product_details,payment_method,invoice_no)
      VALUES(v_merchant,v_customer,'credit',(p_sale->>'net_total')::numeric,'POS credit sale '||(p_sale->>'invoice_no'),
        coalesce(p_sale->'cart_items',p_items),'Due',p_sale->>'invoice_no');
    UPDATE public.customers SET current_balance=current_balance+(p_sale->>'net_total')::numeric
      WHERE id=v_customer AND merchant_id=v_merchant;
    IF NOT FOUND THEN RAISE EXCEPTION 'Customer does not belong to merchant'; END IF;
  END IF;
  RETURN jsonb_build_object('id', v_sale, 'invoice_no', p_sale->>'invoice_no');
END;
$$;

-- Atomic Stock-In Product
CREATE OR REPLACE FUNCTION public.stock_in_product_atomic(p_product jsonb, p_variants jsonb DEFAULT '[]'::jsonb)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
DECLARE
  v_merchant uuid := coalesce(nullif(p_product->>'merchant_id','')::uuid, public.current_merchant_id());
  v_product uuid := (p_product->>'id')::uuid;
  v_variant jsonb; v_variant_id uuid; v_qty numeric;
  v_opening numeric := coalesce((p_product->>'opening_quantity')::numeric,0);
BEGIN
  IF v_merchant IS NULL OR v_product IS NULL THEN RAISE EXCEPTION 'Authenticated merchant and product required'; END IF;
  IF jsonb_typeof(p_variants) <> 'array' THEN RAISE EXCEPTION 'Variants must be an array'; END IF;
  PERFORM pg_advisory_xact_lock(hashtextextended(v_merchant::text || ':product:' || v_product::text,0));
  IF EXISTS(SELECT 1 FROM public.products WHERE id=v_product AND merchant_id=v_merchant) THEN
    RETURN jsonb_build_object('id', v_product, 'idempotent', true);
  END IF;
  IF coalesce((p_product->>'purchase_price')::numeric,0) < 0 OR coalesce((p_product->>'sale_price')::numeric,0) < 0 OR v_opening < 0 THEN
    RAISE EXCEPTION 'Prices and quantity cannot be negative';
  END IF;
  INSERT INTO public.products(id,merchant_id,name,code,category,purchase_price,sale_price,stock_quantity,
    unit,qr_code,cost_price,asking_price,image_url,storefront_details)
  VALUES(v_product,v_merchant,trim(p_product->>'name'),nullif(trim(p_product->>'code'),''),
    coalesce(nullif(trim(p_product->>'category'),''),'General'),coalesce((p_product->>'purchase_price')::numeric,0),
    coalesce((p_product->>'sale_price')::numeric,0),0,coalesce(nullif(trim(p_product->>'unit'),''),'pcs'),
    nullif(trim(p_product->>'qr_code'),''),coalesce((p_product->>'cost_price')::numeric,0),
    coalesce((p_product->>'asking_price')::numeric,0),nullif(p_product->>'image_url',''),coalesce(p_product->'storefront_details','{}'::jsonb));

  IF jsonb_array_length(p_variants)=0 AND v_opening > 0 THEN
    UPDATE public.products SET stock_quantity=v_opening WHERE id=v_product AND merchant_id=v_merchant;
    INSERT INTO public.stock_transactions(merchant_id,product_id,type,quantity,price,reference_note)
      VALUES(v_merchant,v_product,'in',v_opening,coalesce((p_product->>'purchase_price')::numeric,0),'Opening stock');
  END IF;
  FOR v_variant IN SELECT value FROM jsonb_array_elements(p_variants) LOOP
    v_variant_id := (v_variant->>'id')::uuid;
    v_qty := (v_variant->>'quantity')::numeric;
    IF v_qty <= 0 OR coalesce((v_variant->>'cost_price')::numeric,0)<0 OR coalesce((v_variant->>'sale_price')::numeric,0)<0 THEN
      RAISE EXCEPTION 'Invalid variant quantity or price';
    END IF;
    INSERT INTO public.product_variants(id,merchant_id,product_id,variant_name,supplier_id,qr_code,
      cost_price,asking_price,sale_price,stock_quantity)
    VALUES(v_variant_id,v_merchant,v_product,trim(v_variant->>'variant_name'),nullif(v_variant->>'supplier_id','')::uuid,
      trim(v_variant->>'qr_code'),coalesce((v_variant->>'cost_price')::numeric,0),
      coalesce((v_variant->>'asking_price')::numeric,0),coalesce((v_variant->>'sale_price')::numeric,0),v_qty);
    UPDATE public.products SET stock_quantity=stock_quantity+v_qty WHERE id=v_product AND merchant_id=v_merchant;
    INSERT INTO public.stock_transactions(merchant_id,product_id,variant_id,type,quantity,price,supplier_id,reference_note)
      VALUES(v_merchant,v_product,v_variant_id,'in',v_qty,coalesce((v_variant->>'cost_price')::numeric,0),
        nullif(v_variant->>'supplier_id','')::uuid,'Opening stock');
  END LOOP;
  RETURN jsonb_build_object('id', v_product);
END;
$$;

-- Atomic Update Product
CREATE OR REPLACE FUNCTION public.update_product_atomic(p_product jsonb, p_adjustment jsonb DEFAULT NULL)
RETURNS uuid LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
DECLARE
  v_merchant uuid := public.current_merchant_id();
  v_product uuid := (p_product->>'id')::uuid;
  v_current numeric;
  v_expected numeric := (p_product->>'expected_stock')::numeric;
  v_target numeric := (p_product->>'target_stock')::numeric;
  v_adjustment_id uuid; v_adjustment_type text; v_adjustment_quantity numeric;
BEGIN
  IF v_merchant IS NULL OR v_product IS NULL THEN RAISE EXCEPTION 'Authenticated merchant and product required'; END IF;
  IF nullif(trim(coalesce(p_product->>'name','')),'') IS NULL OR nullif(trim(coalesce(p_product->>'unit','')),'') IS NULL THEN
    RAISE EXCEPTION 'Product name and unit are required';
  END IF;
  IF coalesce((p_product->>'purchase_price')::numeric,-1) < 0 OR
     coalesce((p_product->>'sale_price')::numeric,-1) < 0 OR v_expected < 0 OR v_target < 0 THEN
    RAISE EXCEPTION 'Prices and stock cannot be negative';
  END IF;
  PERFORM pg_advisory_xact_lock(hashtextextended(v_merchant::text || ':product:' || v_product::text,0));
  SELECT stock_quantity INTO v_current FROM public.products
    WHERE id=v_product AND merchant_id=v_merchant FOR UPDATE;
  IF NOT FOUND THEN RAISE EXCEPTION 'Product does not belong to merchant'; END IF;

  UPDATE public.products SET
    name=trim(p_product->>'name'),
    code=nullif(trim(p_product->>'code'),''),
    qr_code=nullif(trim(p_product->>'qr_code'),''),
    category=coalesce(nullif(trim(p_product->>'category'),''),'General'),
    purchase_price=(p_product->>'purchase_price')::numeric,
    sale_price=(p_product->>'sale_price')::numeric,
    cost_price=coalesce((p_product->>'cost_price')::numeric,(p_product->>'purchase_price')::numeric),
    asking_price=coalesce((p_product->>'asking_price')::numeric,(p_product->>'sale_price')::numeric),
    unit=trim(p_product->>'unit'),
    image_url=CASE WHEN p_product ? 'image_url' THEN nullif(p_product->>'image_url','') ELSE image_url END,
    storefront_details=coalesce(p_product->'storefront_details',storefront_details)
    WHERE id=v_product AND merchant_id=v_merchant;

  IF p_adjustment IS NULL OR jsonb_typeof(p_adjustment)='null' THEN
    IF v_target <> v_current THEN RAISE EXCEPTION 'A stock adjustment is required when quantity changes'; END IF;
    RETURN v_product;
  END IF;
  IF EXISTS(SELECT 1 FROM public.product_variants WHERE product_id=v_product AND merchant_id=v_merchant) THEN
    RAISE EXCEPTION 'Variant product stock must be adjusted through variants';
  END IF;
  v_adjustment_id := (p_adjustment->>'id')::uuid;
  IF EXISTS(SELECT 1 FROM public.stock_transactions WHERE id=v_adjustment_id AND merchant_id=v_merchant) THEN
    IF v_current <> v_target THEN RAISE EXCEPTION 'Stock adjustment was already applied but target stock differs'; END IF;
    RETURN v_product;
  END IF;
  IF v_current <> v_expected THEN RAISE EXCEPTION 'Stock changed on another device; refresh and retry'; END IF;
  v_adjustment_type := p_adjustment->>'type';
  v_adjustment_quantity := (p_adjustment->>'quantity')::numeric;
  IF v_adjustment_quantity <= 0 OR
     (v_adjustment_type='in' AND v_target-v_current <> v_adjustment_quantity) OR
     (v_adjustment_type='out' AND v_current-v_target <> v_adjustment_quantity) OR
     v_adjustment_type NOT IN ('in','out') THEN
    RAISE EXCEPTION 'Invalid stock adjustment';
  END IF;
  UPDATE public.products SET stock_quantity=v_target WHERE id=v_product AND merchant_id=v_merchant;
  INSERT INTO public.stock_transactions(id,merchant_id,product_id,type,quantity,price,reference_note)
    VALUES(v_adjustment_id,v_merchant,v_product,v_adjustment_type,v_adjustment_quantity,
      coalesce((p_adjustment->>'price')::numeric,0),coalesce(nullif(p_adjustment->>'reference_note',''),'Manual stock adjustment'));
  RETURN v_product;
END;
$$;

-- Atomic Adjust Inventory
CREATE OR REPLACE FUNCTION public.adjust_inventory_atomic(p_movement jsonb)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
DECLARE
  v_merchant uuid := public.current_merchant_id();
  v_id uuid := (p_movement->>'id')::uuid;
  v_product uuid := (p_movement->>'product_id')::uuid;
  v_variant uuid := nullif(p_movement->>'variant_id','')::uuid;
  v_type text := lower(p_movement->>'type');
  v_quantity numeric := (p_movement->>'quantity')::numeric;
  v_delta numeric;
BEGIN
  IF v_merchant IS NULL OR v_id IS NULL OR v_product IS NULL THEN
    RAISE EXCEPTION 'Authenticated merchant, movement and product are required';
  END IF;
  IF v_type NOT IN ('in','out') OR v_quantity IS NULL OR v_quantity <= 0 THEN
    RAISE EXCEPTION 'Inventory movement type and positive quantity are required';
  END IF;
  IF coalesce((p_movement->>'price')::numeric,0) < 0 THEN RAISE EXCEPTION 'Inventory price cannot be negative'; END IF;
  PERFORM pg_advisory_xact_lock(hashtextextended(v_merchant::text || ':stock:' || v_id::text,0));
  IF EXISTS(SELECT 1 FROM public.stock_transactions WHERE id=v_id AND merchant_id=v_merchant) THEN
    RETURN jsonb_build_object('id', v_id, 'idempotent', true);
  END IF;
  v_delta := CASE WHEN v_type='in' THEN v_quantity ELSE -v_quantity END;
  IF v_variant IS NOT NULL THEN
    UPDATE public.product_variants SET stock_quantity=stock_quantity+v_delta
      WHERE id=v_variant AND product_id=v_product AND merchant_id=v_merchant AND stock_quantity+v_delta>=0;
    IF NOT FOUND THEN RAISE EXCEPTION 'Variant is missing or has insufficient stock'; END IF;
  END IF;
  UPDATE public.products SET stock_quantity=stock_quantity+v_delta
    WHERE id=v_product AND merchant_id=v_merchant AND stock_quantity+v_delta>=0;
  IF NOT FOUND THEN RAISE EXCEPTION 'Product is missing or has insufficient stock'; END IF;
  INSERT INTO public.stock_transactions(id,merchant_id,product_id,variant_id,type,quantity,price,customer_id,supplier_id,reference_note)
    VALUES(v_id,v_merchant,v_product,v_variant,v_type,v_quantity,coalesce((p_movement->>'price')::numeric,0),
      nullif(p_movement->>'customer_id','')::uuid,nullif(p_movement->>'supplier_id','')::uuid,
      nullif(p_movement->>'reference_note',''));
  RETURN jsonb_build_object('id', v_id, 'stock_delta', v_delta);
END;
$$;

-- Atomic Record Ledger Transaction
CREATE OR REPLACE FUNCTION public.record_ledger_transaction_atomic(p_transaction jsonb)
RETURNS uuid LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
DECLARE
  v_merchant uuid := public.current_merchant_id();
  v_id uuid := (p_transaction->>'id')::uuid;
  v_customer uuid := nullif(p_transaction->>'customer_id','')::uuid;
  v_supplier uuid := nullif(p_transaction->>'supplier_id','')::uuid;
  v_type text := lower(p_transaction->>'type');
  v_amount numeric := (p_transaction->>'amount')::numeric;
  v_delta numeric;
BEGIN
  IF v_merchant IS NULL OR v_id IS NULL THEN RAISE EXCEPTION 'Authenticated merchant and transaction ID required'; END IF;
  IF (v_customer IS NULL) = (v_supplier IS NULL) THEN RAISE EXCEPTION 'Choose exactly one ledger party'; END IF;
  IF v_type NOT IN ('credit','payment') OR v_amount <= 0 THEN RAISE EXCEPTION 'Invalid ledger type or amount'; END IF;
  PERFORM pg_advisory_xact_lock(hashtextextended(v_merchant::text||':ledger:'||v_id::text,0));
  IF EXISTS(SELECT 1 FROM public.ledger_transactions WHERE id=v_id AND merchant_id=v_merchant) THEN RETURN v_id; END IF;
  INSERT INTO public.ledger_transactions(id,merchant_id,customer_id,supplier_id,type,amount,date,note,
    product_details,is_voice_entry,payment_method,invoice_no)
  VALUES(v_id,v_merchant,v_customer,v_supplier,v_type,v_amount,
    coalesce((p_transaction->>'date')::timestamptz,now()),nullif(p_transaction->>'note',''),
    coalesce(p_transaction->'product_details','[]'::jsonb),coalesce((p_transaction->>'is_voice_entry')::boolean,false),
    coalesce(nullif(p_transaction->>'payment_method',''),'Cash'),nullif(p_transaction->>'invoice_no',''));
  IF v_customer IS NOT NULL THEN
    v_delta := CASE WHEN v_type='credit' THEN v_amount ELSE -v_amount END;
    UPDATE public.customers SET current_balance=current_balance+v_delta WHERE id=v_customer AND merchant_id=v_merchant;
  ELSE
    v_delta := CASE WHEN v_type='credit' THEN -v_amount ELSE v_amount END;
    UPDATE public.suppliers SET current_balance=current_balance+v_delta WHERE id=v_supplier AND merchant_id=v_merchant;
  END IF;
  IF NOT FOUND THEN RAISE EXCEPTION 'Ledger party does not belong to merchant'; END IF;
  RETURN v_id;
END;
$$;

-- Atomic Match Payment
CREATE OR REPLACE FUNCTION public.match_payment_atomic(
  p_order_id uuid,
  p_payment_amount numeric,
  p_sender_number text,
  p_trx_id text,
  p_sms_hash text,
  p_sms_log_id uuid
) RETURNS void LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
DECLARE
  v_merchant_id uuid;
  v_sender_digits text;
BEGIN
  v_sender_digits := regexp_replace(coalesce(p_sender_number,''), '[^0-9]', '', 'g');
  IF length(v_sender_digits) < 10 THEN RAISE EXCEPTION 'Full sender number is required for automatic matching'; END IF;
  v_sender_digits := right(v_sender_digits,10);
  SELECT o.merchant_id INTO v_merchant_id
  FROM public.orders o
  JOIN public.sms_logs s ON s.id = p_sms_log_id AND s.merchant_id = o.merchant_id
  WHERE o.id = p_order_id AND o.status = 'PENDING' AND o.expires_at >= now()
    AND o.amount = p_payment_amount
    AND right(regexp_replace(o.cus_phone,'[^0-9]','','g'),10) = v_sender_digits
    AND s.processed = false AND s.sms_hash = p_sms_hash
    AND s.parsed_amount = p_payment_amount AND s.parsed_trx_id = p_trx_id
    AND right(regexp_replace(s.parsed_sender,'[^0-9]','','g'),10) = v_sender_digits
  FOR UPDATE OF o, s;

  IF NOT FOUND THEN RAISE EXCEPTION 'Order or SMS log is no longer eligible for matching'; END IF;

  PERFORM pg_advisory_xact_lock(hashtextextended(v_merchant_id::text || ':' || p_trx_id, 0));
  IF EXISTS(SELECT 1 FROM public.payments WHERE merchant_id=v_merchant_id AND trx_id=p_trx_id) THEN
    RAISE EXCEPTION 'Provider transaction ID was already processed';
  END IF;
  UPDATE public.orders SET status = 'PAID', paid_at = now(), sender_number = p_sender_number,
    matched_trx_id = p_trx_id WHERE id = p_order_id;
  INSERT INTO public.payments (merchant_id, trx_id, amount, sender_number, sms_hash, status, matched_order_id)
  VALUES (v_merchant_id, p_trx_id, p_payment_amount, p_sender_number, p_sms_hash, 'MATCHED', p_order_id);
  UPDATE public.sms_logs SET processed = true, status = 'matched' WHERE id = p_sms_log_id;
END;
$$;

-- Atomic Resolve Appeal
CREATE OR REPLACE FUNCTION public.resolve_appeal_atomic(
  p_appeal_id uuid, p_action text, p_order_id uuid, p_resolved_by uuid
) RETURNS void LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
DECLARE
  v_merchant_id uuid;
  v_appeal public.appeals%rowtype;
  v_order public.orders%rowtype;
BEGIN
  IF p_action NOT IN ('APPROVED', 'REJECTED') THEN RAISE EXCEPTION 'Invalid appeal action'; END IF;
  SELECT id INTO v_merchant_id FROM public.merchants WHERE user_id = p_resolved_by;
  IF v_merchant_id IS NULL THEN RAISE EXCEPTION 'Merchant not found'; END IF;
  SELECT * INTO v_appeal FROM public.appeals
    WHERE id = p_appeal_id AND order_id = p_order_id AND status = 'PENDING_REVIEW' FOR UPDATE;
  IF NOT FOUND THEN RAISE EXCEPTION 'Appeal is not pending for this order'; END IF;
  SELECT * INTO v_order FROM public.orders
    WHERE id = p_order_id AND merchant_id = v_merchant_id FOR UPDATE;
  IF NOT FOUND THEN RAISE EXCEPTION 'Order does not belong to merchant'; END IF;
  IF p_action = 'APPROVED' AND v_order.status <> 'PENDING' THEN
    RAISE EXCEPTION 'Only a pending order can be approved';
  END IF;
  UPDATE public.appeals SET status = p_action, resolved_at = now(), resolved_by = p_resolved_by
    WHERE id = p_appeal_id;
  IF p_action = 'APPROVED' THEN
    UPDATE public.orders SET status = 'PAID', paid_at = now(),
      sender_number = coalesce(v_appeal.cus_phone, v_order.cus_phone),
      matched_trx_id = v_appeal.trx_id, manual_match = true WHERE id = p_order_id;
    UPDATE public.payments SET status = 'MATCHED', matched_order_id = p_order_id
      WHERE merchant_id = v_merchant_id AND trx_id = v_appeal.trx_id;
    IF NOT FOUND THEN
      INSERT INTO public.payments
        (merchant_id, trx_id, amount, sender_number, sms_timestamp, sms_hash, status, matched_order_id)
      VALUES (v_merchant_id, v_appeal.trx_id, v_order.amount,
        coalesce(v_appeal.cus_phone, v_order.cus_phone), now(),
        encode(digest('appeal:' || p_appeal_id::text, 'sha256'), 'hex'), 'MATCHED', p_order_id);
    END IF;
  END IF;
END;
$$;

-- Grant execution rights on RPCs
GRANT EXECUTE ON FUNCTION public.checkout_pos_atomic(jsonb, jsonb) TO authenticated, service_role, anon;
GRANT EXECUTE ON FUNCTION public.stock_in_product_atomic(jsonb, jsonb) TO authenticated, service_role, anon;
GRANT EXECUTE ON FUNCTION public.update_product_atomic(jsonb, jsonb) TO authenticated, service_role, anon;
GRANT EXECUTE ON FUNCTION public.adjust_inventory_atomic(jsonb) TO authenticated, service_role, anon;
GRANT EXECUTE ON FUNCTION public.record_ledger_transaction_atomic(jsonb) TO authenticated, service_role, anon;
GRANT EXECUTE ON FUNCTION public.match_payment_atomic(uuid, numeric, text, text, text, uuid) TO authenticated, service_role, anon;
GRANT EXECUTE ON FUNCTION public.resolve_appeal_atomic(uuid, text, uuid, uuid) TO authenticated, service_role, anon;

-- ============================================================================
-- ROW LEVEL SECURITY (RLS) POLICIES
-- ============================================================================

ALTER TABLE public.merchants ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.merchant_numbers ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.orders ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.payments ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.sms_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.devices ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.appeals ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.merchant_notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.security_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.security_settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.order_rate_limits ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.mfs_regex_patterns ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.payment_forms ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.form_submissions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.customers ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.suppliers ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.ledger_transactions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.products ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.product_variants ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.stock_transactions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.expenses ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.loans ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.dps_accounts ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.finance_installments ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.employees ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.pos_sales ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.business_analytics ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.store_settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.categories ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.product_photos ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.order_items ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.customer_carts ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.coupons ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.shipping_methods ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.product_reviews ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.payment_gateway_settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.payment_receipt_outbox ENABLE ROW LEVEL SECURITY;

-- Dynamic Policies
DROP POLICY IF EXISTS "Merchants policy" ON public.merchants;
CREATE POLICY "Merchants policy" ON public.merchants FOR ALL TO authenticated, anon USING (user_id = auth.uid() OR id = public.current_merchant_id() OR auth.uid() IS NULL) WITH CHECK (user_id = auth.uid() OR id = public.current_merchant_id() OR auth.uid() IS NULL);

DROP POLICY IF EXISTS "Merchant numbers policy" ON public.merchant_numbers;
DROP POLICY IF EXISTS "Merchant numbers read policy" ON public.merchant_numbers;
DROP POLICY IF EXISTS "Merchant numbers access" ON public.merchant_numbers;
CREATE POLICY "Merchant numbers policy" ON public.merchant_numbers FOR ALL TO authenticated, anon USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Orders policy" ON public.orders;
CREATE POLICY "Orders policy" ON public.orders FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

DROP POLICY IF EXISTS "Payments policy" ON public.payments;
CREATE POLICY "Payments policy" ON public.payments FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

DROP POLICY IF EXISTS "SMS logs policy" ON public.sms_logs;
DROP POLICY IF EXISTS "prod_sms_logs_insert" ON public.sms_logs;
DROP POLICY IF EXISTS "SMS logs insert by device" ON public.sms_logs;
CREATE POLICY "SMS logs policy" ON public.sms_logs FOR ALL TO authenticated, anon USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Devices policy" ON public.devices;
DROP POLICY IF EXISTS "prod_devices_own" ON public.devices;
CREATE POLICY "Devices policy" ON public.devices FOR ALL TO authenticated, anon USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Appeals policy" ON public.appeals;
CREATE POLICY "Appeals policy" ON public.appeals FOR ALL TO authenticated, anon 
  USING (
    merchant_id = public.current_merchant_id() 
    OR EXISTS (SELECT 1 FROM public.orders o WHERE o.id = appeals.order_id AND o.merchant_id = public.current_merchant_id())
    OR auth.uid() IS NULL
  )
  WITH CHECK (
    merchant_id = public.current_merchant_id() 
    OR EXISTS (SELECT 1 FROM public.orders o WHERE o.id = appeals.order_id AND o.merchant_id = public.current_merchant_id())
    OR auth.uid() IS NULL
  );

DROP POLICY IF EXISTS "Notifications policy" ON public.notifications;
CREATE POLICY "Notifications policy" ON public.notifications FOR ALL TO authenticated, anon USING (user_id = auth.uid() OR auth.uid() IS NULL) WITH CHECK (user_id = auth.uid() OR auth.uid() IS NULL);

DROP POLICY IF EXISTS "Merchant notifications policy" ON public.merchant_notifications;
CREATE POLICY "Merchant notifications policy" ON public.merchant_notifications FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

DROP POLICY IF EXISTS "Security logs policy" ON public.security_logs;
CREATE POLICY "Security logs policy" ON public.security_logs FOR ALL TO authenticated, anon USING (user_id = auth.uid() OR merchant_id = public.current_merchant_id() OR auth.uid() IS NULL) WITH CHECK (merchant_id = public.current_merchant_id() OR auth.uid() IS NULL);

DROP POLICY IF EXISTS "MFS regex patterns policy" ON public.mfs_regex_patterns;
CREATE POLICY "MFS regex patterns policy" ON public.mfs_regex_patterns FOR SELECT TO anon, authenticated USING (active = true);

DROP POLICY IF EXISTS "Payment forms policy" ON public.payment_forms;
CREATE POLICY "Payment forms policy" ON public.payment_forms FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

DROP POLICY IF EXISTS "Published payment forms public read" ON public.payment_forms;
CREATE POLICY "Published payment forms public read" ON public.payment_forms FOR SELECT TO anon USING (status = 'PUBLISHED');

DROP POLICY IF EXISTS "Form submissions policy" ON public.form_submissions;
CREATE POLICY "Form submissions policy" ON public.form_submissions FOR ALL TO authenticated, anon USING (EXISTS (SELECT 1 FROM public.payment_forms f WHERE f.id = form_submissions.form_id AND f.merchant_id = public.current_merchant_id())) WITH CHECK (EXISTS (SELECT 1 FROM public.payment_forms f WHERE f.id = form_submissions.form_id AND f.merchant_id = public.current_merchant_id()) OR true);

DROP POLICY IF EXISTS "Customers policy" ON public.customers;
CREATE POLICY "Customers policy" ON public.customers FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

DROP POLICY IF EXISTS "Suppliers policy" ON public.suppliers;
CREATE POLICY "Suppliers policy" ON public.suppliers FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

DROP POLICY IF EXISTS "Ledger policy" ON public.ledger_transactions;
CREATE POLICY "Ledger policy" ON public.ledger_transactions FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

DROP POLICY IF EXISTS "Products policy" ON public.products;
CREATE POLICY "Products policy" ON public.products FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

DROP POLICY IF EXISTS "Products public read" ON public.products;
CREATE POLICY "Products public read" ON public.products FOR SELECT TO anon USING (is_active = true);

DROP POLICY IF EXISTS "Product variants policy" ON public.product_variants;
CREATE POLICY "Product variants policy" ON public.product_variants FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

DROP POLICY IF EXISTS "Product variants public read" ON public.product_variants;
CREATE POLICY "Product variants public read" ON public.product_variants FOR SELECT TO anon USING (true);

DROP POLICY IF EXISTS "Stock transactions policy" ON public.stock_transactions;
CREATE POLICY "Stock transactions policy" ON public.stock_transactions FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

DROP POLICY IF EXISTS "Expenses policy" ON public.expenses;
CREATE POLICY "Expenses policy" ON public.expenses FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

DROP POLICY IF EXISTS "Loans policy" ON public.loans;
CREATE POLICY "Loans policy" ON public.loans FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

DROP POLICY IF EXISTS "DPS accounts policy" ON public.dps_accounts;
CREATE POLICY "DPS accounts policy" ON public.dps_accounts FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

DROP POLICY IF EXISTS "Finance installments policy" ON public.finance_installments;
CREATE POLICY "Finance installments policy" ON public.finance_installments FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

DROP POLICY IF EXISTS "Employees policy" ON public.employees;
CREATE POLICY "Employees policy" ON public.employees FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

DROP POLICY IF EXISTS "POS sales policy" ON public.pos_sales;
CREATE POLICY "POS sales policy" ON public.pos_sales FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

DROP POLICY IF EXISTS "Analytics policy" ON public.business_analytics;
CREATE POLICY "Analytics policy" ON public.business_analytics FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

DROP POLICY IF EXISTS "Security settings policy" ON public.security_settings;
CREATE POLICY "Security settings policy" ON public.security_settings FOR ALL TO authenticated, anon USING (user_id = auth.uid() OR auth.uid() IS NULL) WITH CHECK (user_id = auth.uid() OR auth.uid() IS NULL);

DROP POLICY IF EXISTS "Payment gateway settings policy" ON public.payment_gateway_settings;
CREATE POLICY "Payment gateway settings policy" ON public.payment_gateway_settings FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

DROP POLICY IF EXISTS "Payment receipt outbox policy" ON public.payment_receipt_outbox;
CREATE POLICY "Payment receipt outbox policy" ON public.payment_receipt_outbox FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

-- Storefront Policies
DROP POLICY IF EXISTS "Storefront settings read" ON public.store_settings;
CREATE POLICY "Storefront settings read" ON public.store_settings FOR SELECT USING (true);
DROP POLICY IF EXISTS "Storefront settings manage" ON public.store_settings;
CREATE POLICY "Storefront settings manage" ON public.store_settings FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

DROP POLICY IF EXISTS "Storefront categories read" ON public.categories;
CREATE POLICY "Storefront categories read" ON public.categories FOR SELECT USING (is_active = true);
DROP POLICY IF EXISTS "Storefront categories manage" ON public.categories;
CREATE POLICY "Storefront categories manage" ON public.categories FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

DROP POLICY IF EXISTS "Storefront photos read" ON public.product_photos;
CREATE POLICY "Storefront photos read" ON public.product_photos FOR SELECT USING (true);
DROP POLICY IF EXISTS "Storefront photos manage" ON public.product_photos;
CREATE POLICY "Storefront photos manage" ON public.product_photos FOR ALL TO authenticated, anon USING (EXISTS (SELECT 1 FROM public.products p WHERE p.id = product_photos.product_id AND p.merchant_id = public.current_merchant_id()));

DROP POLICY IF EXISTS "Storefront shipping read" ON public.shipping_methods;
CREATE POLICY "Storefront shipping read" ON public.shipping_methods FOR SELECT USING (is_active = true);
DROP POLICY IF EXISTS "Storefront shipping manage" ON public.shipping_methods;
CREATE POLICY "Storefront shipping manage" ON public.shipping_methods FOR ALL TO authenticated, anon USING (merchant_id = public.current_merchant_id()) WITH CHECK (merchant_id = public.current_merchant_id());

DROP POLICY IF EXISTS "Storefront reviews read" ON public.product_reviews;
CREATE POLICY "Storefront reviews read" ON public.product_reviews FOR SELECT USING (is_approved = true);

DROP POLICY IF EXISTS "Storefront carts manage" ON public.customer_carts;
CREATE POLICY "Storefront carts manage" ON public.customer_carts FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Storefront order items manage" ON public.order_items;
CREATE POLICY "Storefront order items manage" ON public.order_items FOR ALL USING (true) WITH CHECK (true);

-- ============================================================================
-- STORAGE BUCKETS SETUP
-- ============================================================================

INSERT INTO storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
VALUES 
  ('avatars', 'avatars', true, 5242880, ARRAY['image/jpeg', 'image/png', 'image/webp', 'image/gif']),
  ('receipts', 'receipts', true, 10485760, ARRAY['image/jpeg', 'image/png', 'image/webp', 'application/pdf']),
  ('storefront', 'storefront', true, 10485760, ARRAY['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml']),
  ('products', 'products', true, 10485760, ARRAY['image/jpeg', 'image/png', 'image/webp', 'image/gif']),
  ('attachments', 'attachments', true, 10485760, NULL),
  ('merchant-qr-codes', 'merchant-qr-codes', true, 2097152, ARRAY['image/png', 'image/jpeg', 'image/jpg', 'image/webp']),
  ('appeal-screenshots', 'appeal-screenshots', false, 5242880, ARRAY['image/jpeg', 'image/png', 'image/webp', 'application/pdf']),
  ('form-uploads', 'form-uploads', false, 10485760, NULL)
ON CONFLICT (id) DO UPDATE SET 
  public = EXCLUDED.public,
  file_size_limit = EXCLUDED.file_size_limit;

-- Enable RLS on storage.objects safely (already enabled by default in Supabase)
DO $$
BEGIN
  ALTER TABLE storage.objects ENABLE ROW LEVEL SECURITY;
EXCEPTION WHEN OTHERS THEN
  NULL;
END $$;

-- Storage public read policies
DO $$
BEGIN
  DROP POLICY IF EXISTS "Public Access Avatars" ON storage.objects;
  CREATE POLICY "Public Access Avatars" ON storage.objects FOR SELECT USING (bucket_id = 'avatars');
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
  DROP POLICY IF EXISTS "Public Access Receipts" ON storage.objects;
  CREATE POLICY "Public Access Receipts" ON storage.objects FOR SELECT USING (bucket_id = 'receipts');
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
  DROP POLICY IF EXISTS "Public Access Storefront" ON storage.objects;
  CREATE POLICY "Public Access Storefront" ON storage.objects FOR SELECT USING (bucket_id = 'storefront');
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
  DROP POLICY IF EXISTS "Public Access Products" ON storage.objects;
  CREATE POLICY "Public Access Products" ON storage.objects FOR SELECT USING (bucket_id = 'products');
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
  DROP POLICY IF EXISTS "Public Access QR Codes" ON storage.objects;
  CREATE POLICY "Public Access QR Codes" ON storage.objects FOR SELECT USING (bucket_id = 'merchant-qr-codes');
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
  DROP POLICY IF EXISTS "Public Access Attachments" ON storage.objects;
  CREATE POLICY "Public Access Attachments" ON storage.objects FOR SELECT USING (bucket_id = 'attachments');
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

-- Storage authenticated / service role write policies
DO $$
BEGIN
  DROP POLICY IF EXISTS "Authenticated Upload Objects" ON storage.objects;
  CREATE POLICY "Authenticated Upload Objects" ON storage.objects FOR INSERT TO authenticated, anon WITH CHECK (true);
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
  DROP POLICY IF EXISTS "Authenticated Update Objects" ON storage.objects;
  CREATE POLICY "Authenticated Update Objects" ON storage.objects FOR UPDATE TO authenticated, anon USING (true);
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
  DROP POLICY IF EXISTS "Authenticated Delete Objects" ON storage.objects;
  CREATE POLICY "Authenticated Delete Objects" ON storage.objects FOR DELETE TO authenticated, anon USING (true);
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
  DROP POLICY IF EXISTS "Service Role Manage Objects" ON storage.objects;
  CREATE POLICY "Service Role Manage Objects" ON storage.objects FOR ALL TO service_role USING (true);
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

-- ============================================================================
-- REALTIME PUBLICATION CONFIGURATION
-- ============================================================================

DO $$
DECLARE
  tbl text;
  tables_to_add text[] := ARRAY[
    'merchants', 'merchant_numbers', 'orders', 'payments', 'sms_logs', 'devices',
    'appeals', 'notifications', 'merchant_notifications', 'mfs_regex_patterns',
    'payment_forms', 'form_submissions', 'customers', 'suppliers', 'ledger_transactions',
    'products', 'product_variants', 'stock_transactions', 'expenses', 'loans',
    'pos_sales', 'business_analytics', 'employees', 'store_settings', 'categories',
    'order_items', 'customer_carts', 'dps_accounts', 'finance_installments'
  ];
BEGIN
  BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_publication WHERE pubname = 'supabase_realtime') THEN
      CREATE PUBLICATION supabase_realtime;
    END IF;
  EXCEPTION WHEN OTHERS THEN
    NULL;
  END;

  FOREACH tbl IN ARRAY tables_to_add LOOP
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = tbl) THEN
      BEGIN
        IF NOT EXISTS (
          SELECT 1 FROM pg_publication_tables 
          WHERE pubname = 'supabase_realtime' AND schemaname = 'public' AND tablename = tbl
        ) THEN
          EXECUTE format('ALTER PUBLICATION supabase_realtime ADD TABLE public.%I', tbl);
        END IF;
      EXCEPTION WHEN OTHERS THEN
        NULL;
      END;
      BEGIN
        EXECUTE format('ALTER TABLE public.%I REPLICA IDENTITY FULL', tbl);
      EXCEPTION WHEN OTHERS THEN
        NULL;
      END;
    END IF;
  END LOOP;
END $$;

-- ─────────────────────────────────────────────────────────────────────────────
-- AUTO-CONFIRM USERS TRIGGER (PREVENTS LOCALHOST REDIRECTION & SIGN-IN BLOCKS)
-- ─────────────────────────────────────────────────────────────────────────────
DO $$
BEGIN
  CREATE OR REPLACE FUNCTION public.handle_auto_confirm_merchant_user()
  RETURNS TRIGGER AS $func$
  BEGIN
    IF NEW.email_confirmed_at IS NULL THEN
      NEW.email_confirmed_at := NOW();
    END IF;
    IF NEW.confirmed_at IS NULL THEN
      NEW.confirmed_at := NOW();
    END IF;
    RETURN NEW;
  END;
  $func$ LANGUAGE plpgsql SECURITY DEFINER;

  DROP TRIGGER IF EXISTS tr_auto_confirm_merchant_user ON auth.users;
  CREATE TRIGGER tr_auto_confirm_merchant_user
  BEFORE INSERT ON auth.users
  FOR EACH ROW
  EXECUTE FUNCTION public.handle_auto_confirm_merchant_user();
EXCEPTION WHEN OTHERS THEN
  RAISE NOTICE 'Notice: Auto-confirm user trigger skipped: %', SQLERRM;
END $$;

-- ─────────────────────────────────────────────────────────────────────────────
-- SHOWCASE_CONFIG & ADMIN LIVE BANNER REMOTE CONFIGURATION
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS public.showcase_config (
    key TEXT PRIMARY KEY,
    value JSONB NOT NULL DEFAULT '{}'::jsonb,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now())
);

CREATE INDEX IF NOT EXISTS idx_showcase_config_key ON public.showcase_config(key);
ALTER TABLE public.showcase_config ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Public can view showcase_config" ON public.showcase_config;
CREATE POLICY "Public can view showcase_config"
    ON public.showcase_config FOR SELECT TO anon, authenticated USING (true);

DROP POLICY IF EXISTS "Service role and authenticated admins can modify showcase_config" ON public.showcase_config;
CREATE POLICY "Service role and authenticated admins can modify showcase_config"
    ON public.showcase_config FOR ALL TO service_role, authenticated USING (true) WITH CHECK (true);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_publication_tables 
        WHERE pubname = 'supabase_realtime' 
          AND schemaname = 'public' 
          AND tablename = 'showcase_config'
    ) THEN
        ALTER PUBLICATION supabase_realtime ADD TABLE public.showcase_config;
    END IF;
EXCEPTION
    WHEN OTHERS THEN
        NULL;
END $$;

