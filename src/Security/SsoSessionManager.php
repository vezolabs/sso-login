<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Security;

use Pimcore\Model\User as PimcoreUser;
use Pimcore\Security\User\User as SecurityUser;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class SsoSessionManager
{
    public const string FIREWALL_CONTEXT = 'pimcore_admin';
    public const string FIREWALL_NAME = 'pimcore_studio_backend';
    public const string SESSION_TOKEN_KEY = '_security_pimcore_admin';

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ?EventDispatcherInterface $eventDispatcher = null
    ) {
    }

    /**
     * Establish a secure, authenticated Pimcore Studio session for the given user.
     */
    public function establishSession(PimcoreUser $pimcoreUser, Request $request): void
    {
        $session = $request->getSession();

        // 1. Regenerate session ID to prevent session fixation
        $session->migrate(true);

        // 2. Build Symfony security user & token
        $securityUser = new SecurityUser($pimcoreUser);
        $roles = $securityUser->getRoles();

        $token = new PostAuthenticationToken(
            $securityUser,
            self::FIREWALL_NAME,
            $roles
        );

        // 3. Set token in Symfony TokenStorage
        $this->tokenStorage->setToken($token);

        // 4. Save serialized token in session for Pimcore Studio firewall context
        $session->set(self::SESSION_TOKEN_KEY, serialize($token));
        $session->save();

        // 5. Update user last login
        $pimcoreUser->setLastLogin(time());
        $pimcoreUser->setLastLoginDate();
        $pimcoreUser->save();

        // 6. Dispatch Symfony interactive login events
        if ($this->eventDispatcher !== null) {
            $interactiveEvent = new InteractiveLoginEvent($request, $token);
            $this->eventDispatcher->dispatch($interactiveEvent);
        }
    }
}
