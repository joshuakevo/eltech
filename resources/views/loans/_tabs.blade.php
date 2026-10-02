{{-- Shared Loans tab bar. $activeTab: normal | locked-up | closed | general-provisions | specific-provisions --}}
<ul class="nav nav-pills mb-4 gap-2">
    @can('view loans')
    <li class="nav-item">
        <a class="nav-link {{ $activeTab === 'normal' ? 'active' : '' }}" href="{{ route('loans.index', ['type' => 'normal']) }}">Normal Loans</a>
    </li>
    <li class="nav-item">
        <a class="nav-link border border-danger {{ $activeTab === 'locked-up' ? 'active bg-danger text-white' : 'text-danger' }}"
           href="{{ route('loans.index', ['type' => 'locked-up']) }}">
            <i class="bi bi-lock-fill me-1"></i>Locked-Up Loans
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link border border-secondary {{ $activeTab === 'closed' ? 'active bg-secondary text-white' : 'text-secondary' }}"
           href="{{ route('loans.index', ['type' => 'closed']) }}">
            <i class="bi bi-check-circle-fill me-1"></i>Closed Loans
        </a>
    </li>
    @endcan
    @can('view loan provisions')
    <li class="nav-item">
        <a class="nav-link border border-success {{ $activeTab === 'general-provisions' ? 'active bg-success text-white' : 'text-success' }}"
           href="{{ route('loan-provisions.index', ['type' => 'general']) }}">
            <i class="bi bi-shield-check me-1"></i>General Provisions
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link border border-warning {{ $activeTab === 'specific-provisions' ? 'active bg-warning text-dark' : 'text-warning-emphasis' }}"
           href="{{ route('loan-provisions.index', ['type' => 'specific']) }}">
            <i class="bi bi-shield-exclamation me-1"></i>Specific Provisions
        </a>
    </li>
    @endcan
</ul>
