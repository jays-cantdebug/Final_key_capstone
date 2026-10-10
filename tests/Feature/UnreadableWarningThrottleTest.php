<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DassResult;
use App\Support\UnreadableEncryptedValues;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * The "could not be decrypted" warning: every unreadable column of a record
 * is logged by the first request that meets it, then that record is quiet
 * for an hour across requests (a fresh scoped instance = a new request).
 */
class UnreadableWarningThrottleTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array<string, mixed>> */
    private array $warnings = [];

    protected function setUp(): void
    {
        parent::setUp();
        Log::listen(function ($message): void {
            if ($message->level === 'warning') {
                $this->warnings[] = $message->context;
            }
        });
    }

    public function test_a_record_is_logged_once_per_hour_across_requests_with_every_column_the_first_time(): void
    {
        $result = (new DassResult)->forceFill(['id' => 7]);
        $other = (new DassResult)->forceFill(['id' => 8]);

        $this->request(fn (UnreadableEncryptedValues $values) => [
            $values->record($result, 'depression_final_score'),
            $values->record($result, 'anxiety_final_score'),
            $values->record($result, 'depression_final_score'),
        ]);
        $this->assertSame(['depression_final_score', 'anxiety_final_score'], array_column($this->warnings, 'column'));

        // A later request within the hour: nothing for that record, another record still logged.
        $this->warnings = [];
        $this->travel(59)->minutes();
        $this->request(fn (UnreadableEncryptedValues $values) => [
            $values->record($result, 'depression_final_score'),
            $values->record($result, 'stress_final_score'),
            $values->record($other, 'stress_final_score'),
        ]);
        $this->assertSame([8], array_column($this->warnings, 'id'));

        // After the hour: logged again.
        $this->warnings = [];
        $this->travel(2)->minutes();
        $this->request(fn (UnreadableEncryptedValues $values) => $values->record($result, 'depression_final_score'));
        $this->assertSame([7], array_column($this->warnings, 'id'));
        $this->assertSame(['model', 'id', 'column'], array_keys($this->warnings[0]));
    }

    private function request(callable $work): void
    {
        $this->app->forgetScopedInstances();
        $work($this->app->make(UnreadableEncryptedValues::class));
    }
}
