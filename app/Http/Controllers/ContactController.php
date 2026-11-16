<?php

namespace App\Http\Controllers;

use App\Services\PageContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function __construct(private readonly PageContentService $pages) {}

    public function show(Request $request): View
    {
        $contact = $this->pages->contact();
        $enquiry = (string) $request->query('enquiry', '');

        return view('contact.index', [
            'contact' => $contact,
            'selectedEnquiry' => array_key_exists($enquiry, $contact['enquiry_types']) ? $enquiry : null,
        ]);
    }

    /**
     * Validates the enquiry. Sending is NOT implemented in this stage, so the
     * customer is told plainly that online enquiries are not active yet.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'enquiry' => ['required', Rule::in(array_keys($this->pages->contact()['enquiry_types']))],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'phone.regex' => 'Please enter a valid phone number.',
        ]);

        return back()->withInput()->with('notice', 'Thank you. Online enquiries are not active yet, so this message has not been sent. Please contact our team directly.');
    }
}
