# Purchase/Payment Status Functions for FlutterFlow

This document describes the FlutterFlow custom functions available for translating and color-coding purchase and payment statuses.

## Overview

These functions work for **both purchase statuses and payment statuses** since they use the same status values:
- Purchase status: `purchase.status`
- Payment status: `payment.status` (from `purchase_payments` array)

Both use the same status values: `succeeded`, `pending`, `failed`, `refunded`, `cancelled`

---

## Available Functions

### 1. `getPaymentStatusNorwegianLabel(String? status)`
Returns the Norwegian translation of a purchase or payment status.

**Parameters:**
- `status` (String?): The purchase or payment status string (e.g., "succeeded", "pending", "failed", "refunded")

**Returns:**
- `String`: Norwegian label for the status

**Status Mappings:**
- `succeeded` → `"Vellykket"`
- `pending` → `"Ventende"`
- `failed` → `"Feilet"`
- `refunded` → `"Refundert"`
- `cancelled` → `"Avbrutt"`
- `null` or empty → `"Ukjent"`

**Usage Examples:**
```dart
// For purchase status
Text(getPaymentStatusNorwegianLabel(purchase.status))

// For payment status (from purchase_payments array)
Text(getPaymentStatusNorwegianLabel(payment.status))
```

---

### 2. `getPaymentStatusColor(String? status)`
Returns a Flutter `Color` object appropriate for the purchase or payment status.

**Parameters:**
- `status` (String?): The purchase or payment status string

**Returns:**
- `Color`: Flutter Color object for the status

**Color Mappings:**
- `succeeded` → Green (`#4CAF50`)
- `pending` → Orange (`#FF9800`)
- `failed` → Red (`#F44336`)
- `refunded` → Purple (`#9C27B0`)
- `cancelled` → Blue Grey (`#607D8B`)
- `null` or empty → Gray (`#9E9E9E`)

**Usage Examples:**
```dart
// For purchase status
Container(
  decoration: BoxDecoration(
    color: getPaymentStatusColor(purchase.status),
    borderRadius: BorderRadius.circular(8),
  ),
  child: Text('Status'),
)

// For payment status
Container(
  decoration: BoxDecoration(
    color: getPaymentStatusColor(payment.status),
    borderRadius: BorderRadius.circular(8),
  ),
)
```

---

### 3. `getPaymentStatusColorHex(String? status)`
Returns a hex color string for the purchase or payment status.

**Parameters:**
- `status` (String?): The purchase or payment status string

**Returns:**
- `String`: Hex color string (e.g., "#4CAF50")

**Usage Examples:**
```dart
// For purchase status
Text(
  'Status',
  style: TextStyle(
    color: Color(int.parse(
      getPaymentStatusColorHex(purchase.status).replaceFirst('#', '0xFF')
    )),
  ),
)

// For payment status
Text(
  'Status',
  style: TextStyle(
    color: Color(int.parse(
      getPaymentStatusColorHex(payment.status).replaceFirst('#', '0xFF')
    )),
  ),
)
```

---

## Valid Statuses

The following statuses are valid for both purchases and payments (from the API specification):

1. **`succeeded`** - Payment completed successfully
   - Norwegian: "Vellykket"
   - Color: Green (#4CAF50)

2. **`pending`** - Payment is pending (e.g., deferred payments)
   - Norwegian: "Ventende"
   - Color: Orange (#FF9800)

3. **`failed`** - Payment failed
   - Norwegian: "Feilet"
   - Color: Red (#F44336)

4. **`refunded`** - Payment was refunded
   - Norwegian: "Refundert"
   - Color: Purple (#9C27B0)

5. **`cancelled`** - Purchase was cancelled (pending orders that were cancelled)
   - Norwegian: "Avbrutt"
   - Color: Blue Grey (#607D8B)

---

## Common Use Cases

### Status Badge Widget (Purchase)
```dart
Container(
  padding: EdgeInsets.symmetric(horizontal: 12, vertical: 6),
  decoration: BoxDecoration(
    color: getPaymentStatusColor(purchase.status),
    borderRadius: BorderRadius.circular(12),
  ),
  child: Text(
    getPaymentStatusNorwegianLabel(purchase.status),
    style: TextStyle(
      color: Colors.white,
      fontWeight: FontWeight.bold,
      fontSize: 12,
    ),
  ),
)
```

### Status Badge Widget (Payment)
```dart
Container(
  padding: EdgeInsets.symmetric(horizontal: 12, vertical: 6),
  decoration: BoxDecoration(
    color: getPaymentStatusColor(payment.status),
    borderRadius: BorderRadius.circular(12),
  ),
  child: Text(
    getPaymentStatusNorwegianLabel(payment.status),
    style: TextStyle(
      color: Colors.white,
      fontWeight: FontWeight.bold,
      fontSize: 12,
    ),
  ),
)
```

### Status Icon with Color
```dart
Icon(
  Icons.circle,
  color: getPaymentStatusColor(purchase.status),
  size: 12,
)
```

### Status Text with Color
```dart
Text(
  getPaymentStatusNorwegianLabel(purchase.status),
  style: TextStyle(
    color: getPaymentStatusColor(purchase.status),
    fontWeight: FontWeight.w500,
  ),
)
```

### Combined Label and Color
```dart
Container(
  padding: EdgeInsets.all(8),
  decoration: BoxDecoration(
    color: getPaymentStatusColor(purchase.status).withOpacity(0.1),
    border: Border.all(color: getPaymentStatusColor(purchase.status), width: 1),
    borderRadius: BorderRadius.circular(8),
  ),
  child: Row(
    children: [
      Icon(
        Icons.circle,
        color: getPaymentStatusColor(purchase.status),
        size: 8,
      ),
      SizedBox(width: 8),
      Text(
        getPaymentStatusNorwegianLabel(purchase.status),
        style: TextStyle(
          color: getPaymentStatusColor(purchase.status),
          fontWeight: FontWeight.w600,
        ),
      ),
    ],
  ),
)
```

---

## File Locations

Functions are located in:
- `/docs/flutterflow/custom-actions/get_payment_status_norwegian_label.dart`
- `/docs/flutterflow/custom-actions/get_payment_status_color.dart`

---

## Notes

- All functions handle `null` and empty strings gracefully, returning "Ukjent" (Unknown) and gray color
- Status matching is case-insensitive
- Colors follow Material Design color guidelines
- The `refunded` status uses purple to distinguish it from `failed` (red)
- The `cancelled` status uses blue grey to distinguish it from `failed` (red) - cancelled is intentional, failed is an error
- **Same functions work for both purchase.status and payment.status** since they share the same status values
