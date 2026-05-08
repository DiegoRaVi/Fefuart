# API v1 Contract

## Base path

- /api/v1

## Response envelope

### Success

```json
{
  "success": true,
  "data": {},
  "meta": {
    "version": "v1"
  }
}
```

### Error

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "details": {
      "field": ["error message"]
    },
    "trace_id": "uuid"
  }
}
```

## Error codes (initial set)

- AUTH_UNAUTHORIZED
- VALIDATION_ERROR
- RESOURCE_NOT_FOUND
- BUSINESS_RULE_VIOLATION
- INTERNAL_ERROR

## Endpoints implemented in v1

### POST /auth/register

- Description: register a new user account.
- Auth: public.

Request body:

```json
{
  "name": "Ana",
  "email": "ana@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

Success 201:

```json
{
  "success": true,
  "data": {
    "user": {
      "id": 10,
      "name": "Ana",
      "email": "ana@example.com",
      "role": "user"
    }
  },
  "meta": {
    "version": "v1"
  }
}
```

### POST /auth/login

- Description: authenticate user and issue JWT token.
- Auth: public.

Request body:

```json
{
  "email": "ana@example.com",
  "password": "password123"
}
```

Success 200:

```json
{
  "success": true,
  "data": {
    "token": "jwt-token",
    "user": {
      "id": 10,
      "name": "Ana",
      "email": "ana@example.com",
      "role": "user"
    }
  },
  "meta": {
    "version": "v1"
  }
}
```

### GET /auth/me

- Description: get current authenticated user profile.
- Auth: required.

Success 200:

```json
{
  "success": true,
  "data": {
    "user": {
      "id": 10,
      "name": "Ana",
      "email": "ana@example.com",
      "role": "user"
    }
  },
  "meta": {
    "version": "v1"
  }
}
```

### POST /auth/logout

- Description: invalidate current token.
- Auth: required.

Success 200:

```json
{
  "success": true,
  "data": {
    "message": "Logged out successfully"
  },
  "meta": {
    "version": "v1"
  }
}
```

### GET /catalog/products

- Description: public catalog listing.
- Auth: public.
- Filters: category, subcategory.

Success 200:

```json
{
  "success": true,
  "data": {
    "products": [
      {
        "id": 100,
        "name": "Retrato digital",
        "price": 45,
        "category": "dibujo-encargo"
      }
    ]
  },
  "meta": {
    "version": "v1",
    "pagination": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 20,
      "total": 1
    }
  }
}
```

### GET /catalog/products/{id}

- Description: get catalog product details.
- Auth: public.

### POST /catalog/products

- Description: create catalog product.
- Auth: required + backoffice role.

### PATCH /catalog/products/{id}

- Description: update catalog product.
- Auth: required + backoffice role.

### POST /cart

- Description: create or get current active cart for authenticated user.
- Auth: required.

Success 200:

```json
{
  "success": true,
  "data": {
    "cart": {
      "id": 21,
      "user_id": 10,
      "order_date": "2026-04-27",
      "status": "cart",
      "address": "No address provided",
      "total": 0,
      "items": []
    }
  },
  "meta": {
    "version": "v1"
  }
}
```

### GET /cart

- Description: retrieve current active cart.
- Auth: required.

Success 200: same payload as POST /cart.

Error 404:

```json
{
  "success": false,
  "error": {
    "code": "RESOURCE_NOT_FOUND",
    "message": "Cart not found",
    "trace_id": "uuid"
  }
}
```

### POST /cart/items

- Description: add item to current cart and recalculate cart total.
- Auth: required.

Request body:

```json
{
  "name": "Retrato digital",
  "price": 45,
  "quantity": 1,
  "description": "Retrato personalizado",
  "category": "dibujo-encargo",
  "subcategory": "digital",
  "delivery_type": "digital",
  "delivery_time": "7"
}
```

### POST /cart/items/from-catalog

- Description: add item to cart from catalog reference.
- Auth: required.

Request body:

```json
{
  "product_id": 100,
  "quantity": 3
}
```

### PATCH /cart/items/{id}

- Description: update quantity for one line in current active cart and recalculate total.
- Auth: required.

Request body:

```json
{
  "quantity": 4
}
```

Success 200:

```json
{
  "success": true,
  "data": {
    "cart": {
      "id": 21,
      "status": "cart",
      "total": 180
    },
    "item": {
      "id": 77,
      "name": "Retrato digital",
      "quantity": 4
    }
  },
  "meta": {
    "version": "v1"
  }
}
```

### DELETE /cart/items/{id}

- Description: remove one line from current active cart and recalculate total.
- Auth: required.

Success 200:

```json
{
  "success": true,
  "data": {
    "cart": {
      "id": 21,
      "status": "cart",
      "total": 90,
      "items": []
    }
  },
  "meta": {
    "version": "v1"
  }
}
```

Success 201:

```json
{
  "success": true,
  "data": {
    "cart": {
      "id": 21,
      "status": "cart",
      "total": 135
    },
    "item": {
      "id": 77,
      "name": "Retrato digital",
      "quantity": 3
    }
  },
  "meta": {
    "version": "v1"
  }
}
```

Success 201:

```json
{
  "success": true,
  "data": {
    "cart": {
      "id": 21,
      "user_id": 10,
      "status": "cart",
      "total": 45
    },
    "item": {
      "id": 77,
      "name": "Retrato digital",
      "price": 45,
      "quantity": 1
    }
  },
  "meta": {
    "version": "v1"
  }
}
```

### POST /cart/checkout

- Description: close active cart and move it to pending status.
- Auth: required.

Request body:

```json
{
  "address": "Avenida Principal 45"
}
```

Success 200:

```json
{
  "success": true,
  "data": {
    "order": {
      "id": 21,
      "status": "pending",
      "address": "Avenida Principal 45",
      "total": 45
    }
  },
  "meta": {
    "version": "v1"
  }
}
```

Business rule error 422:

```json
{
  "success": false,
  "error": {
    "code": "BUSINESS_RULE_VIOLATION",
    "message": "Cart is empty",
    "trace_id": "uuid"
  }
}
```

### GET /orders/my

- Description: list non-cart orders from current user.
- Auth: required.
- Filters: status (all, pending, paid, shipped, cancelled, rejected, done), per_page.

Success 200:

```json
{
  "success": true,
  "data": {
    "orders": [
      {
        "id": 21,
        "status": "pending"
      }
    ]
  },
  "meta": {
    "version": "v1",
    "pagination": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 10,
      "total": 1
    },
    "filters": {
      "status": "all"
    }
  }
}
```

### GET /health

- Description: health check and API version marker.
- Auth: public.

Success 200:

```json
{
  "success": true,
  "data": {
    "service": "fefuart-api",
    "status": "ok"
  },
  "meta": {
    "version": "v1"
  }
}
```

### POST /live-art/requests

- Description: create a new Live Art request.
- Auth: required.

Request body:

```json
{
  "title": "Boda en Sevilla",
  "description": "Quiero ilustracion en directo para ceremonia y cocktail",
  "phone": "600123123",
  "date": "2026-06-15",
  "location": "Sevilla",
  "schedule": "morning"
}
```

### GET /backoffice/orders

- Description: list orders for backoffice operations.
- Auth: required + backoffice role.
- Filters: status (cart, pending, paid, shipped, cancelled), per_page.

Success 200:

```json
{
  "success": true,
  "data": {
    "orders": [
      {
        "id": 21,
        "user_id": 10,
        "status": "pending",
        "total": 120,
        "items": []
      }
    ]
  },
  "meta": {
    "version": "v1",
    "pagination": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 20,
      "total": 1
    }
  }
}
```

### GET /backoffice/summary

- Description: consolidated operational metrics for backoffice dashboard.
- Auth: required + backoffice role.

Success 200:

```json
{
  "success": true,
  "data": {
    "summary": {
      "orders": {
        "pending": 5,
        "paid": 8
      },
      "events": {
        "pending": 2,
        "confirmed": 4
      },
      "catalog_products_total": 30,
      "generated_at": "2026-04-27T22:00:00.000000Z"
    }
  },
  "meta": {
    "version": "v1"
  }
}
```

### PATCH /backoffice/orders/{id}/status

- Description: update order status from backoffice.
- Auth: required + backoffice role.

Request body:

```json
{
  "status": "paid"
}
```

Allowed values: pending, paid, shipped, cancelled.

### GET /backoffice/events

- Description: list live-art events for backoffice operations.
- Auth: required + backoffice role.
- Filters: status (pending, confirmed, rejected, done), per_page.

### PATCH /backoffice/events/{id}/status

- Description: update live-art event status from backoffice.
- Auth: required + backoffice role.

Request body:

```json
{
  "status": "confirmed"
}
```

Allowed values: pending, confirmed, rejected, done.

Backoffice status updates side effect:

- When a backoffice user changes order or event status, the API creates an in-app operational notification for the affected customer.

### GET /notifications/my

- Description: list notifications from current authenticated user.
- Auth: required.
- Query params: per_page (optional, default 15).

Success 200:

```json
{
  "success": true,
  "data": {
    "notifications": [
      {
        "id": 300,
        "context_type": "order",
        "context_id": 21,
        "title": "Estado de pedido actualizado",
        "previous_status": "pending",
        "new_status": "paid",
        "is_read": false
      }
    ]
  },
  "meta": {
    "version": "v1",
    "pagination": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 15,
      "total": 1
    }
  }
}
```

### PATCH /notifications/{id}/read

- Description: mark a notification as read for current authenticated user.
- Auth: required.

Success 200:

```json
{
  "success": true,
  "data": {
    "notification": {
      "id": 300,
      "is_read": true,
      "read_at": "2026-04-27T20:30:00.000000Z"
    }
  },
  "meta": {
    "version": "v1"
  }
}
```

### POST /media/upload

- Description: upload a media file and persist its metadata.
- Auth: required.
- Content type: multipart/form-data.

Request fields:

- file: required image (jpg, jpeg, png, gif, webp), max 5MB.
- context_type: optional (catalog_product, cart_item, live_art_request, general).
- context_id: optional integer, required when context_type is present.
- visibility: optional (public, private). default public.

Success 201:

```json
{
  "success": true,
  "data": {
    "asset": {
      "id": 501,
      "user_id": 10,
      "path": "media-assets/2026/04/abc123.png",
      "mime_type": "image/png",
      "visibility": "public"
    }
  },
  "meta": {
    "version": "v1"
  }
}
```

### GET /media/{id}

- Description: get media metadata by id.
- Auth: public for visibility public, required for private media.

### DELETE /media/{id}

- Description: delete media asset and file from storage.
- Auth: required.
- Authorization: owner or backoffice role.

Validation rules:

- title: required, string, max 255.
- description: nullable, string.
- phone: nullable, string, max 30.
- date: required, date, after_or_equal today.
- location: required, string, max 255.
- schedule: required, one of morning or evening.

Success 201:

```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Boda en Sevilla",
    "description": "Quiero ilustracion en directo para ceremonia y cocktail",
    "phone": "600123123",
    "date": "2026-06-15",
    "location": "Sevilla",
    "schedule": "morning",
    "status": "pending",
    "user_id": 5,
    "created_at": "2026-04-27T15:00:00.000000Z",
    "updated_at": "2026-04-27T15:00:00.000000Z"
  },
  "meta": {
    "version": "v1"
  }
}
```
