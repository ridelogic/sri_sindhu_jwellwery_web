<?php

namespace App\Services;

/**
 * Editorial copy for the public inner pages.
 *
 * Copy is deliberately conservative: no founding year, branch count,
 * customer numbers, awards, certifications or company history are claimed.
 * Replace with verified brand facts when Sri Sindhu Jewellery provides them.
 */
class PageContentService
{
    public function about(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'About Sri Sindhu',
                'title' => 'Gold, Chosen With Care.',
                'title_emphasis' => 'Chosen With Care.',
                'text' => 'A jewellery house devoted to beautiful gold, honest guidance and a simpler way to save for the moments that matter.',
                'image' => 'images/home/editorial-bridal.jpg',
                'image_position' => '50% 20%',
            ],
            'introduction' => [
                'eyebrow' => 'Sri Sindhu Jewellery',
                'title' => 'Jewellery That Becomes Part of Your Story',
                'title_emphasis' => 'Part of Your Story',
                'paragraphs' => [
                    'For many families, gold is more than an ornament. It marks weddings and festivals, new beginnings and quiet milestones — and it is passed from one generation to the next.',
                    'Sri Sindhu Jewellery exists to honour that tradition. We bring together classic Indian craftsmanship and contemporary design, and we take time to help every customer choose with confidence.',
                ],
            ],
            'story' => [
                'eyebrow' => 'Our Story',
                'title' => 'Rooted in Tradition, Made for Today',
                'title_emphasis' => 'Made for Today',
                'text' => 'Our collections draw on the temple jewellery, bridal sets and everyday gold that Indian families have treasured for centuries — reinterpreted with clean lines and lighter forms for modern life. Whether it is a first pair of earrings or a complete bridal set, each piece is selected to be worn, loved and remembered.',
                'image' => 'images/home/editorial-everyday.jpg',
                'image_alt' => 'Woman wearing a gold link chain necklace and bracelet',
            ],
            'philosophy' => [
                'eyebrow' => 'Our Philosophy',
                'quote' => 'Fine jewellery should be bought with joy and with complete clarity — never with doubt.',
                'text' => 'That belief guides how we present our jewellery, how we explain prices and how we designed our Gold Saving Scheme.',
            ],
            'values' => [
                [
                    'title' => 'Purity',
                    'text' => 'We are clear about the purity of the gold we sell, and we are always happy to explain the details of any piece before you buy.',
                ],
                [
                    'title' => 'Trust',
                    'text' => 'Transparent pricing, clear explanations and no pressure. We would rather earn a lasting relationship than a single sale.',
                ],
                [
                    'title' => 'Customer Service',
                    'text' => 'From choosing a design to planning your savings, our team is here to guide you personally — before and after your purchase.',
                ],
            ],
            'saving' => [
                'eyebrow' => 'Gold Saving Philosophy',
                'title' => 'Saving for Gold Should Feel Simple',
                'title_emphasis' => 'Feel Simple',
                'text' => 'Our Gold Saving Scheme turns a large purchase into small, steady monthly steps. You choose a plan, contribute a fixed amount each month and use your savings towards the jewellery you have been looking forward to.',
                'points' => ['Fixed monthly contributions', 'A receipt for every payment', 'Clear terms shared at enrolment'],
                'image' => 'images/home/rate-gold-bars.jpg',
                'image_alt' => 'Fine gold bars',
            ],
        ];
    }

    public function contact(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Contact',
                'title' => "We'd Love to Hear From You.",
                'title_emphasis' => 'Hear From You.',
                'text' => 'Questions about a plan, a piece of jewellery or a store visit? Send us a message and our team will help.',
                'image' => 'images/home/cta-gold-earrings.jpg',
                'image_position' => '72% 55%',
            ],
            'enquiry_types' => [
                'gold-saving' => 'Gold Saving Scheme',
                'gold-rate' => 'Gold Rate',
                'bridal' => 'Bridal Collection',
                'jewellery' => 'Jewellery',
                'account' => 'Account',
                'general' => 'General Enquiry',
            ],
        ];
    }
}
