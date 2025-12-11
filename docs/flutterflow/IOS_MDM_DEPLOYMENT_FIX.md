# iOS MDM Deployment Fix: "Integrity Can't Be Verified"

## Problem

When deploying a Flutter app via Jamf MDM, you get the error:
> "could not install POSitiv. This app cannot be installed because its integrity can't be verified"

## Root Cause

This error occurs when:
1. The app is signed with an **App Store Distribution** certificate instead of an **Ad-Hoc Distribution** certificate
2. The provisioning profile doesn't include the target device's UDID (required for Ad-Hoc)
3. The provisioning profile doesn't match the deployment method (MDM with Ad-Hoc requires device UDIDs)
4. The signing certificate isn't trusted on the device
5. The IPA wasn't properly signed for ad-hoc distribution

## Solution: Ad-Hoc Distribution (Standard Apple Developer Account)

### Important: Ad-Hoc Requirements

- ✅ Requires **Standard Apple Developer Account** ($99/year)
- ✅ **Device UDIDs must be registered** in the provisioning profile
- ✅ Maximum **100 devices** per year per App ID
- ✅ Works with Jamf MDM
- ❌ Cannot distribute via App Store (different build required)

### Step 1: Get Device UDIDs from Jamf

You need the UDID of each iPad you'll deploy to:

1. In Jamf Pro, go to **Mobile Devices** → Select your iPad
2. View device details → Find **UDID** (also called "Serial Number" or "Device Identifier")
3. Copy all UDIDs you need to deploy to

**Alternative**: Get UDID directly from iPad:
- Connect iPad to Mac
- Open **Finder** → Select iPad → Click **Serial Number** to reveal UDID
- Or use: `system_profiler SPUSBDataType | grep -A 11 iPad`

### Step 2: Create/Download iOS Distribution Certificate

1. Go to [Apple Developer Portal](https://developer.apple.com/account/resources/certificates/list)
2. Create a new certificate:
   - Type: **iOS Distribution** (for Ad-Hoc/App Store)
   - Follow the certificate creation wizard
3. Download and install the certificate in Keychain Access (double-click the `.cer` file)

### Step 3: Create Ad-Hoc Provisioning Profile

1. Go to [Provisioning Profiles](https://developer.apple.com/account/resources/profiles/list)
2. Click **+** to create new profile
3. Select **Ad Hoc** distribution type
4. Select your **App ID** (e.g., `com.yourcompany.positiv`)
5. Select your **iOS Distribution** certificate
6. **IMPORTANT**: Select all device UDIDs you want to deploy to
   - You can add devices later, but you'll need to regenerate the profile
   - Maximum 100 devices per App ID per year
7. Name the profile (e.g., "POSitiv Ad-Hoc")
8. Download the provisioning profile

### Step 4: Configure Xcode Project

#### Step 1: Verify Your Apple Developer Account Type

- **Enterprise Account**: Required for In-House distribution (no App Store)
- **Standard Account**: Can use Ad-Hoc or App Store distribution (not ideal for MDM)

#### Step 2: Create/Download Enterprise Distribution Certificate

1. Go to [Apple Developer Portal](https://developer.apple.com/account/resources/certificates/list)
2. Create a new certificate:
   - Type: **Apple Distribution** (for Enterprise accounts) or **iOS Distribution** (for standard accounts)
   - For Enterprise accounts: Use **In-House Distribution** option
3. Download and install the certificate in Keychain Access

#### Step 3: Create In-House Provisioning Profile

1. Go to [Provisioning Profiles](https://developer.apple.com/account/resources/profiles/list)
2. Create new profile:
   - Type: **In-House** (Enterprise) or **Ad-Hoc** (Standard account)
   - Select your App ID
   - Select your Distribution certificate
   - For Ad-Hoc: Add device UDIDs (or use wildcard)
3. Download the provisioning profile

#### Step 4: Configure Xcode Project

1. Open your Flutter project's iOS folder in Xcode:
   ```bash
   cd ios
   open Runner.xcworkspace
   ```

2. Select the **Runner** target → **Signing & Capabilities**

3. Configure:
   - **Team**: Select your Apple Developer team
   - **Signing Certificate**: Choose "Apple Distribution" or "iPhone Distribution"
   - **Provisioning Profile**: Select your **Ad-Hoc** profile (must include device UDIDs)
   - **Automatically manage signing**: **UNCHECK** this (manual signing for MDM)

4. Verify **Build Settings**:
   - `CODE_SIGN_IDENTITY`: Should be "Apple Distribution" or "iPhone Distribution"
   - `PROVISIONING_PROFILE_SPECIFIER`: Should match your provisioning profile name
   - `DEVELOPMENT_TEAM`: Your team ID

#### Step 5: Build IPA with Ad-Hoc Signing

**Method 1: Using Flutter Build Command (Recommended)**

```bash
# Clean previous builds
flutter clean

# Build with ad-hoc export method
flutter build ipa --release --export-method=ad-hoc

# The IPA will be at: build/ios/ipa/POSitiv.ipa
```

**Method 2: Using Xcode Configuration**

If you've configured signing in Xcode (Step 4), you can also use:

```bash
flutter clean
flutter build ipa --release
```

**Important**: 
- The build must use the Ad-Hoc provisioning profile you configured in Xcode
- The provisioning profile **must include all device UDIDs** you plan to deploy to
- Verify the export method is set to `ad-hoc` (not `app-store` or `enterprise`)

### Option 2: Re-sign Existing IPA (If Already Built)

If you already have an IPA but it's signed incorrectly:

#### Method A: Using Xcode Organizer (Easiest)

1. Open Xcode → **Window** → **Organizer** (or `Cmd+Shift+9`)
2. Drag your IPA into Organizer, or click **+** → **Add**
3. Select the IPA → Click **Distribute App**
4. Choose **Ad Hoc** distribution method
5. Select your **Ad-Hoc provisioning profile** (must include device UDIDs)
6. Choose export location
7. Export the re-signed IPA

#### Method B: Using Command Line (codesign)

```bash
# Extract IPA
unzip POSitiv.ipa -d temp_ipa

# Remove old signature
rm -rf temp_ipa/Payload/POSitiv.app/_CodeSignature

# Re-sign with your certificate
codesign --force --sign "Apple Distribution: Your Name" \
  --entitlements ios/Runner/Runner.entitlements \
  temp_ipa/Payload/POSitiv.app

# Verify signature
codesign --verify --verbose temp_ipa/Payload/POSitiv.app

# Re-package IPA
cd temp_ipa
zip -r ../POSitiv-resigned.ipa Payload
cd ..
rm -rf temp_ipa
```

### Option 3: Verify Export Method in Archive

If building through Xcode directly:

1. Build archive: `Product` → `Archive`
2. In Organizer, select archive → `Distribute App`
3. Choose **Ad Hoc** (not App Store or Enterprise)
4. Select provisioning profile with device UDIDs
5. Export IPA

### Option 4: Use Fastlane (Advanced)

Create a `Fastfile` in `ios/`:

```ruby
lane :build_ad_hoc do
  build_app(
    scheme: "Runner",
    export_method: "ad-hoc",
    export_options: {
      provisioningProfiles: {
        "com.yourcompany.positiv" => "POSitiv Ad-Hoc"  # Your Ad-Hoc profile name
      },
      method: "ad-hoc"
    }
  )
end
```

Then run:
```bash
cd ios
fastlane build_ad_hoc
```

## Jamf MDM Configuration

### 1. Upload IPA to Jamf

1. In Jamf Pro, go to **Mobile Device Apps**
2. Click **+ New** → **App Store apps and purchased apps** → **Internal App**
3. Upload your IPA file
4. Configure app details

### 2. Configure App Installation

1. Go to **Configuration Profiles** → **App Installation**
2. Ensure the app is configured for **Device Assignment**
3. Verify the app is assigned to the correct device group

### 3. Trust Developer Certificate (On Device)

After installation, users may need to trust the developer:

1. On iPad: **Settings** → **General** → **VPN & Device Management** (or **Device Management**)
2. Find your developer certificate (shows your Apple Developer account name)
3. Tap **Trust "Your Company Name"**
4. Confirm trust
5. The app should now launch successfully

## Verification Steps

### 1. Verify IPA Signature

```bash
# Check code signature
codesign -dvvv POSitiv.ipa

# Verify provisioning profile
security cms -D -i Payload/POSitiv.app/embedded.mobileprovision
```

### 2. Check Entitlements

```bash
codesign -d --entitlements - Payload/POSitiv.app
```

### 3. Verify on Device

1. Install via Jamf
2. Check device logs if installation fails
3. Verify certificate trust in Settings

## Common Issues & Solutions

### Issue: "Untrusted Developer" or App Won't Launch

**Solution**: 
- After installation, go to **Settings** → **General** → **VPN & Device Management**
- Find your developer certificate and tap **Trust**
- This is required for Ad-Hoc apps on first launch

### Issue: Provisioning Profile Mismatch / Device UDID Not Included

**Solution**:
- **CRITICAL for Ad-Hoc**: The provisioning profile **must include the device UDID**
- Get the UDID from Jamf or directly from the iPad
- Update the provisioning profile in Apple Developer Portal to include the UDID
- Download the updated profile and rebuild/re-sign the IPA
- You can check if UDID is included: `security cms -D -i Payload/POSitiv.app/embedded.mobileprovision | grep -A 5 "ProvisionedDevices"`

### Issue: Certificate Expired

**Solution**:
- Renew certificate in Apple Developer Portal
- Update provisioning profile
- Re-sign IPA

### Issue: Bundle ID Mismatch

**Solution**:
- Ensure Bundle ID in Xcode matches the provisioning profile
- Check `ios/Runner/Info.plist` → `CFBundleIdentifier`

## Best Practices for Ad-Hoc MDM Deployment

1. **Register all device UDIDs upfront** - You can add up to 100 devices per App ID per year
2. **Keep certificates up to date** (set reminders before expiration)
3. **Test on one device first** before deploying to all devices
4. **Document device UDIDs** - Keep a list of all registered devices
5. **Use CI/CD** to automate signing and building
6. **Monitor device limit** - Ad-Hoc is limited to 100 devices per App ID per year
7. **Regenerate provisioning profile** when adding new devices (don't forget to rebuild IPA)

## Flutter-Specific Notes

- Flutter builds use Xcode's signing configuration
- Always configure signing in Xcode before building
- The `flutter build ipa` command respects Xcode settings
- For FlutterFlow apps, you may need to export and configure in Xcode manually

## Quick Reference: Ad-Hoc Build Command

```bash
# Complete ad-hoc build process
flutter clean
flutter build ipa --release --export-method=ad-hoc

# Verify the IPA was built with ad-hoc method
unzip -l build/ios/ipa/POSitiv.ipa | grep mobileprovision
security cms -D -i build/ios/ipa/POSitiv.app/embedded.mobileprovision | grep ProvisionedDevices
```

## Additional Resources

- [Apple Ad-Hoc Distribution Guide](https://developer.apple.com/documentation/xcode/distributing-your-app-for-beta-testing-and-releases)
- [Jamf App Deployment Documentation](https://docs.jamf.com/)
- [Flutter iOS Deployment Guide](https://docs.flutter.dev/deployment/ios)
- [Managing Device UDIDs in Apple Developer](https://developer.apple.com/account/resources/devices/list)
