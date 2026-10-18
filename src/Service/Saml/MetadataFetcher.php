<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Service\Saml;

use InvalidArgumentException;
use RuntimeException;

class MetadataFetcher
{
    private const int MAX_BYTES = 5 * 1024 * 1024; // 5 MB max
    private const int TIMEOUT_SECONDS = 10;

    /**
     * Safely fetch SAML metadata from a remote URL with SSRF protection.
     */
    public function fetch(string $url): string
    {
        $this->validateUrl($url);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::TIMEOUT_SECONDS,
                'max_redirects' => 3,
                'follow_location' => 1,
                'header' => "Accept: application/samlmetadata+xml, application/xml, text/xml\r\n" .
                            "User-Agent: VezoLabs-SsoLogin/1.0 (Pimcore)\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $content = @file_get_contents($url, false, $context, 0, self::MAX_BYTES);

        if ($content === false) {
            $error = error_get_last();
            $message = $error['message'] ?? 'Could not establish connection';
            throw new RuntimeException(sprintf('Failed to fetch federation metadata from "%s": %s', $url, $message));
        }

        return $content;
    }

    /**
     * Strict SSRF validation on the target metadata URL.
     */
    public function validateUrl(string $url): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException(sprintf('Invalid metadata URL: "%s"', $url));
        }

        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('Incomplete metadata URL structure.');
        }

        // Must be HTTPS for security
        if (strtolower($parts['scheme']) !== 'https') {
            throw new InvalidArgumentException('SSO metadata URLs must use HTTPS for secure transmission.');
        }

        $host = strtolower($parts['host']);

        // Block localhost and standard loopback hostnames
        if (\in_array($host, ['localhost', 'localhost.localdomain', 'ip6-localhost'], true)) {
            throw new InvalidArgumentException(sprintf('Host "%s" is not permitted (loopback address).', $host));
        }

        // Resolve host to IP addresses
        $ips = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (empty($ips)) {
            // Fallback to gethostbynamel
            $ipv4s = @gethostbynamel($host);
            if (!empty($ipv4s)) {
                $ips = array_map(fn($ip) => ['ip' => $ip, 'type' => 'A'], $ipv4s);
            }
        }

        if (empty($ips)) {
            throw new InvalidArgumentException(sprintf('Could not resolve hostname "%s".', $host));
        }

        foreach ($ips as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if ($ip && $this->isPrivateOrReservedIp($ip)) {
                throw new InvalidArgumentException(sprintf(
                    'SSRF Protection: Host "%s" resolves to restricted/private IP "%s".',
                    $host,
                    $ip
                ));
            }
        }
    }

    /**
     * Check if an IP address belongs to private, loopback, link-local, or reserved ranges.
     */
    public function isPrivateOrReservedIp(string $ip): bool
    {
        // FILTER_FLAG_NO_PRIV_RANGE & FILTER_FLAG_NO_RES_RANGE reject private & reserved IPs
        $isPublic = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if ($isPublic === false) {
            return true;
        }

        // Extra checks for loopback or link-local in IPv4 & IPv6
        if (str_starts_with($ip, '127.') || str_starts_with($ip, '169.254.') || $ip === '0.0.0.0') {
            return true;
        }

        if ($ip === '::1' || str_starts_with(strtolower($ip), 'fe80:') || str_starts_with(strtolower($ip), 'fc00:')) {
            return true;
        }

        return false;
    }
}
