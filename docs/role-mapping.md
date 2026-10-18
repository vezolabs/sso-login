# SSO Role Mapping & Provisioning Policies

VezoLabs SSO Login provides granular, zero-code role mapping and user lifecycle controls directly within the Pimcore Studio interface.

---

## 1. Role Resolution Architecture

When a user logs in via SAML, their assertion claims are inspected for external role assignments. The bundle automatically extracts role and group values from:

- `http://schemas.microsoft.com/ws/2008/06/identity/claims/role`
- `http://schemas.microsoft.com/ws/2008/06/identity/claims/groups`
- `roles` / `groups`
- `role` / `group`

Each incoming external role is looked up in the provider's **Role Mapping table**.

---

## 2. Unmapped Role Policies

What happens when an authenticated user does NOT have any role mapped to a Pimcore role? Administrators can choose between three strict policies:

| Policy | Behavior | Security Level |
|---|---|---|
| **Deny access** (`deny`) | Immediate rejection. The user receives an Access Denied screen, and the failed login is recorded in the Audit Log. | **Highest (Default & Recommended)** |
| **Restricted access** (`restricted`) | The user is assigned a single, designated read-only or restricted Pimcore role configured by the administrator. | Medium |
| **Pending approval** (`manual_approval`) | The user is created in an inactive/pending state and cannot access Studio until an administrator manually approves their account. | High |

---

## 3. Role Synchronization Policies

How roles are synchronized across subsequent logins:

- **Every Login (`every_login`)**: Role assignments are updated on every single SSO login to reflect changes in Microsoft Entra ID or your identity provider.
- **First Login Only (`first_login_only`)**: Roles are assigned upon user provisioning only. Subsequent role modifications are managed manually inside Pimcore.

---

## 4. Role Revocation Safeguard

When **Revoke removed roles on login** is enabled:
- If a user is removed from an Azure AD group or role, the corresponding Pimcore role is automatically revoked on their next login.
- **Local Role Preservation**: Roles assigned to the user locally inside Pimcore that are NOT part of the SSO provider's mapping table are **never revoked**. This ensures local administrative permissions remain intact.
- **Super-Admin Protection**: The bundle **never automatically elevates users to super-admin status**. Super-admin privileges must be granted explicitly.

---

## 5. Domain Restrictions

To prevent unauthorized cross-tenant logins or unauthorized email domains, configure the **Allowed Domains** filter:

- Enter allowed email domains (e.g. `vezolabs.com`, `company.org`).
- Any user authenticating with an email from an unlisted domain is immediately rejected before account provisioning or session initialization.
