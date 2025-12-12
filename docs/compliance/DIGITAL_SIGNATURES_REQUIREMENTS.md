# Digital Signatures Requirements for Cash Register Systems

## Overview

This document outlines the requirements for implementing digital signatures in cash register systems as mandated by the Norwegian Tax Administration (Skatteetaten). The requirements are based on the official guidelines: [Requirements and Guidelines for Implementing Digital Signatures in Cash Register Systems v3.0 (30.03.21)](https://www.skatteetaten.no/globalassets/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/saf-t-regnskap/oppdateringer/requirements-and-guidelines-for-implementing-digital-signatures-in-cash-register-systems-v-30_03_21.pdf)

**Legal Basis:** Norwegian Cash Register Systems Act and associated regulations

---

## ⚠️ IMPORTANT: Exemption from Digital Signature Requirements

**Status: ✅ EXEMPT - Digital signatures are NOT required for this system**

### Exemption Criteria Met

According to Skatteetaten's guidelines, digital signatures are **NOT required** when:

1. ✅ **Supplier has operational responsibility and access control**
   - The supplier (visivo.no) maintains full operational control
   - Database access is restricted to supplier personnel only
   - Server infrastructure is managed by the supplier

2. ✅ **Only user interface is available to bookkeeping party**
   - Store owners can only access data through:
     - Filament admin panel (web UI)
     - REST API endpoints (with authentication)
   - No direct database access is provided
   - No raw SQL queries or database exports available to store owners

3. ✅ **Electronic journal access is restricted**
   - Store owners cannot access the electronic journal outside of application functions
   - All journal access goes through:
     - Application UI (Filament resources)
     - API endpoints with proper authorization
     - SAF-T export through application (not direct database access)

### Current System Architecture

- **Multi-tenant architecture**: Store owners are isolated to their own data
- **Access control**: All API endpoints use `authorizeTenant()` verification
- **No direct database access**: Store owners have no database credentials or direct access
- **Operational control**: Supplier controls all infrastructure and database access
- **Application-only access**: All data access is through authenticated application interfaces

### Requirements to Maintain Exemption

To maintain this exemption, the system must ensure:

1. ✅ **No direct database access** for store owners
   - Store owners must never receive database credentials
   - No database export tools accessible to store owners
   - No raw SQL query interfaces for store owners

2. ✅ **All access through application**
   - Electronic journal only accessible through Filament UI or API
   - SAF-T export only through application endpoints
   - Reports only through application functions

3. ✅ **Supplier maintains operational control**
   - Supplier controls database access
   - Supplier controls server infrastructure
   - Supplier controls application deployment

4. ✅ **Access control enforcement**
   - Tenant isolation enforced at application level
   - Authorization checks on all data access
   - No bypass mechanisms for store owners

### Documentation for Compliance

This exemption should be documented in:
- **PRODUKTFRASEGN.md**: State that digital signatures are not required due to supplier operational control
- **System architecture documentation**: Document access control mechanisms
- **Compliance declarations**: Include exemption justification

---

## 1. Purpose of Digital Signatures

Digital signatures serve to:
- **Ensure data integrity**: Verify that transaction data has not been altered
- **Provide authenticity**: Confirm that data originates from the authorized system
- **Enable auditability**: Create a verifiable trail for tax authorities
- **Prevent tampering**: Make it impossible to modify transaction records without detection

---

## 2. Cryptographic Methods

The guidelines specify two acceptable cryptographic methods:

### 2.1 RSA-SHA1-1024
- **Algorithm**: RSA (Rivest-Shamir-Adleman)
- **Hash Function**: SHA-1
- **Key Size**: 1024 bits
- **Signature Size**: 1024 bits (128 bytes)
- **Use Case**: Asymmetric cryptography - uses public/private key pair

### 2.2 HMAC-SHA1-128
- **Algorithm**: HMAC (Hash-based Message Authentication Code)
- **Hash Function**: SHA-1
- **Key Size**: Variable (minimum 128 bits recommended)
- **Signature Size**: 128 bits (16 bytes)
- **Use Case**: Symmetric cryptography - uses shared secret key

---

## 3. Implementation Requirements

### 3.1 Signature Trail
✅ **REQUIRED**: System must maintain a signature trail that records:
- Digital signatures for specific transactions
- Timestamp of signature generation
- Method used (RSA-SHA1-1024 or HMAC-SHA1-128)
- Key identifier (for key rotation scenarios)
- Signature value

### 3.2 What Needs to Be Signed

The following data must be digitally signed:
1. **Individual Transactions** (ConnectedCharge records)
   - Transaction amount
   - Transaction date/time
   - Payment method
   - Items/products sold
   - Tax information
   - Receipt number

2. **POS Sessions** (PosSession records)
   - Session opening
   - Session closing
   - Opening balance
   - Closing balance
   - Cash difference

3. **Reports**
   - X-reports (interim reports)
   - Z-reports (daily closing reports)
   - Complete report data, not just timestamp

4. **SAF-T Files**
   - Entire SAF-T XML file must be signed
   - Signature must be embedded in or accompany the file

5. **Electronic Journal Events** (PosEvent records)
   - Critical events (session open/close, transactions)
   - Event data integrity

### 3.3 Key Management

✅ **REQUIRED**: Robust key management strategy including:

1. **Key Generation**
   - Secure random key generation
   - Appropriate key length (1024 bits for RSA, 128+ bits for HMAC)
   - Key generation timestamp

2. **Key Storage**
   - Private keys must be stored securely (encrypted at rest)
   - Keys should NOT be stored in plain text
   - Consider hardware security modules (HSM) for production
   - Database encryption for key storage

3. **Key Distribution**
   - Secure distribution mechanism for shared keys (HMAC)
   - Public key distribution for RSA (if needed)
   - Key rotation procedures

4. **Key Accountability**
   - Track key generation dates
   - Track key usage
   - Key rotation history
   - Key revocation procedures

5. **Key Rotation**
   - Periodic key rotation policy
   - Support for multiple active keys during transition
   - Historical signature verification with old keys

---

## 4. Current Implementation Status

### ❌ Not Implemented
- Digital signature generation
- Signature storage in database
- Signature verification
- Key management system
- SAF-T file signing
- Transaction signing
- Report signing

### ✅ Existing Infrastructure
- Transaction records (ConnectedCharge)
- Session records (PosSession)
- Event logging (PosEvent)
- SAF-T XML generation
- Report generation (X/Z reports)

---

## 5. Implementation Plan

### Phase 1: Key Management Infrastructure

**Priority: CRITICAL**

1. **Create Key Management Service**
   - `app/Services/DigitalSignatureService.php`
   - Key generation methods
   - Key storage (encrypted)
   - Key retrieval
   - Key rotation support

2. **Database Schema**
   - `digital_signature_keys` table
     - `id`
     - `key_type` (enum: 'rsa', 'hmac')
     - `key_identifier` (unique)
     - `encrypted_private_key` (for RSA) or `encrypted_secret_key` (for HMAC)
     - `public_key` (for RSA, nullable)
     - `algorithm` (e.g., 'RSA-SHA1-1024', 'HMAC-SHA1-128')
     - `key_size`
     - `created_at`
     - `expires_at` (nullable)
     - `is_active`
     - `rotated_from_key_id` (nullable, for key rotation)

3. **Configuration**
   - Add to `config/app.php` or create `config/digital-signatures.php`:
     ```php
     'digital_signatures' => [
         'enabled' => env('DIGITAL_SIGNATURES_ENABLED', false),
         'default_method' => env('DIGITAL_SIGNATURE_METHOD', 'HMAC-SHA1-128'), // or 'RSA-SHA1-1024'
         'key_rotation_days' => env('DIGITAL_SIGNATURE_KEY_ROTATION_DAYS', 365),
         'storage_driver' => env('DIGITAL_SIGNATURE_STORAGE', 'database'), // or 'hsm'
     ],
     ```

### Phase 2: Signature Generation

**Priority: CRITICAL**

1. **Create Signature Generator**
   - `app/Services/DigitalSignatureService.php` (extend from Phase 1)
   - Methods:
     - `signTransaction(ConnectedCharge $charge): string`
     - `signSession(PosSession $session): string`
     - `signReport(array $reportData, string $reportType): string`
     - `signSafTFile(string $xmlContent): string`
     - `signEvent(PosEvent $event): string`

2. **Signature Storage**
   - Add `digital_signature` column to:
     - `connected_charges` table
     - `pos_sessions` table
     - `pos_events` table
     - `pos_session_closings` table (for Z-reports)
   - Create `saf_t_file_signatures` table:
     - `id`
     - `saf_t_file_path`
     - `signature`
     - `signature_method`
     - `key_identifier`
     - `signed_at`
     - `store_id`

3. **Signature Format**
   - Store as base64-encoded string
   - Include metadata: method, key identifier, timestamp

### Phase 3: Signature Verification

**Priority: HIGH**

1. **Verification Methods**
   - `verifyTransaction(ConnectedCharge $charge): bool`
   - `verifySession(PosSession $session): bool`
   - `verifyReport(array $reportData, string $signature): bool`
   - `verifySafTFile(string $xmlContent, string $signature): bool`

2. **Verification API Endpoints**
   - `POST /api/digital-signatures/verify/transaction/{id}`
   - `POST /api/digital-signatures/verify/session/{id}`
   - `POST /api/digital-signatures/verify/saf-t/{filename}`

### Phase 4: Integration with Existing Systems

**Priority: HIGH**

1. **Transaction Signing**
   - Update `ConnectedChargeObserver` or `PurchaseService`
   - Sign transaction immediately after creation
   - Store signature in `digital_signature` column

2. **Session Signing**
   - Sign session on opening (PosSessionObserver)
   - Sign session on closing (with Z-report)
   - Store signatures in `digital_signature` column

3. **Report Signing**
   - Sign X-reports when generated
   - Sign Z-reports when generated
   - Include signature in report data

4. **SAF-T File Signing**
   - Update `GenerateSafTCashRegister` action
   - Sign XML content after generation
   - Store signature separately or embed in XML
   - Update SAF-T schema to include signature element (if supported)

5. **Event Signing**
   - Sign critical events (13012, 13013, 13020, 13021)
   - Store signature in `digital_signature` column

### Phase 5: Audit and Compliance

**Priority: MEDIUM**

1. **Signature Audit Trail**
   - Log all signature operations
   - Track signature generation failures
   - Monitor key usage

2. **Compliance Reporting**
   - Report on signature coverage (% of transactions signed)
   - Key rotation compliance
   - Signature verification status

3. **Filament Admin Interface**
   - View signatures for transactions/sessions
   - Verify signatures manually
   - Manage keys (admin only)
   - View signature audit logs

---

## 6. Technical Implementation Details

### 6.1 RSA-SHA1-1024 Implementation

```php
// Example implementation structure
class DigitalSignatureService
{
    public function signWithRSA(string $data, string $privateKey): string
    {
        // 1. Create SHA-1 hash of data
        $hash = sha1($data, true);
        
        // 2. Sign hash with RSA private key
        openssl_private_encrypt($hash, $signature, $privateKey, OPENSSL_PKCS1_PADDING);
        
        // 3. Return base64-encoded signature
        return base64_encode($signature);
    }
    
    public function verifyWithRSA(string $data, string $signature, string $publicKey): bool
    {
        // 1. Decode signature
        $signatureBytes = base64_decode($signature);
        
        // 2. Decrypt signature with public key
        openssl_public_decrypt($signatureBytes, $decryptedHash, $publicKey, OPENSSL_PKCS1_PADDING);
        
        // 3. Create SHA-1 hash of data
        $dataHash = sha1($data, true);
        
        // 4. Compare hashes
        return hash_equals($dataHash, $decryptedHash);
    }
}
```

### 6.2 HMAC-SHA1-128 Implementation

```php
// Example implementation structure
class DigitalSignatureService
{
    public function signWithHMAC(string $data, string $secretKey): string
    {
        // 1. Create HMAC-SHA1 signature
        $signature = hash_hmac('sha1', $data, $secretKey, true);
        
        // 2. Truncate to 128 bits (16 bytes)
        $truncated = substr($signature, 0, 16);
        
        // 3. Return base64-encoded signature
        return base64_encode($truncated);
    }
    
    public function verifyWithHMAC(string $data, string $signature, string $secretKey): bool
    {
        // 1. Generate expected signature
        $expectedSignature = $this->signWithHMAC($data, $secretKey);
        
        // 2. Compare signatures (timing-safe)
        return hash_equals($expectedSignature, $signature);
    }
}
```

### 6.3 Data Normalization for Signing

**CRITICAL**: Data must be normalized before signing to ensure consistent signatures:

1. **Transaction Data**
   - Sort fields alphabetically
   - Use consistent date/time format (ISO 8601)
   - Use consistent number format (no locale-specific formatting)
   - Include all required fields in consistent order

2. **Session Data**
   - Include session number, dates, balances
   - Consistent formatting

3. **Report Data**
   - Include all report fields
   - Consistent ordering

4. **SAF-T XML**
   - Canonical XML format (C14N)
   - Consistent whitespace handling

---

## 7. Database Schema Changes

### Migration: Add Digital Signature Support

```php
// database/migrations/XXXX_XX_XX_add_digital_signatures.php

Schema::table('connected_charges', function (Blueprint $table) {
    $table->text('digital_signature')->nullable()->after('status');
    $table->string('signature_method', 50)->nullable()->after('digital_signature');
    $table->string('signature_key_identifier', 100)->nullable()->after('signature_method');
    $table->timestamp('signed_at')->nullable()->after('signature_key_identifier');
});

Schema::table('pos_sessions', function (Blueprint $table) {
    $table->text('opening_signature')->nullable()->after('opening_balance');
    $table->text('closing_signature')->nullable()->after('cash_difference');
    $table->string('signature_method', 50)->nullable()->after('closing_signature');
    $table->string('signature_key_identifier', 100)->nullable()->after('signature_method');
});

Schema::table('pos_events', function (Blueprint $table) {
    $table->text('digital_signature')->nullable()->after('event_data');
    $table->string('signature_method', 50)->nullable()->after('digital_signature');
    $table->string('signature_key_identifier', 100)->nullable()->after('signature_method');
});

Schema::table('pos_session_closings', function (Blueprint $table) {
    $table->text('digital_signature')->nullable()->after('summary_data');
    $table->string('signature_method', 50)->nullable()->after('digital_signature');
    $table->string('signature_key_identifier', 100)->nullable()->after('signature_method');
    $table->timestamp('signed_at')->nullable()->after('signature_key_identifier');
});

Schema::create('digital_signature_keys', function (Blueprint $table) {
    $table->id();
    $table->enum('key_type', ['rsa', 'hmac']);
    $table->string('key_identifier', 100)->unique();
    $table->text('encrypted_private_key')->nullable(); // For RSA
    $table->text('encrypted_secret_key')->nullable(); // For HMAC
    $table->text('public_key')->nullable(); // For RSA
    $table->string('algorithm', 50); // e.g., 'RSA-SHA1-1024', 'HMAC-SHA1-128'
    $table->integer('key_size');
    $table->timestamp('created_at');
    $table->timestamp('expires_at')->nullable();
    $table->boolean('is_active')->default(true);
    $table->foreignId('rotated_from_key_id')->nullable()->constrained('digital_signature_keys');
    $table->timestamps();
    
    $table->index(['is_active', 'key_type']);
});

Schema::create('saf_t_file_signatures', function (Blueprint $table) {
    $table->id();
    $table->foreignId('store_id')->constrained('stores');
    $table->string('saf_t_file_path');
    $table->text('signature');
    $table->string('signature_method', 50);
    $table->string('key_identifier', 100);
    $table->timestamp('signed_at');
    $table->timestamps();
    
    $table->index(['store_id', 'signed_at']);
});
```

---

## 8. Security Considerations

### 8.1 Key Storage Security
- ✅ Encrypt keys at rest using Laravel's encryption
- ✅ Use environment variables for master encryption key
- ✅ Consider hardware security modules (HSM) for production
- ✅ Implement key access logging

### 8.2 Key Rotation
- ✅ Rotate keys periodically (recommended: annually)
- ✅ Support multiple active keys during transition period
- ✅ Maintain old keys for historical signature verification
- ✅ Document key rotation procedures

### 8.3 Signature Validation
- ✅ Validate signatures on critical operations
- ✅ Alert on signature verification failures
- ✅ Log all verification attempts

### 8.4 Access Control
- ✅ Restrict key management to administrators only
- ✅ Audit all key operations
- ✅ Implement role-based access control

---

## 9. Testing Requirements

### 9.1 Unit Tests
- [ ] Key generation
- [ ] Signature generation (RSA and HMAC)
- [ ] Signature verification
- [ ] Key rotation
- [ ] Data normalization

### 9.2 Integration Tests
- [ ] Transaction signing on creation
- [ ] Session signing on open/close
- [ ] Report signing
- [ ] SAF-T file signing
- [ ] Signature verification API

### 9.3 Compliance Tests
- [ ] All transactions are signed
- [ ] All sessions are signed
- [ ] All reports are signed
- [ ] SAF-T files are signed
- [ ] Signatures are verifiable

---

## 10. Migration Strategy

### 10.1 Backward Compatibility
- Existing unsigned records should remain valid
- New records must be signed
- Gradual migration: sign existing records during maintenance windows

### 10.2 Rollout Plan
1. **Phase 1**: Deploy key management infrastructure (no signing yet)
2. **Phase 2**: Enable signing for new transactions only
3. **Phase 3**: Backfill signatures for existing records
4. **Phase 4**: Enable signature verification
5. **Phase 5**: Make signing mandatory

---

## 11. Documentation Updates

### 11.1 API Documentation
- [ ] Document signature fields in API responses
- [ ] Document signature verification endpoints
- [ ] Update SAF-T export documentation

### 11.2 Compliance Documentation
- [ ] Update PRODUKTFRASEGN.md to include digital signature support
- [ ] Document key management procedures
- [ ] Document signature verification procedures

### 11.3 User Documentation
- [ ] Document signature status in admin interface
- [ ] Document key rotation procedures (admin)
- [ ] Document signature verification (admin)

---

## 12. Compliance Checklist

- [ ] Key management system implemented
- [ ] RSA-SHA1-1024 support implemented
- [ ] HMAC-SHA1-128 support implemented
- [ ] Transaction signing implemented
- [ ] Session signing implemented
- [ ] Report signing implemented
- [ ] SAF-T file signing implemented
- [ ] Signature verification implemented
- [ ] Signature audit trail implemented
- [ ] Key rotation procedures documented
- [ ] Security measures implemented
- [ ] Testing completed
- [ ] Documentation updated

---

## 13. References

- [Requirements and Guidelines for Implementing Digital Signatures in Cash Register Systems v3.0 (30.03.21)](https://www.skatteetaten.no/globalassets/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/saf-t-regnskap/oppdateringer/requirements-and-guidelines-for-implementing-digital-signatures-in-cash-register-systems-v-30_03_21.pdf)
- [Norwegian Cash Register Systems Act](https://lovdata.no/dokument/NL/lov/2015-06-19-58)
- [Kassasystemforskriften (FOR-2015-12-18-1616)](https://lovdata.no/dokument/SF/forskrift/2015-12-18-1616)

---

## 14. Next Steps

1. **Review and approve this implementation plan**
2. **Choose cryptographic method** (RSA-SHA1-1024 or HMAC-SHA1-128)
3. **Set up development environment** for testing
4. **Implement Phase 1** (Key Management Infrastructure)
5. **Implement Phase 2** (Signature Generation)
6. **Test with sample data**
7. **Deploy to staging environment**
8. **Perform compliance validation**
9. **Deploy to production**
10. **Monitor and maintain**

---

**Status**: Planning Phase  
**Last Updated**: 2025-01-27  
**Priority**: HIGH - Required for compliance with Norwegian tax authority regulations

