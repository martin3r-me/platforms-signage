<?php

namespace Platform\Signage\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Platform\Signage\Support\KitaOffice\KitaOfficeConfig;
use Platform\Signage\Support\KitaOffice\OfficeFiles;
use ZipArchive;

/**
 * Speiseplan (DOCX) und Telefonliste (XLSX) nach Patricks Regeln (kein Laravel nötig).
 */
class KitaOfficeOfficeFilesTest extends TestCase
{
    private function day(string $d): DateTimeImmutable
    {
        return new DateTimeImmutable($d, new DateTimeZone('Europe/Berlin'));
    }

    private function zip(array $files): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'kotest');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $bytes = file_get_contents($tmp);
        unlink($tmp);

        return $bytes;
    }

    private function cell(string ...$paragraphs): string
    {
        return '<w:tc>' . implode('', array_map(fn ($p) => '<w:p><w:r><w:t>' . htmlspecialchars($p) . '</w:t></w:r></w:p>', $paragraphs)) . '</w:tc>';
    }

    private function week(string $title, array $rows): string
    {
        $xml = '<w:p><w:r><w:t>' . $title . '</w:t></w:r></w:p><w:tbl>';
        $xml .= '<w:tr>' . $this->cell('') . $this->cell('Mo') . $this->cell('Di') . $this->cell('Mi') . $this->cell('Do') . $this->cell('Fr') . '</w:tr>';
        foreach ($rows as $label => $days) {
            $xml .= '<w:tr>' . $this->cell($label) . implode('', array_map(fn ($d) => $this->cell(...(array) $d), $days)) . '</w:tr>';
        }

        return $xml . '</w:tbl>';
    }

    private function docx(string $body): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            . $body . '</w:body></w:document>';
    }

    private function plan(): string
    {
        $rows = fn ($w) => [
            'Menü 1' => [["$w Mo Nudeln", 'mit Soße'], "$w Di 1", "$w Mi 1", "$w Do 1", "$w Fr 1"],
            'Menü 2' => ["$w Mo 2", "$w Di 2", "$w Mi 2", "$w Do 2", "$w Fr 2"],
            'Menü 3' => ["$w Mo 3", "$w Di 3", "$w Mi 3", "$w Do 3", "$w Fr 3"],
        ];

        return $this->docx(
            $this->week('Speiseplan 02.11.2026 - 06.11.2026', $rows('W45'))
            . $this->week('Speiseplan 09.11.2026 - 13.11.2026', $rows('W46'))
        );
    }

    public function test_menus_for_the_right_week_and_weekday(): void
    {
        $menus = OfficeFiles::menusFromDocumentXml($this->plan(), $this->day('2026-11-09'));
        $this->assertSame([
            ['number' => 1, 'text' => 'W46 Mo Nudeln · mit Soße'],
            ['number' => 2, 'text' => 'W46 Mo 2'],
            ['number' => 3, 'text' => 'W46 Mo 3'],
        ], $menus);

        $this->assertSame('W45 Fr 2', OfficeFiles::menusFromDocumentXml($this->plan(), $this->day('2026-11-06'))[1]['text']);
    }

    public function test_weekend_is_empty_and_missing_day_is_null(): void
    {
        $this->assertSame([], OfficeFiles::menusFromDocumentXml($this->plan(), $this->day('2026-11-14')));
        $this->assertNull(OfficeFiles::menusFromDocumentXml($this->plan(), $this->day('2026-11-16')));
    }

    public function test_incomplete_day_is_null(): void
    {
        $xml = $this->docx($this->week('Speiseplan 09.11.2026 - 13.11.2026', [
            'Menü 1' => ['a', 'b', 'c', 'd', 'e'],
            'Menü 2' => ['', 'b', 'c', 'd', 'e'],
            'Menü 3' => ['a', 'b', 'c', 'd', 'e'],
        ]));
        $this->assertNull(OfficeFiles::menusFromDocumentXml($xml, $this->day('2026-11-09')));
    }

    public function test_docx_bytes_and_month_path(): void
    {
        $bytes = $this->zip(['word/document.xml' => $this->plan()]);
        $this->assertCount(3, OfficeFiles::menusFromDocx($bytes, $this->day('2026-11-10')));

        $this->assertSame('2026/03 - März/Speiseplan März 2026.docx', OfficeFiles::menuPath(OfficeFiles::DEFAULT_MENU_PATTERN, $this->day('2026-03-02')));
    }

    public function test_phone_list_from_xlsx(): void
    {
        $bytes = $this->zip([
            'xl/workbook.xml' => '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Liste" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>',
            'xl/sharedStrings.xml' => '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Fahrer</t></si><si><t>Nummer</t></si><si><t>Yosko  Petrov</t></si><si><r><t>Ali </t></r><r><t>Demir</t></r></si></sst>',
            'xl/worksheets/sheet1.xml' => '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
                . '<row r="1"><c r="A1" t="inlineStr"><is><t>Telefonliste Logistik</t></is></c></row>'
                . '<row r="2"><c r="B2" t="s"><v>0</v></c><c r="C2" t="s"><v>1</v></c></row>'
                . '<row r="3"><c r="B3" t="s"><v>2</v></c><c r="C3" t="inlineStr"><is><t>\'0171 111 22</t></is></c></row>'
                . '<row r="4"><c r="B4" t="s"><v>3</v></c><c r="C4" t="inlineStr"><is><t>+49 (171) 2-3</t></is></c></row>'
                . '<row r="5"><c r="B5" t="inlineStr"><is><t>Ohne Nummer</t></is></c><c r="C5" t="inlineStr"><is><t>folgt</t></is></c></row>'
                . '</sheetData></worksheet>',
        ]);

        $this->assertSame([
            ['name' => 'Yosko Petrov', 'phone' => '0171 111 22'],
            ['name' => 'Ali Demir', 'phone' => '+49 (171) 2-3'],
        ], OfficeFiles::contactsFromXlsx($bytes));
    }

    public function test_m365_config_is_sanitized(): void
    {
        $cfg = KitaOfficeConfig::sanitize([
            'm365_connection_id' => '7',
            'calendar_group_id'  => 'nope',
            'menu_folder_url'    => 'https://evil.example.com/x',
            'phone_list_url'     => 'https://broichcatering.sharepoint.com/:x:/s/kita/abc',
            'menu_file_pattern'  => '../geheim.docx',
        ]);

        $this->assertSame(7, $cfg['m365_connection_id']);
        $this->assertSame('', $cfg['calendar_group_id']);
        $this->assertSame('', $cfg['menu_folder_url']);
        $this->assertSame('https://broichcatering.sharepoint.com/:x:/s/kita/abc', $cfg['phone_list_url']);
        $this->assertSame(OfficeFiles::DEFAULT_MENU_PATTERN, $cfg['menu_file_pattern']);
        $this->assertSame('DFF24F5A-A951-4BEA-9829-A7D577507106', KitaOfficeConfig::sanitize([])['calendar_group_id']);
    }
}
