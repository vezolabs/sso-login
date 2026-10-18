# VezoLabs SSO Login — Production-Ready Pimcore 12 Studio Bundle

[![CI](https://github.com/vezolabs/sso-login/actions/workflows/ci.yml/badge.svg)](https://github.com/vezolabs/sso-login/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.2-777bb4.svg)](https://php.net)
[![Pimcore Version](https://img.shields.io/badge/Pimcore-12%20Studio-6c2e91.svg)](https://pimcore.com)

**VezoLabs SSO Login** is a complete, production-ready, reusable Single Sign-On (SSO) bundle built exclusively for **Pimcore 12 Studio**. It enables enterprise-grade SAML 2.0 authentication with **Microsoft Entra ID** (formerly Azure AD) and generic SAML identity providers.

Administrators install the package **only once via Composer**. All routine SSO configurations, provider setup, claim mappings, role assignments, user provisioning, certificate rollover, and audit logging are managed entirely through the modern **Pimcore Studio UI** under `Settings → SSO Login`.

---

## 🌟 Key Features

- **Native Pimcore 12 Studio Architecture**: Built directly with React 18, TypeScript, and Module Federation. Integrates natively into Studio navigation and renders custom SSO login buttons into the Studio login form via slot renderers. Zero legacy ExtJS dependencies.
- **Microsoft Entra ID Setup Wizard**: 7-step guided configuration wizard for non-technical administrators with copyable SP Entity ID, ACS URL, and metadata endpoints.
- **Multiple IdP Management**: Configure multiple SAML 2.0 identity providers (Microsoft Entra ID, Okta, Ping Identity, Keycloak, or generic SAML 2.0).
- **Flexible Metadata Ingestion**: Upload Federation Metadata XML, import from a remote HTTPS URL with real-time preview, or configure endpoints manually.
- **Enterprise SAML Security**:
  - Full cryptographic signature verification using `onelogin/php-saml` and `robrichards/xmlseclibs`.
  - Enforced SHA-256 signatures (`RSA-SHA256`) and message digests (`SHA256`).
  - Strict XML External Entity (XXE) injection prevention.
  - SSRF protection rejecting loopback, link-local, and private RFC 1918 CIDRs on remote fetches.
- **Visual Attribute Mapping**: Map SAML claim URIs (including Microsoft Entra claim namespaces) to Pimcore user properties (`email`, `firstname`, `lastname`, `displayname`, `external_id`).
- **Granular Role Mapping**:
  - Map external roles or group IDs to Pimcore roles without writing code.
  - Configurable unmapped role policies: **Deny Access** (default), **Restricted Role**, or **Manual Approval**.
  - Strict role synchronization with automatic revocation of removed external roles while preserving local Pimcore roles.
  - Safeguard preventing accidental elevation to super-admin status.
- **Just-in-Time (JIT) Provisioning**:
  - Automatic account provisioning with username sanitization and collision deduplication.
  - Allowed email domain restrictions to prevent cross-tenant access.
  - Disabled account policy enforcement.
- **Dual Certificate Rollover**:
  - Simultaneous support for Primary and Rollover X.509 signing certificates for zero-downtime certificate rotation.
  - Real-time expiration tracking, warning alerts, and SHA-1/SHA-256 fingerprints.
  - Built-in self-signed SP certificate generator.
- **Comprehensive Audit Trail**:
  - Every authentication attempt is logged with timestamp, user ID, status, failure reason, mapped roles, client IP, user agent, and correlation ID.
- **Emergency Local Login**:
  - When local password login is hidden or auto-redirect is enabled, administrators can bypass SSO and display the local login form via `?local=1`.
- **CLI Tooling**:
  - `bin/console vezolabs:sso:test`: Automated 10-point diagnostic health check.
  - `bin/console vezolabs:sso:rotate-cert`: One-command promotion of rollover certificates.

---

## 📦 Package Identity

| Property | Value |
|---|---|
| **Product Name** | VezoLabs SSO Login |
| **GitHub Repository** | [`vezolabs/sso-login`](https://github.com/vezolabs/sso-login) |
| **Composer Package** | `vezolabs/pimcore-sso-login-bundle` |
| **PHP Namespace** | `Vezolabs\SsoLoginBundle\` |
| **Bundle Class** | `Vezolabs\SsoLoginBundle\VezolabsSsoLoginBundle` |
| **Target Platform** | Pimcore 12 Studio |
| **License** | MIT |

---

## 🚀 Installation

### 1. Require Composer Package

```bash
composer require vezolabs/pimcore-sso-login-bundle
```

### 2. Register Bundle

Add the bundle to `config/bundles.php` (if not auto-registered by Symfony Flex):

```php
return [
    // ...
    Vezolabs\SsoLoginBundle\VezolabsSsoLoginBundle::class => ['all' => true],
];
```

### 3. Install Bundle & Assets

```bash
bin/console pimcore:bundle:install VezolabsSsoLoginBundle
bin/console assets:install public
bin/console cache:clear
```

---

## 🖥️ Administration in Pimcore Studio

1. Log into Pimcore Studio (`/pimcore-studio/`) with administrative credentials.
2. In the navigation sidebar, click **Settings → SSO Login**.
3. Use the **Entra ID Wizard** tab to follow the step-by-step walkthrough to connect Microsoft Entra ID.
4. Set up role mappings in the **Role Mapping** tab to grant appropriate Studio permissions.
5. Click **Test Configuration** to run an end-to-end SAML test before enabling the provider.
6. Toggle the provider to **Enabled**. The **Sign in with Microsoft** button will appear on the Studio login screen.

---

## 🛡️ Emergency Local Login Access

If your identity provider experiences downtime or your SAML certificates expire, you can always bypass the SSO redirect and access the native Pimcore username/password login form by adding `?local=1` to the login URL:

```
https://your-domain.com/pimcore-studio/login?local=1
```

---

## 💻 CLI Commands

### Configuration Diagnostics

Run pre-flight checks on all configured providers:

```bash
bin/console vezolabs:sso:test
```

### Certificate Rollover

Promote a secondary rollover certificate to become the primary certificate:

```bash
bin/console vezolabs:sso:rotate-cert entra-id
```

---

## 🧪 Testing

Run the automated PHPUnit test suite:

```bash
vendor/bin/phpunit -c phpunit.xml.dist
```

To build frontend assets:

```bash
cd assets
npm install
npm run build
```

---

## 📚 Documentation

Detailed guides are available in the [`docs/`](docs/) directory:

- [Installation & Setup](docs/installation.md)
- [Microsoft Entra ID Walkthrough](docs/entra-setup.md)
- [Studio UI Configuration](docs/configuration.md)
- [Role Mapping & Provisioning Policies](docs/role-mapping.md)
- [Security Architecture](docs/security.md)
- [Troubleshooting & Diagnostics](docs/troubleshooting.md)

---

## 📄 License

VezoLabs SSO Login is open-source software licensed under the [MIT License](LICENSE).
