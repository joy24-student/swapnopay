import React, { useEffect, useState } from 'react'
import { adminSupabase, reviewMerchantIdentity, formatKycImageUrl, ADMIN_SUPABASE_URL } from '../adminSupabaseClient'
import { Link } from 'react-router-dom'
import {
  ShieldCheck,
  Search,
  CheckCircle2,
  XCircle,
  Clock,
  ExternalLink,
  User,
  CreditCard,
  FileText,
  AlertTriangle,
  Image as ImageIcon,
  Check,
  X,
  RefreshCw,
  Eye
} from 'lucide-react'

interface KycRecord {
  id: string
  user_id?: string
  business_name: string
  email?: string
  phone?: string
  nid_number?: string
  nid_name?: string
  nid_dob?: string
  nid_front_url?: string
  nid_back_url?: string
  face_photo_url?: string
  kyc_status: 'UNVERIFIED' | 'PENDING' | 'PENDING_REVIEW' | 'VERIFIED' | 'REJECTED'
  kyc_submitted_at?: string
  kyc_reviewed_at?: string
  kyc_rejection_reason?: string
  status?: string
}

export default function KycReviews() {
  const [submissions, setSubmissions] = useState<KycRecord[]>([])
  const [loading, setLoading] = useState(true)
  const [filter, setFilter] = useState<'ALL' | 'PENDING' | 'VERIFIED' | 'REJECTED'>('PENDING')
  const [search, setSearch] = useState('')
  const [selectedMerchant, setSelectedMerchant] = useState<KycRecord | null>(null)
  const [previewImage, setPreviewImage] = useState<string | null>(null)
  const [rejectReason, setRejectReason] = useState('')
  const [isRejecting, setIsRejecting] = useState(false)
  const [actionLoading, setActionLoading] = useState(false)
  const [actionMessage, setActionMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null)

  // Fetch KYC records from merchants table with backend API fallback
  async function loadKycRecords() {
    try {
      let data: any[] | null = null
      let subData: any[] | null = null

      // 1. Attempt direct Supabase PostgREST queries
      try {
        const res = await adminSupabase
          .from('merchants')
          .select('*')
          .order('kyc_submitted_at', { ascending: false, nullsFirst: false })
        if (!res.error && res.data) {
          data = res.data
        }
      } catch (err: any) {
        console.warn('[KycReviews] Direct merchants query notice:', err.message)
      }

      try {
        const res = await adminSupabase
          .from('merchant_kyc_submissions')
          .select('*')
          .order('created_at', { ascending: false })
        if (!res.error && res.data) {
          subData = res.data
        }
      } catch (err: any) {
        console.warn('[KycReviews] Direct kyc_submissions query notice:', err.message)
      }

      // 2. Resilient fallback: backend API via Master Key / Admin Auth
      const masterSecret = typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('swapnopay_admin_secret') : null
      const base = (import.meta as any).env?.VITE_BACKEND_URL || 'https://api.swapnopay.top'
      const { data: { session } } = await adminSupabase.auth.getSession()

      const backendHeaders: Record<string, string> = { 'Accept': 'application/json' }
      if (masterSecret) backendHeaders['X-Admin-Secret'] = masterSecret
      if (session?.access_token) backendHeaders['Authorization'] = `Bearer ${session.access_token}`

      if (!data || data.length === 0) {
        try {
          const res = await fetch(`${base.replace(/\/$/, '')}/v1/admin/merchants`, { headers: backendHeaders })
          if (res.ok) {
            const json = await res.json()
            if (json.ok && Array.isArray(json.merchants)) {
              data = json.merchants
            }
          }
        } catch (bkErr: any) {
          console.warn('[KycReviews] Backend merchants fetch notice:', bkErr.message)
        }
      }

      if (!subData || subData.length === 0) {
        try {
          const res = await fetch(`${base.replace(/\/$/, '')}/v1/kyc/submissions`, { headers: backendHeaders })
          if (res.ok) {
            const json = await res.json()
            if (json.ok && Array.isArray(json.submissions)) {
              subData = json.submissions
            }
          }
        } catch (bkErr: any) {
          console.warn('[KycReviews] Backend submissions fetch notice:', bkErr.message)
        }
      }

      if (data && data.length > 0) {
        const mergedList = (data as KycRecord[]).map(m => {
          const sub = subData?.find(s => s.merchant_id === m.id || s.merchant_id === m.user_id)
          return {
            ...m,
            nid_front_url: m.nid_front_url || sub?.nid_front_url,
            nid_back_url: m.nid_back_url || sub?.nid_back_url,
            face_photo_url: m.face_photo_url || sub?.face_photo_url,
            nid_number: m.nid_number || sub?.nid_number,
            nid_name: m.nid_name || sub?.nid_name,
            nid_dob: m.nid_dob || sub?.nid_dob,
          }
        })
        setSubmissions(mergedList)
        setActionMessage(null)
        if (!selectedMerchant && mergedList.length > 0) {
          const firstPending = mergedList.find(m => m.kyc_status === 'PENDING' || m.kyc_status === 'PENDING_REVIEW')
          setSelectedMerchant(firstPending || mergedList[0])
        } else if (selectedMerchant) {
          const updatedSelected = mergedList.find(m => m.id === selectedMerchant.id)
          if (updatedSelected) setSelectedMerchant(updatedSelected)
        }
      }
    } catch (err: any) {
      console.error('[KycReviews] fetch error:', err.message)
      setActionMessage({ type: 'error', text: 'KYC records notice: ' + err.message })
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadKycRecords()

    const channel = adminSupabase
      .channel('kyc_reviews_realtime')
      .on('postgres_changes', { event: '*', schema: 'public', table: 'merchants' }, () => {
        loadKycRecords()
      })
      .subscribe()

    return () => {
      adminSupabase.removeChannel(channel)
    }
  }, [])

  async function handleApprove(merchantId: string) {
    setActionLoading(true)
    setActionMessage(null)

    try {
      const reviewed = await reviewMerchantIdentity(merchantId, 'APPROVE')
      setActionMessage({ type: 'success', text: 'KYC Approved! Merchant is now Verified and Active.' })
      await loadKycRecords()

      if (selectedMerchant?.id === merchantId) {
        setSelectedMerchant(prev => prev ? { ...prev, ...reviewed } : null)
      }
    } catch (err: any) {
      setActionMessage({ type: 'error', text: err.message || 'Failed to approve KYC.' })
    } finally {
      setActionLoading(false)
    }
  }

  async function handleReject(merchantId: string) {
    if (!rejectReason.trim()) {
      alert('Please specify a rejection reason to inform the merchant on their app.')
      return
    }

    setActionLoading(true)
    setActionMessage(null)

    try {
      await reviewMerchantIdentity(merchantId, 'REJECT', rejectReason.trim())
      setActionMessage({ type: 'success', text: 'KYC Rejected. Reason communicated to the merchant app.' })
      setIsRejecting(false)
      setRejectReason('')
      await loadKycRecords()

      if (selectedMerchant?.id === merchantId) {
        setSelectedMerchant(prev => prev ? { ...prev, kyc_status: 'REJECTED', kyc_rejection_reason: rejectReason.trim() } : null)
      }
    } catch (err: any) {
      setActionMessage({ type: 'error', text: err.message || 'Failed to reject KYC.' })
    } finally {
      setActionLoading(false)
    }
  }

  const filteredList = submissions.filter(m => {
    if (filter === 'PENDING' && !(m.kyc_status === 'PENDING' || m.kyc_status === 'PENDING_REVIEW')) return false
    if (filter === 'VERIFIED' && m.kyc_status !== 'VERIFIED') return false
    if (filter === 'REJECTED' && m.kyc_status !== 'REJECTED') return false

    if (search.trim()) {
      const q = search.toLowerCase()
      return (
        (m.business_name || '').toLowerCase().includes(q) ||
        (m.email || '').toLowerCase().includes(q) ||
        (m.phone || '').includes(q) ||
        (m.nid_number || '').includes(q)
      )
    }
    return true
  })

  const pendingCount = submissions.filter(m => m.kyc_status === 'PENDING' || m.kyc_status === 'PENDING_REVIEW').length
  const verifiedCount = submissions.filter(m => m.kyc_status === 'VERIFIED').length
  const rejectedCount = submissions.filter(m => m.kyc_status === 'REJECTED').length

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
      {/* Top Header */}
      <div style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: 16
      }}>
        <div>
          <h1 style={{ fontSize: 22, fontWeight: 800, color: 'var(--text-primary)', letterSpacing: '-0.4px', margin: 0 }}>
            KYC & Identity Verification
          </h1>
          <div style={{ fontSize: 12.5, color: 'var(--text-muted)', marginTop: 4 }}>
            Review merchant NID identity cards, selfie validation scans, and approve/reject compliance access.
          </div>
        </div>

        {/* Filter Tabs */}
        <div style={{
          display: 'flex',
          background: 'var(--bg-subtle)',
          padding: 3,
          borderRadius: 'var(--radius-sm)',
          border: '1px solid var(--border-default)',
          gap: 2
        }}>
          {[
            { id: 'PENDING', label: `Pending (${pendingCount})` },
            { id: 'VERIFIED', label: `Verified (${verifiedCount})` },
            { id: 'REJECTED', label: `Rejected (${rejectedCount})` },
            { id: 'ALL', label: `All (${submissions.length})` }
          ].map(tab => {
            const isActive = filter === tab.id
            return (
              <button
                key={tab.id}
                onClick={() => setFilter(tab.id as any)}
                className="btn btn-sm"
                style={{
                  border: 'none',
                  background: isActive ? 'var(--bg-surface)' : 'transparent',
                  color: isActive ? 'var(--brand-primary)' : 'var(--text-secondary)',
                  fontWeight: isActive ? 700 : 500,
                  boxShadow: isActive ? 'var(--shadow-xs)' : 'none'
                }}
              >
                {tab.label}
              </button>
            )
          })}
        </div>
      </div>

      {/* Action Banner Message */}
      {actionMessage && (
        <div style={{
          padding: '12px 18px',
          borderRadius: 'var(--radius-md)',
          background: actionMessage.type === 'success' ? 'var(--success-subtle)' : 'var(--danger-subtle)',
          border: `1px solid ${actionMessage.type === 'success' ? 'var(--success-border)' : 'var(--danger-border)'}`,
          color: actionMessage.type === 'success' ? 'var(--success-text)' : 'var(--danger-text)',
          fontWeight: 600,
          fontSize: 13,
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center'
        }}>
          <span>{actionMessage.text}</span>
          <button onClick={() => setActionMessage(null)} className="btn-icon btn-ghost" style={{ width: 22, height: 22 }}>
            <X size={14} />
          </button>
        </div>
      )}

      {/* Two-Column Layout */}
      <div style={{
        display: 'grid',
        gridTemplateColumns: '360px 1fr',
        gap: 20,
        alignItems: 'start'
      }}>
        {/* Left Column: Submissions Queue List */}
        <div className="card" style={{ padding: 16, display: 'flex', flexDirection: 'column', gap: 12 }}>
          {/* Search Input */}
          <div style={{
            display: 'flex',
            alignItems: 'center',
            gap: 8,
            background: 'var(--bg-subtle)',
            border: '1px solid var(--border-default)',
            borderRadius: 'var(--radius-sm)',
            padding: '7px 12px'
          }}>
            <Search size={14} color="var(--text-muted)" />
            <input
              type="text"
              placeholder="Search queue by name, phone, NID..."
              value={search}
              onChange={e => setSearch(e.target.value)}
              style={{
                border: 'none',
                outline: 'none',
                background: 'transparent',
                fontSize: 12.5,
                color: 'var(--text-primary)',
                width: '100%',
                fontFamily: 'inherit'
              }}
            />
          </div>

          {/* Queue List Container */}
          <div style={{ maxHeight: 600, overflowY: 'auto', display: 'flex', flexDirection: 'column', gap: 6 }}>
            {loading ? (
              <div style={{ textAlign: 'center', padding: 24, color: 'var(--text-muted)', fontSize: 13 }}>
                Loading verification queue...
              </div>
            ) : filteredList.length === 0 ? (
              <div style={{ textAlign: 'center', padding: 32, color: 'var(--text-muted)', fontSize: 13 }}>
                No submissions found in this category.
              </div>
            ) : (
              filteredList.map(m => {
                const isSelected = selectedMerchant?.id === m.id
                const isPending = m.kyc_status === 'PENDING' || m.kyc_status === 'PENDING_REVIEW'
                const isVerified = m.kyc_status === 'VERIFIED'
                return (
                  <div
                    key={m.id}
                    onClick={() => { setSelectedMerchant(m); setIsRejecting(false); }}
                    style={{
                      padding: '12px 14px',
                      borderRadius: 'var(--radius-md)',
                      background: isSelected ? 'var(--brand-subtle)' : 'var(--bg-surface)',
                      border: `1px solid ${isSelected ? 'var(--brand-border)' : 'var(--border-default)'}`,
                      cursor: 'pointer',
                      transition: 'all var(--transition-fast)'
                    }}
                  >
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                      <span style={{ fontSize: 13.5, fontWeight: 700, color: isSelected ? 'var(--brand-primary)' : 'var(--text-primary)' }}>
                        {m.business_name || 'Store Merchant'}
                      </span>
                      <span className={`status-pill ${isVerified ? 'success' : isPending ? 'warning' : 'danger'}`} style={{ fontSize: 10 }}>
                        <span className="status-dot" />
                        {m.kyc_status}
                      </span>
                    </div>
                    <div style={{ fontSize: 12, color: 'var(--text-muted)', marginTop: 2 }}>
                      {m.phone || m.email || 'No contact'}
                    </div>
                    <div style={{ fontSize: 11, color: 'var(--text-subtle)', marginTop: 4 }}>
                      NID: {m.nid_number || 'Pending'} • {m.kyc_submitted_at ? new Date(m.kyc_submitted_at).toLocaleDateString() : 'Recent'}
                    </div>
                  </div>
                )
              })
            )}
          </div>
        </div>

        {/* Right Column: Inspection Panel */}
        {selectedMerchant ? (
          <div className="card" style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 20 }}>
            {/* Header info */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: '1px solid var(--border-subtle)', paddingBottom: 16 }}>
              <div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                  <h2 style={{ fontSize: 18, fontWeight: 800, color: 'var(--text-primary)', margin: 0 }}>
                    {selectedMerchant.business_name}
                  </h2>
                  <span className={`status-pill ${selectedMerchant.kyc_status === 'VERIFIED' ? 'success' : selectedMerchant.kyc_status === 'PENDING' ? 'warning' : 'danger'}`}>
                    <span className="status-dot" />
                    {selectedMerchant.kyc_status}
                  </span>
                </div>
                <div style={{ fontSize: 12.5, color: 'var(--text-muted)', marginTop: 4 }}>
                  Merchant ID: <span style={{ fontFamily: 'var(--font-mono)' }}>{selectedMerchant.id}</span>
                </div>
              </div>

              <Link to={`/merchants/${selectedMerchant.id}`} className="btn btn-secondary btn-sm">
                <span>Merchant Profile</span>
                <ExternalLink size={12} />
              </Link>
            </div>

            {/* Merchant Details Grid */}
            <div style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(3, 1fr)',
              gap: 12,
              background: 'var(--bg-subtle)',
              padding: 14,
              borderRadius: 'var(--radius-md)',
              border: '1px solid var(--border-default)'
            }}>
              <div>
                <div style={{ fontSize: 11, color: 'var(--text-muted)', textTransform: 'uppercase', fontWeight: 700 }}>NID Name</div>
                <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--text-primary)', marginTop: 2 }}>
                  {selectedMerchant.nid_name || selectedMerchant.business_name}
                </div>
              </div>
              <div>
                <div style={{ fontSize: 11, color: 'var(--text-muted)', textTransform: 'uppercase', fontWeight: 700 }}>NID Number</div>
                <div style={{ fontSize: 13, fontWeight: 700, fontFamily: 'var(--font-mono)', color: 'var(--brand-primary)', marginTop: 2 }}>
                  {selectedMerchant.nid_number || 'Not provided'}
                </div>
              </div>
              <div>
                <div style={{ fontSize: 11, color: 'var(--text-muted)', textTransform: 'uppercase', fontWeight: 700 }}>Date of Birth</div>
                <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--text-primary)', marginTop: 2 }}>
                  {selectedMerchant.nid_dob || '—'}
                </div>
              </div>
            </div>

            {/* Document Photos Grid */}
            <div>
              <div style={{ fontSize: 13, fontWeight: 700, color: 'var(--text-primary)', marginBottom: 12 }}>
                Submitted Verification Documents
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 14 }}>
                {/* NID Front */}
                <div style={{
                  border: '1px solid var(--border-default)',
                  borderRadius: 'var(--radius-md)',
                  padding: 10,
                  background: 'var(--bg-surface)'
                }}>
                  <div style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-muted)', marginBottom: 6 }}>
                    NID Front Side
                  </div>
                  {selectedMerchant.nid_front_url && formatKycImageUrl(selectedMerchant.nid_front_url) ? (
                    <img
                      src={formatKycImageUrl(selectedMerchant.nid_front_url)!}
                      alt="NID Front"
                      onClick={() => setPreviewImage(formatKycImageUrl(selectedMerchant.nid_front_url))}
                      onError={(e) => {
                        const el = e.currentTarget
                        if (el.src.includes('/uploads/kyc/')) {
                          const file = el.src.split('/uploads/kyc/')[1]
                          el.src = `${ADMIN_SUPABASE_URL}/storage/v1/object/public/kyc-documents/${selectedMerchant.id}/${file}`
                        }
                      }}
                      style={{ width: '100%', height: 130, objectFit: 'cover', borderRadius: 'var(--radius-xs)', cursor: 'pointer' }}
                    />
                  ) : (
                    <div style={{ height: 130, background: 'var(--bg-subtle)', borderRadius: 'var(--radius-xs)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--text-subtle)' }}>
                      Not Uploaded
                    </div>
                  )}
                </div>

                {/* NID Back */}
                <div style={{
                  border: '1px solid var(--border-default)',
                  borderRadius: 'var(--radius-md)',
                  padding: 10,
                  background: 'var(--bg-surface)'
                }}>
                  <div style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-muted)', marginBottom: 6 }}>
                    NID Back Side
                  </div>
                  {selectedMerchant.nid_back_url && formatKycImageUrl(selectedMerchant.nid_back_url) ? (
                    <img
                      src={formatKycImageUrl(selectedMerchant.nid_back_url)!}
                      alt="NID Back"
                      onClick={() => setPreviewImage(formatKycImageUrl(selectedMerchant.nid_back_url))}
                      onError={(e) => {
                        const el = e.currentTarget
                        if (el.src.includes('/uploads/kyc/')) {
                          const file = el.src.split('/uploads/kyc/')[1]
                          el.src = `${ADMIN_SUPABASE_URL}/storage/v1/object/public/kyc-documents/${selectedMerchant.id}/${file}`
                        }
                      }}
                      style={{ width: '100%', height: 130, objectFit: 'cover', borderRadius: 'var(--radius-xs)', cursor: 'pointer' }}
                    />
                  ) : (
                    <div style={{ height: 130, background: 'var(--bg-subtle)', borderRadius: 'var(--radius-xs)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--text-subtle)' }}>
                      Not Uploaded
                    </div>
                  )}
                </div>

                {/* Face Photo */}
                <div style={{
                  border: '1px solid var(--border-default)',
                  borderRadius: 'var(--radius-md)',
                  padding: 10,
                  background: 'var(--bg-surface)'
                }}>
                  <div style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-muted)', marginBottom: 6 }}>
                    Biometric Selfie
                  </div>
                  {selectedMerchant.face_photo_url && formatKycImageUrl(selectedMerchant.face_photo_url) ? (
                    <img
                      src={formatKycImageUrl(selectedMerchant.face_photo_url)!}
                      alt="Selfie"
                      onClick={() => setPreviewImage(formatKycImageUrl(selectedMerchant.face_photo_url))}
                      onError={(e) => {
                        const el = e.currentTarget
                        if (el.src.includes('/uploads/kyc/')) {
                          const file = el.src.split('/uploads/kyc/')[1]
                          el.src = `${ADMIN_SUPABASE_URL}/storage/v1/object/public/kyc-documents/${selectedMerchant.id}/${file}`
                        }
                      }}
                      style={{ width: '100%', height: 130, objectFit: 'cover', borderRadius: 'var(--radius-xs)', cursor: 'pointer' }}
                    />
                  ) : (
                    <div style={{ height: 130, background: 'var(--bg-subtle)', borderRadius: 'var(--radius-xs)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--text-subtle)' }}>
                      Not Uploaded
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* Rejection Form & Actions */}
            {isRejecting ? (
              <div style={{
                background: 'var(--danger-subtle)',
                border: '1px solid var(--danger-border)',
                borderRadius: 'var(--radius-md)',
                padding: 16,
                display: 'flex',
                flexDirection: 'column',
                gap: 10
              }}>
                <div style={{ fontSize: 13, fontWeight: 700, color: 'var(--danger-text)' }}>
                  Reject Application & Notify Merchant
                </div>
                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                  {['Unclear NID Photo', 'Name Mismatch with NID', 'Expired NID Document', 'Blury Selfie Photo'].map(reason => (
                    <button
                      key={reason}
                      type="button"
                      onClick={() => setRejectReason(reason)}
                      className="btn btn-sm btn-secondary"
                      style={{ fontSize: 11 }}
                    >
                      {reason}
                    </button>
                  ))}
                </div>
                <textarea
                  value={rejectReason}
                  onChange={e => setRejectReason(e.target.value)}
                  placeholder="Specify clear instructions for the merchant to correct their NID upload..."
                  rows={3}
                  style={{
                    width: '100%',
                    padding: 10,
                    borderRadius: 'var(--radius-sm)',
                    border: '1px solid var(--danger-border)',
                    fontSize: 12.5,
                    background: 'var(--bg-surface)',
                    color: 'var(--text-primary)',
                    boxSizing: 'border-box',
                    fontFamily: 'inherit'
                  }}
                />
                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                  <button onClick={() => setIsRejecting(false)} className="btn btn-secondary btn-sm">
                    Cancel
                  </button>
                  <button
                    onClick={() => handleReject(selectedMerchant.id)}
                    disabled={actionLoading}
                    className="btn btn-danger btn-sm"
                  >
                    Confirm Rejection
                  </button>
                </div>
              </div>
            ) : (
              <div style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'flex-end',
                gap: 12,
                borderTop: '1px solid var(--border-subtle)',
                paddingTop: 16
              }}>
                <button
                  onClick={() => setIsRejecting(true)}
                  className="btn btn-danger btn-sm"
                  style={{ gap: 6 }}
                >
                  <X size={14} />
                  <span>Reject Application</span>
                </button>
                <button
                  onClick={() => handleApprove(selectedMerchant.id)}
                  disabled={actionLoading}
                  className="btn btn-primary btn-sm"
                  style={{ gap: 6 }}
                >
                  <Check size={14} />
                  <span>Approve & Verify Merchant</span>
                </button>
              </div>
            )}
          </div>
        ) : (
          <div className="card" style={{ padding: 48, textAlign: 'center', color: 'var(--text-muted)' }}>
            Select a merchant from the queue to inspect verification documents.
          </div>
        )}
      </div>

      {/* Image Zoom Modal */}
      {previewImage && (
        <div
          onClick={() => setPreviewImage(null)}
          style={{
            position: 'fixed',
            inset: 0,
            background: 'rgba(0,0,0,0.85)',
            zIndex: 300,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: 24,
            cursor: 'zoom-out'
          }}
        >
          <img
            src={previewImage}
            alt="Zoomed preview"
            style={{ maxWidth: '90%', maxHeight: '90%', borderRadius: 12, boxShadow: '0 20px 40px rgba(0,0,0,0.5)' }}
          />
        </div>
      )}
    </div>
  )
}
