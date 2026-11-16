<?php

namespace Tests\Feature;

use App\Services\HomepageContentService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_with_seo_metadata(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<title>Sri Sindhu Jewellery | Gold Saving Scheme</title>', false)
            ->assertSee('name="description"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('property="og:image"', false)
            ->assertSee('<h1 id="hero-heading"', false);
    }

    public function test_home_page_renders_all_sections_in_order(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder([
                'A Legacy of Gold.',
                'A Future of Savings.',
                'Explore Gold Saving',
                "Today's Gold Rate",
                'View Gold Rate History',
                'Turn Your Monthly Savings',
                'Start Your Gold Journey',
                'Crafted to Be',
                'Discover Bridal',
                'Everyday',
                'Explore Collection',
                'Purity',
                'Service',
                'How Gold Saving Works',
                'Start Saving Today',
                'In Your Hands.',
                'App Coming Soon',
                'Years of Trust',
                'Before You',
                'Your Gold Story',
                'Join Gold Saving Scheme',
                'All Rights Reserved.',
            ]);
    }

    public function test_home_page_shows_content_from_the_content_service(): void
    {
        $this->get('/')
            ->assertSee('22K Gold')
            ->assertSee('₹7,000')
            ->assertSee('₹7,650')
            ->assertSee('Gold Basic')
            ->assertSee('Gold Plus')
            ->assertSee('Gold Premium')
            ->assertSee('₹22,000')   // Gold Plus total (₹2,000 × 11)
            ->assertSee('4.72g')
            ->assertSee('How can I redeem my scheme?');
    }

    public function test_home_page_reflects_changes_to_the_content_service(): void
    {
        $this->mock(HomepageContentService::class, function ($mock) {
            $real = app(HomepageContentService::class);

            $rates = $real->goldRates();
            $rates['rates'][0]['price_per_gram'] = 7123;

            $mock->shouldReceive('goldRates')->andReturn($rates);
            $mock->shouldReceive('stats')->andReturn([['value' => '25+', 'label' => 'Years of Craft']]);

            foreach (['hero', 'goldSaving', 'editorials', 'pillars', 'savingSteps', 'appPreview', 'faqs', 'finalCta'] as $method) {
                $mock->shouldReceive($method)->andReturn($real->{$method}());
            }
        });

        $this->get('/')
            ->assertOk()
            ->assertSee('₹7,123')
            ->assertSee('Years of Craft')
            ->assertDontSee('Years of Trust');
    }

    public function test_every_homepage_image_exists(): void
    {
        $content = app(HomepageContentService::class);
        $editorials = $content->editorials();

        $images = [
            ...array_values(array_diff_key($content->hero()['image'], ['alt' => true])),
            $content->goldRates()['background'],
            $editorials['bridal']['image'],
            $editorials['bridal']['detail_image'],
            $editorials['everyday']['image'],
            $editorials['everyday']['detail_image'],
            $content->finalCta()['image'],
        ];

        foreach ($images as $image) {
            $this->assertFileExists(public_path($image));
        }
    }

    public function test_money_formats_using_indian_digit_grouping(): void
    {
        $this->assertSame('₹1,000', Money::inr(1000));
        $this->assertSame('₹55,000', Money::inr(55000));
        $this->assertSame('₹1,00,000', Money::inr(100000));
        $this->assertSame('₹12,34,56,789', Money::inr(123456789));
        $this->assertSame('750', Money::inr(750, withSymbol: false));
    }
}
