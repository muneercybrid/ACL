<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The feedback endpoint once referenced a model class that did not exist, so
 * every Like/Dislike carrying a conversation_id failed with a 500.
 */
class AcliFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => $name, 'email' => strtolower($name) . '-' . uniqid() . '@acl.test',
            'password' => bcrypt('secret1234'), 'status' => 'active',
            'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::find($id);
    }

    private function makeConversation(User $owner): int
    {
        return DB::table('acli_conversations')->insertGetId([
            'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_owner_can_leave_feedback_on_their_conversation(): void
    {
        $owner = $this->makeUser('Owner');
        $conversationId = $this->makeConversation($owner);

        $this->actingAs($owner)
            ->postJson('/acli/chat/feedback', [
                'conversation_id' => $conversationId,
                'content' => 'Helpful answer',
                'verdict' => 'like',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('acli_message_feedback', [
            'user_id' => $owner->id,
            'conversation_id' => $conversationId,
            'verdict' => 'like',
        ]);
    }

    public function test_feedback_on_someone_elses_conversation_is_refused(): void
    {
        $owner = $this->makeUser('Owner');
        $intruder = $this->makeUser('Intruder');
        $conversationId = $this->makeConversation($owner);

        $this->actingAs($intruder)
            ->postJson('/acli/chat/feedback', [
                'conversation_id' => $conversationId,
                'verdict' => 'dislike',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('acli_message_feedback', [
            'conversation_id' => $conversationId,
        ]);
    }
}
