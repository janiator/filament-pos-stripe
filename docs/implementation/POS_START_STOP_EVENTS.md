# POS Start/Stop Events - Best Practices Guide

## Overview

This document outlines best practices for handling POS application start and stop events to ensure proper audit trail compliance and device status tracking.

## Endpoints

### Start Event
- **Endpoint:** `POST /api/pos-devices/{id}/start`
- **Authentication:** Required (Sanctum)
- **When to Call:** When the POS application initializes/starts
- **Automatic Detection:** A start event is automatically created when a heartbeat is received after 10+ minutes of inactivity

### Shutdown Event
- **Endpoint:** `POST /api/pos-devices/{id}/shutdown`
- **Authentication:** Required (Sanctum)
- **When to Call:** When the POS application closes/shuts down
- **Automatic Detection:** A stop event is automatically created by a scheduled job if no heartbeat is received for 15+ minutes

### Heartbeat Endpoint
- **Endpoint:** `POST /api/pos-devices/{id}/heartbeat`
- **Authentication:** Required (Sanctum)
- **When to Call:** Periodically (recommended every 5 minutes) to keep device status active
- **Automatic Features:**
  - Automatically creates a start event if device was inactive for 10+ minutes
  - Updates `last_seen_at` timestamp
  - Optionally updates device status and metadata

## Automatic Event Detection

The system automatically detects POS start and stop events based on heartbeat activity:

### Automatic Start Event
- **Trigger:** Heartbeat received after 10+ minutes of inactivity
- **Location:** `POST /api/pos-devices/{id}/heartbeat` endpoint
- **Behavior:**
  - Checks if `last_seen_at` is older than 10 minutes
  - Creates a start event (13001) if device was inactive
  - Prevents duplicates by checking for recent start events (within 30 seconds)
  - Includes `auto_detected: true` in event data
  - Includes inactivity duration in event data

### Automatic Stop Event
- **Trigger:** No heartbeat received for 15+ minutes
- **Location:** Scheduled command `pos:check-inactive-devices` (runs every 5 minutes)
- **Behavior:**
  - Checks all devices with `last_seen_at` older than 15 minutes
  - Only processes devices with status `active` or `inactive` (not already `offline`)
  - Prevents duplicates by checking for recent stop events (within 5 minutes)
  - Creates a stop event (13002) for each inactive device
  - Updates device status to `offline`
  - Includes `auto_detected: true` in event data
  - Includes inactivity duration in event data

### Configuration
- **Inactivity threshold for start detection:** 10 minutes (hardcoded in heartbeat endpoint)
- **Inactivity threshold for stop detection:** 15 minutes (configurable via `--timeout` option)
- **Check frequency:** Every 5 minutes (scheduled job)

## Implementation Details

### Start Event Features

1. **Automatic Device Status Update**
   - Device status is automatically set to `active`
   - `last_seen_at` timestamp is updated

2. **Duplicate Prevention**
   - If a start event was logged within the last 30 seconds, the endpoint returns the existing event info instead of creating a duplicate
   - This prevents rapid restart scenarios from flooding the audit log

3. **User Handling**
   - If the app starts before user login, `user_id` will be `null` (this is acceptable)
   - The event will still be logged successfully
   - `event_data.user_logged_in` indicates whether a user was logged in at start time

4. **Session Linking**
   - If there's an open session for the device, it's automatically linked to the event
   - The response includes current session information if available

### Shutdown Event Features

1. **Automatic Device Status Update**
   - Device status is automatically set to `offline`
   - `last_seen_at` timestamp is updated

2. **Open Session Warning**
   - If the device has an open session when shutting down, the response includes a warning
   - This helps identify sessions that should be closed before shutdown

3. **Crash Handling**
   - If the app crashes before logout, `user_id` can be `null` (this is acceptable)
   - The event will still be logged successfully
   - `event_data.user_logged_in` indicates whether a user was logged in at shutdown time

## Frontend Integration

### Flutter/Dart Example

```dart
class PosLifecycleManager {
  final String deviceId;
  final ApiClient apiClient;
  
  // Call when app starts/initializes
  Future<void> logApplicationStart() async {
    try {
      final response = await apiClient.post(
        '/pos-devices/$deviceId/start',
      );
      
      if (response.data['warning'] != null) {
        // Handle duplicate event warning
        print('Start event already logged recently');
      }
      
      // Check for open session
      if (response.data['current_session'] != null) {
        // Handle existing open session
        final session = response.data['current_session'];
        print('Found open session: ${session['session_number']}');
      }
    } catch (e) {
      // Log error but don't block app startup
      print('Failed to log application start: $e');
    }
  }
  
  // Call when app shuts down/closes
  Future<void> logApplicationShutdown() async {
    try {
      final response = await apiClient.post(
        '/pos-devices/$deviceId/shutdown',
      );
      
      // Check for open session warning
      if (response.data['warning'] != null) {
        print('Warning: ${response.data['warning']}');
        // Optionally prompt user to close session
      }
    } catch (e) {
      // Log error but don't block app shutdown
      print('Failed to log application shutdown: $e');
    }
  }
}
```

### React Native Example

```javascript
import { useEffect } from 'react';
import { AppState } from 'react-native';

class PosLifecycleManager {
  constructor(deviceId, apiClient) {
    this.deviceId = deviceId;
    this.apiClient = apiClient;
  }
  
  async logApplicationStart() {
    try {
      const response = await this.apiClient.post(
        `/pos-devices/${this.deviceId}/start`
      );
      
      if (response.data.warning) {
        console.log('Start event already logged recently');
      }
      
      if (response.data.current_session) {
        console.log('Found open session:', response.data.current_session);
      }
    } catch (error) {
      console.error('Failed to log application start:', error);
    }
  }
  
  async logApplicationShutdown() {
    try {
      const response = await this.apiClient.post(
        `/pos-devices/${this.deviceId}/shutdown`
      );
      
      if (response.data.warning) {
        console.warn('Warning:', response.data.warning);
      }
    } catch (error) {
      console.error('Failed to log application shutdown:', error);
    }
  }
}

// Usage in React Native
export function usePosLifecycle(deviceId, apiClient) {
  useEffect(() => {
    const manager = new PosLifecycleManager(deviceId, apiClient);
    
    // Log start when component mounts
    manager.logApplicationStart();
    
    // Handle app state changes
    const subscription = AppState.addEventListener('change', (nextAppState) => {
      if (nextAppState === 'background' || nextAppState === 'inactive') {
        // Log shutdown when app goes to background
        manager.logApplicationShutdown();
      }
    });
    
    // Log shutdown on unmount
    return () => {
      manager.logApplicationShutdown();
      subscription.remove();
    };
  }, [deviceId, apiClient]);
}
```

## Best Practices

### 1. Call Start Event Early
- Call the start event as soon as the app initializes, even before user login
- This ensures the device status is updated immediately
- The event will have `user_id: null` if called before login, which is acceptable

### 2. Handle Shutdown Events Properly
- Call shutdown event in multiple lifecycle hooks:
  - App termination handler
  - App pause handler (for mobile apps)
  - App close handler
  - Before logout (if user explicitly logs out)
- This ensures shutdown events are logged even if the app crashes

### 3. Error Handling
- Don't block app startup/shutdown if event logging fails
- Log errors for debugging but allow the app to continue
- Consider retry logic for network failures

### 4. Session Management
- Check for open sessions in the start event response
- Prompt user to close sessions before shutdown if needed
- Handle abandoned sessions appropriately

### 5. Network Considerations
- For mobile apps, handle offline scenarios gracefully
- Consider queuing events if network is unavailable
- Sync queued events when network is restored

## Common Scenarios

### Scenario 1: Normal Startup
1. App starts
2. Call `/start` endpoint
3. Device status updated to `active`
4. Event logged with user_id (if logged in) or null (if not logged in)
5. Check for open session in response

### Scenario 2: Rapid Restart
1. App starts
2. Call `/start` endpoint
3. App crashes/restarts within 30 seconds
4. Call `/start` endpoint again
5. Backend returns existing event info (no duplicate created)

### Scenario 3: Normal Shutdown
1. User closes app
2. Call `/shutdown` endpoint
3. Device status updated to `offline`
4. Event logged
5. If open session exists, warning returned

### Scenario 4: Crash Shutdown
1. App crashes unexpectedly
2. App lifecycle hook triggers
3. Call `/shutdown` endpoint (may fail if network unavailable)
4. If shutdown event is not logged, the scheduled job will detect inactivity after 15 minutes
5. Automatic stop event is created and device status updated to `offline`

### Scenario 5: Automatic Start Detection (Heartbeat-Based)
1. Device stops sending heartbeats (app closed, network issue, etc.)
2. After 15 minutes of no heartbeats, scheduled job creates stop event
3. Device status updated to `offline`
4. Later, device sends heartbeat again (app reopened, network restored)
5. Heartbeat endpoint detects 10+ minutes of inactivity
6. Automatic start event is created
7. Device status updated to `active`

## Troubleshooting

### Issue: Duplicate Start Events
- **Cause:** Multiple rapid calls to start endpoint
- **Solution:** Backend prevents duplicates within 30 seconds. Check if warning is returned.

### Issue: Missing Shutdown Events
- **Cause:** App crashes before shutdown hook executes
- **Solution:** The system automatically detects inactive devices after 15 minutes and creates stop events. However, it's still recommended to call the shutdown endpoint explicitly when possible.

### Issue: Device Status Not Updating
- **Cause:** Start/shutdown events not being called
- **Solution:** Verify lifecycle hooks are properly configured. Check network connectivity.

### Issue: Open Sessions After Shutdown
- **Cause:** Session not closed before shutdown
- **Solution:** Check shutdown response for warnings. Implement session cleanup logic.

## Compliance Notes

- Both events are required for SAF-T compliance (Kassasystemforskriften)
- Events are immutable once created (cannot be deleted or modified)
- All events are included in SAF-T export
- Events must have accurate timestamps (`occurred_at`)

## Related Documentation

- [POS Session Management](./POS_SESSION_MANAGEMENT.md)
- [Audit Log Implementation Summary](../compliance/AUDIT_LOG_IMPLEMENTATION_SUMMARY.md)
- [SAF-T Event Codes Mapping](../saf-t/SAF_T_EVENT_CODES_MAPPING.md)
