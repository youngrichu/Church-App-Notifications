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
- Device token management
- Notification history tracking
- Duplicate prevention system
- Support for multilingual content (Amharic/English)

## Changelog

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

- `GET /wp-json/church-app/v1/notifications` - Get notifications
- `POST /wp-json/church-app/v1/notifications/register-token` - Register device token
- `PUT /wp-json/church-app/v1/notifications/{id}/read` - Mark notification as read

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## License

GPL v2 or later