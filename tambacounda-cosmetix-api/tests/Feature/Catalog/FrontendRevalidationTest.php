<?php

namespace Tests\Feature\Catalog;

use App\Jobs\RevalidateFrontendCatalog;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\Store;
use App\Models\Tag;
use App\Services\FrontendCacheRevalidator;
use App\Services\ProductService;
use Illuminate\Bus\DebounceLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\Attributes\DebounceFor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use ReflectionClass;
use Tests\TestCase;

class FrontendRevalidationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-secret-with-at-least-thirty-two-chars';

    private function configureFrontend(): void
    {
        config([
            'services.frontend.url' => 'https://thiaala.example/',
            'services.frontend.revalidate_secret' => self::SECRET,
        ]);
    }

    /**
     * Runs $action and asserts it scheduled at least one revalidation. Exact
     * counts don't matter (restore() fires both `saved` and `restored`): the
     * job is debounced.
     */
    private function assertSchedulesRevalidation(string $label, callable $action): mixed
    {
        $before = Queue::pushed(RevalidateFrontendCatalog::class)->count();
        $result = $action();

        $this->assertGreaterThan($before, Queue::pushed(RevalidateFrontendCatalog::class)->count(), "No revalidation scheduled on: {$label}");

        return $result;
    }

    public function test_every_catalogue_write_schedules_a_revalidation(): void
    {
        Queue::fake();

        $category = $this->assertSchedulesRevalidation('category created', fn () => ProductCategory::factory()->create());
        $brand = $this->assertSchedulesRevalidation('brand created', fn () => Brand::factory()->create());
        $tag = $this->assertSchedulesRevalidation('tag created', fn () => Tag::factory()->create());
        $product = $this->assertSchedulesRevalidation(
            'product created',
            fn () => Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand->id]),
        );
        $store = Store::factory()->create();

        $this->assertSchedulesRevalidation('product updated', fn () => $product->update(['price' => 9900]));
        $this->assertSchedulesRevalidation('product deleted', fn () => $product->delete());
        $this->assertSchedulesRevalidation('product restored', fn () => $product->restore());
        $this->assertSchedulesRevalidation('image added', fn () => ProductImage::create([
            'product_id' => $product->id, 'path' => 'products/x.webp', 'sort_order' => 0, 'is_primary' => true,
        ]));
        $this->assertSchedulesRevalidation('stock set', fn () => (new ProductService)->setInitialStock($product, $store, 5));
        $this->assertSchedulesRevalidation('category renamed', fn () => $category->update(['name' => 'Soins du visage']));
        $this->assertSchedulesRevalidation('brand deactivated', fn () => $brand->update(['is_active' => false]));
        $this->assertSchedulesRevalidation('tag renamed', fn () => $tag->update(['name' => 'Bio']));
    }

    public function test_non_catalogue_writes_do_not_schedule_anything(): void
    {
        Queue::fake();

        Store::factory()->create();

        Queue::assertNotPushed(RevalidateFrontendCatalog::class);
    }

    public function test_the_job_is_debounced_so_a_burst_of_changes_makes_a_single_call(): void
    {
        $attribute = (new ReflectionClass(RevalidateFrontendCatalog::class))->getAttributes(DebounceFor::class)[0]->newInstance();
        $this->assertSame(10, $attribute->debounceFor);
        $this->assertSame(60, $attribute->maxWait);

        Queue::fake();
        $category = ProductCategory::factory()->create();
        Product::factory()->count(5)->create(['category_id' => $category->id]);

        // Every write queued a job, but only the most recent one still owns
        // the debounce lock: the others are dropped when the worker picks
        // them up, so the frontend is called once.
        $jobs = Queue::pushed(RevalidateFrontendCatalog::class);
        $currentOwner = (new DebounceLock(app('cache.store')))->getCurrentOwner($jobs->last());

        $this->assertSame(6, $jobs->count());
        $this->assertSame([$jobs->last()->debounceOwner], $jobs->filter(fn ($job) => $job->debounceOwner === $currentOwner)->map->debounceOwner->values()->all());
    }

    public function test_the_job_calls_the_frontend_with_the_secret(): void
    {
        $this->configureFrontend();
        Http::fake(['thiaala.example/*' => Http::response(['revalidated' => true])]);

        (new RevalidateFrontendCatalog)->handle(app(FrontendCacheRevalidator::class));

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://thiaala.example/api/revalidate'
            && $request->hasHeader('Authorization', 'Bearer '.self::SECRET));
    }

    public function test_the_job_does_nothing_without_a_secret(): void
    {
        config(['services.frontend.revalidate_secret' => null]);
        Http::fake();

        (new RevalidateFrontendCatalog)->handle(app(FrontendCacheRevalidator::class));

        Http::assertNothingSent();
    }

    public function test_the_job_fails_on_an_error_so_the_queue_retries_it(): void
    {
        $this->configureFrontend();
        Http::fake(['thiaala.example/*' => Http::response(['message' => 'Non autorisé.'], 401)]);

        $this->expectException(RequestException::class);

        (new RevalidateFrontendCatalog)->handle(app(FrontendCacheRevalidator::class));
    }

    public function test_the_deploy_command_revalidates_synchronously(): void
    {
        $this->configureFrontend();
        Http::fake(['thiaala.example/*' => Http::response(['revalidated' => true])]);

        $this->artisan('catalog:revalidate-frontend')
            ->expectsOutputToContain('Cache du catalogue vidé')
            ->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_the_deploy_command_reports_failures(): void
    {
        config(['services.frontend.revalidate_secret' => null]);
        $this->artisan('catalog:revalidate-frontend')->assertFailed();

        $this->configureFrontend();
        Http::fake(['thiaala.example/*' => Http::response([], 500)]);
        $this->artisan('catalog:revalidate-frontend')->assertFailed();
    }
}
