<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Student;
use App\Services\StudentDuplicateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentDuplicateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_trims_collapses_whitespace_and_lowercases(): void
    {
        $this->assertSame('dela cruz', StudentDuplicateService::normalize("  Dela \t  CRUZ \n"));
        $this->assertSame('', StudentDuplicateService::normalize(null));
    }

    public function test_middle_names_are_compared_by_first_letter_only(): void
    {
        $this->assertSame('d', StudentDuplicateService::middleInitial('D.'));
        $this->assertSame('d', StudentDuplicateService::middleInitial(' d'));
        $this->assertSame('d', StudentDuplicateService::middleInitial('Dela'));
        $this->assertSame('', StudentDuplicateService::middleInitial(null));
    }

    public function test_find_matches_matches_any_middle_name_with_the_same_initial_and_splits_archived(): void
    {
        $initial = Student::factory()->create(['first_name' => 'Juan', 'middle_name' => 'D.', 'last_name' => 'Cruz']);
        $fullMiddle = Student::factory()->create(['first_name' => 'juan', 'middle_name' => 'Dela', 'last_name' => 'CRUZ']);
        $archived = Student::factory()->create(['first_name' => 'Juan', 'middle_name' => 'd', 'last_name' => 'Cruz']);
        $archived->delete();
        Student::factory()->create(['first_name' => 'Juan', 'middle_name' => 'P.', 'last_name' => 'Cruz']);
        Student::factory()->create(['first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Cruz']);

        $matches = (new StudentDuplicateService)->findMatches('Juan', 'D.', 'Cruz');

        $this->assertEqualsCanonicalizing([$initial->id, $fullMiddle->id], $matches['active']->modelKeys());
        $this->assertSame([$archived->id], $matches['archived']->modelKeys());
    }

    public function test_an_enye_middle_initial_is_one_multibyte_letter_distinct_from_n(): void
    {
        $this->assertSame('ñ', StudentDuplicateService::middleInitial('Ñ.'));
        $this->assertSame('ñ', StudentDuplicateService::middleInitial('ñ.'));
        $this->assertSame('ñ', StudentDuplicateService::middleInitial('Ñuñez'));
        $this->assertSame('n', StudentDuplicateService::middleInitial('N.'));
    }

    public function test_find_matches_compares_an_enye_middle_initial_case_insensitively_and_apart_from_n(): void
    {
        $upper = Student::factory()->create(['first_name' => 'Jose', 'middle_name' => 'Ñ.', 'last_name' => 'Rizal']);
        $lower = Student::factory()->create(['first_name' => 'Jose', 'middle_name' => 'ñ.', 'last_name' => 'Rizal']);
        Student::factory()->create(['first_name' => 'Jose', 'middle_name' => 'N.', 'last_name' => 'Rizal']);

        $matches = (new StudentDuplicateService)->findMatches('Jose', 'ñ.', 'Rizal');

        $this->assertEqualsCanonicalizing([$upper->id, $lower->id], $matches['active']->modelKeys());
    }
}
