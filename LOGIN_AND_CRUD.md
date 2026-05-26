# BinsCafe - Login & Test Credentials

## Test Accounts

Once the app deploys and fixtures load, you can use these test accounts:

### Admin Account
- **Email**: `admin@binscafe.com`
- **Password**: `Admin123!`
- **Access**: Full admin access, can create/edit/delete all resources

### Staff Account
- **Email**: `staff@binscafe.com`
- **Password**: `Staff123!`
- **Access**: Staff access, can manage products, stock, and orders

### Customer Account
- **Email**: `customer@binscafe.com`
- **Password**: `Customer123!`
- **Access**: Basic customer access, can view products and place orders

## Login Page

Visit: `/login` (or `/` which redirects to login)

## API Endpoints (For CRUD Operations)

### Products
- **GET** `/api/products` - List all products
- **POST** `/api/products` - Create product (Staff+)
- **PUT** `/api/products/{id}` - Update product (Staff+)
- **DELETE** `/api/products/{id}` - Delete product (Admin)

### Customers
- **GET** `/api/customers` - List all customers
- **POST** `/api/customers` - Create customer (Staff+)
- **PUT** `/api/customers/{id}` - Update customer (Staff+)
- **DELETE** `/api/customers/{id}` - Delete customer (Admin)

### Orders
- **GET** `/api/orders` - List all orders
- **POST** `/api/orders` - Create order
- **PUT** `/api/orders/{id}` - Update order
- **DELETE** `/api/orders/{id}` - Delete order

### Stock
- **GET** `/api/stocks` - List all stock
- **POST** `/api/stocks` - Create stock (Staff+)
- **PUT** `/api/stocks/{id}` - Update stock (Staff+)
- **DELETE** `/api/stocks/{id}` - Delete stock (Admin)

## Example API Call (with cURL)

```bash
# Login to get authentication token
curl -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@binscafe.com","password":"Admin123!"}'

# Create a product (as Staff/Admin)
curl -X POST http://localhost/api/products \
  -H "Content-Type: application/ld+json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "Coffee",
    "category": "Beverages",
    "price": 4.99
  }'
```

## Troubleshooting

If login doesn't work:
1. Check that fixtures loaded: "Loading test data (fixtures)..." in logs
2. Verify DATABASE_URL is set on Railway
3. Check that migrations ran successfully
4. Ensure cache directories have proper permissions

If CRUD operations fail:
1. Verify you're authenticated (use Staff or Admin account)
2. Check user role/permissions
3. Ensure all required fields are included in POST/PUT requests
