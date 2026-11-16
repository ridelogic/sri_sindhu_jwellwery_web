<?php

namespace App\Http\Requests\Admin;

use App\Models\GoldRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation for a combined 22K + 24K gold rate update.
 * Only per-gram rates are accepted — 10-gram values are calculated server-side.
 */
class StoreGoldRateRequest extends FormRequest
{
    /** Upper sanity limit for a per-gram rate (₹). */
    public const MAX_RATE = 999999.99;

    /** How far in the future an effective time may be (minutes). */
    public const FUTURE_TOLERANCE_MINUTES = 15;

    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        $rate = ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.self::MAX_RATE];

        return [
            'rate_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'rate_time' => ['required', 'date_format:H:i'],
            'rate_22k' => $rate,
            'rate_24k' => [...$rate, 'gt:rate_22k'],
            'source' => ['nullable', 'string', 'max:50', Rule::notIn([GoldRate::SOURCE_DEVELOPMENT])],
        ];
    }

    public function messages(): array
    {
        $messages = [
            'rate_date.required' => 'Please choose the rate date.',
            'rate_date.date_format' => 'Please enter a valid date.',
            'rate_date.before_or_equal' => 'The rate date cannot be in the future.',
            'rate_time.required' => 'Please enter the time the rate takes effect.',
            'rate_time.date_format' => 'Please enter a valid time (HH:MM).',
            'source.not_in' => 'This source name is reserved for development data.',
            'source.max' => 'The source may not be longer than 50 characters.',
        ];

        foreach (['rate_22k' => '22K', 'rate_24k' => '24K'] as $field => $label) {
            $messages += [
                "{$field}.required" => "Please enter the {$label} rate per gram.",
                "{$field}.numeric" => "The {$label} rate must be a number, e.g. 7000.00.",
                "{$field}.decimal" => "The {$label} rate can have at most 2 decimal places (paise), e.g. 7000.50.",
                "{$field}.gt" => "The {$label} rate must be greater than zero.",
                "{$field}.max" => "The {$label} rate looks too high — please check the amount.",
            ];
        }

        // rate_24k also has gt:rate_22k (24K gold is purer, so it is always priced higher).
        $messages['rate_24k.gt'] = 'The 24K rate must be greater than zero and higher than the 22K rate.';

        return $messages;
    }

    public function attributes(): array
    {
        return ['rate_22k' => '22K rate', 'rate_24k' => '24K rate'];
    }

    /**
     * Cross-field checks that depend on the existing history.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['rate_date', 'rate_time'])) {
                    return;
                }

                $moment = $this->effectiveMoment();

                if ($moment->isAfter(now()->addMinutes(self::FUTURE_TOLERANCE_MINUTES))) {
                    $validator->errors()->add('rate_time', 'The effective time cannot be in the future.');

                    return;
                }

                $duplicate = GoldRate::query()
                    ->whereDate('rate_date', $moment->toDateString())
                    ->where('rate_time', $moment->format('H:i:s'))
                    ->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', GoldRate::SOURCE_DEVELOPMENT))
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('rate_time', 'A rate has already been recorded for this date and time. Use a different time for a new update.');
                }
            },
        ];
    }

    public function effectiveMoment(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', $this->input('rate_date').' '.$this->input('rate_time'));
    }
}
