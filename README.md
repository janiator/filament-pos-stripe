# POS Stripe Backend

Laravel backend for a Point of Sale (POS) system with Stripe integration, compliant with Norwegian Kassasystemforskriften regulations.

## Features

- ✅ POS Session Management
- ✅ Transaction Processing (Stripe Terminal)
- ✅ Electronic Journal (Audit Log)
- ✅ SAF-T Export (Norwegian Tax Compliance)
- ✅ Receipt Generation
- ✅ Product & Inventory Management
- ✅ Multi-tenant Store Management
- ✅ FlutterFlow Frontend Integration

## Quick Start

See the [Quick Start Guide](./docs/setup/QUICK_START.md) for setup instructions.

## Documentation

All documentation is organized in the [`docs/`](./docs/) directory:

### 📚 Documentation Index

- **[Documentation Index](./docs/README.md)** - Complete documentation overview
- **[Quick Start Guide](./docs/setup/QUICK_START.md)** - Get started quickly
- **[POS Backend Implementation](./docs/implementation/POS_BACKEND_IMPLEMENTATION_SUMMARY.md)** - Backend overview

### 🔒 Compliance

- **[Kassasystemforskriften Compliance](./docs/compliance/KASSASYSTEMFORSKRIFTEN_COMPLIANCE.md)** - Legal compliance requirements
- **[POS Audit Log Requirements](./docs/compliance/POS_AUDIT_LOG_REQUIREMENTS.md)** - Audit logging requirements
- **[SAF-T Implementation](./docs/saf-t/SAF_T_IMPLEMENTATION_PLAN.md)** - Tax compliance

### 📱 Frontend Integration

- **[FlutterFlow Implementation Guide](./docs/flutterflow/FLUTTERFLOW_IMPLEMENTATION_GUIDE.md)** - Complete FlutterFlow setup
- **[FlutterFlow Cart Data Structure](./docs/flutterflow/FLUTTERFLOW_CART_DATA_STRUCTURE.md)** - Shopping cart structure
- **[FlutterFlow Custom Actions](./docs/flutterflow/custom-actions/)** - Code examples

### 🏗️ Architecture

- **[POS Device Architecture](./docs/implementation/POS_DEVICE_ARCHITECTURE.md)** - Device integration
- **[POS Session Management](./docs/implementation/POS_SESSION_MANAGEMENT.md)** - Session system
- **[Product Variants & Inventory](./docs/features/PRODUCT_VARIANTS_AND_INVENTORY_IMPLEMENTATION.md)** - Product system

## Project Structure

```
├── app/
│   ├── Actions/          # Business logic actions
│   ├── Http/            # API controllers
│   ├── Models/          # Eloquent models
│   ├── Services/        # Service classes
│   └── Filament/        # Admin panel resources
├── docs/                # All documentation
│   ├── compliance/      # Legal compliance docs
│   ├── implementation/  # Implementation guides
│   ├── flutterflow/     # FlutterFlow integration
│   ├── saf-t/          # SAF-T documentation
│   ├── setup/          # Setup guides
│   └── features/       # Feature documentation
├── routes/
│   └── api.php         # API routes
└── database/
    └── migrations/     # Database migrations
```

## Requirements

- PHP 8.2+
- Laravel 11
- MySQL/PostgreSQL
- Stripe Account
- Herd (for local development)

## Installation

1. Clone the repository
2. Install dependencies: `composer install`
3. Copy `.env.example` to `.env` and configure
4. Run migrations: `php artisan migrate`
5. See [Quick Start Guide](./docs/setup/QUICK_START.md) for detailed setup

## API Documentation

API specification available in `api-spec.yaml`. Main endpoints:

- `/api/pos-sessions` - POS session management
- `/api/pos-devices` - Device registration
- `/api/charges` - Transaction processing
- `/api/products` - Product management
- `/api/saf-t/generate` - SAF-T export

## Import of CSV

```
php artisan queue:batches-table
php artisan migrate
```

## Compliance

This system is designed to comply with:
- **Kassasystemforskriften** (FOR-2015-12-18-1616) - Norwegian cash register regulation
- **SAF-T Cash Register** - Norwegian tax authority requirements

See [Compliance Documentation](./docs/compliance/) for details.

## License

Proprietary - All rights reserved

---

For complete documentation, see the [Documentation Index](./docs/README.md).
