<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_photo_checkout_returns_download_url(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $photo = Photo::create([
            'user_id' => $user->id,
            'title' => 'Sample Free Photo',
            'category' => 'Nature',
            'description' => null,
            'path' => 'photos/original/sample-free.jpg',
            'file_type' => 'jpg',
            'watermark_path' => null,
            'is_free' => true,
            'price' => null,
            'downloads' => 0,
            'likes' => 0,
        ]);

        $response = $this->postJson('/api/checkout', [
            'photo_id' => $photo->id,
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'paid')
            ->assertJsonStructure(['download_url']);

        $this->assertSame(1, $photo->fresh()->downloads);
    }

    public function test_paid_photo_checkout_requires_payment(): void
    {
        $user = User::factory()->create(['currency' => 'USD']);
        Sanctum::actingAs($user);

        $photo = Photo::create([
            'user_id' => $user->id,
            'title' => 'Premium Photo',
            'category' => 'Art',
            'description' => null,
            'path' => 'photos/original/premium.jpg',
            'file_type' => 'jpg',
            'watermark_path' => 'photos/watermarked/premium.jpg',
            'is_free' => false,
            'price' => 25.00,
            'downloads' => 0,
            'likes' => 0,
        ]);

        $response = $this->postJson('/api/checkout', [
            'photo_id' => $photo->id,
            'return_url' => 'https://example.com/return',
            'cancel_url' => 'https://example.com/cancel',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'requires_payment')
            ->assertJsonPath('currency', 'USD')
            ->assertJsonStructure([
                'intent_id',
                'payment_url',
                'return_url',
                'cancel_url',
            ]);

        $this->assertEquals(25.00, (float) $response->json('amount'));
    }
}
