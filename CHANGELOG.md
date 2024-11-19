# Changelog

All notable changes to the Church App Notifications plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.3.0] - 2024-01-19

### Added
- New dedicated "Send Notification" page in WordPress admin
- WordPress Media Library integration for notification images
- User selection dropdown for targeted notifications
- Notification type categorization (General, Event, News, Announcement)
- Image preview functionality
- WordPress WYSIWYG editor for rich text message formatting

### Changed
- Removed modal-based notification creation for better UX
- Improved notification creation workflow
- Enhanced image upload experience
- Updated JWT authentication to remove external plugin dependency
- Replaced plain textarea with TinyMCE editor for message content

### Fixed
- Fixed 500 Internal Server Error in notifications endpoint
- Fixed image upload issues with modal dialog
- Improved error handling and validation
- Added proper user authentication and filtering

## [2.2.0] - 2024-01-08

### Added
- New REST API endpoint for push notification token registration (`/wp-json/church-app/v1/notifications/register-token/`)
- Secure storage of push notification tokens in the database
- Automatic token cleanup functionality:
  - Keeps only 5 most recent tokens per user
  - Removes tokens not used in the last 90 days
- Token validation for Expo push notification format
- JWT authentication requirement for token registration
- Optional device type tracking for registered tokens

### Security
- Added input validation and sanitization for token registration
- Implemented JWT authentication for the token registration endpoint
- Added protection against duplicate token registrations

## [2.1.0] - Previous version

Initial release of the Church App Notifications plugin with basic notification functionality.
