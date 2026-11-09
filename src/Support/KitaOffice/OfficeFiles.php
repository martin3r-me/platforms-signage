<?php

namespace Platform\Signage\Support\KitaOffice;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Liest die beiden Office-Dateien des Kita-Office-Boards (reine Logik, ohne Laravel):
 *  - Speiseplan (DOCX): drei Tagesmenüs aus der datierten Wochentabelle,
 *  - Telefonliste Logistik (XLSX): Fahrer + Telefonnummer.
 * Regeln 1:1 aus Patricks read-menus.ps1 / read-contacts.ps1.
 */
final class OfficeFiles
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const MONTHS = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

    public const DEFAULT_MENU_PATTERN = '{JJJJ}/{MM} - {Monat}/Speiseplan {Monat} {JJJJ}.docx';

    /** Relativer Dateipfad des Monatsplans, z. B. "2026/11 - November/Speiseplan November 2026.docx". */
    public static function menuPath(string $pattern, DateTimeImmutable $day): string
    {
        $month = self::MONTHS[(int) $day->format('n') - 1];

        return strtr($pattern ?: self::DEFAULT_MENU_PATTERN, [
            '{JJJJ}'  => $day->format('Y'),
            '{MM}'    => $day->format('m'),
            '{Monat}' => $month,
        ]);
    }

    /**
     * @return list<array{number:int, text:string}>|null  [] am Wochenende, null wenn der Tag im Plan fehlt
     */
    public static function menusFromDocx(string $bytes, DateTimeImmutable $day): ?array
    {
        $xml = self::zipEntries($bytes, ['word/document.xml'])['word/document.xml'] ?? null;
        if ($xml === null) {
            throw new RuntimeException('Speiseplan ist kein Word-Dokument.');
        }

        return self::menusFromDocumentXml($xml, $day);
    }

    /** @return list<array{number:int, text:string}>|null */
    public static function menusFromDocumentXml(string $xml, DateTimeImmutable $day): ?array
    {
        if ((int) $day->format('N') >= 6) {
            return [];
        }

        $doc = self::dom($xml);
        $xp = new DOMXPath($doc);
        $xp->registerNamespace('w', self::W);
        $body = $xp->query('//w:body')->item(0);
        if (!$body) {
            return null;
        }

        $date = $day->setTime(0, 0);
        $week = null;
        $menus = [];

        foreach ($body->childNodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            if ($node->localName === 'p'
                && preg_match('~Speiseplan\s+(\d{2})\.(\d{2})\.(\d{4})\s*-\s*\d{2}\.\d{2}\.\d{4}~u', self::text($xp, $node), $m)) {
                $week = (new DateTimeImmutable("{$m[3]}-{$m[2]}-{$m[1]}", $date->getTimezone()))->setTime(0, 0);
            }
            if ($node->localName !== 'tbl' || $week === null) {
                continue;
            }

            $offset = (int) round(($date->getTimestamp() - $week->getTimestamp()) / 86400);
            if ($offset < 0 || $offset > 4) {
                continue;
            }

            foreach ($xp->query('./w:tr', $node) as $row) {
                $cells = $xp->query('./w:tc', $row);
                if ($cells->length < 6 || !preg_match('~^Menü\s*([123])$~u', self::text($xp, $cells->item(0)), $mm)) {
                    continue;
                }
                $parts = [];
                foreach ($xp->query('./w:p', $cells->item($offset + 1)) as $p) {
                    $t = self::text($xp, $p);
                    if ($t !== '') {
                        $parts[] = $t;
                    }
                }
                $menus[] = ['number' => (int) $mm[1], 'text' => implode(' · ', $parts)];
            }
            break;
        }

        $complete = count($menus) === 3 && !array_filter($menus, fn ($m) => $m['text'] === '');

        return $complete ? $menus : null;
    }

    /** @return list<array{name:string, phone:string}> */
    public static function contactsFromXlsx(string $bytes): array
    {
        $entries = self::zipEntries($bytes, null);
        if (!isset($entries['xl/workbook.xml'], $entries['xl/_rels/workbook.xml.rels'])) {
            throw new RuntimeException('Telefonliste ist keine Excel-Datei.');
        }

        $strings = [];
        if (isset($entries['xl/sharedStrings.xml'])) {
            foreach (self::dom($entries['xl/sharedStrings.xml'])->getElementsByTagNameNS('*', 'si') as $si) {
                $strings[] = self::textOf($si, 't');
            }
        }

        $rels = [];
        foreach (self::dom($entries['xl/_rels/workbook.xml.rels'])->getElementsByTagNameNS('*', 'Relationship') as $r) {
            $rels[$r->getAttribute('Id')] = $r->getAttribute('Target');
        }

        $contacts = [];
        $foundHeader = false;
        $relNs = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

        foreach (self::dom($entries['xl/workbook.xml'])->getElementsByTagNameNS('*', 'sheet') as $sheet) {
            $target = $rels[$sheet->getAttributeNS($relNs, 'id')] ?? null;
            if (!$target) {
                continue;
            }
            $target = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
            if (!isset($entries[$target])) {
                continue;
            }

            $nameCol = $phoneCol = null;
            foreach (self::dom($entries[$target])->getElementsByTagNameNS('*', 'row') as $row) {
                $cells = [];
                foreach ($row->getElementsByTagNameNS('*', 'c') as $c) {
                    $type = $c->getAttribute('t');
                    $value = $type === 'inlineStr' ? self::textOf($c, 't') : self::textOf($c, 'v');
                    if ($type === 's') {
                        $value = $strings[(int) $value] ?? '';
                    }
                    $cells[preg_replace('~\d~', '', $c->getAttribute('r'))] = self::clean($value);
                }

                if ($nameCol === null) {
                    foreach ($cells as $col => $v) {
                        if ($v === 'Fahrer') {
                            $nameCol = $col;
                        }
                        if (in_array($v, ['Nummer', 'Telefon', 'Telefonnummer'], true)) {
                            $phoneCol = $col;
                        }
                    }
                    if ($nameCol !== null && $phoneCol !== null) {
                        $foundHeader = true;
                    } else {
                        $nameCol = $phoneCol = null;
                    }
                    continue;
                }

                $name = $cells[$nameCol] ?? '';
                $phone = trim(trim($cells[$phoneCol] ?? '', "\"'"));
                if ($name !== '' && $phone !== '' && $name !== 'Fahrer' && preg_match('~^\+?[0-9\s()/.\-]+$~', $phone)) {
                    $contacts[] = ['name' => $name, 'phone' => $phone];
                }
            }
        }

        if (!$foundHeader || $contacts === []) {
            throw new RuntimeException('Keine Fahrer und Telefonnummern in der Telefonliste erkannt.');
        }

        return $contacts;
    }

    // ------------------------------------------------------------------

    /**
     * @param list<string>|null $names null = alle XML-Einträge
     * @return array<string,string>
     */
    private static function zipEntries(string $bytes, ?array $names): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP-Erweiterung zip fehlt.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'kitaoffice');
        file_put_contents($tmp, $bytes);
        $zip = new ZipArchive();

        try {
            if ($zip->open($tmp) !== true) {
                throw new RuntimeException('Datei ist kein gültiges Office-Dokument.');
            }
            $out = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                $wanted = $names === null ? (str_ends_with($name, '.xml') || str_ends_with($name, '.rels')) : in_array($name, $names, true);
                // Größenbremse gegen Zip-Bomben (Office-Teile sind wenige MB).
                if ($wanted && ($zip->statIndex($i)['size'] ?? 0) <= 20 * 1024 * 1024) {
                    $out[$name] = (string) $zip->getFromIndex($i);
                }
            }

            return $out;
        } finally {
            $zip->close();
            @unlink($tmp);
        }
    }

    private static function dom(string $xml): DOMDocument
    {
        $doc = new DOMDocument();
        if (!@$doc->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('Office-Dokument ist beschädigt.');
        }

        return $doc;
    }

    private static function text(DOMXPath $xp, $node): string
    {
        $s = '';
        foreach ($xp->query('.//w:t', $node) as $t) {
            $s .= $t->textContent;
        }

        return self::clean($s);
    }

    private static function textOf(DOMElement $el, string $local): string
    {
        $s = '';
        foreach ($el->getElementsByTagNameNS('*', $local) as $t) {
            $s .= $t->textContent;
        }

        return $s;
    }

    private static function clean(string $s): string
    {
        return trim(preg_replace('~\s+~u', ' ', $s) ?? $s);
    }
}
