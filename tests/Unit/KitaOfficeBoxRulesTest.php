<?php

namespace Platform\Signage\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Platform\Signage\Support\KitaOffice\BoxRules;
use Platform\Signage\Support\KitaOffice\KitaOfficeConfig;

/**
 * Warnregeln der CoolCooking-Boxen (kein Laravel nötig).
 */
class KitaOfficeBoxRulesTest extends TestCase
{
    private function at(string $berlin): int
    {
        return (new DateTimeImmutable($berlin, new DateTimeZone('Europe/Berlin')))->getTimestamp();
    }

    private function box(array $over = []): array
    {
        return $over + ['id' => 'a', 'name' => 'Box', 'door' => 'closed', 'alerts' => [], 'age_seconds' => 30, 'error' => null];
    }

    public function test_door_open_longer_than_limit_before_cutoff_is_alert(): void
    {
        $rules = new BoxRules(5, 5, '11:00');
        $doors = [];
        $t0 = $this->at('2026-11-09 09:00:00');

        // alle 30 s beobachtet, 6 Minuten lang offen
        for ($t = $t0; $t <= $t0 + 360; $t += 30) {
            $doors = $rules->trackDoors([$this->box(['door' => 'open'])], $doors, $t);
        }

        $r = $rules->evaluate([$this->box(['door' => 'open'])], $doors, $t0 + 360)[0];
        $this->assertSame('alert', $r['level']);
        $this->assertSame(6, $r['door_open_minutes']);
    }

    public function test_door_open_after_cutoff_is_only_warn(): void
    {
        $rules = new BoxRules(5, 5, '11:00');
        $t0 = $this->at('2026-11-09 11:30:00');
        $doors = ['a' => ['since' => $t0 - 900, 'last_seen' => $t0]];

        $this->assertSame('warn', $rules->evaluate([$this->box(['door' => 'open'])], $doors, $t0)[0]['level']);
    }

    public function test_gap_in_observation_restarts_door_timer(): void
    {
        $rules = new BoxRules();
        $t0 = $this->at('2026-11-09 08:00:00');
        $doors = ['a' => ['since' => $t0 - 600, 'last_seen' => $t0 - 300]];   // 5 Min. nicht gesehen

        $doors = $rules->trackDoors([$this->box(['door' => 'open'])], $doors, $t0);
        $this->assertSame($t0, $doors['a']['since']);
    }

    public function test_stale_data_is_offline_and_never_starts_door_warning(): void
    {
        $rules = new BoxRules(5, 5, '11:00');
        $t0 = $this->at('2026-11-09 08:00:00');
        $stale = $this->box(['door' => 'open', 'age_seconds' => 400]);

        $doors = $rules->trackDoors([$stale], [], $t0);
        $this->assertSame([], $doors);

        $r = $rules->evaluate([$stale], $doors, $t0)[0];
        $this->assertTrue($r['offline']);
        $this->assertSame('info', $r['level']);
        $this->assertSame('Offline', $r['warning']);
    }

    public function test_other_device_alert_is_red(): void
    {
        $rules = BoxRules::fromConfig(KitaOfficeConfig::defaults());
        $r = $rules->evaluate([$this->box(['alerts' => ['E12 Übertemperatur']])], [], $this->at('2026-11-09 14:00:00'))[0];

        $this->assertSame('alert', $r['level']);
        $this->assertSame('E12 Übertemperatur', $r['warning']);
    }

    public function test_config_sanitize_rejects_bad_values(): void
    {
        $cfg = KitaOfficeConfig::sanitize([
            'door_warning_until'  => '25:99',
            'boxes_per_page'      => 99,
            'meinconnect_connection_id' => 'abc',
            'whatsapp_only'       => 'Hicham, Salim,,',
            'drivers'             => [['name' => 'Ali', 'phone' => ''], ['name' => 'Bea', 'phone' => '0171']],
        ]);

        $this->assertSame('11:00', $cfg['door_warning_until']);
        $this->assertSame(8, $cfg['boxes_per_page']);
        $this->assertNull($cfg['meinconnect_connection_id']);
        $this->assertSame(['Hicham', 'Salim'], $cfg['whatsapp_only']);
        $this->assertSame([['name' => 'Bea', 'phone' => '0171']], $cfg['drivers']);
        $this->assertSame('light', $cfg['theme']);
        $this->assertSame('dark', KitaOfficeConfig::sanitize(['theme' => 'dark'])['theme']);
    }
}
