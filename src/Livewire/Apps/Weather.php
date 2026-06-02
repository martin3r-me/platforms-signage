<?php

namespace Platform\Signage\Livewire\Apps;

use Illuminate\Support\Facades\Http;
use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignageMedia;

/**
 * Editor für die Wetter-App. Der Ort wird beim Speichern über die kostenlose
 * Open-Meteo-Geocoding-API in Koordinaten aufgelöst und im config-JSON
 * gespeichert. Die Wetterdaten selbst holt der Player live (ebenfalls Open-Meteo,
 * ohne API-Key).
 */
class Weather extends Component
{
    use WithCurrentTeam;

    public ?int $mediaId = null;

    public string $name = '';
    public string $locationQuery = '';
    public string $design = 'modern';      // modern | compact
    public string $colorScheme = 'sky';    // sky | sage | dark | light
    public string $units = 'metric';       // metric (°C, km/h) | imperial (°F, mph)

    // aufgelöst beim Speichern
    public ?string $resolvedName = null;
    public ?float $latitude = null;
    public ?float $longitude = null;
    public ?string $timezone = null;

    public function mount(?SignageMedia $media = null): void
    {
        if ($media && $media->exists) {
            abort_unless($media->team_id === $this->teamId() && $media->isApp(), 403);

            $cfg = $media->config ?? [];
            $this->mediaId       = $media->id;
            $this->name          = (string) $media->name;
            $this->locationQuery = $cfg['location_query'] ?? '';
            $this->resolvedName  = $cfg['location_name']  ?? null;
            $this->latitude      = $cfg['latitude']  ?? null;
            $this->longitude     = $cfg['longitude'] ?? null;
            $this->timezone      = $cfg['timezone']  ?? null;
            $this->design        = $cfg['design']       ?? 'modern';
            $this->colorScheme   = $cfg['color_scheme'] ?? 'sky';
            $this->units         = $cfg['units']        ?? 'metric';
        }
    }

    protected function rules(): array
    {
        return [
            'name'          => 'required|string|max:255',
            'locationQuery' => 'required|string|max:255',
            'design'        => 'required|in:modern,compact',
            'colorScheme'   => 'required|in:sky,sage,dark,light',
            'units'         => 'required|in:metric,imperial',
        ];
    }

    public function save()
    {
        $this->validate();

        // Ort -> Koordinaten (Open-Meteo Geocoding, kein API-Key nötig)
        try {
            $res = Http::timeout(8)->get('https://geocoding-api.open-meteo.com/v1/search', [
                'name'     => $this->locationQuery,
                'count'    => 1,
                'language' => 'de',
                'format'   => 'json',
            ]);
            $hit = $res->json('results.0');
        } catch (\Throwable $e) {
            $hit = null;
        }

        if (!$hit || !isset($hit['latitude'], $hit['longitude'])) {
            $this->addError('locationQuery', 'Ort nicht gefunden. Bitte anders schreiben (z.B. „Köln" oder „Houston, US").');

            return null;
        }

        $config = [
            'location_query' => $this->locationQuery,
            'location_name'  => $hit['name'] ?? $this->locationQuery,
            'latitude'       => (float) $hit['latitude'],
            'longitude'      => (float) $hit['longitude'],
            'timezone'       => $hit['timezone'] ?? 'auto',
            'design'         => $this->design,
            'color_scheme'   => $this->colorScheme,
            'units'          => $this->units,
        ];

        if ($this->mediaId) {
            $media = SignageMedia::where('team_id', $this->teamId())->findOrFail($this->mediaId);
            $media->update(['name' => $this->name, 'config' => $config]);
            $this->bumpScreensUsing($media->id);
        } else {
            SignageMedia::create([
                'team_id'           => $this->teamId(),
                'user_id'           => auth()->id(),
                'name'              => $this->name,
                'kind'              => 'app',
                'app_type'          => 'weather',
                'config'            => $config,
                'processing_status' => 'ready',
            ]);
        }

        session()->flash('signage_message', 'Wetter-App gespeichert ('.$config['location_name'].').');

        return $this->redirectRoute('signage.media.index', navigate: true);
    }

    protected function bumpScreensUsing(int $mediaId): void
    {
        $playlistIds = \Platform\Signage\Models\SignagePlaylistItem::where('media_id', $mediaId)
            ->pluck('playlist_id')->unique();

        if ($playlistIds->isEmpty()) {
            return;
        }

        $screenIds = \Platform\Signage\Models\SignageScreen::whereIn('default_playlist_id', $playlistIds)
            ->orWhereIn('music_playlist_id', $playlistIds)
            ->pluck('id')
            ->merge(
                \Platform\Signage\Models\SignageSchedule::whereIn('playlist_id', $playlistIds)
                    ->pluck('screen_id')
            )
            ->unique();

        if ($screenIds->isNotEmpty()) {
            \Platform\Signage\Models\SignageScreen::whereIn('id', $screenIds)->increment('content_version');
        }
    }

    public function render()
    {
        return view('signage::livewire.apps.weather')->layout('platform::layouts.app');
    }
}
