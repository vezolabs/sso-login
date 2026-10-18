# VezoLabs SSO Login for Pimcore 12 Studio

[![CI](https://github.com/vezolabs/sso-login/actions/workflows/ci.yml/badge.svg)](https://github.com/vezolabs/sso-login/actions/workflows/ci.yml)
[![Software License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%3E%3D%208.2-777bb4.svg)](https://php.net)
[![Pimcore](https://img.shields.io/badge/pimcore-12%20Studio-6c2e91.svg)](https://pimcore.com)
[![Packagist Version](https://img.shields.io/packagist/v/vezolabs/pimcore-sso-login-bundle.svg)](https://packagist.org/packages/vezolabs/pimcore-sso-login-bundle)

**VezoLabs SSO Login** is an enterprise-grade Single Sign-On (SSO) bundle built for **Pimcore 12 Studio**. It enables seamless SAML 2.0 authentication with **Microsoft Entra ID** (Azure AD) and generic SAML identity providers.

Technical administrators install the package once via Composer. From that point forward, all routine SSO management—identity provider setup, SAML metadata ingestion, claim mapping, role assignments, JIT provisioning, certificate rotation, and audit monitoring—is handled entirely through the **Pimcore Studio UI** (`Settings → SSO Login`).

---

## Screenshots & Interface

<!-- Visual placeholders: Drop screenshots into docs/images/ -->

| Overview Dashboard | Microsoft Entra ID Setup Wizard |
| :---: | :---: |
| ![Overview Dashboard](docs/images/overview-dashboard.png) | ![Entra ID Wizard](docs/images/entra-wizard.png) |

| Role & Attribute Mapping | Audit Log & Diagnostics |
| :---: | :---: |
| ![Role Mapping](docs/images/role-mapping.png) | ![Audit Logs](docs/images/audit-logs.png) |

---

## Video Walkthrough

<!-- Video walkthrough placeholder -->

> A video walkthrough covering installation, Microsoft Entra ID configuration, and role mappings will be linked here.
> 
> [![Video Walkthrough](https://img.shields.io/badge/YouTube-Video%20Guide%20(Coming%20Soon)-red?logo=youtube)](https://vezolabs.com)

---

## Key Features

- **Native Pimcore 12 Studio UI**: Built with React 18, TypeScript, and Module Federation. Registers directly into Studio navigation (`Settings → SSO Login`) and injects branded SSO buttons into the Studio login form via slot renderers. Zero legacy ExtJS dependencies.
- **Microsoft Entra ID Wizard**: A step-by-step 7-stage configuration guide providing instant copyable Service Provider endpoints (Entity ID, ACS URL, Metadata URL).
- **Flexible Metadata Ingestion**: Upload Federation Metadata XML, fetch dynamically from remote HTTPS URLs with live preview, or configure SAML endpoints manually.
- **Enterprise SAML 2.0 Security**:
  - Full cryptographic verification powered by `onelogin/php-saml` and `robrichards/xmlseclibs`.
  - Enforced SHA-256 (`RSA-SHA256` signatures and `SHA256` message digests).
  - Strict XML External Entity (XXE) injection protection.
  - Server-Side Request Forgery (SSRF) filters blocking loopback, link-local, and private RFC 1918 CIDRs.
- **Visual Attribute & Role Mapping**:
  - Map SAML assertion claims to Pimcore user profile properties.
  - Map external roles or Azure AD groups to Pimcore roles without writing code.
  - Configurable unmapped policies: **Deny Access** (default), **Restricted Role**, or **Manual Approval**.
  - Role revocation policies on login while preserving local Pimcore roles.
  - Hardened against unauthorized super-admin elevation.
- **Just-in-Time (JIT) User Provisioning**:
  - Automatically provisions users on their first SSO login.
  - Automatic username sanitization and collision deduplication.
  - Domain restrictions to whitelist specific corporate email domains.
  - Enforcement of disabled account policies.
- **Dual Certificate Rollover**:
  - Supports Primary and Secondary (Rollover) X.509 signing certificates simultaneously for zero-downtime key rotation.
  - Real-time expiration tracking, health alerts, and fingerprint calculations (SHA-1 / SHA-256).
  - Built-in self-signed SP certificate generator.
- **Comprehensive Audit Trail**:
  - Logs every authentication attempt with timestamp, user ID, status, failure reasons, mapped roles, IP address, user agent, and correlation ID.
- **Emergency Local Access**:
  - When local login is hidden or auto-redirect is enabled, administrators can bypass SSO at any time using `?local=1` (e.g., `/pimcore-studio/login?local=1`).
- **CLI Utilities**:
  - `bin/console vezolabs:sso:test`: Run pre-flight health diagnostics.
  - `bin/console vezolabs:sso:rotate-cert`: Promote rollover certificates in a single step.

---

## System Requirements & Compatibility

| Requirement | Supported Version |
|---|---|
| **Pimcore** | Pimcore 12 with Studio UI |
| **PHP** | `^8.2`, `^8.3`, `^8.4`, `^8.5` |
| **Database** | MariaDB 10.6+ or MySQL 8.0+ |
| **PHP Extensions** | `openssl`, `dom`, `libxml`, `mbstring`, `json`, `pdo_mysql` |

---

## Legacy Pimcore Support & Customization

Need SSO for an earlier Pimcore version, or require bespoke enterprise integration?

- **Pimcore 6, Pimcore 10, or Pimcore 11**: We maintain dedicated builds and backports tailored for classic Pimcore architectures.
- **Custom Identity Providers & Protocols**: Custom claims engines, multi-tenant directory synchronizations, SCIM provisioning, and OpenID Connect (OIDC).

👉 **[Contact VezoLabs for Customization & Legacy Support](https://vezolabs.com/contact)**

---

## Installation

### 1. Require Package via Composer

```bash
composer require vezolabs/pimcore-sso-login-bundle
```

### 2. Enable Bundle

If not auto-registered by Symfony Flex, add the bundle to `config/bundles.php`:

```php
return [
    // ...
    Vezolabs\SsoLoginBundle\VezolabsSsoLoginBundle::class => ['all' => true],
];
```

### 3. Run Bundle Installer

```bash
bin/console pimcore:bundle:install VezolabsSsoLoginBundle
```

### 4. Publish Assets & Clear Cache

```bash
bin/console assets:install public
bin/console cache:clear
```

### 5. Verify Installation

```bash
bin/console pimcore:bundle:list
bin/console vezolabs:sso:test
```

---

## Quick Start Guide

1. Log into Pimcore Studio (`/pimcore-studio/`) with administrator privileges.
2. In the navigation menu, select **Settings → SSO Login**.
3. Open the **Entra ID Wizard** tab:
   - Copy the generated **Entity ID** and **ACS URL** into your Microsoft Entra Enterprise Application.
   - Download the **Federation Metadata XML** from Azure and upload it into Step 4 (or enter your App Federation Metadata URL).
4. Go to **Role Mapping**:
   - Map your incoming Azure application roles or groups to your target Pimcore roles.
5. In **Provisioning Settings**:
   - Configure your allowed email domains and account creation rules.
6. Click **Test Configuration** to verify your SAML response without affecting existing users.
7. Switch the provider status to **Enabled**. The SSO login button will appear on the Studio login page.

---

## Emergency Local Login Access

If your external identity provider experiences downtime or signing certificates expire, administrators can bypass SSO redirection by appending `?local=1` to the login URL:

```
https://your-domain.com/pimcore-studio/login?local=1
```

This immediately renders the native username and password form.

---

## CLI Commands

### Health Diagnostics
Analyze provider configuration against our 10-point SAML checklist:

```bash
bin/console vezolabs:sso:test
bin/console vezolabs:sso:test entra-id
```

### Promote Rollover Certificate
Promote a secondary rollover certificate to primary without touching code:

```bash
bin/console vezolabs:sso:rotate-cert entra-id
```

---

## Documentation

Detailed documentation is available in the [`docs/`](docs/) directory:

- [Installation & Activation](docs/installation.md)
- [Microsoft Entra ID Setup Guide](docs/entra-setup.md)
- [Studio UI Configuration Reference](docs/configuration.md)
- [Role Mapping & Provisioning Policies](docs/role-mapping.md)
- [Security Architecture & Hardening](docs/security.md)
- [Troubleshooting & Diagnostics](docs/troubleshooting.md)

---

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) for development workflow, coding standards, and testing procedures.

To run the automated PHPUnit test suite:

```bash
vendor/bin/phpunit -c phpunit.xml.dist
```

---

## License

This project is licensed under the [MIT License](LICENSE).

---

## Commercial Support & Consulting

VezoLabs provides professional consulting, enterprise deployment, and custom Pimcore development.

- Website: [https://vezolabs.com](https://vezolabs.com)
- Inquiries: [https://vezolabs.com/contact](https://vezolabs.com/contact)
