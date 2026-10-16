{{--
    Phone bottom navigation (app-style tab bar), shown at 768px and below.
    $items: [['label' => .., 'icon' => 'bi-…', 'url' => .., 'active' => bool, 'fab' => bool, 'badge' => int]]
    $menu:  JS run by the last "More" tab (opens the full menu sheet); null hides it.
--}}
<style>
    .bnav{display:none}
    @media(max-width:768px){
        body{padding-bottom:calc(70px + env(safe-area-inset-bottom))}
        .bnav{display:flex;position:fixed;left:0;right:0;bottom:0;z-index:1045;align-items:flex-end;justify-content:space-around;
              padding:6px 4px calc(6px + env(safe-area-inset-bottom));background:rgba(255,255,255,.97);
              -webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px);border-top:1px solid #e5e7eb;box-shadow:0 -6px 24px rgba(15,36,68,.08)}
        .bnav .bn-tab{flex:1;min-width:0;display:flex;flex-direction:column;align-items:center;gap:3px;padding:2px 0;position:relative;
              background:none;border:0;color:#6b7280;font-size:.66rem;font-weight:600;letter-spacing:.01em;text-decoration:none;-webkit-tap-highlight-color:transparent}
        .bnav .bn-tab span.lbl{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
        .bnav .bn-ico{width:48px;height:30px;border-radius:15px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;transition:background .2s,color .2s,transform .15s}
        .bnav .bn-tab:active .bn-ico{transform:scale(.9)}
        .bnav .bn-tab.active{color:#0f2444}
        .bnav .bn-tab.active .bn-ico{background:#e0e9ff;color:#1d4ed8}
        .bnav .bn-tab.fab .bn-ico{width:56px;height:56px;margin-top:-28px;border-radius:50%;font-size:1.45rem;color:#fff;
              background:linear-gradient(135deg,#2563eb 0%,#0f2444 100%);border:4px solid #fff;box-shadow:0 8px 18px rgba(37,99,235,.38)}
        .bnav .bn-tab.fab.active{color:#1d4ed8}
        .bnav .bn-badge{position:absolute;top:-2px;left:calc(50% + 8px);min-width:17px;height:17px;padding:0 5px;border-radius:999px;
              background:#f59e0b;color:#111;font-size:.6rem;font-weight:700;line-height:17px;text-align:center;border:2px solid #fff}
        #fpPrompt{bottom:calc(84px + env(safe-area-inset-bottom))!important}
    }
    @media print{.bnav{display:none!important}}
</style>
<nav class="bnav" aria-label="Main">
    @foreach($items as $it)
        <a href="{{ $it['url'] }}" class="bn-tab {{ !empty($it['fab']) ? 'fab' : '' }} {{ !empty($it['active']) ? 'active' : '' }}" @if(!empty($it['active'])) aria-current="page" @endif>
            <span class="bn-ico"><i class="bi {{ $it['icon'] }}"></i></span>
            <span class="lbl">{{ $it['label'] }}</span>
            @if(!empty($it['badge']))<span class="bn-badge">{{ $it['badge'] }}</span>@endif
        </a>
    @endforeach
    @if(!empty($menu))
        <button type="button" class="bn-tab" onclick="event.stopPropagation();{{ $menu }}">
            <span class="bn-ico"><i class="bi bi-grid-3x3-gap-fill"></i></span>
            <span class="lbl">More</span>
            @if(!empty($menuBadge))<span class="bn-badge">{{ $menuBadge }}</span>@endif
        </button>
    @endif
</nav>
