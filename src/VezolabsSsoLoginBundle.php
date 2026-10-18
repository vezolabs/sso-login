<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle;

use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\Installer\InstallerInterface;
use Pimcore\Extension\Bundle\PimcoreBundleAdminClassicInterface;
use Vezolabs\SsoLoginBundle\Installer\SsoLoginInstaller;

final class VezolabsSsoLoginBundle extends AbstractPimcoreBundle
{
    public const string BUNDLE_NAME = 'VezolabsSsoLoginBundle';
    public const string PERMISSION_MANAGE = 'VEZOLABS_SSO_MANAGE';
    public const string PERMISSION_VIEW = 'VEZOLABS_SSO_VIEW';

    public function getNiceName(): string
    {
        return 'VezoLabs SSO Login';
    }

    public function getDescription(): string
    {
        return 'Production-ready Microsoft Entra ID & SAML 2.0 Single Sign-On for Pimcore 12 Studio';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getInstaller(): ?InstallerInterface
    {
        return $this->container?->get(SsoLoginInstaller::class);
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
