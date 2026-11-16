<?php

namespace App\Services;

/**
 * Frequently asked questions, grouped by category.
 *
 * TEMPORARY: managed here until the faqs table / admin panel exists.
 * Answers stay deliberately general — detailed policy answers must come
 * from the final scheme terms.
 */
class FaqService
{
    private const CATEGORIES = [
        'gold-saving' => [
            'title' => 'Gold Saving',
            'items' => [
                ['question' => 'What is the Sri Sindhu Jewellery Gold Saving Scheme?', 'answer' => 'A monthly saving plan that helps you set aside a fixed amount every month and use your accumulated savings towards gold jewellery at the end of the scheme.', 'featured' => true],
                ['question' => 'How can I join a scheme?', 'answer' => 'Choose the plan that suits you and complete enrolment with our team. Online registration is being prepared — until then, please contact us or visit our store.', 'featured' => true],
                ['question' => 'Can I have more than one scheme?', 'answer' => 'Yes. You can join more than one plan and each plan is tracked separately.', 'featured' => true],
                ['question' => 'How can I redeem my scheme?', 'answer' => 'Once all installments are complete, you can redeem your savings towards jewellery at our store, as per the scheme terms.', 'featured' => true],
                ['question' => 'Where can I read the full scheme terms?', 'answer' => 'The complete scheme terms will be shared with you in writing at enrolment. Our team is happy to explain them before you join.'],
            ],
        ],
        'payments' => [
            'title' => 'Payments',
            'items' => [
                ['question' => 'How can I pay my monthly installment?', 'answer' => 'Installments can be paid at our store. Secure online payments will be available once customer accounts launch.', 'featured' => true],
                ['question' => 'Will I receive a receipt for my payment?', 'answer' => 'Yes. A receipt is issued for every installment you pay.'],
                ['question' => 'Where can I see my payment history?', 'answer' => 'Your payment history will be available in your online account once it launches. Until then, our team can share your payment details on request.', 'featured' => true],
            ],
        ],
        'gold-rate' => [
            'title' => 'Gold Rate',
            'items' => [
                ['question' => 'Can I track the daily gold rate?', 'answer' => "Yes. Today's 22K and 24K gold rates are published on our Gold Rate page.", 'featured' => true],
                ['question' => 'What is the difference between 22K and 24K gold?', 'answer' => '24K gold is 99.9% pure and is commonly used for coins and bars. 22K gold is about 91.6% pure — blended with other metals for the strength needed in jewellery.'],
                ['question' => 'Why does the gold rate change every day?', 'answer' => 'Gold rates move with international gold prices, currency exchange rates, local demand and applicable duties and taxes.'],
            ],
        ],
        'jewellery' => [
            'title' => 'Jewellery',
            'items' => [
                ['question' => 'Can I see the jewellery collection in person?', 'answer' => 'Yes. You are welcome to visit our store to view the collection. Contact us to plan your visit.'],
                ['question' => 'How is the price of jewellery calculated?', 'answer' => "Jewellery is generally priced on the day's gold rate, the weight of the piece, making charges and applicable taxes. Our team will explain the breakdown for any piece."],
            ],
        ],
        'account' => [
            'title' => 'Account',
            'items' => [
                ['question' => 'Do I need an online account?', 'answer' => 'Online accounts are launching soon and will let you follow your plans and payments. You do not need one to enquire about a plan.'],
                ['question' => 'I have forgotten my password. What should I do?', 'answer' => 'Online accounts are not yet active. Once they launch, you will be able to reset your password from the login page.'],
            ],
        ],
        'general' => [
            'title' => 'General',
            'items' => [
                ['question' => 'How can I contact Sri Sindhu Jewellery?', 'answer' => 'Please use the details on our Contact page, or send us a message using the enquiry form.'],
                ['question' => 'What are your store opening hours?', 'answer' => 'Our store hours are listed on the Contact page. Please call ahead before visiting on public holidays.'],
            ],
        ],
    ];

    /**
     * @return array<string, array{title: string, items: list<array{question: string, answer: string}>}>
     */
    public function categories(?array $only = null): array
    {
        return $only === null ? self::CATEGORIES : array_intersect_key(self::CATEGORIES, array_flip($only));
    }

    /**
     * Highlighted questions for the homepage.
     *
     * @return list<array{question: string, answer: string}>
     */
    public function featured(): array
    {
        $items = array_merge(...array_column(self::CATEGORIES, 'items'));

        return array_values(array_filter($items, fn (array $item) => $item['featured'] ?? false));
    }
}
