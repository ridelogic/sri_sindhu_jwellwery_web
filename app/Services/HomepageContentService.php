<?php

namespace App\Services;

use App\Support\Money;
use App\Support\SiteContact;

/**
 * Supplies all content shown on the public homepage.
 *
 * Every method currently returns PLACEHOLDER data. The return shapes are the
 * contract the Blade views depend on, so each method can later be swapped to
 * read from the database (gold_rates, plans, settings, media tables) or the
 * REST API without touching any view markup.
 *
 * Gold rates, plans and FAQs are delegated to their own services so the same
 * data is shared with the inner pages instead of being duplicated.
 *
 * Image paths are relative to /public and are temporary stock photography —
 * replace them with Sri Sindhu Jewellery's own photography by changing the
 * paths here (or uploading files with the same names).
 */
class HomepageContentService
{
    public function __construct(
        private readonly GoldRateService $goldRateService,
        private readonly GoldSavingPlanService $planService,
        private readonly FaqService $faqService,
    ) {}

    /**
     * Cinematic hero.
     *
     * Future source: admin-managed homepage settings / banners table.
     */
    public function hero(): array
    {
        return [
            'eyebrow' => 'Sri Sindhu Jewellery',
            'title_lines' => ['A Legacy of Gold.', 'A Future of Savings.'],
            'text' => 'Discover exquisite jewellery and build your gold savings with a trusted monthly saving plan designed for your future.',
            'primary_cta' => ['label' => 'Explore Gold Saving', 'href' => route('gold.saving')],
            'secondary_cta' => ['label' => 'View Collection', 'href' => route('home').'#collections'],
            'image' => [
                'desktop' => 'images/home/hero-gold-necklace.jpg',
                'mobile' => 'images/home/hero-gold-necklace-mobile.jpg',
                'alt' => 'Traditional South Indian gold necklace with ruby stones and matching earrings on black',
            ],
        ];
    }

    /**
     * Today's gold rates (delegated to GoldRateService).
     */
    public function goldRates(): array
    {
        return $this->goldRateService->summary();
    }

    /**
     * Gold saving scheme section; plans come from GoldSavingPlanService.
     */
    public function goldSaving(): array
    {
        return [
            'eyebrow' => 'Gold Saving Scheme',
            'title' => 'Turn Your Monthly Savings Into Gold',
            'title_emphasis' => 'Into Gold',
            'text' => "A simple and disciplined way to save towards the jewellery you've always wanted.",
            'plans' => $this->planService->all(),
            'lowest_monthly_amount' => $this->planService->lowestMonthlyAmount(),
            'all_plans_url' => route('gold.saving'),
            'cta' => ['label' => 'Start Your Gold Journey', 'href' => route('register')],
            'note' => 'Plan values shown are indicative. Scheme terms and conditions apply.',
        ];
    }

    /**
     * Magazine-style jewellery editorials.
     *
     * Future source: collections / banners managed from the admin panel.
     */
    public function editorials(): array
    {
        return [
            'bridal' => [
                'eyebrow' => 'Bridal Collection',
                'title' => 'Crafted to Be Remembered',
                'title_emphasis' => 'Remembered',
                'text' => 'Temple-inspired necklaces, heirloom bangles and radiant maang tikkas — bridal gold made to be treasured for generations.',
                'cta' => ['label' => 'Discover Bridal', 'href' => route('contact', ['enquiry' => 'bridal'])],
                'image' => 'images/home/editorial-bridal.jpg',
                'alt' => 'Bride in a silk saree wearing traditional gold temple jewellery',
                'detail_image' => 'images/home/editorial-bridal-detail.jpg',
                'detail_alt' => 'Gold maang tikka and jhumka earrings',
                'highlights' => ['Temple gold', 'Bridal sets', 'Heirloom bangles'],
            ],
            'everyday' => [
                'eyebrow' => 'Daily Wear',
                'title' => 'Everyday Gold',
                'title_emphasis' => 'Gold',
                'text' => 'Timeless pieces designed to become part of your everyday story.',
                'cta' => ['label' => 'Explore Collection', 'href' => route('contact', ['enquiry' => 'jewellery'])],
                'image' => 'images/home/editorial-everyday.jpg',
                'alt' => 'Woman wearing a gold link chain necklace and matching bracelet',
                'detail_image' => 'images/home/editorial-everyday-detail.jpg',
                'detail_alt' => 'Delicate gold pendant necklace and sculpted gold ring',
                'categories' => ['Chains', 'Pendants', 'Rings', 'Bracelets', 'Earrings'],
            ],
        ];
    }

    /**
     * Brand pillars for the "Why Sri Sindhu" section.
     */
    public function pillars(): array
    {
        return [
            'eyebrow' => 'Why Sri Sindhu',
            'title' => 'A Promise Set in Gold',
            'title_emphasis' => 'Set in Gold',
            'text' => 'Every piece we sell and every rupee you save with us is guided by four simple principles.',
            'cta' => ['label' => 'About Sri Sindhu', 'href' => route('about')],
            'items' => [
                ['title' => 'Purity', 'text' => 'Gold you can trust, with clear purity details on every piece.'],
                ['title' => 'Trust', 'text' => 'Honest pricing, clear explanations and lasting relationships.'],
                ['title' => 'Security', 'text' => 'Protected payments and a receipt for every installment you make.'],
                ['title' => 'Service', 'text' => 'Personal guidance from enrolment to the day you redeem.'],
            ],
        ];
    }

    /**
     * Gold saving journey steps.
     */
    public function savingSteps(): array
    {
        return [
            'eyebrow' => 'How Gold Saving Works',
            'title' => 'Six Steps to Your Gold',
            'title_emphasis' => 'Your Gold',
            'cta' => ['label' => 'Start Saving Today', 'href' => route('gold.saving')],
            'items' => [
                ['title' => 'Join', 'text' => 'Enrol with Sri Sindhu Jewellery.'],
                ['title' => 'Choose Your Plan', 'text' => 'Pick the monthly amount that suits you.'],
                ['title' => 'Pay Monthly', 'text' => 'Pay your installment every month.'],
                ['title' => 'Track Your Gold', 'text' => 'Follow every payment and your progress.'],
                ['title' => 'Complete', 'text' => 'Finish your installments on schedule.'],
                ['title' => 'Redeem', 'text' => 'Choose your jewellery as per scheme terms.'],
            ],
        ];
    }

    /**
     * Customer app preview (illustrative UI only).
     *
     * Future source: the mobile app / customer API.
     */
    public function appPreview(): array
    {
        return [
            'title' => 'Your Gold. In Your Hands.',
            'title_emphasis' => 'In Your Hands.',
            'text' => 'Track your plans, payments, gold savings and daily gold rates — wherever you are.',
            'features' => ["Today's gold rate", 'Payment history & receipts', 'Savings progress'],
            // No app store listing exists yet: href stays null and the button renders as "Coming Soon".
            'cta' => ['label' => 'Download the App', 'href' => SiteContact::appUrl('android') ?? SiteContact::appUrl('ios'), 'unavailable_label' => 'App Coming Soon'],
            'secondary_cta' => ['label' => 'Login to Your Account', 'href' => route('login')],
            'screen' => [
                'customer' => 'Welcome back',
                'rate_label' => "Today's Gold Rate",
                'rate' => $this->headlineRate(),
                'plan' => 'Gold Plus',
                'paid' => '₹32,000',
                'grams' => '4.72g',
                'progress' => 64,
                'installments' => '7 of 11',
                'next_payment' => '₹2,000',
                'next_due' => '05 Nov',
            ],
        ];
    }

    /**
     * Brand trust statistics.
     *
     * Future source: settings managed from the Laravel admin panel.
     *
     * @return list<array{value: string, label: string}>
     */
    public function stats(): array
    {
        return [
            ['value' => '10+', 'label' => 'Years of Trust'],
            ['value' => '1,000+', 'label' => 'Customers'],
            ['value' => '3+', 'label' => 'Saving Plans'],
            ['value' => '100%', 'label' => 'Secure Payments'],
        ];
    }

    /**
     * Highlighted questions (delegated to FaqService).
     *
     * @return list<array{question: string, answer: string}>
     */
    public function faqs(): array
    {
        return $this->faqService->featured();
    }

    /**
     * "22K ₹7,000/g" style label for the app preview.
     */
    private function headlineRate(): string
    {
        $rate = $this->goldRateService->current()[0];

        return $rate['purity'].' '.Money::inr($rate['price_per_gram']).'/g';
    }

    /**
     * Closing call to action.
     */
    public function finalCta(): array
    {
        return [
            'title' => 'Your Gold Story Starts Here.',
            'title_emphasis' => 'Starts Here.',
            'text' => 'Save today. Celebrate tomorrow.',
            'primary_cta' => ['label' => 'Join Gold Saving Scheme', 'href' => route('gold.saving')],
            'secondary_cta' => ['label' => 'Explore Jewellery', 'href' => route('home').'#collections'],
            'image' => 'images/home/cta-gold-earrings.jpg',
            'alt' => 'Gold flower earrings set with sparkling stones on dark velvet',
        ];
    }
}
