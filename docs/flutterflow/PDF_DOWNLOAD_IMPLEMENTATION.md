# PDF Download Implementation for POS Reports

## Overview

This document describes the implementation of Flutter file download functionality for POS report PDFs, replacing the webview-based download button with a native Flutter download action.

## Changes Made

### Backend Changes

1. **New API Endpoints** (`routes/api.php`):
   - `GET /api/pos-sessions/{id}/x-report/pdf` - Download X-report PDF with Bearer token auth
   - `GET /api/pos-sessions/{id}/z-report/pdf` - Download Z-report PDF with Bearer token auth

2. **New Controller Methods** (`app/Http/Controllers/ReportController.php`):
   - `downloadXReportPdfApi()` - API endpoint for X-report PDF download
   - `downloadZReportPdfApi()` - API endpoint for Z-report PDF download

   These methods:
   - Accept Bearer token authentication (via `auth:sanctum` middleware)
   - Accept `store` query parameter or `X-Store-Slug` header
   - Generate and return PDF files with proper logging

### Flutter Changes

1. **Updated `PosReportWebView` Widget**:
   - Added download button overlay (top-right corner)
   - Implemented `_downloadPdf()` method that:
     - Uses Bearer token authentication
     - Downloads PDF from API endpoint
     - Saves to device storage (Documents directory on iOS, External storage on Android)
     - Shows success/error messages via SnackBar
     - Handles loading state during download

## Required Flutter Packages

The following packages need to be added to your Flutter project's `pubspec.yaml`:

```yaml
dependencies:
  http: ^1.1.0
  path_provider: ^2.1.1
  permission_handler: ^11.0.1
```

### Package Installation

1. Add the packages to `pubspec.yaml`
2. Run `flutter pub get`
3. For Android, add permissions to `android/app/src/main/AndroidManifest.xml`:
   ```xml
   <uses-permission android:name="android.permission.WRITE_EXTERNAL_STORAGE" />
   <uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE" />
   ```
   Note: For Android 11+ (API 30+), you may need to use scoped storage or request `MANAGE_EXTERNAL_STORAGE` permission.

4. For iOS, no additional permissions are required for saving to Documents directory.

## Usage

The download button appears as a floating action button in the top-right corner of the webview. When clicked:

1. Shows loading indicator
2. Authenticates with Bearer token
3. Downloads PDF from API
4. Saves to device storage
5. Shows success message with filename

## File Storage Locations

- **iOS**: `Documents` directory (accessible via Files app)
- **Android**: External storage directory (accessible via file manager)

## Error Handling

The implementation handles:
- Authentication failures (401)
- Authorization failures (403)
- Not found errors (404)
- Network errors
- Storage permission denials
- File system errors

All errors are displayed to the user via SnackBar messages.

## API Authentication

The PDF download endpoints use:
- **Authentication**: Bearer token (via `Authorization: Bearer {token}` header)
- **Store Selection**: Via `store` query parameter or `X-Store-Slug` header
- **Authorization**: Verifies user has access to the store and session

## Testing

To test the implementation:

1. Ensure user is authenticated with valid token
2. Open a POS report in the webview
3. Click the download button (top-right)
4. Verify PDF is downloaded and saved
5. Check file location on device

## Future Improvements

Potential enhancements:
- Use `share_plus` package to allow sharing the PDF
- Add option to open PDF in external viewer
- Support for Android scoped storage (API 30+)
- Progress indicator for large files
- Download queue for multiple reports
