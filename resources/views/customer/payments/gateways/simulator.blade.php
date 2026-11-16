{{--
    LOCAL DEVELOPMENT ONLY. Stands in for a real gateway's checkout. The result
    is recorded "on the gateway side" and then verified by the server exactly
    like a real callback (signature + the gateway's own record).
--}}
@use('App\Support\Money')

<div class="border border-dashed border-amber-400 bg-amber-50 px-5 py-4 text-sm text-amber-900" data-simulator-notice>
    <p class="font-semibold">Development payment simulator</p>
    <p class="mt-1 text-xs leading-relaxed">No real money is involved. This screen is available only when the payment gateway is set to the simulator outside production.</p>
</div>

<div class="mt-5 grid gap-3 sm:grid-cols-2">
    <form method="POST" action="{{ route('customer.payments.simulate', $payment->reference) }}">
        @csrf
        <input type="hidden" name="outcome" value="success">
        <button type="submit" class="btn btn-gold w-full" data-submit-once data-simulate="success">Simulate Successful Payment</button>
    </form>
    <form method="POST" action="{{ route('customer.payments.simulate', $payment->reference) }}">
        @csrf
        <input type="hidden" name="outcome" value="failure">
        <button type="submit" class="btn btn-ghost-dark w-full" data-submit-once data-simulate="failure">Simulate Failed Payment</button>
    </form>
</div>
