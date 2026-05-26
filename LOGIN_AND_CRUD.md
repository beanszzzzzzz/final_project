# BinsCafe API - JWT Authentication & CRUD Operations Guide

## JWT Authentication

The API uses JWT (JSON Web Token) authentication with HS256 algorithm. All protected API endpoints require an `Authorization: Bearer {token}` header.

### JWT Configuration
- **Algorithm**: HS256
- **Expiration**: 24 hours (configurable)
- **Secret**: Configured via `JWT_SECRET` environment variable

**⚠️ Important**: On Railway, set `JWT_SECRET` in the dashboard with a strong random value.

## Test Account Credentials

Automatically loaded on deployment:

| Email | Password | Role | Access Level |
|-------|----------|------|--------------|
| admin@binscafe.com | Admin123! | ROLE_ADMIN | Full admin access |
| staff@binscafe.com | Staff123! | ROLE_STAFF | Staff operations |
| customer@binscafe.com | Customer123! | ROLE_USER | Customer access |

## Quick Start

### 1. Login & Get Token

```bash
curl -X POST http://localhost:8080/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@binscafe.com",
    "password": "Admin123!"
  }'
```

**Response**:
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user": {
    "id": 1,
    "email": "admin@binscafe.com",
    "roles": ["ROLE_ADMIN"],
    "verified": true
  }
}
```

### 2. Use Token in Requests

```bash
curl -X GET http://localhost:8080/api/products \
  -H "Authorization: Bearer {TOKEN}"
```

## API Endpoints

### Products
- **GET** `/api/products` - List all (public)
- **GET** `/api/products/{id}` - Get one (public)
- **POST** `/api/products` - Create (ROLE_ADMIN)
- **PUT** `/api/products/{id}` - Update (ROLE_ADMIN)
- **DELETE** `/api/products/{id}` - Delete (ROLE_ADMIN)

### Customers
- **GET** `/api/customers` - List all (public)
- **GET** `/api/customers/{id}` - Get one (public)
- **POST** `/api/customers` - Create (ROLE_STAFF)
- **PUT** `/api/customers/{id}` - Update (ROLE_STAFF)
- **DELETE** `/api/customers/{id}` - Delete (ROLE_ADMIN)

### Stock
- **GET** `/api/stocks` - List all (public)
- **GET** `/api/stocks/{id}` - Get one (public)
- **POST** `/api/stocks` - Create (ROLE_STAFF)
- **PUT** `/api/stocks/{id}` - Update (ROLE_STAFF)
- **DELETE** `/api/stocks/{id}` - Delete (ROLE_ADMIN)

### Orders
- **GET** `/api/orders` - List all (public)
- **GET** `/api/orders/{id}` - Get one (public)
- **POST** `/api/orders` - Create (ROLE_USER+)
- **PUT** `/api/orders/{id}` - Update (ROLE_USER+)
- **DELETE** `/api/orders/{id}` - Delete (ROLE_ADMIN)

### Authentication
- **POST** `/api/login` - Get JWT token
- **POST** `/api/register` - Register new user
- **GET** `/api/me` - Get current user info (ROLE_USER+)

## Example CRUD Operations

### Create Product (Admin)
```bash
curl -X POST http://localhost:8080/api/products \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Coffee Beans",
    "category": "Supplies",
    "price": 12.99
  }'
```

### Update Product (Admin)
```bash
curl -X PUT http://localhost:8080/api/products/1 \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Premium Coffee Beans",
    "price": 15.99
  }'
```

### Create Customer (Staff)
```bash
curl -X POST http://localhost:8080/api/customers \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "555-0123",
    "address": "123 Main St"
  }'
```

### Delete Resource (Admin)
```bash
curl -X DELETE http://localhost:8080/api/products/1 \
  -H "Authorization: Bearer {TOKEN}"
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| "Invalid credentials" | Verify email/password are correct, check fixtures loaded |
| "Invalid or expired token" | Token may have expired (24h), get new one from login endpoint |
| "No Bearer token provided" | Include `Authorization: Bearer {TOKEN}` header |
| "Unauthorized" on protected endpoint | Verify user role matches endpoint requirement |
| Database connection failed | Check DATABASE_URL env var, MySQL plugin on Railway |
| Fixtures not loading | Check logs for "Loading test data (fixtures)..." message |

## Deployment on Railway

1. Set environment variables in Railway dashboard:
   - `JWT_SECRET=` (auto-generated if not set)
   - `JWT_EXPIRATION_HOURS=24`

2. Database is auto-injected as `DATABASE_URL`

3. On deployment:
   - Migrations run automatically
   - Test fixtures load automatically
   - Apache starts serving on assigned PORT
