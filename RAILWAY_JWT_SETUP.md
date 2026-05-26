# Railway Deployment & JWT Configuration Guide

## Deployment Status

✅ **Deployed to Railway** - Automatic deployment triggered on git push

## Setting Up JWT_SECRET on Railway

### Step 1: Generate a Strong JWT Secret

Run locally or in Railway shell:
```bash
php -r "echo bin2hex(random_bytes(32));"
```

This generates a 64-character hexadecimal string. Example:
```
a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1
```

### Step 2: Add JWT_SECRET to Railway

1. Go to **Railway Dashboard** → Select your project
2. Click **Variables** tab
3. Click **+ New Variable**
4. Set:
   - **Key**: `JWT_SECRET`
   - **Value**: Paste the generated secret from Step 1
5. Click **Save** or **Deploy**

### Step 3: Verify Deployment

Check **Deployment** tab for status:
- 🟢 Green = Build and deploy successful
- 🔴 Red = Build failed (check logs)
- 🟡 Yellow = Deploying

If deployment fails, check the build logs for errors.

## Database Configuration

The database URL is automatically injected by Railway's MySQL plugin as `DATABASE_URL`.

**No manual configuration needed** - it's handled by:
1. Railway MySQL plugin auto-injection
2. Docker entrypoint uses `$DATABASE_URL` environment variable
3. Doctrine configuration reads from Symfony environment

## Testing JWT Authentication

### 1. Get Login Token

Replace `https://your-railway-url.railway.app` with your actual Railway domain:

```bash
curl -X POST https://your-railway-url.railway.app/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@binscafe.com",
    "password": "Admin123!"
  }'
```

**Success Response**:
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJmaW5hbF9wcm9qZWN0IiwiaWF0IjoxNzMyOTAwMDAwLCJleHAiOjE3MzI5ODY0MDAsInVzZXJfaWQiOjEsImVtYWlsIjoiYWRtaW5AYmluY2NhZmUuY29tIiwicm9sZXMiOlsiUk9MRV9BRE1JTiJdfQ.abc123...",
  "user": {
    "id": 1,
    "email": "admin@binscafe.com",
    "roles": ["ROLE_ADMIN"],
    "verified": true,
    "verifiedAt": "2024-01-01T00:00:00+00:00"
  }
}
```

### 2. Use Token to Access Protected Endpoints

```bash
curl -X GET https://your-railway-url.railway.app/api/products \
  -H "Authorization: Bearer {TOKEN_FROM_STEP_1}"
```

### 3. Create a Resource (Admin Only)

```bash
curl -X POST https://your-railway-url.railway.app/api/products \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Coffee Beans",
    "category": "Supplies",
    "price": 12.99
  }'
```

## Environment Variables on Railway

Current setup uses these environment variables:

| Variable | Purpose | Set Where | Auto-Generated |
|----------|---------|-----------|----------------|
| `DATABASE_URL` | MySQL connection | Railway MySQL plugin | ✅ Auto-injected |
| `APP_SECRET` | Symfony security key | `docker-entrypoint.sh` | ✅ If not set |
| `JWT_SECRET` | JWT signing key | Railway Dashboard | ❌ Manual setup |
| `JWT_EXPIRATION_HOURS` | Token expiration | `docker-entrypoint.sh` | ✅ Default: 24 |
| `APP_ENV` | Application environment | Dockerfile | ✅ prod |
| `PORT` | HTTP listening port | Railway | ✅ Auto-assigned |

## Monitoring & Debugging

### Check Deployment Logs

1. Go to **Railway Dashboard** → Your Project
2. Click **Deployments** tab
3. Select the latest deployment
4. Click **View Logs**

Look for:
- ✅ "Fixing Apache MPM configuration..."
- ✅ "Running database migrations..."
- ✅ "Loading test data (fixtures)..."
- ✅ "Starting Apache..."

### Common Issues & Solutions

| Issue | Solution |
|-------|----------|
| "403 Forbidden" on API requests | JWT_SECRET may not be set or mismatched; redeploy after setting variable |
| "502 Bad Gateway" | Check logs for Apache or PHP errors; verify database connection |
| "Invalid credentials" on login | Verify test accounts loaded; check fixtures in logs |
| Fixtures not loading | Check logs for "Loading test data (fixtures)..."; migrations must run first |
| "Unauthorized" on /api/products POST | Only ROLE_ADMIN can create; use admin@binscafe.com account |

### Manual Railway Shell Access

For debugging, you can SSH into the Railway container:

1. In Railway Dashboard, click **Shell** button
2. Run diagnostics:

```bash
# Check if migrations ran
php bin/console doctrine:migrations:status

# Check if fixtures loaded
php bin/console doctrine:query:sql "SELECT COUNT(*) FROM user"

# Test database connection
php bin/console doctrine:query:sql "SELECT 1"

# Check JWT service
php bin/console debug:container App\\Service\\JwtTokenService

# View all environment variables
env | grep -E "JWT|DATABASE|APP_ENV|APP_SECRET"
```

## API Endpoints Reference

See [LOGIN_AND_CRUD.md](LOGIN_AND_CRUD.md) for complete API documentation.

### Quick Summary

- **POST** `/api/login` - Get JWT token
- **POST** `/api/register` - Create new user
- **GET** `/api/me` - Get authenticated user info
- **GET** `/api/products` - List products (public)
- **POST** `/api/products` - Create product (ROLE_ADMIN)
- **GET** `/api/customers` - List customers (public)
- **POST** `/api/customers` - Create customer (ROLE_STAFF)
- **GET** `/api/stocks` - List stock (public)
- **POST** `/api/stocks` - Create stock (ROLE_STAFF)
- **GET** `/api/orders` - List orders (public)
- **POST** `/api/orders` - Create order (ROLE_USER+)

## Production Security Checklist

Before going live:

- ✅ `JWT_SECRET` is set in Railway (not using default)
- ✅ `APP_SECRET` is generated at runtime
- ✅ `APP_ENV=prod` (no debug info exposed)
- ✅ HTTPS enabled (Railway provides HTTPS automatically)
- ✅ Database backups configured
- ✅ Fixtures load with test accounts
- ✅ Token validation works on protected endpoints
- ✅ CORS policy configured for your domain
- ✅ Logs monitored for errors
- ✅ Rate limiting may be added later

## Next Steps

1. **Set JWT_SECRET on Railway** (required for production)
2. **Test login endpoint** to verify authentication works
3. **Test protected CRUD endpoints** with Bearer token
4. **Monitor logs** for any errors or warnings
5. **Add custom domain** if needed (Railway → Domain settings)
6. **Enable HTTPS** (Railway does this automatically)
7. **Configure backups** for production database
8. **Set up monitoring** for uptime alerts

## Support

For more details, see:
- [LOGIN_AND_CRUD.md](LOGIN_AND_CRUD.md) - Complete API documentation
- [TODO.md](TODO.md) - Project progress tracker
- [docs/ERRORS.md](docs/ERRORS.md) - Error handling guide
- [docs/REALTIME.md](docs/REALTIME.md) - Real-time features
