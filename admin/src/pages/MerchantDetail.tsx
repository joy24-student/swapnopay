import React, { useEffect, useState } from 'react';
import { adminSupabase, reviewMerchantIdentity, formatKycImageUrl, getMerchantPinStatus, clearMerchantPin, forceRequestPinReset, generateMerchantApiKey, revokeMerchantApiKey } from '../adminSupabaseClient';
import { useParams, Link } from 'react-router-dom';
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
  Lock,
  Trash2,
  RefreshCw,
} from 'lucide-react';

export default function MerchantDetail() {
  const { id } = useParams();
  const [merchant, setMerchant] = useState<any | null>(null);
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

      setLoading(false);
    })();
  }, [id]);

  const handleSaveConnection = async () => {
    if (!id) return;
    setTestResult('Saving connections...');
    try {
      // Upsert into Admin Supabase merchant_connections
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

      // Update Supabase
      const { error } = await adminSupabase
        .from('merchants')
        .update(updateData)
        .eq('id', id);

      if (error) throw error;

      // Also call backend review endpoint if available (use helper to include auth)
      try {
        await reviewMerchantIdentity(id, action, rejectionReasonInput.trim());
      } catch (e: any) {
        console.warn('[MerchantDetail] backend KYC review failed:', e?.message || e)
      }

      setMerchant((prev: any) => ({ ...prev, ...updateData }));
      setKycFeedback(`KYC verification successfully ${action === 'APPROVE' ? 'approved and verified' : 'rejected'}.`);
      setShowRejectBox(false);
      setRejectionReasonInput('');
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
    } catch (e: any) {
      setKeyActionResult('❌ ' + e.message);
    } finally {
      setKeyActionLoading(false);
    }
  };

  if (loading || !merchant) {
    return <div className="container"><div className="card">Loading merchant profile...</div></div>;
  }

  return (
    <div className="container">
      {/* Header */}
      <div className="header">
        <div>
          <h1>Merchant: {merchant.business_name || merchant.title || merchant.name}</h1>
          <p style={{ margin: 0, color: '#64748B', fontSize: 12 }}>ID: {merchant.id}</p>
        </div>
        <div>
          <Link to="/merchants"><button className="button">Back to List</button></Link>
        </div>
      </div>

      {/* Overview Cards */}
      <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: 14, marginBottom: 14 }}>
        <div className="card">
          <h3 style={{ marginTop: 0, display: 'flex', alignItems: 'center', gap: 8 }}>
            <User size={16} color="var(--brand-primary)" />
            Merchant Profile
          </h3>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, fontSize: 13 }}>
            <div><strong>Business Name:</strong> {merchant.business_name || merchant.name || '--'}</div>
            <div><strong>Email:</strong> {merchant.email || '--'}</div>
            <div><strong>Phone:</strong> {merchant.phone || '--'}</div>
            <div><strong>Business Type:</strong> {merchant.business_type || 'RETAIL'}</div>
            <div><strong>Subscription Tier:</strong> <span style={{ fontWeight: 700, color: '#7E22CE' }}>{merchant.subscription_tier || 'STARTER'}</span></div>
            <div><strong>Status:</strong> <span style={{ fontWeight: 700, color: merchant.status === 'SUSPENDED' ? 'var(--danger)' : 'var(--success)' }}>{merchant.status || 'ACTIVE'}</span></div>
            <div><strong>Webhook Secret:</strong> <code style={{ fontSize: 11 }}>{merchant.webhook_secret || '--'}</code></div>
            <div><strong>Created:</strong> {merchant.created_at ? new Date(merchant.created_at).toLocaleDateString() : '--'}</div>
          </div>
        </div>

        <div className="card">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 }}>
            <h3 style={{ margin: 0, display: 'flex', alignItems: 'center', gap: 8 }}>
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
              <p style={{ fontSize: 12, color: 'var(--text-muted)', margin: '0 0 8px' }}>No API keys generated yet for this merchant.</p>
              <button className="btn btn-primary btn-sm" onClick={handleGenerateApiKey} disabled={keyActionLoading}>
                Generate Dynamic API Key
              </button>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
              {apiKeys.map(k => (
                <div key={k.id} style={{ padding: 8, background: 'var(--bg-subtle)', borderRadius: 6, border: '1px solid var(--border-default)', fontSize: 12 }}>
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

      {/* ── KYC Identity & Biometric Verification Card ── */}
      <div className="card" style={{ marginBottom: 14, border: merchant.kyc_status === 'PENDING' ? '1.5px solid var(--warning)' : undefined }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 10, marginBottom: 14 }}>
          <div>
            <h3 style={{ margin: 0, display: 'flex', alignItems: 'center', gap: 8 }}>
              <ShieldCheck size={18} color="var(--brand-primary)" />
              KYC Identity & Biometric Verification
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
              Bangladesh NID Card Document OCR & ML Kit Biometric Live Face Verification
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
                {kycProcessing ? 'Processing...' : 'Approve KYC'}
              </button>
            )}
            {merchant.kyc_status !== 'REJECTED' && !showRejectBox && (
              <button
                className="btn btn-danger btn-sm"
                disabled={kycProcessing}
                onClick={() => setShowRejectBox(true)}
              >
                <X size={13} />
                Reject KYC
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

        {/* Image Fullscreen Preview Modal */}
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

      {/* Recent Payments */}
      <div className="card" style={{ marginBottom: 14 }}>
        <h3 style={{ marginTop: 0, display: 'flex', alignItems: 'center', gap: 8 }}>
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

      {/* Connections & Webhooks */}
      <div className="card">
        <h3 style={{ marginTop: 0, display: 'flex', alignItems: 'center', gap: 8 }}>
          <Link2 size={16} color="var(--brand-primary)" />
          Connection Settings & Webhook Relays
        </h3>
        <p style={{ fontSize: 12, color: 'var(--text-secondary)' }}>
          Configure custom callback endpoints and webhook listeners for this merchant.
        </p>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 12, marginTop: 12 }}>
          <div>
            <label style={{ display: 'block', fontSize: 12, fontWeight: 700, marginBottom: 4 }}>Public Checkout Endpoint (GET)</label>
            <input
              className="input"
              value={publicEndpoint}
              onChange={e => setPublicEndpoint(e.target.value)}
              placeholder="https://merchant-store.com/checkout/form"
            />
          </div>

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

        {/* ── PIN Security ──────────────────────────────────────── */}
        <div style={{ background: 'var(--bg-card)', borderRadius: 12, border: '1px solid var(--border-default)', padding: 24, marginTop: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 6 }}>
            <Lock size={18} color="var(--accent)" />
            <span style={{ fontWeight: 700, fontSize: 15 }}>App PIN Security</span>
          </div>
          <p style={{ fontSize: 13, color: 'var(--text-secondary)', marginBottom: 16 }}>
            The merchant's 4-digit app PIN is stored as a SHA-256 hash in Supabase. Raw PINs are never visible. Admin can clear the PIN to force the merchant to set a new one.
          </p>

          {/* Status badges */}
          <div style={{ display: 'flex', gap: 12, marginBottom: 20, flexWrap: 'wrap' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '6px 14px', borderRadius: 20, fontSize: 13, fontWeight: 600,
              background: pinStatus?.pin_set ? 'rgba(16,185,129,0.12)' : 'rgba(239,68,68,0.12)',
              color: pinStatus?.pin_set ? '#10B981' : '#EF4444',
              border: `1px solid ${pinStatus?.pin_set ? '#10B981' : '#EF4444'}` }}>
              {pinStatus?.pin_set ? <Check size={14} /> : <X size={14} />}
              {pinStatus?.pin_set ? 'PIN is set' : 'No PIN configured'}
            </div>
            {pinStatus?.pin_reset_requested && (
              <div style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '6px 14px', borderRadius: 20, fontSize: 13, fontWeight: 600,
                background: 'rgba(245,158,11,0.12)', color: '#F59E0B', border: '1px solid #F59E0B' }}>
                <AlertTriangle size={14} />
                Reset Requested by Merchant
              </div>
            )}
          </div>

          {/* Action result message */}
          {pinActionResult && (
            <div style={{ padding: '10px 14px', borderRadius: 8, fontSize: 13, fontWeight: 600, marginBottom: 16,
              background: pinActionResult.startsWith('✅') ? 'rgba(16,185,129,0.12)' : 'rgba(239,68,68,0.12)',
              color: pinActionResult.startsWith('✅') ? '#10B981' : '#EF4444',
              border: `1px solid ${pinActionResult.startsWith('✅') ? '#10B981' : '#EF4444'}` }}>
              {pinActionResult}
            </div>
          )}

          <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap' }}>
            {/* Clear PIN */}
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
              style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '10px 18px', borderRadius: 8, border: 'none',
                background: !pinStatus?.pin_set ? 'var(--bg-subtle)' : 'rgba(239,68,68,0.12)',
                color: !pinStatus?.pin_set ? 'var(--text-secondary)' : '#EF4444',
                fontWeight: 700, fontSize: 13, cursor: !pinStatus?.pin_set ? 'not-allowed' : 'pointer',
                outline: '1px solid currentColor' }}>
              <Trash2 size={15} />
              {pinActionLoading ? 'Clearing...' : 'Clear PIN'}
            </button>

            {/* Force Reset (mark pin_reset_requested = true) */}
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
                  setPinActionResult('✅ PIN marked for forced reset. It will be cleared on next merchant sync.');
                } catch (err: any) {
                  setPinActionResult('❌ ' + err.message);
                } finally { setPinActionLoading(false); }
              }}
              style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '10px 18px', borderRadius: 8, border: 'none',
                background: (!pinStatus?.pin_set || pinStatus?.pin_reset_requested) ? 'var(--bg-subtle)' : 'rgba(129,140,248,0.12)',
                color: (!pinStatus?.pin_set || pinStatus?.pin_reset_requested) ? 'var(--text-secondary)' : '#818CF8',
                fontWeight: 700, fontSize: 13, cursor: (!pinStatus?.pin_set || pinStatus?.pin_reset_requested) ? 'not-allowed' : 'pointer',
                outline: '1px solid currentColor' }}>
              <RefreshCw size={15} />
              {pinActionLoading ? 'Working...' : 'Force Reset (Next Sync)'}
            </button>

            {/* Refresh PIN status */}
            <button
              disabled={pinActionLoading}
              onClick={async () => {
                if (!id) return;
                setPinActionLoading(true);
                try {
                  const status = await getMerchantPinStatus(id);
                  setPinStatus(status);
                  setPinActionResult('✅ PIN status refreshed.');
                } catch (err: any) {
                  setPinActionResult('❌ ' + err.message);
                } finally { setPinActionLoading(false); }
              }}
              style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '10px 18px', borderRadius: 8, border: '1px solid var(--border-default)',
                background: 'var(--bg-subtle)', color: 'var(--text-secondary)', fontWeight: 700, fontSize: 13, cursor: 'pointer' }}>
              <RefreshCw size={15} />
              Refresh Status
            </button>
          </div>
        </div>

      </div>
    </div>
  );
}
