<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vezolabs\SsoLoginBundle\Service\Saml\MetadataFetcher;

class MetadataFetcherTest extends TestCase
{
    private MetadataFetcher $fetcher;

    protected function setUp(): void
    {
        $this->fetcher = new MetadataFetcher();
    }

    public function testRejectsInsecureHttpUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SSO metadata URLs must use HTTPS');
        $this->fetcher->validateUrl('http://login.microsoftonline.com/metadata.xml');
    }

    public function testRejectsLocalhost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('loopback address');
        $this->fetcher->validateUrl('https://localhost/metadata.xml');
    }

    public function testRejectsMalformedUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid metadata URL');
        $this->fetcher->validateUrl('not-a-valid-url');
    }

    #[DataProvider('privateAndReservedIpProvider')]
    public function testIdentifiesPrivateAndReservedIps(string $ip, bool $expectedRestricted): void
    {
        $result = $this->fetcher->isPrivateOrReservedIp($ip);
        $this->assertSame(
            $expectedRestricted,
            $result,
            sprintf('Failed asserting that IP %s restriction status is %s', $ip, $expectedRestricted ? 'true' : 'false')
        );
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function privateAndReservedIpProvider(): array
    {
        return [
            'IPv4 Loopback 127.0.0.1' => ['127.0.0.1', true],
            'IPv4 Loopback 127.0.1.5' => ['127.0.1.5', true],
            'IPv4 Class A Private 10.0.0.1' => ['10.0.0.1', true],
            'IPv4 Class B Private 172.16.0.1' => ['172.16.0.1', true],
            'IPv4 Class C Private 192.168.1.1' => ['192.168.1.1', true],
            'IPv4 AWS/Cloud Metadata 169.254.169.254' => ['169.254.169.254', true],
            'IPv4 Any 0.0.0.0' => ['0.0.0.0', true],
            'IPv6 Loopback ::1' => ['::1', true],
            'IPv6 Link-Local fe80::1' => ['fe80::1', true],
            'IPv6 Unique Local fc00::1' => ['fc00::1', true],
            'Public IPv4 Cloudflare 1.1.1.1' => ['1.1.1.1', false],
            'Public IPv4 Google 8.8.8.8' => ['8.8.8.8', false],
            'Public IPv6 Google 2001:4860:4860::8888' => ['2001:4860:4860::8888', false],
        ];
    }
}
