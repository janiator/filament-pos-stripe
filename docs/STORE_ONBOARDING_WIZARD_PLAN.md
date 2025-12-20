# Store Onboarding Wizard Plan

## Overview
This document outlines the plan for implementing a Filament wizard to onboard new stores to the POS system. The wizard will guide administrators through all necessary setup steps including tenancy configuration, Stripe Connect account creation, and initial store configuration.

## Current System Architecture

### Store Model
- **Fields:**
  - `name` (required) - Store name
  - `slug` (auto-generated) - URL-friendly identifier
  - `email` (required, unique) - Store email (required by Stripe)
  - `organisasjonsnummer` (optional) - Organization number for receipts
  - `commission_type` (enum: 'percentage' | 'fixed', default: 'percentage')
  - `commission_rate` (integer, default: 0)
  - `stripe_account_id` (nullable, unique) - Stripe Connect account ID

### Tenancy
- Stores act as tenants in Filament
- Users have a many-to-many relationship with stores
- Super admins can access all stores
- Regular users can only access their assigned stores

### Related Models
- **Settings** - Store-specific POS configuration (receipts, cash drawer, currency, timezone, etc.)
- **Users** - Assigned to stores via pivot table
- **Roles** - Spatie Permission roles (super_admin, etc.)

### Stripe Integration
- Uses `Lanos\CashierConnect\Billable` trait
- `SyncStoreToStripe` action creates/updates Stripe Connect accounts
- Stripe account type: 'standard' (can be changed to 'express' or 'custom' if needed)

## Wizard Structure

### Step 1: Basic Store Information
**Purpose:** Collect fundamental store details

**Fields:**
- Store Name (required, text input)
- Email Address (required, email input, unique validation)
- Organisasjonsnummer (optional, text input)
  - Helper text: "Organization number (org.nr.) used on receipts"

**Validation:**
- Email must be unique across all stores
- Name is required

**Actions:**
- Create Store model (but don't save yet - save at end of wizard)
- Auto-generate slug from name

---

### Step 2: Commission Configuration
**Purpose:** Set up platform fee structure

**Fields:**
- Commission Type (radio: 'percentage' | 'fixed', default: 'percentage')
- Commission Rate (required, numeric input)
  - Helper text changes based on type:
    - Percentage: "Enter whole percentage (e.g. 5 = 5%)"
    - Fixed: "Enter fixed fee in minor units (e.g. 500 = 5.00)"
  - Suffix changes: "%" for percentage, "units" for fixed

**Default Values:**
- commission_type: 'percentage'
- commission_rate: 0

---

### Step 3: Stripe Connect Account Setup
**Purpose:** Create and link Stripe Connect account

**Options:**
1. **Create New Stripe Account** (default)
   - Automatically creates a new Stripe Connect account
   - Uses store name and email from Step 1
   - Account type: 'standard'
   - Shows loading state during creation
   - Displays success message with account ID

2. **Link Existing Stripe Account** (optional)
   - Text input for existing Stripe account ID (acct_xxx)
   - Validation: Check if account exists in Stripe
   - Helper text: "If the store already has a Stripe Connect account, enter its ID here"

**Implementation:**
- Use `SyncStoreToStripe` action
- If creating new: Call `$stripe->accounts->create()`
- If linking existing: Validate account exists, then set `stripe_account_id`
- Show error handling if Stripe API fails

**UI:**
- Radio button to choose between "Create New" or "Link Existing"
- Conditional fields based on selection
- Loading spinner during Stripe API calls
- Success/error notifications

---

### Step 4: Initial Settings Configuration
**Purpose:** Set up basic POS settings for the store

**Fields:**
- Currency (select, default: 'nok')
  - Options: NOK, EUR, USD, etc.
- Timezone (select, default: 'Europe/Oslo')
  - Common timezones dropdown
- Locale (select, default: 'nb')
  - Options: nb (Norwegian), en, etc.
- Default VAT Rate (decimal, default: 25.00)
  - Helper text: "Default VAT rate for receipts (e.g. 25.00 = 25%)"
- Tax Included (toggle, default: false)
  - Helper text: "Whether prices include tax by default"
- Tips Enabled (toggle, default: true)
  - Helper text: "Allow customers to add tips to transactions"

**Note:** These will create a Settings record after store is saved

---

### Step 5: User Assignment
**Purpose:** Assign users to the new store

**Fields:**
- Users (multi-select relationship)
  - Searchable user picker
  - Shows: name, email
  - Can select multiple users
  - Helper text: "Select users who should have access to this store"

**Optional: Role Assignment**
- For each selected user, optionally assign a role
- Could use a repeater or nested form
- Or handle role assignment separately after wizard completion

**Default Behavior:**
- If current user is a super_admin, they can assign any user
- If not, they can only assign themselves (or users they manage)

---

### Step 6: Review & Complete
**Purpose:** Review all entered information and finalize

**Display:**
- Summary card showing all entered information:
  - Store name, email, organisasjonsnummer
  - Commission configuration
  - Stripe account status (ID or "Will be created")
  - Settings summary
  - Assigned users count
- Edit links to go back to specific steps

**Actions:**
- "Complete Setup" button
  - Saves Store model
  - Creates Stripe account (if not already created)
  - Creates Settings record with configured values
  - Attaches users to store
  - Assigns default roles if needed
  - Shows success notification
  - Redirects to store view/edit page

**Error Handling:**
- If any step fails, show error and allow retry
- Rollback any partial changes if possible
- Log errors for debugging

---

## Implementation Details

### File Structure
```
app/Filament/Resources/Stores/
├── Pages/
│   └── OnboardStore.php (new wizard page)
└── Schemas/
    └── OnboardStoreWizard.php (new wizard schema)
```

### Wizard Page Class
- Extends `Filament\Resources\Pages\Page`
- Implements `HasForms` and `InteractsWithForms`
- Uses `Filament\Schemas\Components\Wizard` and `Step`
- State management for multi-step form data
- Custom actions for Stripe API calls
- Success/error notifications

### Key Actions Needed
1. **CreateStoreAction** - Save store to database
2. **CreateStripeAccountAction** - Call SyncStoreToStripe or direct Stripe API
3. **CreateStoreSettingsAction** - Create Settings record using `Setting::getForStore()`
4. **AttachUsersToStoreAction** - Attach users via pivot table
5. **AssignDefaultRolesAction** - Optionally assign roles to users

### Navigation
- Add "Onboard Store" button to StoreResource list page
- Only visible to super_admins
- Route: `/app/stores/onboard`

### Validation
- Step-by-step validation
- Final validation before completion
- Unique email check
- Stripe account validation (if linking existing)

### Error Handling
- Try-catch around Stripe API calls
- Transaction rollback if store creation fails
- User-friendly error messages
- Logging for debugging

### Success Flow
1. Show success notification
2. Redirect to store view/edit page
3. Optionally show "Next Steps" modal with:
   - Link to configure terminal locations
   - Link to add products
   - Link to configure receipt templates
   - Link to add more users

---

## Additional Considerations

### Optional Enhancements
1. **Email Verification**
   - Send welcome email to store email address
   - Include setup instructions

2. **Default Data**
   - Create default receipt template
   - Set up default payment methods
   - Create sample products (optional)

3. **Progress Indicator**
   - Show progress bar at top of wizard
   - Step numbers (1 of 6, 2 of 6, etc.)

4. **Save Draft**
   - Allow saving incomplete wizard state
   - Resume later functionality

5. **Bulk Onboarding**
   - CSV import for multiple stores
   - Batch processing

### Security
- Only super_admins can access wizard
- Validate user permissions at each step
- Sanitize all inputs
- Rate limiting on Stripe API calls

### Testing
- Unit tests for each action
- Integration tests for full wizard flow
- Test error scenarios (Stripe API failures, validation errors)
- Test with existing Stripe accounts
- Test user assignment edge cases

---

## Implementation Steps

1. **Create Wizard Schema** (`OnboardStoreWizard.php`)
   - Define all steps and fields
   - Add validation rules
   - Configure conditional fields

2. **Create Wizard Page** (`OnboardStore.php`)
   - Set up form state management
   - Implement step navigation
   - Add Stripe API integration
   - Handle form submission

3. **Create Actions**
   - Refactor `SyncStoreToStripe` if needed
   - Create settings creation action
   - Create user attachment action

4. **Add Navigation**
   - Add "Onboard Store" button to StoreResource
   - Configure route and permissions

5. **Add Error Handling**
   - Implement try-catch blocks
   - Add user-friendly error messages
   - Add logging

6. **Testing**
   - Test happy path
   - Test error scenarios
   - Test edge cases

7. **Documentation**
   - Update API docs if needed
   - Add user guide
   - Document Stripe account types

---

## Example Wizard Flow

```
┌─────────────────────────────────────┐
│  Store Onboarding Wizard            │
│  Step 1 of 6: Basic Information     │
├─────────────────────────────────────┤
│  Store Name: [________________]     │
│  Email: [________________]          │
│  Org. Number: [________________]    │
│                                     │
│  [Cancel]              [Next →]    │
└─────────────────────────────────────┘
```

Each step follows similar pattern with appropriate fields and navigation buttons.

---

## Questions to Resolve

1. **Stripe Account Type**: Should we allow choosing between 'standard', 'express', and 'custom'? Or always use 'standard'?
   - **Recommendation**: Start with 'standard', add option later if needed

2. **User Roles**: Should we assign default roles during onboarding, or handle separately?
   - **Recommendation**: Assign a default role (e.g., 'store_admin') during onboarding, allow changes later

3. **Settings Defaults**: Should we use system defaults or require explicit configuration?
   - **Recommendation**: Use sensible defaults, allow customization in wizard

4. **Wizard Persistence**: Should incomplete wizards be saved?
   - **Recommendation**: Start without persistence, add if needed

5. **Multi-tenant Context**: Should wizard run in admin context (no tenant) or create tenant immediately?
   - **Recommendation**: Run in admin context, switch to new tenant after completion

---

## Success Criteria

- ✅ Wizard guides user through all required setup steps
- ✅ Store is created with all provided information
- ✅ Stripe Connect account is created/linked successfully
- ✅ Settings are initialized with provided values
- ✅ Users are assigned to store
- ✅ User can immediately access the new store as tenant
- ✅ Error handling is robust and user-friendly
- ✅ Only super_admins can access wizard
- ✅ All validation rules are enforced
- ✅ Wizard follows Filament V4 standards



