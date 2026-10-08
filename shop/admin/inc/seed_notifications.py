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

# Seed realistic demo notifications so the user can immediately see the UI in action
existing = conn.run("SELECT COUNT(*) FROM tbl_notifications")
count = existing[0][0]
print(f"Current notification count: {count}")

if count == 0:
    print("Seeding demo notifications...")
    conn.run("""
    INSERT INTO tbl_notifications (merchant_id, customer_id, title, body, type, order_id, is_read, action_url, created_at)
    VALUES
    ('local-merchant-001', NULL, '🎉 Welcome to SwapnoPay Store!', 'Thanks for joining! Enjoy seamless shopping, fast delivery, and exclusive rewards right from our app.', 'broadcast', NULL, false, 'index.php', NOW() - INTERVAL '5 minutes'),
    ('local-merchant-001', NULL, '⚡ Flash Sale Live: Up to 50% Off!', 'Huge discounts on top trending categories this week. Grab your favorites before stocks run out!', 'promo', NULL, false, 'deals.php', NOW() - INTERVAL '2 hours'),
    ('local-merchant-001', NULL, '📦 Order Delivery Update', 'Your recent order #10842 has been dispatched and is on its way with express courier delivery.', 'order', 10842, false, 'customer-order.php', NOW() - INTERVAL '1 day'),
    ('local-merchant-001', NULL, '🔒 Account Security Alert', 'Two-factor security features are now enabled to keep your shopping wallet and account safe.', 'system', NULL, true, 'customer-password-update.php', NOW() - INTERVAL '3 days');
    """)
    print("Demo notifications seeded successfully!")

conn.close()
