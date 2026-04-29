# API Documentation

## Overview

The Fayyaz Travels CRM API provides RESTful endpoints for managing tickets, messages, notifications, and user data. All API responses are in JSON format.

## Authentication

All API endpoints require authentication via Auth0 session. The session is validated automatically for each request.

## Base URL
```
https://crm.fayyaz.travel/api/
```

## Response Format

### Success Response
```json
{
    "success": true,
    "data": {...},
    "message": "Operation completed successfully"
}
```

### Error Response
```json
{
    "success": false,
    "error": "Error message",
    "code": "ERROR_CODE"
}
```

## Endpoints

### Authentication

#### GET /login.php
Initiates Google OAuth authentication flow.

**Response**: Redirects to Auth0 login page

#### GET /callback.php
Handles OAuth callback from Auth0.

**Parameters**:
- `code` (string): Authorization code from Auth0
- `state` (string): State parameter for CSRF protection

**Response**: Redirects to dashboard or login page

#### GET /logout.php
Logs out the current user and clears session.

**Response**: Redirects to Auth0 logout page

### Ticket Management

#### POST /api/create-ticket.php
Creates a new ticket.

**Parameters**:
```json
{
    "ticket_type": "estimate|supplier|general",
    "priority": "LOW|MEDIUM|HIGH|URGENT",
    "status": "OPEN|IN_PROGRESS|RESOLVED|CLOSED",
    "customer_name": "string",
    "billing_address": "string",
    "email": "string",
    "contact_number": "string",
    "consultant_name": "string",
    "service_date": "YYYY-MM-DD",
    "package_details": "string",
    "number_of_persons": "integer",
    "rate_per_person": "decimal",
    "total_amount": "decimal",
    "description": "string",
    "estimated_time": "string"
}
```

**Response**:
```json
{
    "success": true,
    "data": {
        "ticket_id": 123,
        "ticket_type": "estimate",
        "status": "OPEN"
    },
    "message": "Ticket created successfully"
}
```

#### GET /api/get-ticket.php
Retrieves ticket details.

**Parameters**:
- `ticket_id` (integer): Ticket ID
- `ticket_type` (string): Type of ticket (estimate|supplier|general)

**Response**:
```json
{
    "success": true,
    "data": {
        "id": 123,
        "user_id": 1,
        "priority": "HIGH",
        "status": "OPEN",
        "customer_name": "John Doe",
        "billing_address": "123 Main St",
        "email": "john@example.com",
        "contact_number": "+1234567890",
        "consultant_name": "Jane Smith",
        "service_date": "2025-01-15",
        "package_details": "Europe Tour Package",
        "number_of_persons": 4,
        "rate_per_person": 2500.00,
        "total_amount": 10000.00,
        "description": "Family vacation to Europe",
        "estimated_time": "2 weeks",
        "created_at": "2025-01-10 10:30:00",
        "updated_at": "2025-01-10 10:30:00"
    }
}
```

#### POST /api/update-ticket.php
Updates ticket information.

**Parameters**:
```json
{
    "ticket_id": 123,
    "ticket_type": "estimate",
    "priority": "HIGH",
    "status": "IN_PROGRESS",
    "description": "Updated description",
    "estimated_time": "3 weeks"
}
```

**Response**:
```json
{
    "success": true,
    "data": {
        "ticket_id": 123,
        "updated_fields": ["priority", "status", "description"]
    },
    "message": "Ticket updated successfully"
}
```

### Messaging System

#### POST /api/messages_send.php
Sends a message to another user.

**Parameters**:
```json
{
    "recipient_id": 2,
    "message": "Hello, how are you?",
    "attachments": [
        {
            "name": "document.pdf",
            "type": "application/pdf",
            "data": "base64_encoded_data"
        }
    ]
}
```

**Response**:
```json
{
    "success": true,
    "data": {
        "message_id": 456,
        "sender_id": 1,
        "recipient_id": 2,
        "message": "Hello, how are you?",
        "created_at": "2025-01-10 11:00:00"
    },
    "message": "Message sent successfully"
}
```

#### GET /api/messages_list.php
Retrieves message history.

**Parameters**:
- `user_id` (integer): User ID to get messages for
- `limit` (integer, optional): Number of messages to retrieve (default: 50)
- `offset` (integer, optional): Offset for pagination (default: 0)

**Response**:
```json
{
    "success": true,
    "data": {
        "messages": [
            {
                "id": 456,
                "sender_id": 1,
                "recipient_id": 2,
                "message": "Hello, how are you?",
                "is_read": false,
                "created_at": "2025-01-10 11:00:00",
                "attachments": []
            }
        ],
        "total": 150,
        "has_more": true
    }
}
```

#### GET /api/messages_sse.php
Server-Sent Events endpoint for real-time message updates.

**Headers**:
- `Content-Type: text/event-stream`
- `Cache-Control: no-cache`
- `Connection: keep-alive`

**Response**:
```
data: {"type": "message", "data": {...}}

data: {"type": "user_online", "data": {"user_id": 2}}

data: {"type": "notification", "data": {...}}
```

#### POST /api/mark_messages_read.php
Marks messages as read.

**Parameters**:
```json
{
    "message_ids": [456, 457, 458],
    "user_id": 1
}
```

**Response**:
```json
{
    "success": true,
    "data": {
        "updated_count": 3
    },
    "message": "Messages marked as read"
}
```

### User Management

#### GET /api/user_list.php
Retrieves list of users.

**Parameters**:
- `limit` (integer, optional): Number of users to retrieve (default: 100)
- `offset` (integer, optional): Offset for pagination (default: 0)
- `search` (string, optional): Search term for user names

**Response**:
```json
{
    "success": true,
    "data": {
        "users": [
            {
                "id": 1,
                "auth0_id": "auth0|123456789",
                "email": "user@example.com",
                "name": "John Doe",
                "is_admin": false,
                "created_at": "2025-01-01 00:00:00",
                "is_online": true,
                "last_seen": "2025-01-10 11:30:00"
            }
        ],
        "total": 25
    }
}
```

### Notifications

#### GET /api/notifications.php
Retrieves user notifications.

**Parameters**:
- `user_id` (integer): User ID
- `limit` (integer, optional): Number of notifications (default: 20)
- `offset` (integer, optional): Offset for pagination (default: 0)
- `unread_only` (boolean, optional): Return only unread notifications (default: false)

**Response**:
```json
{
    "success": true,
    "data": {
        "notifications": [
            {
                "id": 789,
                "user_id": 1,
                "ticket_id": 123,
                "ticket_type": "estimate",
                "type": "info",
                "title": "New Ticket Created",
                "message": "A new estimate ticket has been created",
                "is_read": false,
                "created_at": "2025-01-10 10:30:00"
            }
        ],
        "total": 45,
        "unread_count": 12
    }
}
```

#### POST /api/mark_notification_read.php
Marks notifications as read.

**Parameters**:
```json
{
    "notification_ids": [789, 790],
    "user_id": 1
}
```

**Response**:
```json
{
    "success": true,
    "data": {
        "updated_count": 2
    },
    "message": "Notifications marked as read"
}
```

## Error Codes

| Code | Description |
|------|-------------|
| `AUTH_REQUIRED` | Authentication required |
| `INVALID_TOKEN` | Invalid or expired token |
| `PERMISSION_DENIED` | User doesn't have required permissions |
| `TICKET_NOT_FOUND` | Ticket not found |
| `USER_NOT_FOUND` | User not found |
| `INVALID_PARAMETERS` | Invalid request parameters |
| `DATABASE_ERROR` | Database operation failed |
| `FILE_UPLOAD_ERROR` | File upload failed |
| `RATE_LIMIT_EXCEEDED` | Too many requests |

## Rate Limiting

- **Authentication endpoints**: 10 requests per minute
- **Ticket operations**: 100 requests per minute
- **Messaging**: 200 requests per minute
- **File uploads**: 50 requests per minute

## File Upload

### Supported File Types
- **Images**: JPG, PNG, GIF, WebP (max 10MB)
- **Documents**: PDF, DOC, DOCX (max 25MB)
- **Audio**: MP3, WAV, M4A (max 50MB)

### Upload Process
1. Send file as base64 encoded data in request
2. Server validates file type and size
3. File is saved to `assets/uploads/` directory
4. File path is returned in response

## WebSocket Events

### Message Events
```json
{
    "type": "message",
    "data": {
        "id": 456,
        "sender_id": 1,
        "recipient_id": 2,
        "message": "Hello!",
        "created_at": "2025-01-10 11:00:00"
    }
}
```

### User Status Events
```json
{
    "type": "user_status",
    "data": {
        "user_id": 2,
        "status": "online|offline",
        "last_seen": "2025-01-10 11:30:00"
    }
}
```

### Notification Events
```json
{
    "type": "notification",
    "data": {
        "id": 789,
        "title": "New Ticket",
        "message": "A new ticket has been assigned to you",
        "type": "info"
    }
}
```

## Testing

### Using cURL

```bash
# Create a ticket
curl -X POST https://crm.fayyaz.travel/api/create-ticket.php \
  -H "Content-Type: application/json" \
  -d '{
    "ticket_type": "estimate",
    "priority": "HIGH",
    "customer_name": "John Doe",
    "email": "john@example.com",
    "description": "Test ticket"
  }'

# Get ticket details
curl -X GET "https://crm.fayyaz.travel/api/get-ticket.php?ticket_id=123&ticket_type=estimate"

# Send message
curl -X POST https://crm.fayyaz.travel/api/messages_send.php \
  -H "Content-Type: application/json" \
  -d '{
    "recipient_id": 2,
    "message": "Hello from API"
  }'
```

### Using JavaScript

```javascript
// Send message
async function sendMessage(recipientId, message) {
    const response = await fetch('/api/messages_send.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            recipient_id: recipientId,
            message: message
        })
    });
    
    return await response.json();
}

// Get real-time updates
const eventSource = new EventSource('/api/messages_sse.php');
eventSource.onmessage = function(event) {
    const data = JSON.parse(event.data);
    console.log('Received:', data);
};
```

## Security Considerations

1. **Authentication**: All endpoints require valid Auth0 session
2. **Input Validation**: All user inputs are validated and sanitized
3. **SQL Injection**: Protected using prepared statements via Medoo
4. **XSS Protection**: Output is properly escaped
5. **CSRF Protection**: State parameter in OAuth flow
6. **File Upload Security**: File type and size validation
7. **Rate Limiting**: Prevents abuse and DoS attacks

## Versioning

API versioning is handled through URL paths:
- Current version: `/api/`
- Future versions: `/api/v2/`, `/api/v3/`, etc.

## Deprecation Policy

- Deprecated endpoints will be marked with `@deprecated` in documentation
- Deprecated endpoints will continue to work for 6 months
- New versions will be announced 3 months before deprecation 