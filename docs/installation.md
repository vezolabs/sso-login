# Installation Guide — VezoLabs SSO Login

## System Requirements

- **PHP**: 8.2 or higher (8.4 and 8.5 fully supported)
- **Pimcore**: Pimcore 12 with Studio UI
- **Required PHP Extensions**: `openssl`, `dom`, `libxml`, `mbstring`, `json`, `pdo_mysql`
- **Dependencies**: `onelogin/php-saml` (^4.3), `robrichards/xmlseclibs` (^3.1)

---

## 1. Install via Composer

Add the bundle to your Pimcore project via Composer:

```bash
composer require vezolabs/pimcore-sso-login-bundle
```

---

## 2. Enable the Bundle

If the bundle is not automatically registered by Symfony Flex, add it to your `config/bundles.php`:

```php
return [
    // ... other bundles
    Vezolabs\SsoLoginBundle\VezolabsSsoLoginBundle::class => ['all' => true],
];
```

---

## 3. Run Bundle Installer

Execute the bundle installation command. This sets up the database tables (`sso_identity_providers`, `sso_external_identities`, `sso_audit_logs`, `sso_config_history`) and registers the required Studio permissions:

```bash
bin/console pimcore:bundle:install VezolabsSsoLoginBundle
```

---

## 4. Install Public Assets & Clear Cache

Publish the frontend assets (Module Federation remotes) and clear the Symfony cache:

```bash
bin/console assets:install public
bin/console cache:clear
```

---

## 5. Verify Installation

Verify the bundle is active and ready:

```bash
bin/console pimcore:bundle:list
bin/console vezolabs:sso:test
```

Log into Pimcore Studio (`/pimcore-studio/`) as an administrator. You will find the **Settings → SSO Login** menu entry in the navigation bar. Routine SSO setup and management is performed directly inside this Studio UI without requiring further code deployments.
