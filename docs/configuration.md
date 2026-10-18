# Studio UI Administration Guide

After installing `vezolabs/pimcore-sso-login-bundle`, all routine SSO management is performed directly inside the Pimcore 12 Studio interface under **Settings → SSO Login**.

---

## Navigation & Sections

The management interface is organized into 8 tabs:

### 1. Overview Dashboard
- Real-time health metrics (SSO Enabled/Disabled status, number of active providers, managed user count, last successful/failed logins).
- Outstanding security warnings and configuration checklists.
- Quick actions: Add Identity Provider, Test Configuration, View Audit Logs.

### 2. Identity Providers
- Manage multiple SAML identity providers (Microsoft Entra ID, Okta, Ping Identity, Keycloak, generic SAML 2.0).
- Toggle providers on or off.
- Set default provider for login button prominence.
- Configure login button label and icon.

### 3. Entra ID Setup Wizard
- 7-step guided setup tailored for non-technical administrators.
- Copyable SP Entity ID, ACS URL, and Metadata URLs.
- Metadata XML file upload, URL import, or manual endpoint configuration.
- Read-only preview test before activation.

### 4. Attribute Mapping
- Map SAML claim URIs to Pimcore user properties (`email`, `firstname`, `lastname`, `displayname`, `external_id`).
- Supports full Microsoft claim namespaces (e.g., `http://schemas.microsoft.com/identity/claims/objectidentifier`).

### 5. Role Mapping
- Map incoming Azure application roles or group claims to Pimcore roles.
- Configure unmapped role policy: **Deny Access** (default), **Restricted Access**, or **Manual Approval**.
- Choose role sync frequency: **Every Login** or **First Login Only**.
- Toggle role revocation for users removed from external groups.

### 6. User Provisioning
- Enable or disable Just-in-Time (JIT) user creation.
- Enforce domain restrictions (whitelist specific email domains).
- Configure handling of disabled Pimcore user accounts.
- Choose whether to synchronize profile attributes on login.

### 7. Certificates & Rollover
- View active X.509 certificate status, expiration dates, days remaining, and cryptographic fingerprints (SHA-1 / SHA-256).
- Configure secondary rollover certificate for zero-downtime key rotation.
- Generate self-signed SP certificate and private key.
- One-click certificate promotion.

### 8. Audit Logs
- Comprehensive authentication audit trail.
- View timestamps, username, external user ID, provider name, status (SUCCESS/FAILED/DENIED), reason, assigned roles, IP address, user agent, and correlation ID.
- Search and filter by status, provider, or username.
