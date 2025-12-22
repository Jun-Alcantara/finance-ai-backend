# Bank Accounts API Specification

This document outlines the API endpoints required for managing Bank Accounts.

## Overview

The Bank Accounts resource allows users to manage their bank accounts, including adding, updating, deleting, and retrieving account details.

## Data Structure

**Bank Account Object**

| Field | Type | Required | Description |
|---|---|---|---|
| `id` | UUID/String | Yes | Unique identifier for the bank account. |
| `name` | String | Yes | The name of the bank account (e.g., "Main Savings"). |
| `balance` | Decimal | Yes | The current balance of the account. |
| `account_number` | String | No | The account number (optional). |
| `created_at` | Timestamp | Yes | Date and time when the account was created. |
| `updated_at` | Timestamp | Yes | Date and time when the account was last updated. |

## Endpoints

### 1. List Bank Accounts

Retrieve a paginated list of bank accounts.

- **URL:** `/api/bank-accounts`
- **Method:** `GET`
- **Query Parameters:**
    - `page` (integer, optional): Page number (default: 1).
    - `per_page` (integer, optional): Items per page (default: 15).
    - `search` (string, optional): Search term to filter by name or account number.

**Response (Success - 200 OK):**

```json
{
  "data": [
    {
      "id": "uuid-1",
      "name": "Main Savings",
      "balance": 5000.00,
      "account_number": "1234567890",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

### 2. Create Bank Account

Create a new bank account.

- **URL:** `/api/bank-accounts`
- **Method:** `POST`
- **Body:**

```json
{
  "name": "Daily Expenses",
  "balance": 100.50,
  "account_number": "0987654321" // Optional
}
```

**Validation:**
- `name`: Required, max 255 chars.
- `balance`: Required, numeric.
- `account_number`: Optional, max 50 chars.

**Response (Created - 201 Created):**

```json
{
  "id": "uuid-2",
  "name": "Daily Expenses",
  "balance": 100.50,
  "account_number": "0987654321",
  "created_at": "2024-01-02T00:00:00Z",
  "updated_at": "2024-01-02T00:00:00Z"
}
```

### 3. Get Bank Account

Retrieve a specific bank account by ID.

- **URL:** `/api/bank-accounts/{id}`
- **Method:** `GET`

**Response (Success - 200 OK):**

```json
{
  "id": "uuid-1",
  "name": "Main Savings",
  "balance": 5000.00,
  "account_number": "1234567890",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

### 4. Update Bank Account

Update an existing bank account.

- **URL:** `/api/bank-accounts/{id}`
- **Method:** `PUT` or `PATCH`
- **Body:**

```json
{
  "name": "Updated Name",
  "balance": 5500.00
}
```

**Response (Success - 200 OK):**

```json
{
  "id": "uuid-1",
  "name": "Updated Name",
  "balance": 5500.00,
  "account_number": "1234567890",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-03T00:00:00Z"
}
```

### 5. Delete Bank Account

Delete a bank account.

- **URL:** `/api/bank-accounts/{id}`
- **Method:** `DELETE`

**Response (Success - 200 OK or 204 No Content):**

```json
{
  "message": "Bank account deleted successfully."
}
```
