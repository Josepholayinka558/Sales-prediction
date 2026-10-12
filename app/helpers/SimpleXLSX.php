<?php

class SimpleXLSX {

    public static function parseFile($filePath) {
        if (!class_exists('ZipArchive')) return self::fallbackSimple($filePath);
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) return false;
        $shared = [];
        if (($idx = $zip->locateName('xl/sharedStrings.xml')) !== false) {
            $xml = $zip->getFromIndex($idx);
            if ($xml !== false) $shared = self::parseSharedStrings($xml);
        }
        $sheets = [];
        $workbook = null;
        $rels = null;
        if (($idx = $zip->locateName('xl/_rels/workbook.xml.rels')) !== false) {
            $rels = self::parseRels($zip->getFromIndex($idx));
        }
        if (($idx = $zip->locateName('xl/workbook.xml')) !== false) {
            $workbook = self::parseWorkbook($zip->getFromIndex($idx), $rels);
        }
        if (empty($workbook)) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = $zip->getNameIndex($i);
                if (preg_match('#xl/worksheets/sheet(\d+)\.xml$#', $n, $m)) {
                    $sheets[(int)$m[1]] = $n;
                }
            }
            ksort($sheets);
            $sheetPath = reset($sheets);
        } else {
            $t = $workbook[0]['target'] ?? 'worksheets/sheet1.xml';
            $sheetPath = (strpos($t, '/') === 0) ? ltrim($t, '/') : 'xl/' . $t;
            if (!file_exists('zip://') && !$zip->locateName($sheetPath)) {
                $sheetPath = 'xl/worksheets/sheet1.xml';
            }
        }
        $sheetIdx = $zip->locateName($sheetPath);
        if ($sheetIdx === false) {
            $sheetIdx = $zip->locateName('xl/worksheets/sheet1.xml');
        }
        if ($sheetIdx === false) { $zip->close(); return false; }
        $sheetXml = $zip->getFromIndex($sheetIdx);
        $zip->close();
        if ($sheetXml === false) return false;
        return self::parseSheet($sheetXml, $shared);
    }

    private static function fallbackSimple($filePath) {
        if (!function_exists('gzopen')) return false;
        $zipHandle = @fopen($filePath, 'rb');
        if (!$zipHandle) return false;
        fclose($zipHandle);
        return false;
    }

    private static function parseRels($xml) {
        $out = [];
        if (!$xml) return $out;
        $dom = @simplexml_load_string($xml);
        if (!$dom) return $out;
        $ns = $dom->getNamespaces(true);
        $defNs = $ns[''] ?? '';
        if ($defNs) {
            $dom->registerXPathNamespace('r', $defNs);
            $items = $dom->xpath('//r:Relationship');
            foreach ($items as $i) {
                $attrs = $i->attributes();
                $out[(string)$attrs['Id']] = (string)$attrs['Target'];
            }
        } else {
            foreach ($dom->Relationship as $i) {
                $attrs = $i->attributes();
                $out[(string)$attrs['Id']] = (string)$attrs['Target'];
            }
        }
        return $out;
    }

    private static function parseWorkbook($xml, $rels) {
        $out = [];
        if (!$xml) return $out;
        $dom = @simplexml_load_string($xml);
        if (!$dom) return $out;
        $ns = $dom->getNamespaces(true);
        $defNs = $ns[''] ?? '';
        $sheets = $defNs ? $dom->children($defNs)->sheets->sheet : $dom->sheets->sheet;
        foreach ($sheets as $s) {
            $attrs = $s->attributes();
            $rId = (string)($attrs->attributes('r', true)['id'] ?? '');
            $out[] = [
                'name' => (string)$attrs['name'],
                'target' => $rels[$rId] ?? ('worksheets/sheet' . count($out) + 1 . '.xml'),
            ];
        }
        return $out;
    }

    private static function parseSharedStrings($xml) {
        $out = [];
        if (!$xml) return $out;
        $dom = @simplexml_load_string($xml);
        if (!$dom) return $out;
        $ns = $dom->getNamespaces(true);
        $defNs = $ns[''] ?? '';
        $strings = $defNs ? $dom->children($defNs)->si : $dom->si;
        foreach ($strings as $si) {
            if (isset($si->t)) {
                $out[] = (string)$si->t;
            } elseif (isset($si->r)) {
                $parts = [];
                foreach ($si->r as $r) if (isset($r->t)) $parts[] = (string)$r->t;
                $out[] = implode('', $parts);
            } else {
                $out[] = trim(preg_replace('/\s+/', ' ', (string)$si));
            }
        }
        return $out;
    }

    private static function colIdx($ref) {
        $ref = preg_replace('/\d/', '', $ref);
        $n = 0;
        for ($i = 0; $i < strlen($ref); $i++) {
            $n = $n * 26 + (ord(strtoupper($ref[$i])) - 64);
        }
        return max(0, $n - 1);
    }

    private static function parseSheet($xml, $shared) {
        if (!$xml) return false;
        $dom = @simplexml_load_string($xml);
        if (!$dom) return false;
        $ns = $dom->getNamespaces(true);
        $defNs = $ns[''] ?? '';
        $sheetData = $defNs ? $dom->children($defNs)->sheetData : $dom->sheetData;
        if (!$sheetData) return false;
        $rows = [];
        $headers = null;
        $maxCol = 0;
        $rowNum = 0;
        foreach ($sheetData->row as $row) {
            $cells = [];
            $rowMax = 0;
            foreach ($row->c as $c) {
                $attrs = $c->attributes();
                $ref = (string)$attrs['r'];
                $type = (string)$attrs['t'];
                $colIdx = self::colIdx($ref);
                $val = '';
                if ($type === 's') {
                    $idx = (int)($c->v ?? 0);
                    $val = isset($shared[$idx]) ? $shared[$idx] : '';
                } elseif ($type === 'inlineStr') {
                    $val = isset($c->is->t) ? (string)$c->is->t : '';
                } elseif ($type === 'b') {
                    $val = (string)$c->v === '1' ? 'TRUE' : 'FALSE';
                } else {
                    $v = (string)($c->v ?? '');
                    if (isset($attrs['s'])) {
                        $style = (int)$attrs['s'];
                        if (strlen($v) && is_numeric($v) && $style > 0) {
                            $num = (float)$v;
                            if ($num > 25568 && $num < 80000) {
                                $days = (int)$num;
                                $frac = $num - $days;
                                $ts = ($days - 25569) * 86400 + (int)round($frac * 86400);
                                $val = date('Y-m-d', $ts);
                                if (round($frac * 86400) > 0) $val .= ' ' . date('H:i:s', round($frac * 86400));
                            } else {
                                $val = $v;
                            }
                        } else {
                            $val = $v;
                        }
                    } else {
                        $val = $v;
                    }
                }
                $cells[$colIdx] = $val;
                if ($colIdx > $rowMax) $rowMax = $colIdx;
            }
            if ($rowNum === 0) {
                if (empty($cells)) continue;
                $maxCol = $rowMax;
                $headers = [];
                for ($i = 0; $i <= $maxCol; $i++) $headers[$i] = trim($cells[$i] ?? '');
                $rowNum++;
                continue;
            }
            if (empty($cells)) { $rowNum++; continue; }
            if ($rowMax > $maxCol) $maxCol = $rowMax;
            $obj = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $key = !empty($headers[$i]) ? $headers[$i] : ('col_' . ($i + 1));
                $obj[$key] = $cells[$i] ?? '';
            }
            $has = false;
            foreach ($obj as $v) if ($v !== '' && $v !== null) { $has = true; break; }
            if ($has) $rows[] = $obj;
            $rowNum++;
        }
        return $rows;
    }
}
