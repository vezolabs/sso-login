# Troubleshooting & Diagnostic Guide

This guide helps administrators diagnose and resolve SAML Single Sign-On issues in Pimcore 12 Studio.

---

## 1. Running Diagnostic Health Checks

The bundle provides an automated CLI diagnostics command that analyzes your identity provider configuration against a 10-point checklist:

```bash
bin/console vezolabs:sso:test
```

Or for a specific provider:

```bash
bin/console vezolabs:sso:test entra-id
```

The output will display the exact status of:
- IdP Entity ID / Issuer URI
- SSO Login URL
- Primary & Rollover X.509 Signing Certificates
- Attribute Mapping configuration
- Role Mapping & Policy resolution
- Assertion Signature Enforcement

---

## 2. Common SAML Errors & Resolutions

### A. "Invalid SAML Response: Signature verification failed"
- **Cause**: The IdP signing certificate entered in Pimcore does not match the private key used by Azure AD to sign the assertion.
- **Solution**: Open Microsoft Entra ID → App registrations / Enterprise applications → Single sign-on → SAML Certificates. Download the latest **Certificate (Base64)** or **Federation Metadata XML** and upload it via the Studio wizard.

### B. "SAML response processing failed: Time condition validation failed (clock skew)"
- **Cause**: Server clock drift between the Pimcore server and Microsoft Entra ID servers.
- **Solution**: Ensure your server synchronizes time via NTP (`chrony` or `systemd-timesyncd`). The bundle allows an internal clock skew tolerance of up to 180 seconds.

### C. "Authentication succeeded, but no Microsoft Entra / SAML role is mapped to an authorized Pimcore role"
- **Cause**: The user logged in successfully via Azure AD, but their assigned roles are not mapped in Pimcore, and the provider's **Unmapped Role Policy** is set to `Deny access`.
- **Solution**: 
  1. Check the Audit Log in **Settings → SSO Login → Audit Logs** to see the exact claims and external roles sent by Azure.
  2. In **Settings → SSO Login → Role Mapping**, add a mapping from the incoming Azure role to a valid Pimcore role.
  3. Alternatively, adjust the policy to `Restricted fallback` if appropriate.

### D. "Domain is not in the list of allowed domains for this provider"
- **Cause**: The user authenticated with an email address from an unauthorized domain.
- **Solution**: Verify the **Allowed Domains** setting in **Settings → SSO Login → Provisioning Settings**.

---

## 3. Emergency Access Bypass

If you accidentally locked yourself out by enabling **Hide Local Login** or **Auto-redirect to SSO** with an invalid SAML configuration:

Navigate to:
```
https://your-domain.com/pimcore-studio/login?local=1
```
This forces Pimcore Studio to show the local username/password login fields. Log in with your standard administrative credentials to fix the SSO settings.

---

## 4. Inspecting Audit Logs

Every single SSO authentication attempt (success, failure, role assignment, external user ID, IP address, user agent, correlation ID) is stored in the database.

View logs in:
- Pimcore Studio: **Settings → SSO Login → Audit Logs**
- Via SQL query:
  ```sql
  SELECT timestamp, username, status, reason, correlation_id FROM sso_audit_logs ORDER BY id DESC LIMIT 50;
  ```
