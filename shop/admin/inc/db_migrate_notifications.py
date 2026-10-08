import os
import sys
import urllib.parse
import pg8000.native
import ssl

db_url = os.environ.get('DATABASE_URL') or (sys.argv[1] if len(sys.argv) > 1 else None)
if not db_url:
    print("Error: DATABASE_URL environment variable is required.")
    sys.exit(1)

parsed = urllib.parse.urlparse(db_url)
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

conn = pg8000.native.Connection(
    user=urllib.parse.unquote(parsed.username or 'postgres'),
    password=urllib.parse.unquote(parsed.password or ''),
    host=parsed.hostname or '127.0.0.1',
    port=parsed.port or 5432,
    database=parsed.path.lstrip('/') or 'postgres',
    ssl_context=ctx
)

print("Checking existing tables...")
tables = conn.run("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND (table_name LIKE '%notif%' OR table_name LIKE '%fcm%')")
print('Existing notif/fcm tables:', tables)

cols = conn.run("SELECT column_name FROM information_schema.columns WHERE table_name = 'tbl_settings'")
col_names = [c[0] for c in cols]
print('tbl_settings columns with fcm/firebase/notif:', [c for c in col_names if any(k in c.lower() for k in ['fcm', 'firebase', 'notif'])])

# Create tbl_notifications
print("Creating tbl_notifications...")
conn.run("""
CREATE TABLE IF NOT EXISTS tbl_notifications (
    id SERIAL PRIMARY KEY,
    merchant_id VARCHAR(100) DEFAULT 'local-merchant-001',
    customer_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'general',
    order_id INT DEFAULT NULL,
    is_read BOOLEAN DEFAULT false,
    icon_url TEXT DEFAULT NULL,
    action_url TEXT DEFAULT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_notif_customer ON tbl_notifications(customer_id, merchant_id);
CREATE INDEX IF NOT EXISTS idx_notif_created ON tbl_notifications(created_at DESC);
""")

# Create tbl_fcm_tokens
print("Creating tbl_fcm_tokens...")
conn.run("""
CREATE TABLE IF NOT EXISTS tbl_fcm_tokens (
    id SERIAL PRIMARY KEY,
    merchant_id VARCHAR(100) DEFAULT 'local-merchant-001',
    customer_id INT DEFAULT NULL,
    fcm_token TEXT NOT NULL UNIQUE,
    device_type VARCHAR(50) DEFAULT 'web',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_fcm_customer ON tbl_fcm_tokens(customer_id, merchant_id);
CREATE INDEX IF NOT EXISTS idx_fcm_token ON tbl_fcm_tokens(fcm_token);
""")

# Add firebase settings columns to tbl_settings if missing
print("Ensuring tbl_settings has Firebase columns...")
settings_cols_to_add = [
    ("fcm_server_key", "TEXT DEFAULT ''"),
    ("firebase_api_key", "VARCHAR(255) DEFAULT ''"),
    ("firebase_auth_domain", "VARCHAR(255) DEFAULT ''"),
    ("firebase_project_id", "VARCHAR(255) DEFAULT ''"),
    ("firebase_storage_bucket", "VARCHAR(255) DEFAULT ''"),
    ("firebase_messaging_sender_id", "VARCHAR(255) DEFAULT ''"),
    ("firebase_app_id", "VARCHAR(255) DEFAULT ''"),
    ("firebase_vapid_key", "TEXT DEFAULT ''")
]

for col, col_type in settings_cols_to_add:
    if col not in col_names:
        print(f"Adding column {col} to tbl_settings...")
        conn.run(f"ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS {col} {col_type};")

print("Migration completed successfully!")
conn.close()
