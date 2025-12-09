# Nullinnslag API Documentation

## Overview

Nullinnslag (cash drawer open without sale) is a mandatory requirement per **Kassasystemforskriften § 2-2**. This document explains how to report and query nullinnslag events via the API.

## Reporting Nullinnslag

When a cash drawer is opened **without** a sale transaction, you must report it as nullinnslag.

### Endpoint

```
POST /api/pos-devices/{id}/cash-drawer/open
```

### Request Body

```json
{
  "pos_session_id": 123,        // Optional: Auto-detected if not provided
  "nullinnslag": true,           // REQUIRED: Set to true for nullinnslag
  "reason": "Change for customer" // Optional: Reason for opening drawer
}
```

### Example Request

```dart
// FlutterFlow example
final response = await http.post(
  Uri.parse('$apiBaseUrl/api/pos-devices/$deviceId/cash-drawer/open'),
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer $authToken',
    'Accept': 'application/json',
  },
  body: jsonEncode({
    'nullinnslag': true,
    'reason': 'Change for customer',
  }),
);
```

### Important Notes

- **Active session required**: For nullinnslag, an active POS session is mandatory. The API will return an error if no session is found.
- **Event code**: Nullinnslag events are logged with event code `13005` (Cash Drawer Open).
- **Event data**: The `nullinnslag` flag and `reason` are stored in the `event_data` JSON field.

### Response

```json
{
  "message": "Cash drawer open logged successfully",
  "event": {
    "nullinnslag": true,
    "session_id": 123
  }
}
```

## Querying Nullinnslag Events

### Get All Nullinnslag Events

Use the POS events endpoint with filters:

```
GET /api/pos-events?event_code=13005&nullinnslag=true
```

### Query Parameters

- `event_code=13005` - Filter for cash drawer open events
- `nullinnslag=true` - Filter for nullinnslag events only
- `nullinnslag=false` - Exclude nullinnslag events
- `pos_session_id` - Filter by specific session
- `from_date` - Filter from date (YYYY-MM-DD)
- `to_date` - Filter to date (YYYY-MM-DD)

### Example Request

```dart
// Get all nullinnslag events for a session
final response = await http.get(
  Uri.parse('$apiBaseUrl/api/pos-events')
    .replace(queryParameters: {
      'event_code': '13005',
      'nullinnslag': 'true',
      'pos_session_id': sessionId.toString(),
    }),
  headers: {
    'Authorization': 'Bearer $authToken',
    'Accept': 'application/json',
  },
);
```

### Response

```json
{
  "events": [
    {
      "id": 456,
      "event_code": "13005",
      "event_type": "drawer",
      "description": "Cash drawer opened without sale (nullinnslag)",
      "event_data": {
        "nullinnslag": true,
        "reason": "Change for customer",
        "has_related_charge": false
      },
      "pos_session_id": 123,
      "user_id": 789,
      "occurred_at": "2025-12-09T14:30:00+01:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 50,
    "total": 1
  }
}
```

## Nullinnslag in Reports

Nullinnslag count is automatically included in X and Z reports:

### X Report

```
POST /api/pos-sessions/{id}/x-report
```

Response includes:
```json
{
  "report": {
    "nullinnslag_count": 2,
    ...
  }
}
```

### Z Report

```
POST /api/pos-sessions/{id}/z-report
```

Response includes:
```json
{
  "report": {
    "nullinnslag_count": 2,
    ...
  }
}
```

## Compliance Requirements

Per **Kassasystemforskriften § 2-2**:

- ✅ Opening cash drawer without sale registration (nullinnslag) **must be logged**
- ✅ Nullinnslag events are tracked separately from normal drawer opens
- ✅ Nullinnslag count appears in X and Z reports
- ✅ All nullinnslag events are included in SAF-T export

## FlutterFlow Integration Example

```dart
// Function to report nullinnslag
Future<Map<String, dynamic>> reportNullinnslag({
  required int deviceId,
  required String apiBaseUrl,
  required String authToken,
  String? reason,
}) async {
  try {
    final response = await http.post(
      Uri.parse('$apiBaseUrl/api/pos-devices/$deviceId/cash-drawer/open'),
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $authToken',
        'Accept': 'application/json',
      },
      body: jsonEncode({
        'nullinnslag': true,
        if (reason != null) 'reason': reason,
      }),
    );

    if (response.statusCode >= 200 && response.statusCode < 300) {
      final data = jsonDecode(response.body) as Map<String, dynamic>;
      return {
        'success': true,
        'message': data['message'],
        'event': data['event'],
        'statusCode': response.statusCode,
      };
    } else {
      return {
        'success': false,
        'message': 'Failed to report nullinnslag',
        'statusCode': response.statusCode,
      };
    }
  } catch (e) {
    return {
      'success': false,
      'message': 'Error reporting nullinnslag: ${e.toString()}',
      'statusCode': 0,
    };
  }
}

// Function to query nullinnslag events
Future<Map<String, dynamic>> getNullinnslagEvents({
  required String apiBaseUrl,
  required String authToken,
  int? sessionId,
  String? fromDate,
  String? toDate,
}) async {
  try {
    final queryParams = <String, String>{
      'event_code': '13005',
      'nullinnslag': 'true',
      if (sessionId != null) 'pos_session_id': sessionId.toString(),
      if (fromDate != null) 'from_date': fromDate,
      if (toDate != null) 'to_date': toDate,
    };

    final response = await http.get(
      Uri.parse('$apiBaseUrl/api/pos-events')
        .replace(queryParameters: queryParams),
      headers: {
        'Authorization': 'Bearer $authToken',
        'Accept': 'application/json',
      },
    );

    if (response.statusCode >= 200 && response.statusCode < 300) {
      final data = jsonDecode(response.body) as Map<String, dynamic>;
      return {
        'success': true,
        'events': data['events'],
        'pagination': data['pagination'],
        'statusCode': response.statusCode,
      };
    } else {
      return {
        'success': false,
        'message': 'Failed to fetch nullinnslag events',
        'statusCode': response.statusCode,
      };
    }
  } catch (e) {
    return {
      'success': false,
      'message': 'Error fetching nullinnslag events: ${e.toString()}',
      'statusCode': 0,
    };
  }
}
```

## Common Scenarios

### Scenario 1: Opening Drawer for Change

```dart
// Customer needs change, drawer opens without sale
await reportNullinnslag(
  deviceId: currentDeviceId,
  apiBaseUrl: apiBaseUrl,
  authToken: authToken,
  reason: 'Change for customer',
);
```

### Scenario 2: Manual Cash Count

```dart
// Opening drawer to count cash manually
await reportNullinnslag(
  deviceId: currentDeviceId,
  apiBaseUrl: apiBaseUrl,
  authToken: authToken,
  reason: 'Manual cash count',
);
```

### Scenario 3: Viewing Nullinnslag History

```dart
// Get all nullinnslag events for today
final today = DateTime.now().toIso8601String().split('T')[0];
final result = await getNullinnslagEvents(
  apiBaseUrl: apiBaseUrl,
  authToken: authToken,
  fromDate: today,
  toDate: today,
);

if (result['success']) {
  final events = result['events'] as List;
  print('Found ${events.length} nullinnslag events today');
}
```

## Error Handling

### No Active Session

If you try to report nullinnslag without an active session:

```json
{
  "message": "Active session required for nullinnslag (drawer open without sale)"
}
```

**Solution**: Ensure a POS session is open before reporting nullinnslag.

### Device Not Found

```json
{
  "message": "Device not found"
}
```

**Solution**: Verify the device ID is correct and belongs to the current store.
