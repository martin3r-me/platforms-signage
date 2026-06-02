<?php

namespace Platform\Signage\Livewire\Apps;

use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignageMedia;

/**
 * Editor für die Uhr-App (create + edit). Speichert als signage_media mit
 * kind=app, app_type=clock und den Einstellungen im config-JSON.
 */
class Clock extends Component
{
    use WithCurrentTeam;

    public ?int $mediaId = null;

    public string $name = '';
    public string $clockType = 'modern_digital'; // modern_digital | minimal | flip
    public string $theme = 'dark';               // light | dark
    public string $timeFormat = '24h';           // 24h | 12h
    public bool $showSeconds = true;
    public bool $showDate = true;
    public string $dateFormat = 'de_long';       // de_long | de_short | en_long | iso

    public function mount(?SignageMedia $media = null): void
    {
        if ($media && $media->exists) {
            abort_unless($media->team_id === $this->teamId() && $media->isApp(), 403);

            $cfg = $media->config ?? [];
            $this->mediaId     = $media->id;
            $this->name        = (string) $media->name;
            $this->clockType   = $cfg['clock_type']   ?? 'modern_digital';
            $this->theme       = $cfg['theme']         ?? 'dark';
            $this->timeFormat  = $cfg['time_format']   ?? '24h';
            $this->showSeconds = (bool) ($cfg['show_seconds'] ?? true);
            $this->showDate    = (bool) ($cfg['show_date']    ?? true);
            $this->dateFormat  = $cfg['date_format']   ?? 'de_long';
        }
    }

    protected function rules(): array
    {
        return [
            'name'       => 'required|string|max:255',
            'clockType'  => 'required|in:modern_digital,minimal,flip',
            'theme'      => 'required|in:light,dark',
            'timeFormat' => 'required|in:24h,12h',
            'dateFormat' => 'required|in:de_long,de_short,en_long,iso',
        ];
    }

    public function save()
    {
        $this->validate();

        $config = [
            'clock_type'   => $this->clockType,
            'theme'        => $this->theme,
            'time_format'  => $this->timeFormat,
            'show_seconds' => $this->showSeconds,
            'show_date'    => $this->showDate,
            'date_format'  => $this->dateFormat,
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
                'app_type'          => 'clock',
                'config'            => $config,
                'processing_status' => 'ready',
            ]);
        }

        session()->flash('signage_message', 'Uhr-App gespeichert.');

        return $this->redirectRoute('signage.media.index', navigate: true);
    }

    /**
     * Bei Änderungen an einer bereits genutzten App die Bildschirme neu laden lassen.
     */
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
        return view('signage::livewire.apps.clock')->layout('platform::layouts.app');
    }
}
