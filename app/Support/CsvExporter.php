<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Authenticated CSV streaming with spreadsheet formula-injection protection.
 */
class CsvExporter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|null>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            // UTF-8 BOM helps spreadsheet apps detect encoding.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                $safeRow = [];

                foreach ($row as $value) {
                    $safeRow[] = self::escapeCell($value);
                }

                fputcsv($handle, $safeRow);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public static function escapeCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $string = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        if ($string !== '' && preg_match('/^[=+\-@\t\r]/', $string) === 1) {
            return "'".$string;
        }

        return $string;
    }
}
