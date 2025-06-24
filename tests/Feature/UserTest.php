<?php

namespace Tests\Feature;

use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_user_with_default_factory()
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'user_name' => Str::snake($user->display_name),
        ]);

        $this->assertEquals($user->created_at, $user->updated_at);
    }

    #[Test]
    public function it_auto_generates_user_name_if_null()
    {
        $user = User::factory()->create(['display_name' => 'Test User', 'user_name' => null]);
        $this->assertEquals('test_user', $user->user_name);
    }

    #[Test]
    public function display_name_is_required()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        User::factory()->create(['display_name' => null]);
    }

    #[Test]
    public function name_must_be_unique()
    {
        User::factory()->create(['display_name' => 'John Doe']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        User::factory()->create(['display_name' => 'John Doe']);
    }

    #[Test]
    public function it_has_sessions_relationship()
    {
        $user = User::factory()->hasSessions(3)->create();

        $this->assertCount(3, $user->sessions);
    }
}
