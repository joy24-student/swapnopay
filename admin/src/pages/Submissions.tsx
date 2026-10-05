import React, { useEffect, useState, useMemo, useRef } from 'react';
import { adminSupabase } from '../adminSupabaseClient';
import { Link } from 'react-router-dom';
import {
  FileSpreadsheet,
  Download,
  Search,
  FileText,
  X,
  Eye,
  Printer,
  Filter,
  Calendar,
  DollarSign,
  TrendingUp,
  CheckCircle2,
  Clock,
  AlertTriangle,
  RotateCcw,
  Copy,
  ExternalLink,
  ChevronDown,
  ChevronUp,
  Layers,
  SlidersHorizontal,
  Columns3,
  Phone,
  Mail,
  User,
  CreditCard,
  Hash,
  ShieldCheck,
  Check,
  HelpCircle,
  FileDown
} from 'lucide-react';

export interface FormSubmission {
  id: string;
  createdAt: string;
  merchantId?: string;
  formId?: string;
  formTitle?: string;
  customer_name?: string;
  customer_phone?: string;
  customer_email?: string;
  amount_bdt?: number;
  payment_method?: string;
  payment_status?: string;
  trx_id?: string;
  order_id?: string;
  submission?: Record<string, any>;
}

export default function Submissions() {
  const [rows, setRows] = useState<FormSubmission[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('ALL');
  const [methodFilter, setMethodFilter] = useState<string>('ALL');
  const [formFilter, setFormFilter] = useState<string>('ALL');
  const [dateFilter, setDateFilter] = useState<string>('ALL');
  const [density, setDensity] = useState<'compact' | 'comfortable'>('comfortable');
  const [showCustomColumns, setShowCustomColumns] = useState(true);
  const [visibleCustomFields, setVisibleCustomFields] = useState<string[]>([]);
  const [showColumnPicker, setShowColumnPicker] = useState(false);
  const [sortField, setSortField] = useState<'createdAt' | 'amount_bdt' | 'customer_name' | 'formTitle'>('createdAt');
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('desc');
  const [selectedSub, setSelectedSub] = useState<FormSubmission | null>(null);
  const [selectedRowIds, setSelectedRowIds] = useState<Set<string>>(new Set());
  const [copiedKey, setCopiedKey] = useState<string | null>(null);
  const [formsMap, setFormsMap] = useState<Map<string, string>>(new Map());

  const printableRef = useRef<HTMLDivElement>(null);

  // ──────────────────────────────────────────────────────────────────────────
  // Data Loading: Supabase + Payment Forms + Fallback Backend API
  // ──────────────────────────────────────────────────────────────────────────
  const fetchSubmissions = async () => {
    setLoading(true);
    let list: FormSubmission[] = [];
    const fMap = new Map<string, string>();

    // 1. Fetch form titles for human-readable labels
    try {
      const { data: formsData } = await adminSupabase
        .from('payment_forms')
        .select('id, title, slug');
      if (formsData && formsData.length > 0) {
        formsData.forEach((f: any) => {
          if (f.id && f.title) fMap.set(f.id, f.title);
          if (f.slug && f.title) fMap.set(f.slug, f.title);
        });
      }
    } catch (_) {}

    // 2. Fetch submissions from primary table 'form_submissions'
    try {
      const { data, error } = await adminSupabase
        .from('form_submissions')
        .select('*')
        .order('created_at', { ascending: false });

      if (!error && data && data.length > 0) {
        list = data.map((d: any) => {
          const rawAnswers = d.answers || d.submission || {};
          let parsedAnswers: Record<string, any> = {};
          if (typeof rawAnswers === 'string') {
            try { parsedAnswers = JSON.parse(rawAnswers); } catch (_) { parsedAnswers = { value: rawAnswers }; }
          } else if (typeof rawAnswers === 'object' && rawAnswers !== null) {
            parsedAnswers = rawAnswers;
          }

          const fId = d.form_id || d.formId || '';
          const resolvedTitle = fMap.get(fId) || d.form_title || (fId ? `Form (${fId.slice(0, 8)}...)` : 'General Form');

          return {
            id: d.id,
            createdAt: d.created_at || d.submitted_at || new Date().toISOString(),
            merchantId: d.merchant_id || d.form_id,
            formId: fId,
            formTitle: resolvedTitle,
            customer_name: d.customer_name || parsedAnswers.name || parsedAnswers.customer_name || parsedAnswers.fullName || '',
            customer_phone: d.customer_phone || parsedAnswers.phone || parsedAnswers.customer_phone || parsedAnswers.mobile || '',
            customer_email: d.customer_email || parsedAnswers.email || parsedAnswers.customer_email || '',
            amount_bdt: Number(d.amount_bdt !== undefined ? d.amount_bdt : (d.amount || 0)),
            payment_method: d.payment_method || d.method || 'bKash',
            payment_status: d.payment_status || 'NOT_REQUIRED',
            trx_id: d.trx_id || d.transaction_id || d.tran_id || '',
            order_id: d.order_id || d.orderId || '',
            submission: parsedAnswers,
          };
        });
      }
    } catch (e) {
      console.warn('[Submissions] Supabase form_submissions notice:', e);
    }

    // 3. Fallback: Query backend API /v1/forms/submissions
    if (list.length === 0) {
      try {
        const base = ((import.meta as any).env?.VITE_BACKEND_URL || 'https://api.swapnopay.top').replace(/\/$/, '');
        const masterSecret = typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('swapnopay_admin_secret') : null;
        const headers: Record<string, string> = { Accept: 'application/json' };
        if (masterSecret) headers['X-Admin-Secret'] = masterSecret;

        const res = await fetch(`${base}/v1/forms/submissions?limit=200`, { headers });
        if (res.ok) {
          const json = await res.json();
          const rawSubs = Array.isArray(json.submissions) ? json.submissions : [];
          list = rawSubs.map((d: any) => {
            const rawAnswers = d.answers || d.submission || {};
            let parsedAnswers: Record<string, any> = {};
            if (typeof rawAnswers === 'string') {
              try { parsedAnswers = JSON.parse(rawAnswers); } catch (_) { parsedAnswers = { value: rawAnswers }; }
            } else if (typeof rawAnswers === 'object' && rawAnswers !== null) {
              parsedAnswers = rawAnswers;
            }

            const fId = d.form_id || d.formId || '';
            const resolvedTitle = fMap.get(fId) || d.form_title || d.formName || (fId ? `Form (${fId.slice(0, 8)}...)` : 'General Form');

            return {
              id: d.id || d.submission_id || `sub_${Math.random().toString(36).slice(2, 9)}`,
              createdAt: d.created_at || d.createdAt || new Date().toISOString(),
              merchantId: d.merchant_id || d.merchantId || d.form_id,
              formId: fId,
              formTitle: resolvedTitle,
              customer_name: d.customer_name || parsedAnswers.name || parsedAnswers.customer_name || '',
              customer_phone: d.customer_phone || parsedAnswers.phone || parsedAnswers.customer_phone || '',
              customer_email: d.customer_email || parsedAnswers.email || '',
              amount_bdt: Number(d.amount_bdt !== undefined ? d.amount_bdt : (d.amount || 0)),
              payment_method: d.payment_method || d.method || 'bKash',
              payment_status: d.payment_status || 'NOT_REQUIRED',
              trx_id: d.trx_id || d.tran_id || '',
              order_id: d.order_id || '',
              submission: parsedAnswers,
            };
          });
        }
      } catch (bkErr) {
        console.warn('[Submissions] Backend fetch fallback notice:', bkErr);
      }
    }

    setFormsMap(fMap);
    setRows(list);
    setLoading(false);
  };

  useEffect(() => {
    fetchSubmissions();
  }, []);

  // ──────────────────────────────────────────────────────────────────────────
  // Extract Dynamic Form Fields for Excel Spreadsheet View
  // ──────────────────────────────────────────────────────────────────────────
  const allCustomFieldKeys = useMemo(() => {
    const keysSet = new Set<string>();
    // Exclude common root keys that already have their own dedicated column
    const standardKeys = new Set([
      'name', 'fullname', 'full_name', 'customer_name', 'phone', 'customer_phone',
      'mobile', 'email', 'customer_email', 'amount', 'amount_bdt', 'trx_id',
      'payment_method', 'payment_status', 'id', 'created_at', 'order_id'
    ]);

    rows.forEach(r => {
      if (r.submission && typeof r.submission === 'object') {
        Object.keys(r.submission).forEach(k => {
          if (!standardKeys.has(k.toLowerCase()) && !k.startsWith('_')) {
            keysSet.add(k);
          }
        });
      }
    });

    return Array.from(keysSet);
  }, [rows]);

  // Set default visible custom fields when rows load
  useEffect(() => {
    if (allCustomFieldKeys.length > 0 && visibleCustomFields.length === 0) {
      setVisibleCustomFields(allCustomFieldKeys.slice(0, 4));
    }
  }, [allCustomFieldKeys]);

  // Distinct form titles for dropdown
  const distinctForms = useMemo(() => {
    const map = new Map<string, string>();
    rows.forEach(r => {
      if (r.formId) {
        map.set(r.formId, r.formTitle || r.formId);
      }
    });
    return Array.from(map.entries()).map(([id, title]) => ({ id, title }));
  }, [rows]);

  // ──────────────────────────────────────────────────────────────────────────
  // Filtering & Search
  // ──────────────────────────────────────────────────────────────────────────
  const filteredRows = useMemo(() => {
    let result = [...rows];

    // 1. Text Search
    if (search.trim()) {
      const q = search.toLowerCase();
      result = result.filter(r => {
        const inCustomer = (r.customer_name || '').toLowerCase().includes(q);
        const inPhone = (r.customer_phone || '').toLowerCase().includes(q);
        const inEmail = (r.customer_email || '').toLowerCase().includes(q);
        const inForm = (r.formTitle || '').toLowerCase().includes(q) || (r.formId || '').toLowerCase().includes(q);
        const inTrx = (r.trx_id || '').toLowerCase().includes(q);
        const inOrder = (r.order_id || '').toLowerCase().includes(q);
        const inId = (r.id || '').toLowerCase().includes(q);

        // Check inside submission answers
        let inAnswers = false;
        if (r.submission && typeof r.submission === 'object') {
          inAnswers = Object.values(r.submission).some(v =>
            String(v || '').toLowerCase().includes(q)
          );
        }

        return inCustomer || inPhone || inEmail || inForm || inTrx || inOrder || inId || inAnswers;
      });
    }

    // 2. Status Filter
    if (statusFilter !== 'ALL') {
      result = result.filter(r => (r.payment_status || 'NOT_REQUIRED').toUpperCase() === statusFilter);
    }

    // 3. Payment Method Filter
    if (methodFilter !== 'ALL') {
      result = result.filter(r => (r.payment_method || '').toLowerCase() === methodFilter.toLowerCase());
    }

    // 4. Form Filter
    if (formFilter !== 'ALL') {
      result = result.filter(r => r.formId === formFilter);
    }

    // 5. Date Filter
    if (dateFilter !== 'ALL') {
      const now = new Date();
      result = result.filter(r => {
        const d = new Date(r.createdAt);
        if (isNaN(d.getTime())) return true;
        if (dateFilter === 'TODAY') {
          return d.toDateString() === now.toDateString();
        }
        if (dateFilter === 'YESTERDAY') {
          const y = new Date(now);
          y.setDate(now.getDate() - 1);
          return d.toDateString() === y.toDateString();
        }
        if (dateFilter === '7DAYS') {
          const past = new Date(now);
          past.setDate(now.getDate() - 7);
          return d >= past;
        }
        if (dateFilter === '30DAYS') {
          const past = new Date(now);
          past.setDate(now.getDate() - 30);
          return d >= past;
        }
        if (dateFilter === 'THIS_MONTH') {
          return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
        }
        return true;
      });
    }

    // 6. Sorting
    result.sort((a, b) => {
      let valA: any = a[sortField];
      let valB: any = b[sortField];

      if (sortField === 'createdAt') {
        valA = new Date(a.createdAt).getTime();
        valB = new Date(b.createdAt).getTime();
      } else if (sortField === 'amount_bdt') {
        valA = Number(a.amount_bdt || 0);
        valB = Number(b.amount_bdt || 0);
      } else {
        valA = String(valA || '').toLowerCase();
        valB = String(valB || '').toLowerCase();
      }

      if (valA < valB) return sortOrder === 'asc' ? -1 : 1;
      if (valA > valB) return sortOrder === 'asc' ? 1 : -1;
      return 0;
    });

    return result;
  }, [rows, search, statusFilter, methodFilter, formFilter, dateFilter, sortField, sortOrder]);

  // ──────────────────────────────────────────────────────────────────────────
  // Data Analysis & Metrics Calculation
  // ──────────────────────────────────────────────────────────────────────────
  const analytics = useMemo(() => {
    const totalCount = filteredRows.length;
    let totalRevenue = 0;
    let paidCount = 0;
    let pendingCount = 0;
    let failedCount = 0;
    let freeCount = 0;

    const methodBreakdown: Record<string, number> = {};
    const formBreakdown: Record<string, { count: number; revenue: number; title: string }> = {};

    filteredRows.forEach(r => {
      const amt = Number(r.amount_bdt || 0);
      const status = (r.payment_status || 'NOT_REQUIRED').toUpperCase();

      if (status === 'PAID') {
        totalRevenue += amt;
        paidCount++;
      } else if (status === 'PENDING') {
        pendingCount++;
      } else if (status === 'FAILED') {
        failedCount++;
      } else {
        freeCount++;
      }

      // Method breakdown
      const m = r.payment_method || 'bKash';
      methodBreakdown[m] = (methodBreakdown[m] || 0) + 1;

      // Form breakdown
      const fKey = r.formId || 'unknown';
      if (!formBreakdown[fKey]) {
        formBreakdown[fKey] = { count: 0, revenue: 0, title: r.formTitle || 'General Form' };
      }
      formBreakdown[fKey].count++;
      if (status === 'PAID') formBreakdown[fKey].revenue += amt;
    });

    const conversionRate = totalCount > 0 ? Math.round((paidCount / totalCount) * 100) : 0;
    const avgTicket = paidCount > 0 ? Math.round(totalRevenue / paidCount) : 0;

    const topForms = Object.values(formBreakdown)
      .sort((a, b) => b.count - a.count)
      .slice(0, 3);

    return {
      totalCount,
      totalRevenue,
      paidCount,
      pendingCount,
      failedCount,
      freeCount,
      conversionRate,
      avgTicket,
      methodBreakdown,
      topForms,
    };
  }, [filteredRows]);

  // ──────────────────────────────────────────────────────────────────────────
  // Helpers & Actions
  // ──────────────────────────────────────────────────────────────────────────
  const handleSort = (field: 'createdAt' | 'amount_bdt' | 'customer_name' | 'formTitle') => {
    if (sortField === field) {
      setSortOrder(prev => (prev === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortField(field);
      setSortOrder('desc');
    }
  };

  const copyToClipboard = (text: string, key: string) => {
    if (!text) return;
    navigator.clipboard.writeText(text);
    setCopiedKey(key);
    setTimeout(() => setCopiedKey(null), 2000);
  };

  const toggleSelectAll = () => {
    if (selectedRowIds.size === filteredRows.length) {
      setSelectedRowIds(new Set());
    } else {
      setSelectedRowIds(new Set(filteredRows.map(r => r.id)));
    }
  };

  const toggleSelectRow = (id: string) => {
    const next = new Set(selectedRowIds);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    setSelectedRowIds(next);
  };

  const resetFilters = () => {
    setSearch('');
    setStatusFilter('ALL');
    setMethodFilter('ALL');
    setFormFilter('ALL');
    setDateFilter('ALL');
  };

  const activeFiltersCount = [
    statusFilter !== 'ALL',
    methodFilter !== 'ALL',
    formFilter !== 'ALL',
    dateFilter !== 'ALL',
    search.trim().length > 0,
  ].filter(Boolean).length;

  // ──────────────────────────────────────────────────────────────────────────
  // Print & PDF Export (Professional Sheet Print)
  // ──────────────────────────────────────────────────────────────────────────
  const handlePrint = () => {
    window.print();
  };

  // ──────────────────────────────────────────────────────────────────────────
  // CSV / Excel Export with All Dynamic Answers Columns
  // ──────────────────────────────────────────────────────────────────────────
  const handleExportCSV = () => {
    if (filteredRows.length === 0) return alert('No submissions to export');

    const baseHeaders = [
      'Row',
      'Submission ID',
      'Submitted Date',
      'Form Title',
      'Customer Name',
      'Customer Phone',
      'Customer Email',
      'Amount (BDT)',
      'Payment Method',
      'Payment Status',
      'Transaction ID',
      'Order ID',
    ];

    const customHeaders = showCustomColumns ? visibleCustomFields : [];
    const fullHeaders = [...baseHeaders, ...customHeaders];

    const csvRows = filteredRows.map((r, idx) => {
      const baseValues = [
        idx + 1,
        `"${r.id}"`,
        `"${new Date(r.createdAt).toLocaleString()}"`,
        `"${(r.formTitle || '').replace(/"/g, '""')}"`,
        `"${(r.customer_name || '').replace(/"/g, '""')}"`,
        `"${r.customer_phone || ''}"`,
        `"${r.customer_email || ''}"`,
        r.amount_bdt || 0,
        `"${r.payment_method || 'bKash'}"`,
        `"${r.payment_status || 'NOT_REQUIRED'}"`,
        `"${r.trx_id || ''}"`,
        `"${r.order_id || ''}"`,
      ];

      const customValues = customHeaders.map(f => {
        const val = r.submission?.[f];
        if (val === undefined || val === null) return '""';
        if (typeof val === 'object') return `"${JSON.stringify(val).replace(/"/g, '""')}"`;
        return `"${String(val).replace(/"/g, '""')}"`;
      });

      return [...baseValues, ...customValues].join(',');
    });

    // Add UTF-8 BOM so Excel opens Bangla/special characters cleanly
    const csvContent = '\uFEFF' + [fullHeaders.join(','), ...csvRows].join('\r\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `SwapnoPay_Form_Submissions_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  };

  // Helper to format custom field answers nicely
  const renderAnswerCell = (val: any) => {
    if (val === undefined || val === null || val === '') return <span style={{ color: '#CBD5E1' }}>—</span>;
    if (typeof val === 'boolean') {
      return (
        <span style={{
          padding: '2px 6px',
          borderRadius: 4,
          fontSize: 10,
          fontWeight: 700,
          background: val ? '#ECFDF5' : '#FEF2F2',
          color: val ? '#059669' : '#DC2626'
        }}>
          {val ? 'YES' : 'NO'}
        </span>
      );
    }
    if (typeof val === 'object') {
      if (val.file_name || val.url || val.object_path) {
        return (
          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4, color: '#4F46E5', fontSize: 11, fontWeight: 600 }}>
            <FileDown size={12} /> {val.file_name || 'Attachment'}
          </span>
        );
      }
      return <span style={{ fontSize: 11, color: '#475569' }}>{Object.values(val).join(', ')}</span>;
    }
    const str = String(val);
    if (str.length > 28) return <span title={str}>{str.slice(0, 25)}...</span>;
    return str;
  };

  return (
    <div className="container submissions-page-container" style={{ maxWidth: 1400, margin: '0 auto', paddingBottom: 40 }}>
      {/* ────────────────────────────────────────────────────────────────────────── */}
      {/* Dynamic Embedded Print & Excel Stylesheet */}
      {/* ────────────────────────────────────────────────────────────────────────── */}
      <style>{`
        /* Excel Table Grid Styling */
        .excel-grid-wrapper {
          border: 1px solid #CBD5E1;
          border-radius: 8px;
          background: #FFFFFF;
          box-shadow: 0 1px 3px rgba(0,0,0,0.05);
          overflow-x: auto;
          position: relative;
        }
        .excel-table {
          width: 100%;
          border-collapse: separate;
          border-spacing: 0;
          font-family: var(--font-sans, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif);
          font-size: ${density === 'compact' ? '12px' : '13px'};
        }
        .excel-table th {
          background: #F1F5F9;
          color: #334155;
          font-weight: 700;
          text-align: left;
          padding: ${density === 'compact' ? '6px 10px' : '10px 14px'};
          border-bottom: 2px solid #CBD5E1;
          border-right: 1px solid #E2E8F0;
          white-space: nowrap;
          user-select: none;
          position: sticky;
          top: 0;
          z-index: 10;
        }
        .excel-table th:last-child {
          border-right: none;
        }
        .excel-table td {
          padding: ${density === 'compact' ? '5px 10px' : '9px 14px'};
          border-bottom: 1px solid #E2E8F0;
          border-right: 1px solid #F1F5F9;
          color: #1E293B;
          vertical-align: middle;
          white-space: nowrap;
        }
        .excel-table td:last-child {
          border-right: none;
        }
        .excel-table tbody tr:nth-child(even) {
          background-color: #F8FAFC;
        }
        .excel-table tbody tr:hover {
          background-color: #EFF6FF !important;
        }
        .excel-table tbody tr.row-selected {
          background-color: #E0E7FF !important;
        }
        .excel-row-num {
          background: #F8FAFC;
          color: #94A3B8;
          font-family: var(--font-mono, monospace);
          font-size: 11px;
          font-weight: 600;
          text-align: center;
          width: 44px;
          border-right: 2px solid #CBD5E1 !important;
          user-select: none;
        }

        /* Printable Area Formatting */
        @media print {
          nav, aside, header, .no-print, button, .action-cell, .search-container, .nav-item-link, #widget-toast-container {
            display: none !important;
          }
          body {
            background: #FFFFFF !important;
            color: #000000 !important;
            font-size: 10pt;
          }
          .container {
            max-width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
          }
          .printable-report-header {
            display: block !important;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #000000;
          }
          .excel-grid-wrapper {
            border: 1px solid #000000 !important;
            box-shadow: none !important;
            overflow: visible !important;
          }
          .excel-table {
            border: 1px solid #000000 !important;
          }
          .excel-table th {
            background: #E5E7EB !important;
            color: #000000 !important;
            border: 1px solid #000000 !important;
            font-size: 9pt !important;
          }
          .excel-table td {
            border: 1px solid #D1D5DB !important;
            font-size: 8.5pt !important;
          }
          .kpi-card-print {
            border: 1px solid #CBD5E1 !important;
            break-inside: avoid;
          }
        }
      `}</style>

      {/* ────────────────────────────────────────────────────────────────────────── */}
      {/* Printable Report Header (Visible only in Print / PDF export) */}
      {/* ────────────────────────────────────────────────────────────────────────── */}
      <div className="printable-report-header" style={{ display: 'none' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div>
            <h1 style={{ margin: 0, fontSize: '18pt', fontWeight: 800 }}>SwapnoPay™ Form Responses & Submissions Audit Report</h1>
            <p style={{ margin: '4px 0 0', fontSize: '9pt', color: '#475569' }}>
              Generated on: {new Date().toLocaleString()} | Filter: Status [{statusFilter}], Date [{dateFilter}], Method [{methodFilter}]
            </p>
          </div>
          <div style={{ textAlign: 'right' }}>
            <div style={{ fontSize: '12pt', fontWeight: 800, color: '#059669' }}>
              Total Revenue: ৳{analytics.totalRevenue.toLocaleString()}
            </div>
            <div style={{ fontSize: '9pt', color: '#64748B' }}>
              Total Records: {analytics.totalCount} | Paid Conversion: {analytics.conversionRate}%
            </div>
          </div>
        </div>
      </div>

      {/* ────────────────────────────────────────────────────────────────────────── */}
      {/* Header & Controls Toolbar */}
      {/* ────────────────────────────────────────────────────────────────────────── */}
      <div className="header no-print" style={{ marginBottom: 20 }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <div style={{ width: 38, height: 38, borderRadius: 10, background: '#107C41', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#FFFFFF', boxShadow: '0 2px 6px rgba(16,124,65,0.25)' }}>
              <FileSpreadsheet size={22} />
            </div>
            <div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <h1 style={{ margin: 0, fontSize: '1.45rem', fontWeight: 800, color: 'var(--text-primary)' }}>
                  Form Submissions & Analytics
                </h1>
                <span style={{ fontSize: 11, fontWeight: 700, padding: '2px 8px', borderRadius: 12, background: '#ECFDF5', color: '#059669', border: '1px solid #A7F3D0' }}>
                  Live Excel View
                </span>
              </div>
              <p style={{ margin: '2px 0 0', color: 'var(--text-muted)', fontSize: 13 }}>
                Professional spreadsheet inspection, advanced multi-attribute filtering, and PDF reporting.
              </p>
            </div>
          </div>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
          <button
            className="button"
            onClick={fetchSubmissions}
            title="Refresh submissions"
            style={{ background: 'var(--bg-surface)', color: 'var(--text-primary)', border: '1px solid var(--border-default)', display: 'inline-flex', alignItems: 'center', gap: 6 }}
          >
            <RotateCcw size={15} className={loading ? 'animate-spin' : ''} />
            Refresh
          </button>

          <button
            className="button"
            onClick={handlePrint}
            title="Print sheet or Save as PDF"
            style={{ background: '#4F46E5', color: '#FFFFFF', display: 'inline-flex', alignItems: 'center', gap: 6, fontWeight: 600, boxShadow: '0 2px 4px rgba(79,70,229,0.2)' }}
          >
            <Printer size={15} /> Print / Save PDF
          </button>

          <button
            className="button"
            onClick={handleExportCSV}
            title="Download full Microsoft Excel / CSV file"
            style={{ background: '#107C41', color: '#FFFFFF', display: 'inline-flex', alignItems: 'center', gap: 6, fontWeight: 600, boxShadow: '0 2px 4px rgba(16,124,65,0.2)' }}
          >
            <Download size={15} /> Export Excel / CSV
          </button>
        </div>
      </div>

      {/* ────────────────────────────────────────────────────────────────────────── */}
      {/* Executive Data Analysis & KPI Metrics Cards (ডাটা এনালাইসিস ওরা যেন খুব সহজ হয়) */}
      {/* ────────────────────────────────────────────────────────────────────────── */}
      <div className="grid no-print" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 14, marginBottom: 20 }}>
        {/* Total Submissions Card */}
        <div className="card kpi-card-print" style={{ padding: '16px 18px', background: 'var(--bg-surface)', border: '1px solid var(--border-default)', borderRadius: 12 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
            <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: 0.5 }}>
              Total Submissions
            </span>
            <div style={{ width: 32, height: 32, borderRadius: 8, background: '#EEF2FF', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#4F46E5' }}>
              <Layers size={16} />
            </div>
          </div>
          <div style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-primary)', lineHeight: 1 }}>
            {analytics.totalCount.toLocaleString()}
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 8, fontSize: 11, color: 'var(--text-subtle)' }}>
            <span>Filtered: <strong>{filteredRows.length}</strong></span>
            <span>•</span>
            <span>All forms: <strong>{rows.length}</strong></span>
          </div>
        </div>

        {/* Total Revenue Card */}
        <div className="card kpi-card-print" style={{ padding: '16px 18px', background: 'var(--bg-surface)', border: '1px solid var(--border-default)', borderRadius: 12 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
            <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: 0.5 }}>
              Collected Revenue
            </span>
            <div style={{ width: 32, height: 32, borderRadius: 8, background: '#ECFDF5', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#059669' }}>
              <DollarSign size={16} />
            </div>
          </div>
          <div style={{ fontSize: '1.75rem', fontWeight: 800, color: '#059669', lineHeight: 1 }}>
            ৳{analytics.totalRevenue.toLocaleString()}
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 8, fontSize: 11, color: 'var(--text-subtle)' }}>
            <span>Avg / Paid: <strong>৳{analytics.avgTicket.toLocaleString()}</strong></span>
          </div>
        </div>

        {/* Conversion Rate Card */}
        <div className="card kpi-card-print" style={{ padding: '16px 18px', background: 'var(--bg-surface)', border: '1px solid var(--border-default)', borderRadius: 12 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
            <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: 0.5 }}>
              Paid Conversion
            </span>
            <div style={{ width: 32, height: 32, borderRadius: 8, background: '#F0F9FF', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#0284C7' }}>
              <TrendingUp size={16} />
            </div>
          </div>
          <div style={{ fontSize: '1.75rem', fontWeight: 800, color: '#0284C7', lineHeight: 1 }}>
            {analytics.conversionRate}%
          </div>
          <div style={{ marginTop: 8, width: '100%', height: 6, background: '#E2E8F0', borderRadius: 3, overflow: 'hidden' }}>
            <div style={{ width: `${analytics.conversionRate}%`, height: '100%', background: '#0284C7', borderRadius: 3, transition: 'width 0.4s ease' }} />
          </div>
        </div>

        {/* Status Breakdown Action Card */}
        <div className="card kpi-card-print" style={{ padding: '16px 18px', background: 'var(--bg-surface)', border: '1px solid var(--border-default)', borderRadius: 12 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
            <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: 0.5 }}>
              Status Breakdown
            </span>
            <div style={{ width: 32, height: 32, borderRadius: 8, background: '#FEF3C7', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#D97706' }}>
              <Clock size={16} />
            </div>
          </div>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 4 }}>
            <span
              onClick={() => setStatusFilter(statusFilter === 'PAID' ? 'ALL' : 'PAID')}
              style={{
                cursor: 'pointer',
                padding: '3px 8px',
                borderRadius: 6,
                fontSize: 11,
                fontWeight: 700,
                background: statusFilter === 'PAID' ? '#059669' : '#ECFDF5',
                color: statusFilter === 'PAID' ? '#FFFFFF' : '#047857',
                border: '1px solid #A7F3D0'
              }}
            >
              PAID: {analytics.paidCount}
            </span>
            <span
              onClick={() => setStatusFilter(statusFilter === 'PENDING' ? 'ALL' : 'PENDING')}
              style={{
                cursor: 'pointer',
                padding: '3px 8px',
                borderRadius: 6,
                fontSize: 11,
                fontWeight: 700,
                background: statusFilter === 'PENDING' ? '#D97706' : '#FFFBEB',
                color: statusFilter === 'PENDING' ? '#FFFFFF' : '#B45309',
                border: '1px solid #FDE68A'
              }}
            >
              PENDING: {analytics.pendingCount}
            </span>
            <span
              onClick={() => setStatusFilter(statusFilter === 'FAILED' ? 'ALL' : 'FAILED')}
              style={{
                cursor: 'pointer',
                padding: '3px 8px',
                borderRadius: 6,
                fontSize: 11,
                fontWeight: 700,
                background: statusFilter === 'FAILED' ? '#DC2626' : '#FEF2F2',
                color: statusFilter === 'FAILED' ? '#FFFFFF' : '#B91C1C',
                border: '1px solid #FECACA'
              }}
            >
              FAILED: {analytics.failedCount}
            </span>
            <span
              onClick={() => setStatusFilter(statusFilter === 'NOT_REQUIRED' ? 'ALL' : 'NOT_REQUIRED')}
              style={{
                cursor: 'pointer',
                padding: '3px 8px',
                borderRadius: 6,
                fontSize: 11,
                fontWeight: 700,
                background: statusFilter === 'NOT_REQUIRED' ? '#475569' : '#F1F5F9',
                color: statusFilter === 'NOT_REQUIRED' ? '#FFFFFF' : '#475569',
                border: '1px solid #E2E8F0'
              }}
            >
              FREE: {analytics.freeCount}
            </span>
          </div>
        </div>
      </div>

      {/* ────────────────────────────────────────────────────────────────────────── */}
      {/* Multi-Criteria Filtering Controls Bar (ফিল্টারিং অপশন থাকবে) */}
      {/* ────────────────────────────────────────────────────────────────────────── */}
      <div className="card no-print" style={{ padding: 14, marginBottom: 16, background: 'var(--bg-surface)', border: '1px solid var(--border-default)', borderRadius: 12 }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          {/* Row 1: Search & Filter Presets */}
          <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'center' }}>
            {/* Search Input */}
            <div style={{ flex: '1 1 300px', display: 'flex', alignItems: 'center', gap: 8, background: 'var(--bg-subtle)', border: '1px solid var(--border-default)', borderRadius: 8, padding: '7px 12px' }}>
              <Search size={16} color="var(--text-subtle)" />
              <input
                type="text"
                placeholder="Search by customer, phone, email, answers, form, TrxID..."
                value={search}
                onChange={e => setSearch(e.target.value)}
                style={{ border: 'none', background: 'transparent', outline: 'none', width: '100%', fontSize: 13, color: 'var(--text-primary)' }}
              />
              {search && (
                <button onClick={() => setSearch('')} style={{ background: 'none', border: 'none', cursor: 'pointer', padding: 2, color: 'var(--text-subtle)' }}>
                  <X size={14} />
                </button>
              )}
            </div>

            {/* Status Filter */}
            <select
              value={statusFilter}
              onChange={e => setStatusFilter(e.target.value)}
              style={{ padding: '8px 12px', borderRadius: 8, border: '1px solid var(--border-default)', background: 'var(--bg-surface)', color: 'var(--text-primary)', fontSize: 12, fontWeight: 600, outline: 'none', cursor: 'pointer' }}
            >
              <option value="ALL">All Statuses</option>
              <option value="PAID">PAID (Completed)</option>
              <option value="PENDING">PENDING</option>
              <option value="FAILED">FAILED</option>
              <option value="NOT_REQUIRED">FREE / Not Required</option>
            </select>

            {/* Payment Method Filter */}
            <select
              value={methodFilter}
              onChange={e => setMethodFilter(e.target.value)}
              style={{ padding: '8px 12px', borderRadius: 8, border: '1px solid var(--border-default)', background: 'var(--bg-surface)', color: 'var(--text-primary)', fontSize: 12, fontWeight: 600, outline: 'none', cursor: 'pointer' }}
            >
              <option value="ALL">All Methods</option>
              <option value="bKash">bKash</option>
              <option value="Nagad">Nagad</option>
              <option value="Rocket">Rocket</option>
              <option value="Upay">Upay</option>
            </select>

            {/* Form Filter */}
            {distinctForms.length > 0 && (
              <select
                value={formFilter}
                onChange={e => setFormFilter(e.target.value)}
                style={{ padding: '8px 12px', borderRadius: 8, border: '1px solid var(--border-default)', background: 'var(--bg-surface)', color: 'var(--text-primary)', fontSize: 12, fontWeight: 600, outline: 'none', cursor: 'pointer', maxWidth: 200 }}
              >
                <option value="ALL">All Forms ({distinctForms.length})</option>
                {distinctForms.map(f => (
                  <option key={f.id} value={f.id}>{f.title}</option>
                ))}
              </select>
            )}

            {/* Date Range Filter */}
            <select
              value={dateFilter}
              onChange={e => setDateFilter(e.target.value)}
              style={{ padding: '8px 12px', borderRadius: 8, border: '1px solid var(--border-default)', background: 'var(--bg-surface)', color: 'var(--text-primary)', fontSize: 12, fontWeight: 600, outline: 'none', cursor: 'pointer' }}
            >
              <option value="ALL">All Time</option>
              <option value="TODAY">Today</option>
              <option value="YESTERDAY">Yesterday</option>
              <option value="7DAYS">Last 7 Days</option>
              <option value="30DAYS">Last 30 Days</option>
              <option value="THIS_MONTH">This Month</option>
            </select>

            {/* Reset Filters */}
            {activeFiltersCount > 0 && (
              <button
                onClick={resetFilters}
                style={{
                  padding: '7px 12px',
                  borderRadius: 8,
                  border: '1px solid #FECACA',
                  background: '#FEF2F2',
                  color: '#DC2626',
                  fontSize: 12,
                  fontWeight: 600,
                  cursor: 'pointer',
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: 5
                }}
              >
                <X size={14} /> Clear Filters ({activeFiltersCount})
              </button>
            )}
          </div>

          {/* Row 2: View Options & Custom Column Toggles */}
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', paddingTop: 8, borderTop: '1px solid var(--border-subtle)', flexWrap: 'wrap', gap: 10 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
              <span style={{ fontSize: 12, color: 'var(--text-muted)', fontWeight: 500 }}>
                Showing <strong>{filteredRows.length}</strong> of <strong>{rows.length}</strong> submissions
              </span>

              {selectedRowIds.size > 0 && (
                <span style={{ fontSize: 11, fontWeight: 700, color: '#4F46E5', background: '#EEF2FF', padding: '2px 8px', borderRadius: 6 }}>
                  {selectedRowIds.size} rows selected
                </span>
              )}
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
              {/* Density Toggle */}
              <div style={{ display: 'flex', background: 'var(--bg-subtle)', padding: 2, borderRadius: 6, border: '1px solid var(--border-default)' }}>
                <button
                  onClick={() => setDensity('compact')}
                  style={{
                    padding: '3px 8px',
                    borderRadius: 4,
                    border: 'none',
                    fontSize: 11,
                    fontWeight: 600,
                    cursor: 'pointer',
                    background: density === 'compact' ? 'var(--bg-surface)' : 'transparent',
                    color: density === 'compact' ? 'var(--text-primary)' : 'var(--text-subtle)',
                    boxShadow: density === 'compact' ? '0 1px 2px rgba(0,0,0,0.06)' : 'none'
                  }}
                >
                  Compact
                </button>
                <button
                  onClick={() => setDensity('comfortable')}
                  style={{
                    padding: '3px 8px',
                    borderRadius: 4,
                    border: 'none',
                    fontSize: 11,
                    fontWeight: 600,
                    cursor: 'pointer',
                    background: density === 'comfortable' ? 'var(--bg-surface)' : 'transparent',
                    color: density === 'comfortable' ? 'var(--text-primary)' : 'var(--text-subtle)',
                    boxShadow: density === 'comfortable' ? '0 1px 2px rgba(0,0,0,0.06)' : 'none'
                  }}
                >
                  Comfortable
                </button>
              </div>

              {/* Dynamic Columns Toggle */}
              {allCustomFieldKeys.length > 0 && (
                <div style={{ position: 'relative' }}>
                  <button
                    onClick={() => setShowColumnPicker(!showColumnPicker)}
                    style={{
                      padding: '5px 10px',
                      borderRadius: 6,
                      border: '1px solid var(--border-default)',
                      background: showCustomColumns ? '#EEF2FF' : 'var(--bg-surface)',
                      color: showCustomColumns ? '#4F46E5' : 'var(--text-secondary)',
                      fontSize: 12,
                      fontWeight: 600,
                      cursor: 'pointer',
                      display: 'inline-flex',
                      alignItems: 'center',
                      gap: 6
                    }}
                  >
                    <Columns3 size={14} />
                    <span>Spreadsheet Columns ({visibleCustomFields.length})</span>
                    <ChevronDown size={12} />
                  </button>

                  {/* Dropdown to toggle individual custom question columns */}
                  {showColumnPicker && (
                    <div
                      style={{
                        position: 'absolute',
                        right: 0,
                        top: '100%',
                        marginTop: 6,
                        background: 'var(--bg-surface)',
                        border: '1px solid var(--border-default)',
                        borderRadius: 8,
                        boxShadow: '0 8px 24px rgba(0,0,0,0.12)',
                        padding: 10,
                        zIndex: 50,
                        minWidth: 220,
                        maxHeight: 280,
                        overflowY: 'auto'
                      }}
                    >
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8, paddingBottom: 6, borderBottom: '1px solid var(--border-subtle)' }}>
                        <span style={{ fontSize: 11, fontWeight: 700, color: 'var(--text-muted)' }}>FORM QUESTIONS</span>
                        <button
                          onClick={() => setShowCustomColumns(!showCustomColumns)}
                          style={{ background: 'none', border: 'none', color: '#4F46E5', fontSize: 11, fontWeight: 700, cursor: 'pointer' }}
                        >
                          {showCustomColumns ? 'Hide All' : 'Show All'}
                        </button>
                      </div>
                      {allCustomFieldKeys.map(k => {
                        const isChecked = visibleCustomFields.includes(k);
                        return (
                          <label key={k} style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '4px 0', fontSize: 12, cursor: 'pointer', color: 'var(--text-primary)' }}>
                            <input
                              type="checkbox"
                              checked={isChecked}
                              onChange={() => {
                                if (isChecked) {
                                  setVisibleCustomFields(visibleCustomFields.filter(f => f !== k));
                                } else {
                                  setVisibleCustomFields([...visibleCustomFields, k]);
                                }
                              }}
                            />
                            <span style={{ textTransform: 'capitalize' }}>{k.replace(/_/g, ' ')}</span>
                          </label>
                        );
                      })}
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* ────────────────────────────────────────────────────────────────────────── */}
      {/* Excel Spreadsheet Style Submissions Table */}
      {/* ────────────────────────────────────────────────────────────────────────── */}
      <div className="excel-grid-wrapper" ref={printableRef}>
        {loading ? (
          <div style={{ padding: 40, textAlign: 'center', color: 'var(--text-muted)' }}>
            <div className="animate-spin" style={{ display: 'inline-block', width: 24, height: 24, border: '3px solid #E2E8F0', borderTopColor: '#107C41', borderRadius: '50%', marginBottom: 8 }} />
            <div>Loading form submissions from database...</div>
          </div>
        ) : filteredRows.length === 0 ? (
          <div style={{ padding: 50, textAlign: 'center', color: 'var(--text-muted)' }}>
            <FileSpreadsheet size={36} color="#94A3B8" style={{ margin: '0 auto 10px' }} />
            <h3 style={{ margin: 0, fontSize: '1.1rem', color: 'var(--text-primary)' }}>No submissions found</h3>
            <p style={{ margin: '6px 0 16px', fontSize: 13 }}>
              {search || activeFiltersCount > 0
                ? 'Try adjusting your search query or filter settings.'
                : 'Form submissions will automatically appear here in real-time when customers submit your payment forms.'}
            </p>
            {activeFiltersCount > 0 && (
              <button className="button" onClick={resetFilters} style={{ background: '#4F46E5', color: '#FFFFFF' }}>
                Reset All Filters
              </button>
            )}
          </div>
        ) : (
          <table className="excel-table">
            <thead>
              <tr>
                {/* Row Number Column (#) */}
                <th className="excel-row-num no-print" style={{ width: 44 }}>
                  <input
                    type="checkbox"
                    checked={selectedRowIds.size === filteredRows.length && filteredRows.length > 0}
                    onChange={toggleSelectAll}
                    title="Select all rows"
                  />
                </th>

                {/* Submitted Date */}
                <th onClick={() => handleSort('createdAt')} style={{ cursor: 'pointer' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                    <span>Submitted At</span>
                    {sortField === 'createdAt' && (sortOrder === 'asc' ? <ChevronUp size={14} /> : <ChevronDown size={14} />)}
                  </div>
                </th>

                {/* Form Title */}
                <th onClick={() => handleSort('formTitle')} style={{ cursor: 'pointer' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                    <span>Form Name</span>
                    {sortField === 'formTitle' && (sortOrder === 'asc' ? <ChevronUp size={14} /> : <ChevronDown size={14} />)}
                  </div>
                </th>

                {/* Customer */}
                <th onClick={() => handleSort('customer_name')} style={{ cursor: 'pointer' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                    <span>Customer</span>
                    {sortField === 'customer_name' && (sortOrder === 'asc' ? <ChevronUp size={14} /> : <ChevronDown size={14} />)}
                  </div>
                </th>

                {/* Phone */}
                <th>Phone</th>

                {/* Amount */}
                <th onClick={() => handleSort('amount_bdt')} style={{ cursor: 'pointer', textAlign: 'right' }}>
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'flex-end', gap: 6 }}>
                    <span>Amount (BDT)</span>
                    {sortField === 'amount_bdt' && (sortOrder === 'asc' ? <ChevronUp size={14} /> : <ChevronDown size={14} />)}
                  </div>
                </th>

                {/* Method */}
                <th>Method</th>

                {/* Status */}
                <th>Status</th>

                {/* Dynamic Custom Question Columns (Excel style) */}
                {showCustomColumns && visibleCustomFields.map(f => (
                  <th key={f} style={{ background: '#F8FAFC', color: '#4F46E5', textTransform: 'capitalize' }}>
                    {f.replace(/_/g, ' ')}
                  </th>
                ))}

                {/* Trx ID */}
                <th>Trx ID</th>

                {/* Actions */}
                <th className="no-print" style={{ textAlign: 'center', width: 90 }}>Action</th>
              </tr>
            </thead>
            <tbody>
              {filteredRows.map((r, idx) => {
                const isSelected = selectedRowIds.has(r.id);
                const isPaid = (r.payment_status || '').toUpperCase() === 'PAID';
                const isPending = (r.payment_status || '').toUpperCase() === 'PENDING';
                const isFailed = (r.payment_status || '').toUpperCase() === 'FAILED';

                return (
                  <tr
                    key={r.id}
                    className={`${isSelected ? 'row-selected' : ''}`}
                    onDoubleClick={() => setSelectedSub(r)}
                  >
                    {/* Row Index */}
                    <td className="excel-row-num no-print">
                      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 4 }}>
                        <input
                          type="checkbox"
                          checked={isSelected}
                          onChange={() => toggleSelectRow(r.id)}
                        />
                        <span>{idx + 1}</span>
                      </div>
                    </td>

                    {/* Date */}
                    <td style={{ fontSize: 11.5, color: '#475569' }}>
                      <span title={new Date(r.createdAt).toLocaleString()}>
                        {new Date(r.createdAt).toLocaleDateString()} {new Date(r.createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                      </span>
                    </td>

                    {/* Form Name */}
                    <td>
                      <div style={{ fontWeight: 600, color: 'var(--text-primary)', maxWidth: 180, overflow: 'hidden', textOverflow: 'ellipsis' }} title={r.formTitle}>
                        {r.formTitle}
                      </div>
                      <div style={{ fontSize: 10.5, color: '#94A3B8', fontFamily: 'monospace' }}>
                        ID: {r.formId ? r.formId.slice(0, 8) : '--'}
                      </div>
                    </td>

                    {/* Customer */}
                    <td>
                      <div style={{ fontWeight: 700, color: 'var(--text-primary)' }}>
                        {r.customer_name || <span style={{ color: '#94A3B8' }}>Anonymous</span>}
                      </div>
                      {r.customer_email && (
                        <div style={{ fontSize: 11, color: '#64748B' }}>
                          <a href={`mailto:${r.customer_email}`} style={{ color: 'inherit', textDecoration: 'none' }}>
                            {r.customer_email}
                          </a>
                        </div>
                      )}
                    </td>

                    {/* Phone */}
                    <td>
                      {r.customer_phone ? (
                        <div style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}>
                          <a href={`tel:${r.customer_phone}`} style={{ color: '#0369A1', fontWeight: 600, textDecoration: 'none', fontFamily: 'monospace' }}>
                            {r.customer_phone}
                          </a>
                          <button
                            onClick={() => copyToClipboard(r.customer_phone || '', `phone_${r.id}`)}
                            className="no-print"
                            title="Copy Phone"
                            style={{ background: 'none', border: 'none', cursor: 'pointer', padding: 2, color: '#94A3B8' }}
                          >
                            {copiedKey === `phone_${r.id}` ? <Check size={12} color="#059669" /> : <Copy size={12} />}
                          </button>
                        </div>
                      ) : (
                        <span style={{ color: '#CBD5E1' }}>—</span>
                      )}
                    </td>

                    {/* Amount */}
                    <td style={{ textAlign: 'right', fontWeight: 800, color: isPaid ? '#059669' : 'var(--text-primary)' }}>
                      ৳{Number(r.amount_bdt || 0).toLocaleString()}
                    </td>

                    {/* Method */}
                    <td>
                      <span style={{
                        display: 'inline-block',
                        padding: '2px 7px',
                        borderRadius: 4,
                        fontSize: 11,
                        fontWeight: 700,
                        background: r.payment_method === 'bKash' ? '#FDF2F8' : r.payment_method === 'Nagad' ? '#FFFBEB' : '#F1F5F9',
                        color: r.payment_method === 'bKash' ? '#BE185D' : r.payment_method === 'Nagad' ? '#B45309' : '#334155',
                        border: '1px solid rgba(0,0,0,0.06)'
                      }}>
                        {r.payment_method || 'bKash'}
                      </span>
                    </td>

                    {/* Status */}
                    <td>
                      <span style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 4,
                        padding: '2px 8px',
                        borderRadius: 6,
                        fontSize: 10.5,
                        fontWeight: 700,
                        background: isPaid ? '#ECFDF5' : isPending ? '#FFFBEB' : isFailed ? '#FEF2F2' : '#F1F5F9',
                        color: isPaid ? '#047857' : isPending ? '#B45309' : isFailed ? '#B91C1C' : '#475569',
                        border: `1px solid ${isPaid ? '#A7F3D0' : isPending ? '#FDE68A' : isFailed ? '#FECACA' : '#E2E8F0'}`
                      }}>
                        {isPaid && <CheckCircle2 size={11} />}
                        {isPending && <Clock size={11} />}
                        {isFailed && <AlertTriangle size={11} />}
                        {r.payment_status || 'NOT_REQUIRED'}
                      </span>
                    </td>

                    {/* Dynamic Custom Question Values */}
                    {showCustomColumns && visibleCustomFields.map(f => (
                      <td key={f} style={{ fontSize: 12 }}>
                        {renderAnswerCell(r.submission?.[f])}
                      </td>
                    ))}

                    {/* Trx ID */}
                    <td>
                      {r.trx_id ? (
                        <div style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}>
                          <code style={{ fontSize: 11, background: '#F1F5F9', padding: '1px 5px', borderRadius: 4, color: '#334155' }}>
                            {r.trx_id}
                          </code>
                          <button
                            onClick={() => copyToClipboard(r.trx_id || '', `trx_${r.id}`)}
                            className="no-print"
                            title="Copy TrxID"
                            style={{ background: 'none', border: 'none', cursor: 'pointer', padding: 2, color: '#94A3B8' }}
                          >
                            {copiedKey === `trx_${r.id}` ? <Check size={12} color="#059669" /> : <Copy size={12} />}
                          </button>
                        </div>
                      ) : (
                        <span style={{ color: '#CBD5E1' }}>—</span>
                      )}
                    </td>

                    {/* Action Button */}
                    <td className="no-print" style={{ textAlign: 'center' }}>
                      <button
                        onClick={() => setSelectedSub(r)}
                        className="button"
                        style={{
                          padding: '4px 10px',
                          fontSize: 11,
                          fontWeight: 600,
                          background: '#4F46E5',
                          color: '#FFFFFF',
                          borderRadius: 6,
                          display: 'inline-flex',
                          alignItems: 'center',
                          gap: 4
                        }}
                      >
                        <Eye size={12} /> View
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>

            {/* Excel Totals Summary Row */}
            <tfoot>
              <tr style={{ background: '#F1F5F9', fontWeight: 800, borderTop: '2px solid #CBD5E1' }}>
                <td className="excel-row-num no-print" style={{ fontWeight: 800 }}>Σ</td>
                <td>Total: {filteredRows.length} Rows</td>
                <td>--</td>
                <td>--</td>
                <td>--</td>
                <td style={{ textAlign: 'right', color: '#059669', fontSize: 14 }}>
                  ৳{analytics.totalRevenue.toLocaleString()}
                </td>
                <td>--</td>
                <td>Paid: {analytics.paidCount}</td>
                {showCustomColumns && visibleCustomFields.map(f => (
                  <td key={f}>--</td>
                ))}
                <td>--</td>
                <td className="no-print">--</td>
              </tr>
            </tfoot>
          </table>
        )}
      </div>

      {/* ────────────────────────────────────────────────────────────────────────── */}
      {/* Modal Replacement: Structured Question & Answer Inspection Drawer */}
      {/* (NO RAW JSON! Beautiful Key-Value Form Table with Single-Record Print) */}
      {/* ────────────────────────────────────────────────────────────────────────── */}
      {selectedSub && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(15, 23, 42, 0.65)', backdropFilter: 'blur(3px)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000, padding: 16 }}>
          <div
            className="card"
            style={{
              maxWidth: 720,
              width: '100%',
              background: '#FFFFFF',
              maxHeight: '90vh',
              overflowY: 'auto',
              borderRadius: 14,
              boxShadow: '0 20px 40px rgba(0,0,0,0.2)',
              padding: 0
            }}
          >
            {/* Modal Header */}
            <div style={{ padding: '16px 20px', background: '#F8FAFC', borderBottom: '1px solid #E2E8F0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <div style={{ width: 34, height: 34, borderRadius: 8, background: '#EEF2FF', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#4F46E5' }}>
                  <FileText size={18} />
                </div>
                <div>
                  <h3 style={{ margin: 0, fontSize: '1.15rem', fontWeight: 800, color: '#0F172A' }}>
                    {selectedSub.formTitle || 'Submission Response'}
                  </h3>
                  <div style={{ fontSize: 11, color: '#64748B', display: 'flex', alignItems: 'center', gap: 6, marginTop: 2 }}>
                    <span>ID: <code>{selectedSub.id}</code></span>
                    <span>•</span>
                    <span>{new Date(selectedSub.createdAt).toLocaleString()}</span>
                  </div>
                </div>
              </div>
              <button
                onClick={() => setSelectedSub(null)}
                aria-label="Close modal"
                style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#64748B', padding: 4, display: 'flex', alignItems: 'center', borderRadius: 6 }}
              >
                <X size={20} />
              </button>
            </div>

            <div style={{ padding: 20 }}>
              {/* Payment & Customer Summary Banner */}
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: 12, marginBottom: 20 }}>
                {/* Customer Box */}
                <div style={{ padding: 12, borderRadius: 8, border: '1px solid #E2E8F0', background: '#F8FAFC' }}>
                  <div style={{ fontSize: 11, fontWeight: 700, color: '#64748B', marginBottom: 6, textTransform: 'uppercase', display: 'flex', alignItems: 'center', gap: 5 }}>
                    <User size={13} /> Customer Details
                  </div>
                  <div style={{ fontWeight: 700, fontSize: 14, color: '#0F172A' }}>
                    {selectedSub.customer_name || 'Anonymous Customer'}
                  </div>
                  {selectedSub.customer_phone && (
                    <div style={{ fontSize: 12, color: '#0369A1', marginTop: 2, display: 'flex', alignItems: 'center', gap: 4 }}>
                      <Phone size={12} /> <a href={`tel:${selectedSub.customer_phone}`} style={{ color: 'inherit', textDecoration: 'none' }}>{selectedSub.customer_phone}</a>
                    </div>
                  )}
                  {selectedSub.customer_email && (
                    <div style={{ fontSize: 12, color: '#475569', marginTop: 2, display: 'flex', alignItems: 'center', gap: 4 }}>
                      <Mail size={12} /> <a href={`mailto:${selectedSub.customer_email}`} style={{ color: 'inherit', textDecoration: 'none' }}>{selectedSub.customer_email}</a>
                    </div>
                  )}
                </div>

                {/* Payment Box */}
                <div style={{ padding: 12, borderRadius: 8, border: '1px solid #E2E8F0', background: '#F8FAFC' }}>
                  <div style={{ fontSize: 11, fontWeight: 700, color: '#64748B', marginBottom: 6, textTransform: 'uppercase', display: 'flex', alignItems: 'center', gap: 5 }}>
                    <CreditCard size={13} /> Payment Info
                  </div>
                  <div style={{ display: 'flex', alignItems: 'baseline', gap: 8 }}>
                    <span style={{ fontSize: '1.25rem', fontWeight: 800, color: selectedSub.payment_status === 'PAID' ? '#059669' : '#0F172A' }}>
                      ৳{Number(selectedSub.amount_bdt || 0).toLocaleString()}
                    </span>
                    <span style={{
                      padding: '2px 6px',
                      borderRadius: 4,
                      fontSize: 10.5,
                      fontWeight: 700,
                      background: selectedSub.payment_status === 'PAID' ? '#ECFDF5' : '#FFFBEB',
                      color: selectedSub.payment_status === 'PAID' ? '#047857' : '#B45309'
                    }}>
                      {selectedSub.payment_status || 'NOT_REQUIRED'}
                    </span>
                  </div>
                  <div style={{ fontSize: 11, color: '#64748B', marginTop: 4 }}>
                    Method: <strong>{selectedSub.payment_method || 'bKash'}</strong>
                    {selectedSub.trx_id && (
                      <span style={{ marginLeft: 8 }}>
                        Trx: <code>{selectedSub.trx_id}</code>
                      </span>
                    )}
                  </div>
                </div>
              </div>

              {/* Questions & Responses Section */}
              <div style={{ marginBottom: 14 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 }}>
                  <h4 style={{ margin: 0, fontSize: '0.95rem', fontWeight: 800, color: '#0F172A', display: 'flex', alignItems: 'center', gap: 6 }}>
                    <FileSpreadsheet size={16} color="#107C41" /> Form Questions & Customer Answers
                  </h4>
                  <span style={{ fontSize: 11, color: '#64748B' }}>
                    {Object.keys(selectedSub.submission || {}).length} Fields Captured
                  </span>
                </div>

                {/* Structured Excel-Style Key-Value Table */}
                {Object.keys(selectedSub.submission || {}).length === 0 ? (
                  <div style={{ padding: 20, textAlign: 'center', background: '#F8FAFC', borderRadius: 8, border: '1px solid #E2E8F0', color: '#94A3B8', fontSize: 12 }}>
                    No custom questionnaire responses recorded for this submission.
                  </div>
                ) : (
                  <div style={{ border: '1px solid #E2E8F0', borderRadius: 8, overflow: 'hidden' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12 }}>
                      <thead>
                        <tr style={{ background: '#F1F5F9', borderBottom: '1px solid #E2E8F0' }}>
                          <th style={{ padding: '8px 12px', textAlign: 'left', fontWeight: 700, color: '#475569', width: '38%' }}>Question / Field</th>
                          <th style={{ padding: '8px 12px', textAlign: 'left', fontWeight: 700, color: '#475569' }}>Customer Response</th>
                        </tr>
                      </thead>
                      <tbody>
                        {Object.entries(selectedSub.submission || {}).map(([key, val], i) => {
                          const prettyKey = key.replace(/_/g, ' ');
                          return (
                            <tr key={key} style={{ borderBottom: '1px solid #F1F5F9', background: i % 2 === 0 ? '#FFFFFF' : '#F8FAFC' }}>
                              <td style={{ padding: '8px 12px', fontWeight: 600, color: '#1E293B', textTransform: 'capitalize', verticalAlign: 'top' }}>
                                {prettyKey}
                              </td>
                              <td style={{ padding: '8px 12px', color: '#334155', verticalAlign: 'top', wordBreak: 'break-word' }}>
                                {typeof val === 'object' && val !== null ? (
                                  val.file_name || val.url ? (
                                    <a
                                      href={val.url || '#'}
                                      target="_blank"
                                      rel="noopener noreferrer"
                                      style={{ color: '#4F46E5', textDecoration: 'underline', display: 'inline-flex', alignItems: 'center', gap: 4 }}
                                    >
                                      <FileDown size={14} /> {val.file_name || 'Download Attachment'}
                                    </a>
                                  ) : (
                                    <div style={{ background: '#F1F5F9', padding: '4px 8px', borderRadius: 4, fontFamily: 'monospace', fontSize: 11 }}>
                                      {Object.entries(val).map(([k2, v2]) => `${k2}: ${v2}`).join(' | ')}
                                    </div>
                                  )
                                ) : (
                                  String(val || '—')
                                )}
                              </td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            </div>

            {/* Modal Footer with Print and Copy Actions */}
            <div style={{ padding: '14px 20px', background: '#F8FAFC', borderTop: '1px solid #E2E8F0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <button
                className="button"
                onClick={() => {
                  const textContent = Object.entries(selectedSub.submission || {})
                    .map(([k, v]) => `${k.replace(/_/g, ' ')}: ${typeof v === 'object' ? JSON.stringify(v) : v}`)
                    .join('\n');
                  copyToClipboard(textContent, 'all_answers');
                }}
                style={{ background: 'var(--bg-surface)', color: 'var(--text-primary)', border: '1px solid var(--border-default)', fontSize: 12, display: 'inline-flex', alignItems: 'center', gap: 6 }}
              >
                {copiedKey === 'all_answers' ? <Check size={14} color="#059669" /> : <Copy size={14} />}
                {copiedKey === 'all_answers' ? 'Copied Answers!' : 'Copy Answers'}
              </button>

              <div style={{ display: 'flex', gap: 8 }}>
                <button
                  className="button"
                  onClick={() => window.print()}
                  style={{ background: '#4F46E5', color: '#FFFFFF', fontSize: 12, display: 'inline-flex', alignItems: 'center', gap: 6 }}
                >
                  <Printer size={14} /> Print Response PDF
                </button>
                <button
                  className="button"
                  onClick={() => setSelectedSub(null)}
                  style={{ background: '#64748B', color: '#FFFFFF', fontSize: 12 }}
                >
                  Close
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
