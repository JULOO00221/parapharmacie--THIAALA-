<?php

namespace Tests\Feature\Api\V1;

use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_all_tags(): void
    {
        Tag::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/tags');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }
}
