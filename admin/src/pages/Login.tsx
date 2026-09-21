import React, { useState } from 'react';
import { adminSupabase } from '../adminSupabaseClient';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../auth';
import { Zap, AlertCircle, ArrowRight, Loader2, Key, Database, Copy, Check, ShieldCheck, Eye, EyeOff, UserPlus, CheckCircle2 } from 'lucide-react';

const SUPER_ADMIN_SQL = `-- 1. Enable Required Extensions
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- 2. Create public.admin_users Table
CREATE TABLE IF NOT EXISTS public.admin_users (
  id          UUID PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
  email       TEXT NOT NULL,
  role        TEXT NOT NULL DEFAULT 'super_admin' CHECK (role IN ('super_admin', 'admin', 'viewer')),
  is_active   BOOLEAN NOT NULL DEFAULT true,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX IF NOT EXISTS admin_users_email_lower_idx ON public.admin_users (lower(email));

-- Ensure columns exist if table was previously created with fewer fields
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'admin_users' AND column_name = 'updated_at') THEN
    ALTER TABLE public.admin_users ADD COLUMN updated_at TIMESTAMPTZ NOT NULL DEFAULT now();
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'admin_users' AND column_name = 'is_active') THEN
    ALTER TABLE public.admin_users ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT true;
  END IF;
END $$;

-- 3. Methods: is_exist, is_admin, is_super_admin
CREATE OR REPLACE FUNCTION public.admin_is_exist(p_email TEXT)
RETURNS BOOLEAN LANGUAGE plpgsql SECURITY DEFINER SET search_path = public, auth AS $$
BEGIN
  RETURN EXISTS (SELECT 1 FROM public.admin_users WHERE lower(email) = lower(trim(p_email)) AND is_active = true);
END; $$;

CREATE OR REPLACE FUNCTION public.is_admin(p_user_id UUID DEFAULT auth.uid())
RETURNS BOOLEAN LANGUAGE plpgsql SECURITY DEFINER STABLE SET search_path = public, auth AS $$
BEGIN
  IF p_user_id IS NULL THEN RETURN false; END IF;
  RETURN EXISTS (SELECT 1 FROM public.admin_users WHERE id = p_user_id AND is_active = true AND role IN ('super_admin', 'admin'));
END; $$;

CREATE OR REPLACE FUNCTION public.is_super_admin(p_user_id UUID DEFAULT auth.uid())
RETURNS BOOLEAN LANGUAGE plpgsql SECURITY DEFINER STABLE SET search_path = public, auth AS $$
BEGIN
  IF p_user_id IS NULL THEN RETURN false; END IF;
  RETURN EXISTS (SELECT 1 FROM public.admin_users WHERE id = p_user_id AND is_active = true AND role = 'super_admin');
END; $$;

-- 4. RLS Configuration
ALTER TABLE public.admin_users ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Service Role full access on admin_users" ON public.admin_users;
CREATE POLICY "Service Role full access on admin_users" ON public.admin_users FOR ALL TO service_role USING (true) WITH CHECK (true);
DROP POLICY IF EXISTS "Authenticated users can read their own admin record" ON public.admin_users;
CREATE POLICY "Authenticated users can read their own admin record" ON public.admin_users FOR SELECT TO authenticated USING (id = auth.uid());
DROP POLICY IF EXISTS "Super admin manage admins" ON public.admin_users;
DROP POLICY IF EXISTS "Super admins can manage all admin users" ON public.admin_users;
DROP POLICY IF EXISTS "Self-bootstrap initial super admin" ON public.admin_users;
DROP POLICY IF EXISTS "Super admins can manage all admin records" ON public.admin_users;
CREATE POLICY "Super admins can manage all admin records" ON public.admin_users FOR ALL TO authenticated USING (public.is_super_admin(auth.uid())) WITH CHECK (public.is_super_admin(auth.uid()));

-- 5. Create or Upgrade Super Admin
DO $$
DECLARE
  v_email TEXT := 'admin@swapnopay.top';
  v_password TEXT := 'Admin@SwapnoPay2026!';
  v_user_id UUID;
  v_encrypted_pw TEXT := crypt(v_password, gen_salt('bf'));
BEGIN
  SELECT id INTO v_user_id FROM auth.users WHERE lower(email) = lower(v_email);
  IF v_user_id IS NULL THEN
    v_user_id := gen_random_uuid();
    INSERT INTO auth.users (instance_id, id, aud, role, email, encrypted_password, email_confirmed_at, raw_app_meta_data, raw_user_meta_data, created_at, updated_at, confirmation_token, recovery_token)
    VALUES ('00000000-0000-0000-0000-000000000000', v_user_id, 'authenticated', 'authenticated', lower(v_email), v_encrypted_pw, now(), '{"provider":"email","providers":["email"]}'::jsonb, '{"role":"super_admin"}'::jsonb, now(), now(), '', '');
  ELSE
    UPDATE auth.users SET encrypted_password = v_encrypted_pw, email_confirmed_at = COALESCE(email_confirmed_at, now()), updated_at = now() WHERE id = v_user_id;
  END IF;
  INSERT INTO public.admin_users (id, email, role, is_active, updated_at)
  VALUES (v_user_id, lower(v_email), 'super_admin', true, now())
  ON CONFLICT (id) DO UPDATE SET role = 'super_admin', is_active = true, email = lower(v_email), updated_at = now();
  RAISE NOTICE 'Super Admin configured successfully for %', v_email;
END $$;`;

export default function Login() {
  const [authMode, setAuthMode] = useState<'password' | 'master_key'>('password');
  const [isRegisterMode, setIsRegisterMode] = useState(false);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [masterKey, setMasterKey] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);
  const [showSqlModal, setShowSqlModal] = useState(false);
  const [copiedSql, setCopiedSql] = useState(false);

  const { loginWithMasterKey } = useAuth();
  const nav = useNavigate();

  async function doEmailLogin(e?: React.FormEvent) {
    if (e) e.preventDefault();
    if (!email || !password) {
      setError('Please enter both email and password.');
      return;
    }
    setLoading(true);
    setError(null);
    setSuccessMsg(null);

    try {
      if (isRegisterMode) {
        // Register new admin account
        const { data: signUpData, error: signUpErr } = await adminSupabase.auth.signUp({
          email: email.trim(),
          password: password.trim(),
        });
        if (signUpErr) throw signUpErr;

        if (signUpData?.user) {
          // Attempt self-heal insert into admin_users
          try {
            await adminSupabase.from('admin_users').upsert({
              id: signUpData.user.id,
              email: signUpData.user.email || email.trim(),
              role: 'super_admin',
              is_active: true,
            });
          } catch {}

          if (signUpData.session) {
            nav('/dashboard');
            return;
          } else {
            setSuccessMsg('Account created! If email confirmation is required, please verify your inbox, or run the SQL script below to auto-confirm.');
            setIsRegisterMode(false);
          }
        }
      } else {
        // Standard Sign In
        const { error: authErr } = await adminSupabase.auth.signInWithPassword({
          email: email.trim(),
          password: password.trim(),
        });
        if (authErr) throw authErr;
        nav('/dashboard');
      }
    } catch (e: any) {
      const msg = e.message || 'Authentication failed. Please verify credentials.';
      setError(msg);
      if (msg.toLowerCase().includes('invalid login credentials') || msg.toLowerCase().includes('user not found')) {
        setError(msg + ' (If you have not initialized the Super Admin user yet, click "Initialize Super Admin" below or run the SQL script).');
      }
    } finally {
      setLoading(false);
    }
  }

  async function doMasterKeyLogin(e?: React.FormEvent) {
    if (e) e.preventDefault();
    if (!masterKey.trim()) {
      setError('Please enter your platform Master Secret (ADMIN_SECRET).');
      return;
    }
    setLoading(true);
    setError(null);

    try {
      // Test the secret against backend API or save locally
      const base = (import.meta as any).env.VITE_BACKEND_URL || 'https://api.swapnopay.top';
      try {
        const resp = await fetch(`${base.replace(/\/$/, '')}/v1/admin/health`, {
          headers: { 'X-Admin-Secret': masterKey.trim() },
        });
        if (!resp.ok && resp.status === 401) {
          throw new Error('Invalid Master Secret Key. Please check ADMIN_SECRET in your backend .env file.');
        }
      } catch (netErr: any) {
        if (netErr.message.includes('Invalid Master Secret Key')) throw netErr;
      }

      loginWithMasterKey(masterKey.trim());
      nav('/dashboard');
    } catch (e: any) {
      setError(e.message || 'Master Key validation failed.');
    } finally {
      setLoading(false);
    }
  }

  async function doGoogleLogin() {
    setLoading(true);
    setError(null);
    try {
      const { error: authErr } = await adminSupabase.auth.signInWithOAuth({
        provider: 'google',
        options: { redirectTo: window.location.origin + '/dashboard' },
      });
      if (authErr) throw authErr;
    } catch (e: any) {
      setError(e.message || 'Google OAuth failed.');
      setLoading(false);
    }
  }

  function handleCopySql() {
    navigator.clipboard.writeText(SUPER_ADMIN_SQL);
    setCopiedSql(true);
    setTimeout(() => setCopiedSql(false), 3000);
  }

  return (
    <div
      style={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: 'radial-gradient(circle at top, #1E293B 0%, #0F172A 50%, #090D16 100%)',
        padding: 20,
      }}
    >
      <div
        style={{
          maxWidth: 460,
          width: '100%',
          background: 'var(--bg-surface, #ffffff)',
          borderRadius: 16,
          padding: '32px 28px',
          boxShadow: '0 25px 60px -15px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.08)',
          border: '1px solid var(--border-default, #E2E8F0)',
        }}
      >
        {/* Brand Top Header */}
        <div style={{ textAlign: 'center', marginBottom: 20 }}>
          <div
            style={{
              width: 44,
              height: 44,
              borderRadius: 12,
              background: 'var(--brand-primary, #4F46E5)',
              display: 'inline-flex',
              alignItems: 'center',
              justifyContent: 'center',
              color: '#ffffff',
              boxShadow: '0 8px 20px -4px rgba(79, 70, 229, 0.5)',
              marginBottom: 12,
            }}
          >
            <Zap size={22} />
          </div>
          <h1
            style={{
              fontSize: 21,
              fontWeight: 700,
              color: 'var(--text-primary, #0F172A)',
              margin: 0,
              letterSpacing: '-0.02em',
            }}
          >
            SwapnoPay Platform Admin
          </h1>
          <p style={{ color: 'var(--text-secondary, #64748B)', fontSize: 12.5, marginTop: 4 }}>
            Control console for gateways, merchants, KYC & ledgers
          </p>
        </div>

        {/* Tab Switcher: Credentials vs Master Secret Key */}
        <div
          style={{
            display: 'flex',
            background: '#F1F5F9',
            borderRadius: 10,
            padding: 3,
            marginBottom: 18,
          }}
        >
          <button
            type="button"
            onClick={() => { setAuthMode('password'); setError(null); }}
            style={{
              flex: 1,
              padding: '8px 0',
              border: 'none',
              borderRadius: 8,
              fontSize: 12.5,
              fontWeight: 600,
              cursor: 'pointer',
              background: authMode === 'password' ? '#ffffff' : 'transparent',
              color: authMode === 'password' ? '#0F172A' : '#64748B',
              boxShadow: authMode === 'password' ? '0 2px 5px rgba(0,0,0,0.08)' : 'none',
              transition: 'all 0.15s ease',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              gap: 6,
            }}
          >
            <ShieldCheck size={15} />
            Super Admin Account
          </button>
          <button
            type="button"
            onClick={() => { setAuthMode('master_key'); setError(null); }}
            style={{
              flex: 1,
              padding: '8px 0',
              border: 'none',
              borderRadius: 8,
              fontSize: 12.5,
              fontWeight: 600,
              cursor: 'pointer',
              background: authMode === 'master_key' ? '#ffffff' : 'transparent',
              color: authMode === 'master_key' ? '#0F172A' : '#64748B',
              boxShadow: authMode === 'master_key' ? '0 2px 5px rgba(0,0,0,0.08)' : 'none',
              transition: 'all 0.15s ease',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              gap: 6,
            }}
          >
            <Key size={14} />
            Master Secret Key
          </button>
        </div>

        {/* Success Alert */}
        {successMsg && (
          <div
            style={{
              padding: '10px 14px',
              background: '#ECFDF5',
              border: '1px solid #A7F3D0',
              borderRadius: 8,
              color: '#065F46',
              fontSize: 12.5,
              fontWeight: 500,
              marginBottom: 16,
              display: 'flex',
              alignItems: 'center',
              gap: 8,
            }}
          >
            <CheckCircle2 size={16} style={{ flexShrink: 0 }} />
            <span>{successMsg}</span>
          </div>
        )}

        {/* Error Alert */}
        {error && (
          <div
            style={{
              padding: '10px 14px',
              background: 'var(--danger-subtle, #FEF2F2)',
              border: '1px solid var(--danger-border, #FECACA)',
              borderRadius: 8,
              color: 'var(--danger-text, #DC2626)',
              fontSize: 12.5,
              fontWeight: 500,
              marginBottom: 16,
              display: 'flex',
              alignItems: 'center',
              gap: 8,
            }}
          >
            <AlertCircle size={16} style={{ flexShrink: 0 }} />
            <div style={{ flex: 1 }}>{error}</div>
          </div>
        )}

        {/* MODE 1: Super Admin Credentials Form */}
        {authMode === 'password' && (
          <form onSubmit={doEmailLogin} style={{ display: 'flex', flexDirection: 'column', gap: 13 }}>
            <div>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 5 }}>
                <label style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-secondary, #475569)' }}>
                  Admin Email
                </label>
                <button
                  type="button"
                  onClick={() => { setIsRegisterMode(!isRegisterMode); setError(null); }}
                  style={{
                    background: 'none',
                    border: 'none',
                    color: 'var(--brand-primary, #4F46E5)',
                    fontSize: 11.5,
                    fontWeight: 600,
                    cursor: 'pointer',
                    padding: 0,
                  }}
                >
                  {isRegisterMode ? '← Back to Sign In' : '+ Initialize / Setup Admin'}
                </button>
              </div>
              <input
                className="input"
                type="email"
                placeholder="admin@swapnopay.top"
                value={email}
                onChange={e => setEmail(e.target.value)}
                style={{ width: '100%', fontSize: 13 }}
                required
              />
            </div>

            <div>
              <label style={{ display: 'block', fontSize: 12, fontWeight: 600, color: 'var(--text-secondary, #475569)', marginBottom: 5 }}>
                {isRegisterMode ? 'Choose Password (min 8 chars)' : 'Password'}
              </label>
              <div style={{ position: 'relative' }}>
                <input
                  className="input"
                  type={showPassword ? 'text' : 'password'}
                  placeholder="••••••••••••"
                  value={password}
                  onChange={e => setPassword(e.target.value)}
                  style={{ width: '100%', fontSize: 13, paddingRight: 38 }}
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  style={{
                    position: 'absolute',
                    right: 10,
                    top: '50%',
                    transform: 'translateY(-50%)',
                    background: 'none',
                    border: 'none',
                    color: '#94A3B8',
                    cursor: 'pointer',
                    padding: 4,
                  }}
                >
                  {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>
            </div>

            <button
              type="submit"
              className="btn btn-primary"
              disabled={loading}
              style={{
                width: '100%',
                padding: '10px 0',
                marginTop: 4,
                fontSize: 13.5,
                fontWeight: 600,
              }}
            >
              {loading ? (
                <>
                  <Loader2 size={16} className="spin" />
                  {isRegisterMode ? 'Creating Admin...' : 'Signing in...'}
                </>
              ) : isRegisterMode ? (
                <>
                  <UserPlus size={15} />
                  Create Super Admin Account
                </>
              ) : (
                <>
                  Sign in to Dashboard
                  <ArrowRight size={15} />
                </>
              )}
            </button>

            {/* Google SSO */}
            {!isRegisterMode && (
              <>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, margin: '14px 0 10px', color: '#94A3B8', fontSize: 11, fontWeight: 600 }}>
                  <div style={{ flex: 1, height: 1, background: '#E2E8F0' }} />
                  <span>OR</span>
                  <div style={{ flex: 1, height: 1, background: '#E2E8F0' }} />
                </div>

                <button
                  type="button"
                  onClick={doGoogleLogin}
                  disabled={loading}
                  className="btn btn-secondary"
                  style={{
                    width: '100%',
                    padding: '8px 0',
                    fontSize: 12.5,
                    fontWeight: 600,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: 8,
                  }}
                >
                  <svg width="15" height="15" viewBox="0 0 48 48">
                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z" />
                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z" />
                    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z" />
                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z" />
                  </svg>
                  Google SSO
                </button>
              </>
            )}
          </form>
        )}

        {/* MODE 2: Master Secret Key Form */}
        {authMode === 'master_key' && (
          <form onSubmit={doMasterKeyLogin} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
            <div style={{ background: '#F8FAFC', border: '1px solid #E2E8F0', borderRadius: 8, padding: 12, fontSize: 12, color: '#475569', lineHeight: 1.5 }}>
              Enter the <code>ADMIN_SECRET</code> configured in your backend <code>.env</code> file. This provides instant super-admin access bypassing Supabase Auth.
            </div>

            <div>
              <label style={{ display: 'block', fontSize: 12, fontWeight: 600, color: 'var(--text-secondary, #475569)', marginBottom: 5 }}>
                Master Secret Key (ADMIN_SECRET)
              </label>
              <div style={{ position: 'relative' }}>
                <input
                  className="input"
                  type={showPassword ? 'text' : 'password'}
                  placeholder="Enter ADMIN_SECRET..."
                  value={masterKey}
                  onChange={e => setMasterKey(e.target.value)}
                  style={{ width: '100%', fontSize: 13, paddingRight: 38 }}
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  style={{
                    position: 'absolute',
                    right: 10,
                    top: '50%',
                    transform: 'translateY(-50%)',
                    background: 'none',
                    border: 'none',
                    color: '#94A3B8',
                    cursor: 'pointer',
                    padding: 4,
                  }}
                >
                  {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>
            </div>

            <button
              type="submit"
              className="btn btn-primary"
              disabled={loading}
              style={{
                width: '100%',
                padding: '10px 0',
                marginTop: 4,
                fontSize: 13.5,
                fontWeight: 600,
                background: '#0F172A',
              }}
            >
              {loading ? (
                <>
                  <Loader2 size={16} className="spin" />
                  Verifying Master Key...
                </>
              ) : (
                <>
                  <Key size={15} />
                  Authorize via Master Key
                </>
              )}
            </button>
          </form>
        )}

        {/* Database Quick Setup Helper */}
        <div style={{ marginTop: 22, paddingTop: 16, borderTop: '1px solid #F1F5F9', textAlign: 'center' }}>
          <button
            type="button"
            onClick={() => setShowSqlModal(!showSqlModal)}
            style={{
              background: 'none',
              border: 'none',
              color: '#6366F1',
              fontSize: 12,
              fontWeight: 600,
              cursor: 'pointer',
              display: 'inline-flex',
              alignItems: 'center',
              gap: 6,
              padding: 0,
            }}
          >
            <Database size={14} />
            {showSqlModal ? 'Hide Database Setup SQL' : '🛠️ Database Setup: Super Admin SQL Script'}
          </button>
        </div>

        {/* SQL Script Viewer */}
        {showSqlModal && (
          <div
            style={{
              marginTop: 14,
              background: '#0F172A',
              borderRadius: 8,
              padding: 12,
              border: '1px solid #334155',
            }}
          >
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
              <span style={{ color: '#94A3B8', fontSize: 11, fontWeight: 700 }}>
                Supabase SQL Editor Script
              </span>
              <button
                type="button"
                onClick={handleCopySql}
                style={{
                  background: copiedSql ? '#10B981' : '#334155',
                  color: 'white',
                  border: 'none',
                  borderRadius: 6,
                  padding: '3px 8px',
                  fontSize: 11,
                  fontWeight: 600,
                  cursor: 'pointer',
                  display: 'flex',
                  alignItems: 'center',
                  gap: 4,
                }}
              >
                {copiedSql ? <Check size={12} /> : <Copy size={12} />}
                {copiedSql ? 'Copied!' : 'Copy SQL'}
              </button>
            </div>
            <pre
              style={{
                margin: 0,
                color: '#38BDF8',
                fontSize: 11,
                fontFamily: 'monospace',
                maxHeight: 180,
                overflowY: 'auto',
                whiteSpace: 'pre-wrap',
                wordBreak: 'break-word',
                lineHeight: 1.4,
              }}
            >
              {SUPER_ADMIN_SQL}
            </pre>
            <div style={{ marginTop: 8, fontSize: 10.5, color: '#94A3B8', lineHeight: 1.4 }}>
              Run this in your <strong>Supabase Dashboard &gt; SQL Editor</strong> to automatically create the <code>admin_users</code> table with <code>is_exist</code> methods and provision your Super Admin.
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

