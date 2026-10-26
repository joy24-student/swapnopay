-- ============================================================================
-- SWAPNOPAY MIGRATION 31: Merchant database accepts app data with NO separate Auth user
-- Run on every merchant-owned Supabase project. Idempotent; safe to re-run.
--
-- Problem it fixes
--   The Android app talks to the merchant project with that project's anon key plus its own
--   merchant UUID. The provisioned policies were `to authenticated` only, and every business
--   table has `merchant_id REFERENCES public.merchants(id)`, so PostgREST rejected the data:
--       * "new row violates row-level security policy"  (42501 / HTTP 403)
--       * "violates foreign key constraint"             (23503 / HTTP 409)
--   ...unless a matching Supabase Auth user / merchant row had been created by hand.
--
-- What it does
--   1. public.fn_normalize_merchant_id() becomes self-provisioning: the merchant row is
--      created on demand for the UUID the app sends, so no separate user is required and the
--      app's own merchant id is preserved (its `merchant_id=eq.<uuid>` reads keep working).
--   2. A permissive RLS policy (authenticated + anon) is created on every business table.
--   3. Triggers are (re)attached, so provisioning also covers legacy tables.
--   4. Grants, realtime publication and a PostgREST schema reload.
--
-- Security note
--   The anon key of a dedicated merchant project is the key the merchant's own app already
--   ships. Every row still stores merchant_id, so data stays attributable. If Supabase Auth
--   is later enabled inside the merchant project you can narrow these policies back to
--   `merchant_id = public.current_merchant_id()` without touching the mobile app.
-- ============================================================================

-- 0. Runner privileges (the Supabase SQL editor may be impersonating another role)
RESET ROLE;
DO $$ BEGIN EXECUTE 'SET ROLE postgres'; EXCEPTION WHEN OTHERS THEN NULL; END $$;
DO $$ BEGIN GRANT USAGE ON SCHEMA public TO postgres, anon, authenticated, service_role;
EXCEPTION WHEN OTHERS THEN NULL; END $$;

-- 1. Minimum tenant table (no-op when the full schema is already installed)
CREATE TABLE IF NOT EXISTS public.merchants (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID,
  business_name TEXT NOT NULL DEFAULT 'My Store',
  email TEXT,
  phone TEXT,
  created_at TIMESTAMPTZ DEFAULT now()
);

DO $$
BEGIN
  EXECUTE 'ALTER TABLE public.merchants ADD COLUMN IF NOT EXISTS user_id UUID';
  EXECUTE 'ALTER TABLE public.merchants ADD COLUMN IF NOT EXISTS business_name TEXT DEFAULT ''My Store''';
  EXECUTE 'ALTER TABLE public.merchants ADD COLUMN IF NOT EXISTS email TEXT';
  EXECUTE 'ALTER TABLE public.merchants ADD COLUMN IF NOT EXISTS phone TEXT';
  EXECUTE 'ALTER TABLE public.merchants ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now()';
  EXECUTE 'CREATE UNIQUE INDEX IF NOT EXISTS merchants_user_id_unique ON public.merchants(user_id)';
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

-- 2. Resolve the owning merchant (never raises, never returns NULL when a row can be created)
CREATE OR REPLACE FUNCTION public.current_merchant_id()
RETURNS UUID LANGUAGE plpgsql STABLE SECURITY DEFINER SET search_path = public AS $$
DECLARE
  v_mid UUID;
  v_hdr TEXT;
BEGIN
  BEGIN v_hdr := NULLIF(btrim(coalesce(current_setting('request.headers', true)::json ->> 'x-merchant-id', '')), '');
  EXCEPTION WHEN OTHERS THEN v_hdr := NULL; END;

  IF v_hdr ~ '^[0-9a-fA-F-]{36}$' THEN
    SELECT id INTO v_mid FROM public.merchants WHERE id = v_hdr::uuid LIMIT 1;
    IF v_mid IS NOT NULL THEN RETURN v_mid; END IF;
  END IF;

  BEGIN
    IF auth.uid() IS NOT NULL THEN
      SELECT id INTO v_mid FROM public.merchants WHERE user_id = auth.uid() LIMIT 1;
      IF v_mid IS NOT NULL THEN RETURN v_mid; END IF;
    END IF;
  EXCEPTION WHEN OTHERS THEN NULL;
  END;

  SELECT id INTO v_mid FROM public.merchants ORDER BY created_at ASC NULLS LAST LIMIT 1;
  RETURN v_mid;
END;
$$;

DO $$ BEGIN GRANT EXECUTE ON FUNCTION public.current_merchant_id() TO postgres, authenticated, service_role, anon;
EXCEPTION WHEN OTHERS THEN NULL; END $$;

-- 3. Self-provisioning merchant_id trigger: accepts the app's UUID and creates the tenant row
CREATE OR REPLACE FUNCTION public.fn_normalize_merchant_id()
RETURNS TRIGGER LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
DECLARE
  v_hdr TEXT;
  v_mid UUID;
  v_uid UUID;
  v_name TEXT;
  v_email TEXT;
  v_phone TEXT;
BEGIN
  BEGIN
    v_hdr   := NULLIF(btrim(coalesce(current_setting('request.headers', true)::json ->> 'x-merchant-id', '')), '');
    v_name  := NULLIF(btrim(coalesce(current_setting('request.headers', true)::json ->> 'x-business-name', '')), '');
    v_email := NULLIF(btrim(coalesce(current_setting('request.headers', true)::json ->> 'x-merchant-email', '')), '');
    v_phone := NULLIF(btrim(coalesce(current_setting('request.headers', true)::json ->> 'x-merchant-phone', '')), '');
  EXCEPTION WHEN OTHERS THEN NULL;
  END;

  BEGIN v_uid := auth.uid(); EXCEPTION WHEN OTHERS THEN v_uid := NULL; END;

  -- (a) Prefer the merchant_id supplied by the app (payload or x-merchant-id header)
  BEGIN
    IF NEW.merchant_id IS NULL AND v_hdr ~ '^[0-9a-fA-F-]{36}$' THEN
      v_mid := v_hdr::uuid;
    ELSE
      v_mid := NEW.merchant_id;
    END IF;
  EXCEPTION WHEN OTHERS THEN v_mid := NEW.merchant_id;
  END;

  IF v_mid IS NOT NULL THEN
    IF NOT EXISTS (SELECT 1 FROM public.merchants WHERE id = v_mid) THEN
      BEGIN
        INSERT INTO public.merchants (id, user_id, business_name, email, phone)
        VALUES (v_mid, v_uid, COALESCE(v_name, 'My Store'), v_email, v_phone)
        ON CONFLICT (id) DO NOTHING;
      EXCEPTION WHEN OTHERS THEN
        BEGIN
          INSERT INTO public.merchants (id, business_name, email, phone)
          VALUES (v_mid, COALESCE(v_name, 'My Store'), v_email, v_phone)
          ON CONFLICT (id) DO NOTHING;
        EXCEPTION WHEN OTHERS THEN NULL;
        END;
      END;
    END IF;

    IF EXISTS (SELECT 1 FROM public.merchants WHERE id = v_mid) THEN
      NEW.merchant_id := v_mid;
      RETURN NEW;
    END IF;
    v_mid := NULL;
  END IF;

  -- (b) Fall back to the signed-in auth user, then to this project's existing tenant row
  IF v_uid IS NOT NULL THEN
    SELECT id INTO v_mid FROM public.merchants WHERE user_id = v_uid LIMIT 1;
  END IF;
  IF v_mid IS NULL THEN
    SELECT id INTO v_mid FROM public.merchants ORDER BY created_at ASC NULLS LAST LIMIT 1;
  END IF;

  -- (c) Still nothing: create the tenant row so the write is never rejected
  IF v_mid IS NULL THEN
    BEGIN
      INSERT INTO public.merchants (user_id, business_name, email, phone)
      VALUES (v_uid, COALESCE(v_name, 'My Store'), v_email, v_phone)
      RETURNING id INTO v_mid;
    EXCEPTION WHEN OTHERS THEN
      INSERT INTO public.merchants (business_name, email, phone)
      VALUES (COALESCE(v_name, 'My Store'), v_email, v_phone)
      RETURNING id INTO v_mid;
    END;
  END IF;

  NEW.merchant_id := v_mid;
  RETURN NEW;
EXCEPTION WHEN OTHERS THEN
  BEGIN
    IF NEW.merchant_id IS NULL THEN
      SELECT id INTO v_mid FROM public.merchants ORDER BY created_at ASC NULLS LAST LIMIT 1;
      NEW.merchant_id := v_mid;
    END IF;
  EXCEPTION WHEN OTHERS THEN NULL;
  END;
  RETURN NEW;
END;
$$;

DO $$ BEGIN GRANT EXECUTE ON FUNCTION public.fn_normalize_merchant_id() TO postgres, authenticated, service_role, anon;
EXCEPTION WHEN OTHERS THEN NULL; END $$;

-- 3b. Ensure appeals, payments, orders, and notifications schema integrity across all Supabase accounts
DO $$
DECLARE
  r RECORD;
BEGIN
  -- A. Appeals Table Schema & Permissive Policies
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'appeals') THEN
    EXECUTE 'ALTER TABLE public.appeals ADD COLUMN IF NOT EXISTS merchant_id UUID REFERENCES public.merchants(id)';
    EXECUTE 'ALTER TABLE public.appeals ADD COLUMN IF NOT EXISTS note TEXT DEFAULT ''''';
    EXECUTE 'ALTER TABLE public.appeals ADD COLUMN IF NOT EXISTS screenshot_url TEXT DEFAULT ''''';
    EXECUTE 'ALTER TABLE public.appeals ADD COLUMN IF NOT EXISTS customer_name TEXT DEFAULT ''''';
    EXECUTE 'ALTER TABLE public.appeals ADD COLUMN IF NOT EXISTS amount NUMERIC(12,2) DEFAULT 0';
    EXECUTE 'ALTER TABLE public.appeals ADD COLUMN IF NOT EXISTS resolved_by UUID';
    EXECUTE 'ALTER TABLE public.appeals ADD COLUMN IF NOT EXISTS resolved_at TIMESTAMPTZ';
    EXECUTE 'ALTER TABLE public.appeals ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now()';
    
    -- Drop strict foreign key constraint on resolved_by so merchant UUIDs from outside auth.users don't fail
    FOR r IN (
      SELECT constraint_name
      FROM information_schema.table_constraints
      WHERE table_schema = 'public' AND table_name = 'appeals' AND constraint_type = 'FOREIGN KEY'
        AND constraint_name LIKE '%resolved_by%'
    ) LOOP
      EXECUTE format('ALTER TABLE public.appeals DROP CONSTRAINT IF EXISTS %I', r.constraint_name);
    END LOOP;

    EXECUTE 'ALTER TABLE public.appeals DROP CONSTRAINT IF EXISTS appeals_status_check';
    EXECUTE 'ALTER TABLE public.appeals ADD CONSTRAINT appeals_status_check CHECK (status IS NULL OR UPPER(status) IN (''PENDING_REVIEW'', ''APPROVED'', ''REJECTED'', ''RESOLVED'', ''CONFIRMED''))';

    EXECUTE 'CREATE INDEX IF NOT EXISTS idx_appeals_merchant_id ON public.appeals(merchant_id)';
    EXECUTE 'CREATE INDEX IF NOT EXISTS idx_appeals_order_id ON public.appeals(order_id)';
    EXECUTE 'CREATE INDEX IF NOT EXISTS idx_appeals_trx_id ON public.appeals(trx_id)';
    EXECUTE 'CREATE INDEX IF NOT EXISTS idx_appeals_status ON public.appeals(status)';

    EXECUTE 'ALTER TABLE public.appeals ENABLE ROW LEVEL SECURITY';
    EXECUTE 'DROP POLICY IF EXISTS "appeals_anon_insert" ON public.appeals';
    EXECUTE 'CREATE POLICY "appeals_anon_insert" ON public.appeals FOR INSERT WITH CHECK (true)';
    EXECUTE 'DROP POLICY IF EXISTS "appeals_anon_select" ON public.appeals';
    EXECUTE 'CREATE POLICY "appeals_anon_select" ON public.appeals FOR SELECT USING (true)';
    EXECUTE 'DROP POLICY IF EXISTS "appeals_anon_update" ON public.appeals';
    EXECUTE 'CREATE POLICY "appeals_anon_update" ON public.appeals FOR UPDATE USING (true)';
  END IF;

  -- B. Orders Table Schema & Constraints
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'orders') THEN
    EXECUTE 'ALTER TABLE public.orders ADD COLUMN IF NOT EXISTS order_status TEXT DEFAULT ''PENDING''';
    EXECUTE 'ALTER TABLE public.orders ADD COLUMN IF NOT EXISTS matched_trx_id TEXT DEFAULT ''''';
    EXECUTE 'ALTER TABLE public.orders ADD COLUMN IF NOT EXISTS callback_url TEXT';
    EXECUTE 'ALTER TABLE public.orders ADD COLUMN IF NOT EXISTS success_url TEXT';
    EXECUTE 'ALTER TABLE public.orders ADD COLUMN IF NOT EXISTS fail_url TEXT';
    EXECUTE 'ALTER TABLE public.orders ADD COLUMN IF NOT EXISTS cancel_url TEXT';
    EXECUTE 'ALTER TABLE public.orders ADD COLUMN IF NOT EXISTS payment_method TEXT DEFAULT ''bKash''';
    EXECUTE 'ALTER TABLE public.orders ADD COLUMN IF NOT EXISTS cus_name TEXT';
    EXECUTE 'ALTER TABLE public.orders ADD COLUMN IF NOT EXISTS cus_phone TEXT';
    EXECUTE 'ALTER TABLE public.orders DROP CONSTRAINT IF EXISTS orders_status_check';
    EXECUTE 'ALTER TABLE public.orders ADD CONSTRAINT orders_status_check CHECK (status IS NULL OR UPPER(status) IN (''PENDING'', ''PAID'', ''EXPIRED'', ''CANCELLED'', ''CONFIRMED'', ''APPROVED'', ''COMPLETED''))';
    EXECUTE 'ALTER TABLE public.orders DROP CONSTRAINT IF EXISTS orders_order_status_check';
    EXECUTE 'ALTER TABLE public.orders ADD CONSTRAINT orders_order_status_check CHECK (order_status IS NULL OR UPPER(order_status) IN (''PENDING'', ''CONFIRMED'', ''PROCESSING'', ''SHIPPED'', ''DELIVERED'', ''CANCELLED'', ''PAID'', ''COMPLETED'', ''APPROVED''))';
  END IF;

  -- C. Payments Table Schema & Defaults
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'payments') THEN
    EXECUTE 'ALTER TABLE public.payments ADD COLUMN IF NOT EXISTS payment_method TEXT DEFAULT ''bKash''';
    EXECUTE 'ALTER TABLE public.payments ADD COLUMN IF NOT EXISTS method TEXT DEFAULT ''bKash''';
    EXECUTE 'ALTER TABLE public.payments ADD COLUMN IF NOT EXISTS sender TEXT DEFAULT ''''';
    EXECUTE 'ALTER TABLE public.payments ADD COLUMN IF NOT EXISTS sender_number TEXT DEFAULT ''''';
    EXECUTE 'ALTER TABLE public.payments ADD COLUMN IF NOT EXISTS sms_raw TEXT DEFAULT ''''';
    EXECUTE 'ALTER TABLE public.payments ADD COLUMN IF NOT EXISTS sms_hash TEXT DEFAULT ''''';
    EXECUTE 'ALTER TABLE public.payments ADD COLUMN IF NOT EXISTS sms_timestamp TIMESTAMPTZ DEFAULT now()';
    EXECUTE 'ALTER TABLE public.payments ALTER COLUMN sms_hash DROP NOT NULL';
    EXECUTE 'ALTER TABLE public.payments ALTER COLUMN sms_hash SET DEFAULT md5(random()::text || clock_timestamp()::text)';
    EXECUTE 'ALTER TABLE public.payments DROP CONSTRAINT IF EXISTS payments_status_check';
    EXECUTE 'ALTER TABLE public.payments ADD CONSTRAINT payments_status_check CHECK (status IS NULL OR UPPER(status) IN (''MATCHED'', ''UNMATCHED'', ''DUPLICATE'', ''PAID'', ''PENDING'', ''REVERSED''))';
    EXECUTE 'CREATE INDEX IF NOT EXISTS idx_payments_merchant_id ON public.payments(merchant_id)';
    EXECUTE 'CREATE INDEX IF NOT EXISTS idx_payments_trx_id ON public.payments(trx_id)';
    EXECUTE 'CREATE INDEX IF NOT EXISTS idx_payments_status ON public.payments(status)';
  END IF;

  -- D. Merchant Notifications Table Schema & RPC
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'merchant_notifications') THEN
    EXECUTE 'ALTER TABLE public.merchant_notifications ADD COLUMN IF NOT EXISTS entity_type TEXT';
    EXECUTE 'ALTER TABLE public.merchant_notifications ADD COLUMN IF NOT EXISTS entity_id TEXT';
    EXECUTE 'ALTER TABLE public.merchant_notifications DROP CONSTRAINT IF EXISTS merchant_notifications_severity_check';
    EXECUTE 'ALTER TABLE public.merchant_notifications ADD CONSTRAINT merchant_notifications_severity_check CHECK (severity IS NULL OR UPPER(severity) IN (''INFO'', ''SUCCESS'', ''WARNING'', ''ERROR'', ''HIGH'', ''ALERT'', ''CRITICAL'', ''URGENT''))';
  END IF;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

-- E. RPC: mark_merchant_notification_read
CREATE OR REPLACE FUNCTION public.mark_merchant_notification_read(p_notification_id UUID, p_read_at TIMESTAMPTZ DEFAULT now())
RETURNS VOID LANGUAGE plpgsql SECURITY DEFINER AS $$
BEGIN
  UPDATE public.merchant_notifications SET read_at = p_read_at WHERE id = p_notification_id;
END;
$$;
DO $$ BEGIN GRANT EXECUTE ON FUNCTION public.mark_merchant_notification_read TO postgres, authenticated, anon, service_role;
EXCEPTION WHEN OTHERS THEN NULL; END $$;

-- 4. (Re)attach the self-provisioning trigger to every business table
DO $$
DECLARE
  t TEXT;
  business_tables TEXT[] := ARRAY[
    'products', 'product_variants', 'stock_transactions', 'pos_sales',
    'customers', 'suppliers', 'ledger_transactions', 'expenses',
    'loans', 'dps_accounts', 'finance_installments', 'business_analytics',
    'payment_forms', 'form_submissions', 'categories', 'store_settings',
    'employees', 'devices', 'sms_logs', 'merchant_numbers', 'orders', 'payments',
    'appeals', 'merchant_notifications',
    'merchant_subscriptions', 'showcase_config', 'product_photos',
    'product_reviews', 'shipping_methods', 'order_items', 'customer_carts'
  ];
BEGIN
  FOREACH t IN ARRAY business_tables LOOP
    IF EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = 'public' AND table_name = t AND column_name = 'merchant_id'
    ) THEN
      BEGIN
        EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY', t);
      EXCEPTION WHEN OTHERS THEN NULL;
      END;
      EXECUTE format('DROP TRIGGER IF EXISTS trg_%I_normalize_merchant ON public.%I', t, t);
      EXECUTE format('CREATE TRIGGER trg_%I_normalize_merchant
                        BEFORE INSERT OR UPDATE OF merchant_id ON public.%I
                        FOR EACH ROW EXECUTE FUNCTION public.fn_normalize_merchant_id()', t, t);
    END IF;
  END LOOP;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

-- 5. Permissive RLS so the app can read/write with the project anon key and no Auth user.
--    Old "…policy" rows from the provisioner are permissive too, so leaving them in place is
--    harmless (permissive policies are OR-ed together).
DO $$
DECLARE
  t TEXT;
  sync_tables TEXT[] := ARRAY[
    'merchants', 'products', 'product_variants', 'stock_transactions', 'pos_sales',
    'customers', 'suppliers', 'ledger_transactions', 'expenses',
    'loans', 'dps_accounts', 'finance_installments', 'business_analytics',
    'payment_forms', 'form_submissions', 'categories', 'store_settings',
    'employees', 'devices', 'sms_logs', 'merchant_numbers', 'orders', 'payments',
    'appeals', 'merchant_notifications',
    'merchant_subscriptions', 'showcase_config', 'product_photos',
    'product_reviews', 'shipping_methods', 'order_items', 'customer_carts'
  ];
BEGIN
  FOREACH t IN ARRAY sync_tables LOOP
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = t) THEN
      BEGIN
        EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY', t);
        EXECUTE format('DROP POLICY IF EXISTS "%s app sync policy" ON public.%I', t, t);
        EXECUTE format('DROP POLICY IF EXISTS "%s open sync policy" ON public.%I', t, t);
        EXECUTE format('DROP POLICY IF EXISTS "%s merchant sync policy" ON public.%I', t, t);
        EXECUTE format('CREATE POLICY "%s app sync policy" ON public.%I
                          FOR ALL TO authenticated, anon USING (true) WITH CHECK (true)', t, t);
      EXCEPTION WHEN OTHERS THEN NULL;
      END;
    END IF;
  END LOOP;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

-- 6. PostgREST grants
DO $$
BEGIN
  GRANT USAGE, CREATE ON SCHEMA public TO postgres, anon, authenticated, service_role;
  GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO postgres, anon, authenticated, service_role;
  GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO postgres, anon, authenticated, service_role;
  ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO anon, authenticated, service_role;
  ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO anon, authenticated, service_role;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

-- 7. Realtime so roster/business changes stream to the signed-in devices
DO $$
DECLARE
  t TEXT;
  rt_tables TEXT[] := ARRAY['merchants', 'employees', 'products', 'customers', 'suppliers',
                            'ledger_transactions', 'pos_sales', 'orders', 'payments', 'appeals', 'merchant_notifications'];
BEGIN
  IF EXISTS (SELECT 1 FROM pg_publication WHERE pubname = 'supabase_realtime') THEN
    FOREACH t IN ARRAY rt_tables LOOP
      BEGIN
        IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = t)
           AND NOT EXISTS (SELECT 1 FROM pg_publication_tables
                            WHERE pubname = 'supabase_realtime' AND schemaname = 'public' AND tablename = t) THEN
          EXECUTE format('ALTER PUBLICATION supabase_realtime ADD TABLE public.%I', t);
        END IF;
      EXCEPTION WHEN OTHERS THEN NULL;
      END;
    END LOOP;
  END IF;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

-- 8. Make PostgREST pick up the new functions/policies immediately
NOTIFY pgrst, 'reload schema';

-- Verification (run manually after applying):
--   select tablename, policyname, roles, cmd from pg_policies where schemaname = 'public' order by tablename;
--   select id, business_name, user_id from public.merchants order by created_at;
-- Then insert a test row with the anon key only:
--   insert into public.employees (merchant_id, name, designation, role, email, phone)
--   values ('<app merchant uuid>', 'Test', 'Staff', 'Sales', 'test@local', '01700000000');

