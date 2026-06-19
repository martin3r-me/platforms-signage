<?php

namespace Platform\Signage\Livewire\Screens;

use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignageScreen;
use Platform\Signage\Services\ScreenPairingService;

class Index extends Component
{
    use WithCurrentTeam;

    public bool $showCreateModal = false;
    public string $pairingCode = '';
    public string $pairingName = '';

    // Fire-TV-Download-Code (für die Downloader-App auf dem TV)
    public ?string $apkCode = null;
    public ?string $apkCodeUrl = null;

    /** Erzeugt einen zeitlich begrenzten 4-stelligen Code + passende Download-URL. */
    public function generateApkCode(): void
    {
        $this->apkCode = \Platform\Signage\Http\Controllers\FireTvApkController::issueCode();
        $this->apkCodeUrl = url('/signage/firetv/'.$this->apkCode.'.apk');
    }

    public function openCreateModal(): void
    {
        $this->reset('pairingCode', 'pairingName');
        $this->resetValidation();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
    }

    public function pair(ScreenPairingService $pairing): void
    {
        $this->validate([
            'pairingCode' => 'required|string|size:6',
            'pairingName' => 'nullable|string|max:255',
        ]);

        try {
            $pairing->claim($this->pairingCode, $this->teamId(), $this->pairingName ?: null, auth()->id());
            $this->reset('pairingCode', 'pairingName');
            $this->showCreateModal = false;
            session()->flash('signage_message', 'Bildschirm gekoppelt.');
        } catch (\RuntimeException $e) {
            $this->addError('pairingCode', $e->getMessage());
        }
    }

    public function reload(int $id, ScreenPairingService $pairing): void
    {
        $screen = SignageScreen::where('team_id', $this->teamId())->findOrFail($id);
        $pairing->bumpVersion($screen);
        session()->flash('signage_message', 'Neuladen ausgelöst.');
    }

    public function renameScreen(int $id, string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        SignageScreen::where('team_id', $this->teamId())->findOrFail($id)
            ->update(['name' => mb_substr($name, 0, 255)]);
        session()->flash('signage_message', 'Bildschirm umbenannt.');
    }

    public function deleteScreen(int $id): void
    {
        SignageScreen::where('team_id', $this->teamId())->findOrFail($id)->delete();
        session()->flash('signage_message', 'Bildschirm entfernt.');
    }

    public function render()
    {
        $screens = SignageScreen::where('team_id', $this->teamId())
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('signage::livewire.screens.index', [
            'screens'    => $screens,
            'firetvApk'  => \Platform\Signage\Http\Controllers\FireTvApkController::available(),
            'apkCodeTtl' => \Platform\Signage\Http\Controllers\FireTvApkController::CODE_TTL_MINUTES,
        ])->layout('platform::layouts.app');
    }
}
