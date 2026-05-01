<?php

declare(strict_types=1);

namespace App\Services\Primeur;

use RuntimeException;

/**
 * Liest Access-MDB-Dateien mit mdb-export (mdbtools) und liefert Zeilen als Arrays.
 */
class MdbReader
{
    private const MDB_EXPORT = '/usr/bin/mdb-export';
    private const DATE_RAW_PATTERN = '!^(\d{2})/(\d{2})/(\d{2,4})\s(\d{2}:\d{2}:\d{2})$!';

    public static function tableExists(string $mdbPath, string $table): bool
    {
        $output = shell_exec(sprintf(
            '/usr/bin/mdb-tables %s 2>/dev/null',
            escapeshellarg($mdbPath)
        ));
        if ($output === null) {
            return false;
        }
        $tables = array_filter(array_map('trim', explode(' ', $output)));
        return in_array($table, $tables, true);
    }

    /**
     * Iterates rows from a MDB table via mdb-export CSV, yielding associative arrays.
     * Skips header row automatically.
     *
     * @param  string  $mdbPath  Absolute path to .mdb file
     * @param  string  $table    Table name
     * @return iterable<array<string,string|null>>
     */
    public static function rows(string $mdbPath, string $table): iterable
    {
        if (! file_exists($mdbPath)) {
            throw new RuntimeException("MDB-Datei nicht gefunden: {$mdbPath}");
        }

        // mdb-export outputs CSV with header row (default: comma-separated, double-quoted strings)
        // -b strip: strip binary data instead of embedding raw bytes
        $cmd = sprintf(
            '%s -b strip %s %s 2>/dev/null',
            self::MDB_EXPORT,
            escapeshellarg($mdbPath),
            escapeshellarg($table)
        );

        // popen() gives a proper stream handle that fgetcsv() handles correctly,
        // including multi-line quoted fields.
        $handle = popen($cmd, 'r');
        if (! is_resource($handle)) {
            throw new RuntimeException("Konnte mdb-export nicht starten für: {$mdbPath}:{$table}");
        }

        $header      = null;
        $headerCount = 0;
        while (! feof($handle)) {
            $row = fgetcsv($handle, 0, ',', '"');
            if ($row === false || $row === null) {
                continue;
            }
            if ($header === null) {
                $header      = $row;
                $headerCount = count($header);
                continue;
            }
            if (count($row) !== $headerCount) {
                continue; // Defekte Zeile überspringen
            }
            yield array_combine($header, $row);
        }

        pclose($handle);
    }

    /**
     * Reads rows from a pre-exported CSV file (from mdb-export run on the host).
     * Same interface as rows().
     *
     * @return iterable<array<string,string|null>>
     */
    public static function csvRows(string $csvPath): iterable
    {
        if (! file_exists($csvPath)) {
            throw new RuntimeException("CSV-Datei nicht gefunden: {$csvPath}");
        }

        $handle = fopen($csvPath, 'r');
        if (! is_resource($handle)) {
            throw new RuntimeException("Konnte CSV nicht öffnen: {$csvPath}");
        }

        $header      = null;
        $headerCount = 0;
        while (! feof($handle)) {
            $row = fgetcsv($handle, 0, ',', '"');
            if ($row === false || $row === null) {
                continue;
            }
            if ($header === null) {
                $header      = $row;
                $headerCount = count($header);
                continue;
            }
            if (count($row) !== $headerCount) {
                continue;
            }
            yield array_combine($header, $row);
        }

        fclose($handle);
    }

    /**
     * Converts Access date string "MM/DD/YY HH:MM:SS" or "MM/DD/YYYY HH:MM:SS" to
     * MySQL-compatible "YYYY-MM-DD HH:MM:SS". Returns null for empty/invalid values.
     */
    public static function parseDateTime(string $raw): ?string
    {
        $raw = trim($raw, " \t\n\r\0\x0B'\"");
        if ($raw === '' || $raw === '0' || $raw === '00/00/00 00:00:00') {
            return null;
        }
        if (preg_match(self::DATE_RAW_PATTERN, $raw, $m)) {
            $month = $m[1];
            $day   = $m[2];
            $year  = strlen($m[3]) === 2 ? (((int) $m[3] > 30) ? '19' : '20') . $m[3] : $m[3];
            $time  = $m[4];
            return "{$year}-{$month}-{$day} {$time}";
        }
        return null;
    }

    public static function parseDate(string $raw): ?string
    {
        $dt = self::parseDateTime($raw);
        return $dt ? substr($dt, 0, 10) : null;
    }

    public static function parseFloat(string $raw): ?float
    {
        $raw = trim($raw, " \"'");
        if ($raw === '' || $raw === 'null') {
            return null;
        }
        return (float) str_replace(',', '.', $raw);
    }

    public static function parseInt(string $raw): ?int
    {
        $raw = trim($raw, " \"'");
        if ($raw === '' || $raw === 'null') {
            return null;
        }
        return (int) $raw;
    }

    public static function parseBool(string $raw): bool
    {
        return in_array(strtolower(trim($raw, " \"'")), ['1', 'true', 'yes', '-1'], true);
    }

    public static function parseStr(string $raw, int $maxLen = 0): ?string
    {
        $val = trim($raw, " \"'");
        if ($val === '' || $val === 'null') {
            return null;
        }
        return $maxLen > 0 ? mb_substr($val, 0, $maxLen) : $val;
    }
}
