<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The contact form is UI-only: it validates input and then tells the visitor
 * honestly that online enquiries are not active yet.
 */
class PublicFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_validates_required_fields(): void
    {
        $this->from(route('contact'))
            ->post(route('contact.store'), [])
            ->assertRedirect(route('contact'))
            ->assertSessionHasErrors(['name', 'phone', 'enquiry', 'message']);
    }

    public function test_contact_form_rejects_unknown_enquiry_type(): void
    {
        $this->post(route('contact.store'), [
            'name' => 'Asha', 'phone' => '9000000000', 'enquiry' => 'nonsense', 'message' => 'Hello there, a question.',
        ])->assertSessionHasErrors('enquiry');
    }

    public function test_valid_contact_enquiry_is_not_claimed_as_sent(): void
    {
        $this->from(route('contact'))
            ->post(route('contact.store'), [
                'name' => 'Asha', 'phone' => '+91 90000 00000', 'enquiry' => 'gold-saving', 'message' => 'I would like to know about Gold Plus.',
            ])
            ->assertRedirect(route('contact'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('notice', fn ($notice) => str_contains($notice, 'has not been sent'));
    }

    // Customer login/registration are real since Stage 4 — see tests/Feature/Customer/*.
}
