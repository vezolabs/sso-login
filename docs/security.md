# Security Architecture & Hardening

VezoLabs SSO Login is built adhering to enterprise-grade security standards and OWASP recommendations for SAML 2.0 implementations.

---

## 1. SAML Cryptography & Signature Enforcement

- **Strict Validation Mode**: All SAML assertions and responses undergo cryptographic signature verification with strict mode enabled (`'strict' => true`).
- **Signature Algorithms**: Enforces modern SHA-256 signatures (`http://www.w3.org/2001/04/xmldsig-more#rsa-sha256`) and SHA-256 message digests (`http://www.w3.org/2001/04/xmlenc#sha256`). Deprecated SHA-1 algorithms are rejected.
- **Assertion and Response Signatures**: Supports demanding both message signatures (`wantMessagesSigned`) and assertion signatures (`wantAssertionsSigned`).

---

## 2. XML External Entity (XXE) Protection

SAML metadata and response XML parsing are protected against XXE injection attacks:
- `libxml_disable_entity_loader(true)` is activated on compatible runtimes.
- `LIBXML_NONET` flag is strictly enforced to disallow network resolution during DOM loading.
- External DTD parsing and entity resolution are disabled.

---

## 3. Server-Side Request Forgery (SSRF) Protection

When fetching federation metadata from remote URLs (`MetadataFetcher`):
- URLs must strictly use `https://`. Insecure plain HTTP endpoints are rejected.
- Hostnames resolving to loopback (`127.0.0.0/8`, `::1`), link-local (`169.254.0.0/16`), private networks (`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `fc00::/7`), and cloud metadata services are blocked before making any socket connection.
- Request timeouts and response payload byte limits (5 MB max) prevent denial-of-service attempts.

---

## 4. Session Fixation & Security

- Upon successful SAML authentication, the user session is regenerated using `SsoSessionManager::regenerateSession()`.
- The session is authenticated directly into Pimcore's `_security_pimcore_admin` context, wrapping the authenticated `Pimcore\Security\User\User` in a Symfony `PostAuthenticationToken`.

---

## 5. Dual Certificate Rollover

- Supports configuring both a **Primary X.509 Certificate** and an optional **Rollover Certificate**.
- When both certificates are present, incoming SAML assertions signed by either certificate are accepted, allowing zero-downtime key rotation before old certificates expire.
- Once the identity provider completes the key switchover, administrators can promote the rollover key via the Studio UI or via CLI:
  ```bash
  bin/console vezolabs:sso:rotate-cert entra-id
  ```

---

## 6. Emergency Local Login Access

If an administrator configures **Hide Local Login** or **Auto-redirect to SSO** and the external identity provider experiences an outage, administrators can bypass the SSO redirect and display the traditional username/password form by adding the `?local=1` query parameter:

```
https://your-domain.com/pimcore-studio/login?local=1
```
