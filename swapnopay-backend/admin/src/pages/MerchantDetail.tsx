import React, { useEffect, useState } from 'react';
import {
  adminSupabase,
  reviewMerchantIdentity,
  formatKycImageUrl,
  getMerchantPinStatus,
  clearMerchantPin,
  forceRequestPinReset,
  generateMerchantApiKey,
  revokeMerchantApiKey,
  updateMerchantAccountStatus,
  deleteMerchantAccount,
  fetchMerchantDiagnostics,
  MerchantDiagnosticsData
} from '../adminSupabaseClient';
import { useParams, Link, useNavigate } from 'react-router-dom';
import {
  User,
  Key,
  ShieldCheck,
  Check,
  X,
  FileText,
  Maximize2,
  CreditCard,
  Link2,
  Activity,
  ArrowLeft,
  Building,
  Save,
  AlertTriangle,
  AlertOctagon,
  Lock,
  Trash2,
  RefreshCw,
  Globe,
  Smartphone,
  Database,
  ExternalLink,
  Copy,
  CheckCircle2,
  Zap,
  Info,
  DollarSign,
  TrendingUp,
  Radio
} from 'lucide-react';

export default function MerchantDetail() {
  const { id } = useParams();
  const [merchant, setMerchant] = useState<any | null>(null);
  const [diagnostics, setDiagnostics] = useState<MerchantDiagnosticsData | null>(null);
  const [diagLoading, setDiagLoading] = useState(false);
  const [copiedField, setCopiedField] = useState<string | null>(null);
  const [apiKeys, setApiKeys] = useState<any[]>([]);
  const [recentEvents, setRecentEvents] = useState<any[]>([]);
  const [publicEndpoint, setPublicEndpoint] = useState('');
  const [webhookUrl, setWebhookUrl] = useState('');
  const [testResult, setTestResult] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [kycProcessing, setKycProcessing] = useState(false);
  const [kycFeedback, setKycFeedback] = useState<string | null>(null);
  const [previewImage, setPreviewImage] = useState<string | null>(null);
  const [rejectionReasonInput, setRejectionReasonInput] = useState('');
  const [showRejectBox, setShowRejectBox] = useState(false);
  const [pinStatus, setPinStatus] = useState<{ pin_set: boolean; pin_reset_requested: boolean } | null>(null);
  const [pinActionLoading, setPinActionLoading] = useState(false);
  const [pinActionResult, setPinActionResult] = useState<string | null>(null);
  const [keyActionLoading, setKeyActionLoading] = useState(false);
  const [newlyGeneratedKey, setNewlyGeneratedKey] = useState<string | null>(null);
  const [keyActionResult, setKeyActionResult] = useState<string | null>(null);
  const [statusLoading, setStatusLoading] = useState(false);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const navigate = useNavigate();

  const loadDiagnostics = async (merchantId: string) => {
    setDiagLoading(true);
    try {
      const data = await fetchMerchantDiagnostics(merchantId);
      if (data) {
        setDiagnostics(data);
      }
    } catch (err) {
      console.warn('[MerchantDetail] Diagnostics load error:', err);
    } finally {
      setDiagLoading(false);
    }
  };

  useEffect(() => {
    if (!id) return;
    (async () => {
      let mData: any = null;

      // 1. Load merchant from Admin Supabase
      try {
        let { data } = await adminSupabase
          .from('merchants')
          .select('*')
          .eq('id', id)
          .maybeSingle();

        if (!data) {
          const res = await adminSupabase
            .from('merchants')
            .select('*')
            .eq('user_id', id)
            .maybeSingle();
          data = res.data;
        }

        if (data) {
          try {
            const { data: subData } = await adminSupabase
              .from('merchant_kyc_submissions')
              .select('*')
              .or(`merchant_id.eq.${data.id},merchant_id.eq.${data.user_id || data.id}`)
              .order('created_at', { ascending: false })
              .limit(1)
              .maybeSingle();
            if (subData) {
              data = {
                ...data,
                nid_front_url: data.nid_front_url || subData.nid_front_url,
                nid_back_url: data.nid_back_url || subData.nid_back_url,
                face_photo_url: data.face_photo_url || subData.face_photo_url,
                nid_number: data.nid_number || subData.nid_number,
                nid_name: data.nid_name || subData.nid_name,
                nid_dob: data.nid_dob || subData.nid_dob,
              };
            }
          } catch (_) {}
          mData = data;
        }
      } catch (e) {
        console.warn('[MerchantDetail] Supabase fetch error:', e);
      }

      setMerchant(mData);

      // 2. Load merchant API keys from Admin Supabase
      try {
        const { data: keys } = await adminSupabase
          .from('platform_api_keys')
          .select('*')
          .eq('merchant_id', id);
        if (keys) setApiKeys(keys);
      } catch (e) {
        console.warn('[MerchantDetail] API keys fetch error:', e);
      }

      // 3. Load recent payment events for this merchant
      try {
        const { data: events } = await adminSupabase
          .from('payment_events')
          .select('*')
          .eq('merchant_id', id)
          .order('recorded_at', { ascending: false })
          .limit(10);
        if (events) setRecentEvents(events);
      } catch (e) {
        console.warn('[MerchantDetail] Events fetch error:', e);
      }

      // 4. Load connection settings from Admin Supabase
      try {
        const { data: conn } = await adminSupabase
          .from('merchant_connections')
          .select('*')
          .eq('merchant_id', id)
          .single();
        if (conn) {
          setPublicEndpoint(conn.public_endpoint || '');
          setWebhookUrl(conn.webhook_url || '');
        }
      } catch (e) {
        console.warn('[MerchantDetail] Connections fetch error:', e);
      }

      // 5. Load merchant PIN status
      try {
        const targetId = mData?.id || id;
        const status = await getMerchantPinStatus(targetId);
        setPinStatus(status);
      } catch (e) {
        console.warn('[MerchantDetail] PIN status fetch error:', e);
      }

      // 6. Load real-time shop diagnostics & health
      await loadDiagnostics(id);

      setLoading(false);
    })();
  }, [id]);

  const copyToClipboard = (text: string, fieldName: string) => {
    navigator.clipboard.writeText(text);
    setCopiedField(fieldName);
    setTimeout(() => setCopiedField(null), 2200);
  };

  const handleSaveConnection = async () => {
    if (!id) return;
    setTestResult('Saving connections...');
    try {
      const { error } = await adminSupabase
        .from('merchant_connections')
        .upsert({
          merchant_id: id,
          public_endpoint: publicEndpoint.trim() || null,
          webhook_url: webhookUrl.trim() || null,
          updated_at: new Date().toISOString(),
        }, { onConflict: 'merchant_id' });

      if (error) throw error;
      setTestResult('Connection settings saved successfully.');
    } catch (err: any) {
      setTestResult('Save error: ' + err.message);
    }
  };

  const formatDocUrl = (url: string | null | undefined) => {
    return formatKycImageUrl(url);
  };

  const handleReviewKyc = async (action: 'APPROVE' | 'REJECT') => {
    if (!id) return;
    if (action === 'REJECT' && !rejectionReasonInput.trim()) {
      alert('Please provide a reason for rejecting the KYC verification.');
      return;
    }
    setKycProcessing(true);
    setKycFeedback(null);
    try {
      const now = new Date().toISOString();
      const nextKycStatus = action === 'APPROVE' ? 'VERIFIED' : 'REJECTED';
      const updateData: any = {
        kyc_status: nextKycStatus,
        kyc_reviewed_at: now,
        kyc_reviewed_by: 'ADMIN',
        updated_at: now,
      };
      if (action === 'APPROVE') {
        updateData.status = 'ACTIVE';
      } else {
        updateData.kyc_rejection_reason = rejectionReasonInput.trim();
      }

      const { error } = await adminSupabase
        .from('merchants')
        .update(updateData)
        .eq('id', id);

      if (error) throw error;

      try {
        await reviewMerchantIdentity(id, action, rejectionReasonInput.trim());
      } catch (e: any) {
        console.warn('[MerchantDetail] backend KYC review failed:', e?.message || e);
      }

      setMerchant((prev: any) => ({ ...prev, ...updateData }));
      setKycFeedback(`KYC verification successfully ${action === 'APPROVE' ? 'approved and verified' : 'rejected'}.`);
      setShowRejectBox(false);
      setRejectionReasonInput('');
      await loadDiagnostics(id);
    } catch (err: any) {
      setKycFeedback('KYC update error: ' + err.message);
    } finally {
      setKycProcessing(false);
    }
  };

  const handleGenerateApiKey = async () => {
    if (!id) return;
    setKeyActionLoading(true);
    setKeyActionResult(null);
    setNewlyGeneratedKey(null);
    try {
      const data = await generateMerchantApiKey(id, merchant?.business_name || 'Merchant', 'Admin Generated Dynamic Key');
      if (data && (data.ok || data.api_key)) {
        setNewlyGeneratedKey(data.api_key);
        setKeyActionResult('✅ Dynamic API key generated successfully!');
        const { data: keys } = await adminSupabase
          .from('platform_api_keys')
          .select('*')
          .eq('merchant_id', id);
        if (keys) setApiKeys(keys);
        await loadDiagnostics(id);
      } else {
        setKeyActionResult('❌ ' + (data.error || 'Failed to generate key'));
      }
    } catch (e: any) {
      setKeyActionResult('❌ ' + e.message);
    } finally {
      setKeyActionLoading(false);
    }
  };

  const handleRevokeApiKey = async (keyId: string) => {
    if (!window.confirm('Are you sure you want to revoke this API key? Services using it will lose access immediately.')) return;
    setKeyActionLoading(true);
    try {
      await revokeMerchantApiKey(keyId);
      setKeyActionResult('✅ API key revoked successfully');
      const { data: keys } = await adminSupabase
        .from('platform_api_keys')
        .select('*')
        .eq('merchant_id', id);
      if (keys) setApiKeys(keys);
      await loadDiagnostics(id);
    } catch (e: any) {
      setKeyActionResult('❌ ' + e.message);
    } finally {
      setKeyActionLoading(false);
    }
  };

  const handleToggleStatus = async () => {
    if (!merchant || !id) return;
    const currentStatus = merchant.status || 'ACTIVE';
    const nextStatus = currentStatus === 'SUSPENDED' ? 'ACTIVE' : 'SUSPENDED';
    if (!window.confirm(`Are you sure you want to mark this merchant as ${nextStatus}?`)) return;

    setStatusLoading(true);
    try {
      await updateMerchantAccountStatus(merchant.id || id, nextStatus);
      setMerchant((prev: any) => prev ? { ...prev, status: nextStatus } : prev);
      alert(`Merchant status updated to ${nextStatus} successfully.`);
      await loadDiagnostics(id);
    } catch (err: any) {
      alert('Failed to update status: ' + err.message);
    } finally {
      setStatusLoading(false);
    }
  };

  const handleDeleteMerchant = async () => {
    if (!merchant || !id) return;
    if (!window.confirm(`⚠️ PERMANENT ACCOUNT DELETION\n\nAre you sure you want to permanently delete "${merchant.business_name || id}"?\n\nThis will purge all merchant data, dynamic API keys, KYC documents, and records. This action cannot be undone.`)) {
      return;
    }
    const check = window.prompt(`To confirm permanent deletion, type "DELETE" below:`);
    if (check !== 'DELETE') {
      alert('Deletion cancelled: confirmation keyword did not match.');
      return;
    }

    setDeleteLoading(true);
    try {
      await deleteMerchantAccount(merchant.id || id);
      alert('Merchant account and all associated data have been permanently deleted.');
      navigate('/merchants');
    } catch (err: any) {
      alert('Failed to delete merchant: ' + err.message);
    } finally {
      setDeleteLoading(false);
    }
  };

  if (loading || !merchant) {
    return (
      <div className="container" style={{ padding: '60px 20px', textAlign: 'center' }}>
        <RefreshCw size={28} className="animate-spin" style={{ color: 'var(--brand-primary)', margin: '0 auto 16px' }} />
        <div style={{ color: 'var(--text-primary)', fontSize: 15, fontWeight: 700 }}>
          Loading Merchant Shop Details & Diagnostics...
        </div>
      </div>
    );
  }

  // Diagnostics shortcut helpers
  const healthStatus = diagnostics?.health?.status || (merchant.status === 'SUSPENDED' ? 'SUSPENDED' : 'HEALTHY');
  const healthScore = diagnostics?.health?.score ?? 100;
  const issues = diagnostics?.issues || [];
  const storefrontUrl = diagnostics?.urls?.storefront_url || merchant.website || null;
  const storefrontAdminUrl = diagnostics?.urls?.storefront_admin_url || (storefrontUrl ? `${storefrontUrl.replace(/\/$/, '')}/admin/` : null);
  const checkoutWidgetUrl = diagnostics?.urls?.checkout_widget_url || `https://pay.swapnopay.top/widget.html?merchant_id=${merchant.id}`;
  const checkoutFormUrl = diagnostics?.urls?.checkout_form_url || `https://pay.swapnopay.top/form.html?merchant_id=${merchant.id}`;
  const receivingNumbers = diagnostics?.gateway?.receiving_numbers || {};
  const activeNumbers = diagnostics?.gateway?.active_numbers || {};

  // Status badge styling
  const healthConfig = {
    HEALTHY: { label: 'LIVE & HEALTHY', bg: 'rgba(16,185,129,0.12)', border: '#10B981', color: '#10B981', icon: CheckCircle2 },
    DEGRADED: { label: 'DEGRADED (ATTENTION NEEDED)', bg: 'rgba(245,158,11,0.12)', border: '#F59E0B', color: '#F59E0B', icon: AlertTriangle },
    CRITICAL: { label: 'CRITICAL ISSUES DETECTED', bg: 'rgba(239,68,68,0.14)', border: '#EF4444', color: '#EF4444', icon: AlertOctagon },
    SUSPENDED: { label: 'ACCOUNT SUSPENDED', bg: 'rgba(100,116,139,0.15)', border: '#64748B', color: '#64748B', icon: X }
  }[healthStatus] || { label: healthStatus, bg: 'rgba(16,185,129,0.12)', border: '#10B981', color: '#10B981', icon: CheckCircle2 };

  const HealthIcon = healthConfig.icon;

  return (
    <div className="container" style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
      {/* ──────────────── Top Navigation Bar ──────────────── */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 12 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <Link to="/merchants" style={{ textDecoration: 'none' }}>
            <button className="btn btn-secondary btn-sm" style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
              <ArrowLeft size={14} />
              <span>Back to Merchants</span>
            </button>
          </Link>
          <div>
            <h1 style={{ fontSize: 20, fontWeight: 800, margin: 0, color: 'var(--text-primary)', display: 'flex', alignItems: 'center', gap: 8 }}>
              <span>{merchant.business_name || merchant.name || 'Merchant Shop'}</span>
              <span style={{ fontSize: 11, padding: '3px 8px', borderRadius: 6, fontWeight: 700, background: 'var(--bg-subtle)', color: 'var(--text-secondary)', fontFamily: 'var(--font-mono)' }}>
                {merchant.subscription_tier || 'STARTER'}
              </span>
            </h1>
            <div style={{ fontSize: 11.5, color: 'var(--text-muted)', marginTop: 2, display: 'flex', alignItems: 'center', gap: 6 }}>
              <span>ID: <code style={{ fontFamily: 'var(--font-mono)' }}>{merchant.id}</code></span>
              <button
                onClick={() => copyToClipboard(merchant.id, 'merchant_id')}
                className="btn-icon btn-ghost"
                style={{ width: 18, height: 18 }}
                title="Copy Merchant UUID"
              >
                <Copy size={11} color={copiedField === 'merchant_id' ? 'var(--success)' : 'var(--text-muted)'} />
              </button>
            </div>
          </div>
        </div>

        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          <button
            onClick={() => loadDiagnostics(id || '')}
            disabled={diagLoading}
            className="btn btn-secondary btn-sm"
            style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}
            title="Re-probe storefront, database, and terminal status"
          >
            <RefreshCw size={13} className={diagLoading ? 'animate-spin' : ''} />
            <span>{diagLoading ? 'Probing Services...' : 'Run Diagnostics'}</span>
          </button>
          <button
            onClick={handleToggleStatus}
            disabled={statusLoading}
            className={`btn btn-sm ${merchant.status === 'SUSPENDED' ? 'btn-secondary' : 'btn-danger'}`}
          >
            {statusLoading ? 'Updating...' : (merchant.status === 'SUSPENDED' ? 'Activate Account' : 'Suspend Account')}
          </button>
        </div>
      </div>

      {/* ──────────────── 1. SHOP HEALTH & REAL-TIME RUNTIME DIAGNOSTICS (HERO BANNER) ──────────────── */}
      <div className="card" style={{
        padding: 20,
        background: 'var(--bg-surface)',
        border: `1.5px solid ${healthConfig.border}`,
        borderRadius: 12,
        boxShadow: '0 4px 20px -5px rgba(0,0,0,0.06)'
      }}>
        {/* Banner Top Bar */}
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 12, marginBottom: 16 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <div style={{
              width: 36,
              height: 36,
              borderRadius: 8,
              background: healthConfig.bg,
              border: `1px solid ${healthConfig.border}`,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              color: healthConfig.color
            }}>
              <HealthIcon size={20} />
            </div>
            <div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <span style={{ fontSize: 15, fontWeight: 800, color: healthConfig.color, letterSpacing: '0.2px' }}>
                  {healthConfig.label}
                </span>
                <span style={{
                  fontSize: 11,
                  fontWeight: 800,
                  padding: '2px 8px',
                  borderRadius: 12,
                  background: healthConfig.bg,
                  color: healthConfig.color,
                  border: `1px solid ${healthConfig.border}`
                }}>
                  Score: {healthScore}%
                </span>
              </div>
              <div style={{ fontSize: 11.5, color: 'var(--text-muted)', marginTop: 2 }}>
                Real-time operational health audit • Probed at {diagnostics?.timestamp ? new Date(diagnostics.timestamp).toLocaleTimeString() : new Date().toLocaleTimeString()}
              </div>
            </div>
          </div>

          <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
            {storefrontUrl && (
              <a
                href={storefrontUrl}
                target="_blank"
                rel="noreferrer"
                className="btn btn-primary btn-sm"
                style={{ display: 'inline-flex', alignItems: 'center', gap: 6, textDecoration: 'none' }}
              >
                <Globe size={13} />
                <span>Visit Live Storefront</span>
                <ExternalLink size={12} />
              </a>
            )}
            <a
              href={checkoutWidgetUrl}
              target="_blank"
              rel="noreferrer"
              className="btn btn-secondary btn-sm"
              style={{ display: 'inline-flex', alignItems: 'center', gap: 6, textDecoration: 'none' }}
            >
              <CreditCard size={13} />
              <span>Test Payment Gateway</span>
              <ExternalLink size={12} />
            </a>
          </div>
        </div>

        {/* Core Services 4-Card Grid */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(210px, 1fr))', gap: 12 }}>
          {/* Card 1: Storefront Web Service */}
          <div style={{ padding: '12px 14px', borderRadius: 8, background: 'var(--bg-subtle)', border: '1px solid var(--border-default)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6 }}>
              <span style={{ fontSize: 11, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', display: 'flex', alignItems: 'center', gap: 5 }}>
                <Globe size={13} color="var(--brand-primary)" />
                Storefront
              </span>
              <span className={`status-pill ${storefrontUrl && diagnostics?.shop?.probe?.reachable !== false ? 'success' : 'danger'}`} style={{ fontSize: 10.5 }}>
                <span className="status-dot" />
                {storefrontUrl ? (diagnostics?.shop?.probe?.reachable !== false ? 'ONLINE' : 'UNREACHABLE') : 'NO STORE'}
              </span>
            </div>
            <div style={{ fontSize: 12.5, fontWeight: 700, color: 'var(--text-primary)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
              {storefrontUrl ? storefrontUrl.replace(/^https?:\/\//, '') : 'No active domain'}
            </div>
            <div style={{ fontSize: 11, color: 'var(--text-muted)', marginTop: 4, display: 'flex', justifyContent: 'space-between' }}>
              <span>HTTP Probe: {diagnostics?.shop?.probe?.status ? `HTTP ${diagnostics.shop.probe.status}` : (storefrontUrl ? 'Ready' : 'N/A')}</span>
              {diagnostics?.shop?.probe?.latency_ms && (
                <span style={{ color: 'var(--success)', fontWeight: 700 }}>⚡ {diagnostics.shop.probe.latency_ms}ms</span>
              )}
            </div>
          </div>

          {/* Card 2: SMS Forwarder Device */}
          <div style={{ padding: '12px 14px', borderRadius: 8, background: 'var(--bg-subtle)', border: '1px solid var(--border-default)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6 }}>
              <span style={{ fontSize: 11, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', display: 'flex', alignItems: 'center', gap: 5 }}>
                <Smartphone size={13} color="var(--brand-primary)" />
                SMS Terminal
              </span>
              <span className={`status-pill ${
                diagnostics?.device?.status === 'ONLINE' ? 'success' :
                diagnostics?.device?.status === 'STALE' ? 'warning' : 'danger'
              }`} style={{ fontSize: 10.5 }}>
                <span className="status-dot" />
                {diagnostics?.device?.status || 'NOT PAIRED'}
              </span>
            </div>
            <div style={{ fontSize: 12.5, fontWeight: 700, color: 'var(--text-primary)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
              {diagnostics?.device?.device_model || diagnostics?.device?.device_name || 'No phone linked'}
            </div>
            <div style={{ fontSize: 11, color: 'var(--text-muted)', marginTop: 4 }}>
              {diagnostics?.device?.last_active ? (
                <span>Last Ping: {new Date(diagnostics.device.last_active).toLocaleTimeString()} ({new Date(diagnostics.device.last_active).toLocaleDateString()})</span>
              ) : (
                <span>No heartbeat received</span>
              )}
            </div>
          </div>

          {/* Card 3: Dedicated Database */}
          <div style={{ padding: '12px 14px', borderRadius: 8, background: 'var(--bg-subtle)', border: '1px solid var(--border-default)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6 }}>
              <span style={{ fontSize: 11, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', display: 'flex', alignItems: 'center', gap: 5 }}>
                <Database size={13} color="var(--brand-primary)" />
                Dedicated DB
              </span>
              <span className={`status-pill ${
                diagnostics?.database?.reachable ? 'success' :
                diagnostics?.database?.configured ? 'danger' : 'neutral'
              }`} style={{ fontSize: 10.5 }}>
                <span className="status-dot" />
                {diagnostics?.database?.reachable ? 'CONNECTED' : (diagnostics?.database?.configured ? 'ERROR' : 'LOCAL FALLBACK')}
              </span>
            </div>
            <div style={{ fontSize: 12.5, fontWeight: 700, color: 'var(--text-primary)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
              {diagnostics?.database?.url ? diagnostics.database.url.replace(/^https?:\/\//, '').split('.')[0] + '.supabase.co' : 'Local SQLite/Embedded'}
            </div>
            <div style={{ fontSize: 11, color: 'var(--text-muted)', marginTop: 4, display: 'flex', justifyContent: 'space-between' }}>
              <span>Engine: PostgreSQL</span>
              {diagnostics?.database?.latency_ms && (
                <span style={{ color: 'var(--success)', fontWeight: 700 }}>⚡ {diagnostics.database.latency_ms}ms</span>
              )}
            </div>
          </div>

          {/* Card 4: MFS Payment Gateway */}
          <div style={{ padding: '12px 14px', borderRadius: 8, background: 'var(--bg-subtle)', border: '1px solid var(--border-default)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6 }}>
              <span style={{ fontSize: 11, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', display: 'flex', alignItems: 'center', gap: 5 }}>
                <CreditCard size={13} color="var(--brand-primary)" />
                MFS Gateway
              </span>
              <span className={`status-pill ${Object.keys(activeNumbers).length > 0 ? 'success' : 'danger'}`} style={{ fontSize: 10.5 }}>
                <span className="status-dot" />
                {Object.keys(activeNumbers).length > 0 ? `${Object.keys(activeNumbers).length} Active` : 'NO NUMBERS'}
              </span>
            </div>
            <div style={{ fontSize: 12.5, fontWeight: 700, color: 'var(--text-primary)' }}>
              {Object.keys(activeNumbers).length > 0 ? Object.keys(activeNumbers).join(', ') : 'No receiving numbers'}
            </div>
            <div style={{ fontSize: 11, color: 'var(--text-muted)', marginTop: 4 }}>
              Fee: {diagnostics?.gateway?.fee_bearer === 'CUSTOMER' ? 'Customer Paid (1.5%)' : 'Merchant Borne (0%)'}
            </div>
          </div>
        </div>

        {/* Detected Issues Alert Box */}
        <div style={{ marginTop: 16 }}>
          {issues.length === 0 ? (
            <div style={{
              display: 'flex',
              alignItems: 'center',
              gap: 10,
              padding: '10px 14px',
              borderRadius: 8,
              background: 'rgba(16,185,129,0.08)',
              border: '1px solid rgba(16,185,129,0.3)',
              color: 'var(--success)',
              fontSize: 12.5,
              fontWeight: 600
            }}>
              <CheckCircle2 size={16} />
              <span>All Systems Operational — Storefront, gateway, database, and SMS terminal are fully active with zero bottlenecks detected.</span>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
              <div style={{ fontSize: 12, fontWeight: 800, color: 'var(--text-secondary)', textTransform: 'uppercase', letterSpacing: '0.4px', display: 'flex', alignItems: 'center', gap: 6 }}>
                <AlertTriangle size={14} color="#EF4444" />
                <span>Detected Operational Issues & Diagnostics ({issues.length}):</span>
              </div>
              {issues.map((iss, idx) => {
                const isCrit = iss.level === 'CRITICAL';
                return (
                  <div
                    key={idx}
                    style={{
                      display: 'flex',
                      alignItems: 'flex-start',
                      justifyContent: 'space-between',
                      flexWrap: 'wrap',
                      gap: 10,
                      padding: '10px 14px',
                      borderRadius: 8,
                      background: isCrit ? 'rgba(239,68,68,0.08)' : 'rgba(245,158,11,0.08)',
                      border: `1px solid ${isCrit ? 'rgba(239,68,68,0.3)' : 'rgba(245,158,11,0.3)'}`,
                      fontSize: 12
                    }}
                  >
                    <div style={{ display: 'flex', alignItems: 'flex-start', gap: 10 }}>
                      <div style={{ marginTop: 2, color: isCrit ? '#EF4444' : '#F59E0B' }}>
                        {isCrit ? <AlertOctagon size={16} /> : <AlertTriangle size={16} />}
                      </div>
                      <div>
                        <div style={{ fontWeight: 700, color: isCrit ? '#EF4444' : '#D97706', fontSize: 12.5 }}>
                          [{iss.category}] {iss.title}
                        </div>
                        <div style={{ color: 'var(--text-secondary)', marginTop: 2 }}>
                          {iss.message}
                        </div>
                      </div>
                    </div>
                    {iss.action && (
                      <span style={{
                        fontSize: 11,
                        fontWeight: 700,
                        padding: '3px 8px',
                        borderRadius: 6,
                        background: 'var(--bg-surface)',
                        border: '1px solid var(--border-default)',
                        color: 'var(--text-primary)',
                        alignSelf: 'center'
                      }}>
                        💡 {iss.action}
                      </span>
                    )}
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>

      {/* ──────────────── 2. STOREFRONT & PAYMENT GATEWAY URLS DIRECTORY ──────────────── */}
      <div className="card" style={{ padding: 20 }}>
        <h3 style={{ margin: '0 0 14px', display: 'flex', alignItems: 'center', gap: 8, fontSize: 15, fontWeight: 700 }}>
          <Link2 size={16} color="var(--brand-primary)" />
          <span>Storefront & Payment Gateway URLs</span>
          <span style={{ fontSize: 11, color: 'var(--text-muted)', fontWeight: 500 }}>(Live Endpoints & Integration Links)</span>
        </h3>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: 12 }}>
          {/* URL 1: Customer Storefront */}
          <div style={{ padding: 12, borderRadius: 8, background: 'var(--bg-subtle)', border: '1px solid var(--border-default)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 }}>
              <span style={{ fontSize: 11, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase' }}>
                Customer Storefront URL
              </span>
              <span style={{ fontSize: 10.5, fontWeight: 700, color: storefrontUrl ? 'var(--success)' : 'var(--text-muted)' }}>
                {storefrontUrl ? '● Active' : '○ Not Configured'}
              </span>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 4 }}>
              <input
                className="input"
                readOnly
                value={storefrontUrl || 'Storefront not provisioned'}
                style={{ fontSize: 11.5, fontFamily: 'var(--font-mono)', background: 'var(--bg-surface)' }}
              />
              {storefrontUrl && (
                <>
                  <button
                    className="btn btn-secondary btn-sm"
                    onClick={() => copyToClipboard(storefrontUrl, 'storefront_url')}
                    title="Copy Storefront URL"
                  >
                    <Copy size={12} color={copiedField === 'storefront_url' ? 'var(--success)' : 'currentColor'} />
                  </button>
                  <a href={storefrontUrl} target="_blank" rel="noreferrer" className="btn btn-primary btn-sm" title="Open Storefront in New Tab">
                    <ExternalLink size={12} />
                  </a>
                </>
              )}
            </div>
          </div>

          {/* URL 2: Storefront Merchant Admin */}
          <div style={{ padding: 12, borderRadius: 8, background: 'var(--bg-subtle)', border: '1px solid var(--border-default)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 }}>
              <span style={{ fontSize: 11, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase' }}>
                Storefront Admin Panel URL
              </span>
              <span style={{ fontSize: 10.5, fontWeight: 700, color: storefrontAdminUrl ? 'var(--success)' : 'var(--text-muted)' }}>
                {storefrontAdminUrl ? '● Web Admin' : '○ Disabled'}
              </span>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 4 }}>
              <input
                className="input"
                readOnly
                value={storefrontAdminUrl || 'Admin login not provisioned'}
                style={{ fontSize: 11.5, fontFamily: 'var(--font-mono)', background: 'var(--bg-surface)' }}
              />
              {storefrontAdminUrl && (
                <>
                  <button
                    className="btn btn-secondary btn-sm"
                    onClick={() => copyToClipboard(storefrontAdminUrl, 'admin_url')}
                    title="Copy Admin Panel URL"
                  >
                    <Copy size={12} color={copiedField === 'admin_url' ? 'var(--success)' : 'currentColor'} />
                  </button>
                  <a href={storefrontAdminUrl} target="_blank" rel="noreferrer" className="btn btn-secondary btn-sm" title="Open Storefront Admin">
                    <ExternalLink size={12} />
                  </a>
                </>
              )}
            </div>
          </div>

          {/* URL 3: Payment Checkout Widget */}
          <div style={{ padding: 12, borderRadius: 8, background: 'var(--bg-subtle)', border: '1px solid var(--border-default)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 }}>
              <span style={{ fontSize: 11, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase' }}>
                Hosted Checkout Widget URL
              </span>
              <span style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--success)' }}>
                ● Universal Embed
              </span>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 4 }}>
              <input
                className="input"
                readOnly
                value={checkoutWidgetUrl}
                style={{ fontSize: 11.5, fontFamily: 'var(--font-mono)', background: 'var(--bg-surface)' }}
              />
              <button
                className="btn btn-secondary btn-sm"
                onClick={() => copyToClipboard(checkoutWidgetUrl, 'widget_url')}
                title="Copy Checkout Widget URL"
              >
                <Copy size={12} color={copiedField === 'widget_url' ? 'var(--success)' : 'currentColor'} />
              </button>
              <a href={checkoutWidgetUrl} target="_blank" rel="noreferrer" className="btn btn-primary btn-sm" title="Test Checkout Widget">
                <ExternalLink size={12} />
              </a>
            </div>
          </div>

          {/* URL 4: Hosted Payment Form */}
          <div style={{ padding: 12, borderRadius: 8, background: 'var(--bg-subtle)', border: '1px solid var(--border-default)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 }}>
              <span style={{ fontSize: 11, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase' }}>
                Hosted Dynamic Payment Form URL
              </span>
              <span style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--success)' }}>
                ● Direct Pay Link
              </span>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 4 }}>
              <input
                className="input"
                readOnly
                value={checkoutFormUrl}
                style={{ fontSize: 11.5, fontFamily: 'var(--font-mono)', background: 'var(--bg-surface)' }}
              />
              <button
                className="btn btn-secondary btn-sm"
                onClick={() => copyToClipboard(checkoutFormUrl, 'form_url')}
                title="Copy Form URL"
              >
                <Copy size={12} color={copiedField === 'form_url' ? 'var(--success)' : 'currentColor'} />
              </button>
              <a href={checkoutFormUrl} target="_blank" rel="noreferrer" className="btn btn-secondary btn-sm" title="Test Payment Form">
                <ExternalLink size={12} />
              </a>
            </div>
          </div>
        </div>
      </div>

      {/* ──────────────── 3. MFS PAYMENT GATEWAY CONFIGURATION BREAKDOWN ──────────────── */}
      <div className="card" style={{ padding: 20 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
          <h3 style={{ margin: 0, display: 'flex', alignItems: 'center', gap: 8, fontSize: 15, fontWeight: 700 }}>
            <CreditCard size={16} color="var(--brand-primary)" />
            <span>MFS Payment Gateway Receiving Numbers & Setup</span>
          </h3>
          <span style={{ fontSize: 12, fontWeight: 700, color: Object.keys(activeNumbers).length > 0 ? 'var(--success)' : 'var(--danger)' }}>
            {Object.keys(activeNumbers).length} / 4 Providers Configured
          </span>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 12, marginBottom: 14 }}>
          {[
            { key: 'bKash', name: 'bKash', color: '#D12053', ussd: '*247#', defaultMode: 'Send Money / Payment' },
            { key: 'Nagad', name: 'Nagad', color: '#F7941D', ussd: '*167#', defaultMode: 'Send Money' },
            { key: 'Rocket', name: 'Rocket (DBBL)', color: '#8C3494', ussd: '*322#', defaultMode: 'Send Money' },
            { key: 'Upay', name: 'Upay (UCB)', color: '#005696', ussd: '*268#', defaultMode: 'Send Money' },
          ].map(prov => {
            const num = receivingNumbers[prov.key] || receivingNumbers[prov.key.toLowerCase()] || '';
            const isConfigured = Boolean(num && num.length >= 10);

            return (
              <div
                key={prov.key}
                style={{
                  padding: 14,
                  borderRadius: 8,
                  background: isConfigured ? 'var(--bg-surface)' : 'var(--bg-subtle)',
                  border: isConfigured ? `1.5px solid ${prov.color}` : '1px dashed var(--border-default)',
                  boxShadow: isConfigured ? '0 2px 8px -2px rgba(0,0,0,0.05)' : 'none'
                }}
              >
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                  <span style={{ fontWeight: 800, fontSize: 14, color: prov.color }}>
                    {prov.name}
                  </span>
                  <span className={`status-pill ${isConfigured ? 'success' : 'danger'}`} style={{ fontSize: 10 }}>
                    <span className="status-dot" />
                    {isConfigured ? 'CONFIGURED' : 'NOT SET'}
                  </span>
                </div>
                <div style={{ fontSize: 11, color: 'var(--text-muted)', marginBottom: 2 }}>
                  Receiving Phone Number:
                </div>
                <div style={{ fontSize: 14, fontWeight: 800, fontFamily: 'var(--font-mono)', color: isConfigured ? 'var(--text-primary)' : 'var(--text-muted)' }}>
                  {isConfigured ? num : '— Missing Number —'}
                </div>
                <div style={{ fontSize: 10.5, color: 'var(--text-secondary)', marginTop: 6, display: 'flex', justifyContent: 'space-between' }}>
                  <span>USSD: {prov.ussd}</span>
                  <span>Type: Personal</span>
                </div>
              </div>
            );
          })}
        </div>

        {/* Limits & Rules */}
        <div style={{
          padding: '10px 14px',
          borderRadius: 8,
          background: 'var(--bg-subtle)',
          border: '1px solid var(--border-default)',
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          flexWrap: 'wrap',
          gap: 12,
          fontSize: 12
        }}>
          <div>
            <strong>Fee Bearer:</strong>{' '}
            <span style={{ color: 'var(--brand-primary)', fontWeight: 700 }}>
              {diagnostics?.gateway?.fee_bearer || 'MERCHANT'}
            </span>
          </div>
          <div>
            <strong>Min Limit:</strong>{' '}
            <span style={{ fontFamily: 'var(--font-mono)', fontWeight: 700 }}>
              ৳{Number(diagnostics?.gateway?.min_amount || 10).toLocaleString()}
            </span>
          </div>
          <div>
            <strong>Max Limit:</strong>{' '}
            <span style={{ fontFamily: 'var(--font-mono)', fontWeight: 700 }}>
              ৳{Number(diagnostics?.gateway?.max_amount || 500000).toLocaleString()}
            </span>
          </div>
          <div>
            <strong>Supported Currency:</strong>{' '}
            <span style={{ fontWeight: 700 }}>BDT (৳)</span>
          </div>
        </div>
      </div>

      {/* ──────────────── 4. MERCHANT PROFILE & DYNAMIC API KEYS ──────────────── */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(340px, 1fr))', gap: 14 }}>
        {/* Profile Card */}
        <div className="card">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
            <h3 style={{ margin: 0, display: 'flex', alignItems: 'center', gap: 8, fontSize: 14, fontWeight: 700 }}>
              <User size={16} color="var(--brand-primary)" />
              Merchant Identity Profile
            </h3>
            <div style={{ display: 'flex', gap: 6 }}>
              <button
                onClick={handleDeleteMerchant}
                disabled={deleteLoading}
                className="btn btn-sm"
                style={{ background: 'rgba(239,68,68,0.12)', color: 'var(--danger)', border: '1px solid var(--danger)', fontSize: 11 }}
                title="Permanently delete merchant account and all associated records"
              >
                <Trash2 size={12} />
                <span>{deleteLoading ? 'Deleting...' : 'Delete'}</span>
              </button>
            </div>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, fontSize: 12.5 }}>
            <div><strong>Business Name:</strong> {merchant.business_name || merchant.name || '--'}</div>
            <div><strong>Email:</strong> {merchant.email || '--'}</div>
            <div><strong>Phone:</strong> {merchant.phone || '--'}</div>
            <div><strong>Business Type:</strong> {merchant.business_type || 'RETAIL'}</div>
            <div><strong>Subscription Tier:</strong> <span style={{ fontWeight: 700, color: '#7E22CE' }}>{merchant.subscription_tier || 'STARTER'}</span></div>
            <div>
              <strong>Status:</strong>{' '}
              <span className={`status-pill ${merchant.status === 'SUSPENDED' ? 'danger' : 'success'}`} style={{ display: 'inline-flex', verticalAlign: 'middle', marginLeft: 4 }}>
                <span className="status-dot" />
                {merchant.status || 'ACTIVE'}
              </span>
            </div>
            <div><strong>Webhook Secret:</strong> <code style={{ fontSize: 11 }}>{merchant.webhook_secret ? merchant.webhook_secret.slice(0, 14) + '...' : '--'}</code></div>
            <div><strong>Registered:</strong> {merchant.created_at ? new Date(merchant.created_at).toLocaleDateString() : '--'}</div>
          </div>
        </div>

        {/* Dynamic API Keys Card */}
        <div className="card">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 }}>
            <h3 style={{ margin: 0, display: 'flex', alignItems: 'center', gap: 8, fontSize: 14, fontWeight: 700 }}>
              <Key size={16} color="var(--brand-primary)" />
              Dynamic API Keys ({apiKeys.length})
            </h3>
            <button
              className="btn btn-primary btn-sm"
              disabled={keyActionLoading}
              onClick={handleGenerateApiKey}
              style={{ fontSize: 11, padding: '4px 10px' }}
            >
              {keyActionLoading ? 'Working...' : '+ Generate Key'}
            </button>
          </div>

          {keyActionResult && (
            <div style={{
              padding: '6px 10px',
              borderRadius: 6,
              fontSize: 11,
              fontWeight: 600,
              marginBottom: 10,
              background: keyActionResult.startsWith('✅') ? 'rgba(16,185,129,0.12)' : 'rgba(239,68,68,0.12)',
              color: keyActionResult.startsWith('✅') ? '#10B981' : '#EF4444',
              border: `1px solid ${keyActionResult.startsWith('✅') ? '#10B981' : '#EF4444'}`
            }}>
              {keyActionResult}
            </div>
          )}

          {newlyGeneratedKey && (
            <div style={{
              padding: 10,
              borderRadius: 8,
              background: 'rgba(245,158,11,0.12)',
              border: '1px solid #F59E0B',
              marginBottom: 10
            }}>
              <div style={{ fontSize: 11, fontWeight: 700, color: '#F59E0B', marginBottom: 4 }}>
                ⚠️ NEW RAW KEY (SHOWN ONCE):
              </div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                <input
                  className="input"
                  readOnly
                  value={newlyGeneratedKey}
                  style={{ fontFamily: 'var(--font-mono)', fontSize: 11, fontWeight: 700, background: 'var(--bg-card)', padding: '4px 6px', flex: 1 }}
                />
                <button
                  className="btn btn-secondary btn-sm"
                  style={{ fontSize: 11, padding: '4px 8px' }}
                  onClick={() => {
                    navigator.clipboard.writeText(newlyGeneratedKey);
                    alert('API key copied to clipboard!');
                  }}
                >
                  Copy
                </button>
              </div>
            </div>
          )}

          {apiKeys.length === 0 ? (
            <div style={{ textAlign: 'center', padding: '12px 0' }}>
              <p style={{ fontSize: 12, color: 'var(--text-muted)', margin: '0 0 8px' }}>No dynamic API keys generated yet.</p>
              <button className="btn btn-primary btn-sm" onClick={handleGenerateApiKey} disabled={keyActionLoading}>
                Generate Dynamic API Key
              </button>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 6, maxHeight: 180, overflowY: 'auto' }}>
              {apiKeys.map(k => (
                <div key={k.id} style={{ padding: 8, background: 'var(--bg-subtle)', borderRadius: 6, border: '1px solid var(--border-default)', fontSize: 11.5 }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div style={{ fontWeight: 700 }}>{k.label || 'Dynamic Gateway Key'}</div>
                    {!k.revoked && (
                      <button
                        className="btn btn-danger btn-sm"
                        style={{ fontSize: 10, padding: '2px 6px' }}
                        disabled={keyActionLoading}
                        onClick={() => handleRevokeApiKey(k.id)}
                      >
                        Revoke
                      </button>
                    )}
                  </div>
                  <div style={{ fontSize: 11, color: 'var(--text-secondary)', marginTop: 2 }}>
                    <code>{k.key_preview}</code>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 4 }}>
                    <span style={{ fontSize: 10, color: k.revoked ? 'var(--danger)' : 'var(--success)', fontWeight: 700 }}>
                      {k.revoked ? 'REVOKED' : 'ACTIVE'}
                    </span>
                    <span style={{ fontSize: 10, color: 'var(--text-muted)' }}>
                      {new Date(k.created_at).toLocaleDateString()}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* ──────────────── 5. KYC IDENTITY & BIOMETRIC VERIFICATION CARD ──────────────── */}
      <div className="card" style={{ border: merchant.kyc_status === 'PENDING' ? '1.5px solid var(--warning)' : undefined }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 10, marginBottom: 14 }}>
          <div>
            <h3 style={{ margin: 0, display: 'flex', alignItems: 'center', gap: 8, fontSize: 15, fontWeight: 700 }}>
              <ShieldCheck size={18} color="var(--brand-primary)" />
              <span>KYC Identity & Biometric Verification</span>
              <span className={`status-pill ${
                merchant.kyc_status === 'VERIFIED' ? 'success' :
                merchant.kyc_status === 'REJECTED' ? 'danger' :
                merchant.kyc_status === 'PENDING' ? 'warning' : 'neutral'
              }`} style={{ fontSize: 11 }}>
                <span className="status-dot" />
                {merchant.kyc_status === 'VERIFIED' ? 'VERIFIED' :
                 merchant.kyc_status === 'REJECTED' ? 'REJECTED' :
                 merchant.kyc_status === 'PENDING' ? 'PENDING REVIEW' : 'UNVERIFIED'}
              </span>
            </h3>
            <p style={{ margin: '4px 0 0', fontSize: 12, color: 'var(--text-secondary)' }}>
              Bangladesh NID OCR Document Check & ML Kit Biometric Live Face Verification
            </p>
          </div>

          <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
            {merchant.kyc_status !== 'VERIFIED' && (
              <button
                className="btn btn-primary btn-sm"
                disabled={kycProcessing}
                onClick={() => handleReviewKyc('APPROVE')}
              >
                <Check size={13} />
                <span>{kycProcessing ? 'Processing...' : 'Approve KYC'}</span>
              </button>
            )}
            {merchant.kyc_status !== 'REJECTED' && !showRejectBox && (
              <button
                className="btn btn-danger btn-sm"
                disabled={kycProcessing}
                onClick={() => setShowRejectBox(true)}
              >
                <X size={13} />
                <span>Reject KYC</span>
              </button>
            )}
          </div>
        </div>

        {kycFeedback && (
          <div style={{ padding: '8px 12px', background: 'var(--bg-subtle)', borderRadius: 6, marginBottom: 12, fontSize: 12, fontWeight: 600, border: '1px solid var(--border-default)' }}>
            {kycFeedback}
          </div>
        )}

        {showRejectBox && (
          <div style={{ padding: 12, background: 'var(--danger-subtle)', border: '1px solid var(--danger-border)', borderRadius: 8, marginBottom: 14 }}>
            <h4 style={{ margin: '0 0 8px', color: 'var(--danger)', fontSize: 13 }}>Specify KYC Rejection Reason</h4>
            <input
              className="input"
              value={rejectionReasonInput}
              onChange={e => setRejectionReasonInput(e.target.value)}
              placeholder="e.g. Blurry NID card image, numbers unreadable, or face mismatch..."
              style={{ marginBottom: 8, background: 'var(--bg-surface)' }}
            />
            <div style={{ display: 'flex', gap: 8 }}>
              <button
                className="btn btn-danger btn-sm"
                disabled={kycProcessing}
                onClick={() => handleReviewKyc('REJECT')}
              >
                Confirm Rejection
              </button>
              <button
                className="btn btn-secondary btn-sm"
                onClick={() => setShowRejectBox(false)}
              >
                Cancel
              </button>
            </div>
          </div>
        )}

        {/* NID Data Fields */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: 12, padding: 12, background: 'var(--bg-subtle)', borderRadius: 8, border: '1px solid var(--border-default)', marginBottom: 14 }}>
          <div>
            <div style={{ fontSize: 11, color: 'var(--text-muted)', textTransform: 'uppercase', fontWeight: 700 }}>National ID Number</div>
            <div style={{ fontSize: 15, fontWeight: 800, color: 'var(--text-primary)', fontFamily: 'var(--font-mono)', marginTop: 2 }}>
              {merchant.nid_number || '--'}
            </div>
          </div>
          <div>
            <div style={{ fontSize: 11, color: 'var(--text-muted)', textTransform: 'uppercase', fontWeight: 700 }}>Name on NID</div>
            <div style={{ fontSize: 14, fontWeight: 700, color: 'var(--text-primary)', marginTop: 2 }}>
              {merchant.nid_name || merchant.business_name || '--'}
            </div>
          </div>
          <div>
            <div style={{ fontSize: 11, color: 'var(--text-muted)', textTransform: 'uppercase', fontWeight: 700 }}>Date of Birth</div>
            <div style={{ fontSize: 14, fontWeight: 700, color: 'var(--text-primary)', marginTop: 2 }}>
              {merchant.nid_dob || '--'}
            </div>
          </div>
          <div>
            <div style={{ fontSize: 11, color: 'var(--text-muted)', textTransform: 'uppercase', fontWeight: 700 }}>Submitted At</div>
            <div style={{ fontSize: 13, color: 'var(--text-secondary)', marginTop: 2 }}>
              {merchant.kyc_submitted_at ? new Date(merchant.kyc_submitted_at).toLocaleString() : '--'}
            </div>
          </div>
          {merchant.kyc_rejection_reason && (
            <div style={{ gridColumn: '1 / -1', color: 'var(--danger)', fontSize: 12, background: 'var(--danger-subtle)', padding: 8, borderRadius: 6, border: '1px solid var(--danger-border)' }}>
              <strong>Rejection Reason:</strong> {merchant.kyc_rejection_reason}
            </div>
          )}
        </div>

        {/* Document Photos Inspection Gallery */}
        <div>
          <h4 style={{ margin: '0 0 10px', fontSize: 13, color: 'var(--text-primary)', display: 'flex', alignItems: 'center', gap: 7 }}>
            <FileText size={15} color="var(--brand-primary)" />
            Document & Biometric Scan Evidence
          </h4>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 14 }}>
            {/* Front Document */}
            <div style={{ border: '1px solid var(--border-default)', borderRadius: 8, overflow: 'hidden', background: 'var(--bg-surface)' }}>
              <div style={{ padding: '8px 10px', background: 'var(--bg-subtle)', fontSize: 12, fontWeight: 700, display: 'flex', justifyContent: 'space-between' }}>
                <span>NID Front Document</span>
                <span style={{ fontSize: 10, color: 'var(--text-muted)' }}>Card Front</span>
              </div>
              <div style={{ height: 160, display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#0F172A', cursor: 'pointer', overflow: 'hidden' }}
                   onClick={() => formatDocUrl(merchant.nid_front_url) && setPreviewImage(formatDocUrl(merchant.nid_front_url))}>
                {formatDocUrl(merchant.nid_front_url) ? (
                  <img src={formatDocUrl(merchant.nid_front_url)!} alt="NID Front" style={{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }} />
                ) : (
                  <div style={{ color: 'var(--text-muted)', fontSize: 12, textAlign: 'center', padding: 10 }}>
                    No Front Document Uploaded
                  </div>
                )}
              </div>
              {formatDocUrl(merchant.nid_front_url) && (
                <div style={{ padding: 6, textAlign: 'center' }}>
                  <button className="btn btn-secondary btn-sm" style={{ fontSize: 11 }} onClick={() => setPreviewImage(formatDocUrl(merchant.nid_front_url))}>
                    <Maximize2 size={11} />
                    View Fullscreen
                  </button>
                </div>
              )}
            </div>

            {/* Back Document */}
            <div style={{ border: '1px solid var(--border-default)', borderRadius: 8, overflow: 'hidden', background: 'var(--bg-surface)' }}>
              <div style={{ padding: '8px 10px', background: 'var(--bg-subtle)', fontSize: 12, fontWeight: 700, display: 'flex', justifyContent: 'space-between' }}>
                <span>NID Back Document</span>
                <span style={{ fontSize: 10, color: 'var(--text-muted)' }}>Card Back</span>
              </div>
              <div style={{ height: 160, display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#0F172A', cursor: 'pointer', overflow: 'hidden' }}
                   onClick={() => formatDocUrl(merchant.nid_back_url) && setPreviewImage(formatDocUrl(merchant.nid_back_url))}>
                {formatDocUrl(merchant.nid_back_url) ? (
                  <img src={formatDocUrl(merchant.nid_back_url)!} alt="NID Back" style={{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }} />
                ) : (
                  <div style={{ color: 'var(--text-muted)', fontSize: 12, textAlign: 'center', padding: 10 }}>
                    No Back Document Uploaded
                  </div>
                )}
              </div>
              {formatDocUrl(merchant.nid_back_url) && (
                <div style={{ padding: 6, textAlign: 'center' }}>
                  <button className="btn btn-secondary btn-sm" style={{ fontSize: 11 }} onClick={() => setPreviewImage(formatDocUrl(merchant.nid_back_url))}>
                    <Maximize2 size={11} />
                    View Fullscreen
                  </button>
                </div>
              )}
            </div>

            {/* Face Biometric Selfie */}
            <div style={{ border: '1px solid var(--border-default)', borderRadius: 8, overflow: 'hidden', background: 'var(--bg-surface)' }}>
              <div style={{ padding: '8px 10px', background: 'var(--bg-subtle)', fontSize: 12, fontWeight: 700, display: 'flex', justifyContent: 'space-between' }}>
                <span>Biometric Face Scan</span>
                <span style={{ fontSize: 10, color: 'var(--success)', fontWeight: 700 }}>ML Kit Live</span>
              </div>
              <div style={{ height: 160, display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#0F172A', cursor: 'pointer', overflow: 'hidden' }}
                   onClick={() => formatDocUrl(merchant.face_photo_url) && setPreviewImage(formatDocUrl(merchant.face_photo_url))}>
                {formatDocUrl(merchant.face_photo_url) ? (
                  <img src={formatDocUrl(merchant.face_photo_url)!} alt="Biometric Face Selfie" style={{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }} />
                ) : (
                  <div style={{ color: 'var(--text-muted)', fontSize: 12, textAlign: 'center', padding: 10 }}>
                    No Face Scan Captured
                  </div>
                )}
              </div>
              {formatDocUrl(merchant.face_photo_url) && (
                <div style={{ padding: 6, textAlign: 'center' }}>
                  <button className="btn btn-secondary btn-sm" style={{ fontSize: 11 }} onClick={() => setPreviewImage(formatDocUrl(merchant.face_photo_url))}>
                    <Maximize2 size={11} />
                    View Fullscreen
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Fullscreen Modal */}
        {previewImage && (
          <div
            style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.85)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 9999, padding: 20 }}
            onClick={() => setPreviewImage(null)}
          >
            <div style={{ maxWidth: '90vw', maxHeight: '90vh', position: 'relative' }} onClick={e => e.stopPropagation()}>
              <img src={previewImage} alt="Document Preview" style={{ maxWidth: '100%', maxHeight: '85vh', borderRadius: 8, boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.5)' }} />
              <button
                className="btn btn-danger btn-icon"
                style={{ position: 'absolute', top: -12, right: -12, borderRadius: '50%', width: 34, height: 34 }}
                onClick={() => setPreviewImage(null)}
              >
                <X size={16} />
              </button>
            </div>
          </div>
        )}
      </div>

      {/* ──────────────── 6. RECENT PAYMENT TRANSACTIONS ──────────────── */}
      <div className="card">
        <h3 style={{ marginTop: 0, display: 'flex', alignItems: 'center', gap: 8, fontSize: 15, fontWeight: 700 }}>
          <CreditCard size={16} color="var(--brand-primary)" />
          Recent Payment Events ({recentEvents.length})
        </h3>
        {recentEvents.length === 0 ? (
          <p style={{ fontSize: 12, color: 'var(--text-muted)' }}>No payment transactions recorded for this merchant yet.</p>
        ) : (
          <div className="enterprise-table-container">
            <table className="enterprise-table">
              <thead>
                <tr>
                  <th>Time</th>
                  <th>Order ID</th>
                  <th>Amount</th>
                  <th>Method</th>
                  <th>Status</th>
                  <th>Trx ID</th>
                </tr>
              </thead>
              <tbody>
                {recentEvents.map(e => (
                  <tr key={e.id}>
                    <td style={{ fontSize: 11 }}>{new Date(e.recorded_at).toLocaleString()}</td>
                    <td style={{ fontFamily: 'var(--font-mono)', fontSize: 11 }}>#{e.order_id?.slice(0, 10)}...</td>
                    <td style={{ fontWeight: 700 }}>৳{Number(e.amount || 0).toLocaleString()}</td>
                    <td>{e.payment_method || 'MFS'}</td>
                    <td>
                      <span className={`status-pill ${e.status === 'PAID' ? 'success' : 'danger'}`}>
                        <span className="status-dot" />
                        {e.status}
                      </span>
                    </td>
                    <td style={{ fontFamily: 'var(--font-mono)', fontSize: 11 }}>{e.trx_id || '--'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* ──────────────── 7. WEBHOOKS & APP PIN SECURITY ──────────────── */}
      <div className="card">
        <h3 style={{ marginTop: 0, display: 'flex', alignItems: 'center', gap: 8, fontSize: 15, fontWeight: 700 }}>
          <Link2 size={16} color="var(--brand-primary)" />
          Webhook Relays & App PIN Security
        </h3>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 12, marginTop: 12 }}>
          <div>
            <label style={{ display: 'block', fontSize: 12, fontWeight: 700, marginBottom: 4 }}>Merchant Webhook Listener URL (POST)</label>
            <input
              className="input"
              value={webhookUrl}
              onChange={e => setWebhookUrl(e.target.value)}
              placeholder="https://merchant-store.com/api/webhooks/swapnopay"
            />
            <p style={{ fontSize: 11, color: 'var(--text-muted)', marginTop: 4 }}>
              SwapnoPay backend sends HMAC-SHA256 signed payment payloads to this URL on successful payment verification.
            </p>
          </div>

          <div style={{ display: 'flex', gap: 8, marginTop: 6 }}>
            <button className="btn btn-primary btn-sm" onClick={handleSaveConnection}>
              <Save size={13} />
              Save Connection Settings
            </button>
            {webhookUrl && (
              <button
                className="btn btn-secondary btn-sm"
                onClick={async () => {
                  setTestResult('Sending test webhook ping...');
                  try {
                    const res = await fetch(webhookUrl, {
                      method: 'POST',
                      headers: { 'Content-Type': 'application/json' },
                      body: JSON.stringify({ event: 'TEST_PING', merchant_id: id, timestamp: new Date().toISOString() })
                    });
                    setTestResult(`Webhook response HTTP ${res.status}`);
                  } catch (err: any) {
                    setTestResult('Test webhook error: ' + err.message);
                  }
                }}
              >
                <Activity size={13} />
                Test Webhook Ping
              </button>
            )}
          </div>

          {testResult && (
            <div style={{ padding: 10, background: 'var(--bg-subtle)', borderRadius: 6, fontSize: 12, fontWeight: 600, border: '1px solid var(--border-default)' }}>
              {testResult}
            </div>
          )}
        </div>

        {/* ── App PIN Security Sub-Card ── */}
        <div style={{ background: 'var(--bg-subtle)', borderRadius: 8, border: '1px solid var(--border-default)', padding: 16, marginTop: 16 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 6 }}>
            <Lock size={16} color="var(--brand-primary)" />
            <span style={{ fontWeight: 700, fontSize: 13.5 }}>Merchant App PIN Security</span>
          </div>
          <p style={{ fontSize: 12, color: 'var(--text-secondary)', marginBottom: 12 }}>
            The merchant's 4-digit app PIN is stored as a SHA-256 hash in Supabase. Admin can clear or force-reset the PIN if a merchant is locked out.
          </p>

          <div style={{ display: 'flex', gap: 10, marginBottom: 14, flexWrap: 'wrap' }}>
            <div style={{
              display: 'flex', alignItems: 'center', gap: 6, padding: '5px 12px', borderRadius: 20, fontSize: 12, fontWeight: 600,
              background: pinStatus?.pin_set ? 'rgba(16,185,129,0.12)' : 'rgba(239,68,68,0.12)',
              color: pinStatus?.pin_set ? '#10B981' : '#EF4444',
              border: `1px solid ${pinStatus?.pin_set ? '#10B981' : '#EF4444'}`
            }}>
              {pinStatus?.pin_set ? <Check size={13} /> : <X size={13} />}
              {pinStatus?.pin_set ? 'PIN Configured' : 'No PIN Set'}
            </div>
            {pinStatus?.pin_reset_requested && (
              <div style={{
                display: 'flex', alignItems: 'center', gap: 6, padding: '5px 12px', borderRadius: 20, fontSize: 12, fontWeight: 600,
                background: 'rgba(245,158,11,0.12)', color: '#F59E0B', border: '1px solid #F59E0B'
              }}>
                <AlertTriangle size={13} />
                Forced Reset Active
              </div>
            )}
          </div>

          {pinActionResult && (
            <div style={{
              padding: '8px 12px', borderRadius: 6, fontSize: 12, fontWeight: 600, marginBottom: 12,
              background: pinActionResult.startsWith('✅') ? 'rgba(16,185,129,0.12)' : 'rgba(239,68,68,0.12)',
              color: pinActionResult.startsWith('✅') ? '#10B981' : '#EF4444',
              border: `1px solid ${pinActionResult.startsWith('✅') ? '#10B981' : '#EF4444'}`
            }}>
              {pinActionResult}
            </div>
          )}

          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            <button
              disabled={pinActionLoading || !pinStatus?.pin_set}
              onClick={async () => {
                const targetId = merchant?.id || id;
                if (!targetId || !window.confirm('Clear this merchant\'s PIN? They will need to set a new PIN on next login.')) return;
                setPinActionLoading(true);
                setPinActionResult(null);
                try {
                  await clearMerchantPin(targetId);
                  setPinStatus({ pin_set: false, pin_reset_requested: true });
                  setPinActionResult('✅ PIN cleared. Merchant must set a new PIN on next login.');
                } catch (err: any) {
                  setPinActionResult('❌ ' + err.message);
                } finally { setPinActionLoading(false); }
              }}
              className="btn btn-secondary btn-sm"
              style={{ color: 'var(--danger)' }}
            >
              <Trash2 size={13} />
              {pinActionLoading ? 'Clearing...' : 'Clear PIN'}
            </button>

            <button
              disabled={pinActionLoading || !pinStatus?.pin_set || pinStatus?.pin_reset_requested}
              onClick={async () => {
                const targetId = merchant?.id || id;
                if (!targetId) return;
                setPinActionLoading(true);
                setPinActionResult(null);
                try {
                  await forceRequestPinReset(targetId);
                  setPinStatus(prev => prev ? { ...prev, pin_reset_requested: true } : prev);
                  setPinActionResult('✅ PIN marked for forced reset on next sync.');
                } catch (err: any) {
                  setPinActionResult('❌ ' + err.message);
                } finally { setPinActionLoading(false); }
              }}
              className="btn btn-secondary btn-sm"
            >
              <RefreshCw size={13} />
              {pinActionLoading ? 'Working...' : 'Force Reset'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
