{{-- Rate updates table (one row per update: date + time). Scrolls horizontally on small screens. --}}
@use('App\Support\Money')

@if ($updates->isEmpty())
    <p class="px-5 py-8 text-sm text-taupe">No gold rates recorded{{ request()->hasAny(['from', 'to']) ? ' for the selected dates' : ' yet' }}.</p>
@else
    <p class="px-5 pt-3 text-xs text-taupe sm:hidden">Swipe the table sideways to see all columns.</p>
    <div class="overflow-x-auto" data-scroll-table>
        <table class="w-full min-w-[42rem] text-left text-sm" data-table="rate-updates">
            <thead class="text-[0.68rem] tracking-[0.14em] text-taupe uppercase">
                <tr>
                    <th scope="col" class="px-5 py-3 font-semibold">Date</th>
                    <th scope="col" class="px-5 py-3 font-semibold">Time</th>
                    @foreach (['22K', '24K'] as $purity)
                        <th scope="col" class="px-5 py-3 text-right font-semibold">{{ $purity }} / g</th>
                    @endforeach
                    <th scope="col" class="px-5 py-3 font-semibold">Source</th>
                    <th scope="col" class="px-5 py-3 font-semibold">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($updates as $update)
                    <tr data-rate-update>
                        <td class="px-5 py-3 whitespace-nowrap">{{ $update['date']->format('d M Y') }}</td>
                        <td class="px-5 py-3 whitespace-nowrap tabular-nums">{{ $update['time'] ? substr($update['time'], 0, 5) : '—' }}</td>
                        @foreach (['22K', '24K'] as $purity)
                            @php $rate = $update['rates'][$purity]; @endphp
                            <td class="px-5 py-3 text-right whitespace-nowrap tabular-nums">
                                @if ($rate)
                                    <span class="font-semibold">{{ Money::inr($rate->rate_per_gram) }}</span>
                                    <span class="block text-xs text-taupe">{{ Money::inr($rate->rate_per_10_gram) }} / 10g</span>
                                @else
                                    <span class="text-taupe">—</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="px-5 py-3">
                            {{ $update['source'] ?? '—' }}
                            @if ($update['source'] === \App\Models\GoldRate::SOURCE_DEVELOPMENT)
                                <span class="ml-1 rounded-sm bg-amber-50 px-1.5 py-0.5 text-[0.6rem] font-semibold text-amber-800 uppercase ring-1 ring-amber-200">Sample</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $update['active'] ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-stone-100 text-stone-700 ring-stone-300' }}">{{ $update['active'] ? 'Active' : 'Inactive' }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
