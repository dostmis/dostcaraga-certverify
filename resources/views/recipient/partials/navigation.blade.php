<nav style="
    background: #fff;
    border-bottom: 1px solid #dbe5ef;
    position: sticky;
    top: 0;
    z-index: 40;
    backdrop-filter: blur(12px);
    background: rgba(255,255,255,0.94);
">
    <div style="margin:0 auto; max-width:1200px; padding:0 16px;">
        <div style="display:flex; height:56px; align-items:center; justify-content:space-between;">
            <div style="display:flex; align-items:center; gap:28px;">
                <a href="{{ route('recipient.certificates') }}" style="display:flex; align-items:center; gap:8px; text-decoration:none;">
                    <img src="{{ asset('images/dosttt.png') }}" alt="DOST Logo" style="width:32px;height:32px;object-fit:contain;">
                    <span style="font-weight:800;font-size:15px;color:#0f172a;letter-spacing:-0.01em;">CERTiFY</span>
                </a>
                <a href="{{ route('recipient.certificates') }}" style="
                    font-size:13px;font-weight:700;text-decoration:none;padding:4px 0;
                    border-bottom:2px solid {{ request()->routeIs('recipient.certificates') ? '#0891B2' : 'transparent' }};
                    color:{{ request()->routeIs('recipient.certificates') ? '#0891B2' : '#475569' }};
                    transition: color 0.15s;
                " onmouseenter="this.style.color='#0891B2'" onmouseleave="this.style.color='{{ request()->routeIs('recipient.certificates') ? '#0891B2' : '#475569' }}'">
                    My Certificates
                </a>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <a href="{{ route('recipient.profile.edit') }}" style="
                    font-size:13px;font-weight:600;text-decoration:none;color:#64748b;
                    display:flex;align-items:center;gap:5px;padding:6px 10px;border-radius:8px;
                    transition:all 0.15s;
                " onmouseenter="this.style.color='#334155';this.style.background='#f1f5f9'" onmouseleave="this.style.color='#64748b';this.style.background='transparent'">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    Settings
                </a>
                <span style="font-size:13px;color:#64748b;font-weight:500;">{{ auth('recipient')->user()->name }}</span>
                <form method="POST" action="{{ route('recipient.logout') }}" style="display:inline;">
                    @csrf
                    <button type="submit" style="
                        background:none;border:none;font-size:13px;font-weight:600;color:#94a3b8;
                        cursor:pointer;padding:6px 12px;border-radius:8px;
                        transition:all 0.15s;
                    " onmouseenter="this.style.color='#ef4444';this.style.background='#fef2f2'" onmouseleave="this.style.color='#94a3b8';this.style.background='none'">
                        Log out
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
