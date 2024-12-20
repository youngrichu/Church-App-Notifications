# Church App Notifications

A WordPress plugin for managing push notifications in the Church App.

## Features

- Send push notifications to app users
- Automatic notifications for new blog posts with:
  - Post title and excerpt
  - Featured image (if available)
  - Direct link to post
- Admin interface for managing notifications:
  - WordPress-style list table with bulk actions
  - Sortable columns
  - Status filters (All/Read/Unread)
  - Pagination
- REST API endpoints for notifications
- Secure device token management:
  - Token validation
  - Automatic cleanup of old tokens
  - Device type tracking
- Notification history tracking
- Duplicate prevention system
- Support for multilingual content (Amharic/English)

## Changelog

### 2.2.0 - 2024-01-08
- Added secure token registration endpoint with JWT authentication
- Added automatic token cleanup system
- Added device type tracking
- Improved token validation and security

### 2.1.0 - 2024-02-20
- Added WordPress-style list table for notifications management
- Added bulk delete functionality
- Added status filters (All/Read/Unread)
- Added sortable columns
- Added pagination
- Improved security with nonce verification
- Added per-row actions

### 2.0.0 - 2024-02-01
- Initial public release
- Basic notifications functionality
- REST API endpoints
- Admin interface

## Installation

1. Upload the plugin files to the `/wp-content/plugins/church-app-notifications` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure the plugin settings under the 'App Notifications' menu

## API Endpoints

### Notifications
- `GET /wp-json/church-app/v1/notifications` - Get notifications
- `PUT /wp-json/church-app/v1/notifications/{id}/read` - Mark notification as read

### Token Management
- `POST /wp-json/church-app/v1/notifications/register-token` - Register device token
  - Requires JWT authentication
  - Request body:
    ```json
    {
      "token": "ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]",
      "device_type": "ios" // optional
    }
    ```
  - Success response:
    ```json
    {
      "success": true,
      "message": "Token registered successfully"
    }
    ```

## Security

- JWT authentication required for token registration
- Input validation and sanitization
- Protection against duplicate token registrations
- Automatic cleanup of unused tokens
- Secure token storage

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## License

GPL v2 or later