<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DuplicateDetectionService
{
    /**
     * Detect exact duplicates and fuzzy name matches.
     */
    public function detect(string $table, string $uniqueColumn, ?string $uniqueValue, string $nameColumn, ?string $nameValue, ?int $excludeId = null, string $idColumn = 'id'): array
    {
        $exactDuplicate = false;
        if ($uniqueValue !== null && $uniqueValue !== '') {
            $exactDuplicate = DB::table($table)
                ->where($uniqueColumn, $uniqueValue)
                ->when($excludeId, function ($query) use ($idColumn, $excludeId) {
                    return $query->where($idColumn, '!=', $excludeId);
                })
                ->exists();
        }

        $fuzzyWarnings = [];
        if ($nameValue !== null && $nameValue !== '') {
            // Fetch names for comparison
            $records = DB::table($table)
                ->select($idColumn, $nameColumn)
                ->when($excludeId, function ($query) use ($idColumn, $excludeId) {
                    return $query->where($idColumn, '!=', $excludeId);
                })
                ->get();

            foreach ($records as $record) {
                $existingName = $record->$nameColumn;
                if (!$existingName) {
                    continue;
                }

                similar_text(strtolower($nameValue), strtolower($existingName), $percent);

                if ($percent >= 70) {
                    $fuzzyWarnings[] = [
                        'id' => $record->$idColumn,
                        'name' => $existingName,
                        'similarity' => round($percent, 1) . '%'
                    ];
                }
            }

            // Sort warnings by similarity descending
            usort($fuzzyWarnings, function ($a, $b) {
                return floatval($b['similarity']) <=> floatval($a['similarity']);
            });
        }

        return [
            'exact_duplicate' => $exactDuplicate,
            'fuzzy_warnings' => $fuzzyWarnings
        ];
    }
}
