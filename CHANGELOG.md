# Changelog

All notable changes to `form-handler-otp` will be documented in this file.

## v1.1.0 - 2026-07-31

### Added
- Shared Form Flow screen and action components for OTP capture
- Configurable default, compact, and immersive UI variants
- Server-enforced resend limits and cooldowns
- Laravel 12/13 and Inertia 2/3 compatibility matrix
- Provider-contract tests for Txtcmdr request and verification behavior

### Fixed
- Submit the OTP through the current nested Form Flow payload
- Focus the OTP input reliably
- Fail closed when the flow has no mobile or active verification session

### Changed
- Document Txtcmdr as the OTP generation, delivery, and verification authority
- Require Form Flow 1.8 or newer

## v1.0.0 - 2024-12-24

### Added
- Initial release
- Time-based OTP (TOTP RFC 6238)
- SMS delivery via callback
- Resend functionality with cooldown
- EngageSpark integration
- Auto-registration with Form Flow Manager
- Automated install command
