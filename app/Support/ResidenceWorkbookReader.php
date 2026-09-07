<?php

namespace App\Support;

use ZipArchive;
use RuntimeException;

class ResidenceWorkbookReader
{
    public static function color(\SimpleXMLElement $color, array $theme): string
    {
        $rgb = (string) $color['rgb'];
        if ($rgb === '' && isset($color['theme'])) $rgb = $theme[(int) $color['theme']] ?? 'UNRESOLVED';
        if ($rgb === '' && isset($color['indexed'])) {
            $palette = [0=>'000000',1=>'FFFFFF',2=>'FF0000',3=>'00FF00',4=>'0000FF',5=>'FFFF00',6=>'FF00FF',7=>'00FFFF',
                8=>'000000',9=>'FFFFFF',10=>'FF0000',11=>'00FF00',12=>'0000FF',13=>'FFFF00',14=>'FF00FF',15=>'00FFFF',
                17=>'008000',42=>'CCFFCC',50=>'00CC99',57=>'003300',64=>''];
            $rgb = $palette[(int) $color['indexed']] ?? 'UNRESOLVED';
        }
        if ($rgb === '' || $rgb === 'UNRESOLVED') return $rgb;
        $rgb = substr($rgb, -6);
        $tint = (float) ($color['tint'] ?? 0);
        $result = '';
        foreach (str_split($rgb, 2) as $channel) {
            $value = hexdec($channel);
            $value = $tint < 0 ? $value * (1 + $tint) : $value + (255 - $value) * $tint;
            $result .= sprintf('%02X', (int) round($value));
        }
        return 'FF'.$result;
    }

public static function read(string $path, ?array $sheetNames = null): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException("Unable to open {$path}");
    }

    $shared = [];
    if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
        $document = simplexml_load_string($xml);
        foreach ($document->si as $item) {
            $texts = $item->xpath('.//*[local-name()="t"]');
            $shared[] = implode('', array_map(fn ($text) => (string) $text, $texts));
        }
    }

    $book = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
    $relationships = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
    $targets = [];
    foreach ($relationships->Relationship as $relationship) {
        $targets[(string) $relationship['Id']] = 'xl/'.ltrim((string) $relationship['Target'], '/');
    }

    $styles = simplexml_load_string($zip->getFromName('xl/styles.xml'));
    $theme = [];
    if (($themeXml = $zip->getFromName('xl/theme/theme1.xml')) !== false) {
        $themeDocument = simplexml_load_string($themeXml);
        $themeNames = ['lt1','dk1','lt2','dk2','accent1','accent2','accent3','accent4','accent5','accent6','hlink','folHlink'];
        foreach ($themeDocument->xpath('//*[local-name()="clrScheme"]/*') as $entry) {
            $index = array_search($entry->getName(), $themeNames, true);
            $nodes = $entry->xpath('./*');
            if ($index !== false && $nodes) $theme[$index] = (string) ($nodes[0]['val'] ?? $nodes[0]['lastClr']);
            if ($index !== false && isset($nodes[0]['lastClr'])) $theme[$index] = (string) $nodes[0]['lastClr'];
        }
    }
    $fills = [];
    foreach ($styles->fills->fill as $fill) $fills[] = self::color($fill->patternFill->fgColor, $theme);
    $cellFills = [];
    foreach ($styles->cellXfs->xf as $style) $cellFills[] = $fills[(int) $style['fillId']] ?? '';
    $sheets = [];
    foreach ($book->sheets->sheet as $sheet) {
        if ($sheetNames !== null && ! in_array((string) $sheet['name'], $sheetNames, true)) continue;
        $attributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $reader = new \XMLReader();
        if (! $reader->open('zip://'.str_replace('\\', '/', realpath($path)).'#'.$targets[(string) $attributes['id']], null, LIBXML_NONET)) throw new RuntimeException('Unable to stream worksheet');
        $rows = [];
        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'row') continue;
            $xml = $reader->readOuterXml();
            if (! str_contains($xml, '<v>') && ! str_contains($xml, '<is>')) continue;
            $row = simplexml_load_string($xml);
            $values = [];
            foreach ($row->c as $cell) {
                preg_match('/^[A-Z]+/', (string) $cell['r'], $match);
                $column = $match[0];
                $value = (string) $cell->v;
                if ((string) $cell['t'] === 's' && $value !== '') {
                    $value = $shared[(int) $value] ?? '';
                } elseif ((string) $cell['t'] === 'inlineStr') {
                    $value = implode('', array_map(fn ($text) => (string) $text, $cell->is->xpath('.//*[local-name()="t"]')));
                }
                $values[$column] = trim($value);
                $values['_fills'][$column] = $cellFills[(int) $cell['s']] ?? '';
            }
            if (array_filter($values, fn ($value, $key) => $key !== '_fills' && $value !== '', ARRAY_FILTER_USE_BOTH)) $rows[(int) $row['r']] = $values;
        }
        $reader->close();
        $sheets[(string) $sheet['name']] = $rows;
    }
    $zip->close();

    return $sheets;
}

}
