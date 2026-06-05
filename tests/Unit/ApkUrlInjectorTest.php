<?php

namespace Platform\Signage\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Platform\Signage\Support\ApkUrlInjector;

/**
 * Pure Logik-Tests für die APK-URL-Injektion (kein Laravel nötig).
 */
class ApkUrlInjectorTest extends TestCase
{
    /** Baut ein minimales "ZIP" mit gültigem EOCD (ohne Kommentar). */
    private function fakeApk(string $payload = 'PK-content'): string
    {
        // EOCD: Signatur(4) + 16 Byte Felder + 2 Byte Kommentarlänge(0) = 22 Byte.
        $eocd = "PK\x05\x06".str_repeat("\x00", 16)."\x00\x00";

        return $payload.$eocd;
    }

    public function test_inject_and_extract_roundtrip(): void
    {
        $url = 'https://office.bhgdigital.de/signage/play';
        $out = ApkUrlInjector::inject($this->fakeApk(), $url);

        $this->assertSame($url, ApkUrlInjector::extract($out));
    }

    public function test_trailer_is_at_end_with_magic(): void
    {
        $out = ApkUrlInjector::inject($this->fakeApk(), 'https://x.de/signage/play');

        $this->assertSame('SIGNGURL', substr($out, -8));
    }

    public function test_eocd_comment_length_is_updated(): void
    {
        $url = 'https://x.de/signage/play';
        $out = ApkUrlInjector::inject($this->fakeApk(), $url);

        $eocd = strrpos($out, "PK\x05\x06");
        $commentLen = unpack('v', substr($out, $eocd + 20, 2))[1];
        $trailerLen = strlen($url) + 4 + 8; // url + länge(4) + magic(8)

        $this->assertSame($trailerLen, $commentLen);
        // Der Kommentar ist exakt der Rest der Datei nach der festen EOCD-Struktur.
        $this->assertSame($trailerLen, strlen($out) - ($eocd + 22));
    }

    public function test_reinjection_replaces_previous_url(): void
    {
        $out1 = ApkUrlInjector::inject($this->fakeApk(), 'https://a.de/signage/play');
        $out2 = ApkUrlInjector::inject($out1, 'https://b.de/signage/play');

        $this->assertSame('https://b.de/signage/play', ApkUrlInjector::extract($out2));
    }

    public function test_extract_returns_null_without_trailer(): void
    {
        $this->assertNull(ApkUrlInjector::extract($this->fakeApk()));
    }

    public function test_inject_throws_without_eocd(): void
    {
        $this->expectException(\RuntimeException::class);
        ApkUrlInjector::inject('no zip here', 'https://x.de/');
    }
}
