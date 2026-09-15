<?php

namespace App\Support;

class RbiFieldOptions
{
    public static function all(): array
    {
        return [
            'civil_status' => ['Single', 'Married', 'Widowed', 'Separated', 'Annulled', 'Divorced', 'Live-in / Common-law'],
            'education_level' => array_merge(['No formal education', 'Preschool', 'Kindergarten'], array_map(fn ($grade) => 'Grade '.$grade, range(1, 12)), ['Elementary level', 'Elementary graduate', 'High school level', 'High school graduate', 'Junior high school graduate', 'Senior high school graduate', 'Vocational / Technical level', 'Vocational / Technical graduate', 'College level', 'College graduate', 'Postgraduate level', 'Postgraduate degree']),
            'religion' => ['Roman Catholic', 'Iglesia ni Cristo', 'Islam', 'Seventh-day Adventist', 'Baptist', 'Born Again Christian', 'United Church of Christ in the Philippines', 'United Methodist Church', 'Iglesia Filipina Independiente', 'Jehovah\'s Witnesses', 'Church of Jesus Christ of Latter-day Saints', 'Buddhism', 'Hinduism', 'No religious affiliation', 'Prefer not to say'],
        ];
    }
}
