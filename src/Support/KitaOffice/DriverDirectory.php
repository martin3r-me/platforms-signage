<?php

namespace Platform\Signage\Support\KitaOffice;

/**
 * Fahrername → Telefonnummer, nach den Regeln aus Patricks Prototyp:
 *  - Dienstleister-Präfixe ("Onway / …") werden entfernt.
 *  - Bei mehreren Namen ("A + B", "A / B", "A & B", "A und B") zählt der erste.
 *  - Aliase (z. B. Mimi → Hicham, Serdat → Sedat, T. Giesecke → Thomas Giesecke).
 *  - Abgleich exakt über den normalisierten Namen; bei einem einzelnen Vornamen
 *    ersatzweise über den Vornamen. Mehrdeutige Treffer werden gemeldet, nie geraten.
 *  - Für einzelne Fahrer wird "nur über WhatsApp" angezeigt.
 *
 * Reine Logik, ohne Laravel.
 */
final class DriverDirectory
{
    /** @var array<string,string> normalisierter Alias => Zielname */
    private array $aliases = [];

    /** @var list<string> */
    private array $prefixes;

    /** @var array<string,true> */
    private array $whatsappOnly = [];

    /**
     * @param list<array{name:string, phone:string}> $contacts
     * @param list<array{from:string, to:string}>    $aliases
     * @param list<string>                           $whatsappOnly
     * @param list<string>                           $prefixes
     */
    public function __construct(
        private array $contacts = [],
        array $aliases = [],
        array $whatsappOnly = [],
        array $prefixes = [],
    ) {
        foreach ($aliases as $a) {
            if (($a['from'] ?? '') !== '' && ($a['to'] ?? '') !== '') {
                $this->aliases[self::aliasKey($a['from'])] = trim($a['to']);
            }
        }
        foreach ($whatsappOnly as $name) {
            $this->whatsappOnly[self::key($name)] = true;
        }
        $this->prefixes = array_values(array_filter(array_map('trim', $prefixes)));
    }

    public static function fromConfig(array $cfg, array $contacts = []): self
    {
        return new self(
            $contacts !== [] ? $contacts : ($cfg['drivers'] ?? []),
            $cfg['driver_aliases'] ?? [],
            $cfg['whatsapp_only'] ?? [],
            $cfg['strip_prefixes'] ?? [],
        );
    }

    /** Anzuzeigender Fahrername (erster Name, ohne Präfix, Alias aufgelöst). */
    public function displayName(string $raw): string
    {
        $name = trim(preg_replace('~\s+~u', ' ', $raw) ?? $raw);

        foreach ($this->prefixes as $prefix) {
            $name = preg_replace('~^\s*' . preg_quote($prefix, '~') . '\s*[/:\-]?\s*~iu', '', $name) ?? $name;
        }

        $parts = preg_split('~\s*(?:\+|&|/|,)\s*|\s+und\s+~iu', $name) ?: [$name];
        $name = trim($parts[0] ?? '');

        return $this->aliases[self::aliasKey($name)] ?? $name;
    }

    /**
     * @return array{driver:string, phone:?string, status:string, note:string, whatsapp_only:bool}
     *   status: matched | ambiguous | missing
     */
    public function lookup(string $raw): array
    {
        $driver = $this->displayName($raw);
        $key = self::key($driver);
        $whatsapp = $key !== '' && isset($this->whatsappOnly[$key]);

        if ($key === '') {
            return ['driver' => '', 'phone' => null, 'status' => 'missing', 'note' => '', 'whatsapp_only' => false];
        }

        $matches = array_filter($this->contacts, fn ($c) => self::key($this->displayName((string) ($c['name'] ?? ''))) === $key);

        if ($matches === [] && !str_contains($key, ' ')) {
            $matches = array_filter($this->contacts, fn ($c) => explode(' ', self::key((string) ($c['name'] ?? '')))[0] === $key);
        }

        $numbers = array_values(array_unique(array_filter(array_map(
            fn ($c) => trim((string) ($c['phone'] ?? '')),
            $matches
        ))));

        if (count($numbers) === 1) {
            return ['driver' => $driver, 'phone' => $numbers[0], 'status' => 'matched', 'note' => '', 'whatsapp_only' => $whatsapp];
        }

        $status = $numbers === [] ? 'missing' : 'ambiguous';

        return [
            'driver'        => $driver,
            'phone'         => null,
            'status'        => $status,
            'note'          => $status === 'ambiguous' ? 'Mehrere Telefonnummern – prüfen' : 'Keine Nummer hinterlegt',
            'whatsapp_only' => $whatsapp,
        ];
    }

    /** Vergleichsschlüssel: Kleinschreibung, Leerraum normalisiert. */
    public static function key(string $name): string
    {
        $name = class_exists(\Normalizer::class) ? (\Normalizer::normalize($name, \Normalizer::FORM_KC) ?: $name) : $name;

        return trim(preg_replace('~\s+~u', ' ', mb_strtolower($name)) ?? '');
    }

    /** Alias-Schlüssel: zusätzlich ohne Punkte und Leerzeichen ("T.Giesecke" = "t giesecke"). */
    private static function aliasKey(string $name): string
    {
        return preg_replace('~[\s.]+~u', '', self::key($name)) ?? '';
    }
}
