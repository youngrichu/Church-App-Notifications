# Church App Notifications

A WordPress plugin for managing push notifications in the Church App.

## Features

- Send push notifications to app users
- Automatic notifications for new blog posts
- Admin interface for managing notifications
- REST API endpoints for notifications
- Device token management
- Notification history tracking

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