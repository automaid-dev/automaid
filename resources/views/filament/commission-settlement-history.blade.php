<div class="fi-ta-content overflow-x-auto">
    @if ($settlements->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400 px-4 py-6 text-center">No settlements have been paid out to this user yet.</p>
    @else
        <table class="fi-ta-table w-full text-sm text-left">
            <thead>
                <tr class="border-b border-gray-200 dark:border-white/10">
                    <th class="px-3 py-2 font-medium">Paid At</th>
                    <th class="px-3 py-2 font-medium">Bank/Transfer Ref</th>
                    <th class="px-3 py-2 font-medium text-right">Gross (RM)</th>
                    <th class="px-3 py-2 font-medium text-right">Deductions (RM)</th>
                    <th class="px-3 py-2 font-medium text-right">Net Paid (RM)</th>
                    <th class="px-3 py-2 font-medium">Deduction Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($settlements as $settlement)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="px-3 py-2 whitespace-nowrap">{{ $settlement->paid_at?->format('d M Y, h:i A') }}</td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $settlement->bank_transaction_id }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($settlement->gross_amount, 2) }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($settlement->total_deductions, 2) }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ number_format($settlement->net_amount, 2) }}</td>
                        <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                            @forelse ($settlement->deductions as $deduction)
                                {{ ucfirst($deduction->type) }}: RM{{ number_format($deduction->amount, 2) }}@if($deduction->description) ({{ $deduction->description }})@endif<br>
                            @empty
                                &mdash;
                            @endforelse
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
