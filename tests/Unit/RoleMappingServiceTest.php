<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Vezolabs\SsoLoginBundle\Entity\IdentityProvider;
use Vezolabs\SsoLoginBundle\Service\RoleMapping\RoleMappingService;

class RoleMappingServiceTest extends TestCase
{
    private RoleMappingService $service;

    protected function setUp(): void
    {
        $this->service = new RoleMappingService();
    }

    public function testExtractExternalRolesFromMicrosoftClaimUri(): void
    {
        $attributes = [
            'http://schemas.microsoft.com/ws/2008/06/identity/claims/role' => [
                'Pimcore.ContentEditor',
                'Pimcore.Admin',
            ],
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => [
                'user@example.com',
            ],
        ];

        $roles = $this->service->extractExternalRoles($attributes);

        $this->assertCount(2, $roles);
        $this->assertContains('Pimcore.ContentEditor', $roles);
        $this->assertContains('Pimcore.Admin', $roles);
    }

    public function testExtractExternalRolesFromGenericClaimsAndDeduplicates(): void
    {
        $attributes = [
            'roles' => ['editor', 'viewer'],
            'groups' => ['viewer', 'marketing'],
        ];

        $roles = $this->service->extractExternalRoles($attributes);

        $this->assertCount(3, $roles);
        $this->assertSame(['editor', 'viewer', 'marketing'], $roles);
    }

    public function testResolveRolesThrowsAccessDeniedWhenUnmappedAndPolicyIsDeny(): void
    {
        $provider = new IdentityProvider();
        $provider->setName('entra-id');
        $provider->setUnmappedRolePolicy(IdentityProvider::UNMAPPED_ROLE_DENY);
        $provider->setRoleMapping([
            'EntraAdmin' => 'pimcore_admin_role',
        ]);

        $incomingAttributes = [
            'roles' => ['UnknownExternalRole'],
        ];

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('no Microsoft Entra / SAML role is mapped to an authorized Pimcore role');

        $this->service->resolveRoles($provider, $incomingAttributes);
    }

    public function testResolveRolesThrowsAccessDeniedWhenUnmappedAndPolicyIsManual(): void
    {
        $provider = new IdentityProvider();
        $provider->setName('entra-id');
        $provider->setUnmappedRolePolicy(IdentityProvider::UNMAPPED_ROLE_MANUAL);
        $provider->setRoleMapping([]);

        $incomingAttributes = [
            'roles' => ['AnyRole'],
        ];

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('pending administrator approval');

        $this->service->resolveRoles($provider, $incomingAttributes);
    }
}
