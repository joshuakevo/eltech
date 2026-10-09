{{-- Shared Loans tab bar. $activeTab: normal | locked-up | closed | general-provisions | specific-provisions --}}
@php
    $loanTabs = [];
    if (auth()->user()->can('view loans')) {
        $loanTabs[] = ['normal', route('loans.index', ['type' => 'normal']), 'bi-cash-stack', 'Normal Loans'];
        $loanTabs[] = ['locked-up', route('loans.index', ['type' => 'locked-up']), 'bi-lock', 'Locked-Up Loans'];
        $loanTabs[] = ['closed', route('loans.index', ['type' => 'closed']), 'bi-check2-circle', 'Closed Loans'];
    }
    if (auth()->user()->can('view loan provisions')) {
        $loanTabs[] = ['general-provisions', route('loan-provisions.index', ['type' => 'general']), 'bi-shield-check', 'General Provisions'];
        $loanTabs[] = ['specific-provisions', route('loan-provisions.index', ['type' => 'specific']), 'bi-shield-exclamation', 'Specific Provisions'];
    }
@endphp
<style>
    .loan-tabs { border-bottom: 1px solid #e2e6ec; gap: .25rem; }
    .loan-tabs .nav-link { color: #5b6573; font-weight: 500; border: 0; border-bottom: 2px solid transparent; border-radius: 0; padding: .6rem .95rem; margin-bottom: -1px; background: none; }
    .loan-tabs .nav-link i { color: #9aa3af; }
    .loan-tabs .nav-link:hover { color: #0f2444; border-bottom-color: #c9d1dc; }
    .loan-tabs .nav-link.active { color: #0f2444; font-weight: 600; border-bottom-color: #0f2444; }
    .loan-tabs .nav-link.active i { color: #0f2444; }
    .loan-tabs .tab-sep { width: 1px; background: #e2e6ec; margin: .55rem .5rem; }
</style>
<ul class="nav loan-tabs mb-4">
    @foreach($loanTabs as $i => [$key, $url, $icon, $label])
        @if($i > 0 && $key === 'general-provisions' && $loanTabs[0][0] === 'normal')<li class="tab-sep" aria-hidden="true"></li>@endif
        <li class="nav-item">
            <a class="nav-link text-nowrap {{ $activeTab === $key ? 'active' : '' }}" href="{{ $url }}"><i class="bi {{ $icon }} me-1"></i>{{ $label }}</a>
        </li>
    @endforeach
</ul>
