# Product Image Upload & Stripe Sync Guide

## Overview

Product images are now handled using Spatie Media Library and automatically synced to Stripe when products are updated.

## How It Works

### 1. Image Upload
- Images are uploaded via Filament form using `SpatieMediaLibraryFileUpload` component
- Images are stored in the `public` disk (accessible at `/storage/...`)
- Multiple images can be uploaded per product (up to 8)
- Images support editing (crop, resize) in the Filament interface

### 2. Stripe Sync
When a product is saved with images:
1. The sync listener detects image changes
2. A queued job (`SyncConnectedProductToStripeJob`) is dispatched
3. The job uploads images to Stripe File API
4. Stripe returns file URLs
5. Product's `images` field is updated with Stripe URLs
6. Product is updated in Stripe with the image URLs

### 3. Image Storage
- **Local Storage**: Images stored via Spatie Media Library in `storage/app/public`
- **Stripe Storage**: Images uploaded to Stripe File API with purpose `product_image`
- **Database**: Stripe image URLs stored in `connected_products.images` JSON field

## Usage

### Uploading Images

1. Edit a product in Filament
2. Use the "Product Images" field to upload images
3. Images can be reordered, edited, or removed
4. Save the product
5. Images will automatically sync to Stripe via queued job

### Viewing Images

- Images are displayed in the product view page (infolist)
- Images are also visible in Stripe dashboard
- Images from Stripe are synced back when products are synced from Stripe

## Technical Details

### Model Configuration
- `ConnectedProduct` implements `HasMedia` interface
- Uses `InteractsWithMedia` trait
- Media collection: `images`
- Accepts: JPEG, PNG, WebP, GIF
- Stored on: `public` disk

### Sync Process
1. **Listener** (`SyncConnectedProductToStripeListener`)
   - Detects when images field changes or media is added/removed
   - Dispatches sync job

2. **Job** (`SyncConnectedProductToStripeJob`)
   - Processes in `stripe-sync` queue
   - Uploads images to Stripe File API
   - Updates product with Stripe URLs

3. **Action** (`UpdateConnectedProductToStripe`)
   - Checks for media library images
   - Calls `UploadProductImagesToStripe` if media exists
   - Updates Stripe product with image URLs

4. **Upload Action** (`UploadProductImagesToStripe`)
   - Reads media files from storage
   - Uploads to Stripe File API using file handles
   - Creates file links if needed
   - Returns array of Stripe image URLs

## Troubleshooting

### Images not syncing to Stripe
1. Check Horizon is running: `php artisan horizon`
2. Check job logs in Horizon dashboard
3. Verify Stripe API keys are configured
4. Check Laravel logs for errors

### Images not displaying
1. Ensure storage link exists: `php artisan storage:link`
2. Check file permissions on `storage/app/public`
3. Verify `APP_URL` is correct in `.env`

### Stripe upload failures
1. Check Stripe API key has correct permissions
2. Verify file sizes are within Stripe limits
3. Check network connectivity
4. Review error logs for specific Stripe API errors

## File Limits

- **Filament**: Up to 8 images per product
- **Stripe**: File size limits apply (check Stripe documentation)
- **Supported formats**: JPEG, PNG, WebP, GIF

## Notes

- Images uploaded via Filament are automatically uploaded to Stripe
- Images synced from Stripe are stored as URLs (not re-uploaded)
- Media library provides image management features (crop, resize, etc.)
- All image operations are queued for better performance

