-- Enable pgcrypto for gen_random_uuid()
create extension if not exists "pgcrypto";

-- 1. Merchants Table
create table if not exists merchants (
  id uuid primary key default gen_random_uuid(),
  user_id uuid references auth.users not null,
  business_name text not null default 'My Store',
  default_number text,
  webhook_secret text,
  created_at timestamptz default now()
);

-- 2. Merchant Payment Numbers
create table if not exists merchant_numbers (
  id uuid primary key default gen_random_uuid(),
  merchant_id uuid references merchants on delete cascade not null,
  number text not null,
  type text not null check (type in ('bKash','Nagad','Rocket','Upay')),
  is_default boolean default false,
  active boolean default true,
  created_at timestamptz default now()
);

-- 3. Orders Table
create table if not exists orders (
  id uuid primary key default gen_random_uuid(),
  merchant_id uuid references merchants not null,
  tran_id text unique not null,
  amount numeric(12,2) not null,
  cus_phone text not null,
  cus_email text,
  cus_name text,
  status text not null default 'PENDING' check (status in ('PENDING','PAID','EXPIRED','CANCELLED')),
  product_name text,
  product_category text,
  callback_url text,
  success_url text,
  fail_url text,
  cancel_url text,
  metadata jsonb default '{}',
  expires_at timestamptz not null,
  paid_at timestamptz,
  payment_method text,
  sender_number text,
  matched_trx_id text,
  manual_match boolean default false,
  created_at timestamptz default now()
);

-- 4. Payments Table
create table if not exists payments (
  id uuid primary key default gen_random_uuid(),
  merchant_id uuid references merchants not null,
  trx_id text, -- Stores raw transaction ID from MFS
  amount numeric(12,2) not null,
  sender_number text,
  merchant_number text,
  sms_timestamp timestamptz,
  sms_hash text unique not null,
  status text not null default 'UNMATCHED' check (status in ('MATCHED','UNMATCHED','DUPLICATE')),
  matched_order_id uuid references orders,
  created_at timestamptz default now()
);

-- 5. SMS Logs Table
create table if not exists sms_logs (
  id uuid primary key default gen_random_uuid(),
  device_id uuid not null,
  merchant_id uuid references merchants not null,
  raw_sms text not null,
  parsed_amount numeric(12,2),
  parsed_sender text,
  parsed_trx_id text,
  parsed_timestamp timestamptz,
  sms_hash text unique not null,
  processed boolean default false,
  status text default 'unprocessed' check (status in ('unprocessed','matched','unmatched','duplicate')),
  created_at timestamptz default now()
);

-- 6. Devices Table
create table if not exists devices (
  id uuid primary key default gen_random_uuid(),
  user_id uuid references auth.users not null,
  merchant_id uuid references merchants,
  fcm_token text,
  device_model text,
  os_version text,
  battery_level integer,
  online boolean default true,
  last_sync timestamptz default now(),
  disabled boolean default false,
  created_at timestamptz default now()
);

-- 7. Appeals Table
create table if not exists appeals (
  id uuid primary key default gen_random_uuid(),
  merchant_id uuid references merchants(id) on delete cascade,
  trx_id text not null,
  cus_phone text,
  order_id uuid references orders,
  note text,
  screenshot_url text, -- Store URL or filename of uploaded screenshot
  status text default 'PENDING_REVIEW' check (status in ('PENDING_REVIEW','APPROVED','REJECTED')),
  resolved_by uuid references auth.users,
  resolved_at timestamptz,
  created_at timestamptz default now()
);

create index if not exists idx_appeals_merchant_id on appeals(merchant_id);
create index if not exists idx_appeals_order_id on appeals(order_id);
create index if not exists idx_appeals_trx_id on appeals(trx_id);
create index if not exists idx_appeals_status on appeals(status);

-- 8. Notifications Table
create table if not exists notifications (
  id uuid primary key default gen_random_uuid(),
  user_id uuid references auth.users not null,
  type text,
  title text,
  body text,
  read boolean default false,
  created_at timestamptz default now()
);

-- 9. Security Logs Table
create table if not exists security_logs (
  id uuid primary key default gen_random_uuid(),
  user_id uuid references auth.users,
  merchant_id uuid references merchants,
  event text not null,
  details jsonb,
  ip_address text,
  created_at timestamptz default now()
);

-- 10. Composite Indexes for Optimal Query Performance
create index if not exists idx_orders_merchant_status on orders(merchant_id, status);
create index if not exists idx_orders_expires on orders(expires_at) where status = 'PENDING';
create index if not exists idx_payments_sms_hash on payments(sms_hash);
create index if not exists idx_sms_logs_merchant_created on sms_logs(merchant_id, created_at);
create index if not exists idx_appeals_status on appeals(status);
create index if not exists idx_devices_merchant on devices(merchant_id);
create index if not exists idx_notifications_user_read on notifications(user_id, read);
