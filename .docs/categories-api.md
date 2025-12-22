# Categories API

Base URL: `/api/categories`

## Data Model

| Field | Type | Description |
|---|---|---|
| id | string | Unique identifier |
| name | string | Name of the category |
| description | string | Description of the category |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

## Endpoints

### List Categories
Retrieves a paginated list of categories with optional search filtering.

- **URL**: `/api/categories`
- **Method**: `GET`
- **Auth Required**: Yes
- **Query Parameters**:
  - `page` (optional, integer): Page number to retrieve (default: 1).
  - `per_page` (optional, integer): Items per page (default: 15).
  - `search` (optional, string): Search term to filter categories by name.
- **Success Response**:
  - **Code**: 200 OK
  - **Content**:
    ```json
    {
      "data": [
        {
          "id": "1",
          "name": "Food & Dining",
          "description": "Groceries, restaurants, and fast food",
          "created_at": "2024-01-01T00:00:00Z",
          "updated_at": "2024-01-01T00:00:00Z"
        }
      ],
      "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 5,
        "per_page": 15,
        "to": 15,
        "total": 75
      },
      "links": {
        "first": "http://api.app.com/categories?page=1",
        "last": "http://api.app.com/categories?page=5",
        "prev": null,
        "next": "http://api.app.com/categories?page=2"
      }
    }
    ```

### Create Category
Creates a new category.

- **URL**: `/api/categories`
- **Method**: `POST`
- **Auth Required**: Yes
- **Data Params**:
  ```json
  {
    "name": "Entertainment",
    "description": "Movies, games..."
  }
  ```
- **Success Response**:
  - **Code**: 201 Created
  - **Content**:
    ```json
    {
      "id": "4",
      "name": "Entertainment",
      "description": "Movies, games...",
      "created_at": "...",
      "updated_at": "..."
    }
    ```
- **Error Response**:
  - **Code**: 422 Unprocessable Entity (Validation Error)

### Get Category
Get a specific category by ID.

- **URL**: `/api/categories/:id`
- **Method**: `GET`
- **Auth Required**: Yes
- **Success Response**:
  - **Code**: 200 OK
  - **Content**:
    ```json
    {
      "id": "1",
      ...
    }
    ```

### Update Category
Updates an existing category.

- **URL**: `/api/categories/:id`
- **Method**: `PUT` or `PATCH`
- **Auth Required**: Yes
- **Data Params**:
  ```json
  {
    "name": "Updated Name",
    "description": "Updated Description"
  }
  ```
- **Success Response**:
  - **Code**: 200 OK
  - **Content**:
    ```json
    {
      "id": "1",
      "name": "Updated Name",
      ...
    }
    ```

### Delete Category
Deletes a category.

- **URL**: `/api/categories/:id`
- **Method**: `DELETE`
- **Auth Required**: Yes
- **Success Response**:
  - **Code**: 204 No Content
