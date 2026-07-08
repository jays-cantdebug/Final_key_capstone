<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ClassificationThreshold;
use App\Services\ClassificationThresholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class ClassificationThresholdTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOfficialThresholds();
    }

    public function test_psychometrician_can_view_the_thresholds_page(): void
    {
        $psychometrician = $this->psychometrician();

        $this->actingAs($psychometrician)->get(route('settings.classification-thresholds'))->assertOk();
    }

    public function test_updating_a_threshold_marks_it_as_overridden(): void
    {
        $psychometrician = $this->psychometrician();
        $threshold = ClassificationThreshold::query()
            ->where('subscale', ClassificationThreshold::SUBSCALE_DEPRESSION)
            ->where('severity_level', ClassificationThreshold::SEVERITY_NORMAL)
            ->first();

        $this->actingAs($psychometrician)->put(route('settings.classification-thresholds.update'), [
            'thresholds' => [
                ['id' => $threshold->id, 'min_score' => 0, 'max_score' => 8],
            ],
        ]);

        $this->assertSame(8, $threshold->fresh()->max_score);
        $this->assertTrue(app(ClassificationThresholdService::class)->isOverridden());
    }

    public function test_restore_official_resets_an_overridden_threshold(): void
    {
        $psychometrician = $this->psychometrician();
        $threshold = ClassificationThreshold::query()
            ->where('subscale', ClassificationThreshold::SUBSCALE_DEPRESSION)
            ->where('severity_level', ClassificationThreshold::SEVERITY_NORMAL)
            ->first();

        $this->actingAs($psychometrician)->put(route('settings.classification-thresholds.update'), [
            'thresholds' => [['id' => $threshold->id, 'min_score' => 0, 'max_score' => 8]],
        ]);
        $this->actingAs($psychometrician)->post(route('settings.classification-thresholds.restore'));

        $this->assertSame(9, $threshold->fresh()->max_score);
        $this->assertFalse(app(ClassificationThresholdService::class)->isOverridden());
    }

    public function test_max_score_must_be_greater_than_or_equal_to_min_score(): void
    {
        $psychometrician = $this->psychometrician();
        $threshold = ClassificationThreshold::first();

        $response = $this->actingAs($psychometrician)->put(route('settings.classification-thresholds.update'), [
            'thresholds' => [['id' => $threshold->id, 'min_score' => 10, 'max_score' => 5]],
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_guidance_counselor_cannot_access_classification_thresholds(): void
    {
        $counselor = $this->guidanceCounselor();

        $this->actingAs($counselor)->get(route('settings.classification-thresholds'))->assertForbidden();
    }
}
