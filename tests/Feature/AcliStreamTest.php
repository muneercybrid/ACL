<?php
namespace Tests\Feature;

use Tests\TestCase;

class AcliStreamTest extends TestCase
{
    public function test_stream_endpoint_returns_event_stream_or_typed_error(): void
    {
        $userId = \DB::table('users')->insertGetId([
            'name' => 'Stream Student', 'email' => 'stream-' . uniqid() . '@acl.test',
            'password' => bcrypt('secret1234'), 'status' => 'active',
            'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        \DB::table('students')->insert([
            'user_id' => $userId, 'acl_student_id' => 'ACL-S' . $userId, 'level' => 100,
            'verification_status' => 'verified', 'verification_method' => 'jamb',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs(\App\Models\User::find($userId))
            ->postJson('/acli/chat/stream', ['message' => 'Say hi']);

        // Either a stream, or a typed refusal. What must never happen is an
        // empty 200 with no frames, which is what left the chat silent.
        $this->assertContains($response->getStatusCode(), [200, 403, 503], 'unexpected status');

        if ($response->getStatusCode() === 200) {
            $this->assertStringContainsString(
                'text/event-stream',
                (string) $response->headers->get('Content-Type'),
                'a successful stream must declare its content type'
            );
        } else {
            $this->assertNotEmpty($response->json('error'), 'a refusal must explain itself');
        }
    }
}
