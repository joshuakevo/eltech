{{-- Shared Loans tab bar. $activeTab: normal | locked-up | closed | general-provisions | specific-provisions --}}
@php
    $loanTabs = [];
    if (auth()->user()->can('view loans')) {
        $loanTabs[] = ['normal', route('loans.index', ['type' => 'normal']), 'bi-cash-stack', 'Normal Loans', '37, 99, 235'];
        $loanTabs[] = ['locked-up', route('loans.index', ['type' => 'locked-up']), 'bi-lock', 'Locked-Up Loans', '185, 28, 28'];
        $loanTabs[] = ['closed', route('loans.index', ['type' => 'closed']), 'bi-check2-circle', 'Closed Loans', '71, 85, 105'];
    }
    if (auth()->user()->can('view loan provisions')) {
        $loanTabs[] = ['general-provisions', route('loan-provisions.index', ['type' => 'general']), 'bi-shield-check', 'General Provisions', '21, 128, 61'];
        $loanTabs[] = ['specific-provisions', route('loan-provisions.index', ['type' => 'specific']), 'bi-shield-exclamation', 'Specific Provisions', '180, 83, 9'];
    }
@endphp
<style>
    .loan-tabs { border-bottom: 1px solid #e2e6ec; gap: .25rem; }
    .loan-tabs .nav-link { color: #5b6573; font-weight: 500; border: 0; border-bottom: 2px solid transparent; border-radius: .4rem .4rem 0 0; padding: .6rem .95rem; margin-bottom: -1px; background: none; transition: background .15s, color .15s; }
    .loan-tabs .nav-link i { color: rgb(var(--tab)); opacity: .75; }
    .loan-tabs .nav-link:hover { color: rgb(var(--tab)); background: rgba(var(--tab), .05); border-bottom-color: rgba(var(--tab), .35); }
    .loan-tabs .nav-link.active { color: rgb(var(--tab)); font-weight: 600; background: rgba(var(--tab), .09); border-bottom: 3px solid rgb(var(--tab)); }
    .loan-tabs .nav-link.active i { opacity: 1; }
    .loan-tabs .tab-sep { width: 1px; background: #e2e6ec; margin: .55rem .5rem; }
</style>
<ul class="nav loan-tabs mb-4">
    @foreach($loanTabs as $i => [$key, $url, $icon, $label, $rgb])
        @if($i > 0 && $key === 'general-provisions' && $loanTabs[0][0] === 'normal')<li class="tab-sep" aria-hidden="true"></li>@endif
        <li class="nav-item">
            <a class="nav-link text-nowrap {{ $activeTab === $key ? 'active' : '' }}" style="--tab: {{ $rgb }}" href="{{ $url }}"><i class="bi {{ $icon }} me-1"></i>{{ $label }}</a>
        </li>
    @endforeach
</ul>
