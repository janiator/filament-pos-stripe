# Suggested Filament Navigation Grouping

## Current Structure Analysis

### Current Groups:
- **Katalog**: Collections, Products, Coupons, Discounts, Vendors
- **Kunder**: Customers
- **Terminaler**: POS Devices, Terminal Locations, Terminal Readers, Receipt Printers
- **Innstillinger**: (empty)
- **Administrasjon**: Users, Settings
- **System**: (for Horizon/Pulse)
- **POS-system**: POS Sessions, POS Purchases, POS Events, Receipts, Product Declarations, Payment Methods, Receipt Templates
- **Betalinger**: Connected Payment Methods, Payment Intents, Payment Links, Charges, Transfers
- **Abonnementer**: Subscriptions

## Issues with Current Structure

1. **"Abonnementer" is a separate group** - Subscriptions should be with Customers
2. **"Terminaler" could be clearer** - Could be "Terminaler og utstyr" to include all hardware
3. **Payment Methods confusion** - POS Payment Methods vs Connected Payment Methods are in different groups
4. **Missing Prices** - Connected Prices resource exists but navigation group unclear

## Suggested Improved Grouping

### 1. **POS-system** (Core POS Operations) ⭐
**Purpose**: All resources directly related to POS operations and compliance

**Resources:**
- POS Sessions (økter) - Sort: 1
- POS Purchases (kjøp) - Sort: 2
- POS Events (hendelser) - Sort: 3
- Receipts (kvitteringer) - Sort: 4
- Payment Methods (betalingsmetoder) - Sort: 5 ⚠️ POS payment methods (not Stripe)
- Receipt Templates (kvitteringsmaler) - Sort: 6
- Product Declaration (produktfråsegn) - Sort: 7

**Rationale**: All core POS functionality required for daily operations and compliance with kassasystemforskriften.

---

### 2. **Katalog** (Product & Catalog Management)
**Purpose**: Product catalog, pricing, and promotional management

**Resources:**
- Products (produkter)
- Collections (samlinger)
- Vendors (leverandører)
- Prices (priser) ⚠️ Currently missing from navigation
- Coupons (kuponger)
- Discounts (rabatter)

**Rationale**: All related to managing the product catalog, pricing, and promotions.

---

### 3. **Kunder** (Customer Management)
**Purpose**: Customer relationships and subscriptions

**Resources:**
- Customers (kunder)
- Subscriptions (abonnementer) ⚠️ Move from separate "Abonnementer" group

**Rationale**: Customer-focused resources grouped together. Subscriptions are customer-related.

---

### 4. **Betalinger** (Payment Operations)
**Purpose**: Payment processing and financial transactions from Stripe

**Resources:**
- Charges (belastninger)
- Payment Intents (betalingsintensjoner)
- Payment Links (betalingslenker)
- Connected Payment Methods (kundebetalingsmetoder)
- Transfers (overføringer)

**Rationale**: All payment-related operations from Stripe and other payment providers. These are financial transactions, not POS configuration.

---

### 5. **Terminaler og utstyr** (Hardware & Equipment)
**Purpose**: Physical hardware and device management

**Resources:**
- POS Devices (POS-enheter)
- Terminal Locations (terminal-lokasjoner)
- Terminal Readers (terminal-lesere)
- Receipt Printers (kvitteringsskrivere)

**Rationale**: All hardware and physical equipment management. Rename "Terminaler" to "Terminaler og utstyr" for clarity.

---

### 6. **Innstillinger** (Settings)
**Purpose**: System configuration and settings

**Resources:**
- Settings (innstillinger)

**Rationale**: System-wide configuration.

---

### 7. **Administrasjon** (Administration)
**Purpose**: User and role management

**Resources:**
- Users (brukere)
- Roles (roller) - Shield

**Rationale**: Administrative functions for managing access and users.

---

### 8. **Butikker** (Stores)
**Purpose**: Multi-tenant store management

**Resources:**
- Stores (butikker) - No group (main resource, top level)

**Rationale**: Stores are the main tenant resource, typically shown at top level.

---

## Summary of Required Changes

### Resources to Move:

1. **Subscriptions**: `subscriptions` → `customers`
   - Move ConnectedSubscriptions from "Abonnementer" to "Kunder"

2. **Group Rename**:
   - `terminals` → `terminals_and_equipment` (Norwegian: "Terminaler og utstyr")

3. **Note on Prices**:
   - Connected Prices is intentionally hidden from navigation (managed through products)

### Suggested Navigation Order (by sort priority):

1. **Butikker** (Stores) - top level, no group
2. **POS-system** (Sort: 1-7)
3. **Katalog** (Sort: 10-15)
4. **Kunder** (Sort: 20-21)
5. **Betalinger** (Sort: 30-34)
6. **Terminaler og utstyr** (Sort: 40-43)
7. **Innstillinger** (Sort: 50)
8. **Administrasjon** (Sort: 60-61)
9. **System** (Sort: 100-101) - Horizon/Pulse

## Implementation Details

### Key Distinctions:

1. **POS Payment Methods** vs **Connected Payment Methods**:
   - **POS Payment Methods** (PaymentMethodResource) = Configuration for POS system (cash, card, etc.) → POS-system
   - **Connected Payment Methods** (ConnectedPaymentMethodResource) = Customer payment methods from Stripe → Betalinger

2. **Receipt Templates** = POS configuration → POS-system
3. **Product Declaration** = POS compliance → POS-system
4. **Subscriptions** = Customer relationship → Kunder (not separate group)

### Benefits of This Structure:

✅ **Logical workflow**: POS operations → Catalog → Customers → Payments → Hardware → Settings
✅ **Clear separation**: POS configuration vs Stripe payment operations
✅ **Better discoverability**: Related resources grouped together
✅ **Compliance focus**: All POS compliance resources in one place
✅ **Customer-centric**: All customer-related resources together

## Recommended Implementation Steps

1. Move Subscriptions to Kunder group
2. Rename "Terminaler" to "Terminaler og utstyr"
3. Ensure Connected Prices is in Katalog group
4. Add navigation sort values for consistent ordering
5. Remove unused "Abonnementer" and "pos" groups from language file
