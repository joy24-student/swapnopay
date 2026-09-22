package com.example.data.local

import androidx.room.*
import kotlinx.coroutines.flow.Flow

@Dao
interface AppDao {

    // SMS Queue
    @Query("SELECT * FROM sms_queue WHERE merchantId = :merchantId ORDER BY timestamp DESC")
    fun observeSmsQueue(merchantId: String): Flow<List<SmsQueueEntity>>

    @Query("SELECT * FROM sms_queue WHERE status = 'PENDING'")
    suspend fun getPendingSms(): List<SmsQueueEntity>

    @Query("SELECT * FROM sms_queue WHERE merchantId = :merchantId AND status = 'PENDING'")
    suspend fun getPendingSms(merchantId: String): List<SmsQueueEntity>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertSms(sms: SmsQueueEntity): Long

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertSmsList(smsList: List<SmsQueueEntity>): List<Long>

    @Update
    suspend fun updateSms(sms: SmsQueueEntity)

    @Query("UPDATE sms_queue SET status = :status WHERE id = :id")
    suspend fun updateSmsStatus(id: Int, status: String)

    @Query("UPDATE sms_queue SET status = :status WHERE trxId = :trxId")
    suspend fun updateSmsStatusByTrxId(trxId: String, status: String)

    @Query("DELETE FROM sms_queue WHERE id = :id")
    suspend fun deleteSmsById(id: Int)

    @Query("DELETE FROM sms_queue")
    suspend fun clearSmsQueue()

    @Query("SELECT EXISTS(SELECT 1 FROM sms_queue WHERE trxId = :trxId)")
    suspend fun hasSmsWithTrxId(trxId: String): Boolean

    @Query("SELECT EXISTS(SELECT 1 FROM cached_payments WHERE id = :trxId)")
    suspend fun hasPaymentWithId(trxId: String): Boolean

    @Transaction
    suspend fun processAndInsertSmsAtomically(
        sms: SmsQueueEntity,
        payment: CachedPaymentEntity
    ): Long? {
        if (hasSmsWithTrxId(sms.trxId) || hasPaymentWithId(sms.trxId)) {
            return null
        }
        val id = insertSms(sms)
        insertPayment(payment)
        return id
    }

    // Orders
    @Query("SELECT * FROM cached_orders WHERE merchantId = :merchantId ORDER BY createdAt DESC")
    fun observeOrders(merchantId: String): Flow<List<CachedOrderEntity>>

    @Query("SELECT * FROM cached_orders")
    suspend fun getAllOrders(): List<CachedOrderEntity>

    @Query("SELECT * FROM cached_orders WHERE merchantId = :merchantId")
    suspend fun getAllOrders(merchantId: String): List<CachedOrderEntity>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertOrders(orders: List<CachedOrderEntity>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertOrder(order: CachedOrderEntity)

    @Query("DELETE FROM cached_orders WHERE id = :id")
    suspend fun deleteOrderById(id: String)

    // Payments
    @Query("SELECT * FROM cached_payments WHERE merchantId = :merchantId OR merchantId = '00000000-0000-0000-0000-000000000001' OR merchantId = '' OR :merchantId = '' OR :merchantId = '00000000-0000-0000-0000-000000000001' ORDER BY timestamp DESC")
    fun observePayments(merchantId: String): Flow<List<CachedPaymentEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertPayments(payments: List<CachedPaymentEntity>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertPayment(payment: CachedPaymentEntity)

    // Appeals
    @Query("SELECT * FROM appeals WHERE merchantId = :merchantId ORDER BY timestamp DESC")
    fun observeAppeals(merchantId: String): Flow<List<AppealEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAppeals(appeals: List<AppealEntity>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAppeal(appeal: AppealEntity)

    @Query("UPDATE appeals SET status = :status WHERE id = :id")
    suspend fun updateAppealStatus(id: String, status: String)

    // Merchant Profile
    @Query("SELECT * FROM merchant_profile LIMIT 1")
    fun observeMerchantProfile(): Flow<MerchantProfileEntity?>

    @Query("SELECT * FROM merchant_profile LIMIT 1")
    suspend fun getMerchantProfile(): MerchantProfileEntity?

    @Query("SELECT * FROM merchant_profile WHERE id = :id LIMIT 1")
    suspend fun getMerchantProfileById(id: String): MerchantProfileEntity?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertMerchantProfile(profile: MerchantProfileEntity)

    @Query("UPDATE customers SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignCustomers(oldId: String, newId: String)
    @Query("UPDATE suppliers SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignSuppliers(oldId: String, newId: String)
    @Query("UPDATE ledger_transactions SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignLedger(oldId: String, newId: String)
    @Query("UPDATE products SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignProducts(oldId: String, newId: String)
    @Query("UPDATE product_variants SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignVariants(oldId: String, newId: String)
    @Query("UPDATE stock_transactions SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignStock(oldId: String, newId: String)
    @Query("UPDATE expenses SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignExpenses(oldId: String, newId: String)
    @Query("UPDATE loans SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignLoans(oldId: String, newId: String)
    @Query("UPDATE dps_accounts SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignDpsAccounts(oldId: String, newId: String)
    @Query("UPDATE finance_installments SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignFinanceInstallments(oldId: String, newId: String)
    @Query("UPDATE merchant_notifications SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignMerchantNotifications(oldId: String, newId: String)
    @Query("UPDATE pos_sales SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignPosSales(oldId: String, newId: String)
    @Query("UPDATE business_analytics SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignAnalytics(oldId: String, newId: String)
    @Query("UPDATE employees SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignEmployees(oldId: String, newId: String)
    @Query("UPDATE merchant_numbers SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignMerchantNumbers(oldId: String, newId: String)
    @Query("UPDATE payment_form_cache SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignPaymentForms(oldId: String, newId: String)
    @Query("UPDATE form_submission_cache SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignFormSubmissions(oldId: String, newId: String)
    @Query("UPDATE sms_queue SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignSmsQueue(oldId: String, newId: String)
    @Query("UPDATE cached_orders SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignOrders(oldId: String, newId: String)
    @Query("UPDATE cached_payments SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignPayments(oldId: String, newId: String)
    @Query("UPDATE appeals SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignAppeals(oldId: String, newId: String)
    @Query("UPDATE devices SET merchantId = :newId WHERE merchantId = :oldId")
    suspend fun reassignDevices(oldId: String, newId: String)

    @Transaction
    suspend fun reassignMerchantData(oldId: String, newId: String) {
        if (oldId == newId) return
        reassignCustomers(oldId, newId)
        reassignSuppliers(oldId, newId)
        reassignLedger(oldId, newId)
        reassignProducts(oldId, newId)
        reassignVariants(oldId, newId)
        reassignStock(oldId, newId)
        reassignExpenses(oldId, newId)
        reassignLoans(oldId, newId)
        reassignDpsAccounts(oldId, newId)
        reassignFinanceInstallments(oldId, newId)
        reassignMerchantNotifications(oldId, newId)
        reassignPosSales(oldId, newId)
        reassignAnalytics(oldId, newId)
        reassignEmployees(oldId, newId)
        reassignMerchantNumbers(oldId, newId)
        reassignPaymentForms(oldId, newId)
        reassignFormSubmissions(oldId, newId)
        reassignSmsQueue(oldId, newId)
        reassignOrders(oldId, newId)
        reassignPayments(oldId, newId)
        reassignAppeals(oldId, newId)
        reassignDevices(oldId, newId)
    }

    // Devices
    @Query("SELECT * FROM devices WHERE merchantId = :merchantId ORDER BY lastSyncTime DESC")
    fun observeDevices(merchantId: String): Flow<List<DeviceInfoEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertDevices(devices: List<DeviceInfoEntity>)

    @Query("DELETE FROM devices")
    suspend fun clearDevices()

    @Query("DELETE FROM devices WHERE merchantId = :merchantId")
    suspend fun clearDevices(merchantId: String)

    // Supabase Profiles
    @Query("SELECT * FROM supabase_profiles ORDER BY businessName ASC")
    fun observeSupabaseProfiles(): Flow<List<SupabaseProfileEntity>>

    @Query("SELECT * FROM supabase_profiles WHERE id = :id")
    suspend fun getSupabaseProfileById(id: String): SupabaseProfileEntity?

    @Query("SELECT * FROM supabase_profiles WHERE isActive = 1 LIMIT 1")
    suspend fun getActiveSupabaseProfile(): SupabaseProfileEntity?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertSupabaseProfile(profile: SupabaseProfileEntity)

    @Query("UPDATE supabase_profiles SET isActive = 0")
    suspend fun deactivateAllProfiles()

    @Query("UPDATE supabase_profiles SET isActive = 1 WHERE id = :id")
    suspend fun activateProfile(id: String)

    @Query("DELETE FROM supabase_profiles WHERE id = :id")
    suspend fun deleteSupabaseProfile(id: String)

    // Dynamic MFS Regex Patterns
    @Query("SELECT * FROM mfs_patterns WHERE active = 1")
    fun observeMfsPatterns(): Flow<List<MfsPatternEntity>>

    @Query("SELECT * FROM mfs_patterns WHERE active = 1")
    suspend fun getAllMfsPatterns(): List<MfsPatternEntity>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertMfsPatterns(patterns: List<MfsPatternEntity>)

    @Query("DELETE FROM mfs_patterns")
    suspend fun clearMfsPatterns()

    // Customers
    @Query("SELECT * FROM customers WHERE merchantId = :merchantId ORDER BY name ASC")
    fun observeCustomers(merchantId: String): Flow<List<CustomerEntity>>

    @Query("SELECT * FROM customers WHERE id = :id AND merchantId = :merchantId")
    suspend fun getCustomerById(id: String, merchantId: String): CustomerEntity?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertCustomer(customer: CustomerEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertCustomers(customers: List<CustomerEntity>)

    @Query("UPDATE customers SET currentBalance = currentBalance + :delta WHERE id = :id AND merchantId = :merchantId")
    suspend fun incrementCustomerBalance(id: String, merchantId: String, delta: Double): Int

    @Query("DELETE FROM customers WHERE id = :id")
    suspend fun deleteCustomerById(id: String)

    // Suppliers
    @Query("SELECT * FROM suppliers WHERE merchantId = :merchantId ORDER BY name ASC")
    fun observeSuppliers(merchantId: String): Flow<List<SupplierEntity>>

    @Query("SELECT * FROM suppliers WHERE id = :id AND merchantId = :merchantId")
    suspend fun getSupplierById(id: String, merchantId: String): SupplierEntity?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertSupplier(supplier: SupplierEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertSuppliers(suppliers: List<SupplierEntity>)

    @Query("UPDATE suppliers SET currentBalance = currentBalance + :delta WHERE id = :id AND merchantId = :merchantId")
    suspend fun incrementSupplierBalance(id: String, merchantId: String, delta: Double): Int

    @Query("DELETE FROM suppliers WHERE id = :id")
    suspend fun deleteSupplierById(id: String)

    // Ledger Transactions
    @Query("SELECT * FROM ledger_transactions WHERE merchantId = :merchantId ORDER BY date DESC")
    fun observeLedgerTransactions(merchantId: String): Flow<List<LedgerTransactionEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertLedgerTransaction(transaction: LedgerTransactionEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertLedgerTransactions(transactions: List<LedgerTransactionEntity>)

    @Transaction
    suspend fun recordLedgerTransactionAtomic(transaction: LedgerTransactionEntity) {
        require(transaction.amount > 0.0 && transaction.amount.isFinite()) { "Ledger amount must be positive" }
        require(transaction.type == "credit" || transaction.type == "payment") { "Unsupported ledger type" }
        require(!(transaction.customerId != null && transaction.supplierId != null)) { "A ledger row cannot target both parties" }
        insertLedgerTransaction(transaction)
        val delta = if (transaction.type == "credit") transaction.amount else -transaction.amount
        transaction.customerId?.let { customerId ->
            require(incrementCustomerBalance(customerId, transaction.merchantId, delta) == 1) {
                "Customer does not belong to the active merchant"
            }
        }
        transaction.supplierId?.let { supplierId ->
            val supplierDelta = if (transaction.type == "credit") -transaction.amount else transaction.amount
            require(incrementSupplierBalance(supplierId, transaction.merchantId, supplierDelta) == 1) {
                "Supplier does not belong to the active merchant"
            }
        }
    }

    @Query("DELETE FROM ledger_transactions WHERE id = :id")
    suspend fun deleteLedgerTransactionById(id: String)

    // Products / Stock Inventory
    @Query("SELECT * FROM products WHERE merchantId = :merchantId ORDER BY name ASC")
    fun observeProducts(merchantId: String): Flow<List<ProductItemEntity>>

    @Query("SELECT * FROM products WHERE id = :id AND merchantId = :merchantId")
    suspend fun getProductById(id: String, merchantId: String): ProductItemEntity?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertProduct(product: ProductItemEntity)

    @Query("UPDATE products SET imageUrl=:image, storefrontDetailsJson=:details WHERE id=:id AND merchantId=:merchantId AND storefrontDetailsJson=:previous")
    suspend fun updateProductMedia(id: String, merchantId: String, image: String?, details: String, previous: String): Int

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertProducts(products: List<ProductItemEntity>)

    @Query("UPDATE products SET stockQuantity = stockQuantity + :delta WHERE id = :id AND merchantId = :merchantId AND stockQuantity + :delta >= 0")
    suspend fun incrementProductStock(id: String, merchantId: String, delta: Double): Int

    @Query("DELETE FROM products WHERE id = :id AND merchantId = :merchantId")
    suspend fun deleteProductById(id: String, merchantId: String): Int

    @Query("DELETE FROM stock_transactions WHERE productId = :id AND merchantId = :merchantId")
    suspend fun deleteStockTransactionsByProductId(id: String, merchantId: String): Int

    @Query("DELETE FROM product_variants WHERE productId = :id AND merchantId = :merchantId")
    suspend fun deleteProductVariantsByProductId(id: String, merchantId: String): Int

    @Transaction
    suspend fun deleteProductCascade(id: String, merchantId: String): Boolean {
        deleteStockTransactionsByProductId(id, merchantId)
        deleteProductVariantsByProductId(id, merchantId)
        return deleteProductById(id, merchantId) > 0
    }

    @Query("SELECT COUNT(*) FROM stock_transactions WHERE productId = :id AND merchantId = :merchantId")
    suspend fun countProductStockTransactions(id: String, merchantId: String): Int

    @Query("SELECT COUNT(*) FROM product_variants WHERE productId = :id AND merchantId = :merchantId")
    suspend fun countProductVariants(id: String, merchantId: String): Int

    @Transaction
    suspend fun canDeletePristineProduct(id: String, merchantId: String): Boolean {
        val product = getProductById(id, merchantId) ?: return false
        return product.stockQuantity == 0.0 &&
            countProductStockTransactions(id, merchantId) == 0 &&
            countProductVariants(id, merchantId) == 0
    }

    @Transaction
    suspend fun deletePristineProduct(id: String, merchantId: String): Boolean {
        val product = getProductById(id, merchantId) ?: return false
        require(product.stockQuantity == 0.0) { "Products with stock cannot be deleted" }
        require(countProductStockTransactions(id, merchantId) == 0) { "Products with stock history must be retained for audit" }
        require(countProductVariants(id, merchantId) == 0) { "Remove product variants before deleting the product" }
        return deleteProductById(id, merchantId) == 1
    }

    @Transaction
    suspend fun updateProductWithStockAdjustmentAtomic(
        product: ProductItemEntity,
        stockAdjustment: StockTransactionEntity?
    ) {
        val current = getProductById(product.id, product.merchantId)
            ?: throw IllegalArgumentException("Product does not belong to the active merchant")
        require(product.name.isNotBlank() && product.unit.isNotBlank())
        require(product.purchasePrice.isFinite() && product.purchasePrice >= 0.0)
        require(product.salePrice.isFinite() && product.salePrice >= 0.0)
        require(product.stockQuantity.isFinite() && product.stockQuantity >= 0.0)
        val delta = product.stockQuantity - current.stockQuantity
        insertProduct(product.copy(stockQuantity = current.stockQuantity))
        if (delta != 0.0) {
            require(countProductVariants(product.id, product.merchantId) == 0) {
                "Stock for variant products must be adjusted through its variants"
            }
            val adjustment = requireNotNull(stockAdjustment) { "A stock movement is required when quantity changes" }
            require(adjustment.merchantId == product.merchantId && adjustment.productId == product.id)
            require(adjustment.variantId == null && adjustment.quantity == kotlin.math.abs(delta))
            require(adjustment.type == if (delta > 0.0) "in" else "out")
            recordStockTransactionsAtomic(listOf(adjustment))
        } else {
            require(stockAdjustment == null) { "Unexpected stock movement" }
        }
    }

    // Product Variants & QR Codes
    @Query("SELECT * FROM product_variants WHERE merchantId = :merchantId ORDER BY variantName ASC")
    fun observeProductVariants(merchantId: String): Flow<List<ProductVariantEntity>>

    @Query("SELECT * FROM product_variants WHERE productId = :productId AND merchantId = :merchantId")
    suspend fun getVariantsByProductId(productId: String, merchantId: String): List<ProductVariantEntity>

    @Query("SELECT * FROM product_variants WHERE qrCode = :qrCode AND merchantId = :merchantId LIMIT 1")
    suspend fun getVariantByQrCode(qrCode: String, merchantId: String): ProductVariantEntity?

    @Query("SELECT * FROM products WHERE merchantId = :merchantId AND (qrCode = :code OR code = :code) LIMIT 1")
    suspend fun getProductByCode(code: String, merchantId: String): ProductItemEntity?

    @Query("SELECT * FROM product_variants WHERE id = :id AND merchantId = :merchantId LIMIT 1")
    suspend fun getVariantById(id: String, merchantId: String): ProductVariantEntity?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertProductVariant(variant: ProductVariantEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertProductVariants(variants: List<ProductVariantEntity>)

    @Query("UPDATE product_variants SET stockQuantity = stockQuantity + :delta WHERE id = :id AND merchantId = :merchantId AND stockQuantity + :delta >= 0")
    suspend fun incrementVariantStock(id: String, merchantId: String, delta: Double): Int

    @Query("DELETE FROM product_variants WHERE id = :id")
    suspend fun deleteProductVariantById(id: String)

    // Stock Transactions
    @Query("SELECT * FROM stock_transactions WHERE merchantId = :merchantId ORDER BY createdAt DESC")
    fun observeStockTransactions(merchantId: String): Flow<List<StockTransactionEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertStockTransaction(transaction: StockTransactionEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertStockTransactions(transactions: List<StockTransactionEntity>)

    @Transaction
    suspend fun recordStockTransactionsAtomic(transactions: List<StockTransactionEntity>) {
        transactions.forEach { transaction ->
            require(transaction.quantity > 0.0 && transaction.quantity.isFinite()) { "Stock quantity must be positive" }
            require(transaction.type == "in" || transaction.type == "out") { "Unsupported stock movement" }
            val delta = if (transaction.type == "in") transaction.quantity else -transaction.quantity
            transaction.variantId?.let { variantId ->
                val variant = requireNotNull(getVariantById(variantId, transaction.merchantId)) {
                    "Variant does not belong to the active merchant"
                }
                require(variant.productId == transaction.productId) { "Variant does not belong to product" }
                require(incrementVariantStock(variantId, transaction.merchantId, delta) == 1) {
                    "Insufficient variant stock"
                }
            }
            require(incrementProductStock(transaction.productId, transaction.merchantId, delta) == 1) {
                "Product is missing or has insufficient stock"
            }
            insertStockTransaction(transaction)
        }
    }

    @Transaction
    suspend fun createProductWithVariantsAtomic(
        product: ProductItemEntity,
        variants: List<ProductVariantEntity>,
        transactions: List<StockTransactionEntity>
    ) {
        require(variants.isNotEmpty()) { "At least one product variant is required" }
        require(variants.all { it.merchantId == product.merchantId && it.productId == product.id }) {
            "Variant tenant or product mismatch"
        }
        require(variants.map { it.qrCode.trim().lowercase() }.distinct().size == variants.size) {
            "QR codes must be unique"
        }
        require(transactions.size == variants.size && transactions.all {
            it.merchantId == product.merchantId && it.productId == product.id && it.type == "in"
        }) { "Opening stock movements do not match variants" }
        insertProduct(product)
        insertProductVariants(variants)
        recordStockTransactionsAtomic(transactions)
    }

    @Transaction
    suspend fun createProductWithOpeningStockAtomic(
        product: ProductItemEntity,
        openingMovement: StockTransactionEntity?
    ) {
        require(product.stockQuantity == 0.0) { "Product stock must be derived from stock movements" }
        insertProduct(product)
        openingMovement?.let {
            require(it.merchantId == product.merchantId && it.productId == product.id && it.type == "in")
            recordStockTransactionsAtomic(listOf(it))
        }
    }

    // Expenses
    @Query("SELECT * FROM expenses WHERE merchantId = :merchantId ORDER BY date DESC")
    fun observeExpenses(merchantId: String): Flow<List<ExpenseEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertExpense(expense: ExpenseEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertExpenses(expenses: List<ExpenseEntity>)

    @Query("DELETE FROM expenses WHERE id = :id")
    suspend fun deleteExpenseById(id: String)

    // Business Loans
    @Query("SELECT * FROM loans WHERE merchantId = :merchantId ORDER BY appliedAt DESC")
    fun observeLoans(merchantId: String): Flow<List<BusinessLoanEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertLoan(loan: BusinessLoanEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertLoans(loans: List<BusinessLoanEntity>)

    @Query("UPDATE loans SET status = :status, disbursedAt = :disbursedAt WHERE id = :id")
    suspend fun updateLoanStatus(id: String, status: String, disbursedAt: Long?)

    // DPS and materialized EMI schedules
    @Query("SELECT * FROM dps_accounts WHERE merchantId = :merchantId ORDER BY createdAt DESC")
    fun observeDpsAccounts(merchantId: String): Flow<List<DpsAccountEntity>>

    @Insert(onConflict = OnConflictStrategy.ABORT)
    suspend fun insertDpsAccount(account: DpsAccountEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun restoreDpsAccounts(accounts: List<DpsAccountEntity>)

    @Query("SELECT * FROM finance_installments WHERE merchantId = :merchantId ORDER BY dueDate ASC")
    fun observeFinanceInstallments(merchantId: String): Flow<List<FinanceInstallmentEntity>>

    @Insert(onConflict = OnConflictStrategy.ABORT)
    suspend fun insertFinanceInstallments(installments: List<FinanceInstallmentEntity>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun restoreFinanceInstallments(installments: List<FinanceInstallmentEntity>)

    @Transaction
    suspend fun createDpsAccountAtomic(account: DpsAccountEntity, installments: List<FinanceInstallmentEntity>) {
        require(account.monthlyDeposit > 0.0 && account.monthlyDeposit.isFinite())
        require(account.durationMonths in 1..600)
        require(installments.size == account.durationMonths)
        require(installments.all { it.merchantId == account.merchantId && it.accountType == "DPS" && it.accountId == account.id })
        insertDpsAccount(account)
        insertFinanceInstallments(installments)
    }

    @Transaction
    suspend fun createLoanWithScheduleAtomic(loan: BusinessLoanEntity, installments: List<FinanceInstallmentEntity>) {
        require(loan.principalAmount > 0.0 && loan.principalAmount.isFinite())
        require(installments.size == loan.durationMonths)
        require(installments.all { it.merchantId == loan.merchantId && it.accountType == "LOAN" && it.accountId == loan.id })
        insertLoan(loan)
        insertFinanceInstallments(installments)
    }

    @Query("UPDATE finance_installments SET status = 'PAID', paidAt = :paidAt, paymentMethod = :paymentMethod, paymentReference = :paymentReference, isSynced = 0 WHERE id = :installmentId AND merchantId = :merchantId AND status IN ('PENDING', 'OVERDUE')")
    suspend fun markFinanceInstallmentPaid(
        installmentId: String,
        merchantId: String,
        paidAt: Long,
        paymentMethod: String,
        paymentReference: String
    ): Int

    @Query("UPDATE dps_accounts SET isSynced = 1, updatedAt = :updatedAt WHERE id = :id AND merchantId = :merchantId")
    suspend fun markDpsAccountSynced(id: String, merchantId: String, updatedAt: Long = System.currentTimeMillis())

    @Query("UPDATE loans SET isSynced = 1 WHERE id = :id AND merchantId = :merchantId")
    suspend fun markLoanSynced(id: String, merchantId: String)

    @Query("UPDATE finance_installments SET isSynced = 1 WHERE id = :id AND merchantId = :merchantId")
    suspend fun markFinanceInstallmentSynced(id: String, merchantId: String)

    // POS Sales Invoices
    @Query("SELECT * FROM pos_sales WHERE merchantId = :merchantId ORDER BY timestamp DESC")
    fun observePosSales(merchantId: String): Flow<List<PosSaleEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertPosSale(sale: PosSaleEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertPosSales(sales: List<PosSaleEntity>)

    @Transaction
    suspend fun checkoutPosSaleAtomic(
        sale: PosSaleEntity,
        stockMovements: List<StockTransactionEntity>,
        creditLedgerEntry: LedgerTransactionEntity?
    ) {
        require(stockMovements.isNotEmpty()) { "Checkout has no stock movements" }
        require(stockMovements.all { it.merchantId == sale.merchantId && it.type == "out" }) {
            "Checkout stock movement mismatch"
        }
        recordStockTransactionsAtomic(stockMovements)
        creditLedgerEntry?.let {
            require(it.merchantId == sale.merchantId && it.customerId == sale.customerId)
            recordLedgerTransactionAtomic(it)
        }
        insertPosSale(sale)
    }

    // Durable merchant notifications
    @Query("SELECT * FROM merchant_notifications WHERE merchantId = :merchantId ORDER BY createdAt DESC")
    fun observeMerchantNotifications(merchantId: String): Flow<List<MerchantNotificationEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertMerchantNotifications(notifications: List<MerchantNotificationEntity>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertMerchantNotification(notification: MerchantNotificationEntity)

    @Query("UPDATE merchant_notifications SET readAt = :readAt WHERE id = :id AND merchantId = :merchantId")
    suspend fun markMerchantNotificationRead(id: String, merchantId: String, readAt: Long): Int

    @Query("UPDATE merchant_notifications SET readAt = :readAt WHERE merchantId = :merchantId AND readAt IS NULL")
    suspend fun markAllMerchantNotificationsRead(merchantId: String, readAt: Long): Int

    @Query("DELETE FROM pos_sales WHERE id = :id")
    suspend fun deletePosSaleById(id: String)

    // Business Analytics
    @Query("SELECT * FROM business_analytics WHERE merchantId = :merchantId LIMIT 1")
    fun observeBusinessAnalytics(merchantId: String): Flow<BusinessAnalyticsEntity?>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertBusinessAnalytics(analytics: BusinessAnalyticsEntity)

    // Employees
    @Query("SELECT * FROM employees WHERE merchantId = :merchantId ORDER BY name ASC")
    fun observeEmployees(merchantId: String): Flow<List<EmployeeEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertEmployee(employee: EmployeeEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertEmployees(employees: List<EmployeeEntity>)

    @Query("DELETE FROM employees WHERE id = :id")
    suspend fun deleteEmployee(id: String)

    // Merchant payment numbers
    @Query("SELECT * FROM merchant_numbers WHERE merchantId = :merchantId ORDER BY isDefault DESC, method ASC")
    fun observeMerchantNumbers(merchantId: String): Flow<List<MerchantNumberEntity>>

    @Query("SELECT * FROM merchant_numbers WHERE merchantId = :merchantId ORDER BY isDefault DESC, method ASC")
    suspend fun getMerchantNumbers(merchantId: String): List<MerchantNumberEntity>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertMerchantNumber(number: MerchantNumberEntity)

    @Query("UPDATE merchant_numbers SET isDefault = 0 WHERE merchantId = :merchantId")
    suspend fun clearDefaultMerchantNumber(merchantId: String)

    @Transaction
    suspend fun setDefaultMerchantNumber(merchantId: String, number: MerchantNumberEntity) {
        clearDefaultMerchantNumber(merchantId)
        upsertMerchantNumber(number.copy(isDefault = true, updatedAt = System.currentTimeMillis()))
    }

    @Query("DELETE FROM merchant_numbers WHERE merchantId = :merchantId AND number = :number")
    suspend fun deleteMerchantNumber(merchantId: String, number: String)

    // Offline form and response mirrors
    @Query("SELECT * FROM payment_form_cache WHERE merchantId = :merchantId ORDER BY updatedAt DESC")
    fun observePaymentFormCache(merchantId: String): Flow<List<PaymentFormCacheEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertPaymentFormCache(form: PaymentFormCacheEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertPaymentFormCaches(forms: List<PaymentFormCacheEntity>)

    @Query("DELETE FROM payment_form_cache WHERE id = :id")
    suspend fun deletePaymentFormCache(id: String)

    @Query("SELECT * FROM form_submission_cache WHERE merchantId = :merchantId ORDER BY submittedAt DESC")
    fun observeFormSubmissionCache(merchantId: String): Flow<List<FormSubmissionCacheEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertFormSubmissionCache(submission: FormSubmissionCacheEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertFormSubmissionCaches(submissions: List<FormSubmissionCacheEntity>)

    // Outbox SMS (Campaigns, Due Reminders, Gateway OTP & Custom SMS)
    @Query("SELECT * FROM outbox_sms WHERE merchantId = :merchantId ORDER BY createdAt DESC")
    fun observeOutboxSms(merchantId: String): Flow<List<OutboxSmsEntity>>

    @Query("SELECT * FROM outbox_sms WHERE merchantId = :merchantId AND status = 'QUEUED' ORDER BY createdAt ASC LIMIT :limit")
    suspend fun getPendingOutboxSms(merchantId: String, limit: Int = 50): List<OutboxSmsEntity>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertOutboxSms(sms: OutboxSmsEntity): Long

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertOutboxSmsList(smsList: List<OutboxSmsEntity>): List<Long>

    @Query("UPDATE outbox_sms SET status = :status, sentAt = :sentAt, errorMessage = :errorMessage WHERE id = :id")
    suspend fun updateOutboxSmsStatus(id: String, status: String, sentAt: Long? = null, errorMessage: String? = null): Int

    @Query("DELETE FROM outbox_sms WHERE id = :id")
    suspend fun deleteOutboxSms(id: String): Int

    @Query("DELETE FROM outbox_sms WHERE merchantId = :merchantId")
    suspend fun clearOutboxSms(merchantId: String): Int

    @Query("SELECT * FROM customers WHERE merchantId = :merchantId AND currentBalance > 0 ORDER BY currentBalance DESC")
    fun observeCustomersWithDue(merchantId: String): Flow<List<CustomerEntity>>

    @Query("SELECT * FROM customers WHERE merchantId = :merchantId AND currentBalance > 0 ORDER BY currentBalance DESC")
    suspend fun getCustomersWithDue(merchantId: String): List<CustomerEntity>

    @Query("SELECT * FROM customers WHERE merchantId = :merchantId ORDER BY name ASC")
    suspend fun getAllCustomersList(merchantId: String): List<CustomerEntity>
}
