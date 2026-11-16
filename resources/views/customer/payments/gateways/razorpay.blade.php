{{--
    Razorpay Standard Checkout. Only the PUBLIC key id is sent to the browser.
    The handler posts Razorpay's response to our callback, where the server
    verifies the signature and fetches the payment from Razorpay before
    crediting anything. A failure report only ever marks the attempt failed.
--}}
@use('App\Support\Money')

<button type="button" class="btn btn-gold w-full" data-razorpay-open>
    <x-icon name="lock" class="size-4" />
    Pay {{ Money::inr($payment->amount()) }}
</button>

<form method="POST" action="{{ route('customer.payments.callback', $payment->reference) }}" class="hidden" data-razorpay-callback>
    @csrf
    <input type="hidden" name="razorpay_order_id">
    <input type="hidden" name="razorpay_payment_id">
    <input type="hidden" name="razorpay_signature">
</form>
<form method="POST" action="{{ route('customer.payments.cancel', $payment->reference) }}" class="hidden" data-razorpay-failed>
    @csrf
    <input type="hidden" name="outcome" value="failed">
</form>

<script src="{{ $script }}"></script>
<script>
    (() => {
        const options = @json($options);
        const callback = document.querySelector('[data-razorpay-callback]');
        const failed = document.querySelector('[data-razorpay-failed]');
        const button = document.querySelector('[data-razorpay-open]');
        if (typeof window.Razorpay !== 'function') {
            button.disabled = true;
            button.textContent = 'Payment gateway unavailable — please try again';
            return;
        }
        const checkout = new window.Razorpay(Object.assign(options, {
            handler(response) {
                for (const name of ['razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature']) {
                    callback.elements[name].value = response[name] || '';
                }
                callback.submit();
            },
            modal: { confirm_close: true },
        }));
        checkout.on('payment.failed', () => failed.submit());
        button.addEventListener('click', () => checkout.open());
        checkout.open();
    })();
</script>
