<?php

namespace Platform\Signage\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Platform\Signage\Support\KitaOffice\DriverDirectory;
use Platform\Signage\Support\KitaOffice\KitaOfficeConfig;

/**
 * Fahrer-Abgleich nach den Regeln aus Patricks Prototyp (kein Laravel nötig).
 */
class KitaOfficeDriverDirectoryTest extends TestCase
{
    private function directory(array $contacts): DriverDirectory
    {
        return DriverDirectory::fromConfig(KitaOfficeConfig::defaults(), $contacts);
    }

    public function test_first_of_multiple_drivers_and_provider_prefix(): void
    {
        $dir = $this->directory([]);

        $this->assertSame('Yosko', $dir->displayName('Onway / Yosko + Ali'));
        $this->assertSame('Yosko', $dir->displayName('Yosko und Ali'));
        $this->assertSame('Yosko', $dir->displayName('Yosko & Ali'));
    }

    public function test_aliases_ignore_case_dots_and_spaces(): void
    {
        $dir = $this->directory([]);

        $this->assertSame('Hicham', $dir->displayName('mimi'));
        $this->assertSame('Sedat', $dir->displayName('Serdat'));
        $this->assertSame('Thomas Giesecke', $dir->displayName('T.Giesecke'));
        $this->assertSame('Thomas Giesecke', $dir->displayName('t. giesecke'));
        $this->assertSame('Thomas Giesecke', $dir->displayName('Thomas G.'));
    }

    public function test_matches_full_name_then_first_name(): void
    {
        $dir = $this->directory([
            ['name' => 'Yosko Petrov', 'phone' => '0171 111'],
            ['name' => 'Thomas Giesecke', 'phone' => '0171 222'],
        ]);

        $this->assertSame('0171 222', $dir->lookup('T. Giesecke')['phone']);

        $byFirst = $dir->lookup('Yosko');
        $this->assertSame('matched', $byFirst['status']);
        $this->assertSame('0171 111', $byFirst['phone']);
    }

    public function test_ambiguous_numbers_are_reported_not_guessed(): void
    {
        $dir = $this->directory([
            ['name' => 'Ali Demir', 'phone' => '0171 1'],
            ['name' => 'Ali Kaya', 'phone' => '0171 2'],
        ]);

        $r = $dir->lookup('Ali');
        $this->assertSame('ambiguous', $r['status']);
        $this->assertNull($r['phone']);
        $this->assertSame('Mehrere Telefonnummern – prüfen', $r['note']);
    }

    public function test_missing_and_whatsapp_only(): void
    {
        $dir = $this->directory([]);

        $r = $dir->lookup('Mimi');
        $this->assertSame('Hicham', $r['driver']);
        $this->assertSame('missing', $r['status']);
        $this->assertTrue($r['whatsapp_only']);

        $this->assertFalse($dir->lookup('Yosko')['whatsapp_only']);
        $this->assertSame('', $dir->lookup('')['driver']);
    }
}
