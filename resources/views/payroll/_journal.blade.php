{{-- Journal breakdown table. Expects $lines: [['account' => Account|null, 'debit', 'credit', 'description'], ...] --}}
<div class="table-responsive">
    <table class="table table-sm mb-0 small">
        <thead class="table-light">
            <tr>
                <th>Account</th>
                <th>Description</th>
                <th class="text-end">Debit</th>
                <th class="text-end">Credit</th>
            </tr>
        </thead>
        <tbody>
        @forelse($lines as $line)
            <tr>
                <td class="{{ $line['credit'] > 0 ? 'ps-4' : '' }}">
                    @if($line['account'])
                        <span class="font-monospace">{{ $line['account']->account_code }}</span> {{ $line['account']->account_name }}
                    @else
                        <span class="text-danger">Unknown account</span>
                    @endif
                </td>
                <td class="text-muted">{{ $line['description'] }}</td>
                <td class="text-end">{{ $line['debit'] > 0 ? number_format($line['debit'], 0) : '' }}</td>
                <td class="text-end">{{ $line['credit'] > 0 ? number_format($line['credit'], 0) : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-muted text-center py-2">Nothing to post — no employee has a net pay above 0.</td></tr>
        @endforelse
        </tbody>
        @if(count($lines))
        <tfoot class="table-light fw-bold">
            <tr>
                <td colspan="2">Totals</td>
                <td class="text-end">{{ number_format(collect($lines)->sum('debit'), 0) }}</td>
                <td class="text-end">{{ number_format(collect($lines)->sum('credit'), 0) }}</td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>
