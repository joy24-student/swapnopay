package com.example.data.local

import androidx.room.Entity
import androidx.room.Index
import androidx.room.PrimaryKey
import androidx.room.ColumnInfo

@Entity(tableName = "sms_queue", indices = [Index("merchantId")])
data class SmsQueueEntity(
    @PrimaryKey(autoGenerate = true) val id: Int = 0,
    @ColumnInfo(defaultValue = "'00000000-0000-0000-0000-000000000001'") val merchantId: String,
    val amount: Double,
    val sender: String,
    val trxId: String,
    val timestamp: Long,
    val merchantNumber: String,
    val status: String, // "PENDING", "SYNCED", "FAILED"
    val rawBody: String
)

@Entity(tableName = "cached_orders", indices = [Index("merchantId")])
data class CachedOrderEntity(
    @PrimaryKey val id: String,
    @ColumnInfo(defaultValue = "'00000000-0000-0000-0000-000000000001'") val merchantId: String,
    val customerName: String,
    val customerPhone: String,
    val amount: Double,
    val status: String, // "PENDING", "PAID", "EXPIRED", "CANCELLED"
    val method: String, // "bKash", "Nagad", "Rocket"
    val createdAt: Long,
    val expiresAt: Long,
    val notes: String = ""
)

@Entity(tableName = "cached_payments", indices = [Index("merchantId")])
data class CachedPaymentEntity(
    @PrimaryKey val id: String, // transaction ID
    @ColumnInfo(defaultValue = "'00000000-0000-0000-0000-000000000001'") val merchantId: String,
    val amount: Double,
    val sender: String,
    val timestamp: Long,
    val status: String, // "MATCHED", "UNMATCHED", "DUPLICATE"
    val method: String, // "bKash", "Nagad", "Rocket"
    val orderId: String? = null
)

@Entity(tableName = "appeals", indices = [Index("merchantId")])
data class AppealEntity(
    @PrimaryKey val id: String,
    @ColumnInfo(defaultValue = "'00000000-0000-0000-0000-000000000001'") val merchantId: String,
    val orderId: String,
    val amount: Double,
    val customerName: String,
    val customerPhone: String,
    val trxId: String,
    val timestamp: Long,
    val status: String // "PENDING_REVIEW", "APPROVED", "REJECTED"
)

@Entity(tableName = "merchant_profile")
data class MerchantProfileEntity(
    @PrimaryKey val id: String,
    val businessName: String,
    val email: String,
    val phone: String,
    val businessType: String,
    val website: String,
    val primaryBank: String,
    val accountHolder: String,
    val accountNumber: String,
    val kycStatus: String, // "VERIFIED", "PENDING", "UNVERIFIED", "REJECTED"
    val photoUrl: String = "",
    val kycRejectionReason: String = "",
    @androidx.room.ColumnInfo(defaultValue = "''") val nidNumber: String = "",
    @androidx.room.ColumnInfo(defaultValue = "''") val nidFrontUrl: String = "",
    @androidx.room.ColumnInfo(defaultValue = "''") val nidBackUrl: String = ""
)

@Entity(tableName = "devices", indices = [Index("merchantId")])
data class DeviceInfoEntity(
    @PrimaryKey val id: String,
    @ColumnInfo(defaultValue = "'00000000-0000-0000-0000-000000000001'") val merchantId: String,
    val deviceName: String,
    val status: String, // "ONLINE", "OFFLINE"
    val batteryLevel: Int,
    val lastSyncTime: Long
)

@Entity(tableName = "supabase_profiles")
data class SupabaseProfileEntity(
    @PrimaryKey val id: String,
    val businessName: String,
    val supabaseUrl: String,
    val anonKey: String,
    val serviceRoleKey: String = "",
    val authEmail: String = "",
    val authSessionToken: String = "",
    @ColumnInfo(defaultValue = "''") val authRefreshToken: String = "",
    @ColumnInfo(defaultValue = "0") val authTokenExpiresAt: Long = 0L,
    val isActive: Boolean = false,
    val defaultNumber: String = "",
    @ColumnInfo(defaultValue = "0") val lastSyncAt: Long = 0L,
    @ColumnInfo(defaultValue = "''") val lastSyncError: String = ""
)

@Entity(tableName = "mfs_patterns")
data class MfsPatternEntity(
    @PrimaryKey val id: String,
    val mfsName: String,
    val patternName: String,
    val regexPattern: String,
    val active: Boolean
)

@Entity(tableName = "customers", indices = [Index("merchantId")])
data class CustomerEntity(
    @PrimaryKey val id: String,
    val merchantId: String,
    val name: String,
    val phone: String,
    val email: String?,
    val address: String?,
    val openingBalance: Double,
    val currentBalance: Double,
    val status: String = "VIP", // VIP, Risk, Inactive, Potential
    val createdAt: Long = System.currentTimeMillis(),
    @ColumnInfo(defaultValue = "''") val code: String = ""
)

@Entity(tableName = "suppliers", indices = [Index("merchantId")])
data class SupplierEntity(
    @PrimaryKey val id: String,
    val merchantId: String,
    val name: String,
    val phone: String,
    val email: String?,
    val address: String?,
    val openingBalance: Double,
    val currentBalance: Double,
    val createdAt: Long = System.currentTimeMillis(),
    @ColumnInfo(defaultValue = "''") val code: String = ""
)

@Entity(tableName = "ledger_transactions", indices = [Index("merchantId")])
data class LedgerTransactionEntity(
    @PrimaryKey val id: String,
    val merchantId: String,
    val customerId: String? = null,
    val supplierId: String? = null,
    val type: String, // "credit", "payment"
    val amount: Double,
    val date: Long = System.currentTimeMillis(),
    val note: String? = null,
    val productDetailsJson: String = "[]",
    val isVoiceEntry: Boolean = false,
    val attachmentUri: String? = null,
    val paymentMethod: String = "Cash", // Cash, bKash, Card, Bank Transfer
    val invoiceNo: String? = null,
    val tagadaSentAt: Long? = null,
    val isSynced: Boolean = false
)

@Entity(tableName = "pos_sales", indices = [Index("merchantId")])
data class PosSaleEntity(
    @PrimaryKey val id: String,
    val merchantId: String,
    val invoiceNo: String,
    val customerId: String? = null,
    val customerName: String = "Walk-in Customer",
    val customerPhone: String = "",
    val subtotal: Double,
    val discount: Double = 0.0,
    val netTotal: Double,
    val cashReceived: Double,
    val changeDue: Double = 0.0,
    val paymentMethod: String = "Cash", // Cash, bKash, Card, Due
    val paymentStatus: String = "PAID", // PAID, PARTIAL, DUE
    val itemCount: Int = 1,
    val cartItemsJson: String = "[]",
    val timestamp: Long = System.currentTimeMillis(),
    val isSynced: Boolean = false
)

@Entity(tableName = "products", indices = [Index("merchantId")])
data class ProductItemEntity(
    @PrimaryKey val id: String,
    val merchantId: String,
    val name: String,
    val code: String? = null,
    val category: String? = "General",
    val purchasePrice: Double = 0.0,
    val salePrice: Double = 0.0,
    val stockQuantity: Double = 0.0,
    val minStockThreshold: Double = 5.0,
    val unit: String = "pcs",
    val qrCode: String? = null,             // QR Code / Barcode identifier
    val imageUrl: String? = null,
    val createdAt: Long = System.currentTimeMillis(),
    val costPrice: Double = purchasePrice,    // Buying / Real Prize
    val askingPrice: Double = salePrice,      // Tag / List Prize
    @androidx.room.ColumnInfo(defaultValue = "'{}'") val storefrontDetailsJson: String = "{}"
)

@Entity(tableName = "product_variants", indices = [Index("merchantId")])
data class ProductVariantEntity(
    @PrimaryKey val id: String,
    val merchantId: String,
    val productId: String,
    val variantName: String,         // e.g. "Size XL", "Red 500g"
    val supplierId: String? = null,
    val qrCode: String,              // Unique QR code identifier
    val costPrice: Double = 0.0,     // Real Prize / Purchase cost
    val askingPrice: Double = 0.0,   // Asking Prize / MSRP
    val salePrice: Double = 0.0,     // Selling Prize
    val stockQuantity: Double = 0.0,
    val createdAt: Long = System.currentTimeMillis()
)

@Entity(tableName = "stock_transactions", indices = [Index("merchantId")])
data class StockTransactionEntity(
    @PrimaryKey val id: String = java.util.UUID.randomUUID().toString(),
    val merchantId: String,
    val productId: String,
    val variantId: String? = null,
    val type: String, // "in", "out"
    val quantity: Double,
    val price: Double = 0.0,
    val customerId: String? = null,
    val supplierId: String? = null,
    val referenceNote: String? = "Stock Transaction",
    val createdAt: Long = System.currentTimeMillis()
)

@Entity(tableName = "expenses", indices = [Index("merchantId")])
data class ExpenseEntity(
    @PrimaryKey val id: String = java.util.UUID.randomUUID().toString(),
    val merchantId: String,
    val category: String, // Utilities, Rent, Transport, Operating, Payroll, Others
    val amount: Double,
    val date: Long = System.currentTimeMillis(),
    val description: String? = null,
    val receiptImageUrl: String? = null,
    val paymentMethod: String = "Cash",
    val isRecurring: Boolean = false,
    val createdAt: Long = System.currentTimeMillis()
)

@Entity(tableName = "loans", indices = [Index("merchantId")])
data class BusinessLoanEntity(
    @PrimaryKey val id: String = java.util.UUID.randomUUID().toString(),
    val merchantId: String,
    val principalAmount: Double,
    val interestRate: Double,
    val interestType: String, // Flat, Reducing
    val durationMonths: Int,
    val monthlyInstallment: Double,
    @ColumnInfo(defaultValue = "''") val providerName: String = "",
    @ColumnInfo(defaultValue = "''") val accountReference: String = "",
    @ColumnInfo(defaultValue = "0") val startDate: Long = 0L,
    @ColumnInfo(defaultValue = "0") val isSynced: Boolean = false,
    val status: String = "applied", // applied, approved, disbursed, repaid
    val appliedAt: Long = System.currentTimeMillis(),
    val disbursedAt: Long? = null
)

/** A merchant-owned recurring deposit account. No bank credentials are stored. */
@Entity(
    tableName = "dps_accounts",
    indices = [Index("merchantId"), Index(value = ["merchantId", "accountReference"], unique = true)]
)
data class DpsAccountEntity(
    @PrimaryKey val id: String,
    val merchantId: String,
    val providerName: String,
    val accountReference: String,
    val monthlyDeposit: Double,
    val interestRate: Double,
    val durationMonths: Int,
    val startDate: Long,
    val maturityDate: Long,
    val status: String = "ACTIVE", // ACTIVE, MATURED, CLOSED
    val isSynced: Boolean = false,
    val createdAt: Long = System.currentTimeMillis(),
    val updatedAt: Long = System.currentTimeMillis()
)

/** Materialized DPS/loan schedule. Guarded updates make payments idempotent. */
@Entity(
    tableName = "finance_installments",
    indices = [
        Index("merchantId"),
        Index(value = ["merchantId", "accountType", "accountId", "installmentNumber"], unique = true),
        Index(value = ["merchantId", "paymentReference"], unique = true)
    ]
)
data class FinanceInstallmentEntity(
    @PrimaryKey val id: String,
    val merchantId: String,
    val accountType: String, // DPS, LOAN
    val accountId: String,
    val installmentNumber: Int,
    val dueDate: Long,
    val principalAmount: Double,
    val interestAmount: Double = 0.0,
    val totalAmount: Double,
    val status: String = "PENDING", // PENDING, PAID, OVERDUE, WAIVED
    val paidAt: Long? = null,
    val paymentMethod: String? = null,
    val paymentReference: String? = null,
    val isSynced: Boolean = false,
    val createdAt: Long = System.currentTimeMillis()
)

/** Durable in-app notification mirrored from merchant database events. */
@Entity(tableName = "merchant_notifications", indices = [Index("merchantId"), Index("createdAt")])
data class MerchantNotificationEntity(
    @PrimaryKey val id: String,
    val merchantId: String,
    val type: String,
    val title: String,
    val message: String,
    val severity: String = "INFO", // INFO, SUCCESS, WARNING, ERROR
    val entityType: String? = null,
    val entityId: String? = null,
    val createdAt: Long = System.currentTimeMillis(),
    val readAt: Long? = null
)

@Entity(tableName = "business_analytics", indices = [Index("merchantId")])
data class BusinessAnalyticsEntity(
    @PrimaryKey val id: String,
    val merchantId: String,
    val totalRevenue: Double = 0.0,
    val cashReceived: Double = 0.0,
    val totalDues: Double = 0.0,
    val totalPayables: Double = 0.0,
    val totalExpenses: Double = 0.0,
    val netProfit: Double = 0.0,
    val lastUpdated: Long = System.currentTimeMillis()
)

/** Team members are cached locally so the Employees screen remains usable offline. */
@Entity(tableName = "employees", indices = [Index("merchantId")])
data class EmployeeEntity(
    @PrimaryKey val id: String,
    val merchantId: String,
    val name: String,
    val designation: String,
    val role: String,
    val email: String,
    val phone: String,
    val department: String = "General",
    val status: String = "Active",
    val avatarUrl: String? = null,
    val permissionsJson: String = "[]",
    val joinedDate: String,
    val updatedAt: Long = System.currentTimeMillis()
)

/** Mobile-wallet numbers configured by a merchant. */
@Entity(
    tableName = "merchant_numbers",
    primaryKeys = ["merchantId", "number", "method"],
    indices = [Index("merchantId")]
)
data class MerchantNumberEntity(
    val number: String,
    val merchantId: String,
    val method: String,
    val accountType: String,
    val isActive: Boolean = true,
    val isDefault: Boolean = false,
    val qrCodeUrl: String? = null,
    val updatedAt: Long = System.currentTimeMillis()
)

/** JSON mirrors keep the no-code form UI available while the network is offline. */
@Entity(tableName = "payment_form_cache", indices = [Index("merchantId")])
data class PaymentFormCacheEntity(
    @PrimaryKey val id: String,
    val merchantId: String,
    val payloadJson: String,
    val updatedAt: Long = System.currentTimeMillis(),
    val isDirty: Boolean = false
)

@Entity(tableName = "form_submission_cache", indices = [Index("merchantId")])
data class FormSubmissionCacheEntity(
    @PrimaryKey val id: String,
    val merchantId: String,
    val formId: String,
    val payloadJson: String,
    val submittedAt: Long = System.currentTimeMillis(),
    val isDirty: Boolean = false
)

@Entity(tableName = "outbox_sms", indices = [Index("merchantId"), Index("status"), Index("createdAt")])
data class OutboxSmsEntity(
    @PrimaryKey val id: String = java.util.UUID.randomUUID().toString(),
    val merchantId: String,
    val recipientPhone: String,
    val messageText: String,
    val smsType: String = "DUE_REMINDER", // "DUE_REMINDER", "MARKETING", "SALES_RECEIPT", "GATEWAY_OTP", "GATEWAY_CUSTOM"
    val simSlot: Int = 0, // 0 = Default, 1 = SIM 1, 2 = SIM 2
    val status: String = "QUEUED", // "QUEUED", "SENDING", "SENT", "DELIVERED", "FAILED"
    val externalJobId: String? = null,
    val customerId: String? = null,
    val errorMessage: String? = null,
    val partsCount: Int = 1,
    val createdAt: Long = System.currentTimeMillis(),
    val sentAt: Long? = null
)

