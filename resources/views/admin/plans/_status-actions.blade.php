{{-- Activate / deactivate buttons (POST + CSRF). No delete action exists for plans. --}}
@if ($plan->status)
    <form method="POST" action="{{ route('admin.plans.deactivate', $plan) }}" class="inline">
        @csrf
        <button type="submit" class="{{ $class ?? '' }} rounded-md border border-ad-line px-3 py-1.5 text-xs font-semibold transition-colors hover:border-red-300 hover:text-red-700">Deactivate</button>
    </form>
@else
    <form method="POST" action="{{ route('admin.plans.activate', $plan) }}" class="inline">
        @csrf
        <button type="submit" class="{{ $class ?? '' }} rounded-md border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-800 transition-colors hover:bg-emerald-50">Activate</button>
    </form>
@endif
