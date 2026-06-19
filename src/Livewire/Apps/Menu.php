<?php

namespace Platform\Signage\Livewire\Apps;

use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Models\SignageScreen;

/**
 * Editor für die Menü-/Speisekarten-App (create + edit). Speichert als signage_media
 * mit kind=app, app_type=menu. Die komplette Karte liegt im config-JSON (self-contained).
 */
class Menu extends Component
{
    use WithCurrentTeam;

    public ?int $mediaId = null;
    public string $name = '';

    /** Komplette Menü-Konfiguration (clientseitig via Alpine bearbeitet, @entangle). */
    public const STYLES = ['elegant', 'warm', 'modern', 'night'];

    public array $config = [
        'style'      => 'elegant', // elegant | warm | modern | night
        'title'      => 'Speisekarte',
        'columns'    => 1,        // 1 | 2
        'categories' => [],       // [ { name, items: [ { name, description, price } ] } ]
        'special'    => ['name' => '', 'description' => '', 'price' => ''],
    ];

    public function mount(?SignageMedia $media = null): void
    {
        if ($media && $media->exists) {
            abort_unless($media->team_id === $this->teamId() && $media->isApp(), 403);

            $cfg = $media->config ?? [];
            $this->mediaId = $media->id;
            $this->name = (string) $media->name;
            $this->config = [
                'style'      => $this->resolveStyle($cfg),
                'title'      => (string) ($cfg['title'] ?? 'Speisekarte'),
                'columns'    => (int) ($cfg['columns'] ?? 1) === 2 ? 2 : 1,
                'categories' => is_array($cfg['categories'] ?? null) ? $cfg['categories'] : [],
                'special'    => [
                    'name'        => (string) ($cfg['special']['name'] ?? ''),
                    'description' => (string) ($cfg['special']['description'] ?? ''),
                    'price'       => (string) ($cfg['special']['price'] ?? ''),
                ],
            ];
        }
    }

    protected function rules(): array
    {
        return ['name' => 'required|string|max:255'];
    }

    public function save()
    {
        $this->validate();

        $config = [
            'style'      => in_array($this->config['style'] ?? '', self::STYLES, true) ? $this->config['style'] : 'elegant',
            'title'      => mb_substr((string) ($this->config['title'] ?? ''), 0, 120),
            'columns'    => (int) ($this->config['columns'] ?? 1) === 2 ? 2 : 1,
            'categories' => $this->sanitizeCategories($this->config['categories'] ?? []),
            'special'    => [
                'name'        => mb_substr((string) ($this->config['special']['name'] ?? ''), 0, 200),
                'description' => mb_substr((string) ($this->config['special']['description'] ?? ''), 0, 300),
                'price'       => mb_substr((string) ($this->config['special']['price'] ?? ''), 0, 40),
            ],
        ];

        if ($this->mediaId) {
            $media = SignageMedia::where('team_id', $this->teamId())->findOrFail($this->mediaId);
            $media->update(['name' => $this->name, 'config' => $config]);
            SignageScreen::bumpForMedia($media->id);
        } else {
            SignageMedia::create([
                'team_id'           => $this->teamId(),
                'user_id'           => auth()->id(),
                'name'              => $this->name,
                'kind'              => 'app',
                'app_type'          => 'menu',
                'config'            => $config,
                'processing_status' => 'ready',
            ]);
        }

        session()->flash('signage_message', 'Menü-App gespeichert.');

        return $this->redirectRoute('signage.media.index', navigate: true);
    }

    /** Stil aus der Config bestimmen; altes theme (dark/light) wird gemappt. */
    private function resolveStyle(array $cfg): string
    {
        if (in_array($cfg['style'] ?? '', self::STYLES, true)) {
            return $cfg['style'];
        }

        return ($cfg['theme'] ?? 'dark') === 'light' ? 'warm' : 'elegant';
    }

    private function sanitizeCategories($categories): array
    {
        if (!is_array($categories)) {
            return [];
        }

        return array_values(array_map(function ($cat) {
            $items = is_array($cat['items'] ?? null) ? $cat['items'] : [];

            return [
                'name'  => mb_substr((string) ($cat['name'] ?? ''), 0, 120),
                'items' => array_values(array_map(fn ($it) => [
                    'name'        => mb_substr((string) ($it['name'] ?? ''), 0, 200),
                    'description' => mb_substr((string) ($it['description'] ?? ''), 0, 300),
                    'price'       => mb_substr((string) ($it['price'] ?? ''), 0, 40),
                ], $items)),
            ];
        }, $categories));
    }

    public function render()
    {
        return view('signage::livewire.apps.menu')->layout('platform::layouts.app');
    }
}
