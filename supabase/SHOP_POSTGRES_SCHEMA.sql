-- ============================================================================
-- SWAPNOPAY E-COMMERCE MASTER PRODUCTION POSTGRESQL SCHEMA
-- Converted from MySQL (ecommerceweb.sql) to Enterprise PostgreSQL
-- Fully integrated with SwapnoPay Merchants Database ('merchants' table)
-- Multi-Tenant UUID Isolation + Full Primary Keys + Foreign Keys + RLS
-- ============================================================================

CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Helper Trigger Function for updated_at
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE 'plpgsql';

-- Helper: Current Merchant Resolution Function (if not already defined)
CREATE OR REPLACE FUNCTION current_merchant_id()
RETURNS UUID LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
  SELECT id FROM merchants WHERE user_id = auth.uid() LIMIT 1;
$$;

-- Compatibility: MySQL rand() alias for PostgreSQL random()
CREATE OR REPLACE FUNCTION rand()
RETURNS double precision LANGUAGE plpgsql AS $$
BEGIN
  RETURN random();
END;
$$;


-- --------------------------------------------------------
-- Table structure for "tbl_advertisements"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_advertisements" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "ad_id" SERIAL PRIMARY KEY,
  "advertiser_user_id" INTEGER NOT NULL,
  "title" VARCHAR(255) NOT NULL,
  "description" TEXT,
  "ad_type" TEXT CHECK ("ad_type" IN ('banner','popup','featured_listing')) NOT NULL DEFAULT 'banner',
  "target_url" VARCHAR(255) DEFAULT NULL,
  "image_file" VARCHAR(255) DEFAULT NULL,
  "start_date" TIMESTAMPTZ NOT NULL,
  "end_date" TIMESTAMPTZ NOT NULL,
  "price_paid" NUMERIC(10, 2) DEFAULT NULL,
  "status" TEXT CHECK ("status" IN ('active','inactive','pending_approval','rejected','expired')) NOT NULL DEFAULT 'pending_approval',
  "created_at" TIMESTAMPTZ DEFAULT NOW(),
  "updated_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_advertisements_merchant" ON "tbl_advertisements"(merchant_id);
ALTER TABLE "tbl_advertisements" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_advertisements" ON "tbl_advertisements";
CREATE POLICY "merchant_manage_tbl_advertisements" ON "tbl_advertisements"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_auctions"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_auctions" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "auction_id" SERIAL PRIMARY KEY,
  "seller_user_id" INTEGER NOT NULL,
  "product_name" VARCHAR(255) NOT NULL,
  "description" TEXT,
  "starting_bid" NUMERIC(10, 2) NOT NULL,
  "current_bid" NUMERIC(10, 2) DEFAULT NULL,
  "highest_bidder_user_id" INTEGER DEFAULT NULL,
  "main_photo" VARCHAR(255) DEFAULT NULL,
  "other_photos" TEXT,
  "start_time" TIMESTAMPTZ NOT NULL,
  "end_time" TIMESTAMPTZ NOT NULL,
  "status" TEXT CHECK ("status" IN ('active','completed','cancelled','pending_approval','rejected')) NOT NULL DEFAULT 'pending_approval',
  "created_at" TIMESTAMPTZ DEFAULT NOW(),
  "updated_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_auctions_merchant" ON "tbl_auctions"(merchant_id);
ALTER TABLE "tbl_auctions" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_auctions" ON "tbl_auctions";
CREATE POLICY "merchant_manage_tbl_auctions" ON "tbl_auctions"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_auction_bids"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_auction_bids" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "bid_id" SERIAL PRIMARY KEY,
  "auction_id" INTEGER NOT NULL,
  "bidder_user_id" INTEGER NOT NULL,
  "bid_amount" NUMERIC(10, 2) NOT NULL,
  "bid_time" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_auction_bids_merchant" ON "tbl_auction_bids"(merchant_id);
ALTER TABLE "tbl_auction_bids" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_auction_bids" ON "tbl_auction_bids";
CREATE POLICY "merchant_manage_tbl_auction_bids" ON "tbl_auction_bids"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_businesses"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_businesses" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "business_id" SERIAL PRIMARY KEY,
  "owner_user_id" INTEGER NOT NULL,
  "business_name" VARCHAR(255) NOT NULL,
  "description" TEXT,
  "address" VARCHAR(255) NOT NULL,
  "city" VARCHAR(100) NOT NULL,
  "nid_number" VARCHAR(50) DEFAULT NULL,
  "nid_front_doc" VARCHAR(255) NOT NULL,
  "nid_back_doc" VARCHAR(255) NOT NULL,
  "nid_document" VARCHAR(255) DEFAULT NULL,
  "face_capture" VARCHAR(255) DEFAULT NULL,
  "state" VARCHAR(100) DEFAULT NULL,
  "zip" VARCHAR(20) DEFAULT NULL,
  "phone" VARCHAR(50) NOT NULL,
  "email" VARCHAR(255) DEFAULT NULL,
  "trade_license_number" VARCHAR(100) DEFAULT NULL,
  "trade_license_photo" VARCHAR(255) DEFAULT NULL,
  "tin_certificate_photo" VARCHAR(255) DEFAULT NULL,
  "other_organization_docs" TEXT,
  "website" VARCHAR(255) DEFAULT NULL,
  "latitude" NUMERIC(10, 8) DEFAULT NULL,
  "longitude" NUMERIC(11, 8) DEFAULT NULL,
  "logo" VARCHAR(255) DEFAULT NULL,
  "banner_photo" VARCHAR(255) DEFAULT NULL,
  "verification_status" TEXT CHECK ("verification_status" IN ('pending','approved','rejected')) NOT NULL DEFAULT 'pending',
  "is_active" SMALLINT NOT NULL DEFAULT 1,
  "created_at" TIMESTAMPTZ DEFAULT NOW(),
  "updated_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_businesses_merchant" ON "tbl_businesses"(merchant_id);
CREATE UNIQUE INDEX IF NOT EXISTS "uq_tbl_businesses_0" ON "tbl_businesses"("email");
ALTER TABLE "tbl_businesses" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_businesses" ON "tbl_businesses";
CREATE POLICY "merchant_manage_tbl_businesses" ON "tbl_businesses"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_coin_transactions"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_coin_transactions" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "transaction_id" SERIAL PRIMARY KEY,
  "user_id" INTEGER NOT NULL,
  "transaction_type" TEXT CHECK ("transaction_type" IN ('credit','debit')) NOT NULL,
  "amount" NUMERIC(15, 2) NOT NULL,
  "description" TEXT,
  "source_id" INTEGER DEFAULT NULL,
  "transaction_date" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_coin_transactions_merchant" ON "tbl_coin_transactions"(merchant_id);
ALTER TABLE "tbl_coin_transactions" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_coin_transactions" ON "tbl_coin_transactions";
CREATE POLICY "merchant_manage_tbl_coin_transactions" ON "tbl_coin_transactions"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_color"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_color" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "color_id" SERIAL PRIMARY KEY,
  "color_name" VARCHAR(255) NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_color_merchant" ON "tbl_color"(merchant_id);
ALTER TABLE "tbl_color" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_color" ON "tbl_color";
CREATE POLICY "merchant_manage_tbl_color" ON "tbl_color"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);
DROP POLICY IF EXISTS "public_read_tbl_color" ON "tbl_color";
CREATE POLICY "public_read_tbl_color" ON "tbl_color"
  FOR SELECT USING (true);


-- --------------------------------------------------------
-- Table structure for "tbl_country"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_country" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "country_id" SERIAL PRIMARY KEY,
  "country_name" VARCHAR(100) NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_country_merchant" ON "tbl_country"(merchant_id);
ALTER TABLE "tbl_country" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_country" ON "tbl_country";
CREATE POLICY "merchant_manage_tbl_country" ON "tbl_country"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);
DROP POLICY IF EXISTS "public_read_tbl_country" ON "tbl_country";
CREATE POLICY "public_read_tbl_country" ON "tbl_country"
  FOR SELECT USING (true);


-- --------------------------------------------------------
-- Table structure for "tbl_coupon"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_coupon" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "coupon_id" SERIAL PRIMARY KEY,
  "coupon_code" VARCHAR(50) NOT NULL,
  "discount_type" TEXT CHECK ("discount_type" IN ('percentage','fixed')) NOT NULL DEFAULT 'percentage',
  "discount_value" NUMERIC(10, 2) NOT NULL,
  "minimum_order" NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
  "usage_limit" INTEGER NOT NULL DEFAULT 0,
  "used_count" INTEGER NOT NULL DEFAULT 0,
  "start_date" DATE NOT NULL,
  "end_date" DATE NOT NULL,
  "status" TEXT CHECK ("status" IN ('active','inactive')) NOT NULL DEFAULT 'active'
);

CREATE INDEX IF NOT EXISTS "idx_tbl_coupon_merchant" ON "tbl_coupon"(merchant_id);
CREATE UNIQUE INDEX IF NOT EXISTS "uq_tbl_coupon_0" ON "tbl_coupon"("coupon_code");
ALTER TABLE "tbl_coupon" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_coupon" ON "tbl_coupon";
CREATE POLICY "merchant_manage_tbl_coupon" ON "tbl_coupon"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_customer"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_customer" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "cust_id" SERIAL PRIMARY KEY,
  "cust_name" VARCHAR(100) NOT NULL,
  "cust_cname" VARCHAR(100) NOT NULL,
  "cust_email" VARCHAR(100) NOT NULL,
  "cust_phone" VARCHAR(50) NOT NULL,
  "cust_country" INTEGER NOT NULL,
  "cust_address" TEXT NOT NULL,
  "cust_city" VARCHAR(100) NOT NULL,
  "cust_state" VARCHAR(100) NOT NULL,
  "cust_zip" VARCHAR(30) NOT NULL,
  "cust_b_name" VARCHAR(100) NOT NULL,
  "cust_b_cname" VARCHAR(100) NOT NULL,
  "cust_b_phone" VARCHAR(50) NOT NULL,
  "cust_b_country" INTEGER NOT NULL,
  "cust_b_address" TEXT NOT NULL,
  "cust_b_city" VARCHAR(100) NOT NULL,
  "cust_b_state" VARCHAR(100) NOT NULL,
  "cust_b_zip" VARCHAR(30) NOT NULL,
  "cust_s_name" VARCHAR(100) NOT NULL,
  "cust_s_cname" VARCHAR(100) NOT NULL,
  "cust_s_phone" VARCHAR(50) NOT NULL,
  "cust_s_country" INTEGER NOT NULL,
  "cust_s_address" TEXT NOT NULL,
  "cust_s_city" VARCHAR(100) NOT NULL,
  "cust_s_state" VARCHAR(100) NOT NULL,
  "cust_s_zip" VARCHAR(30) NOT NULL,
  "cust_password" VARCHAR(255) NOT NULL,
  "cust_coin_balance" NUMERIC(15, 2) NOT NULL DEFAULT 0.00,
  "cust_token" VARCHAR(255) NOT NULL,
  "cust_datetime" VARCHAR(100) NOT NULL,
  "cust_timestamp" VARCHAR(100) NOT NULL,
  "cust_status" SMALLINT NOT NULL,
  "role_type" VARCHAR(50) DEFAULT 'customer',
  "is_verified" SMALLINT DEFAULT 0,
  "trust_score" INTEGER DEFAULT 50,
  "current_lat" NUMERIC(10, 8) DEFAULT NULL,
  "current_lng" NUMERIC(11, 8) DEFAULT NULL,
  "wallet_balance" NUMERIC(15, 2) DEFAULT 0.00,
  "identity_doc_path" VARCHAR(255) DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_customer_merchant" ON "tbl_customer"(merchant_id);
ALTER TABLE "tbl_customer" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_customer" ON "tbl_customer";
CREATE POLICY "merchant_manage_tbl_customer" ON "tbl_customer"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_customer_carts"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_customer_carts" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "cart_id" SERIAL PRIMARY KEY,
  "customer_id" INTEGER NOT NULL,
  "product_id" INTEGER NOT NULL,
  "size_id" INTEGER DEFAULT NULL,
  "size_name" VARCHAR(255),
  "color_id" INTEGER DEFAULT NULL,
  "color_name" VARCHAR(255),
  "quantity" INTEGER NOT NULL DEFAULT 1,
  "price_at_add" NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
  "product_name" VARCHAR(255),
  "product_photo" VARCHAR(255) DEFAULT NULL,
  "added_at" TIMESTAMPTZ DEFAULT NOW() NOT NULL,
  "updated_at" TIMESTAMPTZ DEFAULT NOW() NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_customer_carts_merchant" ON "tbl_customer_carts"(merchant_id);
ALTER TABLE "tbl_customer_carts" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_customer_carts" ON "tbl_customer_carts";
CREATE POLICY "merchant_manage_tbl_customer_carts" ON "tbl_customer_carts"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_customer_message"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_customer_message" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "customer_message_id" SERIAL PRIMARY KEY,
  "subject" VARCHAR(255) NOT NULL,
  "message" TEXT NOT NULL,
  "order_detail" TEXT NOT NULL,
  "cust_id" INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_customer_message_merchant" ON "tbl_customer_message"(merchant_id);
ALTER TABLE "tbl_customer_message" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_customer_message" ON "tbl_customer_message";
CREATE POLICY "merchant_manage_tbl_customer_message" ON "tbl_customer_message"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_drivers"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_drivers" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "driver_id" SERIAL PRIMARY KEY,
  "user_id" INTEGER DEFAULT NULL,
  "name" VARCHAR(255) NOT NULL,
  "email" VARCHAR(255) DEFAULT NULL,
  "phone" VARCHAR(50) NOT NULL,
  "nid_number" VARCHAR(50) DEFAULT NULL,
  "nid_photo_front" VARCHAR(255) DEFAULT NULL,
  "nid_photo_back" VARCHAR(255) DEFAULT NULL,
  "driving_license_number" VARCHAR(100) DEFAULT NULL,
  "driving_license_photo" VARCHAR(255) DEFAULT NULL,
  "vehicle_registration_photo" VARCHAR(255) DEFAULT NULL,
  "license_number" VARCHAR(100) NOT NULL,
  "vehicle_type" TEXT CHECK ("vehicle_type" IN ('rickshaw','cng','bike','car')) NOT NULL,
  "current_latitude" NUMERIC(10, 8) DEFAULT NULL,
  "current_longitude" NUMERIC(11, 8) DEFAULT NULL,
  "status" TEXT CHECK ("status" IN ('online','offline','on_trip')) NOT NULL DEFAULT 'offline',
  "is_verified" SMALLINT NOT NULL DEFAULT 0,
  "created_at" TIMESTAMPTZ DEFAULT NOW(),
  "updated_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_drivers_merchant" ON "tbl_drivers"(merchant_id);
CREATE UNIQUE INDEX IF NOT EXISTS "uq_tbl_drivers_0" ON "tbl_drivers"("phone");
CREATE UNIQUE INDEX IF NOT EXISTS "uq_tbl_drivers_1" ON "tbl_drivers"("license_number");
CREATE UNIQUE INDEX IF NOT EXISTS "uq_tbl_drivers_2" ON "tbl_drivers"("user_id");
CREATE UNIQUE INDEX IF NOT EXISTS "uq_tbl_drivers_3" ON "tbl_drivers"("email");
ALTER TABLE "tbl_drivers" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_drivers" ON "tbl_drivers";
CREATE POLICY "merchant_manage_tbl_drivers" ON "tbl_drivers"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_emergency_contacts"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_emergency_contacts" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "contact_id" SERIAL PRIMARY KEY,
  "service_type" VARCHAR(100) NOT NULL,
  "contact_number" VARCHAR(50) NOT NULL,
  "region" VARCHAR(255) DEFAULT NULL,
  "description" TEXT,
  "is_active" SMALLINT NOT NULL DEFAULT 1,
  "created_at" TIMESTAMPTZ DEFAULT NOW(),
  "updated_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_emergency_contacts_merchant" ON "tbl_emergency_contacts"(merchant_id);
ALTER TABLE "tbl_emergency_contacts" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_emergency_contacts" ON "tbl_emergency_contacts";
CREATE POLICY "merchant_manage_tbl_emergency_contacts" ON "tbl_emergency_contacts"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_end_category"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_end_category" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "ecat_id" SERIAL PRIMARY KEY,
  "ecat_name" VARCHAR(255) NOT NULL,
  "mcat_id" INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_end_category_merchant" ON "tbl_end_category"(merchant_id);
ALTER TABLE "tbl_end_category" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_end_category" ON "tbl_end_category";
CREATE POLICY "merchant_manage_tbl_end_category" ON "tbl_end_category"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);
DROP POLICY IF EXISTS "public_read_tbl_end_category" ON "tbl_end_category";
CREATE POLICY "public_read_tbl_end_category" ON "tbl_end_category"
  FOR SELECT USING (true);


-- --------------------------------------------------------
-- Table structure for "tbl_faq"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_faq" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "faq_id" SERIAL PRIMARY KEY,
  "faq_title" VARCHAR(255) NOT NULL,
  "faq_content" TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_faq_merchant" ON "tbl_faq"(merchant_id);
ALTER TABLE "tbl_faq" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_faq" ON "tbl_faq";
CREATE POLICY "merchant_manage_tbl_faq" ON "tbl_faq"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);
DROP POLICY IF EXISTS "public_read_tbl_faq" ON "tbl_faq";
CREATE POLICY "public_read_tbl_faq" ON "tbl_faq"
  FOR SELECT USING (true);


-- --------------------------------------------------------
-- Table structure for "tbl_features"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_features" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "id" SERIAL PRIMARY KEY,
  "icon" VARCHAR(50) NOT NULL,
  "title" VARCHAR(100) NOT NULL,
  "link" VARCHAR(255) NOT NULL,
  "order_no" INTEGER DEFAULT 0
);

CREATE INDEX IF NOT EXISTS "idx_tbl_features_merchant" ON "tbl_features"(merchant_id);
ALTER TABLE "tbl_features" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_features" ON "tbl_features";
CREATE POLICY "merchant_manage_tbl_features" ON "tbl_features"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_food_orders"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_food_orders" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "order_id" SERIAL PRIMARY KEY,
  "user_id" INTEGER NOT NULL,
  "restaurant_id" INTEGER NOT NULL,
  "delivery_address" TEXT NOT NULL,
  "delivery_latitude" NUMERIC(10, 8) NOT NULL,
  "delivery_longitude" NUMERIC(11, 8) NOT NULL,
  "order_total" NUMERIC(10, 2) NOT NULL,
  "delivery_fee" NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
  "payment_method" VARCHAR(50) NOT NULL,
  "payment_status" TEXT CHECK ("payment_status" IN ('pending','paid','refunded')) NOT NULL DEFAULT 'pending',
  "order_status" TEXT CHECK ("order_status" IN ('placed','accepted','preparing','out_for_delivery','delivered','cancelled')) NOT NULL DEFAULT 'placed',
  "driver_id" INTEGER DEFAULT NULL,
  "order_notes" TEXT,
  "placed_at" TIMESTAMPTZ DEFAULT NOW(),
  "accepted_at" TIMESTAMPTZ DEFAULT NULL,
  "delivered_at" TIMESTAMPTZ DEFAULT NULL,
  "cancelled_at" TIMESTAMPTZ DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_food_orders_merchant" ON "tbl_food_orders"(merchant_id);
ALTER TABLE "tbl_food_orders" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_food_orders" ON "tbl_food_orders";
CREATE POLICY "merchant_manage_tbl_food_orders" ON "tbl_food_orders"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_food_order_items"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_food_order_items" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "order_item_id" SERIAL PRIMARY KEY,
  "order_id" INTEGER NOT NULL,
  "item_id" INTEGER NOT NULL,
  "quantity" INTEGER NOT NULL,
  "price_at_order" NUMERIC(10, 2) NOT NULL,
  "notes" TEXT
);

CREATE INDEX IF NOT EXISTS "idx_tbl_food_order_items_merchant" ON "tbl_food_order_items"(merchant_id);
ALTER TABLE "tbl_food_order_items" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_food_order_items" ON "tbl_food_order_items";
CREATE POLICY "merchant_manage_tbl_food_order_items" ON "tbl_food_order_items"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_gov_projects"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_gov_projects" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "project_id" SERIAL PRIMARY KEY,
  "project_name" VARCHAR(255) NOT NULL,
  "description" TEXT,
  "region" VARCHAR(255) NOT NULL,
  "start_date" DATE,
  "end_date" DATE,
  "status" TEXT CHECK ("status" IN ('planning','voting','approved','in_progress','completed','cancelled')) NOT NULL DEFAULT 'planning',
  "total_votes_for" INTEGER NOT NULL DEFAULT 0,
  "total_votes_against" INTEGER NOT NULL DEFAULT 0,
  "created_at" TIMESTAMPTZ DEFAULT NOW(),
  "updated_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_gov_projects_merchant" ON "tbl_gov_projects"(merchant_id);
ALTER TABLE "tbl_gov_projects" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_gov_projects" ON "tbl_gov_projects";
CREATE POLICY "merchant_manage_tbl_gov_projects" ON "tbl_gov_projects"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_home_sections"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_home_sections" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "id" SERIAL PRIMARY KEY,
  "title" VARCHAR(255) NOT NULL,
  "category_id" INTEGER NOT NULL,
  "category_type" VARCHAR(20) NOT NULL,
  "product_limit" INTEGER DEFAULT 8,
  "order_no" INTEGER DEFAULT 0
);

CREATE INDEX IF NOT EXISTS "idx_tbl_home_sections_merchant" ON "tbl_home_sections"(merchant_id);
ALTER TABLE "tbl_home_sections" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_home_sections" ON "tbl_home_sections";
CREATE POLICY "merchant_manage_tbl_home_sections" ON "tbl_home_sections"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_home_tabs"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_home_tabs" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "id" SERIAL PRIMARY KEY,
  "tab_name" VARCHAR(100) NOT NULL,
  "tab_icon" VARCHAR(50) NOT NULL,
  "filter_type" TEXT CHECK ("filter_type" IN ('recommendation','free_shipping','top_sale','max_vouchered','overseas','premium','official')) NOT NULL,
  "display_order" INTEGER NOT NULL DEFAULT 0,
  "is_active" SMALLINT NOT NULL DEFAULT 1
);

CREATE INDEX IF NOT EXISTS "idx_tbl_home_tabs_merchant" ON "tbl_home_tabs"(merchant_id);
ALTER TABLE "tbl_home_tabs" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_home_tabs" ON "tbl_home_tabs";
CREATE POLICY "merchant_manage_tbl_home_tabs" ON "tbl_home_tabs"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_hotels"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_hotels" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "hotel_id" SERIAL PRIMARY KEY,
  "owner_user_id" INTEGER DEFAULT NULL,
  "travel_agency_id" INTEGER DEFAULT NULL,
  "hotel_name" VARCHAR(255) NOT NULL,
  "description" TEXT,
  "address" VARCHAR(255) NOT NULL,
  "city" VARCHAR(100) NOT NULL,
  "state" VARCHAR(100) DEFAULT NULL,
  "zip" VARCHAR(20) DEFAULT NULL,
  "phone" VARCHAR(50) NOT NULL,
  "email" VARCHAR(255) DEFAULT NULL,
  "website" VARCHAR(255) DEFAULT NULL,
  "latitude" NUMERIC(10, 8) NOT NULL,
  "longitude" NUMERIC(11, 8) NOT NULL,
  "star_rating" SMALLINT DEFAULT NULL,
  "check_in_time" TIME,
  "check_out_time" TIME,
  "main_photo" VARCHAR(255) DEFAULT NULL,
  "other_photos" TEXT,
  "amenities_json" TEXT,
  "status" TEXT CHECK ("status" IN ('active','inactive','pending_approval','rejected')) NOT NULL DEFAULT 'pending_approval',
  "created_at" TIMESTAMPTZ DEFAULT NOW(),
  "updated_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_hotels_merchant" ON "tbl_hotels"(merchant_id);
ALTER TABLE "tbl_hotels" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_hotels" ON "tbl_hotels";
CREATE POLICY "merchant_manage_tbl_hotels" ON "tbl_hotels"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_hotel_rooms"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_hotel_rooms" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "room_id" SERIAL PRIMARY KEY,
  "hotel_id" INTEGER NOT NULL,
  "room_type_en" VARCHAR(255) NOT NULL,
  "room_type_bn" VARCHAR(255) DEFAULT NULL,
  "description" TEXT,
  "base_price_per_night" NUMERIC(10, 2) NOT NULL,
  "max_occupancy" INTEGER NOT NULL DEFAULT 1,
  "total_rooms_available" INTEGER DEFAULT NULL,
  "current_available_rooms" INTEGER DEFAULT NULL,
  "main_photo" VARCHAR(255) DEFAULT NULL,
  "other_photos" TEXT,
  "amenities_json" TEXT,
  "is_active" SMALLINT NOT NULL DEFAULT 1,
  "created_at" TIMESTAMPTZ DEFAULT NOW(),
  "updated_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_hotel_rooms_merchant" ON "tbl_hotel_rooms"(merchant_id);
ALTER TABLE "tbl_hotel_rooms" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_hotel_rooms" ON "tbl_hotel_rooms";
CREATE POLICY "merchant_manage_tbl_hotel_rooms" ON "tbl_hotel_rooms"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_house_rentals"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_house_rentals" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "rental_id" SERIAL PRIMARY KEY,
  "owner_user_id" INTEGER NOT NULL,
  "property_type" VARCHAR(100) NOT NULL,
  "address" VARCHAR(255) NOT NULL,
  "city" VARCHAR(100) NOT NULL,
  "area" VARCHAR(100) DEFAULT NULL,
  "latitude" NUMERIC(10, 8) NOT NULL,
  "longitude" NUMERIC(11, 8) NOT NULL,
  "rent_amount" NUMERIC(10, 2) NOT NULL,
  "deposit_amount" NUMERIC(10, 2) DEFAULT NULL,
  "bedrooms" INTEGER DEFAULT NULL,
  "bathrooms" INTEGER DEFAULT NULL,
  "square_feet" INTEGER DEFAULT NULL,
  "description" TEXT,
  "main_photo" VARCHAR(255) DEFAULT NULL,
  "other_photos" TEXT,
  "availability_date" DATE,
  "status" TEXT CHECK ("status" IN ('available','rented','pending_approval','rejected')) NOT NULL DEFAULT 'pending_approval',
  "created_at" TIMESTAMPTZ DEFAULT NOW(),
  "updated_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_house_rentals_merchant" ON "tbl_house_rentals"(merchant_id);
ALTER TABLE "tbl_house_rentals" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_house_rentals" ON "tbl_house_rentals";
CREATE POLICY "merchant_manage_tbl_house_rentals" ON "tbl_house_rentals"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_house_sales"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_house_sales" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "sale_id" SERIAL PRIMARY KEY,
  "owner_user_id" INTEGER NOT NULL,
  "property_type" VARCHAR(100) NOT NULL,
  "address" VARCHAR(255) NOT NULL,
  "city" VARCHAR(100) NOT NULL,
  "area" VARCHAR(100) DEFAULT NULL,
  "latitude" NUMERIC(10, 8) NOT NULL,
  "longitude" NUMERIC(11, 8) NOT NULL,
  "selling_price" NUMERIC(15, 2) NOT NULL,
  "bedrooms" INTEGER DEFAULT NULL,
  "bathrooms" INTEGER DEFAULT NULL,
  "square_feet" INTEGER DEFAULT NULL,
  "land_area_sqft" NUMERIC(10, 2) DEFAULT NULL,
  "description" TEXT,
  "main_photo" VARCHAR(255) DEFAULT NULL,
  "other_photos" TEXT,
  "status" TEXT CHECK ("status" IN ('available','sold','pending_approval','rejected')) NOT NULL DEFAULT 'pending_approval',
  "created_at" TIMESTAMPTZ DEFAULT NOW(),
  "updated_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_house_sales_merchant" ON "tbl_house_sales"(merchant_id);
ALTER TABLE "tbl_house_sales" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_house_sales" ON "tbl_house_sales";
CREATE POLICY "merchant_manage_tbl_house_sales" ON "tbl_house_sales"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_kyc_verifications"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_kyc_verifications" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "kyc_id" SERIAL PRIMARY KEY,
  "cust_id" INTEGER NOT NULL,
  "nid_number" VARCHAR(50) NOT NULL,
  "nid_front" VARCHAR(255) NOT NULL,
  "nid_back" VARCHAR(255) DEFAULT NULL,
  "face_capture" VARCHAR(255) NOT NULL,
  "gps_location" VARCHAR(100) DEFAULT NULL,
  "status" TEXT CHECK ("status" IN ('pending','approved','rejected')) DEFAULT 'pending',
  "created_at" TIMESTAMPTZ DEFAULT NOW() NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_kyc_verifications_merchant" ON "tbl_kyc_verifications"(merchant_id);
ALTER TABLE "tbl_kyc_verifications" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_kyc_verifications" ON "tbl_kyc_verifications";
CREATE POLICY "merchant_manage_tbl_kyc_verifications" ON "tbl_kyc_verifications"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_language"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_language" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "lang_id" SERIAL PRIMARY KEY,
  "lang_name" VARCHAR(255) NOT NULL,
  "lang_value" TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_language_merchant" ON "tbl_language"(merchant_id);
ALTER TABLE "tbl_language" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_language" ON "tbl_language";
CREATE POLICY "merchant_manage_tbl_language" ON "tbl_language"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);
DROP POLICY IF EXISTS "public_read_tbl_language" ON "tbl_language";
CREATE POLICY "public_read_tbl_language" ON "tbl_language"
  FOR SELECT USING (true);


-- --------------------------------------------------------
-- Table structure for "tbl_menu_categories"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_menu_categories" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "menu_category_id" SERIAL PRIMARY KEY,
  "restaurant_id" INTEGER NOT NULL,
  "category_name_en" VARCHAR(255) NOT NULL,
  "category_name_bn" VARCHAR(255) DEFAULT NULL,
  "display_order" INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS "idx_tbl_menu_categories_merchant" ON "tbl_menu_categories"(merchant_id);
ALTER TABLE "tbl_menu_categories" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_menu_categories" ON "tbl_menu_categories";
CREATE POLICY "merchant_manage_tbl_menu_categories" ON "tbl_menu_categories"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_menu_items"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_menu_items" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "item_id" SERIAL PRIMARY KEY,
  "restaurant_id" INTEGER NOT NULL,
  "menu_category_id" INTEGER DEFAULT NULL,
  "item_name_en" VARCHAR(255) NOT NULL,
  "item_name_bn" VARCHAR(255) DEFAULT NULL,
  "description_en" TEXT,
  "description_bn" TEXT,
  "price" NUMERIC(10, 2) NOT NULL,
  "is_available" SMALLINT NOT NULL DEFAULT 1,
  "photo" VARCHAR(255) DEFAULT NULL,
  "created_at" TIMESTAMPTZ DEFAULT NOW(),
  "updated_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_menu_items_merchant" ON "tbl_menu_items"(merchant_id);
ALTER TABLE "tbl_menu_items" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_menu_items" ON "tbl_menu_items";
CREATE POLICY "merchant_manage_tbl_menu_items" ON "tbl_menu_items"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_mid_category"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_mid_category" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "mcat_id" SERIAL PRIMARY KEY,
  "mcat_name" VARCHAR(255) NOT NULL,
  "tcat_id" INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_mid_category_merchant" ON "tbl_mid_category"(merchant_id);
ALTER TABLE "tbl_mid_category" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_mid_category" ON "tbl_mid_category";
CREATE POLICY "merchant_manage_tbl_mid_category" ON "tbl_mid_category"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);
DROP POLICY IF EXISTS "public_read_tbl_mid_category" ON "tbl_mid_category";
CREATE POLICY "public_read_tbl_mid_category" ON "tbl_mid_category"
  FOR SELECT USING (true);


-- --------------------------------------------------------
-- Table structure for "tbl_order"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_order" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "id" SERIAL PRIMARY KEY,
  "cust_id" INTEGER DEFAULT NULL,
  "product_id" INTEGER NOT NULL,
  "product_name" VARCHAR(255) NOT NULL,
  "size" VARCHAR(100) NOT NULL,
  "color" VARCHAR(100) NOT NULL,
  "quantity" VARCHAR(50) NOT NULL,
  "unit_price" VARCHAR(50) NOT NULL,
  "payment_id" VARCHAR(255) NOT NULL,
  "coupon_code" VARCHAR(100) DEFAULT NULL,
  "coupon_discount" NUMERIC(10, 2) DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_order_merchant" ON "tbl_order"(merchant_id);
ALTER TABLE "tbl_order" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_order" ON "tbl_order";
CREATE POLICY "merchant_manage_tbl_order" ON "tbl_order"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_page"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_page" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "id" SERIAL PRIMARY KEY,
  "about_title" VARCHAR(255) NOT NULL,
  "about_content" TEXT NOT NULL,
  "about_banner" VARCHAR(255) NOT NULL,
  "about_meta_title" VARCHAR(255) NOT NULL,
  "about_meta_keyword" TEXT NOT NULL,
  "about_meta_description" TEXT NOT NULL,
  "faq_title" VARCHAR(255) NOT NULL,
  "faq_banner" VARCHAR(255) NOT NULL,
  "faq_meta_title" VARCHAR(255) NOT NULL,
  "faq_meta_keyword" TEXT NOT NULL,
  "faq_meta_description" TEXT NOT NULL,
  "blog_title" VARCHAR(255) NOT NULL,
  "blog_banner" VARCHAR(255) NOT NULL,
  "blog_meta_title" VARCHAR(255) NOT NULL,
  "blog_meta_keyword" TEXT NOT NULL,
  "blog_meta_description" TEXT NOT NULL,
  "contact_title" VARCHAR(255) NOT NULL,
  "contact_banner" VARCHAR(255) NOT NULL,
  "contact_meta_title" VARCHAR(255) NOT NULL,
  "contact_meta_keyword" TEXT NOT NULL,
  "contact_meta_description" TEXT NOT NULL,
  "pgallery_title" VARCHAR(255) NOT NULL,
  "pgallery_banner" VARCHAR(255) NOT NULL,
  "pgallery_meta_title" VARCHAR(255) NOT NULL,
  "pgallery_meta_keyword" TEXT NOT NULL,
  "pgallery_meta_description" TEXT NOT NULL,
  "vgallery_title" VARCHAR(255) NOT NULL,
  "vgallery_banner" VARCHAR(255) NOT NULL,
  "vgallery_meta_title" VARCHAR(255) NOT NULL,
  "vgallery_meta_keyword" TEXT NOT NULL,
  "vgallery_meta_description" TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_page_merchant" ON "tbl_page"(merchant_id);
ALTER TABLE "tbl_page" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_page" ON "tbl_page";
CREATE POLICY "merchant_manage_tbl_page" ON "tbl_page"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_payment"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_payment" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "id" SERIAL PRIMARY KEY,
  "customer_id" INTEGER NOT NULL,
  "customer_name" VARCHAR(255) NOT NULL,
  "customer_email" VARCHAR(255) NOT NULL,
  "payment_date" VARCHAR(50) NOT NULL,
  "txnid" VARCHAR(255) NOT NULL,
  "paid_amount" INTEGER NOT NULL,
  "card_number" VARCHAR(50) DEFAULT NULL,
  "card_cvv" VARCHAR(10) DEFAULT NULL,
  "card_month" VARCHAR(10) DEFAULT NULL,
  "card_year" VARCHAR(10) DEFAULT NULL,
  "bank_transaction_info" TEXT,
  "payment_method" VARCHAR(20) NOT NULL,
  "payment_status" VARCHAR(25) NOT NULL,
  "shipping_status" VARCHAR(20) NOT NULL,
  "payment_id" VARCHAR(255) NOT NULL,
  "payment_note" TEXT,
  "ssl_payment_method" VARCHAR(100) DEFAULT NULL,
  "billing_name" VARCHAR(255) DEFAULT NULL,
  "billing_cname" VARCHAR(255) DEFAULT NULL,
  "billing_phone" VARCHAR(50) DEFAULT NULL,
  "billing_country" VARCHAR(255) DEFAULT NULL,
  "billing_address" TEXT,
  "billing_city" VARCHAR(100) DEFAULT NULL,
  "billing_state" VARCHAR(100) DEFAULT NULL,
  "billing_zip" VARCHAR(20) DEFAULT NULL,
  "shipping_name" VARCHAR(255) DEFAULT NULL,
  "shipping_cname" VARCHAR(255) DEFAULT NULL,
  "shipping_phone" VARCHAR(50) DEFAULT NULL,
  "shipping_country" VARCHAR(255) DEFAULT NULL,
  "shipping_address" TEXT,
  "shipping_city" VARCHAR(100) DEFAULT NULL,
  "shipping_state" VARCHAR(100) DEFAULT NULL,
  "shipping_zip" VARCHAR(20) DEFAULT NULL,
  "shipping_cost" NUMERIC(10, 2) DEFAULT NULL,
  "coupon_code" VARCHAR(100) DEFAULT NULL,
  "coupon_discount" NUMERIC(10, 2) DEFAULT 0.00,
  "coupon_id" INTEGER DEFAULT NULL,
  "billing_email" VARCHAR(100) DEFAULT NULL,
  "billing_street" VARCHAR(255) DEFAULT NULL,
  "shipping_street" VARCHAR(255) DEFAULT NULL,
  "shipping_email" VARCHAR(100) DEFAULT NULL,
  "customer_note" TEXT,
  "card_holder_name" VARCHAR(255) DEFAULT NULL,
  "card_security_code" VARCHAR(255) DEFAULT NULL,
  "card_expiry_month" VARCHAR(255) DEFAULT NULL,
  "card_expiry_year" VARCHAR(255) DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_payment_merchant" ON "tbl_payment"(merchant_id);
ALTER TABLE "tbl_payment" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_payment" ON "tbl_payment";
CREATE POLICY "merchant_manage_tbl_payment" ON "tbl_payment"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_photo"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_photo" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "id" SERIAL PRIMARY KEY,
  "caption" VARCHAR(255) NOT NULL,
  "photo" VARCHAR(255) NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_photo_merchant" ON "tbl_photo"(merchant_id);
ALTER TABLE "tbl_photo" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_photo" ON "tbl_photo";
CREATE POLICY "merchant_manage_tbl_photo" ON "tbl_photo"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_post"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_post" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "post_id" SERIAL PRIMARY KEY,
  "post_title" VARCHAR(255) NOT NULL,
  "post_slug" VARCHAR(255) NOT NULL,
  "post_content" TEXT NOT NULL,
  "post_date" VARCHAR(255) NOT NULL,
  "photo" VARCHAR(255) NOT NULL,
  "category_id" INTEGER NOT NULL,
  "total_view" INTEGER NOT NULL,
  "meta_title" VARCHAR(255) NOT NULL,
  "meta_keyword" TEXT NOT NULL,
  "meta_description" TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_post_merchant" ON "tbl_post"(merchant_id);
ALTER TABLE "tbl_post" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_post" ON "tbl_post";
CREATE POLICY "merchant_manage_tbl_post" ON "tbl_post"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_product"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_product" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "p_id" SERIAL PRIMARY KEY,
  "business_id" INTEGER DEFAULT NULL,
  "p_name" VARCHAR(255) NOT NULL,
  "p_old_price" VARCHAR(10) NOT NULL,
  "p_current_price" VARCHAR(10) NOT NULL,
  "p_qty" INTEGER NOT NULL,
  "p_featured_photo" VARCHAR(255) NOT NULL,
  "p_description" TEXT NOT NULL,
  "p_short_description" TEXT NOT NULL,
  "p_feature" TEXT NOT NULL,
  "p_condition" TEXT NOT NULL,
  "p_return_policy" TEXT NOT NULL,
  "p_total_view" INTEGER NOT NULL,
  "p_is_featured" SMALLINT NOT NULL,
  "p_is_active" SMALLINT NOT NULL,
  "ecat_id" INTEGER NOT NULL,
  "p_video_link" VARCHAR(255),
  "is_top_sale" SMALLINT DEFAULT 0,
  "is_free_shipping" SMALLINT DEFAULT 0,
  "is_official" SMALLINT DEFAULT 0,
  "is_premium" SMALLINT DEFAULT 0,
  "is_overseas" SMALLINT DEFAULT 0,
  "is_max_vouchered" SMALLINT DEFAULT 0,
  "vendor_id" INTEGER DEFAULT 0,
  "allow_coin_payment" SMALLINT DEFAULT 0,
  "is_coin_buyable" SMALLINT DEFAULT 0,
  "coin_price" INTEGER DEFAULT 0,
  "coupon_code" VARCHAR(255) DEFAULT NULL,
  "slug" VARCHAR(255) DEFAULT NULL,
  "created_at" TIMESTAMPTZ DEFAULT NOW(),
  "updated_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_product_merchant" ON "tbl_product"(merchant_id);
ALTER TABLE "tbl_product" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_product" ON "tbl_product";
CREATE POLICY "merchant_manage_tbl_product" ON "tbl_product"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);
DROP POLICY IF EXISTS "public_read_tbl_product" ON "tbl_product";
CREATE POLICY "public_read_tbl_product" ON "tbl_product"
  FOR SELECT USING (true);


-- --------------------------------------------------------
-- Table structure for "tbl_product_color"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_product_color" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "id" SERIAL PRIMARY KEY,
  "color_id" INTEGER NOT NULL,
  "p_id" INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_product_color_merchant" ON "tbl_product_color"(merchant_id);
ALTER TABLE "tbl_product_color" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_product_color" ON "tbl_product_color";
CREATE POLICY "merchant_manage_tbl_product_color" ON "tbl_product_color"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);
DROP POLICY IF EXISTS "public_read_tbl_product_color" ON "tbl_product_color";
CREATE POLICY "public_read_tbl_product_color" ON "tbl_product_color"
  FOR SELECT USING (true);


-- --------------------------------------------------------
-- Table structure for "tbl_product_photo"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_product_photo" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "pp_id" SERIAL PRIMARY KEY,
  "photo" VARCHAR(255) NOT NULL,
  "p_id" INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_product_photo_merchant" ON "tbl_product_photo"(merchant_id);
ALTER TABLE "tbl_product_photo" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_product_photo" ON "tbl_product_photo";
CREATE POLICY "merchant_manage_tbl_product_photo" ON "tbl_product_photo"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);
DROP POLICY IF EXISTS "public_read_tbl_product_photo" ON "tbl_product_photo";
CREATE POLICY "public_read_tbl_product_photo" ON "tbl_product_photo"
  FOR SELECT USING (true);


-- --------------------------------------------------------
-- Table structure for "tbl_product_size"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_product_size" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "id" SERIAL PRIMARY KEY,
  "size_id" INTEGER NOT NULL,
  "p_id" INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_product_size_merchant" ON "tbl_product_size"(merchant_id);
ALTER TABLE "tbl_product_size" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_product_size" ON "tbl_product_size";
CREATE POLICY "merchant_manage_tbl_product_size" ON "tbl_product_size"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);
DROP POLICY IF EXISTS "public_read_tbl_product_size" ON "tbl_product_size";
CREATE POLICY "public_read_tbl_product_size" ON "tbl_product_size"
  FOR SELECT USING (true);


-- --------------------------------------------------------
-- Table structure for "tbl_product_variants"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_product_variants" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "variant_id" SERIAL PRIMARY KEY,
  "p_id" INTEGER NOT NULL,
  "size_id" INTEGER NOT NULL,
  "color_id" INTEGER NOT NULL,
  "variant_price" NUMERIC(10, 2) NOT NULL,
  "variant_qty" INTEGER DEFAULT 0
);

CREATE INDEX IF NOT EXISTS "idx_tbl_product_variants_merchant" ON "tbl_product_variants"(merchant_id);
ALTER TABLE "tbl_product_variants" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_product_variants" ON "tbl_product_variants";
CREATE POLICY "merchant_manage_tbl_product_variants" ON "tbl_product_variants"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);
DROP POLICY IF EXISTS "public_read_tbl_product_variants" ON "tbl_product_variants";
CREATE POLICY "public_read_tbl_product_variants" ON "tbl_product_variants"
  FOR SELECT USING (true);


-- --------------------------------------------------------
-- Table structure for "tbl_professionals"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_professionals" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "professional_id" SERIAL PRIMARY KEY,
  "user_id" INTEGER DEFAULT NULL,
  "name" VARCHAR(255) NOT NULL,
  "email" VARCHAR(255) NOT NULL,
  "phone" VARCHAR(50) NOT NULL,
  "nid_number" VARCHAR(50) DEFAULT NULL,
  "nid_photo_front" VARCHAR(255) DEFAULT NULL,
  "nid_photo_back" VARCHAR(255) DEFAULT NULL,
  "license_photo" VARCHAR(255) DEFAULT NULL,
  "additional_docs" TEXT,
  "category_id" INTEGER DEFAULT NULL,
  "bio" TEXT,
  "address" VARCHAR(255) DEFAULT NULL,
  "city" VARCHAR(100) DEFAULT NULL,
  "state" VARCHAR(100) DEFAULT NULL,
  "zip" VARCHAR(20) DEFAULT NULL,
  "latitude" NUMERIC(10, 8) DEFAULT NULL,
  "longitude" NUMERIC(11, 8) DEFAULT NULL,
  "verification_status" TEXT CHECK ("verification_status" IN ('pending','approved','rejected')) NOT NULL DEFAULT 'pending',
  "documents_uploaded" SMALLINT NOT NULL DEFAULT 0,
  "profile_photo" VARCHAR(255) DEFAULT NULL,
  "created_at" TIMESTAMPTZ DEFAULT NOW(),
  "updated_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_professionals_merchant" ON "tbl_professionals"(merchant_id);
CREATE UNIQUE INDEX IF NOT EXISTS "uq_tbl_professionals_0" ON "tbl_professionals"("email");
CREATE UNIQUE INDEX IF NOT EXISTS "uq_tbl_professionals_1" ON "tbl_professionals"("user_id");
ALTER TABLE "tbl_professionals" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_professionals" ON "tbl_professionals";
CREATE POLICY "merchant_manage_tbl_professionals" ON "tbl_professionals"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_professional_categories"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_professional_categories" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "category_id" SERIAL PRIMARY KEY,
  "category_name_en" VARCHAR(255) NOT NULL,
  "category_name_bn" VARCHAR(255) DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_professional_categories_merchant" ON "tbl_professional_categories"(merchant_id);
CREATE UNIQUE INDEX IF NOT EXISTS "uq_tbl_professional_categories_0" ON "tbl_professional_categories"("category_name_en");
ALTER TABLE "tbl_professional_categories" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_professional_categories" ON "tbl_professional_categories";
CREATE POLICY "merchant_manage_tbl_professional_categories" ON "tbl_professional_categories"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_professional_reviews"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_professional_reviews" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "review_id" SERIAL PRIMARY KEY,
  "professional_id" INTEGER NOT NULL,
  "user_id" INTEGER NOT NULL,
  "rating" SMALLINT NOT NULL,
  "review_text" TEXT,
  "created_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_professional_reviews_merchant" ON "tbl_professional_reviews"(merchant_id);
ALTER TABLE "tbl_professional_reviews" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_professional_reviews" ON "tbl_professional_reviews";
CREATE POLICY "merchant_manage_tbl_professional_reviews" ON "tbl_professional_reviews"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_professional_services"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_professional_services" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "service_id" SERIAL PRIMARY KEY,
  "professional_id" INTEGER NOT NULL,
  "service_name" VARCHAR(255) NOT NULL,
  "description" TEXT,
  "price" NUMERIC(10, 2) DEFAULT NULL,
  "duration_minutes" INTEGER DEFAULT NULL,
  "is_available" SMALLINT NOT NULL DEFAULT 1,
  "created_at" TIMESTAMPTZ DEFAULT NOW(),
  "updated_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_professional_services_merchant" ON "tbl_professional_services"(merchant_id);
ALTER TABLE "tbl_professional_services" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_professional_services" ON "tbl_professional_services";
CREATE POLICY "merchant_manage_tbl_professional_services" ON "tbl_professional_services"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_project_votes"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_project_votes" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "vote_id" SERIAL PRIMARY KEY,
  "project_id" INTEGER NOT NULL,
  "user_id" INTEGER NOT NULL,
  "vote_type" TEXT CHECK ("vote_type" IN ('for','against')) NOT NULL,
  "voted_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_project_votes_merchant" ON "tbl_project_votes"(merchant_id);
CREATE UNIQUE INDEX IF NOT EXISTS "uq_tbl_project_votes_0" ON "tbl_project_votes"("project_id", "user_id");
ALTER TABLE "tbl_project_votes" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_project_votes" ON "tbl_project_votes";
CREATE POLICY "merchant_manage_tbl_project_votes" ON "tbl_project_votes"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_rating"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_rating" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "rt_id" SERIAL PRIMARY KEY,
  "p_id" INTEGER NOT NULL,
  "cust_id" INTEGER NOT NULL,
  "comment" TEXT NOT NULL,
  "rating" INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_rating_merchant" ON "tbl_rating"(merchant_id);
ALTER TABLE "tbl_rating" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_rating" ON "tbl_rating";
CREATE POLICY "merchant_manage_tbl_rating" ON "tbl_rating"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_restaurants"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_restaurants" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "restaurant_id" SERIAL PRIMARY KEY,
  "business_owner_id" INTEGER DEFAULT NULL,
  "name" VARCHAR(255) NOT NULL,
  "description" TEXT,
  "address" VARCHAR(255) NOT NULL,
  "city" VARCHAR(100) NOT NULL,
  "state" VARCHAR(100) DEFAULT NULL,
  "zip" VARCHAR(20) DEFAULT NULL,
  "phone" VARCHAR(50) NOT NULL,
  "email" VARCHAR(255) DEFAULT NULL,
  "latitude" NUMERIC(10, 8) NOT NULL,
  "longitude" NUMERIC(11, 8) NOT NULL,
  "opening_time" TIME,
  "closing_time" TIME,
  "is_open" SMALLINT NOT NULL DEFAULT 1,
  "status" TEXT CHECK ("status" IN ('pending','approved','rejected')) NOT NULL DEFAULT 'pending',
  "logo" VARCHAR(255) DEFAULT NULL,
  "cover_photo" VARCHAR(255) DEFAULT NULL,
  "created_at" TIMESTAMPTZ DEFAULT NOW(),
  "updated_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_restaurants_merchant" ON "tbl_restaurants"(merchant_id);
CREATE UNIQUE INDEX IF NOT EXISTS "uq_tbl_restaurants_0" ON "tbl_restaurants"("email");
ALTER TABLE "tbl_restaurants" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_restaurants" ON "tbl_restaurants";
CREATE POLICY "merchant_manage_tbl_restaurants" ON "tbl_restaurants"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_review"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_review" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "review_id" SERIAL PRIMARY KEY,
  "cust_id" INTEGER NOT NULL,
  "product_id" INTEGER NOT NULL,
  "rating" INTEGER NOT NULL,
  "review_text" TEXT NOT NULL,
  "review_date" TIMESTAMPTZ NOT NULL,
  "status" VARCHAR(20) NOT NULL DEFAULT 'Pending',
  "created_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_review_merchant" ON "tbl_review"(merchant_id);
ALTER TABLE "tbl_review" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_review" ON "tbl_review";
CREATE POLICY "merchant_manage_tbl_review" ON "tbl_review"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_review_image"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_review_image" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "image_id" SERIAL PRIMARY KEY,
  "review_id" INTEGER NOT NULL,
  "image_path" VARCHAR(255) NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_review_image_merchant" ON "tbl_review_image"(merchant_id);
ALTER TABLE "tbl_review_image" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_review_image" ON "tbl_review_image";
CREATE POLICY "merchant_manage_tbl_review_image" ON "tbl_review_image"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_service"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_service" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "id" SERIAL PRIMARY KEY,
  "title" VARCHAR(255) NOT NULL,
  "content" TEXT NOT NULL,
  "photo" VARCHAR(255) NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_service_merchant" ON "tbl_service"(merchant_id);
ALTER TABLE "tbl_service" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_service" ON "tbl_service";
CREATE POLICY "merchant_manage_tbl_service" ON "tbl_service"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);
DROP POLICY IF EXISTS "public_read_tbl_service" ON "tbl_service";
CREATE POLICY "public_read_tbl_service" ON "tbl_service"
  FOR SELECT USING (true);


-- --------------------------------------------------------
-- Table structure for "tbl_settings"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_settings" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "id" SERIAL PRIMARY KEY,
  "logo" TEXT NOT NULL,
  "favicon" TEXT NOT NULL,
  "footer_about" TEXT NOT NULL,
  "footer_copyright" TEXT NOT NULL,
  "contact_address" TEXT NOT NULL,
  "contact_email" TEXT NOT NULL,
  "contact_phone" TEXT NOT NULL,
  "contact_fax" TEXT NOT NULL,
  "contact_map_iframe" TEXT NOT NULL,
  "receive_email" TEXT NOT NULL,
  "receive_email_subject" TEXT NOT NULL,
  "receive_email_thank_you_message" TEXT NOT NULL,
  "forget_password_message" TEXT NOT NULL,
  "total_recent_post_footer" INTEGER NOT NULL,
  "total_popular_post_footer" INTEGER NOT NULL,
  "total_recent_post_sidebar" INTEGER NOT NULL,
  "total_popular_post_sidebar" INTEGER NOT NULL,
  "total_featured_product_home" INTEGER NOT NULL,
  "total_latest_product_home" INTEGER NOT NULL,
  "total_popular_product_home" INTEGER NOT NULL,
  "meta_title_home" TEXT NOT NULL,
  "meta_keyword_home" TEXT NOT NULL,
  "meta_description_home" TEXT NOT NULL,
  "google_client_id" VARCHAR(255) DEFAULT NULL,
  "facebook_app_id" VARCHAR(255) DEFAULT NULL,
  "twilio_account_sid" VARCHAR(255) DEFAULT NULL,
  "twilio_auth_token" VARCHAR(255) DEFAULT NULL,
  "twilio_phone_number" VARCHAR(255) DEFAULT NULL,
  "smtp_host" VARCHAR(255) DEFAULT NULL,
  "smtp_username" VARCHAR(255) DEFAULT NULL,
  "smtp_password" VARCHAR(255) DEFAULT NULL,
  "smtp_encryption" VARCHAR(50) DEFAULT 'NONE',
  "smtp_port" INTEGER DEFAULT 587,
  "smtp_from_email" VARCHAR(255) DEFAULT 'no-reply@yourdomain.com',
  "smtp_from_name" VARCHAR(255) DEFAULT 'Your Store',
  "banner_login" TEXT NOT NULL,
  "banner_registration" TEXT NOT NULL,
  "banner_forget_password" TEXT NOT NULL,
  "banner_reset_password" TEXT NOT NULL,
  "banner_search" TEXT NOT NULL,
  "banner_cart" TEXT NOT NULL,
  "banner_checkout" TEXT NOT NULL,
  "banner_product_category" TEXT NOT NULL,
  "banner_blog" TEXT NOT NULL,
  "cta_title" TEXT NOT NULL,
  "cta_content" TEXT NOT NULL,
  "cta_read_more_text" TEXT NOT NULL,
  "cta_read_more_url" TEXT NOT NULL,
  "cta_photo" TEXT NOT NULL,
  "featured_product_title" TEXT NOT NULL,
  "featured_product_subtitle" TEXT NOT NULL,
  "latest_product_title" TEXT NOT NULL,
  "latest_product_subtitle" TEXT NOT NULL,
  "popular_product_title" TEXT NOT NULL,
  "popular_product_subtitle" TEXT NOT NULL,
  "testimonial_title" TEXT NOT NULL,
  "testimonial_subtitle" TEXT NOT NULL,
  "testimonial_photo" TEXT NOT NULL,
  "blog_title" TEXT NOT NULL,
  "blog_subtitle" TEXT NOT NULL,
  "newsletter_text" TEXT NOT NULL,
  "paypal_email" TEXT NOT NULL,
  "stripe_public_key" TEXT NOT NULL,
  "stripe_secret_key" TEXT NOT NULL,
  "bank_detail" TEXT NOT NULL,
  "before_head" TEXT NOT NULL,
  "after_body" TEXT NOT NULL,
  "before_body" TEXT NOT NULL,
  "home_service_on_off" INTEGER NOT NULL,
  "home_welcome_on_off" INTEGER NOT NULL,
  "home_featured_product_on_off" INTEGER NOT NULL,
  "home_latest_product_on_off" INTEGER NOT NULL,
  "home_popular_product_on_off" INTEGER NOT NULL,
  "home_testimonial_on_off" INTEGER NOT NULL,
  "home_blog_on_off" INTEGER NOT NULL,
  "newsletter_on_off" INTEGER NOT NULL,
  "ads_above_welcome_on_off" SMALLINT NOT NULL,
  "ads_above_featured_product_on_off" SMALLINT NOT NULL,
  "ads_above_latest_product_on_off" SMALLINT NOT NULL,
  "ads_above_popular_product_on_off" SMALLINT NOT NULL,
  "ads_above_testimonial_on_off" SMALLINT NOT NULL,
  "ads_category_sidebar_on_off" SMALLINT NOT NULL,
  "sslcz_store_id" VARCHAR(255) NOT NULL,
  "sslcz_store_pass" VARCHAR(255) NOT NULL,
  "sslcz_mode" TEXT CHECK ("sslcz_mode" IN ('sandbox','live')) NOT NULL DEFAULT 'sandbox',
  "payment_methods" VARCHAR(255) NOT NULL DEFAULT 'Cash on Delivery',
  "review_feature_on_off" SMALLINT DEFAULT 1,
  "estimated_delivery_time_local" VARCHAR(255) DEFAULT '3-5 days',
  "estimated_delivery_time_international" VARCHAR(255) DEFAULT '10-20 days',
  "gemini_api_key" VARCHAR(255),
  "flash_sale_end_time" TIMESTAMPTZ DEFAULT NULL,
  "free_delivery_threshold_qty" INTEGER DEFAULT 5,
  "product_voucher_code" VARCHAR(50) DEFAULT 'SAVE10',
  "product_voucher_discount" NUMERIC(10, 2) DEFAULT 10.00,
  "email_method" VARCHAR(50) NOT NULL DEFAULT 'smtp',
  "BASE_URL" VARCHAR(50) NOT NULL DEFAULT 'smtp',
  "paypal_client_id" VARCHAR(50) NOT NULL,
  "paypal_secret" TEXT NOT NULL,
  "paypal_sandbox_mode" TEXT NOT NULL,
  "cod_enabled" SMALLINT NOT NULL DEFAULT 0,
  "facebook_app_secret" VARCHAR(255) DEFAULT NULL,
  "google_client_secret" VARCHAR(255) DEFAULT NULL,
  "home_slider_on_off" SMALLINT DEFAULT 1,
  "home_features_on_off" SMALLINT DEFAULT 1,
  "home_map_on_off" SMALLINT NOT NULL DEFAULT 1,
  "home_newsletter_on_off" SMALLINT NOT NULL DEFAULT 1,
  "home_brand_on_off" SMALLINT NOT NULL DEFAULT 1,
  "home_category_on_off" SMALLINT NOT NULL DEFAULT 1,
  "home_slider_order" INTEGER NOT NULL DEFAULT 1,
  "home_features_order" INTEGER NOT NULL DEFAULT 2,
  "home_category_order" INTEGER NOT NULL DEFAULT 3,
  "home_flash_order" INTEGER NOT NULL DEFAULT 4,
  "home_featured_product_order" INTEGER NOT NULL DEFAULT 5,
  "home_latest_product_order" INTEGER NOT NULL DEFAULT 6,
  "home_popular_product_order" INTEGER NOT NULL DEFAULT 7,
  "bg_color_categories" VARCHAR(20) DEFAULT '#ffffff',
  "bg_color_latest_products" VARCHAR(20) DEFAULT '#ffffff',
  "show_scroll_top_btn" SMALLINT DEFAULT 0,
  "slider_side_banner_img" VARCHAR(255) DEFAULT 'side-banner.jpg',
  "slider_side_banner_text" TEXT,
  "extra_footer_section_enable" SMALLINT DEFAULT 1,
  "home_sticky_nav_on_off" SMALLINT NOT NULL DEFAULT 1,
  "home_sticky_nav_order" INTEGER NOT NULL DEFAULT 8,
  "multi_vendor_active" SMALLINT DEFAULT 1,
  "coin_system_active" SMALLINT DEFAULT 1,
  "sticky_header_mobile" SMALLINT DEFAULT 1,
  "sticky_header_desktop" SMALLINT DEFAULT 1,
  "multi_vendor_on_off" SMALLINT DEFAULT 0,
  "coin_payment_system_on_off" SMALLINT DEFAULT 0,
  "chat_system_on_off" SMALLINT DEFAULT 1,
  "coin_payment_on_off" SMALLINT DEFAULT 0,
  "desktop_advanced_layout_on_off" SMALLINT DEFAULT 1,
  "hide_banner_desktop" SMALLINT NOT NULL DEFAULT 0,
  "hide_banner_mobile" SMALLINT NOT NULL DEFAULT 0,
  "hide_free_delivery_desktop" SMALLINT NOT NULL DEFAULT 0,
  "hide_free_delivery_mobile" SMALLINT NOT NULL DEFAULT 0,
  "bg_color_featured_products" VARCHAR(255) NOT NULL DEFAULT '#ffffff',
  "featured_product_count" INTEGER NOT NULL DEFAULT 8
);

CREATE INDEX IF NOT EXISTS "idx_tbl_settings_merchant" ON "tbl_settings"(merchant_id);
ALTER TABLE "tbl_settings" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_settings" ON "tbl_settings";
CREATE POLICY "merchant_manage_tbl_settings" ON "tbl_settings"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);
DROP POLICY IF EXISTS "public_read_tbl_settings" ON "tbl_settings";
CREATE POLICY "public_read_tbl_settings" ON "tbl_settings"
  FOR SELECT USING (true);


-- --------------------------------------------------------
-- Table structure for "tbl_shipping_cost"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_shipping_cost" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "shipping_cost_id" SERIAL PRIMARY KEY,
  "country_id" INTEGER NOT NULL,
  "amount" VARCHAR(20) NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_shipping_cost_merchant" ON "tbl_shipping_cost"(merchant_id);
ALTER TABLE "tbl_shipping_cost" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_shipping_cost" ON "tbl_shipping_cost";
CREATE POLICY "merchant_manage_tbl_shipping_cost" ON "tbl_shipping_cost"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);
DROP POLICY IF EXISTS "public_read_tbl_shipping_cost" ON "tbl_shipping_cost";
CREATE POLICY "public_read_tbl_shipping_cost" ON "tbl_shipping_cost"
  FOR SELECT USING (true);


-- --------------------------------------------------------
-- Table structure for "tbl_shipping_cost_all"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_shipping_cost_all" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "sca_id" SERIAL PRIMARY KEY,
  "amount" VARCHAR(20) NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_shipping_cost_all_merchant" ON "tbl_shipping_cost_all"(merchant_id);
ALTER TABLE "tbl_shipping_cost_all" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_shipping_cost_all" ON "tbl_shipping_cost_all";
CREATE POLICY "merchant_manage_tbl_shipping_cost_all" ON "tbl_shipping_cost_all"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);
DROP POLICY IF EXISTS "public_read_tbl_shipping_cost_all" ON "tbl_shipping_cost_all";
CREATE POLICY "public_read_tbl_shipping_cost_all" ON "tbl_shipping_cost_all"
  FOR SELECT USING (true);


-- --------------------------------------------------------
-- Table structure for "tbl_size"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_size" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "size_id" SERIAL PRIMARY KEY,
  "size_name" VARCHAR(255) NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_size_merchant" ON "tbl_size"(merchant_id);
ALTER TABLE "tbl_size" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_size" ON "tbl_size";
CREATE POLICY "merchant_manage_tbl_size" ON "tbl_size"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);
DROP POLICY IF EXISTS "public_read_tbl_size" ON "tbl_size";
CREATE POLICY "public_read_tbl_size" ON "tbl_size"
  FOR SELECT USING (true);


-- --------------------------------------------------------
-- Table structure for "tbl_slider"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_slider" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "id" SERIAL PRIMARY KEY,
  "photo" VARCHAR(255) NOT NULL,
  "heading" VARCHAR(255) DEFAULT NULL,
  "content" TEXT,
  "button_text" VARCHAR(255) DEFAULT NULL,
  "button_url" VARCHAR(255) DEFAULT NULL,
  "position" VARCHAR(50) DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_slider_merchant" ON "tbl_slider"(merchant_id);
ALTER TABLE "tbl_slider" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_slider" ON "tbl_slider";
CREATE POLICY "merchant_manage_tbl_slider" ON "tbl_slider"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);
DROP POLICY IF EXISTS "public_read_tbl_slider" ON "tbl_slider";
CREATE POLICY "public_read_tbl_slider" ON "tbl_slider"
  FOR SELECT USING (true);


-- --------------------------------------------------------
-- Table structure for "tbl_social"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_social" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "id" SERIAL PRIMARY KEY,
  "social_name" VARCHAR(100) NOT NULL,
  "social_url" VARCHAR(255)
);

CREATE INDEX IF NOT EXISTS "idx_tbl_social_merchant" ON "tbl_social"(merchant_id);
ALTER TABLE "tbl_social" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_social" ON "tbl_social";
CREATE POLICY "merchant_manage_tbl_social" ON "tbl_social"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);
DROP POLICY IF EXISTS "public_read_tbl_social" ON "tbl_social";
CREATE POLICY "public_read_tbl_social" ON "tbl_social"
  FOR SELECT USING (true);


-- --------------------------------------------------------
-- Table structure for "tbl_subscriber"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_subscriber" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "subs_id" SERIAL PRIMARY KEY,
  "subs_email" VARCHAR(255) NOT NULL,
  "subs_date" VARCHAR(255) NOT NULL,
  "subs_hash" VARCHAR(255) NOT NULL,
  "subs_active" INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_subscriber_merchant" ON "tbl_subscriber"(merchant_id);
ALTER TABLE "tbl_subscriber" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_subscriber" ON "tbl_subscriber";
CREATE POLICY "merchant_manage_tbl_subscriber" ON "tbl_subscriber"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_top_category"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_top_category" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "tcat_id" SERIAL PRIMARY KEY,
  "tcat_name" VARCHAR(255) NOT NULL,
  "show_on_menu" SMALLINT NOT NULL DEFAULT 0,
  "tcat_order" INTEGER NOT NULL,
  "photo" VARCHAR(255) NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_top_category_merchant" ON "tbl_top_category"(merchant_id);
ALTER TABLE "tbl_top_category" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_top_category" ON "tbl_top_category";
CREATE POLICY "merchant_manage_tbl_top_category" ON "tbl_top_category"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);
DROP POLICY IF EXISTS "public_read_tbl_top_category" ON "tbl_top_category";
CREATE POLICY "public_read_tbl_top_category" ON "tbl_top_category"
  FOR SELECT USING (true);


-- --------------------------------------------------------
-- Table structure for "tbl_transport_bookings"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_transport_bookings" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "booking_id" SERIAL PRIMARY KEY,
  "user_id" INTEGER NOT NULL,
  "driver_id" INTEGER DEFAULT NULL,
  "vehicle_type" TEXT CHECK ("vehicle_type" IN ('rickshaw','cng','bike','car')) NOT NULL,
  "pickup_latitude" NUMERIC(10, 8) NOT NULL,
  "pickup_longitude" NUMERIC(11, 8) NOT NULL,
  "dropoff_latitude" NUMERIC(10, 8) NOT NULL,
  "dropoff_longitude" NUMERIC(11, 8) NOT NULL,
  "pickup_address" TEXT NOT NULL,
  "dropoff_address" TEXT NOT NULL,
  "estimated_fare" NUMERIC(10, 2) DEFAULT NULL,
  "actual_fare" NUMERIC(10, 2) DEFAULT NULL,
  "distance_km" NUMERIC(10, 2) DEFAULT NULL,
  "status" TEXT CHECK ("status" IN ('pending','accepted','started','completed','cancelled')) NOT NULL DEFAULT 'pending',
  "payment_status" TEXT CHECK ("payment_status" IN ('unpaid','paid','refunded')) NOT NULL DEFAULT 'unpaid',
  "payment_method" VARCHAR(50) DEFAULT NULL,
  "booked_at" TIMESTAMPTZ DEFAULT NOW(),
  "accepted_at" TIMESTAMPTZ DEFAULT NULL,
  "started_at" TIMESTAMPTZ DEFAULT NULL,
  "completed_at" TIMESTAMPTZ DEFAULT NULL,
  "cancelled_at" TIMESTAMPTZ DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_transport_bookings_merchant" ON "tbl_transport_bookings"(merchant_id);
ALTER TABLE "tbl_transport_bookings" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_transport_bookings" ON "tbl_transport_bookings";
CREATE POLICY "merchant_manage_tbl_transport_bookings" ON "tbl_transport_bookings"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_travel_agencies"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_travel_agencies" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "agency_id" SERIAL PRIMARY KEY,
  "owner_user_id" INTEGER NOT NULL,
  "agency_name" VARCHAR(255) NOT NULL,
  "contact_email" VARCHAR(255) NOT NULL,
  "contact_phone" VARCHAR(50) NOT NULL,
  "address" VARCHAR(255) DEFAULT NULL,
  "description" TEXT,
  "logo" VARCHAR(255) DEFAULT NULL,
  "verification_status" TEXT CHECK ("verification_status" IN ('pending','approved','rejected')) NOT NULL DEFAULT 'pending',
  "is_active" SMALLINT NOT NULL DEFAULT 1,
  "created_at" TIMESTAMPTZ DEFAULT NOW(),
  "updated_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_travel_agencies_merchant" ON "tbl_travel_agencies"(merchant_id);
CREATE UNIQUE INDEX IF NOT EXISTS "uq_tbl_travel_agencies_0" ON "tbl_travel_agencies"("contact_email");
ALTER TABLE "tbl_travel_agencies" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_travel_agencies" ON "tbl_travel_agencies";
CREATE POLICY "merchant_manage_tbl_travel_agencies" ON "tbl_travel_agencies"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_travel_bookings"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_travel_bookings" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "booking_id" SERIAL PRIMARY KEY,
  "user_id" INTEGER NOT NULL,
  "agency_id" INTEGER DEFAULT NULL,
  "booking_type" TEXT CHECK ("booking_type" IN ('hotel','flight','bus','train','package')) NOT NULL,
  "service_provider" VARCHAR(255) DEFAULT NULL,
  "destination" VARCHAR(255) NOT NULL,
  "check_in_date" DATE,
  "check_out_date" DATE,
  "departure_date" DATE,
  "return_date" DATE,
  "total_passengers" INTEGER NOT NULL DEFAULT 1,
  "total_amount" NUMERIC(10, 2) NOT NULL,
  "payment_status" TEXT CHECK ("payment_status" IN ('pending','paid','refunded')) NOT NULL DEFAULT 'pending',
  "booking_status" TEXT CHECK ("booking_status" IN ('pending','confirmed','cancelled','completed')) NOT NULL DEFAULT 'pending',
  "booking_details_json" TEXT,
  "created_at" TIMESTAMPTZ DEFAULT NOW(),
  "updated_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_travel_bookings_merchant" ON "tbl_travel_bookings"(merchant_id);
ALTER TABLE "tbl_travel_bookings" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_travel_bookings" ON "tbl_travel_bookings";
CREATE POLICY "merchant_manage_tbl_travel_bookings" ON "tbl_travel_bookings"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_used_products"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_used_products" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "used_product_id" SERIAL PRIMARY KEY,
  "seller_user_id" INTEGER NOT NULL,
  "product_name" VARCHAR(255) NOT NULL,
  "description" TEXT,
  "price" NUMERIC(10, 2) NOT NULL,
  "condition" TEXT CHECK ("condition" IN ('new_like','good','fair','used')) NOT NULL DEFAULT 'good',
  "category_id" INTEGER DEFAULT NULL,
  "location_address" VARCHAR(255) DEFAULT NULL,
  "latitude" NUMERIC(10, 8) DEFAULT NULL,
  "longitude" NUMERIC(11, 8) DEFAULT NULL,
  "main_photo" VARCHAR(255) DEFAULT NULL,
  "other_photos" TEXT,
  "status" TEXT CHECK ("status" IN ('active','sold','pending_approval','rejected')) NOT NULL DEFAULT 'pending_approval',
  "created_at" TIMESTAMPTZ DEFAULT NOW(),
  "updated_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_used_products_merchant" ON "tbl_used_products"(merchant_id);
ALTER TABLE "tbl_used_products" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_used_products" ON "tbl_used_products";
CREATE POLICY "merchant_manage_tbl_used_products" ON "tbl_used_products"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_user"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_user" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "id" SERIAL PRIMARY KEY,
  "full_name" VARCHAR(255) NOT NULL,
  "email" VARCHAR(255) NOT NULL,
  "phone" VARCHAR(50) NOT NULL,
  "photo" VARCHAR(255) NOT NULL DEFAULT 'default.png',
  "role" VARCHAR(50) NOT NULL DEFAULT 'User',
  "password" VARCHAR(255) NOT NULL,
  "status" VARCHAR(50) NOT NULL DEFAULT 'Active'
);

CREATE INDEX IF NOT EXISTS "idx_tbl_user_merchant" ON "tbl_user"(merchant_id);
ALTER TABLE "tbl_user" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_user" ON "tbl_user";
CREATE POLICY "merchant_manage_tbl_user" ON "tbl_user"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_vehicles"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_vehicles" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "vehicle_id" SERIAL PRIMARY KEY,
  "driver_id" INTEGER NOT NULL,
  "make" VARCHAR(100) DEFAULT NULL,
  "model" VARCHAR(100) DEFAULT NULL,
  "license_plate" VARCHAR(50) NOT NULL,
  "color" VARCHAR(50) DEFAULT NULL,
  "photo" VARCHAR(255) DEFAULT NULL,
  "created_at" TIMESTAMPTZ DEFAULT NOW(),
  "updated_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_vehicles_merchant" ON "tbl_vehicles"(merchant_id);
CREATE UNIQUE INDEX IF NOT EXISTS "uq_tbl_vehicles_0" ON "tbl_vehicles"("license_plate");
ALTER TABLE "tbl_vehicles" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_vehicles" ON "tbl_vehicles";
CREATE POLICY "merchant_manage_tbl_vehicles" ON "tbl_vehicles"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_vouchers"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_vouchers" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "voucher_id" SERIAL PRIMARY KEY,
  "business_id" INTEGER DEFAULT NULL,
  "shop_id" INTEGER DEFAULT NULL,
  "voucher_code" VARCHAR(50) NOT NULL,
  "voucher_name" VARCHAR(255) NOT NULL,
  "voucher_value" NUMERIC(10, 2) NOT NULL,
  "min_purchase_amount" NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
  "usage_limit" INTEGER NOT NULL DEFAULT 1,
  "used_count" INTEGER NOT NULL DEFAULT 0,
  "issue_date" TIMESTAMPTZ NOT NULL,
  "expiry_date" TIMESTAMPTZ NOT NULL,
  "status" TEXT CHECK ("status" IN ('active','inactive','expired')) NOT NULL DEFAULT 'active',
  "created_at" TIMESTAMPTZ DEFAULT NOW(),
  "updated_at" TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "idx_tbl_vouchers_merchant" ON "tbl_vouchers"(merchant_id);
CREATE UNIQUE INDEX IF NOT EXISTS "uq_tbl_vouchers_0" ON "tbl_vouchers"("voucher_code");
ALTER TABLE "tbl_vouchers" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_vouchers" ON "tbl_vouchers";
CREATE POLICY "merchant_manage_tbl_vouchers" ON "tbl_vouchers"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);


-- --------------------------------------------------------
-- Table structure for "tbl_wishlist"
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS "tbl_wishlist" (
  merchant_id UUID REFERENCES merchants(id) ON DELETE CASCADE,
  "wishlist_id" SERIAL PRIMARY KEY,
  "cust_id" INTEGER NOT NULL,
  "product_id" INTEGER NOT NULL,
  "added_date" TIMESTAMPTZ DEFAULT NOW() NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_tbl_wishlist_merchant" ON "tbl_wishlist"(merchant_id);
CREATE UNIQUE INDEX IF NOT EXISTS "uq_tbl_wishlist_0" ON "tbl_wishlist"("cust_id", "product_id");
ALTER TABLE "tbl_wishlist" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "merchant_manage_tbl_wishlist" ON "tbl_wishlist";
CREATE POLICY "merchant_manage_tbl_wishlist" ON "tbl_wishlist"
  FOR ALL USING (merchant_id = current_merchant_id() OR merchant_id IS NULL);

-- ============================================================================
-- Foreign Key Constraints Between Shop Tables
-- ============================================================================
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_ad_advertiser') THEN
    ALTER TABLE "tbl_advertisements" ADD CONSTRAINT "fk_ad_advertiser" FOREIGN KEY ("advertiser_user_id") REFERENCES "tbl_customer"("cust_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_auction_highest_bidder') THEN
    ALTER TABLE "tbl_auctions" ADD CONSTRAINT "fk_auction_highest_bidder" FOREIGN KEY ("highest_bidder_user_id") REFERENCES "tbl_customer"("cust_id") ON DELETE SET NULL ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_auction_seller') THEN
    ALTER TABLE "tbl_auctions" ADD CONSTRAINT "fk_auction_seller" FOREIGN KEY ("seller_user_id") REFERENCES "tbl_customer"("cust_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_bid_auction') THEN
    ALTER TABLE "tbl_auction_bids" ADD CONSTRAINT "fk_bid_auction" FOREIGN KEY ("auction_id") REFERENCES "tbl_auctions"("auction_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_bid_bidder') THEN
    ALTER TABLE "tbl_auction_bids" ADD CONSTRAINT "fk_bid_bidder" FOREIGN KEY ("bidder_user_id") REFERENCES "tbl_customer"("cust_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_business_owner') THEN
    ALTER TABLE "tbl_businesses" ADD CONSTRAINT "fk_business_owner" FOREIGN KEY ("owner_user_id") REFERENCES "tbl_customer"("cust_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_coin_transaction_user') THEN
    ALTER TABLE "tbl_coin_transactions" ADD CONSTRAINT "fk_coin_transaction_user" FOREIGN KEY ("user_id") REFERENCES "tbl_customer"("cust_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'tbl_customer_carts_ibfk_1') THEN
    ALTER TABLE "tbl_customer_carts" ADD CONSTRAINT "tbl_customer_carts_ibfk_1" FOREIGN KEY ("customer_id") REFERENCES "tbl_customer"("cust_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_driver_user') THEN
    ALTER TABLE "tbl_drivers" ADD CONSTRAINT "fk_driver_user" FOREIGN KEY ("user_id") REFERENCES "tbl_customer"("cust_id") ON DELETE SET NULL ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_food_order_driver') THEN
    ALTER TABLE "tbl_food_orders" ADD CONSTRAINT "fk_food_order_driver" FOREIGN KEY ("driver_id") REFERENCES "tbl_drivers"("driver_id") ON DELETE SET NULL ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_food_order_restaurant') THEN
    ALTER TABLE "tbl_food_orders" ADD CONSTRAINT "fk_food_order_restaurant" FOREIGN KEY ("restaurant_id") REFERENCES "tbl_restaurants"("restaurant_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_food_order_user') THEN
    ALTER TABLE "tbl_food_orders" ADD CONSTRAINT "fk_food_order_user" FOREIGN KEY ("user_id") REFERENCES "tbl_customer"("cust_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_order_item_item') THEN
    ALTER TABLE "tbl_food_order_items" ADD CONSTRAINT "fk_order_item_item" FOREIGN KEY ("item_id") REFERENCES "tbl_menu_items"("item_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_order_item_order') THEN
    ALTER TABLE "tbl_food_order_items" ADD CONSTRAINT "fk_order_item_order" FOREIGN KEY ("order_id") REFERENCES "tbl_food_orders"("order_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_hotel_agency') THEN
    ALTER TABLE "tbl_hotels" ADD CONSTRAINT "fk_hotel_agency" FOREIGN KEY ("travel_agency_id") REFERENCES "tbl_travel_agencies"("agency_id") ON DELETE SET NULL ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_hotel_owner') THEN
    ALTER TABLE "tbl_hotels" ADD CONSTRAINT "fk_hotel_owner" FOREIGN KEY ("owner_user_id") REFERENCES "tbl_customer"("cust_id") ON DELETE SET NULL ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_room_hotel') THEN
    ALTER TABLE "tbl_hotel_rooms" ADD CONSTRAINT "fk_room_hotel" FOREIGN KEY ("hotel_id") REFERENCES "tbl_hotels"("hotel_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_rental_owner') THEN
    ALTER TABLE "tbl_house_rentals" ADD CONSTRAINT "fk_rental_owner" FOREIGN KEY ("owner_user_id") REFERENCES "tbl_customer"("cust_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_sale_owner') THEN
    ALTER TABLE "tbl_house_sales" ADD CONSTRAINT "fk_sale_owner" FOREIGN KEY ("owner_user_id") REFERENCES "tbl_customer"("cust_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'tbl_kyc_verifications_ibfk_1') THEN
    ALTER TABLE "tbl_kyc_verifications" ADD CONSTRAINT "tbl_kyc_verifications_ibfk_1" FOREIGN KEY ("cust_id") REFERENCES "tbl_customer"("cust_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_menu_category_restaurant') THEN
    ALTER TABLE "tbl_menu_categories" ADD CONSTRAINT "fk_menu_category_restaurant" FOREIGN KEY ("restaurant_id") REFERENCES "tbl_restaurants"("restaurant_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_menu_item_category') THEN
    ALTER TABLE "tbl_menu_items" ADD CONSTRAINT "fk_menu_item_category" FOREIGN KEY ("menu_category_id") REFERENCES "tbl_menu_categories"("menu_category_id") ON DELETE SET NULL ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_menu_item_restaurant') THEN
    ALTER TABLE "tbl_menu_items" ADD CONSTRAINT "fk_menu_item_restaurant" FOREIGN KEY ("restaurant_id") REFERENCES "tbl_restaurants"("restaurant_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_product_business') THEN
    ALTER TABLE "tbl_product" ADD CONSTRAINT "fk_product_business" FOREIGN KEY ("business_id") REFERENCES "tbl_businesses"("business_id") ON DELETE SET NULL ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'tbl_product_variants_ibfk_1') THEN
    ALTER TABLE "tbl_product_variants" ADD CONSTRAINT "tbl_product_variants_ibfk_1" FOREIGN KEY ("p_id") REFERENCES "tbl_product"("p_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_professional_category') THEN
    ALTER TABLE "tbl_professionals" ADD CONSTRAINT "fk_professional_category" FOREIGN KEY ("category_id") REFERENCES "tbl_professional_categories"("category_id") ON DELETE SET NULL ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_professional_user') THEN
    ALTER TABLE "tbl_professionals" ADD CONSTRAINT "fk_professional_user" FOREIGN KEY ("user_id") REFERENCES "tbl_customer"("cust_id") ON DELETE SET NULL ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_review_professional') THEN
    ALTER TABLE "tbl_professional_reviews" ADD CONSTRAINT "fk_review_professional" FOREIGN KEY ("professional_id") REFERENCES "tbl_professionals"("professional_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_review_user') THEN
    ALTER TABLE "tbl_professional_reviews" ADD CONSTRAINT "fk_review_user" FOREIGN KEY ("user_id") REFERENCES "tbl_customer"("cust_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_service_professional') THEN
    ALTER TABLE "tbl_professional_services" ADD CONSTRAINT "fk_service_professional" FOREIGN KEY ("professional_id") REFERENCES "tbl_professionals"("professional_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_vote_project') THEN
    ALTER TABLE "tbl_project_votes" ADD CONSTRAINT "fk_vote_project" FOREIGN KEY ("project_id") REFERENCES "tbl_gov_projects"("project_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_vote_user') THEN
    ALTER TABLE "tbl_project_votes" ADD CONSTRAINT "fk_vote_user" FOREIGN KEY ("user_id") REFERENCES "tbl_customer"("cust_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'tbl_review_image_ibfk_1') THEN
    ALTER TABLE "tbl_review_image" ADD CONSTRAINT "tbl_review_image_ibfk_1" FOREIGN KEY ("review_id") REFERENCES "tbl_review"("review_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_booking_driver') THEN
    ALTER TABLE "tbl_transport_bookings" ADD CONSTRAINT "fk_booking_driver" FOREIGN KEY ("driver_id") REFERENCES "tbl_drivers"("driver_id") ON DELETE SET NULL ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_booking_user') THEN
    ALTER TABLE "tbl_transport_bookings" ADD CONSTRAINT "fk_booking_user" FOREIGN KEY ("user_id") REFERENCES "tbl_customer"("cust_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_agency_owner') THEN
    ALTER TABLE "tbl_travel_agencies" ADD CONSTRAINT "fk_agency_owner" FOREIGN KEY ("owner_user_id") REFERENCES "tbl_customer"("cust_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_travel_booking_agency') THEN
    ALTER TABLE "tbl_travel_bookings" ADD CONSTRAINT "fk_travel_booking_agency" FOREIGN KEY ("agency_id") REFERENCES "tbl_travel_agencies"("agency_id") ON DELETE SET NULL ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_travel_booking_user') THEN
    ALTER TABLE "tbl_travel_bookings" ADD CONSTRAINT "fk_travel_booking_user" FOREIGN KEY ("user_id") REFERENCES "tbl_customer"("cust_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_used_product_seller') THEN
    ALTER TABLE "tbl_used_products" ADD CONSTRAINT "fk_used_product_seller" FOREIGN KEY ("seller_user_id") REFERENCES "tbl_customer"("cust_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_vehicle_driver') THEN
    ALTER TABLE "tbl_vehicles" ADD CONSTRAINT "fk_vehicle_driver" FOREIGN KEY ("driver_id") REFERENCES "tbl_drivers"("driver_id") ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END $$;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_voucher_business') THEN
    ALTER TABLE "tbl_vouchers" ADD CONSTRAINT "fk_voucher_business" FOREIGN KEY ("business_id") REFERENCES "tbl_businesses"("business_id") ON DELETE SET NULL ON UPDATE CASCADE;
  END IF;
END $$;

-- ============================================================================
-- Seed Data & System Configuration (Safe Idempotent Inserts)
-- ============================================================================

INSERT INTO "tbl_color" ("color_id", "color_name")
VALUES (1, 'Red'),
(2, 'Black'),
(3, 'Blue'),
(4, 'Yellow'),
(5, 'Green'),
(6, 'White'),
(7, 'Orange'),
(8, 'Brown'),
(9, 'Tan'),
(10, 'Pink'),
(11, 'Mixed'),
(12, 'Lightblue'),
(13, 'Violet'),
(14, 'Light Purple'),
(15, 'Salmon'),
(16, 'Gold'),
(17, 'Gray'),
(18, 'Ash'),
(19, 'Maroon'),
(20, 'Silver'),
(21, 'Dark Clay'),
(22, 'Cognac'),
(23, 'Coffee'),
(24, 'Charcoal'),
(25, 'Navy'),
(26, 'Fuchsia'),
(27, 'Olive'),
(28, 'Burgundy'),
(29, 'Midnight Blue'),
(1, 'Red'),
(2, 'Black'),
(3, 'Blue'),
(4, 'Yellow'),
(5, 'Green'),
(6, 'White'),
(7, 'Orange'),
(8, 'Brown'),
(9, 'Tan'),
(10, 'Pink'),
(11, 'Mixed'),
(12, 'Lightblue'),
(13, 'Violet'),
(14, 'Light Purple'),
(15, 'Salmon'),
(16, 'Gold'),
(17, 'Gray'),
(18, 'Ash'),
(19, 'Maroon'),
(20, 'Silver'),
(21, 'Dark Clay'),
(22, 'Cognac'),
(23, 'Coffee'),
(24, 'Charcoal'),
(25, 'Navy'),
(26, 'Fuchsia'),
(27, 'Olive'),
(28, 'Burgundy'),
(29, 'Midnight Blue')
ON CONFLICT ("color_id") DO NOTHING;

INSERT INTO "tbl_country" ("country_id", "country_name")
VALUES (1, 'Afghanistan'),
(2, 'Albania'),
(3, 'Algeria'),
(4, 'American Samoa'),
(5, 'Andorra'),
(6, 'Angola'),
(7, 'Anguilla'),
(8, 'Antarctica'),
(9, 'Antigua and Barbuda'),
(10, 'Argentina'),
(11, 'Armenia'),
(12, 'Aruba'),
(13, 'Australia'),
(14, 'Austria'),
(15, 'Azerbaijan'),
(16, 'Bahamas'),
(17, 'Bahrain'),
(18, 'Bangladesh'),
(19, 'Barbados'),
(20, 'Belarus'),
(21, 'Belgium'),
(22, 'Belize'),
(23, 'Benin'),
(24, 'Bermuda'),
(25, 'Bhutan'),
(26, 'Bolivia'),
(27, 'Bosnia and Herzegovina'),
(28, 'Botswana'),
(29, 'Bouvet Island'),
(30, 'Brazil'),
(31, 'British Indian Ocean Territory'),
(32, 'Brunei Darussalam'),
(33, 'Bulgaria'),
(34, 'Burkina Faso'),
(35, 'Burundi'),
(36, 'Cambodia'),
(37, 'Cameroon'),
(38, 'Canada'),
(39, 'Cape Verde'),
(40, 'Cayman Islands'),
(41, 'Central African Republic'),
(42, 'Chad'),
(43, 'Chile'),
(44, 'China'),
(45, 'Christmas Island'),
(46, 'Cocos (Keeling) Islands'),
(47, 'Colombia'),
(48, 'Comoros'),
(49, 'Congo'),
(50, 'Cook Islands'),
(51, 'Costa Rica'),
(52, 'Croatia (Hrvatska)'),
(53, 'Cuba'),
(54, 'Cyprus'),
(55, 'Czech Republic'),
(56, 'Denmark'),
(57, 'Djibouti'),
(58, 'Dominica'),
(59, 'Dominican Republic'),
(60, 'East Timor'),
(61, 'Ecuador'),
(62, 'Egypt'),
(63, 'El Salvador'),
(64, 'Equatorial Guinea'),
(65, 'Eritrea'),
(66, 'Estonia'),
(67, 'Ethiopia'),
(68, 'Falkland Islands (Malvinas)'),
(69, 'Faroe Islands'),
(70, 'Fiji'),
(71, 'Finland'),
(72, 'France'),
(73, 'France, Metropolitan'),
(74, 'French Guiana'),
(75, 'French Polynesia'),
(76, 'French Southern Territories'),
(77, 'Gabon'),
(78, 'Gambia'),
(79, 'Georgia'),
(80, 'Germany'),
(81, 'Ghana'),
(82, 'Gibraltar'),
(83, 'Guernsey'),
(84, 'Greece'),
(85, 'Greenland'),
(86, 'Grenada'),
(87, 'Guadeloupe'),
(88, 'Guam'),
(89, 'Guatemala'),
(90, 'Guinea'),
(91, 'Guinea-Bissau'),
(92, 'Guyana'),
(93, 'Haiti'),
(94, 'Heard and Mc Donald Islands'),
(95, 'Honduras'),
(96, 'Hong Kong'),
(97, 'Hungary'),
(98, 'Iceland'),
(99, 'India'),
(100, 'Isle of Man'),
(101, 'Indonesia'),
(102, 'Iran (Islamic Republic of)'),
(103, 'Iraq'),
(104, 'Ireland'),
(105, 'Israel'),
(106, 'Italy'),
(107, 'Ivory Coast'),
(108, 'Jersey'),
(109, 'Jamaica'),
(110, 'Japan'),
(111, 'Jordan'),
(112, 'Kazakhstan'),
(113, 'Kenya'),
(114, 'Kiribati'),
(115, 'Korea, Democratic People''s Republic of'),
(116, 'Korea, Republic of'),
(117, 'Kosovo'),
(118, 'Kuwait'),
(119, 'Kyrgyzstan'),
(120, 'Lao People''s Democratic Republic'),
(121, 'Latvia'),
(122, 'Lebanon'),
(123, 'Lesotho'),
(124, 'Liberia'),
(125, 'Libyan Arab Jamahiriya'),
(126, 'Liechtenstein'),
(127, 'Lithuania'),
(128, 'Luxembourg'),
(129, 'Macau'),
(130, 'Macedonia'),
(131, 'Madagascar'),
(132, 'Malawi'),
(133, 'Malaysia'),
(134, 'Maldives'),
(135, 'Mali'),
(136, 'Malta'),
(137, 'Marshall Islands'),
(138, 'Martinique'),
(139, 'Mauritania'),
(140, 'Mauritius'),
(141, 'Mayotte'),
(142, 'Mexico'),
(143, 'Micronesia, Federated States of'),
(144, 'Moldova, Republic of'),
(145, 'Monaco'),
(146, 'Mongolia'),
(147, 'Montenegro'),
(148, 'Montserrat'),
(149, 'Morocco'),
(150, 'Mozambique'),
(151, 'Myanmar'),
(152, 'Namibia'),
(153, 'Nauru'),
(154, 'Nepal'),
(155, 'Netherlands'),
(156, 'Netherlands Antilles'),
(157, 'New Caledonia'),
(158, 'New Zealand'),
(159, 'Nicaragua'),
(160, 'Niger'),
(161, 'Nigeria'),
(162, 'Niue'),
(163, 'Norfolk Island'),
(164, 'Northern Mariana Islands'),
(165, 'Norway'),
(166, 'Oman'),
(167, 'Pakistan'),
(168, 'Palau'),
(169, 'Palestine'),
(170, 'Panama'),
(171, 'Papua New Guinea'),
(172, 'Paraguay'),
(173, 'Peru'),
(174, 'Philippines'),
(175, 'Pitcairn'),
(176, 'Poland'),
(177, 'Portugal'),
(178, 'Puerto Rico'),
(179, 'Qatar'),
(180, 'Reunion'),
(181, 'Romania'),
(182, 'Russian Federation'),
(183, 'Rwanda'),
(184, 'Saint Kitts and Nevis'),
(185, 'Saint Lucia'),
(186, 'Saint Vincent and the Grenadines'),
(187, 'Samoa'),
(188, 'San Marino'),
(189, 'Sao Tome and Principe'),
(190, 'Saudi Arabia'),
(191, 'Senegal'),
(192, 'Serbia'),
(193, 'Seychelles'),
(194, 'Sierra Leone'),
(195, 'Singapore'),
(196, 'Slovakia'),
(197, 'Slovenia'),
(198, 'Solomon Islands'),
(199, 'Somalia'),
(200, 'South Africa'),
(201, 'South Georgia South Sandwich Islands'),
(202, 'Spain'),
(203, 'Sri Lanka'),
(204, 'St. Helena'),
(205, 'St. Pierre and Miquelon'),
(206, 'Sudan'),
(207, 'Suriname'),
(208, 'Svalbard and Jan Mayen Islands'),
(209, 'Swaziland'),
(210, 'Sweden'),
(211, 'Switzerland'),
(212, 'Syrian Arab Republic'),
(213, 'Taiwan'),
(214, 'Tajikistan'),
(215, 'Tanzania, United Republic of'),
(216, 'Thailand'),
(217, 'Togo'),
(218, 'Tokelau'),
(219, 'Tonga'),
(220, 'Trinidad and Tobago'),
(221, 'Tunisia'),
(222, 'Turkey'),
(223, 'Turkmenistan'),
(224, 'Turks and Caicos Islands'),
(225, 'Tuvalu'),
(226, 'Uganda'),
(227, 'Ukraine'),
(228, 'United Arab Emirates'),
(229, 'United Kingdom'),
(230, 'United States'),
(231, 'United States minor outlying islands'),
(232, 'Uruguay'),
(233, 'Uzbekistan'),
(234, 'Vanuatu'),
(235, 'Vatican City State'),
(236, 'Venezuela'),
(237, 'Vietnam'),
(238, 'Virgin Islands (British)'),
(239, 'Virgin Islands (U.S.)'),
(240, 'Wallis and Futuna Islands'),
(241, 'Western Sahara'),
(242, 'Yemen'),
(243, 'Zaire'),
(244, 'Zambia'),
(245, 'Zimbabwe'),
(1, 'Afghanistan'),
(2, 'Albania'),
(3, 'Algeria'),
(4, 'American Samoa'),
(5, 'Andorra'),
(6, 'Angola'),
(7, 'Anguilla'),
(8, 'Antarctica'),
(9, 'Antigua and Barbuda'),
(10, 'Argentina'),
(11, 'Armenia'),
(12, 'Aruba'),
(13, 'Australia'),
(14, 'Austria'),
(15, 'Azerbaijan'),
(16, 'Bahamas'),
(17, 'Bahrain'),
(18, 'Bangladesh'),
(19, 'Barbados'),
(20, 'Belarus'),
(21, 'Belgium'),
(22, 'Belize'),
(23, 'Benin'),
(24, 'Bermuda'),
(25, 'Bhutan'),
(26, 'Bolivia'),
(27, 'Bosnia and Herzegovina'),
(28, 'Botswana'),
(29, 'Bouvet Island'),
(30, 'Brazil'),
(31, 'British Indian Ocean Territory'),
(32, 'Brunei Darussalam'),
(33, 'Bulgaria'),
(34, 'Burkina Faso'),
(35, 'Burundi'),
(36, 'Cambodia'),
(37, 'Cameroon'),
(38, 'Canada'),
(39, 'Cape Verde'),
(40, 'Cayman Islands'),
(41, 'Central African Republic'),
(42, 'Chad'),
(43, 'Chile'),
(44, 'China'),
(45, 'Christmas Island'),
(46, 'Cocos (Keeling) Islands'),
(47, 'Colombia'),
(48, 'Comoros'),
(49, 'Congo'),
(50, 'Cook Islands'),
(51, 'Costa Rica'),
(52, 'Croatia (Hrvatska)'),
(53, 'Cuba'),
(54, 'Cyprus'),
(55, 'Czech Republic'),
(56, 'Denmark'),
(57, 'Djibouti'),
(58, 'Dominica'),
(59, 'Dominican Republic'),
(60, 'East Timor'),
(61, 'Ecuador'),
(62, 'Egypt'),
(63, 'El Salvador'),
(64, 'Equatorial Guinea'),
(65, 'Eritrea'),
(66, 'Estonia'),
(67, 'Ethiopia'),
(68, 'Falkland Islands (Malvinas)'),
(69, 'Faroe Islands'),
(70, 'Fiji'),
(71, 'Finland'),
(72, 'France'),
(73, 'France, Metropolitan'),
(74, 'French Guiana'),
(75, 'French Polynesia'),
(76, 'French Southern Territories'),
(77, 'Gabon'),
(78, 'Gambia'),
(79, 'Georgia'),
(80, 'Germany'),
(81, 'Ghana'),
(82, 'Gibraltar'),
(83, 'Guernsey'),
(84, 'Greece'),
(85, 'Greenland'),
(86, 'Grenada'),
(87, 'Guadeloupe'),
(88, 'Guam'),
(89, 'Guatemala'),
(90, 'Guinea'),
(91, 'Guinea-Bissau'),
(92, 'Guyana'),
(93, 'Haiti'),
(94, 'Heard and Mc Donald Islands'),
(95, 'Honduras'),
(96, 'Hong Kong'),
(97, 'Hungary'),
(98, 'Iceland'),
(99, 'India'),
(100, 'Isle of Man'),
(101, 'Indonesia'),
(102, 'Iran (Islamic Republic of)'),
(103, 'Iraq'),
(104, 'Ireland'),
(105, 'Israel'),
(106, 'Italy'),
(107, 'Ivory Coast'),
(108, 'Jersey'),
(109, 'Jamaica'),
(110, 'Japan'),
(111, 'Jordan'),
(112, 'Kazakhstan'),
(113, 'Kenya'),
(114, 'Kiribati'),
(115, 'Korea, Democratic People''s Republic of'),
(116, 'Korea, Republic of'),
(117, 'Kosovo'),
(118, 'Kuwait'),
(119, 'Kyrgyzstan'),
(120, 'Lao People''s Democratic Republic'),
(121, 'Latvia'),
(122, 'Lebanon'),
(123, 'Lesotho'),
(124, 'Liberia'),
(125, 'Libyan Arab Jamahiriya'),
(126, 'Liechtenstein'),
(127, 'Lithuania'),
(128, 'Luxembourg'),
(129, 'Macau'),
(130, 'Macedonia'),
(131, 'Madagascar'),
(132, 'Malawi'),
(133, 'Malaysia'),
(134, 'Maldives'),
(135, 'Mali'),
(136, 'Malta'),
(137, 'Marshall Islands'),
(138, 'Martinique'),
(139, 'Mauritania'),
(140, 'Mauritius'),
(141, 'Mayotte'),
(142, 'Mexico'),
(143, 'Micronesia, Federated States of'),
(144, 'Moldova, Republic of'),
(145, 'Monaco'),
(146, 'Mongolia'),
(147, 'Montenegro'),
(148, 'Montserrat'),
(149, 'Morocco'),
(150, 'Mozambique'),
(151, 'Myanmar'),
(152, 'Namibia'),
(153, 'Nauru'),
(154, 'Nepal'),
(155, 'Netherlands'),
(156, 'Netherlands Antilles'),
(157, 'New Caledonia'),
(158, 'New Zealand'),
(159, 'Nicaragua'),
(160, 'Niger'),
(161, 'Nigeria'),
(162, 'Niue'),
(163, 'Norfolk Island'),
(164, 'Northern Mariana Islands'),
(165, 'Norway'),
(166, 'Oman'),
(167, 'Pakistan'),
(168, 'Palau'),
(169, 'Palestine'),
(170, 'Panama'),
(171, 'Papua New Guinea'),
(172, 'Paraguay'),
(173, 'Peru'),
(174, 'Philippines'),
(175, 'Pitcairn'),
(176, 'Poland'),
(177, 'Portugal'),
(178, 'Puerto Rico'),
(179, 'Qatar'),
(180, 'Reunion'),
(181, 'Romania'),
(182, 'Russian Federation'),
(183, 'Rwanda'),
(184, 'Saint Kitts and Nevis'),
(185, 'Saint Lucia'),
(186, 'Saint Vincent and the Grenadines'),
(187, 'Samoa'),
(188, 'San Marino'),
(189, 'Sao Tome and Principe'),
(190, 'Saudi Arabia'),
(191, 'Senegal'),
(192, 'Serbia'),
(193, 'Seychelles'),
(194, 'Sierra Leone'),
(195, 'Singapore'),
(196, 'Slovakia'),
(197, 'Slovenia'),
(198, 'Solomon Islands'),
(199, 'Somalia'),
(200, 'South Africa'),
(201, 'South Georgia South Sandwich Islands'),
(202, 'Spain'),
(203, 'Sri Lanka'),
(204, 'St. Helena'),
(205, 'St. Pierre and Miquelon'),
(206, 'Sudan'),
(207, 'Suriname'),
(208, 'Svalbard and Jan Mayen Islands'),
(209, 'Swaziland'),
(210, 'Sweden'),
(211, 'Switzerland'),
(212, 'Syrian Arab Republic'),
(213, 'Taiwan'),
(214, 'Tajikistan'),
(215, 'Tanzania, United Republic of'),
(216, 'Thailand'),
(217, 'Togo'),
(218, 'Tokelau'),
(219, 'Tonga'),
(220, 'Trinidad and Tobago'),
(221, 'Tunisia'),
(222, 'Turkey'),
(223, 'Turkmenistan'),
(224, 'Turks and Caicos Islands'),
(225, 'Tuvalu'),
(226, 'Uganda'),
(227, 'Ukraine'),
(228, 'United Arab Emirates'),
(229, 'United Kingdom'),
(230, 'United States'),
(231, 'United States minor outlying islands'),
(232, 'Uruguay'),
(233, 'Uzbekistan'),
(234, 'Vanuatu'),
(235, 'Vatican City State'),
(236, 'Venezuela'),
(237, 'Vietnam'),
(238, 'Virgin Islands (British)'),
(239, 'Virgin Islands (U.S.)'),
(240, 'Wallis and Futuna Islands'),
(241, 'Western Sahara'),
(242, 'Yemen'),
(243, 'Zaire'),
(244, 'Zambia'),
(245, 'Zimbabwe')
ON CONFLICT ("country_id") DO NOTHING;

INSERT INTO "tbl_coupon" ("coupon_id", "coupon_code", "discount_type", "discount_value", "minimum_order", "usage_limit", "used_count", "start_date", "end_date", "status")
VALUES (1, '6', 'fixed', 666.00, 0.00, 0, 6, '2025-06-07', '2025-10-12', 'active')
ON CONFLICT ("coupon_id") DO NOTHING;

INSERT INTO "tbl_customer" ("cust_id", "cust_name", "cust_cname", "cust_email", "cust_phone", "cust_country", "cust_address", "cust_city", "cust_state", "cust_zip", "cust_b_name", "cust_b_cname", "cust_b_phone", "cust_b_country", "cust_b_address", "cust_b_city", "cust_b_state", "cust_b_zip", "cust_s_name", "cust_s_cname", "cust_s_phone", "cust_s_country", "cust_s_address", "cust_s_city", "cust_s_state", "cust_s_zip", "cust_password", "cust_coin_balance", "cust_token", "cust_datetime", "cust_timestamp", "cust_status", "role_type", "is_verified", "trust_score", "current_lat", "current_lng", "wallet_balance", "identity_doc_path")
VALUES (1, 'Liam Moore', 'WV Company', 'liam@mail.com', '7458965410', 230, '788 Cottonwood Lane', 'Nashville', 'TN', '37072', '', '', '', 0, '', '', '', '', '', '', '', 0, '', '', '', '', '5f4dcc3b5aa765d61d8327deb882cf99', 0.00, '0081e99a29cacd4b553db15c5c5c047e', '2022-03-17 11:09:34', '1647544174', 1, 'customer', 0, 50, NULL, NULL, 0.00, NULL),
(2, 'Chad N. Carney', 'none', 'chad@mail.com', '4785690000', 230, '469 Diamond Street', 'Charlotte', 'NC', '28808', 'Chad N. Carney', 'none', '7477474440', 230, '469 Diamond Street', 'Charlotte', 'NC', '28808', 'Chad N. Carney', 'none', '7477474440', 230, '469 Diamond Street', 'Charlotte', 'NC', '28808', '5f4dcc3b5aa765d61d8327deb882cf99', 0.00, 'ca87666426f4bc5c5128a96dabfecefb', '2022-03-17 11:15:26', '1647544526', 1, 'customer', 0, 50, NULL, NULL, 0.00, NULL),
(3, 'Jean Collins', 'none', 'jean@mail.com', '1478523698', 230, '1508 Crosswind Drive', 'Owensboro', 'KY', '13040', 'Jean Collins', 'none', '1478523698', 230, '1508 Crosswind Drive', 'Owensboro', 'KY', '13040', 'Jean Collins', 'none', '1478523698', 230, '1508 Crosswind Drive', 'Owensboro', 'KY', '13040', '5f4dcc3b5aa765d61d8327deb882cf99', 0.00, '6b3439bf95644a36a1ed92bef374ebb7', '2022-03-20 10:29:39', '1647797379', 1, 'customer', 0, 50, NULL, NULL, 0.00, NULL),
(4, 'Annie Young', 'XYZ Company', 'annie@mail.com', '7770001144', 230, '79 Burwell Heights Road', 'Beaumont', 'TX', '77400', '', '', '', 0, '', '', '', '', '', '', '', 0, '', '', '', '', '5f4dcc3b5aa765d61d8327deb882cf99', 0.00, 'fc8f07537cdd6b3f89eb94f1cad78060', '2022-03-20 10:31:35', '1647797495', 1, 'customer', 0, 50, NULL, NULL, 0.00, NULL),
(5, 'Matthew Morales', 'ABC Company', 'matthew@mail.com', '7896587450', 230, '81 Felosa Drive', 'Mira Loma', 'CA', '91002', 'Matthew Morales', 'ABC Company', '7896587450', 230, '81 Felosa Drive', 'Mira Loma', 'CA', '91002', 'Matthew Morales', 'ABC Company', '7896587450', 230, '81 Felosa Drive', 'Mira Loma', 'CA', '91002', '5f4dcc3b5aa765d61d8327deb882cf99', 0.00, 'c391105908fe01a636bfa5fc39eed33d', '2022-03-20 10:33:15', '1647797595', 1, 'customer', 0, 50, NULL, NULL, 0.00, NULL),
(6, 'August F. Freels', 'none', 'august@mail.com', '1478547850', 230, '96 Johnny Lane', 'Milwaukee', 'WI', '55550', 'August F. Freels', 'none', '1478547850', 230, '96 Johnny Lane', 'Milwaukee', 'WI', '55550', 'August F. Freels', 'none', '1478547850', 230, '96 Johnny Lane', 'Milwaukee', 'WI', '55550', '5f4dcc3b5aa765d61d8327deb882cf99', 0.00, 'decc1fc2c5dd9935df82c0233002ce66', '2022-03-20 10:34:08', '1647797648', 1, 'customer', 0, 50, NULL, NULL, 0.00, NULL),
(7, 'Carl M. Dineen', 'none', 'carl@mail.com', '789878987', 230, '77 Lyndon Street', 'Kutztown', 'PA', '19855', '', '', '', 0, '', '', '', '', '', '', '', 0, '', '', '', '', '5f4dcc3b5aa765d61d8327deb882cf99', 0.00, 'c79bac688e70cc9665a2164c57ec172c', '2022-03-20 10:35:02', '1647797702', 1, 'customer', 0, 50, NULL, NULL, 0.00, NULL),
(8, 'Benjamin B. Louque', 'none', 'benjamin@mail.com', '7777889955', 230, '32 Bridge Street', 'Tulsa', 'OK', '74220', '', '', '', 0, '', '', '', '', '', '', '', 0, '', '', '', '', '5f4dcc3b5aa765d61d8327deb882cf99', 0.00, '5a0e096368f9669508af7b7203382b07', '2022-03-20 10:36:31', '1647797791', 1, 'customer', 0, 50, NULL, NULL, 0.00, NULL),
(9, 'Joe K. Richardson', 'none', 'joe@mail.com', '4444445555', 230, '17 Derek Drive', 'Youngstown', 'OH', '44500', '', '', '', 0, '', '', '', '', '', '', '', 0, '', '', '', '', '5f4dcc3b5aa765d61d8327deb882cf99', 0.00, 'e74ac0178d7833988d4b1625c42ba26e', '2022-03-20 10:37:18', '1647797838', 1, 'customer', 0, 50, NULL, NULL, 0.00, NULL),
(10, 'Will Williams', 'Test Company', 'williams@mail.com', '7410000000', 230, '39 Marcus Street', 'Anniston', 'AL', '37207', 'Will Williams', 'Test Company', '7410000000', 230, '39 Marcus Street', 'Anniston', 'AL', '37207', 'Will Williams', 'Test Company', '7410000000', 230, '39 Marcus Street', 'Anniston', 'AL', '37207', '5f4dcc3b5aa765d61d8327deb882cf99', 0.00, '941c9265fb920f691cf01b12a15f80f8', '2022-03-20 11:15:59', '1647800159', 1, 'customer', 0, 50, NULL, NULL, 0.00, NULL),
(24, 'Joy Saha', '', 'jsaha3741@gmail.com', '01735342839', 18, 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'CUMILLA', 'Cfg', '3700', 'Joy Saha', 'ytrfhgfh', '01735342839', 18, 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'CUMILLA', 'Cfg', '3700', 'Joy Saha', 'tyyt', '01735342839', 18, 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'CUMILLA', 'Cfg', '3700', '$2y$10$EN89MGW6KGSQYVMsoVcl6.mYQX6yG0wo1kCTessRtOBeMXqzsMYKi', 0.00, '', '2026-01-21 02:51:27', '', 1, 'customer', 0, 50, NULL, NULL, 0.00, NULL)
ON CONFLICT ("cust_id") DO NOTHING;

INSERT INTO "tbl_customer_carts" ("cart_id", "customer_id", "product_id", "size_id", "size_name", "color_id", "color_name", "quantity", "price_at_add", "product_name", "product_photo", "added_at", "updated_at")
VALUES (18, 24, 87, 29, '12 Months', 3, 'Blue', 1, 37.00, 'Truck Boys Pajamas Toddler Sleepwear Clothes', 'product-featured-87.jpg', '2026-01-21 12:56:18', '2026-01-21 12:56:18'),
(19, 24, 102, 42, '14 Plus', 2, 'Black', 2, 169.00, 'Women''s Plus-Size Shirt Dress with Gold Hardware', 'product-featured-102.jpg', '2026-01-21 13:16:43', '2026-01-21 13:16:46')
ON CONFLICT ("cart_id") DO NOTHING;

INSERT INTO "tbl_customer_message" ("customer_message_id", "subject", "message", "order_detail", "cust_id")
VALUES (0, 'yt', '5yr', '\nCustomer Name: Joy Saha<br>\nCustomer Email: s@1.com<br>\nPayment Method: PayPal<br>\nPayment Date: 2025-06-06 06:18:34<br>\nPayment Details: <br>\nTransaction Id: <br>\n        		<br>\nPaid Amount: 379<br>\nPayment Status: Pending<br>\nShipping Status: Pending<br>\nPayment Id: 1749215914<br>\n            ', 0),
(0, 'yt', '5yr', '\nCustomer Name: Joy Saha<br>\nCustomer Email: s@1.com<br>\nPayment Method: PayPal<br>\nPayment Date: 2025-06-06 06:18:34<br>\nPayment Details: <br>\nTransaction Id: <br>\n        		<br>\nPaid Amount: 379<br>\nPayment Status: Pending<br>\nShipping Status: Pending<br>\nPayment Id: 1749215914<br>\n            ', 0),
(0, 'yt', '5yr', '\nCustomer Name: Joy Saha<br>\nCustomer Email: s@1.com<br>\nPayment Method: PayPal<br>\nPayment Date: 2025-06-06 06:18:34<br>\nPayment Details: <br>\nTransaction Id: <br>\n        		<br>\nPaid Amount: 379<br>\nPayment Status: Pending<br>\nShipping Status: Pending<br>\nPayment Id: 1749215914<br>\n            ', 0),
(0, 'yt', '5yr', '\nCustomer Name: Joy Saha<br>\nCustomer Email: s@1.com<br>\nPayment Method: PayPal<br>\nPayment Date: 2025-06-06 06:18:34<br>\nPayment Details: <br>\nTransaction Id: <br>\n        		<br>\nPaid Amount: 379<br>\nPayment Status: Pending<br>\nShipping Status: Pending<br>\nPayment Id: 1749215914<br>\n            ', 0)
ON CONFLICT ("customer_message_id") DO NOTHING;

INSERT INTO "tbl_end_category" ("ecat_id", "ecat_name", "mcat_id")
VALUES (1, 'Headwear ', 1),
(2, 'Sunglasses', 1),
(3, 'Watches', 1),
(4, 'Sandals', 2),
(5, 'Boots', 2),
(6, 'Tops', 3),
(7, 'T-Shirt', 3),
(8, 'Watches', 4),
(9, 'Sunglasses', 4),
(11, 'Sports Shoes', 2),
(12, 'Sandals', 6),
(13, 'Flat Shoes', 6),
(14, 'Hoodies', 7),
(15, 'Coats & Jackets', 7),
(16, 'Pants', 8),
(17, 'Jeans', 8),
(18, 'Joggers', 8),
(19, 'Shorts', 8),
(20, 'T-shirts', 9),
(21, 'Casual Shirts', 9),
(22, 'Formal Shirts', 9),
(23, 'Polo Shirts', 9),
(24, 'Vests', 9),
(25, 'Casual Shoes', 2),
(26, 'Boys', 10),
(27, 'Girls', 10),
(28, 'Boys', 11),
(29, 'Girls', 11),
(30, 'Boys', 12),
(31, 'Girls', 12),
(32, 'Dresses', 7),
(33, 'Tops', 7),
(34, 'T-Shirts & Vests', 7),
(35, 'Pants & Leggings', 7),
(36, 'Sportswear', 7),
(37, 'Plus Size Clothing', 7),
(38, 'Socks & Hosiery', 7),
(39, 'Fragrance', 3),
(40, 'Skincare', 3),
(41, 'Hair Care', 3),
(42, 'Jewellery', 4),
(43, 'Eyes Care', 3),
(44, 'Lips', 3),
(45, 'Face Care', 3),
(46, 'Gift Sets', 3),
(47, 'Scarves & Headwear', 4),
(48, 'Multipacks', 4),
(49, 'Other Accessories', 4),
(50, 'Pumps', 6),
(51, 'Sneakers', 6),
(52, 'Sports Shoes', 6),
(53, 'Boots', 6),
(54, 'Comfort Shoes', 6),
(55, 'Slippers & Casual Shoes', 6),
(56, 'Formal Shoes', 2),
(57, 'Belts', 1),
(58, 'Multipacks', 1),
(59, 'Other Accessories', 1),
(60, 'Bags', 4),
(61, 'Cell Phone and Accessories', 14),
(62, 'Headphones', 14),
(63, 'Security and Surveillance', 14),
(64, 'Television and Video', 14),
(65, 'GPS and Navigation', 14),
(66, 'Home Audio', 14),
(67, 'Computer Components', 15),
(68, 'Computers and Tablets', 15),
(69, 'Laptop Accessories', 15),
(70, 'Printer and Monitors', 15),
(71, 'External Components', 15),
(72, 'Networking Products', 15),
(73, 'Medical Supplies and Equipment', 16),
(74, 'Oral Care', 16),
(75, 'Vision Care', 16),
(76, 'Vitamins and Dietary Supplements', 16),
(77, 'Baby and Child Care', 17),
(78, 'Household Supplies', 17),
(79, 'Stationery and Gift Wrapping Supplies', 17),
(1, 'Headwear ', 1),
(2, 'Sunglasses', 1),
(3, 'Watches', 1),
(4, 'Sandals', 2),
(5, 'Boots', 2),
(6, 'Tops', 3),
(7, 'T-Shirt', 3),
(8, 'Watches', 4),
(9, 'Sunglasses', 4),
(11, 'Sports Shoes', 2),
(12, 'Sandals', 6),
(13, 'Flat Shoes', 6),
(14, 'Hoodies', 7),
(15, 'Coats & Jackets', 7),
(16, 'Pants', 8),
(17, 'Jeans', 8),
(18, 'Joggers', 8),
(19, 'Shorts', 8),
(20, 'T-shirts', 9),
(21, 'Casual Shirts', 9),
(22, 'Formal Shirts', 9),
(23, 'Polo Shirts', 9),
(24, 'Vests', 9),
(25, 'Casual Shoes', 2),
(26, 'Boys', 10),
(27, 'Girls', 10),
(28, 'Boys', 11),
(29, 'Girls', 11),
(30, 'Boys', 12),
(31, 'Girls', 12),
(32, 'Dresses', 7),
(33, 'Tops', 7),
(34, 'T-Shirts & Vests', 7),
(35, 'Pants & Leggings', 7),
(36, 'Sportswear', 7),
(37, 'Plus Size Clothing', 7),
(38, 'Socks & Hosiery', 7),
(39, 'Fragrance', 3),
(40, 'Skincare', 3),
(41, 'Hair Care', 3),
(42, 'Jewellery', 4),
(43, 'Eyes Care', 3),
(44, 'Lips', 3),
(45, 'Face Care', 3),
(46, 'Gift Sets', 3),
(47, 'Scarves & Headwear', 4),
(48, 'Multipacks', 4),
(49, 'Other Accessories', 4),
(50, 'Pumps', 6),
(51, 'Sneakers', 6),
(52, 'Sports Shoes', 6),
(53, 'Boots', 6),
(54, 'Comfort Shoes', 6),
(55, 'Slippers & Casual Shoes', 6),
(56, 'Formal Shoes', 2),
(57, 'Belts', 1),
(58, 'Multipacks', 1),
(59, 'Other Accessories', 1),
(60, 'Bags', 4),
(61, 'Cell Phone and Accessories', 14),
(62, 'Headphones', 14),
(63, 'Security and Surveillance', 14),
(64, 'Television and Video', 14),
(65, 'GPS and Navigation', 14),
(66, 'Home Audio', 14),
(67, 'Computer Components', 15),
(68, 'Computers and Tablets', 15),
(69, 'Laptop Accessories', 15),
(70, 'Printer and Monitors', 15),
(71, 'External Components', 15),
(72, 'Networking Products', 15),
(73, 'Medical Supplies and Equipment', 16),
(74, 'Oral Care', 16),
(75, 'Vision Care', 16),
(76, 'Vitamins and Dietary Supplements', 16),
(77, 'Baby and Child Care', 17),
(78, 'Household Supplies', 17),
(79, 'Stationery and Gift Wrapping Supplies', 17)
ON CONFLICT ("ecat_id") DO NOTHING;

INSERT INTO "tbl_faq" ("faq_id", "faq_title", "faq_content")
VALUES (1, 'How to find an item?', '<h3 class="checkout-complete-box font-bold txt16">How to find an item?</h3>')
ON CONFLICT ("faq_id") DO NOTHING;

INSERT INTO "tbl_features" ("id", "icon", "title", "link", "order_no")
VALUES (1, 'fa-truck', 'Free Shipping', '#', 1),
(2, 'fa-shield', 'Secure Payment', '#', 2),
(3, 'fa-undo', 'Easy Returns', '#', 3),
(4, 'fa-headphones', '24/7 Support', '#', 4)
ON CONFLICT ("id") DO NOTHING;

INSERT INTO "tbl_home_tabs" ("id", "tab_name", "tab_icon", "filter_type", "display_order", "is_active")
VALUES (1, 'For You', 'fa-magic', 'recommendation', 1, 1),
(2, 'Free Shipping', 'fa-truck', 'free_shipping', 2, 1),
(3, 'Top Sale', 'fa-fire', 'top_sale', 3, 1),
(4, 'Official', 'fa-check-circle', 'official', 4, 1),
(5, 'Voucher King', 'fa-ticket', 'max_vouchered', 5, 1)
ON CONFLICT ("id") DO NOTHING;

INSERT INTO "tbl_kyc_verifications" ("kyc_id", "cust_id", "nid_number", "nid_front", "nid_back", "face_capture", "gps_location", "status", "created_at")
VALUES (1, 24, '', 'nid_front_24_1769328666.jpg', 'nid_back_24_1769327731.jpg', 'live_face_24_1769328696.jpg', NULL, 'pending', '2026-01-25 08:21:01')
ON CONFLICT ("kyc_id") DO NOTHING;

INSERT INTO "tbl_language" ("lang_id", "lang_name", "lang_value")
VALUES (1, 'Currency', 'BDT  '),
(2, 'Search Product', 'Search Product'),
(3, 'Search', 'Search'),
(4, 'Submit', 'Submit'),
(5, 'Update', 'Update'),
(6, 'Read More', 'Read More'),
(7, 'Serial', 'Serial'),
(8, 'Photo', 'Photo'),
(9, 'Login', 'Login'),
(10, 'Customer Login', 'Customer Login'),
(11, 'Click here to login', 'Click here to login'),
(12, 'Back to Login Page', 'Back to Login Page'),
(13, 'Logged in as', 'Logged in as'),
(14, 'Logout', 'Logout'),
(15, 'Register', 'Register'),
(16, 'Customer Registration', 'Customer Registration'),
(17, 'Registration Successful', 'Registration Successful'),
(18, 'Cart', 'Cart'),
(19, 'View Cart', 'View Cart'),
(20, 'Update Cart', 'Update Cart'),
(21, 'Back to Cart', 'Back to Cart'),
(22, 'Checkout', 'Checkout'),
(23, 'Proceed to Checkout', 'Proceed to Checkout'),
(24, 'Orders', 'Orders'),
(25, 'Order History', 'Order History'),
(26, 'Order Details', 'Order Details'),
(27, 'Payment Date and Time', 'Payment Date and Time'),
(28, 'Transaction ID', 'Transaction ID'),
(29, 'Paid Amount', 'Paid Amount'),
(30, 'Payment Status', 'Payment Status'),
(31, 'Payment Method', 'Payment Method'),
(32, 'Payment ID', 'Payment ID'),
(33, 'Payment Section', 'Payment Section'),
(34, 'Select Payment Method', 'Select Payment Method'),
(35, 'Select a Method', 'Select a Method'),
(36, 'PayPal', 'PayPal'),
(37, 'Stripe', 'Stripe'),
(38, 'Bank Deposit', 'Bank Deposit'),
(39, 'Card Number', 'Card Number'),
(40, 'CVV', 'CVV'),
(41, 'Month', 'Month'),
(42, 'Year', 'Year'),
(43, 'Send to this Details', 'Send to this Details'),
(44, 'Transaction Information', 'Transaction Information'),
(45, 'Include transaction id and other information correctly', 'Include transaction id and other information correctly'),
(46, 'Pay Now', 'Pay Now'),
(47, 'Product Name', 'Product Name'),
(48, 'Product Details', 'Product Details'),
(49, 'Categories', 'Categories'),
(50, 'Category:', 'Category:'),
(51, 'All Products Under', 'All Products Under'),
(52, 'Select Size', 'Select Size'),
(53, 'Select Color', 'Select Color'),
(54, 'Product Price', 'Product Price'),
(55, 'Quantity', 'Quantity'),
(56, 'Out of Stock', 'Out of Stock'),
(57, 'Share This', 'Share This'),
(58, 'Share This Product', 'Share This Product'),
(59, 'Product Description', 'Product Description'),
(60, 'Features', 'Features'),
(61, 'Conditions', 'Conditions'),
(62, 'Return Policy', 'Return Policy'),
(63, 'Reviews', 'Reviews'),
(64, 'Review', 'Review'),
(65, 'Give a Review', 'Give a Review'),
(66, 'Write your comment (Optional)', 'Write your comment (Optional)'),
(67, 'Submit Review', 'Submit Review'),
(68, 'You already have given a rating!', 'You already have given a rating!'),
(69, 'You must have to login to give a review', 'You must have to login to give a review'),
(70, 'No description found', 'No description found'),
(71, 'No feature found', 'No feature found'),
(72, 'No condition found', 'No condition found'),
(73, 'No return policy found', 'No return policy found'),
(74, 'Review not found', 'Review not found'),
(75, 'Customer Name', 'Customer Name'),
(76, 'Comment', 'Comment'),
(77, 'Comments', 'Comments'),
(78, 'Rating', 'Rating'),
(79, 'Previous', 'Previous'),
(80, 'Next', 'Next'),
(81, 'Sub Total', 'Sub Total'),
(82, 'Total', 'Total'),
(83, 'Action', 'Action'),
(84, 'Shipping Cost', 'Shipping Cost'),
(85, 'Continue Shopping', 'Continue Shopping'),
(86, 'Update Billing Address', 'Update Billing Address'),
(87, 'Update Shipping Address', 'Update Shipping Address'),
(88, 'Update Billing and Shipping Info', 'Update Billing and Shipping Info'),
(89, 'Dashboard', 'Dashboard'),
(90, 'Welcome to the Dashboard', 'Welcome to the Dashboard'),
(91, 'Back to Dashboard', 'Back to Dashboard'),
(92, 'Subscribe', 'Subscribe'),
(93, 'Subscribe To Our Newsletter', 'Subscribe To Our Newsletter'),
(94, 'Email Address', 'Email Address'),
(95, 'Enter Your Email Address', 'Enter Your Email Address'),
(96, 'Password', 'Password'),
(97, 'Forget Password', 'Forget Password'),
(98, 'Retype Password', 'Retype Password'),
(99, 'Update Password', 'Update Password'),
(100, 'New Password', 'New Password'),
(101, 'Retype New Password', 'Retype New Password'),
(102, 'Full Name', 'Full Name'),
(103, 'Company Name', 'Company Name'),
(104, 'Phone Number', 'Phone Number'),
(105, 'Address', 'Address'),
(106, 'Country', 'Country'),
(107, 'City', 'City'),
(108, 'State', 'State'),
(109, 'Zip Code', 'Zip Code'),
(110, 'About Us', 'About Us'),
(111, 'Featured Posts', 'Featured Posts'),
(112, 'Popular Posts', 'Popular Posts'),
(113, 'Recent Posts', 'Recent Posts'),
(114, 'Contact Information', 'Contact Information'),
(115, 'Contact Form', 'Contact Form'),
(116, 'Our Office', 'Our Office'),
(117, 'Update Profile', 'Update Profile'),
(118, 'Send Message', 'Send Message'),
(119, 'Message', 'Message'),
(120, 'Find Us On Map', 'Find Us On Map'),
(121, 'Congratulation! Payment is successful.', 'Congratulation! Payment is successful.'),
(122, 'Billing and Shipping Information is updated successfully.', 'Billing and Shipping Information is updated successfully.'),
(123, 'Customer Name can not be empty.', 'Customer Name can not be empty.'),
(124, 'Phone Number can not be empty.', 'Phone Number can not be empty.'),
(125, 'Address can not be empty.', 'Address can not be empty.'),
(126, 'You must have to select a country.', 'You must have to select a country.'),
(127, 'City can not be empty.', 'City can not be empty.'),
(128, 'State can not be empty.', 'State can not be empty.'),
(129, 'Zip Code can not be empty.', 'Zip Code can not be empty.'),
(130, 'Profile Information is updated successfully.', 'Profile Information is updated successfully.'),
(131, 'Email Address can not be empty', 'Email Address can not be empty'),
(132, 'Email and/or Password can not be empty.', 'Email and/or Password can not be empty.'),
(133, 'Email Address does not match.', 'Email Address does not match.'),
(134, 'Email address must be valid.', 'Email address must be valid.'),
(135, 'You email address is not found in our system.', 'You email address is not found in our system.'),
(136, 'Please check your email and confirm your subscription.', 'Please check your email and confirm your subscription.'),
(137, 'Your email is verified successfully. You can now login to our website.', 'Your email is verified successfully. You can now login to our website.'),
(138, 'Password can not be empty.', 'Password can not be empty.'),
(139, 'Passwords do not match.', 'Passwords do not match.'),
(140, 'Please enter new and retype passwords.', 'Please enter new and retype passwords.'),
(141, 'Password is updated successfully.', 'Password is updated successfully.'),
(142, 'To reset your password, please click on the link below.', 'To reset your password, please click on the link below.'),
(143, 'PASSWORD RESET REQUEST - YOUR WEBSITE.COM', 'PASSWORD RESET REQUEST - YOUR WEBSITE.COM'),
(144, 'The password reset email time (24 hours) has expired. Please again try to reset your password.', 'The password reset email time (24 hours) has expired. Please again try to reset your password.'),
(145, 'A confirmation link is sent to your email address. You will get the password reset information in there.', 'A confirmation link is sent to your email address. You will get the password reset information in there.'),
(146, 'Password is reset successfully. You can now login.', 'Password is reset successfully. You can now login.'),
(147, 'Email Address Already Exists', 'Email Address Already Exists.'),
(148, 'Sorry! Your account is inactive. Please contact to the administrator.', 'Sorry! Your account is inactive. Please contact to the administrator.'),
(149, 'Change Password', 'Change Password'),
(150, 'Registration Email Confirmation for YOUR WEBSITE', 'Registration Email Confirmation for YOUR WEBSITE.'),
(151, 'Thank you for your registration! Your account has been created. To active your account click on the link below:', 'Thank you for your registration! Your account has been created. To active your account click on the link below:'),
(152, 'Your registration is completed. Please check your email address to follow the process to confirm your registration.', 'Your registration is completed. Please check your email address to follow the process to confirm your registration.'),
(153, 'No Product Found', 'No Product Found'),
(154, 'Add to Cart', 'Add to Cart'),
(155, 'Related Products', 'Related Products'),
(156, 'See all related products from below', 'See all the related products from below'),
(157, 'Size', 'Size'),
(158, 'Color', 'Color'),
(159, 'Price', 'Price'),
(160, 'Please login as customer to checkout', 'Please login as customer to checkout'),
(161, 'Billing Address', 'Billing Address'),
(162, 'Shipping Address', 'Shipping Address'),
(163, 'Rating is Submitted Successfully!', 'Rating is Submitted Successfully!'),
(1, 'Currency', 'BDT  '),
(2, 'Search Product', 'Search Product'),
(3, 'Search', 'Search'),
(4, 'Submit', 'Submit'),
(5, 'Update', 'Update'),
(6, 'Read More', 'Read More'),
(7, 'Serial', 'Serial'),
(8, 'Photo', 'Photo'),
(9, 'Login', 'Login'),
(10, 'Customer Login', 'Customer Login'),
(11, 'Click here to login', 'Click here to login'),
(12, 'Back to Login Page', 'Back to Login Page'),
(13, 'Logged in as', 'Logged in as'),
(14, 'Logout', 'Logout'),
(15, 'Register', 'Register'),
(16, 'Customer Registration', 'Customer Registration'),
(17, 'Registration Successful', 'Registration Successful'),
(18, 'Cart', 'Cart'),
(19, 'View Cart', 'View Cart'),
(20, 'Update Cart', 'Update Cart'),
(21, 'Back to Cart', 'Back to Cart'),
(22, 'Checkout', 'Checkout'),
(23, 'Proceed to Checkout', 'Proceed to Checkout'),
(24, 'Orders', 'Orders'),
(25, 'Order History', 'Order History'),
(26, 'Order Details', 'Order Details'),
(27, 'Payment Date and Time', 'Payment Date and Time'),
(28, 'Transaction ID', 'Transaction ID'),
(29, 'Paid Amount', 'Paid Amount'),
(30, 'Payment Status', 'Payment Status'),
(31, 'Payment Method', 'Payment Method'),
(32, 'Payment ID', 'Payment ID'),
(33, 'Payment Section', 'Payment Section'),
(34, 'Select Payment Method', 'Select Payment Method'),
(35, 'Select a Method', 'Select a Method'),
(36, 'PayPal', 'PayPal'),
(37, 'Stripe', 'Stripe'),
(38, 'Bank Deposit', 'Bank Deposit'),
(39, 'Card Number', 'Card Number'),
(40, 'CVV', 'CVV'),
(41, 'Month', 'Month'),
(42, 'Year', 'Year'),
(43, 'Send to this Details', 'Send to this Details'),
(44, 'Transaction Information', 'Transaction Information'),
(45, 'Include transaction id and other information correctly', 'Include transaction id and other information correctly'),
(46, 'Pay Now', 'Pay Now'),
(47, 'Product Name', 'Product Name'),
(48, 'Product Details', 'Product Details'),
(49, 'Categories', 'Categories'),
(50, 'Category:', 'Category:'),
(51, 'All Products Under', 'All Products Under'),
(52, 'Select Size', 'Select Size'),
(53, 'Select Color', 'Select Color'),
(54, 'Product Price', 'Product Price'),
(55, 'Quantity', 'Quantity'),
(56, 'Out of Stock', 'Out of Stock'),
(57, 'Share This', 'Share This'),
(58, 'Share This Product', 'Share This Product'),
(59, 'Product Description', 'Product Description'),
(60, 'Features', 'Features'),
(61, 'Conditions', 'Conditions'),
(62, 'Return Policy', 'Return Policy'),
(63, 'Reviews', 'Reviews'),
(64, 'Review', 'Review'),
(65, 'Give a Review', 'Give a Review'),
(66, 'Write your comment (Optional)', 'Write your comment (Optional)'),
(67, 'Submit Review', 'Submit Review'),
(68, 'You already have given a rating!', 'You already have given a rating!'),
(69, 'You must have to login to give a review', 'You must have to login to give a review'),
(70, 'No description found', 'No description found'),
(71, 'No feature found', 'No feature found'),
(72, 'No condition found', 'No condition found'),
(73, 'No return policy found', 'No return policy found'),
(74, 'Review not found', 'Review not found'),
(75, 'Customer Name', 'Customer Name'),
(76, 'Comment', 'Comment'),
(77, 'Comments', 'Comments'),
(78, 'Rating', 'Rating'),
(79, 'Previous', 'Previous'),
(80, 'Next', 'Next'),
(81, 'Sub Total', 'Sub Total'),
(82, 'Total', 'Total'),
(83, 'Action', 'Action'),
(84, 'Shipping Cost', 'Shipping Cost'),
(85, 'Continue Shopping', 'Continue Shopping'),
(86, 'Update Billing Address', 'Update Billing Address'),
(87, 'Update Shipping Address', 'Update Shipping Address'),
(88, 'Update Billing and Shipping Info', 'Update Billing and Shipping Info'),
(89, 'Dashboard', 'Dashboard'),
(90, 'Welcome to the Dashboard', 'Welcome to the Dashboard'),
(91, 'Back to Dashboard', 'Back to Dashboard'),
(92, 'Subscribe', 'Subscribe'),
(93, 'Subscribe To Our Newsletter', 'Subscribe To Our Newsletter'),
(94, 'Email Address', 'Email Address'),
(95, 'Enter Your Email Address', 'Enter Your Email Address'),
(96, 'Password', 'Password'),
(97, 'Forget Password', 'Forget Password'),
(98, 'Retype Password', 'Retype Password'),
(99, 'Update Password', 'Update Password'),
(100, 'New Password', 'New Password'),
(101, 'Retype New Password', 'Retype New Password'),
(102, 'Full Name', 'Full Name'),
(103, 'Company Name', 'Company Name'),
(104, 'Phone Number', 'Phone Number'),
(105, 'Address', 'Address'),
(106, 'Country', 'Country'),
(107, 'City', 'City'),
(108, 'State', 'State'),
(109, 'Zip Code', 'Zip Code'),
(110, 'About Us', 'About Us'),
(111, 'Featured Posts', 'Featured Posts'),
(112, 'Popular Posts', 'Popular Posts'),
(113, 'Recent Posts', 'Recent Posts'),
(114, 'Contact Information', 'Contact Information'),
(115, 'Contact Form', 'Contact Form'),
(116, 'Our Office', 'Our Office'),
(117, 'Update Profile', 'Update Profile'),
(118, 'Send Message', 'Send Message'),
(119, 'Message', 'Message'),
(120, 'Find Us On Map', 'Find Us On Map'),
(121, 'Congratulation! Payment is successful.', 'Congratulation! Payment is successful.'),
(122, 'Billing and Shipping Information is updated successfully.', 'Billing and Shipping Information is updated successfully.'),
(123, 'Customer Name can not be empty.', 'Customer Name can not be empty.'),
(124, 'Phone Number can not be empty.', 'Phone Number can not be empty.'),
(125, 'Address can not be empty.', 'Address can not be empty.'),
(126, 'You must have to select a country.', 'You must have to select a country.'),
(127, 'City can not be empty.', 'City can not be empty.'),
(128, 'State can not be empty.', 'State can not be empty.'),
(129, 'Zip Code can not be empty.', 'Zip Code can not be empty.'),
(130, 'Profile Information is updated successfully.', 'Profile Information is updated successfully.'),
(131, 'Email Address can not be empty', 'Email Address can not be empty'),
(132, 'Email and/or Password can not be empty.', 'Email and/or Password can not be empty.'),
(133, 'Email Address does not match.', 'Email Address does not match.'),
(134, 'Email address must be valid.', 'Email address must be valid.'),
(135, 'You email address is not found in our system.', 'You email address is not found in our system.'),
(136, 'Please check your email and confirm your subscription.', 'Please check your email and confirm your subscription.'),
(137, 'Your email is verified successfully. You can now login to our website.', 'Your email is verified successfully. You can now login to our website.'),
(138, 'Password can not be empty.', 'Password can not be empty.'),
(139, 'Passwords do not match.', 'Passwords do not match.'),
(140, 'Please enter new and retype passwords.', 'Please enter new and retype passwords.'),
(141, 'Password is updated successfully.', 'Password is updated successfully.'),
(142, 'To reset your password, please click on the link below.', 'To reset your password, please click on the link below.'),
(143, 'PASSWORD RESET REQUEST - YOUR WEBSITE.COM', 'PASSWORD RESET REQUEST - YOUR WEBSITE.COM'),
(144, 'The password reset email time (24 hours) has expired. Please again try to reset your password.', 'The password reset email time (24 hours) has expired. Please again try to reset your password.'),
(145, 'A confirmation link is sent to your email address. You will get the password reset information in there.', 'A confirmation link is sent to your email address. You will get the password reset information in there.'),
(146, 'Password is reset successfully. You can now login.', 'Password is reset successfully. You can now login.'),
(147, 'Email Address Already Exists', 'Email Address Already Exists.'),
(148, 'Sorry! Your account is inactive. Please contact to the administrator.', 'Sorry! Your account is inactive. Please contact to the administrator.'),
(149, 'Change Password', 'Change Password'),
(150, 'Registration Email Confirmation for YOUR WEBSITE', 'Registration Email Confirmation for YOUR WEBSITE.'),
(151, 'Thank you for your registration! Your account has been created. To active your account click on the link below:', 'Thank you for your registration! Your account has been created. To active your account click on the link below:'),
(152, 'Your registration is completed. Please check your email address to follow the process to confirm your registration.', 'Your registration is completed. Please check your email address to follow the process to confirm your registration.'),
(153, 'No Product Found', 'No Product Found'),
(154, 'Add to Cart', 'Add to Cart'),
(155, 'Related Products', 'Related Products'),
(156, 'See all related products from below', 'See all the related products from below'),
(157, 'Size', 'Size'),
(158, 'Color', 'Color'),
(159, 'Price', 'Price'),
(160, 'Please login as customer to checkout', 'Please login as customer to checkout'),
(161, 'Billing Address', 'Billing Address'),
(162, 'Shipping Address', 'Shipping Address'),
(163, 'Rating is Submitted Successfully!', 'Rating is Submitted Successfully!')
ON CONFLICT ("lang_id") DO NOTHING;

INSERT INTO "tbl_mid_category" ("mcat_id", "mcat_name", "tcat_id")
VALUES (1, 'Men Accessories', 1),
(2, 'Men''s Shoes', 1),
(3, 'Beauty Products', 2),
(4, 'Accessories', 2),
(6, 'Shoes', 2),
(7, 'Clothing', 2),
(8, 'Bottoms', 1),
(9, 'T-shirts & Shirts', 1),
(10, 'Clothing', 3),
(11, 'Shoes', 3),
(12, 'Accessories', 3),
(14, 'Electronic Items', 4),
(15, 'Computers', 4),
(16, 'Health', 5),
(17, 'Household', 5),
(0, 'r', 1),
(1, 'Men Accessories', 1),
(2, 'Men''s Shoes', 1),
(3, 'Beauty Products', 2),
(4, 'Accessories', 2),
(6, 'Shoes', 2),
(7, 'Clothing', 2),
(8, 'Bottoms', 1),
(9, 'T-shirts & Shirts', 1),
(10, 'Clothing', 3),
(11, 'Shoes', 3),
(12, 'Accessories', 3),
(14, 'Electronic Items', 4),
(15, 'Computers', 4),
(16, 'Health', 5),
(17, 'Household', 5),
(0, 'r', 1)
ON CONFLICT ("mcat_id") DO NOTHING;

INSERT INTO "tbl_order" ("id", "cust_id", "product_id", "product_name", "size", "color", "quantity", "unit_price", "payment_id", "coupon_code", "coupon_discount")
VALUES (1, 0, 95, 'Bose QuietComfort 45 Bluetooth Wireless Headphones', 'One Size for All', 'Black', '1', '279', '1749215216', NULL, NULL),
(2, 0, 84, 'Loose-fit One-Shoulder Cutout Rib Knit Maxi Dress', 'S', 'Black', '1', '39', '1749241730', NULL, NULL),
(3, 0, 102, 'Women''s Plus-Size Shirt Dress with Gold Hardware', '14 Plus', 'Black', '1', '169', '1749241730', NULL, NULL),
(4, 0, 86, 'Amazfit GTS 3 Smart Watch for Android iPhone', 'Free Size', 'Black', '1', '179', 'COD-1749286625', NULL, NULL),
(5, 0, 84, 'Loose-fit One-Shoulder Cutout Rib Knit Maxi Dress', 'S', 'Black', '1', '39', 'COD-1749287800', NULL, NULL),
(6, 0, 102, 'Women''s Plus-Size Shirt Dress with Gold Hardware', '14 Plus', 'Black', '1', '169', 'COD-1749287800', NULL, NULL),
(7, 0, 85, 'Men''s Soft Classic Sneaker', '38', 'Dark Clay', '1', '91', 'COD-1749292105', NULL, NULL),
(8, 0, 86, 'Amazfit GTS 3 Smart Watch for Android iPhone', 'Free Size', 'Black', '1', '179', 'COD-1749292416', NULL, NULL),
(9, 0, 86, 'Amazfit GTS 3 Smart Watch for Android iPhone', 'Free Size', 'Black', '1', '179', 'COD-1749301633', NULL, NULL),
(10, 0, 102, 'Women''s Plus-Size Shirt Dress with Gold Hardware', '14 Plus', 'Black', '1', '169', 'SSL_68443ede5636a', NULL, NULL),
(11, 0, 102, 'Women''s Plus-Size Shirt Dress with Gold Hardware', '14 Plus', 'Black', '1', '169', 'SSL_6844451b41668', NULL, NULL),
(12, 0, 102, 'Women''s Plus-Size Shirt Dress with Gold Hardware', '14 Plus', 'Black', '1', '169', 'SSL_6844456e0663f', NULL, NULL),
(13, 0, 102, 'Women''s Plus-Size Shirt Dress with Gold Hardware', '14 Plus', 'Black', '1', '169', 'COD-1749305069', NULL, NULL),
(14, 0, 86, 'Amazfit GTS 3 Smart Watch for Android iPhone', 'Free Size', 'Black', '1', '179', 'SSL_68444786546cc', NULL, NULL),
(15, 0, 86, 'Amazfit GTS 3 Smart Watch for Android iPhone', 'Free Size', 'Black', '1', '179', 'SSL_684449677d3c8', NULL, NULL),
(16, 0, 86, 'Amazfit GTS 3 Smart Watch for Android iPhone', 'Free Size', 'Black', '6', '179', 'SSL_68444b5e1de0f', NULL, NULL),
(17, 0, 97, 'Women''s Tea Length Dress with Rosette Detail (Petite & Regular)', '31', 'Navy', '1', '67', 'SSL_68444c281e76a', NULL, NULL),
(18, 0, 95, 'Bose QuietComfort 45 Bluetooth Wireless Headphones', 'One Size for All', 'Black', '1', '279', 'SSL_68444d176ca51', NULL, NULL),
(19, 0, 95, 'Bose QuietComfort 45 Bluetooth Wireless Headphones', 'One Size for All', 'Black', '1', '279', 'SSL_68444f1d057d7', NULL, NULL),
(20, 0, 95, 'Bose QuietComfort 45 Bluetooth Wireless Headphones', 'One Size for All', 'Black', '1', '279', 'SSL_68444fbb54448', NULL, NULL),
(21, 0, 95, 'Bose QuietComfort 45 Bluetooth Wireless Headphones', 'One Size for All', 'Black', '1', '279', 'SSL_684451962084a', NULL, NULL),
(22, 0, 98, 'Women''s Fuzzy Fleece Lapel Open Front Long Cardigan Coat', 'L', 'Green', '1', '43', 'SSL_68445a2067b6c', NULL, NULL),
(23, 0, 98, 'Women''s Fuzzy Fleece Lapel Open Front Long Cardigan Coat', 'L', 'Green', '1', '43', 'SSL_6844661518150', NULL, NULL),
(24, 0, 98, 'Women''s Fuzzy Fleece Lapel Open Front Long Cardigan Coat', 'L', 'Green', '1', '43', 'COD-1749313528', NULL, NULL),
(25, 0, 86, 'Amazfit GTS 3 Smart Watch for Android iPhone', 'Free Size', 'Black', '1', '179', 'COD-1749330843', NULL, NULL),
(26, 0, 86, 'Amazfit GTS 3 Smart Watch for Android iPhone', 'Free Size', 'Black', '1', '179', 'COD-1749330905', NULL, NULL),
(27, 0, 84, 'Loose-fit One-Shoulder Cutout Rib Knit Maxi Dress', 'S', 'Black', '1', '39', 'SSL_684597f7bf0f5', NULL, NULL),
(28, 0, 84, 'Loose-fit One-Shoulder Cutout Rib Knit Maxi Dress', 'S', 'Black', '1', '39', 'SSL_68459837d961e', NULL, NULL),
(29, 0, 84, 'Loose-fit One-Shoulder Cutout Rib Knit Maxi Dress', 'S', 'Black', '1', '39', 'SSL_68459bcebd19d', NULL, NULL),
(30, 0, 84, 'Loose-fit One-Shoulder Cutout Rib Knit Maxi Dress', 'S', 'Black', '1', '39', 'COD-1749392363', NULL, NULL),
(31, 0, 84, 'Loose-fit One-Shoulder Cutout Rib Knit Maxi Dress', 'S', 'Black', '1', '39', 'COD-1749393685', NULL, NULL),
(32, 0, 84, 'Loose-fit One-Shoulder Cutout Rib Knit Maxi Dress', 'S', 'Black', '1', '39', 'COD-1749394029', NULL, NULL),
(33, 0, 84, 'Loose-fit One-Shoulder Cutout Rib Knit Maxi Dress', 'S', 'Black', '1', '39', 'COD-1749394175', NULL, NULL),
(34, 0, 84, 'Loose-fit One-Shoulder Cutout Rib Knit Maxi Dress', 'S', 'Black', '1', '39', 'COD-1749394367', NULL, NULL),
(35, 0, 86, 'Amazfit GTS 3 Smart Watch for Android iPhone', 'Free Size', 'Black', '1', '179', 'COD-1749399833-0', NULL, NULL),
(36, 0, 95, 'Bose QuietComfort 45 Bluetooth Wireless Headphones', 'One Size for All', 'Black', '1', '279', 'COD-1749399916-0', NULL, NULL),
(37, 0, 97, 'Women''s Tea Length Dress with Rosette Detail (Petite & Regular)', '31', 'Navy', '1', '67', 'SSL_6845c65f37b96', NULL, NULL),
(38, 0, 84, 'Loose-fit One-Shoulder Cutout Rib Knit Maxi Dress', 'S', 'Black', '1', '39', 'SSL_6845c65f37b96', NULL, NULL),
(39, 0, 95, 'Bose QuietComfort 45 Bluetooth Wireless Headphones', 'One Size for All', 'Black', '1', '279', 'SSL_6845c78c379f1', NULL, NULL),
(40, 0, 84, 'Loose-fit One-Shoulder Cutout Rib Knit Maxi Dress', 'S', 'Black', '1', '39', 'COD-1749409655-0', NULL, NULL),
(41, 0, 95, 'Bose QuietComfort 45 Bluetooth Wireless Headphones', 'One Size for All', 'Black', '1', '279', 'SSL_6845ea105cde0', NULL, NULL),
(42, 0, 95, 'Bose QuietComfort 45 Bluetooth Wireless Headphones', 'One Size for All', 'Black', '1', '279', 'SSL_6845ea94cadae', NULL, NULL),
(43, 0, 95, 'Bose QuietComfort 45 Bluetooth Wireless Headphones', 'One Size for All', 'Black', '1', '279', 'SSL_6845f64b56201', NULL, NULL),
(44, 0, 84, 'Loose-fit One-Shoulder Cutout Rib Knit Maxi Dress', 'S', 'Black', '1', '39', 'SSL_6845fad1a64de', NULL, NULL),
(45, 0, 84, 'Loose-fit One-Shoulder Cutout Rib Knit Maxi Dress', 'S', 'Black', '1', '39', 'SSL_684606d21f64c', NULL, NULL),
(46, 0, 86, 'Amazfit GTS 3 Smart Watch for Android iPhone', 'Free Size', 'Black', '1', '179', 'SSL_684607e87a203', NULL, NULL),
(47, 0, 86, 'Amazfit GTS 3 Smart Watch for Android iPhone', 'Free Size', 'Black', '1', '179', 'SSL_68467102cb6a7', NULL, NULL),
(48, 0, 95, 'Bose QuietComfort 45 Bluetooth Wireless Headphones', 'One Size for All', 'Black', '1', '279', 'SSL_6846909e64339', NULL, NULL),
(49, 0, 86, 'Amazfit GTS 3 Smart Watch for Android iPhone', 'Free Size', 'Black', '1', '179', 'SSL_684708b59cecd', NULL, NULL),
(50, 0, 95, 'Bose QuietComfort 45 Bluetooth Wireless Headphones', 'One Size for All', 'Black', '1', '279', 'SSL_6848d3b9a569b', NULL, NULL),
(51, 0, 102, 'Women''s Plus-Size Shirt Dress with Gold Hardware', '14 Plus', 'Black', '1', '169', 'SSL_6848d96da530d', NULL, NULL),
(52, 0, 97, 'Women''s Tea Length Dress with Rosette Detail (Petite & Regular)', '31', 'Navy', '1', '67', 'SSL_6848d9f9754a7', NULL, NULL),
(53, 0, 86, 'Amazfit GTS 3 Smart Watch for Android iPhone', 'Free Size', 'Black', '1', '179', 'SSL_6848daa7dca39', NULL, NULL),
(54, 0, 95, 'Bose QuietComfort 45 Bluetooth Wireless Headphones', 'One Size for All', 'Black', '1', '279', 'SSL_6848db0032183', NULL, NULL),
(55, 0, 95, 'Bose QuietComfort 45 Bluetooth Wireless Headphones', 'One Size for All', 'Black', '1', '279', 'SSL_6848dc389c1ae', NULL, NULL),
(56, 0, 102, 'Women''s Plus-Size Shirt Dress with Gold Hardware', '14 Plus', 'Black', '1', '169', 'SSL_6848ecdfa2def', NULL, NULL),
(57, 0, 83, 'Men''s Ultra Cotton T-Shirt, Multipack', 'XS', 'Red', '1', '19', 'COD-1749609836-0', NULL, NULL),
(78, 21, 90, 'Women''s Thin Cotton Zip Up Hoodie Jacket', 'XS', 'Black', '1', '32', 'SSL_6918396315102', NULL, NULL),
(83, 21, 94, 'WD 5TB Elements Portable External Hard Drive HDD', '5T', 'Black', '1', '149', 'SSL_69184a7d73ad4', NULL, NULL),
(84, 21, 98, 'Women''s Fuzzy Fleece Lapel Open Front Long Cardigan Coat', 'L', 'Green', '1', '43', 'SSL_69184ae0aaa50', NULL, NULL),
(85, 21, 94, 'WD 5TB Elements Portable External Hard Drive HDD', '5T', 'Black', '1', '149', 'SSL_69184ae0aaa50', NULL, NULL),
(86, 21, 94, 'WD 5TB Elements Portable External Hard Drive HDD', '5T', 'Black', '1', '149', 'SSL_69184bc3574ea', NULL, NULL),
(87, 21, 86, 'Amazfit GTS 3 Smart Watch for Android iPhone', 'Free Size', 'Black', '1', '179', 'SSL_69184c769fc50', NULL, NULL),
(88, 21, 93, 'Gold Plated Leopard Print Crystal Big Round Hoop Earrings', 'One Size for All', 'Gold', '1', '25', 'SSL_69184c98314da', NULL, NULL),
(89, 21, 83, 'Men''s Ultra Cotton T-Shirt, Multipack', 'XS', 'Red', '1', '19', 'SSL_69184d1e5a34d', NULL, NULL),
(90, 21, 84, 'Loose-fit One-Shoulder Cutout Rib Knit Maxi Dress', 'S', 'Black', '1', '39', 'SSL_691850e745eeb', NULL, NULL),
(92, 21, 97, 'Women''s Tea Length Dress with Rosette Detail (Petite & Regular)', '31', 'Navy', '1', '67', 'SSL_691851e733f52', NULL, NULL),
(93, 21, 97, 'Women''s Tea Length Dress with Rosette Detail (Petite & Regular)', '31', 'Navy', '1', '67', 'SSL_6918523f4f85c', NULL, NULL),
(94, 21, 98, 'Women''s Fuzzy Fleece Lapel Open Front Long Cardigan Coat', 'L', 'Green', '1', '43', 'SSL_6918540650c1d', NULL, NULL),
(95, 21, 98, 'Women''s Fuzzy Fleece Lapel Open Front Long Cardigan Coat', 'L', 'Green', '1', '43', 'SSL_6918540e617f1', NULL, NULL),
(96, 21, 98, 'Women''s Fuzzy Fleece Lapel Open Front Long Cardigan Coat', 'L', 'Green', '1', '43', 'SSL_6918541dc7c00', NULL, NULL),
(97, 21, 98, 'Women''s Fuzzy Fleece Lapel Open Front Long Cardigan Coat', 'L', 'Green', '1', '43', 'SSL_691854514b8c6', NULL, NULL),
(98, 21, 91, 'Women''s Oversized Fleece Hoodie', 'S', 'Olive', '1', '56', 'SSL_6918562e7806e', NULL, NULL),
(99, 21, 102, 'Women''s Plus-Size Shirt Dress with Gold Hardware', '14 Plus', 'Black', '1', '169', 'SSL_691896efbcc46', NULL, NULL),
(101, 21, 95, 'Bose QuietComfort 45 Bluetooth Wireless Headphones', 'One Size for All', 'Black', '1', '279', 'SSL_691897e3982bd', NULL, NULL),
(104, 23, 95, 'Bose QuietComfort 45 Bluetooth Wireless Headphones', 'One Size for All', 'Black', '24', '279', 'SSL_695d4ff268c81', NULL, NULL),
(106, 23, 99, 'Oculus Quest 2 - Advanced All-In-One Virtual Reality Headset', '256 GB', 'White', '1', '495.00', 'SSL_6961518ea8a29', NULL, NULL),
(107, 23, 87, 'Truck Boys Pajamas Toddler Sleepwear Clothes', '12 Months', 'Blue', '1', '37', 'SSL_696153d65e40b', NULL, NULL),
(110, 23, 94, 'WD 5TB Elements Portable External Hard Drive HDD', '5T', 'Black', '1', '149', 'SSL_696def80526b9', NULL, NULL),
(111, 23, 94, 'WD 5TB Elements Portable External Hard Drive HDD', '5T', 'Black', '1', '149.00', 'SSL_696df011cfab3', NULL, NULL),
(112, 23, 83, 'Men''s Ultra Cotton T-Shirt, Multipack', 'XS', 'Red', '1', '19', 'SSL_696df011cfab3', NULL, NULL),
(113, 23, 93, 'Gold Plated Leopard Print Crystal Big Round Hoop Earrings', 'One Size for All', 'Gold', '2', '25', 'COD-1768813009-233488', '', 0.00),
(114, 23, 83, 'Men''s Ultra Cotton T-Shirt, Multipack', 'XS', 'Red', '1', '19', 'SSL_696df4119e8d2', NULL, NULL),
(115, 23, 90, 'Women''s Thin Cotton Zip Up Hoodie Jacket', 'XS', 'Black', '1', '32', 'SSL_696fdbd7ac2c6', NULL, NULL),
(116, 24, 87, 'Truck Boys Pajamas Toddler Sleepwear Clothes', '12 Months', 'Blue', '1', '37.00', 'SSL_6970d26ee15bf', NULL, NULL),
(117, 24, 102, 'Women''s Plus-Size Shirt Dress with Gold Hardware', '14 Plus', 'Black', '2', '169', 'SSL_6970d26ee15bf', NULL, NULL),
(118, 24, 87, 'Truck Boys Pajamas Toddler Sleepwear Clothes', '12 Months', 'Blue', '1', '37.00', 'SSL_6971dcc461c1e', NULL, NULL),
(119, 24, 102, 'Women''s Plus-Size Shirt Dress with Gold Hardware', '14 Plus', 'Black', '2', '169.00', 'SSL_6971dcc461c1e', NULL, NULL),
(120, 24, 87, 'Truck Boys Pajamas Toddler Sleepwear Clothes', '12 Months', 'Blue', '1', '37.00', 'SSL_6971dd5de1748', NULL, NULL),
(121, 24, 102, 'Women''s Plus-Size Shirt Dress with Gold Hardware', '14 Plus', 'Black', '2', '169.00', 'SSL_6971dd5de1748', NULL, NULL),
(122, 24, 87, 'Truck Boys Pajamas Toddler Sleepwear Clothes', '12 Months', 'Blue', '1', '37.00', 'SSL_6971dd6e9e31a', NULL, NULL),
(123, 24, 102, 'Women''s Plus-Size Shirt Dress with Gold Hardware', '14 Plus', 'Black', '2', '169.00', 'SSL_6971dd6e9e31a', NULL, NULL),
(124, 24, 87, 'Truck Boys Pajamas Toddler Sleepwear Clothes', '12 Months', 'Blue', '1', '37.00', 'SSL_6971ddc16df98', NULL, NULL),
(125, 24, 102, 'Women''s Plus-Size Shirt Dress with Gold Hardware', '14 Plus', 'Black', '2', '169.00', 'SSL_6971ddc16df98', NULL, NULL),
(126, 24, 87, 'Truck Boys Pajamas Toddler Sleepwear Clothes', '12 Months', 'Blue', '1', '37.00', 'SSL_6971de675c938', NULL, NULL),
(127, 24, 102, 'Women''s Plus-Size Shirt Dress with Gold Hardware', '14 Plus', 'Black', '2', '169.00', 'SSL_6971de675c938', NULL, NULL),
(128, 24, 87, 'Truck Boys Pajamas Toddler Sleepwear Clothes', '12 Months', 'Blue', '1', '37.00', 'SSL_6971dea8351d0', NULL, NULL),
(129, 24, 102, 'Women''s Plus-Size Shirt Dress with Gold Hardware', '14 Plus', 'Black', '2', '169.00', 'SSL_6971dea8351d0', NULL, NULL),
(130, 24, 87, 'Truck Boys Pajamas Toddler Sleepwear Clothes', '12 Months', 'Blue', '1', '37.00', 'SSL_6971e0520728e', NULL, NULL),
(131, 24, 102, 'Women''s Plus-Size Shirt Dress with Gold Hardware', '14 Plus', 'Black', '2', '169.00', 'SSL_6971e0520728e', NULL, NULL),
(132, 24, 87, 'Truck Boys Pajamas Toddler Sleepwear Clothes', '12 Months', 'Blue', '1', '37.00', 'SSL_6971e1b30f688', NULL, NULL),
(133, 24, 102, 'Women''s Plus-Size Shirt Dress with Gold Hardware', '14 Plus', 'Black', '2', '169.00', 'SSL_6971e1b30f688', NULL, NULL),
(134, 24, 87, 'Truck Boys Pajamas Toddler Sleepwear Clothes', '12 Months', 'Blue', '1', '37.00', 'SSL_6971e1fc8d952', NULL, NULL),
(135, 24, 102, 'Women''s Plus-Size Shirt Dress with Gold Hardware', '14 Plus', 'Black', '2', '169.00', 'SSL_6971e1fc8d952', NULL, NULL),
(136, 24, 87, 'Truck Boys Pajamas Toddler Sleepwear Clothes', '12 Months', 'Blue', '1', '37.00', 'SSL_6971e7ebca8c4', NULL, NULL),
(137, 24, 102, 'Women''s Plus-Size Shirt Dress with Gold Hardware', '14 Plus', 'Black', '2', '169.00', 'SSL_6971e7ebca8c4', NULL, NULL)
ON CONFLICT ("id") DO NOTHING;

INSERT INTO "tbl_page" ("id", "about_title", "about_content", "about_banner", "about_meta_title", "about_meta_keyword", "about_meta_description", "faq_title", "faq_banner", "faq_meta_title", "faq_meta_keyword", "faq_meta_description", "blog_title", "blog_banner", "blog_meta_title", "blog_meta_keyword", "blog_meta_description", "contact_title", "contact_banner", "contact_meta_title", "contact_meta_keyword", "contact_meta_description", "pgallery_title", "pgallery_banner", "pgallery_meta_title", "pgallery_meta_keyword", "pgallery_meta_description", "vgallery_title", "vgallery_banner", "vgallery_meta_title", "vgallery_meta_keyword", "vgallery_meta_description")
VALUES (1, 'About Us', '<p style="border: 0px solid
VALUES (1, 'About Us', '<p>About Us content</p>', 'about-banner.jpg', 'About Us', 'about, shop', 'About our shop', 'FAQ', 'faq-banner.jpg', 'FAQ', 'faq, questions', 'Frequently asked questions', 'Blog', 'blog-banner.jpg', 'Blog', 'news, blog', 'Our latest blog posts', 'Contact Us', 'contact-banner.jpg', 'Contact Us', 'contact, support', 'Contact our support team', 'Photo Gallery', 'pgallery-banner.jpg', 'Photo Gallery', 'photos, gallery', 'Our photo gallery', 'Video Gallery', 'vgallery-banner.jpg', 'Video Gallery', 'videos, gallery', 'Our video gallery')
ON CONFLICT ("id") DO NOTHING;

INSERT INTO "tbl_payment" ("id", "customer_id", "customer_name", "customer_email", "payment_date", "txnid", "paid_amount", "card_number", "card_cvv", "card_month", "card_year", "bank_transaction_info", "payment_method", "payment_status", "shipping_status", "payment_id", "payment_note", "ssl_payment_method", "billing_name", "billing_cname", "billing_phone", "billing_country", "billing_address", "billing_city", "billing_state", "billing_zip", "shipping_name", "shipping_cname", "shipping_phone", "shipping_country", "shipping_address", "shipping_city", "shipping_state", "shipping_zip", "shipping_cost", "coupon_code", "coupon_discount", "coupon_id", "billing_email", "billing_street", "shipping_street", "shipping_email", "customer_note", "card_holder_name", "card_security_code", "card_expiry_month", "card_expiry_year")
VALUES (1, 0, 'Joy Saha', 's@1.com', '2025-06-10 19:41:35', 'SSL_6848ecdfa2def', 269, '', '', '', '', '', 'SSLCommerz', 'Completed', 'Pending', 'SSL_6848ecdfa2def', NULL, NULL, 'Joy Saha', NULL, '6666666666', '18', NULL, 'CUMILLA', 'state', '3700', 'ryrtyr', NULL, '665555555555', '18', NULL, 'Lakshmipur', 'sratr', 'Ght', 0.00, '', 0.00, NULL, NULL, 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Bangladesh', NULL, NULL, NULL, NULL, NULL, NULL),
(2, 0, 'Joy Saha', 's@1.com', '2025-06-10 19:43:56', '', 119, '', '', '', '', '', 'Cash on Delivery', 'Completed', 'Pending', 'COD-1749609836-0', '', NULL, 'Joy Saha', NULL, '6666666666', '18', NULL, 'CUMILLA', 'state', '3700', 'ryrtyr', NULL, '665555555555', '18', NULL, 'Lakshmipur', 'sratr', 'Ght', 100.00, '', 0.00, NULL, 's@1.com', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Bangladesh', 's@1.com', NULL, NULL, NULL, NULL, NULL),
(3, 0, 'Joy Saha', 's@1.com', '2025-06-10 18:30:32', 'SSL_6848dc389c1ae', 379, '', '', '', '', '', 'SSLCommerz', 'Completed', 'Completed', 'SSL_6848dc389c1ae', NULL, NULL, 'Joy Saha', NULL, '6666666666', '18', NULL, 'CUMILLA', 'state', '3700', 'ryrtyr', NULL, '665555555555', '18', NULL, 'Lakshmipur', 'sratr', 'Ght', 0.00, '', 0.00, NULL, NULL, 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Bangladesh', NULL, NULL, NULL, NULL, NULL, NULL),
(4, 0, 'Joy Saha', 's@1.com', '2025-06-10 18:25:20', 'SSL_6848db0032183', 379, '', '', '', '', '', 'SSLCommerz', 'Completed', 'Completed', 'SSL_6848db0032183', NULL, NULL, 'Joy Saha', NULL, '6666666666', '18', NULL, 'CUMILLA', 'state', '3700', 'ryrtyr', NULL, '665555555555', '18', NULL, 'Lakshmipur', 'sratr', 'Ght', 0.00, '', 0.00, NULL, NULL, 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Bangladesh', NULL, NULL, NULL, NULL, NULL, NULL),
(5, 0, 'Joy Saha', 's@1.com', '2025-06-10 18:23:51', 'SSL_6848daa7dca39', 279, '', '', '', '', '', 'SSLCommerz', 'Completed', 'Completed', 'SSL_6848daa7dca39', NULL, NULL, 'Joy Saha', NULL, '6666666666', '18', NULL, 'CUMILLA', 'state', '3700', 'ryrtyr', NULL, '665555555555', '18', NULL, 'Lakshmipur', 'sratr', 'Ght', 0.00, '', 0.00, NULL, NULL, 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Bangladesh', NULL, NULL, NULL, NULL, NULL, NULL),
(6, 0, 'Joy Saha', 's@1.com', '2025-06-10 18:20:57', 'SSL_6848d9f9754a7', 167, '', '', '', '', '', 'SSLCommerz', 'Completed', 'Completed', 'SSL_6848d9f9754a7', NULL, NULL, 'Joy Saha', NULL, '6666666666', '18', NULL, 'CUMILLA', 'state', '3700', 'ryrtyr', NULL, '665555555555', '18', NULL, 'Lakshmipur', 'sratr', 'Ght', 0.00, '', 0.00, NULL, NULL, 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Bangladesh', NULL, NULL, NULL, NULL, NULL, NULL),
(7, 0, 'Joy Saha', 's@1.com', '2025-06-10 18:18:37', 'SSL_6848d96da530d', 269, '', '', '', '', '', 'SSLCommerz', 'Completed', 'Completed', 'SSL_6848d96da530d', NULL, NULL, 'Joy Saha', NULL, '6666666666', '18', NULL, 'CUMILLA', 'state', '3700', 'ryrtyr', NULL, '665555555555', '18', NULL, 'Lakshmipur', 'sratr', 'Ght', 0.00, '', 0.00, NULL, NULL, 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Bangladesh', NULL, NULL, NULL, NULL, NULL, NULL),
(8, 0, 'Joy Saha', 's@1.com', '2025-06-10 17:54:17', 'SSL_6848d3b9a569b', 379, '', '', '', '', '', 'SSLCommerz', 'Completed', 'Completed', 'SSL_6848d3b9a569b', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(43, 21, 'Joy Saha', 'jsha3741@gmail.com', '2025-11-15 00:27:15', 'SSL_6918396315102', 132, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_6918396315102', NULL, NULL, 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 0.00, '', 0.00, NULL, 'jsha3741@gmail.com', 'Bangladesh', 'Bangladesh', 'jsha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(47, 21, 'Joy Saha', 'jsha3741@gmail.com', '2025-11-15 01:40:13', 'SSL_69184a7d73ad4', 249, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Failed', 'Pending', 'SSL_69184a7d73ad4', NULL, NULL, 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 0.00, '', 0.00, NULL, 'jsha3741@gmail.com', 'Bangladesh', 'Bangladesh', 'jsha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(48, 21, 'Joy Saha', 'jsha3741@gmail.com', '2025-11-15 01:41:52', 'SSL_69184ae0aaa50', 292, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Failed', 'Pending', 'SSL_69184ae0aaa50', NULL, NULL, 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 0.00, '', 0.00, NULL, 'jsha3741@gmail.com', 'Bangladesh', 'Bangladesh', 'jsha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(49, 21, 'Joy Saha', 'jsha3741@gmail.com', '2025-11-15 01:45:39', 'SSL_69184bc3574ea', 249, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Failed', 'Pending', 'SSL_69184bc3574ea', NULL, NULL, 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 0.00, '', 0.00, NULL, 'jsha3741@gmail.com', 'Bangladesh', 'Bangladesh', 'jsha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(50, 21, 'Joy Saha', 'jsha3741@gmail.com', '2025-11-15 01:48:38', 'SSL_69184c769fc50', 279, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Failed', 'Pending', 'SSL_69184c769fc50', NULL, NULL, 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 0.00, '', 0.00, NULL, 'jsha3741@gmail.com', 'Bangladesh', 'Bangladesh', 'jsha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(51, 21, 'Joy Saha', 'jsha3741@gmail.com', '2025-11-15 01:49:12', 'SSL_69184c98314da', 125, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Failed', 'Pending', 'SSL_69184c98314da', NULL, NULL, 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 0.00, '', 0.00, NULL, 'jsha3741@gmail.com', 'Bangladesh', 'Bangladesh', 'jsha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(52, 21, 'Joy Saha', 'jsha3741@gmail.com', '2025-11-15 01:51:26', 'SSL_69184d1e5a34d', 119, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Failed', 'Pending', 'SSL_69184d1e5a34d', NULL, NULL, 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 0.00, '', 0.00, NULL, 'jsha3741@gmail.com', 'Bangladesh', 'Bangladesh', 'jsha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(54, 21, 'Joy Saha', 'jsha3741@gmail.com', '2025-11-15 02:07:35', 'SSL_691850e745eeb', 139, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Failed', 'Pending', 'SSL_691850e745eeb', NULL, NULL, 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 0.00, '', 0.00, NULL, 'jsha3741@gmail.com', 'Bangladesh', 'Bangladesh', 'jsha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(56, 21, 'Joy Saha', 'jsha3741@gmail.com', '2025-11-15 02:11:51', 'SSL_691851e733f52', 167, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Failed', 'Pending', 'SSL_691851e733f52', NULL, NULL, 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 0.00, '', 0.00, NULL, 'jsha3741@gmail.com', 'Bangladesh', 'Bangladesh', 'jsha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(57, 21, 'Joy Saha', 'jsha3741@gmail.com', '2025-11-15 02:13:19', 'SSL_6918523f4f85c', 167, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_6918523f4f85c', NULL, NULL, 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 0.00, '', 0.00, NULL, 'jsha3741@gmail.com', 'Bangladesh', 'Bangladesh', 'jsha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(58, 21, 'Joy Saha', 'jsha3741@gmail.com', '2025-11-15 02:20:54', 'SSL_6918540650c1d', 143, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_6918540650c1d', NULL, NULL, 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 0.00, '', 0.00, NULL, 'jsha3741@gmail.com', 'Bangladesh', 'Bangladesh', 'jsha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(59, 21, 'Joy Saha', 'jsha3741@gmail.com', '2025-11-15 02:21:02', 'SSL_6918540e617f1', 143, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_6918540e617f1', NULL, NULL, 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 0.00, '', 0.00, NULL, 'jsha3741@gmail.com', 'Bangladesh', 'Bangladesh', 'jsha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(60, 21, 'Joy Saha', 'jsha3741@gmail.com', '2025-11-15 02:21:17', 'SSL_6918541dc7c00', 143, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_6918541dc7c00', NULL, NULL, 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 0.00, '', 0.00, NULL, 'jsha3741@gmail.com', 'Bangladesh', 'Bangladesh', 'jsha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(61, 21, 'Joy Saha', 'jsha3741@gmail.com', '2025-11-15 02:22:09', 'SSL_691854514b8c6', 143, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_691854514b8c6', NULL, NULL, 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 0.00, '', 0.00, NULL, 'jsha3741@gmail.com', 'Bangladesh', 'Bangladesh', 'jsha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(62, 21, 'Joy Saha', 'jsha3741@gmail.com', '2025-11-15 02:30:06', 'SSL_6918562e7806e', 156, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_6918562e7806e', NULL, NULL, 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 0.00, '', 0.00, NULL, 'jsha3741@gmail.com', 'Bangladesh', 'Bangladesh', 'jsha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(63, 21, 'Joy Saha', 'jsha3741@gmail.com', '2025-11-15 07:06:23', 'SSL_691896efbcc46', 269, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_691896efbcc46', NULL, NULL, 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 0.00, '', 0.00, NULL, 'jsha3741@gmail.com', 'Bangladesh', 'Bangladesh', 'jsha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(67, 23, 'Joy Saha', 'jsaha3741@gmail.com', '2026-01-06 10:09:54', 'SSL_695d4ff268c81', 6796, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_695d4ff268c81', NULL, NULL, 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 0.00, '', 0.00, NULL, 'jsaha3741@gmail.com', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'jsaha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(65, 21, 'Joy Saha', 'jsha3741@gmail.com', '2025-11-15 07:10:27', 'SSL_691897e3982bd', 379, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_691897e3982bd', NULL, NULL, 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 'Joy Saha', NULL, '01863054816', '18', NULL, 'Lakshmipur', 'T', 'Ght', 0.00, '', 0.00, NULL, 'jsha3741@gmail.com', 'Bangladesh', 'Bangladesh', 'jsha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(69, 23, 'Joy Saha', 'jsaha3741@gmail.com', '2026-01-09 11:05:50', 'SSL_6961518ea8a29', 595, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_6961518ea8a29', NULL, NULL, 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 0.00, '', 0.00, NULL, 'jsaha3741@gmail.com', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'jsaha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(70, 23, 'Joy Saha', 'jsaha3741@gmail.com', '2026-01-09 11:15:34', 'SSL_696153d65e40b', 137, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_696153d65e40b', NULL, NULL, 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 0.00, '', 0.00, NULL, 'jsaha3741@gmail.com', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'jsaha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(73, 23, 'Joy Saha', 'jsaha3741@gmail.com', '2026-01-19 00:46:56', 'SSL_696def80526b9', 249, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Cancelled', 'Cancelled', 'SSL_696def80526b9', NULL, NULL, 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 0.00, '', 0.00, NULL, 'jsaha3741@gmail.com', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'jsaha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(74, 23, 'Joy Saha', 'jsaha3741@gmail.com', '2026-01-19 00:49:21', 'SSL_696df011cfab3', 268, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Cancelled', 'Cancelled', 'SSL_696df011cfab3', NULL, NULL, 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 0.00, '', 0.00, NULL, 'jsaha3741@gmail.com', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'jsaha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(75, 23, 'Joy Saha', 'jsaha3741@gmail.com', '2026-01-19 00:56:49', '', 150, '', NULL, NULL, NULL, '', 'Cash on Delivery', 'Cancelled', 'Cancelled', 'COD-1768813009-233488', '', NULL, 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 100.00, '', 0.00, NULL, 'jsaha3741@gmail.com', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'jsaha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(76, 23, 'Joy Saha', 'jsaha3741@gmail.com', '2026-01-19 01:06:25', 'SSL_696df4119e8d2', 119, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_696df4119e8d2', NULL, NULL, 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 0.00, '', 0.00, NULL, 'jsaha3741@gmail.com', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'jsaha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(77, 23, 'Joy Saha', 'jsaha3741@gmail.com', '2026-01-20 11:47:35', 'SSL_696fdbd7ac2c6', 132, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_696fdbd7ac2c6', NULL, NULL, 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 0.00, '', 0.00, NULL, 'jsaha3741@gmail.com', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'jsaha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(78, 24, 'Joy Saha', 'jsaha3741@gmail.com', '2026-01-21 05:19:42', 'SSL_6970d26ee15bf', 475, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_6970d26ee15bf', NULL, NULL, 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 0.00, '', 0.00, NULL, 'jsaha3741@gmail.com', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'jsaha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(79, 24, 'Joy Saha', 'jsaha3741@gmail.com', '2026-01-22 00:16:04', 'SSL_6971dcc461c1e', 475, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_6971dcc461c1e', NULL, NULL, 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 0.00, '', 0.00, NULL, 'jsaha3741@gmail.com', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'jsaha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(80, 24, 'Joy Saha', 'jsaha3741@gmail.com', '2026-01-22 00:18:37', 'SSL_6971dd5de1748', 475, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_6971dd5de1748', NULL, NULL, 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 0.00, '', 0.00, NULL, 'jsaha3741@gmail.com', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'jsaha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(81, 24, 'Joy Saha', 'jsaha3741@gmail.com', '2026-01-22 00:18:54', 'SSL_6971dd6e9e31a', 475, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_6971dd6e9e31a', NULL, NULL, 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 0.00, '', 0.00, NULL, 'jsaha3741@gmail.com', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'jsaha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(82, 24, 'Joy Saha', 'jsaha3741@gmail.com', '2026-01-22 00:20:17', 'SSL_6971ddc16df98', 475, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_6971ddc16df98', NULL, NULL, 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 0.00, '', 0.00, NULL, 'jsaha3741@gmail.com', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'jsaha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(83, 24, 'Joy Saha', 'jsaha3741@gmail.com', '2026-01-22 00:23:03', 'SSL_6971de675c938', 475, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_6971de675c938', NULL, NULL, 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 0.00, '', 0.00, NULL, 'jsaha3741@gmail.com', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'jsaha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(84, 24, 'Joy Saha', 'jsaha3741@gmail.com', '2026-01-22 00:24:08', 'SSL_6971dea8351d0', 475, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_6971dea8351d0', NULL, NULL, 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 0.00, '', 0.00, NULL, 'jsaha3741@gmail.com', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'jsaha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(85, 24, 'Joy Saha', 'jsaha3741@gmail.com', '2026-01-22 00:31:14', 'SSL_6971e0520728e', 475, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_6971e0520728e', NULL, NULL, 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 0.00, '', 0.00, NULL, 'jsaha3741@gmail.com', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'jsaha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(86, 24, 'Joy Saha', 'jsaha3741@gmail.com', '2026-01-22 00:37:07', 'SSL_6971e1b30f688', 475, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_6971e1b30f688', NULL, NULL, 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 0.00, '', 0.00, NULL, 'jsaha3741@gmail.com', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'jsaha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(87, 24, 'Joy Saha', 'jsaha3741@gmail.com', '2026-01-22 00:38:20', 'SSL_6971e1fc8d952', 475, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_6971e1fc8d952', NULL, NULL, 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 0.00, '', 0.00, NULL, 'jsaha3741@gmail.com', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'jsaha3741@gmail.com', NULL, NULL, NULL, NULL, NULL),
(88, 24, 'Joy Saha', 'jsaha3741@gmail.com', '2026-01-22 01:03:39', 'SSL_6971e7ebca8c4', 475, NULL, NULL, NULL, NULL, NULL, 'SSLCommerz', 'Completed', 'Pending', 'SSL_6971e7ebca8c4', NULL, NULL, 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 'Joy Saha', NULL, '01735342839', '18', NULL, 'CUMILLA', 'Cfg', '3700', 0.00, '', 0.00, NULL, 'jsaha3741@gmail.com', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'Vill:Shomserabad, p.o: Lakshmipur Sadar-3700,', 'jsaha3741@gmail.com', NULL, NULL, NULL, NULL, NULL)
ON CONFLICT ("id") DO NOTHING;

INSERT INTO "tbl_photo" ("id", "caption", "photo")
VALUES (1, 'Photo 1', 'photo-1.jpg'),
(2, 'Photo 2', 'photo-2.jpg'),
(3, 'Photo 3', 'photo-3.jpg'),
(4, 'Photo 4', 'photo-4.jpg'),
(5, 'Photo 5', 'photo-5.jpg'),
(6, 'Photo 6', 'photo-6.jpg')
ON CONFLICT ("id") DO NOTHING;

INSERT INTO "tbl_post" ("post_id", "post_title", "post_slug", "post_content", "post_date", "photo", "category_id", "total_view", "meta_title", "meta_keyword", "meta_description")
VALUES (1, 'Cu vel choro exerci pri et oratio iisque', 'cu-vel-choro-exerci-pri-et-oratio-iisque', '<p>Lorem ipsum dolor sit amet, qui case probo velit no, an postea scaevola partiendo mei. Id mea fuisset perpetua referrentur. Ut everti ceteros mei, alii discere eum no, duo id malis iuvaret. Ad sint everti accusam vel, ea viderer suscipiantur pri. Brute option minimum in cum, ignota iuvaret an pro.</p>\r\n\r\n<p>Solum atqui intellegebat mea an. Ne ius alterum aliquam. Ea nec populo aliquid mentitum, vis in meliore atomorum, sanctus consequat vituperatoribus duo ea. Ad doctus pertinacia ius, virtute fuisset id has, eum ut modo principes. Qui eu labore adversarium, oporteat delicata qui ut, an qui meliore principes. Id aliquid dolorum nam.</p>\r\n\r\n<p>Reque pericula philosophia ut mei, volumus eligendi mandamus has an. In nobis consulatu pri, has at timeam scaevola, has simul quaeque et. Te nec sale accumsan. Dolorem prodesset efficiendi sea ea.</p>\r\n\r\n<p>Et habeo modus debitis pri, vel quis fierent albucius ne. Ea animal meliore usu, nec etiam dolorum atomorum at, nam in audire mandamus omittantur. Cu ius dicam officiis molestiae, mea volumus officiis cotidieque no. Ut vel possim interpretaris, idque probatus antiopam has ad. Facilisi qualisque te sea, no dolorum mnesarchum usu.</p>\r\n\r\n<p>Eum tota graeci impetus an, eirmod invenire rationibus ne mel. Ignota habemus eum ex, vis omnesque delicata perpetua an. Sit id modo invidunt sapientem, ne eum vocibus dolores phaedrum. Case praesent appellantur eu per.</p>\r\n', '05-09-2017', 'news-1.jpg', 3, 14, 'Cu vel choro exerci pri et oratio iisque', '', ''),
(2, 'Epicurei necessitatibus eu facilisi postulant ', 'epicurei-necessitatibus-eu-facilisi-postulant-', '<p>Lorem ipsum dolor sit amet, qui case probo velit no, an postea scaevola partiendo mei. Id mea fuisset perpetua referrentur. Ut everti ceteros mei, alii discere eum no, duo id malis iuvaret. Ad sint everti accusam vel, ea viderer suscipiantur pri. Brute option minimum in cum, ignota iuvaret an pro.</p>\r\n\r\n<p>Solum atqui intellegebat mea an. Ne ius alterum aliquam. Ea nec populo aliquid mentitum, vis in meliore atomorum, sanctus consequat vituperatoribus duo ea. Ad doctus pertinacia ius, virtute fuisset id has, eum ut modo principes. Qui eu labore adversarium, oporteat delicata qui ut, an qui meliore principes. Id aliquid dolorum nam.</p>\r\n\r\n<p>Reque pericula philosophia ut mei, volumus eligendi mandamus has an. In nobis consulatu pri, has at timeam scaevola, has simul quaeque et. Te nec sale accumsan. Dolorem prodesset efficiendi sea ea.</p>\r\n\r\n<p>Et habeo modus debitis pri, vel quis fierent albucius ne. Ea animal meliore usu, nec etiam dolorum atomorum at, nam in audire mandamus omittantur. Cu ius dicam officiis molestiae, mea volumus officiis cotidieque no. Ut vel possim interpretaris, idque probatus antiopam has ad. Facilisi qualisque te sea, no dolorum mnesarchum usu.</p>\r\n\r\n<p>Eum tota graeci impetus an, eirmod invenire rationibus ne mel. Ignota habemus eum ex, vis omnesque delicata perpetua an. Sit id modo invidunt sapientem, ne eum vocibus dolores phaedrum. Case praesent appellantur eu per.</p>\r\n', '05-09-2017', 'news-2.jpg', 3, 6, 'Epicurei necessitatibus eu facilisi postulant ', '', ''),
(3, 'Mei ut errem legimus periculis eos liber', 'mei-ut-errem-legimus-periculis-eos-liber', '<p>Lorem ipsum dolor sit amet, qui case probo velit no, an postea scaevola partiendo mei. Id mea fuisset perpetua referrentur. Ut everti ceteros mei, alii discere eum no, duo id malis iuvaret. Ad sint everti accusam vel, ea viderer suscipiantur pri. Brute option minimum in cum, ignota iuvaret an pro.</p>\r\n\r\n<p>Solum atqui intellegebat mea an. Ne ius alterum aliquam. Ea nec populo aliquid mentitum, vis in meliore atomorum, sanctus consequat vituperatoribus duo ea. Ad doctus pertinacia ius, virtute fuisset id has, eum ut modo principes. Qui eu labore adversarium, oporteat delicata qui ut, an qui meliore principes. Id aliquid dolorum nam.</p>\r\n\r\n<p>Reque pericula philosophia ut mei, volumus eligendi mandamus has an. In nobis consulatu pri, has at timeam scaevola, has simul quaeque et. Te nec sale accumsan. Dolorem prodesset efficiendi sea ea.</p>\r\n\r\n<p>Et habeo modus debitis pri, vel quis fierent albucius ne. Ea animal meliore usu, nec etiam dolorum atomorum at, nam in audire mandamus omittantur. Cu ius dicam officiis molestiae, mea volumus officiis cotidieque no. Ut vel possim interpretaris, idque probatus antiopam has ad. Facilisi qualisque te sea, no dolorum mnesarchum usu.</p>\r\n\r\n<p>Eum tota graeci impetus an, eirmod invenire rationibus ne mel. Ignota habemus eum ex, vis omnesque delicata perpetua an. Sit id modo invidunt sapientem, ne eum vocibus dolores phaedrum. Case praesent appellantur eu per.</p>\r\n', '05-09-2017', 'news-3.jpg', 3, 1, 'Mei ut errem legimus periculis eos liber', '', ''),
(4, 'Id pro unum pertinax oportere vel', 'id-pro-unum-pertinax-oportere-vel', '<p>Lorem ipsum dolor sit amet, qui case probo velit no, an postea scaevola partiendo mei. Id mea fuisset perpetua referrentur. Ut everti ceteros mei, alii discere eum no, duo id malis iuvaret. Ad sint everti accusam vel, ea viderer suscipiantur pri. Brute option minimum in cum, ignota iuvaret an pro.</p>\r\n\r\n<p>Solum atqui intellegebat mea an. Ne ius alterum aliquam. Ea nec populo aliquid mentitum, vis in meliore atomorum, sanctus consequat vituperatoribus duo ea. Ad doctus pertinacia ius, virtute fuisset id has, eum ut modo principes. Qui eu labore adversarium, oporteat delicata qui ut, an qui meliore principes. Id aliquid dolorum nam.</p>\r\n\r\n<p>Reque pericula philosophia ut mei, volumus eligendi mandamus has an. In nobis consulatu pri, has at timeam scaevola, has simul quaeque et. Te nec sale accumsan. Dolorem prodesset efficiendi sea ea.</p>\r\n\r\n<p>Et habeo modus debitis pri, vel quis fierent albucius ne. Ea animal meliore usu, nec etiam dolorum atomorum at, nam in audire mandamus omittantur. Cu ius dicam officiis molestiae, mea volumus officiis cotidieque no. Ut vel possim interpretaris, idque probatus antiopam has ad. Facilisi qualisque te sea, no dolorum mnesarchum usu.</p>\r\n\r\n<p>Eum tota graeci impetus an, eirmod invenire rationibus ne mel. Ignota habemus eum ex, vis omnesque delicata perpetua an. Sit id modo invidunt sapientem, ne eum vocibus dolores phaedrum. Case praesent appellantur eu per.</p>\r\n', '05-09-2017', 'news-4.jpg', 4, 0, 'Id pro unum pertinax oportere vel', '', ''),
(5, 'Tollit cetero cu usu etiam evertitur', 'tollit-cetero-cu-usu-etiam-evertitur', '<p>Lorem ipsum dolor sit amet, qui case probo velit no, an postea scaevola partiendo mei. Id mea fuisset perpetua referrentur. Ut everti ceteros mei, alii discere eum no, duo id malis iuvaret. Ad sint everti accusam vel, ea viderer suscipiantur pri. Brute option minimum in cum, ignota iuvaret an pro.</p>\r\n\r\n<p>Solum atqui intellegebat mea an. Ne ius alterum aliquam. Ea nec populo aliquid mentitum, vis in meliore atomorum, sanctus consequat vituperatoribus duo ea. Ad doctus pertinacia ius, virtute fuisset id has, eum ut modo principes. Qui eu labore adversarium, oporteat delicata qui ut, an qui meliore principes. Id aliquid dolorum nam.</p>\r\n\r\n<p>Reque pericula philosophia ut mei, volumus eligendi mandamus has an. In nobis consulatu pri, has at timeam scaevola, has simul quaeque et. Te nec sale accumsan. Dolorem prodesset efficiendi sea ea.</p>\r\n\r\n<p>Et habeo modus debitis pri, vel quis fierent albucius ne. Ea animal meliore usu, nec etiam dolorum atomorum at, nam in audire mandamus omittantur. Cu ius dicam officiis molestiae, mea volumus officiis cotidieque no. Ut vel possim interpretaris, idque probatus antiopam has ad. Facilisi qualisque te sea, no dolorum mnesarchum usu.</p>\r\n\r\n<p>Eum tota graeci impetus an, eirmod invenire rationibus ne mel. Ignota habemus eum ex, vis omnesque delicata perpetua an. Sit id modo invidunt sapientem, ne eum vocibus dolores phaedrum. Case praesent appellantur eu per.</p>\r\n', '05-09-2017', 'news-5.jpg', 4, 24, 'Tollit cetero cu usu etiam evertitur', '', ''),
(6, 'Omnes ornatus qui et te aeterno', 'omnes-ornatus-qui-et-te-aeterno', '<p>Lorem ipsum dolor sit amet, qui case probo velit no, an postea scaevola partiendo mei. Id mea fuisset perpetua referrentur. Ut everti ceteros mei, alii discere eum no, duo id malis iuvaret. Ad sint everti accusam vel, ea viderer suscipiantur pri. Brute option minimum in cum, ignota iuvaret an pro.</p>\r\n\r\n<p>Solum atqui intellegebat mea an. Ne ius alterum aliquam. Ea nec populo aliquid mentitum, vis in meliore atomorum, sanctus consequat vituperatoribus duo ea. Ad doctus pertinacia ius, virtute fuisset id has, eum ut modo principes. Qui eu labore adversarium, oporteat delicata qui ut, an qui meliore principes. Id aliquid dolorum nam.</p>\r\n\r\n<p>Reque pericula philosophia ut mei, volumus eligendi mandamus has an. In nobis consulatu pri, has at timeam scaevola, has simul quaeque et. Te nec sale accumsan. Dolorem prodesset efficiendi sea ea.</p>\r\n\r\n<p>Et habeo modus debitis pri, vel quis fierent albucius ne. Ea animal meliore usu, nec etiam dolorum atomorum at, nam in audire mandamus omittantur. Cu ius dicam officiis molestiae, mea volumus officiis cotidieque no. Ut vel possim interpretaris, idque probatus antiopam has ad. Facilisi qualisque te sea, no dolorum mnesarchum usu.</p>\r\n\r\n<p>Eum tota graeci impetus an, eirmod invenire rationibus ne mel. Ignota habemus eum ex, vis omnesque delicata perpetua an. Sit id modo invidunt sapientem, ne eum vocibus dolores phaedrum. Case praesent appellantur eu per.</p>\r\n', '05-09-2017', 'news-6.jpg', 4, 2, 'Omnes ornatus qui et te aeterno', '', ''),
(7, 'Vix tale noluisse voluptua ad ne', 'vix-tale-noluisse-voluptua-ad-ne', '<p>Lorem ipsum dolor sit amet, qui case probo velit no, an postea scaevola partiendo mei. Id mea fuisset perpetua referrentur. Ut everti ceteros mei, alii discere eum no, duo id malis iuvaret. Ad sint everti accusam vel, ea viderer suscipiantur pri. Brute option minimum in cum, ignota iuvaret an pro.</p>\r\n\r\n<p>Solum atqui intellegebat mea an. Ne ius alterum aliquam. Ea nec populo aliquid mentitum, vis in meliore atomorum, sanctus consequat vituperatoribus duo ea. Ad doctus pertinacia ius, virtute fuisset id has, eum ut modo principes. Qui eu labore adversarium, oporteat delicata qui ut, an qui meliore principes. Id aliquid dolorum nam.</p>\r\n\r\n<p>Reque pericula philosophia ut mei, volumus eligendi mandamus has an. In nobis consulatu pri, has at timeam scaevola, has simul quaeque et. Te nec sale accumsan. Dolorem prodesset efficiendi sea ea.</p>\r\n\r\n<p>Et habeo modus debitis pri, vel quis fierent albucius ne. Ea animal meliore usu, nec etiam dolorum atomorum at, nam in audire mandamus omittantur. Cu ius dicam officiis molestiae, mea volumus officiis cotidieque no. Ut vel possim interpretaris, idque probatus antiopam has ad. Facilisi qualisque te sea, no dolorum mnesarchum usu.</p>\r\n\r\n<p>Eum tota graeci impetus an, eirmod invenire rationibus ne mel. Ignota habemus eum ex, vis omnesque delicata perpetua an. Sit id modo invidunt sapientem, ne eum vocibus dolores phaedrum. Case praesent appellantur eu per.</p>\r\n', '05-09-2017', 'news-7.jpg', 2, 0, 'Vix tale noluisse voluptua ad ne', '', ''),
(8, 'Liber utroque vim an ne his brute', 'liber-utroque-vim-an-ne-his-brute', '<p>Lorem ipsum dolor sit amet, qui case probo velit no, an postea scaevola partiendo mei. Id mea fuisset perpetua referrentur. Ut everti ceteros mei, alii discere eum no, duo id malis iuvaret. Ad sint everti accusam vel, ea viderer suscipiantur pri. Brute option minimum in cum, ignota iuvaret an pro.</p>\r\n\r\n<p>Solum atqui intellegebat mea an. Ne ius alterum aliquam. Ea nec populo aliquid mentitum, vis in meliore atomorum, sanctus consequat vituperatoribus duo ea. Ad doctus pertinacia ius, virtute fuisset id has, eum ut modo principes. Qui eu labore adversarium, oporteat delicata qui ut, an qui meliore principes. Id aliquid dolorum nam.</p>\r\n\r\n<p>Reque pericula philosophia ut mei, volumus eligendi mandamus has an. In nobis consulatu pri, has at timeam scaevola, has simul quaeque et. Te nec sale accumsan. Dolorem prodesset efficiendi sea ea.</p>\r\n\r\n<p>Et habeo modus debitis pri, vel quis fierent albucius ne. Ea animal meliore usu, nec etiam dolorum atomorum at, nam in audire mandamus omittantur. Cu ius dicam officiis molestiae, mea volumus officiis cotidieque no. Ut vel possim interpretaris, idque probatus antiopam has ad. Facilisi qualisque te sea, no dolorum mnesarchum usu.</p>\r\n\r\n<p>Eum tota graeci impetus an, eirmod invenire rationibus ne mel. Ignota habemus eum ex, vis omnesque delicata perpetua an. Sit id modo invidunt sapientem, ne eum vocibus dolores phaedrum. Case praesent appellantur eu per.</p>\r\n', '05-09-2017', 'news-8.jpg', 2, 12, 'Liber utroque vim an ne his brute', '', ''),
(9, 'Nostrum copiosae argumentum has', 'nostrum-copiosae-argumentum-has', '<p>Lorem ipsum dolor sit amet, qui case probo velit no, an postea scaevola partiendo mei. Id mea fuisset perpetua referrentur. Ut everti ceteros mei, alii discere eum no, duo id malis iuvaret. Ad sint everti accusam vel, ea viderer suscipiantur pri. Brute option minimum in cum, ignota iuvaret an pro.</p>\r\n\r\n<p>Solum atqui intellegebat mea an. Ne ius alterum aliquam. Ea nec populo aliquid mentitum, vis in meliore atomorum, sanctus consequat vituperatoribus duo ea. Ad doctus pertinacia ius, virtute fuisset id has, eum ut modo principes. Qui eu labore adversarium, oporteat delicata qui ut, an qui meliore principes. Id aliquid dolorum nam.</p>\r\n\r\n<p>Reque pericula philosophia ut mei, volumus eligendi mandamus has an. In nobis consulatu pri, has at timeam scaevola, has simul quaeque et. Te nec sale accumsan. Dolorem prodesset efficiendi sea ea.</p>\r\n\r\n<p>Et habeo modus debitis pri, vel quis fierent albucius ne. Ea animal meliore usu, nec etiam dolorum atomorum at, nam in audire mandamus omittantur. Cu ius dicam officiis molestiae, mea volumus officiis cotidieque no. Ut vel possim interpretaris, idque probatus antiopam has ad. Facilisi qualisque te sea, no dolorum mnesarchum usu.</p>\r\n\r\n<p>Eum tota graeci impetus an, eirmod invenire rationibus ne mel. Ignota habemus eum ex, vis omnesque delicata perpetua an. Sit id modo invidunt sapientem, ne eum vocibus dolores phaedrum. Case praesent appellantur eu per.</p>\r\n', '05-09-2017', 'news-9.jpg', 1, 12, 'Nostrum copiosae argumentum has', '', ''),
(10, 'An labores explicari qui eu', 'an-labores-explicari-qui-eu', '<p>Lorem ipsum dolor sit amet, qui case probo velit no, an postea scaevola partiendo mei. Id mea fuisset perpetua referrentur. Ut everti ceteros mei, alii discere eum no, duo id malis iuvaret. Ad sint everti accusam vel, ea viderer suscipiantur pri. Brute option minimum in cum, ignota iuvaret an pro.</p>\r\n\r\n<p>Solum atqui intellegebat mea an. Ne ius alterum aliquam. Ea nec populo aliquid mentitum, vis in meliore atomorum, sanctus consequat vituperatoribus duo ea. Ad doctus pertinacia ius, virtute fuisset id has, eum ut modo principes. Qui eu labore adversarium, oporteat delicata qui ut, an qui meliore principes. Id aliquid dolorum nam.</p>\r\n\r\n<p>Reque pericula philosophia ut mei, volumus eligendi mandamus has an. In nobis consulatu pri, has at timeam scaevola, has simul quaeque et. Te nec sale accumsan. Dolorem prodesset efficiendi sea ea.</p>\r\n\r\n<p>Et habeo modus debitis pri, vel quis fierent albucius ne. Ea animal meliore usu, nec etiam dolorum atomorum at, nam in audire mandamus omittantur. Cu ius dicam officiis molestiae, mea volumus officiis cotidieque no. Ut vel possim interpretaris, idque probatus antiopam has ad. Facilisi qualisque te sea, no dolorum mnesarchum usu.</p>\r\n\r\n<p>Eum tota graeci impetus an, eirmod invenire rationibus ne mel. Ignota habemus eum ex, vis omnesque delicata perpetua an. Sit id modo invidunt sapientem, ne eum vocibus dolores phaedrum. Case praesent appellantur eu per.</p>\r\n', '05-09-2017', 'news-10.jpg', 1, 4, 'An labores explicari qui eu', '', ''),
(11, 'Lorem ipsum dolor sit amet', 'lorem-ipsum-dolor-sit-amet', '<p>Lorem ipsum dolor sit amet, qui case probo velit no, an postea scaevola partiendo mei. Id mea fuisset perpetua referrentur. Ut everti ceteros mei, alii discere eum no, duo id malis iuvaret. Ad sint everti accusam vel, ea viderer suscipiantur pri. Brute option minimum in cum, ignota iuvaret an pro.</p>\r\n\r\n<p>Solum atqui intellegebat mea an. Ne ius alterum aliquam. Ea nec populo aliquid mentitum, vis in meliore atomorum, sanctus consequat vituperatoribus duo ea. Ad doctus pertinacia ius, virtute fuisset id has, eum ut modo principes. Qui eu labore adversarium, oporteat delicata qui ut, an qui meliore principes. Id aliquid dolorum nam.</p>\r\n\r\n<p>Reque pericula philosophia ut mei, volumus eligendi mandamus has an. In nobis consulatu pri, has at timeam scaevola, has simul quaeque et. Te nec sale accumsan. Dolorem prodesset efficiendi sea ea.</p>\r\n\r\n<p>Et habeo modus debitis pri, vel quis fierent albucius ne. Ea animal meliore usu, nec etiam dolorum atomorum at, nam in audire mandamus omittantur. Cu ius dicam officiis molestiae, mea volumus officiis cotidieque no. Ut vel possim interpretaris, idque probatus antiopam has ad. Facilisi qualisque te sea, no dolorum mnesarchum usu.</p>\r\n\r\n<p>Eum tota graeci impetus an, eirmod invenire rationibus ne mel. Ignota habemus eum ex, vis omnesque delicata perpetua an. Sit id modo invidunt sapientem, ne eum vocibus dolores phaedrum. Case praesent appellantur eu per.</p>\r\n', '05-09-2017', 'news-11.jpg', 1, 18, 'Lorem ipsum dolor sit amet', '', '')
ON CONFLICT ("post_id") DO NOTHING;

INSERT INTO "tbl_product" ("p_id", "business_id", "p_name", "p_old_price", "p_current_price", "p_qty", "p_featured_photo", "p_description", "p_short_description", "p_feature", "p_condition", "p_return_policy", "p_total_view", "p_is_featured", "p_is_active", "ecat_id", "p_video_link", "is_top_sale", "is_free_shipping", "is_official", "is_premium", "is_overseas", "is_max_vouchered", "vendor_id", "allow_coin_payment", "is_coin_buyable", "coin_price")
VALUES (0, NULL, 'op', '100.00', '55.00', 1, 'product-featured-.jpg', '<p>kk</p>', '<p><b>bbb</b></p>', '', '', '', 1, 1, 1, 1, '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(83, NULL, 'Men''s Ultra Cotton T-Shirt, Multipack', '26', '19', 73, 'product-featured-83.jpg', '<p style="list-style: disc
(83, NULL, 'Men''s Ultra Cotton T-Shirt, Multipack', '26', '19', 73, 'product-featured-83.jpg', '<p>Men Ultra Cotton T-Shirt Multipack</p>', '<p>Multipack T-Shirt</p>', '<p>100% Cotton</p>', '<p>New</p>', '<p>30 Days Return</p>', 10, 1, 1, 1, '', 1, 1, 1, 0, 0, 0, 0, 0, 0, 0),
(99, NULL, 'Oculus Quest 2 - Advanced All-In-One Virtual Reality Headset', '512', '495', 46, 'product-featured-99.jpg', '<p>Oculus Quest 2 Advanced VR Headset</p>', '<p>Virtual Reality Headset</p>', '<p>All-in-one VR</p>', '<p>New</p>', '<p>30 Days Return</p>', 20, 1, 1, 1, '', 1, 1, 1, 0, 0, 0, 0, 0, 0, 0)
ON CONFLICT ("p_id") DO NOTHING;

INSERT INTO "tbl_product" ("p_id", "business_id", "p_name", "p_old_price", "p_current_price", "p_qty", "p_featured_photo", "p_description", "p_short_description", "p_feature", "p_condition", "p_return_policy", "p_total_view", "p_is_featured", "p_is_active", "ecat_id", "p_video_link", "is_top_sale", "is_free_shipping", "is_official", "is_premium", "is_overseas", "is_max_vouchered", "vendor_id", "allow_coin_payment", "is_coin_buyable", "coin_price")
VALUES (99, NULL, 'Oculus Quest 2 - Advanced All-In-One Virtual Reality Headset', '512', '495', 46, 'product-featured-99.jpg', '<p><span style="color: rgb(51, 51, 51)
ON CONFLICT ("p_id") DO NOTHING;

INSERT INTO "tbl_product_color" ("id", "color_id", "p_id")
VALUES (69, 1, 4),
(70, 4, 4),
(77, 6, 6),
(82, 2, 12),
(83, 9, 13),
(84, 3, 14),
(85, 2, 15),
(86, 6, 15),
(87, 3, 16),
(88, 3, 17),
(89, 2, 18),
(90, 3, 19),
(91, 1, 20),
(92, 8, 21),
(93, 2, 22),
(94, 2, 23),
(95, 2, 25),
(96, 5, 26),
(97, 2, 27),
(98, 4, 27),
(99, 5, 28),
(100, 7, 29),
(101, 10, 30),
(102, 11, 31),
(103, 14, 32),
(105, 2, 34),
(106, 1, 35),
(107, 3, 36),
(109, 6, 38),
(110, 2, 39),
(111, 11, 42),
(149, 3, 10),
(150, 6, 9),
(151, 3, 8),
(152, 7, 7),
(159, 2, 77),
(163, 17, 79),
(164, 2, 78),
(167, 3, 80),
(168, 2, 81),
(172, 1, 82),
(173, 2, 82),
(174, 4, 82),
(195, 2, 84),
(201, 2, 86),
(202, 6, 86),
(203, 17, 86),
(222, 16, 93),
(223, 21, 85),
(224, 22, 85),
(225, 23, 85),
(226, 1, 83),
(227, 2, 83),
(228, 3, 83),
(229, 4, 83),
(230, 5, 83),
(231, 6, 83),
(232, 8, 83),
(233, 14, 83),
(234, 17, 83),
(235, 18, 83),
(236, 12, 89),
(237, 27, 91),
(239, 2, 92),
(240, 29, 92),
(241, 2, 88),
(242, 8, 88),
(243, 17, 88),
(244, 2, 90),
(245, 6, 90),
(246, 25, 90),
(247, 27, 90),
(248, 28, 90),
(251, 2, 95),
(252, 6, 95),
(256, 2, 94),
(257, 3, 87),
(258, 17, 87),
(261, 25, 97),
(262, 5, 98),
(263, 6, 99),
(266, 6, 101),
(267, 2, 102)
ON CONFLICT ("id") DO NOTHING;

INSERT INTO "tbl_product_photo" ("pp_id", "photo", "p_id")
VALUES (106, '106.jpg', 83),
(107, '107.jpg', 83),
(108, '108.jpg', 84),
(109, '109.jpg', 84),
(110, '110.jpg', 85),
(111, '111.jpg', 85),
(112, '112.jpg', 86),
(113, '113.jpg', 86),
(114, '114.jpg', 87),
(115, '115.jpg', 87),
(116, '116.jpg', 88),
(117, '117.jpg', 88),
(118, '118.jpg', 89),
(119, '119.jpg', 89),
(120, '120.jpg', 90),
(121, '121.jpg', 91),
(122, '122.jpg', 92),
(123, '123.jpg', 92),
(124, '124.jpg', 93),
(125, '125.jpg', 94),
(126, '126.jpg', 95),
(128, '128.jpg', 97),
(129, '129.jpg', 98),
(130, '130.jpg', 98),
(132, '132.jpg', 102)
ON CONFLICT ("pp_id") DO NOTHING;

INSERT INTO "tbl_product_size" ("id", "size_id", "p_id")
VALUES (44, 1, 6),
(56, 8, 12),
(57, 9, 12),
(58, 10, 12),
(59, 11, 12),
(60, 12, 12),
(61, 13, 12),
(62, 9, 13),
(63, 11, 13),
(64, 13, 13),
(65, 15, 13),
(66, 9, 14),
(67, 11, 14),
(68, 12, 14),
(69, 13, 14),
(70, 9, 15),
(71, 11, 15),
(72, 13, 15),
(73, 15, 16),
(74, 16, 16),
(75, 17, 16),
(76, 16, 17),
(77, 17, 17),
(78, 14, 18),
(79, 15, 18),
(80, 16, 18),
(81, 17, 18),
(82, 15, 19),
(83, 16, 19),
(84, 17, 19),
(85, 14, 20),
(86, 15, 20),
(87, 17, 20),
(88, 15, 21),
(89, 17, 21),
(90, 15, 22),
(91, 16, 22),
(92, 17, 22),
(93, 15, 23),
(94, 16, 23),
(95, 17, 23),
(96, 18, 25),
(97, 19, 25),
(98, 20, 25),
(99, 21, 25),
(100, 19, 26),
(101, 21, 26),
(102, 22, 26),
(103, 23, 26),
(104, 19, 27),
(105, 20, 27),
(106, 21, 27),
(107, 22, 27),
(108, 19, 28),
(109, 20, 28),
(110, 21, 28),
(111, 19, 29),
(112, 20, 29),
(113, 22, 29),
(114, 1, 30),
(115, 2, 30),
(116, 3, 30),
(117, 4, 30),
(118, 23, 31),
(119, 26, 32),
(123, 2, 34),
(124, 2, 35),
(125, 2, 36),
(126, 3, 36),
(129, 2, 38),
(130, 3, 38),
(131, 4, 38),
(132, 5, 38),
(133, 27, 39),
(134, 8, 42),
(210, 3, 10),
(211, 4, 10),
(212, 5, 10),
(213, 6, 10),
(214, 3, 9),
(215, 4, 9),
(216, 3, 8),
(217, 4, 8),
(218, 2, 7),
(219, 3, 7),
(220, 4, 7),
(249, 1, 79),
(250, 2, 79),
(251, 3, 79),
(252, 1, 78),
(253, 2, 78),
(254, 3, 78),
(255, 4, 78),
(256, 5, 78),
(259, 26, 80),
(262, 3, 82),
(263, 4, 82),
(278, 2, 84),
(279, 3, 84),
(280, 4, 84),
(281, 5, 84),
(282, 6, 84),
(305, 26, 86),
(339, 27, 93),
(340, 15, 85),
(341, 16, 85),
(342, 17, 85),
(343, 18, 85),
(344, 19, 85),
(345, 20, 85),
(346, 21, 85),
(347, 22, 85),
(348, 23, 85),
(349, 24, 85),
(350, 25, 85),
(351, 1, 83),
(352, 2, 83),
(353, 3, 83),
(354, 4, 83),
(355, 5, 83),
(356, 6, 83),
(357, 7, 83),
(358, 3, 89),
(359, 4, 89),
(360, 5, 89),
(361, 6, 89),
(362, 7, 89),
(363, 2, 91),
(364, 3, 91),
(365, 4, 91),
(366, 5, 91),
(367, 6, 91),
(369, 27, 92),
(370, 3, 88),
(371, 4, 88),
(372, 5, 88),
(373, 6, 88),
(374, 7, 88),
(375, 1, 90),
(376, 2, 90),
(377, 3, 90),
(378, 4, 90),
(380, 27, 95),
(398, 33, 94),
(399, 29, 87),
(400, 30, 87),
(401, 31, 87),
(402, 32, 87),
(403, 33, 87),
(404, 34, 87),
(405, 35, 87),
(406, 36, 87),
(407, 37, 87),
(408, 38, 87),
(409, 39, 87),
(418, 8, 97),
(419, 9, 97),
(420, 10, 97),
(421, 11, 97),
(422, 12, 97),
(423, 13, 97),
(424, 14, 97),
(425, 15, 97),
(426, 16, 97),
(427, 17, 97),
(428, 18, 97),
(429, 19, 97),
(430, 4, 98),
(431, 5, 98),
(432, 6, 98),
(433, 7, 98),
(434, 40, 99),
(435, 41, 99),
(441, 27, 101),
(442, 42, 102),
(443, 43, 102),
(444, 44, 102),
(445, 45, 102),
(446, 46, 102),
(447, 47, 102)
ON CONFLICT ("id") DO NOTHING;

INSERT INTO "tbl_service" ("id", "title", "content", "photo")
VALUES (5, 'Easy Returns', 'Return any item before 15 days!', 'service-5.png'),
(6, 'Free Shipping', 'Enjoy free shipping inside US.', 'service-6.png'),
(7, 'Fast Shipping', 'Items are shipped within 24 hours.', 'service-7.png'),
(8, 'Satisfaction Guarantee', 'We guarantee you with our quality satisfaction.', 'service-8.png'),
(9, 'Secure Checkout', 'Providing Secure Checkout Options for all', 'service-9.png'),
(10, 'Money Back Guarantee', 'Offer money back guarantee on our products', 'service-10.png')
ON CONFLICT ("id") DO NOTHING;

INSERT INTO "tbl_settings" ("id", "logo", "favicon", "footer_about", "footer_copyright", "contact_address", "contact_email", "contact_phone", "contact_fax", "contact_map_iframe", "receive_email", "receive_email_subject", "receive_email_thank_you_message", "forget_password_message", "total_recent_post_footer", "total_popular_post_footer", "total_recent_post_sidebar", "total_popular_post_sidebar", "total_featured_product_home", "total_latest_product_home", "total_popular_product_home", "meta_title_home", "meta_keyword_home", "meta_description_home", "google_client_id", "facebook_app_id", "twilio_account_sid", "twilio_auth_token", "twilio_phone_number", "smtp_host", "smtp_username", "smtp_password", "smtp_encryption", "smtp_port", "smtp_from_email", "smtp_from_name", "banner_login", "banner_registration", "banner_forget_password", "banner_reset_password", "banner_search", "banner_cart", "banner_checkout", "banner_product_category", "banner_blog", "cta_title", "cta_content", "cta_read_more_text", "cta_read_more_url", "cta_photo", "featured_product_title", "featured_product_subtitle", "latest_product_title", "latest_product_subtitle", "popular_product_title", "popular_product_subtitle", "testimonial_title", "testimonial_subtitle", "testimonial_photo", "blog_title", "blog_subtitle", "newsletter_text", "paypal_email", "stripe_public_key", "stripe_secret_key", "bank_detail", "before_head", "after_body", "before_body", "home_service_on_off", "home_welcome_on_off", "home_featured_product_on_off", "home_latest_product_on_off", "home_popular_product_on_off", "home_testimonial_on_off", "home_blog_on_off", "newsletter_on_off", "ads_above_welcome_on_off", "ads_above_featured_product_on_off", "ads_above_latest_product_on_off", "ads_above_popular_product_on_off", "ads_above_testimonial_on_off", "ads_category_sidebar_on_off", "sslcz_store_id", "sslcz_store_pass", "sslcz_mode", "payment_methods", "review_feature_on_off", "estimated_delivery_time_local", "estimated_delivery_time_international", "gemini_api_key", "flash_sale_end_time", "free_delivery_threshold_qty", "product_voucher_code", "product_voucher_discount", "email_method", "BASE_URL", "paypal_client_id", "paypal_secret", "paypal_sandbox_mode", "cod_enabled", "facebook_app_secret", "google_client_secret", "home_slider_on_off", "home_features_on_off", "home_map_on_off", "home_newsletter_on_off", "home_brand_on_off", "home_category_on_off", "home_slider_order", "home_features_order", "home_category_order", "home_flash_order", "home_featured_product_order", "home_latest_product_order", "home_popular_product_order", "bg_color_categories", "bg_color_latest_products", "show_scroll_top_btn", "slider_side_banner_img", "slider_side_banner_text", "extra_footer_section_enable", "home_sticky_nav_on_off", "home_sticky_nav_order", "multi_vendor_active", "coin_system_active", "sticky_header_mobile", "sticky_header_desktop", "multi_vendor_on_off", "coin_payment_system_on_off", "chat_system_on_off", "coin_payment_on_off", "desktop_advanced_layout_on_off", "hide_banner_desktop", "hide_banner_mobile", "hide_free_delivery_desktop", "hide_free_delivery_mobile", "bg_color_featured_products", "featured_product_count")
VALUES (1, 'logo-1768060821-4cfa5d2ae5.png', 'favicon-1768060928-ae939e44e1.png', 'About Us text here.', 'Copyright © 2025 All Rights Reserved.', '123 Shopping Street, Your City', 'contact@yourwebsite.com', '01863054816', '', '<p>Map Iframe Code Here</p>', 'studentroutinemanager@gmail.com', 'New Contact Form Message', 'Thank you for contacting us!', 'Password reset instructions here.', 0, 0, 0, 0, 0, 0, 0, 'Home Page', 'ecommerce, shop, products', 'This is the description of the home page.', '', '', '', '', '', 'smtp.gmail.com', '', '', 'SSL', 465, '', 'JoyStore', 'banner_login.jpg', 'banner_registration.jpg', 'banner_forget_password.jpg', 'banner_reset_password.jpg', 'banner_search.jpg', 'banner_cart.jpg', 'banner_checkout.jpg', 'banner_product_category.jpg', '', '', '', '', '', '', 'Featured Products', 'Check out our best products', 'Latest Products', 'See what is new in our store', 'Popular Products', 'Products that everyone loves', '', '', '', '', '', '', 'paypal-business@yourwebsite.com', '', '', 'Your Bank Details Here...', '', '', '', 0, 0, 1, 1, 1, 0, 0, 1, 0, 0, 0, 0, 0, 0, 'pract663184d9bae59', 'pract663184d9bae59@ssl', 'sandbox', 'Bank Deposit,Cash on Delivery,SSLCommerz', 1, '3-5 business days', '10-20 business days', '', NULL, 5, 'SAVE10', 10.00, 'PHP Mail', '', '', '', '0', 1, '', '', 1, 1, 0, 0, 0, 1, 5, 1, 2, 4, 3, 6, 7, '#fb9dab', '#dab2f0', 1, 'side-banner.jpg', '', 1, 1, 8, 1, 1, 1, 1, 0, 0, 1, 0, 1, 1, 1, 1, 1, '#ffffff', 8)
ON CONFLICT ("id") DO NOTHING;

INSERT INTO "tbl_shipping_cost" ("shipping_cost_id", "country_id", "amount")
VALUES (1, 228, '11'),
(2, 167, '10'),
(3, 13, '8'),
(4, 230, '0')
ON CONFLICT ("shipping_cost_id") DO NOTHING;

INSERT INTO "tbl_shipping_cost_all" ("sca_id", "amount")
VALUES (1, '100')
ON CONFLICT ("sca_id") DO NOTHING;

INSERT INTO "tbl_size" ("size_id", "size_name")
VALUES (1, 'XS'),
(2, 'S'),
(3, 'M'),
(4, 'L'),
(5, 'XL'),
(6, 'XXL'),
(7, '3XL'),
(8, '31'),
(9, '32'),
(10, '33'),
(11, '34'),
(12, '35'),
(13, '36'),
(14, '37'),
(15, '38'),
(16, '39'),
(17, '40'),
(18, '41'),
(19, '42'),
(20, '43'),
(21, '44'),
(22, '45'),
(23, '46'),
(24, '47'),
(25, '48'),
(26, 'Free Size'),
(27, 'One Size for All'),
(28, '10'),
(29, '12 Months'),
(30, '2T'),
(31, '3T'),
(32, '4T'),
(33, '5T'),
(34, '6 Years'),
(35, '7 Years'),
(36, '8 Years'),
(37, '10 Years'),
(38, '12 Years'),
(39, '14 Years'),
(40, '256 GB'),
(41, '128 GB'),
(42, '14 Plus'),
(43, '16 Plus'),
(44, '18 Plus'),
(45, '20 Plus'),
(46, '22 Plus'),
(47, '24 Plus')
ON CONFLICT ("size_id") DO NOTHING;

INSERT INTO "tbl_social" ("id", "social_name", "social_url")
VALUES (1, 'Facebook', ''),
(2, 'Twitter', ''),
(3, 'LinkedIn', ''),
(4, 'Google Plus', ''),
(5, 'Pinterest', ''),
(6, 'YouTube', ''),
(7, 'Instagram', ''),
(8, 'Tumblr', ''),
(9, 'Flickr', ''),
(10, 'Reddit', ''),
(11, 'Snapchat', ''),
(12, 'WhatsApp', ''),
(13, 'Quora', ''),
(14, 'StumbleUpon', ''),
(15, 'Delicious', ''),
(16, 'Digg', '')
ON CONFLICT ("id") DO NOTHING;

INSERT INTO "tbl_top_category" ("tcat_id", "tcat_name", "show_on_menu", "tcat_order", "photo")
VALUES (1, 'yiyi', 1, 0, '')
ON CONFLICT ("tcat_id") DO NOTHING;

INSERT INTO "tbl_user" ("id", "full_name", "email", "phone", "photo", "role", "password", "status")
VALUES (2, 'Admin User', 'admin@example.com', '', 'default.png', 'User', '0192023a7bbd73250516f069df18b500', 'Active')
ON CONFLICT ("id") DO NOTHING;

INSERT INTO "tbl_wishlist" ("wishlist_id", "cust_id", "product_id", "added_date")
VALUES (1, 21, 102, '2025-11-08 00:01:11'),
(2, 21, 84, '2025-11-08 00:06:37'),
(3, 21, 97, '2025-11-08 00:06:40'),
(6, 23, 83, '2026-01-19 14:47:29')
ON CONFLICT ("wishlist_id") DO NOTHING;

-- ============================================================================
-- Align Sequence Values for Auto-Increment / Serial Columns
-- ============================================================================
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_advertisements"', 'ad_id'), COALESCE((SELECT MAX("ad_id") FROM "tbl_advertisements"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_auctions"', 'auction_id'), COALESCE((SELECT MAX("auction_id") FROM "tbl_auctions"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_auction_bids"', 'bid_id'), COALESCE((SELECT MAX("bid_id") FROM "tbl_auction_bids"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_businesses"', 'business_id'), COALESCE((SELECT MAX("business_id") FROM "tbl_businesses"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_coin_transactions"', 'transaction_id'), COALESCE((SELECT MAX("transaction_id") FROM "tbl_coin_transactions"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_coupon"', 'coupon_id'), COALESCE((SELECT MAX("coupon_id") FROM "tbl_coupon"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_customer"', 'cust_id'), COALESCE((SELECT MAX("cust_id") FROM "tbl_customer"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_customer_carts"', 'cart_id'), COALESCE((SELECT MAX("cart_id") FROM "tbl_customer_carts"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_drivers"', 'driver_id'), COALESCE((SELECT MAX("driver_id") FROM "tbl_drivers"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_emergency_contacts"', 'contact_id'), COALESCE((SELECT MAX("contact_id") FROM "tbl_emergency_contacts"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_features"', 'id'), COALESCE((SELECT MAX("id") FROM "tbl_features"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_food_orders"', 'order_id'), COALESCE((SELECT MAX("order_id") FROM "tbl_food_orders"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_food_order_items"', 'order_item_id'), COALESCE((SELECT MAX("order_item_id") FROM "tbl_food_order_items"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_gov_projects"', 'project_id'), COALESCE((SELECT MAX("project_id") FROM "tbl_gov_projects"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_home_sections"', 'id'), COALESCE((SELECT MAX("id") FROM "tbl_home_sections"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_home_tabs"', 'id'), COALESCE((SELECT MAX("id") FROM "tbl_home_tabs"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_hotels"', 'hotel_id'), COALESCE((SELECT MAX("hotel_id") FROM "tbl_hotels"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_hotel_rooms"', 'room_id'), COALESCE((SELECT MAX("room_id") FROM "tbl_hotel_rooms"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_house_rentals"', 'rental_id'), COALESCE((SELECT MAX("rental_id") FROM "tbl_house_rentals"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_house_sales"', 'sale_id'), COALESCE((SELECT MAX("sale_id") FROM "tbl_house_sales"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_kyc_verifications"', 'kyc_id'), COALESCE((SELECT MAX("kyc_id") FROM "tbl_kyc_verifications"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_menu_categories"', 'menu_category_id'), COALESCE((SELECT MAX("menu_category_id") FROM "tbl_menu_categories"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_menu_items"', 'item_id'), COALESCE((SELECT MAX("item_id") FROM "tbl_menu_items"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_order"', 'id'), COALESCE((SELECT MAX("id") FROM "tbl_order"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_payment"', 'id'), COALESCE((SELECT MAX("id") FROM "tbl_payment"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_product"', 'p_id'), COALESCE((SELECT MAX("p_id") FROM "tbl_product"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_product_variants"', 'variant_id'), COALESCE((SELECT MAX("variant_id") FROM "tbl_product_variants"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_professionals"', 'professional_id'), COALESCE((SELECT MAX("professional_id") FROM "tbl_professionals"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_professional_categories"', 'category_id'), COALESCE((SELECT MAX("category_id") FROM "tbl_professional_categories"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_professional_reviews"', 'review_id'), COALESCE((SELECT MAX("review_id") FROM "tbl_professional_reviews"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_professional_services"', 'service_id'), COALESCE((SELECT MAX("service_id") FROM "tbl_professional_services"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_project_votes"', 'vote_id'), COALESCE((SELECT MAX("vote_id") FROM "tbl_project_votes"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_restaurants"', 'restaurant_id'), COALESCE((SELECT MAX("restaurant_id") FROM "tbl_restaurants"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_review"', 'review_id'), COALESCE((SELECT MAX("review_id") FROM "tbl_review"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_review_image"', 'image_id'), COALESCE((SELECT MAX("image_id") FROM "tbl_review_image"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_settings"', 'id'), COALESCE((SELECT MAX("id") FROM "tbl_settings"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_slider"', 'id'), COALESCE((SELECT MAX("id") FROM "tbl_slider"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_social"', 'id'), COALESCE((SELECT MAX("id") FROM "tbl_social"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_subscriber"', 'subs_id'), COALESCE((SELECT MAX("subs_id") FROM "tbl_subscriber"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_top_category"', 'tcat_id'), COALESCE((SELECT MAX("tcat_id") FROM "tbl_top_category"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_transport_bookings"', 'booking_id'), COALESCE((SELECT MAX("booking_id") FROM "tbl_transport_bookings"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_travel_agencies"', 'agency_id'), COALESCE((SELECT MAX("agency_id") FROM "tbl_travel_agencies"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_travel_bookings"', 'booking_id'), COALESCE((SELECT MAX("booking_id") FROM "tbl_travel_bookings"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_used_products"', 'used_product_id'), COALESCE((SELECT MAX("used_product_id") FROM "tbl_used_products"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_user"', 'id'), COALESCE((SELECT MAX("id") FROM "tbl_user"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_vehicles"', 'vehicle_id'), COALESCE((SELECT MAX("vehicle_id") FROM "tbl_vehicles"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_vouchers"', 'voucher_id'), COALESCE((SELECT MAX("voucher_id") FROM "tbl_vouchers"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_wishlist"', 'wishlist_id'), COALESCE((SELECT MAX("wishlist_id") FROM "tbl_wishlist"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_color"', 'color_id'), COALESCE((SELECT MAX("color_id") FROM "tbl_color"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_country"', 'country_id'), COALESCE((SELECT MAX("country_id") FROM "tbl_country"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_customer_message"', 'customer_message_id'), COALESCE((SELECT MAX("customer_message_id") FROM "tbl_customer_message"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_end_category"', 'ecat_id'), COALESCE((SELECT MAX("ecat_id") FROM "tbl_end_category"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_faq"', 'faq_id'), COALESCE((SELECT MAX("faq_id") FROM "tbl_faq"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_language"', 'lang_id'), COALESCE((SELECT MAX("lang_id") FROM "tbl_language"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_mid_category"', 'mcat_id'), COALESCE((SELECT MAX("mcat_id") FROM "tbl_mid_category"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_page"', 'id'), COALESCE((SELECT MAX("id") FROM "tbl_page"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_photo"', 'id'), COALESCE((SELECT MAX("id") FROM "tbl_photo"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_post"', 'post_id'), COALESCE((SELECT MAX("post_id") FROM "tbl_post"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_product_color"', 'id'), COALESCE((SELECT MAX("id") FROM "tbl_product_color"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_product_photo"', 'pp_id'), COALESCE((SELECT MAX("pp_id") FROM "tbl_product_photo"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_product_size"', 'id'), COALESCE((SELECT MAX("id") FROM "tbl_product_size"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_rating"', 'rt_id'), COALESCE((SELECT MAX("rt_id") FROM "tbl_rating"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_service"', 'id'), COALESCE((SELECT MAX("id") FROM "tbl_service"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_shipping_cost"', 'shipping_cost_id'), COALESCE((SELECT MAX("shipping_cost_id") FROM "tbl_shipping_cost"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_shipping_cost_all"', 'sca_id'), COALESCE((SELECT MAX("sca_id") FROM "tbl_shipping_cost_all"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
DO $$ BEGIN
  PERFORM setval(pg_get_serial_sequence('"tbl_size"', 'size_id'), COALESCE((SELECT MAX("size_id") FROM "tbl_size"), 1));
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
