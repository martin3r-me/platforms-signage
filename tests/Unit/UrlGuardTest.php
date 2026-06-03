<?php

namespace Platform\Signage\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Platform\Signage\Support\UrlGuard;

/**
 * Reine Logik-Tests für den SSRF-Schutz des Website-Proxys (kein Laravel nötig).
 */
class UrlGuardTest extends TestCase
{
    public function test_blocks_non_http_schemes(): void
    {
        $this->assertFalse(UrlGuard::isSafePublicHttpUrl('ftp://example.com'));
        $this->assertFalse(UrlGuard::isSafePublicHttpUrl('file:///etc/passwd'));
        $this->assertFalse(UrlGuard::isSafePublicHttpUrl('gopher://example.com'));
    }

    public function test_blocks_private_loopback_and_linklocal_ips(): void
    {
        $this->assertFalse(UrlGuard::isSafePublicHttpUrl('http://127.0.0.1/'));
        $this->assertFalse(UrlGuard::isSafePublicHttpUrl('http://localhost/'));        // localhost -> 127.0.0.1
        $this->assertFalse(UrlGuard::isSafePublicHttpUrl('http://10.0.0.5/'));
        $this->assertFalse(UrlGuard::isSafePublicHttpUrl('http://192.168.1.10/'));
        $this->assertFalse(UrlGuard::isSafePublicHttpUrl('http://169.254.169.254/'));  // Cloud-Metadaten
        $this->assertFalse(UrlGuard::isSafePublicHttpUrl('http://[::1]/'));
    }

    public function test_allows_public_ip(): void
    {
        $this->assertTrue(UrlGuard::isSafePublicHttpUrl('https://1.1.1.1/'));
        $this->assertTrue(UrlGuard::isSafePublicHttpUrl('https://8.8.8.8/'));
    }

    public function test_isPublicIp_classification(): void
    {
        $this->assertTrue(UrlGuard::isPublicIp('8.8.8.8'));
        $this->assertFalse(UrlGuard::isPublicIp('127.0.0.1'));
        $this->assertFalse(UrlGuard::isPublicIp('172.16.0.1'));
        $this->assertFalse(UrlGuard::isPublicIp('not-an-ip'));
    }

    public function test_resolve_location_variants(): void
    {
        $base = 'https://example.com/sub/page.html';

        $this->assertSame(
            'https://other.com/x',
            UrlGuard::resolveLocation($base, 'https://other.com/x')
        );
        $this->assertSame(
            'https://example.com/root',
            UrlGuard::resolveLocation($base, '/root')
        );
        $this->assertSame(
            'https://cdn.example.com/a.js',
            UrlGuard::resolveLocation($base, '//cdn.example.com/a.js')
        );
        $this->assertSame(
            'https://example.com/sub/next.html',
            UrlGuard::resolveLocation($base, 'next.html')
        );
    }
}
