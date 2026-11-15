# LEADX ENTERPRISE PLATFORM
# OFFICIAL REST API SPECIFICATION & REFERENCE

**Project:** LeadX Enterprise Lead & Sales Management Platform  
**Specification Version:** 2.0.0 (Enterprise)  
**Target Architecture:** Laravel 12.x / PHP 8.2+ / Laravel Sanctum  
**Protocol:** HTTPS / JSON REST  
**Document Generated:** November 16, 2026  
**File Location:** `docs/LEADX_API_DOCUMENTATION.md`  

---

## 1. AUTHENTICATION & SECURITY ARCHITECTURE

### 1.1 Dual-Guard Model
LeadX implements an enterprise dual-authentication security model powered by Laravel Sanctum:
1. **Super Admin Guard (`admin`)**: Comprehensive administrative access.
2. **Individual Employee Guard (`employee`)**: Scoped to the authenticated employee's assigned account, team, and permission preset.

> [!IMPORTANT]
> Legacy Team Authentication (`/api/v1/team/login`) is deprecated and permanently disabled. Requests to legacy team endpoints return `410 Gone`.

### 1.2 Authentication Headers
All protected API requests must provide the Sanctum Bearer token in the `Authorization` header, along with the `Accept: application/json` header:

```http
Authorization: Bearer <SANCTUM_TOKEN>
Accept: application/json
Content-Type: application/json
```

### 1.3 Dynamic API Management & Personal Access Tokens
Super Admins can generate, view, and revoke personal access tokens dynamically from the Admin Panel via **API Integrations** (`/admin/api-settings`).
Additionally, all external third-party API configurations (Pusher Beams, Google OAuth 2.0, WhatsApp, Payment Gateway & Webhooks) can be edited dynamically in the Admin UI without modifying server `.env` files. Secrets are stored AES-256 encrypted at rest and overlaid at runtime.

---

## 2. AUTHENTICATION ENDPOINTS

### 2.1 Super Admin Login
- **Method:** `POST`
- **URI:** `/api/v1/admin/login`
- **Access:** Public

#### Request Body
```json
{
  "email": "admin@leadx.local",
  "password": "AdminSecurePassword123!"
}
```

#### Response (`200 OK`)
```json
{
  "token": "1|sanctum_token_hash_here...",
  "admin": {
    "id": 1,
    "name": "Super Administrator",
    "email": "admin@leadx.local"
  }
}
```

---

### 2.2 Individual Employee Login
- **Method:** `POST`
- **URI:** `/api/v1/employee/login`
- **Access:** Public

#### Request Body
```json
{
  "email": "john.sales@leadx.local",
  "password": "EmployeePassword123!"
}
```

#### Response (`200 OK`)
```json
{
  "token": "2|sanctum_token_hash_here...",
  "employee": {
    "id": 14,
    "name": "John Salesman",
    "username": "john_sales",
    "email": "john.sales@leadx.local",
    "team_id": 3,
    "team_name": "Enterprise Closers",
    "team_code": "ENT-CLS",
    "status": "active"
  }
}
```

---

### 2.3 Legacy Team Login Deprecation
- **Method:** `POST`
- **URI:** `/api/v1/team/login`
- **Access:** Public

#### Response (`410 Gone`)
```json
{
  "error": "Team login is deprecated and permanently disabled. Please use individual employee login.",
  "status": 410,
  "redirect": "/employee/login"
}
```

---

## 3. SUPER ADMIN API ENDPOINTS

### 3.1 Order Closing Value Approval Decision
- **Method:** `POST`
- **URI:** `/admin/orders/{order}/closing-approval/accept` or `/reject`
- **Guard:** `admin`

#### Accept Request
```json
{
  "notes": "Corporate bulk license discount approved by VP."
}
```

#### Reject Request
```json
{
  "rejection_reason": "Requested margin is below sustainable threshold."
}
```

---

### 3.2 Super Admin Closing Value Modification
- **Method:** `POST`
- **URI:** `/admin/orders/{order}/closing-value/modify`
- **Guard:** `admin`

Allows Super Admin to modify an already Accepted closing value with a mandatory audit reason.

#### Request Body
```json
{
  "new_closing_value": 890.00,
  "modification_reason": "Adjusted after direct customer executive negotiation."
}
```

#### Response (`302 Redirect` or `200 OK`)
Logs change to `order_edit_histories` and `order_closing_value_histories`.

---

### 3.3 Super Admin API Key Entry
- **Method:** `POST`
- **URI:** `/admin/orders/{order}/api-key`
- **Guard:** `admin`

Super Admin exclusive write-access endpoint to store or rotate a customer's encrypted product API key.

#### Request Body
```json
{
  "api_key": "sk-live-enterprise-secret-key-999888"
}
```

#### Response (`302 Redirect` / `200 OK`)
- AES-256 encrypted at rest in `order_api_credentials`.
- Audit record created in `order_api_credential_audits`.

---

### 3.4 Process Full / Partial Refund
- **Method:** `POST`
- **URI:** `/admin/orders/{order}/refunds`
- **Guard:** `admin`

Atomic financial refund accounting. Updates order payment state to `partially_refunded` or `refunded`.

#### Request Body
```json
{
  "amount": 250.00,
  "reason": "Customer downgraded to starter tier within 14-day SLA.",
  "status": "processed",
  "gateway_reference": "rfnd_ch_stripe_888999"
}
```

#### Response (`302 Redirect` / `200 OK`)
- If refund status is `processed`, decrements remaining refundable amount and updates order `payment_status`.

---

### 3.5 Refund Statuses Configuration
- **Method:** `GET|POST|PUT`
- **URI:** `/admin/settings/refund-statuses`
- **Guard:** `admin`

Configure allowable refund statuses stored in `refund_statuses` table (`requested`, `recorded`, `processed`, `failed`, or custom).

---

### 3.6 Admin Dashboard Customization Preferences
- **Method:** `POST`
- **URI:** `/admin/dashboard/preferences`
- **Guard:** `admin`

#### Request Body
```json
{
  "cards": [
    {"key": "verified_revenue", "visible": true, "order": 1},
    {"key": "pending_collections", "visible": true, "order": 2},
    {"key": "total_closing_value", "visible": true, "order": 3},
    {"key": "orders_total", "visible": true, "order": 4}
  ]
}
```

- **Reset URI:** `POST /admin/dashboard/preferences/reset`

---

## 4. EMPLOYEE API ENDPOINTS

### 4.1 Create Sales Order
- **Method:** `POST`
- **URI:** `/employee/orders`
- **Guard:** `employee`

#### Business Rules
1. Floor price enforcement: `closing_value >= product.minimum_closing_price`. If below, request fails with HTTP 422 / session validation error.
2. If `closing_value == product.price`, order is created immediately with `closing_value_status = 'Accepted'`.
3. If `closing_value < product.price` (discounted, but `>= floor`), order is created with `closing_value_status = 'Requested'` and requires Super Admin approval.

#### Request Body
```json
{
  "customer_name": "Acme Global Industries",
  "customer_phone": "+14155550199",
  "customer_email": "purchasing@acmeglobal.com",
  "product_id": 4,
  "duration": "1y",
  "closing_value": 850.00,
  "payment_method": "bank_transfer"
}
```

---

### 4.2 Resubmit Rejected Closing Value
- **Method:** `POST`
- **URI:** `/employee/orders/{order}/closing-approval/resubmit`
- **Guard:** `employee`

Allows employee to adjust a rejected order's deal value (validated `>= minimum_closing_price`), returning the order to `Requested` status.

#### Request Body
```json
{
  "closing_value": 900.00,
  "resubmission_notes": "Customer accepted adjusted 10% discount concession."
}
```

---

### 4.3 Employee Profile
- **Method:** `GET|PUT`
- **URI:** `/employee/profile`
- **Guard:** `employee`

#### GET Response
Returns full profile details: Name, Email, Phone, Employee ID, Team Name, Team Code, Status, Role, Assigned Preset.

#### PUT Request (Update Contact Info)
```json
{
  "name": "John Salesman",
  "email": "john.salesman@leadx.local",
  "phone": "+14155552233"
}
```
> [!NOTE]
> System attributes (`role`, `team_id`, `preset_id`, `status`) are strictly stripped and immutable from employee profile requests.

#### PUT Password Update
- **URI:** `/employee/profile/password`
```json
{
  "current_password": "OldPassword123!",
  "password": "NewSecurePassword456!",
  "password_confirmation": "NewSecurePassword456!"
}
```

---

### 4.4 Reveal Encrypted Customer API Key
- **Method:** `POST`
- **URI:** `/employee/orders/{order}/api-key/reveal`
- **Guard:** `employee`
- **Permission Required:** `orders.view_api_key`

Decrypts and temporarily exposes key in session. Blocked with 403 Forbidden if employee lacks permission.

---

### 4.5 Employee Dashboard & Sidebar Preferences
- **Save Cards:** `POST /employee/dashboard/preferences`
- **Reset Cards:** `POST /employee/dashboard/preferences/reset`
- **Save Sidebar:** `POST /employee/sidebar/preferences`
- **Reset Sidebar:** `POST /employee/sidebar/preferences/reset`

Saves employee UI customization to `user_ui_preferences` table with full server-side validation.

---

## 5. STANDARD ERROR RESPONSES

### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

### 403 Forbidden
```json
{
  "message": "This action is unauthorized or outside your granted permissions."
}
```

### 422 Unprocessable Entity (Validation Error)
```json
{
  "message": "The closing value ($700.00) cannot be less than the product minimum closing price of $800.00.",
  "errors": {
    "closing_value": [
      "The closing value ($700.00) cannot be less than the product minimum closing price of $800.00."
    ]
  }
}
```

### 410 Gone (Deprecated Endpoint)
```json
{
  "error": "Team login is deprecated and permanently disabled. Please use individual employee login.",
  "status": 410,
  "redirect": "/employee/login"
}
```
