<?php

namespace App\Services;

use App\Models\User;
use App\Modules\Academic\Events\ImportCompleted;

class ImportEngine
{
    /**
     * Preview student CSV content.
     *
     * @return array<int, array<string, string>>
     */
    public function previewCsv(string $csvContent): array
    {
        $lines = explode("\n", trim($csvContent));
        $headerLine = array_shift($lines);
        if (!$headerLine) {
            return [];
        }

        $header = str_getcsv($headerLine);
        $data = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }
            $row = str_getcsv($line);
            if (count($row) === count($header)) {
                $data[] = array_combine($header, $row);
            }
        }

        return $data;
    }

    /**
     * Import users from CSV array.
     *
     * @param  array<int, array<string, string>>  $data
     * @return array{imported: int, errors: int}
     */
    public function importStudents(array $data): array
    {
        $imported = 0;
        $errors = 0;

        foreach ($data as $row) {
            if (!isset($row['name'], $row['email'])) {
                $errors++;

                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => bcrypt('password'),
                ]
            );

            $user->assignRole('student');
            $imported++;
        }

        event(new ImportCompleted('student_csv', $imported));

        return ['imported' => $imported, 'errors' => $errors];
    }
}
