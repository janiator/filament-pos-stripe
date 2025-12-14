# Embedding POS Sessions List in FlutterFlow

## Overview

This guide shows how to embed the POS sessions list from the Filament admin panel into a FlutterFlow app using iframe embedding. This allows you to display the current POS session and session history directly in your FlutterFlow application.

## API Endpoints

### Get Current POS Session

**Endpoint:** `GET /api/pos-sessions/current`

**Authentication:** Required (Bearer token)

**Query Parameters:**
- `pos_device_id` (required) - The ID of the POS device

**Response:**
```json
{
  "id": 123,
  "session_number": "000001",
  "status": "open",
  "opened_at": "2025-12-10T08:00:00+01:00",
  "opening_balance": 50000,
  "expected_cash": 150000,
  "device": {
    "id": 1,
    "name": "Main Cash Register"
  },
  "cashier": {
    "id": 5,
    "name": "John Doe"
  },
  "transactions_count": 45,
  "total_amount": 200000
}
```

### List POS Sessions

**Endpoint:** `GET /api/pos-sessions`

**Authentication:** Required (Bearer token)

**Query Parameters:**
- `status` (optional) - Filter by status: `open`, `closed`, `abandoned`
- `date` (optional) - Filter by opening date (YYYY-MM-DD)
- `pos_device_id` (optional) - Filter by device ID
- `per_page` (optional) - Results per page (default: 20)

**Response:**
```json
{
  "sessions": [
    {
      "id": 123,
      "session_number": "000001",
      "status": "open",
      "opened_at": "2025-12-10T08:00:00+01:00",
      "closed_at": null,
      "device": {
        "id": 1,
        "name": "Main Cash Register"
      },
      "cashier": {
        "id": 5,
        "name": "John Doe"
      },
      "transactions_count": 45,
      "total_amount": 200000
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100
  }
}
```

## Filament Embed Routes

### Embed POS Sessions List

**Route:** `/app/store/{tenant}/pos-sessions/embed`

**Authentication:** Required (web session)

**Parameters:**
- `tenant` - Store slug (e.g., "my-store")
- Query parameters (via table filters):
  - `tableFilters[status][value]` (optional) - Filter by status: `open`, `closed`, `abandoned`
  - `tableFilters[pos_device_id][value]` (optional) - Filter by device ID

**Usage in FlutterFlow:**

1. **Get Filament Auth Token:**
   - Use the existing `/api/auth/login` endpoint to authenticate
   - Store the token for subsequent requests

2. **Get Filament Session:**
   - Use `/filament-auth/{token}?store={store_slug}&redirect=pos-sessions/embed` to get a web session
   - This redirects to the Filament panel with a valid session

3. **Embed in FlutterFlow:**
   - Use an `WebView` widget in FlutterFlow
   - Set the URL to: `https://your-domain.com/app/store/{store_slug}/pos-sessions/embed?tableFilters[status][value]=open`
   - The iframe will display the POS sessions list filtered to open sessions

**Example URLs:**
- All sessions: `https://your-domain.com/app/store/my-store/pos-sessions/embed`
- Open sessions only: `https://your-domain.com/app/store/my-store/pos-sessions/embed?tableFilters[status][value]=open`
- Closed sessions: `https://your-domain.com/app/store/my-store/pos-sessions/embed?tableFilters[status][value]=closed`

### Example FlutterFlow Implementation

```dart
// 1. Authenticate and get Filament token
final authResponse = await FFAppState().apiClient.post(
  '/api/auth/login',
  data: {
    'email': FFAppState().userEmail,
    'password': FFAppState().userPassword,
  },
);

final token = authResponse.data['token'];

// 2. Get Filament session URL
final embedUrl = 'https://your-domain.com/filament-auth/$token?store=${FFAppState().storeSlug}&redirect=pos-sessions/embed&status=open';

// 3. Display in WebView
WebView(
  initialUrl: embedUrl,
  javascriptMode: JavascriptMode.unrestricted,
)
```

## Creating the Embed Route

The embed route needs to be created in the Filament panel. Here's how to add it:

### Embed Route (Already Implemented)

The embed route is already implemented in the Filament resource:

**URL:** `/app/store/{tenant}/pos-sessions/embed`

**File:** `app/Filament/Resources/PosSessions/Pages/EmbedPosSessions.php`

This page extends `ListRecords` and displays the POS sessions table without navigation, making it perfect for embedding in FlutterFlow.

**Alternative:** You can also use the existing POS sessions list with query parameters:

**URL:** `/app/store/{tenant}/pos-sessions?tableFilters[status][value]=open`

## Direct API Integration (Recommended for FlutterFlow)

Instead of embedding Filament, you can directly use the API endpoints to build a custom UI in FlutterFlow:

### 1. Get Current Session

```dart
// Custom Action: getCurrentPosSession
Future<Map<String, dynamic>?> getCurrentPosSession(int deviceId) async {
  final response = await FFAppState().apiClient.get(
    '/api/pos-sessions/current',
    queryParameters: {
      'pos_device_id': deviceId,
    },
  );
  
  return response.data;
}
```

### 2. List Sessions

```dart
// Custom Action: listPosSessions
Future<List<Map<String, dynamic>>> listPosSessions({
  String? status,
  String? date,
  int? deviceId,
}) async {
  final queryParams = <String, dynamic>{};
  if (status != null) queryParams['status'] = status;
  if (date != null) queryParams['date'] = date;
  if (deviceId != null) queryParams['pos_device_id'] = deviceId;
  
  final response = await FFAppState().apiClient.get(
    '/api/pos-sessions',
    queryParameters: queryParams,
  );
  
  return List<Map<String, dynamic>>.from(response.data['sessions']);
}
```

### 3. Display in FlutterFlow UI

```dart
// In your FlutterFlow page
final currentSession = await getCurrentPosSession(FFAppState().posDeviceId);

if (currentSession != null) {
  // Display current session info
  Text('Session: ${currentSession['session_number']}');
  Text('Status: ${currentSession['status']}');
  Text('Total: ${(currentSession['total_amount'] / 100).toStringAsFixed(2)} NOK');
}

// List all open sessions
final openSessions = await listPosSessions(status: 'open');

ListView.builder(
  itemCount: openSessions.length,
  itemBuilder: (context, index) {
    final session = openSessions[index];
    return ListTile(
      title: Text('Session ${session['session_number']}'),
      subtitle: Text('Opened: ${session['opened_at']}'),
      trailing: Text('${(session['total_amount'] / 100).toStringAsFixed(2)} NOK'),
    );
  },
);
```

## Authentication

All API endpoints require authentication via Bearer token:

```dart
// Set authorization header
FFAppState().apiClient.options.headers['Authorization'] = 'Bearer ${FFAppState().apiToken}';
```

## Filtering Options

### By Status
- `status=open` - Only open sessions
- `status=closed` - Only closed sessions
- `status=abandoned` - Only abandoned sessions

### By Date
- `date=2025-12-10` - Sessions opened on specific date

### By Device
- `pos_device_id=1` - Sessions for specific device

### Combined Filters
```
GET /api/pos-sessions?status=open&date=2025-12-10&pos_device_id=1
```

## Response Format

All dates are returned in ISO 8601 format with Oslo timezone offset:
- `2025-12-10T08:00:00+01:00`

All amounts are in øre (smallest currency unit):
- `200000` = 2000.00 NOK

## Error Handling

```dart
try {
  final session = await getCurrentPosSession(deviceId);
  // Handle session data
} catch (e) {
  if (e.response?.statusCode == 404) {
    // No open session found
    showMessage('No active session');
  } else {
    // Other error
    showError('Failed to load session: ${e.message}');
  }
}
```

## References

- [POS Sessions API Documentation](../api/POS_SESSIONS_API.md)
- [Filament Embedding Guide](./FILAMENT_IFRAME_EMBEDDING.md)
- [Authentication Guide](./AUTHENTICATION.md)

