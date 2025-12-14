# Embedding Filament Panel in FlutterFlow via iframe

This guide explains how to embed the Filament admin panel inside your FlutterFlow app using an iframe with token-based authentication.

## Overview

The Filament panel uses session-based authentication (cookies), but your FlutterFlow app uses API tokens. We've created a bridge route that accepts a token, authenticates the user, creates a session, and redirects to the Filament panel.

## Authentication Flow

1. **User logs in via API** - Get token from `/api/auth/login`
2. **Embed Filament in iframe** - Use the token authentication route
3. **Automatic session creation** - The route creates a web session
4. **Redirect to Filament** - User is automatically logged into Filament panel

## Implementation

### Step 1: Get Authentication Token

First, authenticate the user via the API:

```dart
// Login and get token
final loginResponse = await http.post(
  Uri.parse('$apiBaseUrl/auth/login'),
  headers: {'Content-Type': 'application/json'},
  body: jsonEncode({
    'email': userEmail,
    'password': userPassword,
  }),
);

final responseData = jsonDecode(loginResponse.body);
final token = responseData['token'];
final currentStore = responseData['current_store'];
```

### Step 2: Build Filament URL

Construct the Filament authentication URL:

```dart
// Base URL for your Filament installation
final filamentBaseUrl = 'https://your-domain.com'; // or 'https://pos.visivo.no'

// Build authentication URL with token
final filamentAuthUrl = '$filamentBaseUrl/filament-auth/$token';

// Optional: Specify store/tenant
final storeSlug = currentStore['slug'];
final filamentUrlWithStore = '$filamentAuthUrl?store=$storeSlug';

// Optional: Redirect to specific Filament page after login
final filamentUrlWithRedirect = '$filamentAuthUrl?store=$storeSlug&redirect=pos-sessions';

// Optional: Use embed panel (recommended for WebView - no navigation, stays in embed mode)
final filamentUrlEmbedded = '$filamentAuthUrl?store=$storeSlug&redirect=pos-sessions&embed=1';
```

### Step 3: Embed in iframe

Use a WebView widget in FlutterFlow to embed the Filament panel:

```dart
// In FlutterFlow, use WebView widget
// Use embed=1 to hide navigation and show only the resource content
WebView(
  initialUrl: filamentUrlEmbedded, // Use embed mode for clean view
  javascriptMode: JavascriptMode.unrestricted,
  // Enable cookies for session management
  onWebViewCreated: (WebViewController controller) {
    // Cookies are automatically handled by the WebView
  },
)
```

**Embed Panel**: When using `embed=1` or `minimal=1` parameter, you'll be routed to a separate Filament panel (`/embed/`) that has:
- No sidebar navigation
- No top navigation/header
- No account widget/user menu
- No breadcrumbs
- No footer

Only the resource content (tables, forms, etc.) will be displayed. **All navigation within the embed panel stays in embed mode**, making it perfect for embedding in WebView/iframe.

### Step 4: Handle Navigation (Optional)

If you want to navigate to specific Filament pages:

```dart
// Navigate to POS Sessions list
final posSessionsUrl = '$filamentAuthUrl?store=$storeSlug&redirect=pos-sessions';

// Navigate to a specific POS Session
final sessionUrl = '$filamentAuthUrl?store=$storeSlug&redirect=pos-sessions/123';

// Navigate to Receipts
final receiptsUrl = '$filamentAuthUrl?store=$storeSlug&redirect=receipts';
```

## URL Parameters

The `/filament-auth/{token}` route accepts the following query parameters:

- **`store`** (optional): Store slug to use as tenant. If not provided, uses user's current store or first available store.
- **`redirect`** (optional): Path to redirect to after authentication. Examples:
  - `pos-sessions` - Go to POS Sessions list
  - `pos-sessions/123` - View specific POS Session
  - `pos-purchases` - Go to POS Purchases list
  - `pos-purchases/456` - View specific purchase
  - `receipts` - Go to Receipts list
  - `receipts/456/preview` - Preview specific receipt
- **`embed`** or **`minimal`** (optional): Use the dedicated embed panel (`/embed/`) instead of the main panel (`/app/`). The embed panel has no sidebar, header, or navigation - perfect for WebView/iframe embedding. Use `embed=1` or `minimal=1`. **Recommended for WebView/iframe embedding.**

## Embed Panel (Minimal UI)

When embedding Filament in a WebView/iframe, use the **separate embed panel** which is designed specifically for embedded views with minimal UI.

### How to Use the Embed Panel

Add `embed=1` or `minimal=1` to your authentication URL. This automatically routes you to the dedicated embed panel:

```dart
// Enable embed panel (automatically uses /embed/ instead of /app/)
final embeddedUrl = '$filamentAuthUrl?store=$storeSlug&redirect=pos-purchases&embed=1';
```

### What's Different in the Embed Panel

The embed panel is a **separate Filament panel** (`/embed/`) that:

- ✅ **No sidebar navigation** - Sidebar is completely hidden
- ✅ **No top navigation/header** - Top bar is hidden
- ✅ **No account widget** - User menu is hidden
- ✅ **No breadcrumbs** - Breadcrumb trail is hidden
- ✅ **No footer** - Footer elements are hidden
- ✅ **Full-width content** - Content takes 100% width
- ✅ **Persistent across navigation** - All links stay within the embed panel

### Benefits of Separate Panel

Unlike CSS-based hiding, the embed panel:
- **Persists across navigation** - All links automatically stay in embed mode
- **Cleaner implementation** - No CSS hacks needed
- **Better performance** - No JavaScript needed to hide elements
- **More maintainable** - Uses Filament's native panel system

### What Remains Visible

- ✅ **Resource content** - Tables, forms, infolists
- ✅ **Action buttons** - Edit, view, delete buttons (if enabled)
- ✅ **Filters and search** - Table filters and search functionality
- ✅ **Pagination** - Table pagination controls

### Example: Embedded POS Purchases List

```dart
// Get token from login
final token = await getAuthToken();
final storeSlug = currentStore['slug'];

// Build embedded URL (automatically uses embed panel)
final embeddedPurchasesUrl = 
    'https://your-domain.com/filament-auth/$token?store=$storeSlug&redirect=pos-purchases&embed=1';

// Use in WebView - all navigation stays in embed mode
WebView(initialUrl: embeddedPurchasesUrl)
```

### Example: Embedded Specific Purchase View

```dart
final purchaseId = 123; // ConnectedCharge ID
final embeddedPurchaseUrl = 
    'https://your-domain.com/filament-auth/$token?store=$storeSlug&redirect=pos-purchases/$purchaseId&embed=1';

WebView(initialUrl: embeddedPurchaseUrl)
// Clicking any link within this view stays in embed mode!
```

## Cookie Configuration for iPad

Cookies work automatically when:
- FlutterFlow app and Filament are on the **same domain**
- Using HTTPS (required for Secure cookies)

If you're using different domains/subdomains, you may need to configure:

```env
SESSION_SAME_SITE=none
SESSION_SECURE_COOKIE=true
```

This allows cookies to work across different domains (e.g., `app.example.com` → `admin.example.com`).

## Security Considerations

1. **Token Security**: Tokens are passed in the URL. While this works for iframe embedding, be aware that tokens may appear in browser history and server logs.

2. **Token Expiration**: Tokens expire based on your Sanctum configuration. Users will need to re-authenticate when tokens expire.

3. **HTTPS Required**: Always use HTTPS in production to protect tokens and session cookies.

4. **Same-Origin Policy**: If embedding from a different domain, ensure CORS and cookie settings are properly configured.

## Example FlutterFlow Implementation

### Custom Action: Get Filament URL

```dart
Future<String> getFilamentUrl(String token, {String? storeSlug, String? redirect}) async {
  final baseUrl = 'https://your-domain.com';
  var url = '$baseUrl/filament-auth/$token';
  
  final params = <String>[];
  if (storeSlug != null) {
    params.add('store=$storeSlug');
  }
  if (redirect != null) {
    params.add('redirect=$redirect');
  }
  
  if (params.isNotEmpty) {
    url += '?${params.join('&')}';
  }
  
  return url;
}
```

### Usage in FlutterFlow

1. **Create a WebView page** in FlutterFlow
2. **Set initial URL** using the custom action above
3. **Pass token** from your app state
4. **Handle navigation** by updating the WebView URL

## Troubleshooting

### Cookies Not Working

- **Check domain**: Ensure FlutterFlow app and Filament are on the same domain
- **Check HTTPS**: Cookies require HTTPS in production
- **Check SameSite**: May need `SESSION_SAME_SITE=none` for cross-domain

### Authentication Fails

- **Check token**: Verify token is valid and not expired
- **Check permissions**: Ensure user has access to Filament panel
- **Check stores**: Ensure user has at least one accessible store

### Redirect Not Working

- **Check path**: Ensure redirect path is valid Filament route
- **Check tenant**: Ensure store slug matches user's accessible stores

## API Response Format

The route returns a redirect (302) by default. If called with `Accept: application/json`, it returns:

```json
{
  "message": "Authentication successful",
  "redirect_url": "/app/store/my-store-slug",
  "store": {
    "id": 1,
    "slug": "my-store-slug",
    "name": "My Store"
  }
}
```

## Notes

- The authentication route creates a **persistent session** (remember me enabled)
- Sessions expire based on your `SESSION_LIFETIME` configuration
- Users can access Filament panel directly after authentication until session expires
- Multiple tabs/windows will share the same session

