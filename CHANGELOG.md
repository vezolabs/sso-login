# Changelog

All notable changes to `vezolabs/pimcore-sso-login-bundle` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-18

### Added
- Native Pimcore 12 Studio integration using React 18, TypeScript, and Module Federation.
- Microsoft Entra ID (Azure AD) step-by-step setup wizard with instant copyable SP endpoints.
- Generic SAML 2.0 Identity Provider support with multi-provider capability.
- Security-hardened SAML 2.0 metadata parser with XML External Entity (XXE) injection prevention.
- SSRF-safe remote federation metadata importer restricting private, loopback, and link-local IP ranges.
- Just-in-Time (JIT) user provisioning with automatic username sanitization and collision deduplication.
- Visual attribute mapping supporting arbitrary claim URIs (email, given name, surname, display name, object ID).
- Granular role mapping with configurable unmapped policies (`deny`, `restricted`, `manual_approval`).
- Role revocation policies on login to synchronize group membership changes.
- Dual-certificate rollover management with zero-downtime certificate promotion.
- Automated X.509 certificate expiration tracking and health alerts.
- Dedicated Studio dashboard overview with real-time configuration health status.
- Full audit logging tracking every SSO authentication event, client IP, user agent, and correlation ID.
- CLI diagnostics tool: `bin/console vezolabs:sso:test`.
- CLI certificate promotion tool: `bin/console vezolabs:sso:rotate-cert`.
- Emergency local login bypass via `?local=1` query parameter when local login form is hidden.
- Automated test suite covering metadata parsing, SSRF prevention, certificate management, role resolution, and security settings.
