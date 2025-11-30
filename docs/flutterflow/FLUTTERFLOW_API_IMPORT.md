# FlutterFlow API Import Guide

## Importing the API Specification

The `api-spec.yaml` file is an OpenAPI 3.0.3 specification that can be imported directly into FlutterFlow.

### Steps to Import:

1. **Open FlutterFlow**
   - Go to your FlutterFlow project
   - Navigate to **API Calls** section

2. **Import API Specification**
   - Click on **"Import API"** or **"Add API"**
   - Select **"Import from OpenAPI/Swagger"**
   - Upload or paste the contents of `api-spec.yaml`

3. **Configure Authentication**
   - FlutterFlow will automatically detect the Bearer Token authentication
   - Set up the authentication token variable:
     - Variable name: `authToken` or `apiToken`
     - Get token from: `/auth/login` endpoint response

4. **Set Base URL**
   - For production: `https://pos.visivo.no/api`
   - For local development: `http://localhost:8000/api` or `https://pos-stripe.test/api`

## Available Endpoints

### Authentication
- `POST /auth/login` - Login and get token
- `GET /auth/me` - Get current user info
- `POST /auth/logout` - Logout (revoke current token)
- `POST /auth/logout-all` - Logout from all devices

### Stores
- `GET /stores` - List all accessible stores
- `GET /stores/current` - Get current store
- `PUT /stores/current` - Change current store
- `GET /stores/{slug}` - Get store by slug

### POS Devices
- `GET /pos-devices` - List POS devices
- `POST /pos-devices` - Register new POS device
- `GET /pos-devices/{id}` - Get POS device
- `PUT /pos-devices/{id}` - Update POS device
- `PATCH /pos-devices/{id}` - Partial update
- `POST /pos-devices/{id}/heartbeat` - Update device heartbeat

### Terminals
- `GET /terminals/locations` - List terminal locations
- `GET /terminals/readers` - List terminal readers
- `POST /stores/{store}/terminal/connection-token` - Get connection token
- `POST /stores/{store}/terminal/payment-intents` - Create payment intent

### Customers
- `GET /customers` - List customers (paginated)
- `POST /customers` - Create customer
- `GET /customers/{id}` - Get customer
- `PUT /customers/{id}` - Update customer
- `DELETE /customers/{id}` - Delete customer

## Authentication Flow

1. **Login**
   ```json
   POST /auth/login
   Body: {
     "email": "user@example.com",
     "password": "password"
   }
   Response: {
     "user": {...},
     "token": "1|abc123...",
     "current_store": {...},
     "stores": [...]
   }
   ```

2. **Store Token**
   - Save the `token` from login response
   - Use it in all subsequent requests as: `Authorization: Bearer {token}`

3. **Use in API Calls**
   - FlutterFlow will automatically add the Authorization header
   - Set the token variable in FlutterFlow's API configuration

## Important Notes

- All endpoints (except `/auth/login` and webhooks) require authentication
- The API uses tenant scoping - requests are automatically scoped to the user's current store
- Use the `X-Tenant` header to override the default tenant/store
- Store slugs are used in URLs (e.g., `/stores/my-store-slug`)

## FlutterFlow Custom Actions

The following custom actions are available in `flutterflow_custom_actions/`:
- `register_pos_device.dart` - Register/update POS device
- `update_device_heartbeat.dart` - Update device heartbeat

These actions handle device information collection and API communication automatically.
