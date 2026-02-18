# Changelog

## [3.2.1] - 2025-05-20
### Security
- **Critical**: Added missing capability checks (`manage_options`) to all AJAX endpoints.
- **Encryption**: Improved API key encryption with secure key generation (fallback to legacy key for backward compatibility).

### Changed
- **Architecture**: Refactored Admin AJAX handling into `PostalWarmup\Admin\AjaxHandler`.
- **GDPR**: Added option to disable IP logging for Mailto tracker and anonymize IPs by default.
- **Performance**: Consolidated dashboard AJAX calls into a single endpoint (`pw_get_dashboard_data`).
- **Assets**: Localized Chart.js library to remove external CDN dependency.

## [3.2.0] - 2024-05-24
### Added
- **Architecture**: Complete refactor to PSR-4 standards with `PostalWarmup` namespace.
- **Security**: Added AES-256-CBC encryption for API keys in database.
- **Security**: Added `pw_webhook_strict_mode` option to enforce webhook signature validation.
- **Performance**: Added log rotation (keeps last 5 files) to prevent disk saturation.
- **Performance**: Added "File Only" logging mode (default) to reduce database size.
- **Optimization**: Added composite SQL indexes on `postal_stats` table for faster queries.
- **Dev**: Added `package.json` with build scripts for asset minification.
- **Docs**: Added internal documentation (Architecture, Security, API).

### Changed
- **Core**: Migrated all logic from `includes/` to `src/`.
- **Admin**: Reorganized admin assets into `admin/assets/`.
- **Webhooks**: Removed sensitive signature logging in `WebhookHandler`.
- **Database**: Updated `Activator` to automatically patch missing columns/indexes.

### Removed
- **Legacy**: Removed `admin/class-pw-admin.php` and duplicate code.
- **Legacy**: Removed `includes/` directory.

## [3.1.0] - 2024-04-15
### Added
- Multi-server support.
- JSON Templates V2 with variants.
- Mailto tracker.

## [3.0.0] - 2024-01-10
- Initial public release.
