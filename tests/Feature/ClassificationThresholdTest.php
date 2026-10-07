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
        $threshold = $this->band(ClassificationThreshold::SUBSCALE_DEPRESSION, ClassificationThreshold::SEVERITY_NORMAL);

        // Mild moves down with Normal, so score 9 still has a band.
        $this->actingAs($psychometrician)->put(route('settings.classification-thresholds.update'), [
            'thresholds' => $this->shrinkDepressionNormalTo8(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(8, $threshold->fresh()->max_score);
        $this->assertTrue(app(ClassificationThresholdService::class)->isOverridden());
    }

    public function test_restore_official_resets_an_overridden_threshold(): void
    {
        $psychometrician = $this->psychometrician();
        $threshold = $this->band(ClassificationThreshold::SUBSCALE_DEPRESSION, ClassificationThreshold::SEVERITY_NORMAL);

        $this->actingAs($psychometrician)->put(route('settings.classification-thresholds.update'), [
            'thresholds' => $this->shrinkDepressionNormalTo8(),
        ])->assertSessionHasNoErrors();
        $this->actingAs($psychometrician)->post(route('settings.classification-thresholds.restore'));

        $this->assertSame(9, $threshold->fresh()->max_score);
        $this->assertSame(10, $this->band(ClassificationThreshold::SUBSCALE_DEPRESSION, ClassificationThreshold::SEVERITY_MILD)->min_score);
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

    public function test_saving_every_row_unchanged_is_accepted(): void
    {
        $this->actingAs($this->psychometrician())->put(route('settings.classification-thresholds.update'), [
            'thresholds' => $this->formRows(),
        ])->assertSessionHasNoErrors();

        $this->assertFalse(app(ClassificationThresholdService::class)->isOverridden());
    }

    public function test_a_valid_override_of_every_subscale_is_saved(): void
    {
        $this->actingAs($this->psychometrician())->put(route('settings.classification-thresholds.update'), [
            'thresholds' => $this->formRows([
                'Depression:Normal' => [0, 8],
                'Depression:Mild' => [9, 13],
                'Anxiety:Severe' => [15, 20],
                'Anxiety:Extremely Severe' => [21, 42],
                'Stress:Moderate' => [19, 24],
                'Stress:Severe' => [25, 33],
            ]),
        ])->assertSessionHasNoErrors();

        $this->assertSame([25, 33], $this->range(ClassificationThreshold::SUBSCALE_STRESS, ClassificationThreshold::SEVERITY_SEVERE));
        $this->assertSame([21, 42], $this->range(ClassificationThreshold::SUBSCALE_ANXIETY, ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE));
        $this->assertTrue(app(ClassificationThresholdService::class)->isOverridden());
    }

    public function test_a_gap_between_bands_is_refused_and_nothing_is_saved(): void
    {
        $response = $this->actingAs($this->psychometrician())->put(route('settings.classification-thresholds.update'), [
            'thresholds' => $this->formRows(['Stress:Severe' => [26, 29]]),
        ]);

        $response->assertSessionHasErrors(['thresholds' => 'Stress: Extremely Severe must start at 30, right after Severe ends at 29, not at 34 — as entered, scores 30 to 33 would have no severity level.']);
        $this->assertSame([26, 33], $this->range(ClassificationThreshold::SUBSCALE_STRESS, ClassificationThreshold::SEVERITY_SEVERE));
        $this->assertDatabaseMissing('audit_logs', ['action' => 'Classification Threshold Override']);
    }

    public function test_a_one_score_gap_is_refused(): void
    {
        $response = $this->actingAs($this->psychometrician())->put(route('settings.classification-thresholds.update'), [
            'thresholds' => $this->formRows(['Anxiety:Mild' => [9, 9]]),
        ]);

        $response->assertSessionHasErrors(['thresholds' => 'Anxiety: Mild must start at 8, right after Normal ends at 7, not at 9 — as entered, score 8 would have no severity level.']);
    }

    public function test_overlapping_bands_are_refused_and_nothing_is_saved(): void
    {
        $response = $this->actingAs($this->psychometrician())->put(route('settings.classification-thresholds.update'), [
            'thresholds' => $this->formRows(['Stress:Extremely Severe' => [30, 42]]),
        ]);

        $response->assertSessionHasErrors(['thresholds' => 'Stress: Extremely Severe must start at 34, right after Severe ends at 33, not at 30 — as entered, scores 30 to 33 would fall in both Severe and Extremely Severe.']);
        $this->assertSame([34, 42], $this->range(ClassificationThreshold::SUBSCALE_STRESS, ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE));
    }

    public function test_bands_out_of_severity_order_are_refused(): void
    {
        $response = $this->actingAs($this->psychometrician())->put(route('settings.classification-thresholds.update'), [
            'thresholds' => $this->formRows(['Depression:Normal' => [5, 9], 'Depression:Mild' => [0, 4], 'Depression:Moderate' => [10, 20]]),
        ]);

        $response->assertSessionHasErrors(['thresholds' => 'Depression: Normal must start at 0, not 5 — as entered, scores 0 to 4 would have no severity level.']);
        $response->assertSessionHasErrors(['thresholds' => 'Depression: Mild must start at 10, right after Normal ends at 9, not at 0 — as entered, Mild comes before Normal.']);
    }

    public function test_the_lowest_band_must_start_at_zero(): void
    {
        $response = $this->actingAs($this->psychometrician())->put(route('settings.classification-thresholds.update'), [
            'thresholds' => $this->formRows(['Anxiety:Normal' => [1, 7]]),
        ]);

        $response->assertSessionHasErrors(['thresholds' => 'Anxiety: Normal must start at 0, not 1 — as entered, score 0 would have no severity level.']);
    }

    public function test_the_top_band_must_end_at_42(): void
    {
        $response = $this->actingAs($this->psychometrician())->put(route('settings.classification-thresholds.update'), [
            'thresholds' => $this->formRows(['Depression:Extremely Severe' => [28, 40], 'Stress:Extremely Severe' => [34, 50]]),
        ]);

        $response->assertSessionHasErrors(['thresholds' => 'Depression: Extremely Severe must end at 42, the highest possible score (it covers every score from its minimum up), not at 40 — as entered, scores 41 to 42 would have no severity level.']);
        $response->assertSessionHasErrors(['thresholds' => 'Stress: Extremely Severe must end at 42, the highest possible score (it covers every score from its minimum up), not at 50.']);
        $this->assertSame([28, 42], $this->range(ClassificationThreshold::SUBSCALE_DEPRESSION, ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE));
    }

    public function test_rows_left_out_of_the_submission_are_checked_at_their_current_values(): void
    {
        // Only Normal is sent; Mild still starts at 10, so score 9 would be lost.
        $response = $this->actingAs($this->psychometrician())->put(route('settings.classification-thresholds.update'), [
            'thresholds' => [['id' => $this->band(ClassificationThreshold::SUBSCALE_DEPRESSION, ClassificationThreshold::SEVERITY_NORMAL)->id, 'min_score' => 0, 'max_score' => 8]],
        ]);

        $response->assertSessionHasErrors(['thresholds' => 'Depression: Mild must start at 9, right after Normal ends at 8, not at 10 — as entered, score 9 would have no severity level.']);
        $this->assertSame([0, 9], $this->range(ClassificationThreshold::SUBSCALE_DEPRESSION, ClassificationThreshold::SEVERITY_NORMAL));
    }

    public function test_the_problems_are_shown_on_the_thresholds_page(): void
    {
        $psychometrician = $this->psychometrician();

        $this->actingAs($psychometrician)
            ->from(route('settings.classification-thresholds'))
            ->put(route('settings.classification-thresholds.update'), [
                'thresholds' => $this->formRows(['Stress:Severe' => [26, 29]]),
            ])
            ->assertRedirect(route('settings.classification-thresholds'));

        $this->actingAs($psychometrician)->get(route('settings.classification-thresholds'))
            ->assertSee('Stress: Extremely Severe must start at 30, right after Severe ends at 29, not at 34 — as entered, scores 30 to 33 would have no severity level.');
    }

    public function test_guidance_counselor_cannot_access_classification_thresholds(): void
    {
        $counselor = $this->guidanceCounselor();

        $this->actingAs($counselor)->get(route('settings.classification-thresholds'))->assertForbidden();
    }

    private function band(string $subscale, string $severityLevel): ClassificationThreshold
    {
        return ClassificationThreshold::query()
            ->where('subscale', $subscale)
            ->where('severity_level', $severityLevel)
            ->firstOrFail();
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function range(string $subscale, string $severityLevel): array
    {
        $band = $this->band($subscale, $severityLevel);

        return [$band->min_score, $band->max_score];
    }

    /**
     * All 15 rows, as the Override Mode form submits them, with the given
     * "Subscale:Severity Level" => [min, max] changes applied.
     *
     * @param  array<string, array{0: int, 1: int}>  $changes
     * @return array<int, array{id: int, min_score: int, max_score: int}>
     */
    private function formRows(array $changes = []): array
    {
        return ClassificationThreshold::query()->orderBy('id')->get()
            ->map(function (ClassificationThreshold $threshold) use ($changes): array {
                [$min, $max] = $changes["{$threshold->subscale}:{$threshold->severity_level}"] ?? [$threshold->min_score, $threshold->max_score];

                return ['id' => $threshold->id, 'min_score' => $min, 'max_score' => $max];
            })
            ->all();
    }

    /**
     * @return array<int, array{id: int, min_score: int, max_score: int}>
     */
    private function shrinkDepressionNormalTo8(): array
    {
        return $this->formRows(['Depression:Normal' => [0, 8], 'Depression:Mild' => [9, 13]]);
    }
}
