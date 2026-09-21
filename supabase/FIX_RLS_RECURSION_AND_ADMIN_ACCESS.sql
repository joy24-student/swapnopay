-- ==============================================================================
-- SwapnoPay Platform Database Repair: Fix RLS Infinite Recursion on admin_users
-- File: supabase/FIX_RLS_RECURSION_AND_ADMIN_ACCESS.sql
-- Run this in your Supabase SQL Editor: Dashboard > SQL Editor > New Query > Run
-- ==============================================================================

-- 1. Enable Required Extensions
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- 2. Ensure public.admin_users Table Exists with Required Columns
CREATE TABLE IF NOT EXISTS public.admin_users (
  id          UUID PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
  email       TEXT NOT NULL,
  role        TEXT NOT NULL DEFAULT 'super_admin' CHECK (role IN ('super_admin', 'admin', 'viewer')),
  is_active   BOOLEAN NOT NULL DEFAULT true,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS admin_users_email_lower_idx ON public.admin_users (lower(email));

DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'admin_users' AND column_name = 'updated_at') THEN
    ALTER TABLE public.admin_users ADD COLUMN updated_at TIMESTAMPTZ NOT NULL DEFAULT now();
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'admin_users' AND column_name = 'is_active') THEN
    ALTER TABLE public.admin_users ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT true;
  END IF;
END $$;

-- 3. CRITICAL: SECURITY DEFINER Helper Functions (Prevents RLS Infinite Recursion)
-- Because these functions are SECURITY DEFINER with search_path = public, auth,
-- PostgreSQL executes queries on admin_users with function owner privileges,
-- entirely bypassing Row Level Security and breaking the recursion loop!

CREATE OR REPLACE FUNCTION public.is_admin(p_user_id UUID DEFAULT auth.uid())
RETURNS BOOLEAN
LANGUAGE plpgsql
SECURITY DEFINER
STABLE
SET search_path = public, auth
AS $$
BEGIN
  IF p_user_id IS NULL THEN RETURN false; END IF;
  RETURN EXISTS (
    SELECT 1 FROM public.admin_users 
    WHERE id = p_user_id AND is_active = true AND role IN ('super_admin', 'admin')
  );
END;
$$;

CREATE OR REPLACE FUNCTION public.is_super_admin(p_user_id UUID DEFAULT auth.uid())
RETURNS BOOLEAN
LANGUAGE plpgsql
SECURITY DEFINER
STABLE
SET search_path = public, auth
AS $$
BEGIN
  IF p_user_id IS NULL THEN RETURN false; END IF;
  RETURN EXISTS (
    SELECT 1 FROM public.admin_users 
    WHERE id = p_user_id AND is_active = true AND role = 'super_admin'
  );
END;
$$;

CREATE OR REPLACE FUNCTION public.admin_is_exist(p_email TEXT)
RETURNS BOOLEAN
LANGUAGE plpgsql
SECURITY DEFINER
STABLE
SET search_path = public, auth
AS $$
BEGIN
  RETURN EXISTS (
    SELECT 1 FROM public.admin_users 
    WHERE lower(email) = lower(trim(p_email)) AND is_active = true
  );
END;
$$;

-- 4. Clean Up ALL Recursive Policies on public.admin_users
ALTER TABLE public.admin_users ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Super admin manage admins" ON public.admin_users;
DROP POLICY IF EXISTS "Super admins can manage all admin users" ON public.admin_users;
DROP POLICY IF EXISTS "Super admins can manage all admin records" ON public.admin_users;
DROP POLICY IF EXISTS "Self-bootstrap initial super admin" ON public.admin_users;
DROP POLICY IF EXISTS "Admin self read" ON public.admin_users;
DROP POLICY IF EXISTS "Authenticated users can read their own admin record" ON public.admin_users;
DROP POLICY IF EXISTS "Service Role full access on admin_users" ON public.admin_users;

-- Safe non-recursive policies on admin_users:
CREATE POLICY "Service Role full access on admin_users"
  ON public.admin_users FOR ALL
  TO service_role
  USING (true)
  WITH CHECK (true);

CREATE POLICY "Authenticated users can read their own admin record"
  ON public.admin_users FOR SELECT
  TO authenticated
  USING (id = auth.uid());

CREATE POLICY "Super admins can manage all admin records"
  ON public.admin_users FOR ALL
  TO authenticated
  USING (public.is_super_admin(auth.uid()))
  WITH CHECK (public.is_super_admin(auth.uid()));

-- 5. Fix All Operational Tables to use public.is_admin() instead of inline subqueries
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
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = tbl) THEN
      EXECUTE format('DROP POLICY IF EXISTS "Admin full access on %I" ON public.%I;', tbl, tbl);
      EXECUTE format('CREATE POLICY "Admin full access on %I" ON public.%I FOR ALL TO authenticated USING (public.is_admin(auth.uid())) WITH CHECK (public.is_admin(auth.uid()));', tbl, tbl);
    END IF;
  END LOOP;
END $$;

-- 6. Grant direct merchant access to their own data in merchants and merchant_kyc_submissions
DROP POLICY IF EXISTS "Merchants self read" ON public.merchants;
CREATE POLICY "Merchants self read" ON public.merchants FOR SELECT TO authenticated
  USING (id = auth.uid() OR user_id = auth.uid()::text OR public.is_admin(auth.uid()));

DROP POLICY IF EXISTS "Merchants self insert" ON public.merchants;
CREATE POLICY "Merchants self insert" ON public.merchants FOR INSERT TO authenticated
  WITH CHECK (id = auth.uid() OR user_id = auth.uid()::text OR public.is_admin(auth.uid()));

DROP POLICY IF EXISTS "Merchants self update" ON public.merchants;
CREATE POLICY "Merchants self update" ON public.merchants FOR UPDATE TO authenticated
  USING (id = auth.uid() OR user_id = auth.uid()::text OR public.is_admin(auth.uid()))
  WITH CHECK (id = auth.uid() OR user_id = auth.uid()::text OR public.is_admin(auth.uid()));

DROP POLICY IF EXISTS "Merchant KYC self read" ON public.merchant_kyc_submissions;
CREATE POLICY "Merchant KYC self read" ON public.merchant_kyc_submissions FOR SELECT TO authenticated
  USING (merchant_id = auth.uid() OR public.is_admin(auth.uid()));

DROP POLICY IF EXISTS "Merchant KYC self insert" ON public.merchant_kyc_submissions;
CREATE POLICY "Merchant KYC self insert" ON public.merchant_kyc_submissions FOR INSERT TO authenticated
  WITH CHECK (merchant_id = auth.uid() OR public.is_admin(auth.uid()));

DROP POLICY IF EXISTS "Merchant KYC self update" ON public.merchant_kyc_submissions;
CREATE POLICY "Merchant KYC self update" ON public.merchant_kyc_submissions FOR UPDATE TO authenticated
  USING (merchant_id = auth.uid() OR public.is_admin(auth.uid()))
  WITH CHECK (merchant_id = auth.uid() OR public.is_admin(auth.uid()));

-- 7. Ensure Default Super Admin is Active in admin_users
DO $$
DECLARE
  v_email TEXT := 'admin@swapnopay.top';
  v_user_id UUID;
BEGIN
  SELECT id INTO v_user_id FROM auth.users WHERE lower(email) = lower(v_email) LIMIT 1;
  IF v_user_id IS NOT NULL THEN
    INSERT INTO public.admin_users (id, email, role, is_active, updated_at)
    VALUES (v_user_id, lower(v_email), 'super_admin', true, now())
    ON CONFLICT (id) DO UPDATE SET
      role = 'super_admin',
      is_active = true,
      updated_at = now();
  END IF;
END $$;
