<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * UserSeeder creates the default Psychometrician only when missing: running
 * the seeders again never resets a changed password or re-activates the
 * account. ADMIN_DEFAULT_PASSWORD is set or cleared here, never read from .env.
 */
class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    private ?string $originalPassword = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalPassword = $_ENV['ADMIN_DEFAULT_PASSWORD'] ?? $_SERVER['ADMIN_DEFAULT_PASSWORD'] ?? (getenv('ADMIN_DEFAULT_PASSWORD') ?: null);
        $this->seed(RoleSeeder::class);
    }

    protected function tearDown(): void
    {
        $this->setAdminPassword($this->originalPassword);
        parent::tearDown();
    }

    public function test_first_run_creates_an_active_psychometrician(): void
    {
        $this->setAdminPassword('First-Run-Pass-123');
        $this->seed(UserSeeder::class);

        $user = User::query()->where('email', UserSeeder::EMAIL)->sole();
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasRole('psychometrician'));
        $this->assertTrue(Hash::check('First-Run-Pass-123', $user->password));
    }

    public function test_seeding_again_keeps_a_changed_password_and_a_deactivation(): void
    {
        $this->setAdminPassword('First-Run-Pass-123');
        $this->seed(UserSeeder::class);
        $user = User::query()->where('email', UserSeeder::EMAIL)->sole();
        $user->forceFill(['password' => Hash::make('Changed-After-Go-Live-1'), 'is_active' => false])->save();
        $hash = $user->fresh()->password;

        $this->setAdminPassword('A-Different-Default-9');
        $this->seed(UserSeeder::class);
        $this->setAdminPassword(null);
        $this->seed(UserSeeder::class); // no password needed once the account exists

        $after = $user->fresh();
        $this->assertSame($hash, $after->password);
        $this->assertFalse($after->is_active);
        $this->assertSame(1, User::query()->where('email', UserSeeder::EMAIL)->count());
    }

    public function test_a_missing_password_on_first_creation_is_a_clear_error(): void
    {
        $this->setAdminPassword(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ADMIN_DEFAULT_PASSWORD is not set');

        $this->seed(UserSeeder::class);
    }

    private function setAdminPassword(?string $value): void
    {
        if ($value === null) {
            unset($_ENV['ADMIN_DEFAULT_PASSWORD'], $_SERVER['ADMIN_DEFAULT_PASSWORD']);
            putenv('ADMIN_DEFAULT_PASSWORD');

            return;
        }

        $_ENV['ADMIN_DEFAULT_PASSWORD'] = $_SERVER['ADMIN_DEFAULT_PASSWORD'] = $value;
        putenv("ADMIN_DEFAULT_PASSWORD={$value}");
    }
}
