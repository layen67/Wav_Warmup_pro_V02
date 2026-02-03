# Changelog

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
