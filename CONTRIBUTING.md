# Contributing to VezoLabs SSO Login

Thank you for your interest in contributing to VezoLabs SSO Login for Pimcore 12 Studio!

## Development Guidelines

1. **Architecture Integrity**:
   - The bundle targets Pimcore 12 and the Pimcore Studio UI.
   - Do NOT use legacy ExtJS administration APIs or deprecated Pimcore 10/11 classes.
   - All management interfaces must be built for Pimcore Studio using React 18, TypeScript, and Module Federation.

2. **Security by Default**:
   - Never implement SAML cryptography manually. All SAML assertions and signatures must be handled by `onelogin/php-saml` and `robrichards/xmlseclibs`.
   - All XML parsing must enforce XXE protection (`LIBXML_NONET`).
   - Remote URL fetches must undergo SSRF validation (`MetadataFetcher::validateUrl`).
   - JIT user provisioning must never default to super-admin status (`$user->setAdmin(false)`).

3. **Code Style & Standards**:
   - Follow PSR-12 coding standard and PHP 8.2+ strict typing (`declare(strict_types=1);`).
   - Follow Symfony and Pimcore conventions for bundle structure and service registration.

## Running Tests

To run the automated PHPUnit test suite:

```bash
vendor/bin/phpunit -c phpunit.xml.dist
```

## Building Frontend Assets

Frontend assets are built using Rsbuild:

```bash
cd assets
npm install
npm run build
```

The compiled output will be written to `public/build/vezolabs-sso-login/`.

## Submitting Pull Requests

1. Fork the repository and create your feature branch: `git checkout -b feature/my-new-feature`.
2. Commit your changes with clear, descriptive commit messages.
3. Ensure all tests pass and there are no lint or type errors.
4. Push to the branch and open a Pull Request.
