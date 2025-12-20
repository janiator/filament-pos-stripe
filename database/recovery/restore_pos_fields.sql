-- Recovery script to restore pos_session_id, transaction_code, and payment_code
-- Run this script on the live database to recover lost POS data

-- Step 1: Restore pos_session_id from PosEvents (most reliable source)
UPDATE connected_charges cc
SET pos_session_id = pe.pos_session_id
FROM pos_events pe
WHERE pe.related_charge_id = cc.id
  AND pe.pos_session_id IS NOT NULL
  AND cc.pos_session_id IS NULL;

-- Step 2: Restore pos_session_id from Receipts (fallback)
UPDATE connected_charges cc
SET pos_session_id = r.pos_session_id
FROM receipts r
WHERE r.charge_id = cc.id
  AND r.pos_session_id IS NOT NULL
  AND cc.pos_session_id IS NULL;

-- Step 3: Regenerate transaction_code and payment_code based on payment_method
-- Note: This uses PostgreSQL CASE statements to map payment methods to SAF-T codes
-- You may need to adjust these mappings based on your SafTCodeMapper implementation

-- For transaction_code (mapping payment methods to transaction codes)
UPDATE connected_charges
SET transaction_code = CASE
    WHEN payment_method = 'cash' THEN '13016'
    WHEN payment_method = 'card' THEN '13017'
    WHEN payment_method = 'mobile' THEN '13018'
    WHEN payment_method IN ('other', 'unknown') THEN '13019'
    ELSE NULL
END
WHERE transaction_code IS NULL
  AND payment_method IS NOT NULL;

-- For payment_code (mapping payment methods to payment codes)
UPDATE connected_charges
SET payment_code = CASE
    WHEN payment_method = 'cash' THEN '1'  -- Cash
    WHEN payment_method = 'card' THEN '2'  -- Card
    WHEN payment_method = 'mobile' THEN '3' -- Mobile payment
    WHEN payment_method IN ('other', 'unknown') THEN '99' -- Other
    ELSE NULL
END
WHERE payment_code IS NULL
  AND payment_method IS NOT NULL;

-- Verification queries (run these to check recovery status)
-- Check how many charges still have null pos_session_id
SELECT COUNT(*) as charges_without_session
FROM connected_charges
WHERE pos_session_id IS NULL;

-- Check how many charges still have null transaction_code
SELECT COUNT(*) as charges_without_transaction_code
FROM connected_charges
WHERE transaction_code IS NULL
  AND payment_method IS NOT NULL;

-- Check how many charges still have null payment_code
SELECT COUNT(*) as charges_without_payment_code
FROM connected_charges
WHERE payment_code IS NULL
  AND payment_method IS NOT NULL;

-- Show charges that couldn't be recovered (for manual review)
SELECT 
    cc.id,
    cc.stripe_charge_id,
    cc.payment_method,
    cc.created_at,
    COUNT(DISTINCT pe.id) as event_count,
    COUNT(DISTINCT r.id) as receipt_count
FROM connected_charges cc
LEFT JOIN pos_events pe ON pe.related_charge_id = cc.id
LEFT JOIN receipts r ON r.charge_id = cc.id
WHERE cc.pos_session_id IS NULL
GROUP BY cc.id, cc.stripe_charge_id, cc.payment_method, cc.created_at
ORDER BY cc.created_at DESC;

