<?php

namespace App\Services;

use App\Models\BarangayRbiUpdate;
use App\Support\HouseholdRbi;
use Illuminate\Validation\ValidationException;

class RbiDuplicateEntries
{
    public function validate(array $rows, array $deceased, string $barangay, string $month, ?int $exceptReport = null): void
    {
        $reports = BarangayRbiUpdate::where('barangay_name', $barangay)
            ->whereDate('reporting_month', substr($month, 0, 7).'-01')
            ->when($exceptReport, fn ($query) => $query->whereKeyNot($exceptReport))
            ->get(['id', 'rows', 'deceased_rows']);
        $messages = [];
        foreach (['rows' => $rows, 'deceased_rows' => $deceased] as $section => $entries) {
            $seen = [];
            foreach ($reports as $report) {
                foreach ($report->$section ?? [] as $entry) {
                    foreach ($this->keys($entry, $section) as $key) {
                        $seen[$key] = 'saved report #'.$report->id;
                    }
                }
            }
            foreach ($entries as $index => $entry) {
                $keys = $this->keys($entry, $section);
                $label = ($section === 'rows' ? 'Resident' : 'Deceased').' entry '.($index + 1);
                foreach ($keys as $key) {
                    if (isset($seen[$key])) {
                        $messages[] = "Duplicate entry warning: {$label} matches {$seen[$key]}. Review or remove the repeated entry before saving.";
                        break;
                    }
                }
                foreach ($keys as $key) {
                    $seen[$key] = strtolower($label).' in this form';
                }
            }
        }
        if ($messages) {
            throw ValidationException::withMessages(['duplicates' => array_values(array_unique($messages))]);
        }
    }

    public function keys(array $row, string $section): array
    {
        $row = HouseholdRbi::normalize($row);
        $keys = [];
        if (! empty($row['inhabitant_id'])) {
            $keys[] = 'id:'.$row['inhabitant_id'];
        }
        $name = $section === 'deceased_rows' ? ($row['deceased_name'] ?? '')
            : implode('|', [$row['last_name'] ?? '', $row['first_name'] ?? '', $row['middle_name'] ?? '', $row['suffix'] ?? '']);
        $name = $this->normalize($name);
        if (trim($name, '| ') === '') {
            return $keys;
        }
        $date = $row[$section === 'deceased_rows' ? 'death_date' : 'birth_date'] ?? '';
        $date = $date ? date('Y-m-d', strtotime($date)) : '';
        // Without a date, include family context to avoid treating namesakes as the same person.
        $family = $date ? '' : $this->normalize(($row['household_number'] ?? '').'|'.($row['household_head'] ?? ''));
        $keys[] = 'person:'.$name.'|'.$date.'|'.$family;

        return $keys;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)));
    }
}
