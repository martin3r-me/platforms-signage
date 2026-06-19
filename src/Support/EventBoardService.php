<?php

namespace Platform\Signage\Support;

/**
 * Liest Veranstaltungen/Raumbuchungen aus dem Events-Modul für das Belegungs-Board.
 * Strikt gekapselt + per class_exists gegatet: ohne installiertes Events-Modul
 * liefert der Service einfach nichts (Board zeigt einen Hinweis).
 */
class EventBoardService
{
    public static function available(): bool
    {
        return class_exists(\Platform\Events\Models\Booking::class);
    }

    /**
     * Kommende Raumbuchungen eines Teams für die nächsten $days Tage (heute inklusive).
     *
     * @param  array<string>  $statuses  optionaler Filter auf Event-Status (leer = alle)
     * @return array<int, array{date:string,start:?string,end:?string,room:?string,pers:?string,title:string,status:?string}>
     */
    public static function upcoming(int $teamId, int $days = 1, array $statuses = []): array
    {
        if (!self::available() || $teamId <= 0) {
            return [];
        }

        $days = max(1, min(14, $days));
        $today = now()->toDateString();
        $until = now()->addDays($days - 1)->toDateString();

        $bookings = \Platform\Events\Models\Booking::query()
            ->where('team_id', $teamId)
            ->whereBetween('datum', [$today, $until])
            ->with(['event:id,name,status', 'location:id,name,kuerzel'])
            ->orderBy('datum')
            ->orderBy('start_time')
            ->limit(200)
            ->get();

        return $bookings
            ->filter(fn ($b) => empty($statuses) || in_array(optional($b->event)->status, $statuses, true))
            ->map(fn ($b) => [
                'date'   => (string) $b->datum,
                'start'  => $b->start_time ? substr((string) $b->start_time, 0, 5) : null,
                'end'    => $b->end_time ? substr((string) $b->end_time, 0, 5) : null,
                'room'   => $b->display_room,
                'pers'   => $b->pers !== null ? (string) $b->pers : null,
                'title'  => (string) (optional($b->event)->name ?? ''),
                'status' => optional($b->event)->status,
            ])
            ->values()
            ->all();
    }

    /** Verfügbare Event-Status (für den Editor-Filter), falls ableitbar. */
    public static function knownStatuses(): array
    {
        // Bewusst statisch gehalten (Events-Modul hat keine zentrale Status-Liste).
        return ['Option', 'Definitiv', 'Vertrag'];
    }
}
