<?php

namespace Platform\Signage\Tests\Unit;

use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Platform\Core\Models\User;
use Platform\Signage\Support\KitaOffice\KitaOfficeConfig;
use Platform\Signage\Support\KitaOffice\M365Source;

/**
 * Auswahl von Kalender-Gruppe, Speiseplan-Ordner und Telefonliste im Editor:
 * Bereinigung der gespeicherten Kennungen und die Graph-Abfragen des Browsers
 * (gegen eine nachgebaute Graph-API, ohne Netz).
 */
class KitaOfficeM365PickerTest extends TestCase
{
    private const API = 'Platform\\UserConnectors\\Services\\Microsoft365\\Microsoft365ApiService';

    private FakeGraph $graph;

    protected function setUp(): void
    {
        $this->graph = new FakeGraph();
        $container = new Container();
        $container->instance(self::API, $this->graph);
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    private function user(): User
    {
        return (new \ReflectionClass(User::class))->newInstanceWithoutConstructor();
    }

    public function test_drive_item_is_sanitized(): void
    {
        $this->assertSame(
            ['drive_id' => 'b!AbC_-1', 'item_id' => '01XYZ', 'label' => 'AG - KITA OFFICE › Dokumente'],
            KitaOfficeConfig::driveItem(['drive_id' => ' b!AbC_-1 ', 'item_id' => '01XYZ', 'label' => 'AG - KITA OFFICE › Dokumente', 'evil' => 1])
        );
        $this->assertNull(KitaOfficeConfig::driveItem(['drive_id' => 'b!x', 'item_id' => '../../me']));
        $this->assertNull(KitaOfficeConfig::driveItem(['drive_id' => 'a/b', 'item_id' => '1']));
        $this->assertNull(KitaOfficeConfig::driveItem('b!x'));
        $this->assertNull(KitaOfficeConfig::sanitize([])['menu_folder_item']);
        $this->assertSame('root', KitaOfficeConfig::sanitize(['menu_folder_item' => ['drive_id' => 'b!x', 'item_id' => 'root']])['menu_folder_item']['item_id']);
    }

    public function test_selected_item_wins_over_link(): void
    {
        $item = ['drive_id' => 'b!x', 'item_id' => '1', 'label' => ''];
        $url = 'https://broichcatering.sharepoint.com/:f:/s/kita/abc';

        $this->assertSame($item, KitaOfficeConfig::source(['menu_folder_item' => $item, 'menu_folder_url' => $url], 'menu_folder_item', 'menu_folder_url'));
        $this->assertSame($url, KitaOfficeConfig::source(['menu_folder_item' => null, 'menu_folder_url' => $url], 'menu_folder_item', 'menu_folder_url'));
        $this->assertNull(KitaOfficeConfig::source(['menu_folder_item' => null, 'menu_folder_url' => ''], 'menu_folder_item', 'menu_folder_url'));
    }

    public function test_groups_lists_only_unified_groups_sorted_across_pages(): void
    {
        $this->graph->responses['/me/memberOf'] = ['value' => [
            ['@odata.type' => '#microsoft.graph.group', 'id' => 'g2', 'displayName' => 'Zentrale', 'groupTypes' => ['Unified']],
            ['@odata.type' => '#microsoft.graph.group', 'id' => 'sec', 'displayName' => 'Sicherheitsgruppe', 'groupTypes' => []],
            ['@odata.type' => '#microsoft.graph.directoryRole', 'id' => 'role', 'displayName' => 'Admin'],
        ], '@odata.nextLink' => 'https://graph/next'];
        $this->graph->responses['https://graph/next'] = ['value' => [
            ['@odata.type' => '#microsoft.graph.group', 'id' => 'g1', 'displayName' => 'AG - KITA OFFICE', 'groupTypes' => ['Unified']],
        ]];

        $this->assertSame(
            [['id' => 'g1', 'name' => 'AG - KITA OFFICE'], ['id' => 'g2', 'name' => 'Zentrale']],
            M365Source::groups($this->user(), 5)
        );
        $this->assertSame(5, $this->graph->connectionId);
    }

    public function test_browse_walks_groups_libraries_and_folders(): void
    {
        $this->graph->responses['/me/memberOf'] = ['value' => [
            ['@odata.type' => '#microsoft.graph.group', 'id' => 'g1', 'displayName' => 'AG - KITA OFFICE', 'groupTypes' => ['Unified']],
        ]];
        $this->graph->responses['/me/followedSites'] = ['value' => [['id' => 'site,1,2', 'displayName' => 'Logistik']]];
        $this->assertSame(
            [['type' => 'group', 'id' => 'g1', 'name' => 'AG - KITA OFFICE'], ['type' => 'site', 'id' => 'site,1,2', 'name' => 'Logistik']],
            M365Source::browse($this->user(), 5, [])
        );

        $this->graph->responses['/groups/g1/drives'] = ['value' => [['id' => 'b!d1', 'name' => 'Dokumente']]];
        $this->assertSame(
            [['type' => 'drive', 'drive_id' => 'b!d1', 'item_id' => 'root', 'name' => 'Dokumente']],
            M365Source::browse($this->user(), 5, ['type' => 'group', 'id' => 'g1'])
        );

        $this->graph->responses['/drives/b!d1/root/children'] = ['value' => [
            ['id' => 'f2', 'name' => 'Speisepläne', 'folder' => ['childCount' => 3]],
            ['id' => 'x1', 'name' => 'Telefonliste Logistik.xlsx', 'file' => []],
            ['id' => 'w1', 'name' => 'Notiz.docx', 'file' => []],
            ['id' => 'f1', 'name' => 'Archiv', 'folder' => []],
        ]];
        $this->assertSame(['Archiv', 'Speisepläne'], array_column(M365Source::browse($this->user(), 5, ['type' => 'folder', 'drive_id' => 'b!d1', 'item_id' => 'root']), 'name'));
        $this->assertSame(
            ['folder', 'folder', 'file'],
            array_column(M365Source::browse($this->user(), 5, ['type' => 'folder', 'drive_id' => 'b!d1', 'item_id' => 'root'], '.xlsx'), 'type')
        );

        $this->graph->responses['/drives/b!d1/items/f2/children'] = ['value' => []];
        $this->assertSame([], M365Source::browse($this->user(), 5, ['type' => 'folder', 'drive_id' => 'b!d1', 'item_id' => 'f2']));
    }

    public function test_probe_menu_resolves_month_file_below_selected_folder(): void
    {
        $this->graph->responses['/drives/b!d1/items/f2'] = ['id' => 'f2', 'folder' => []];
        $this->graph->fallback = fn (string $path) => str_starts_with($path, '/drives/b!d1/items/f2:/') ? ['id' => 'm', 'file' => []] : null;

        $result = M365Source::probeMenu($this->user(), 5, ['drive_id' => 'b!d1', 'item_id' => 'f2', 'label' => ''], '{JJJJ}/Speiseplan {Monat}.docx');

        $this->assertStringStartsWith('Gefunden: ' . date('Y') . '/Speiseplan ', $result);
        $this->assertStringEndsWith('✓', $result);
    }
}

/** Nachbau der Graph-API aus user-connectors (get/getNextPage/forConnection/segment). */
class FakeGraph
{
    public array $responses = [];
    public ?\Closure $fallback = null;
    public ?int $connectionId = null;

    public function forConnection(?int $connectionId): static
    {
        $this->connectionId = $connectionId;

        return $this;
    }

    public static function segment(string $value): string
    {
        return rawurlencode($value);
    }

    public function get($user, string $path, array $query = [], array $headers = []): array
    {
        $path = rawurldecode($path);
        if (array_key_exists($path, $this->responses)) {
            return $this->responses[$path];
        }
        if ($this->fallback && ($hit = ($this->fallback)($path)) !== null) {
            return $hit;
        }

        throw new \RuntimeException('Unerwarteter Graph-Aufruf: ' . $path);
    }

    public function getNextPage($user, string $nextLink, array $headers = []): array
    {
        return $this->get($user, $nextLink);
    }
}
