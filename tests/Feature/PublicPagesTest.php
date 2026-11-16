<?php

namespace Tests\Feature;

use App\Services\GoldSavingPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Route name => [expected <title>, expected H1 text].
     */
    public static function pageProvider(): array
    {
        return [
            'home' => ['home', [], 'Sri Sindhu Jewellery | Gold Saving Scheme', 'A Legacy of Gold.'],
            'about' => ['about', [], 'About Sri Sindhu Jewellery', 'Chosen With Care.'],
            'gold rate' => ['gold.rate', [], 'Gold Rate Today | Sri Sindhu Jewellery', 'Gold Rate'],
            'gold saving' => ['gold.saving', [], 'Gold Saving Scheme | Sri Sindhu Jewellery', 'Turn Every Contribution'],
            'plan' => ['gold.saving.show', ['gold-plus'], 'Gold Plus Plan | Gold Saving Scheme | Sri Sindhu Jewellery', 'Gold Plus.'],
            'faq' => ['faq', [], 'Frequently Asked Questions | Sri Sindhu Jewellery', 'Questions,'],
            'contact' => ['contact', [], 'Contact Sri Sindhu Jewellery', "We'd Love to"],
            'login' => ['login', [], 'Login | Sri Sindhu Jewellery', 'Welcome'],
            'register' => ['register', [], 'Create an Account | Sri Sindhu Jewellery', 'Create Your'],
        ];
    }

    #[DataProvider('pageProvider')]
    public function test_page_loads_with_unique_seo_metadata(string $route, array $params, string $title, string $heading): void
    {
        $response = $this->get(route($route, $params));

        $response->assertOk()
            ->assertSee('<title>'.e($title).'</title>', false)
            ->assertSee('<meta name="description"', false)
            ->assertSee('<meta property="og:title" content="'.e($title).'"', false)
            ->assertSee('<meta property="og:description"', false)
            ->assertSee('<meta property="og:type" content="website"', false)
            ->assertSee($heading);

        $this->assertSame(1, substr_count($response->getContent(), '<h1'), "Exactly one H1 expected on [$route].");
    }

    public function test_every_page_has_a_unique_title_and_description(): void
    {
        $titles = [];
        $descriptions = [];

        foreach (self::pageProvider() as [$route, $params]) {
            $html = $this->get(route($route, $params))->getContent();
            preg_match('/<title>(.*?)<\/title>/', $html, $title);
            preg_match('/<meta name="description" content="(.*?)"/', $html, $description);
            $titles[] = $title[1];
            $descriptions[] = $description[1];
        }

        $this->assertSame($titles, array_unique($titles));
        $this->assertSame($descriptions, array_unique($descriptions));
    }

    public function test_all_named_public_routes_are_registered(): void
    {
        foreach (['home', 'about', 'gold.rate', 'gold.saving', 'gold.saving.show', 'faq', 'contact', 'contact.store', 'login', 'login.store', 'register', 'register.store'] as $name) {
            $this->assertTrue(Route::has($name), "Route [$name] is missing.");
        }
    }

    public function test_every_plan_has_a_detail_page(): void
    {
        foreach (app(GoldSavingPlanService::class)->all() as $plan) {
            $this->get($plan['url'])
                ->assertOk()
                ->assertSee($plan['name'])
                ->assertSee('Monthly Contribution')
                ->assertSee('Total Contribution')
                ->assertSee('How Payment')
                ->assertSee('Important Notes');
        }
    }

    public function test_unknown_plan_returns_branded_404(): void
    {
        $this->get('/gold-saving/not-a-plan')
            ->assertNotFound()
            ->assertSee('Moved On.')->assertSee('Back to Home');
    }

    public function test_no_page_links_to_a_bare_hash(): void
    {
        foreach (self::pageProvider() as $name => [$route, $params]) {
            $html = $this->get(route($route, $params))->getContent();
            $this->assertDoesNotMatchRegularExpression('/href="#"/', $html, "Page [$name] contains href=\"#\".");
        }
    }

    public function test_internal_links_resolve(): void
    {
        $base = rtrim(config('app.url'), '/');
        $checked = [];

        foreach (self::pageProvider() as $name => [$route, $params]) {
            $html = $this->get(route($route, $params))->getContent();
            preg_match_all('/href="([^"]+)"/', $html, $matches);

            foreach ($matches[1] as $href) {
                $href = html_entity_decode($href);
                if (! str_starts_with($href, $base)) {
                    continue; // external, mailto, tel or in-page anchors
                }

                $path = parse_url($href, PHP_URL_PATH) ?: '/';
                if (str_starts_with($path, '/build/') || str_contains($path, '.') || isset($checked[$path])) {
                    continue; // assets and already-checked pages
                }

                $checked[$path] = true;
                $query = parse_url($href, PHP_URL_QUERY);
                $this->get($path.($query ? '?'.$query : ''))->assertOk();
            }
        }

        $this->assertNotEmpty($checked);
    }

    public function test_header_and_footer_link_to_every_public_page(): void
    {
        $html = $this->get(route('home'))->getContent();

        foreach (['about', 'gold.rate', 'gold.saving', 'faq', 'contact', 'login', 'register'] as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $html, "No link to [$route].");
        }

        foreach (app(GoldSavingPlanService::class)->all() as $plan) {
            $this->assertStringContainsString('href="'.$plan['url'].'"', $html);
        }
    }

    public function test_unconfigured_contact_details_show_placeholders_not_fake_data(): void
    {
        config([
            'site.contact.phone' => null,
            'site.contact.email' => null,
            'site.contact.whatsapp' => null,
            'site.contact.address' => null,
            'site.contact.map_embed_url' => null,
        ]);

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('To be announced')
            ->assertSee('data-map-placeholder', false)
            ->assertSee('data-whatsapp-unavailable', false)
            ->assertDontSee('tel:', false)
            ->assertDontSee('mailto:', false)
            ->assertDontSee('98765');
    }

    public function test_configured_contact_details_are_displayed(): void
    {
        config([
            'site.contact.phone' => '+91 90000 00000',
            'site.contact.email' => 'hello@example.com',
            'site.contact.whatsapp' => '919000000000',
        ]);

        $this->get(route('contact'))
            ->assertSee('href="tel:+919000000000"', false)
            ->assertSee('mailto:hello@example.com', false)
            ->assertSee('https://wa.me/919000000000', false);
    }

    public function test_contact_page_preselects_enquiry_type(): void
    {
        $this->get(route('contact', ['enquiry' => 'bridal']))
            ->assertSee('<option value="bridal" selected', false);
    }

    public function test_gold_rate_page_shows_rate_details_and_sample_notice(): void
    {
        $this->get(route('gold.rate'))
            ->assertSee('22K Gold')
            ->assertSee('24K Gold')
            ->assertSee('₹7,000')
            ->assertSee('₹70,000')   // 22K per 10 gram
            ->assertSee('₹76,500')   // 24K per 10 gram
            ->assertSee('Previous Rate')
            ->assertSee('Last updated')
            ->assertSee('Rate History')
            ->assertSee('data-rate-chart', false)
            ->assertSee('data-sample-notice', false)
            ->assertSee(route('gold.saving'), false);
    }

    public function test_register_page_preselects_the_requested_plan(): void
    {
        \App\Models\Plan::factory()->create(['name' => 'Gold Basic', 'slug' => 'gold-basic']);
        \App\Models\Plan::factory()->create(['name' => 'Gold Premium', 'slug' => 'gold-premium']);

        $this->get(route('register', ['plan' => 'gold-premium']))
            ->assertSee('Gold Premium')
            ->assertSee('value="gold-premium" class="mt-1.5 size-4 shrink-0 accent-champagne" checked', false)
            ->assertDontSee('value="gold-basic" class="mt-1.5 size-4 shrink-0 accent-champagne" checked', false);
    }

    public function test_app_download_shows_coming_soon_without_store_url(): void
    {
        config(['site.app.android' => null, 'site.app.ios' => null]);

        $this->get(route('home'))
            ->assertSee('App Coming Soon')
            ->assertSee('data-app-coming-soon', false);
    }
}
