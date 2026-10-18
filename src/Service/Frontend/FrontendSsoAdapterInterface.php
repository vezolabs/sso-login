<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Service\Frontend;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vezolabs\SsoLoginBundle\Entity\IdentityProvider;

/**
 * Optional extensibility interface for customer-facing frontend SSO login.
 * Keeps frontend customer user authorization strictly separated from Pimcore Studio administrators.
 */
interface FrontendSsoAdapterInterface
{
    /**
     * Determines whether this adapter handles the given request/provider.
     */
    public function supports(IdentityProvider $provider, Request $request): bool;

    /**
     * Authenticate or provision a frontend customer user (e.g. DataObject\UserAccount).
     *
     * @param array<string, array<string>> $attributes
     * @return Response Redirect or response following customer login
     */
    public function handleFrontendLogin(
        IdentityProvider $provider,
        string $nameId,
        array $attributes,
        Request $request
    ): Response;
}
