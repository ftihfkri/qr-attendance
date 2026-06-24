@extends('layouts.app')
@section('title', 'Board Election — KOP-SSB')

@push('head')
<style>
    @keyframes kopGradient { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
    @keyframes kopFloat   { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
    @keyframes kopPulse   { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.35;transform:scale(.8)} }
    @keyframes kopPop     { 0%{transform:scale(.6);opacity:0} 60%{transform:scale(1.06)} 100%{transform:scale(1);opacity:1} }
    @keyframes kopShimmer { 0%{transform:translateX(-120%)} 100%{transform:translateX(320%)} }
    @keyframes kopRise    { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
    @keyframes confettiFall { to { transform: translateY(110vh) rotate(720deg); opacity:.85; } }
    .kop-display-bg { background:linear-gradient(120deg,#06210f,#0d3d1d,#0a3a2c,#06210f); background-size:300% 300%; animation:kopGradient 20s ease infinite; }
    .kop-float    { animation:kopFloat 4.5s ease-in-out infinite; }
    .kop-live-dot { animation:kopPulse 1.2s ease-in-out infinite; }
    .kop-row      { animation:kopRise .5s ease both; }
    .kop-pop      { animation:kopPop .6s cubic-bezier(.22,1,.36,1) both; }
    .kop-leader   { box-shadow:0 0 0 2px rgba(212,175,55,.65), 0 0 34px rgba(212,175,55,.30); }
    .kop-bar-fill { position:relative; overflow:hidden; transition:width .85s cubic-bezier(.22,1,.36,1); }
    .kop-bar-fill::after { content:''; position:absolute; inset:0; width:38%; background:linear-gradient(90deg,transparent,rgba(255,255,255,.40),transparent); animation:kopShimmer 2.3s linear infinite; }
    .confetti-piece { position:fixed; top:-14px; width:10px; height:14px; border-radius:2px; z-index:60; animation:confettiFall linear forwards; }
</style>
@endpush

@section('content')
@php($voteUrl = url('/vote/' . $token))
@php($logo = asset('images/kop-ssb-logo.png'))

@if ($display)
{{-- ════════════ Projector / big-screen display ════════════ --}}
<div class="kop-display-bg min-h-screen text-white px-4 sm:px-8 py-6 flex flex-col">
    <!-- Header -->
    <div class="flex items-center justify-between gap-4 mb-7">
        <div class="flex items-center gap-4 min-w-0">
            <div class="bg-white rounded-2xl p-2 shadow-xl kop-float shrink-0"><img src="{{ $logo }}" alt="KOP-SSB" class="h-12 sm:h-16 w-auto"></div>
            <div class="min-w-0">
                <div class="text-[10px] sm:text-xs uppercase tracking-[0.25em] text-emerald-300/80">Koperasi Kakitangan Sabah Softwoods Berhad</div>
                <h1 class="text-2xl sm:text-4xl font-extrabold leading-tight truncate">🗳 Board Election</h1>
            </div>
        </div>
        <div class="flex items-center gap-3 shrink-0">
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 border border-white/20 text-sm font-semibold">
                <span class="kop-live-dot w-2.5 h-2.5 rounded-full bg-emerald-400 inline-block"></span>
                <span id="dStatusText">Loading…</span>
            </div>
            <button id="dCloseBtn" class="hidden px-4 py-2 rounded-full bg-red-500/90 hover:bg-red-600 text-white text-sm font-semibold border border-red-300/30 transition">■ Close Voting</button>
        </div>
    </div>

    <!-- Body: 50% QR · 50% live results -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 flex-1 items-start">
        <!-- QR + timer + totals -->
        <div class="space-y-5">
            <!-- Big countdown timer, above the QR -->
            <div id="dTimer" class="hidden items-center justify-center gap-4 bg-black/30 border border-white/15 rounded-3xl py-5 shadow-xl">
                <span class="text-4xl sm:text-5xl">⏳</span>
                <span id="dTimerText" class="text-5xl sm:text-7xl font-extrabold tabular-nums tracking-tight">--:--</span>
            </div>
            <div class="bg-white text-slate-800 rounded-3xl p-6 shadow-2xl text-center kop-float">
                <div class="text-xl font-bold mb-4 text-emerald-800">Scan to Vote</div>
                <div id="qrcode" class="flex justify-center mb-3"></div>
                <div class="text-[11px] text-slate-400 break-all">{{ $voteUrl }}</div>
                <div class="mt-3 text-sm text-slate-500 leading-relaxed">Must be checked in · Candidates can’t vote · One vote each</div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-white/10 border border-white/15 rounded-2xl p-4 text-center">
                    <div id="dTotal" class="text-4xl sm:text-5xl font-extrabold tabular-nums">0</div>
                    <div class="text-[11px] uppercase tracking-widest text-emerald-200/70 mt-1">Votes Cast</div>
                </div>
                <div class="bg-white/10 border border-white/15 rounded-2xl p-4 text-center">
                    <div id="dElig" class="text-4xl sm:text-5xl font-extrabold tabular-nums">0</div>
                    <div class="text-[11px] uppercase tracking-widest text-emerald-200/70 mt-1">Eligible</div>
                </div>
            </div>
        </div>

        <!-- Live results -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl sm:text-3xl font-bold">Live Results</h2>
                <span class="text-sm text-emerald-200/70">Top <span id="dSeats">1</span> win</span>
            </div>
            <div id="dBars" class="space-y-4"></div>
            <div id="dWinner" class="hidden mt-6"></div>
        </div>
    </div>
</div>
@else
{{-- ════════════ Voter ballot (phone) ════════════ --}}
<div class="min-h-screen p-4" style="background:linear-gradient(135deg,#ecfdf5 0%,#f0fdfa 100%)">
    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-2xl shadow-card p-7 sm:p-8 border border-slate-100 mt-6">
            <div class="text-center mb-6">
                <img src="{{ $logo }}" alt="KOP-SSB" class="h-16 w-auto mx-auto mb-3">
                <div class="text-[10px] uppercase tracking-[0.18em] text-emerald-700/70 mb-1">Koperasi Kakitangan Sabah Softwoods Berhad</div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Board Election</h1>
                <p class="text-slate-500 text-sm mt-1">Find your name, then choose one candidate.</p>
            </div>

            <div id="ballot">
                <div class="mb-4 relative">
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Full Name</label>
                    <input id="name" autocomplete="off" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 outline-none transition" placeholder="Type your name…">
                    <ul id="nameSuggestions" class="absolute z-20 left-0 right-0 bg-white border border-slate-200 rounded-lg shadow-lg mt-1 max-h-56 overflow-y-auto hidden"></ul>
                </div>
                <div class="mb-5">
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Nombor Ahli / Nombor Anggota</label>
                    <input id="koperasi_id" autocomplete="off" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 outline-none transition" placeholder="Auto-fills when you pick your name">
                </div>

                <p class="text-sm font-medium text-slate-700 mb-2">Candidates</p>
                @if ($candidates->isEmpty())
                    <p class="text-sm text-slate-400 mb-4">No candidates have been nominated yet.</p>
                @else
                <div class="space-y-2 mb-5">
                    @foreach ($candidates as $c)
                    <label class="flex items-center gap-3 p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-emerald-50 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50 transition">
                        <input type="radio" name="candidate" value="{{ $c['candidate_id'] }}" class="accent-emerald-600 w-4 h-4">
                        <span class="text-slate-800 font-medium">{{ $c['name'] }}</span>
                    </label>
                    @endforeach
                </div>
                <button id="voteBtn" class="w-full bg-emerald-600 text-white py-2.5 rounded-lg hover:bg-emerald-700 active:bg-emerald-800 font-semibold shadow-sm transition disabled:opacity-50">Submit Vote</button>
                @endif
                <p id="status" class="text-center text-sm mt-3 min-h-5"></p>
            </div>

            <!-- After voting: thank-you + live results on the voter's own phone -->
            <div id="thanks" class="hidden">
                <div class="text-center py-4">
                    <div class="w-16 h-16 bg-emerald-100 rounded-2xl flex items-center justify-center mx-auto mb-3 text-emerald-600 text-3xl kop-pop">✓</div>
                    <h2 class="text-lg font-semibold text-slate-900">Vote recorded</h2>
                    <p class="text-slate-500 text-sm mt-1">Thank you for voting. Here are the live results:</p>
                </div>
                <div class="flex items-center justify-between mb-2 mt-2">
                    <span class="text-sm font-semibold text-slate-700">Live Results</span>
                    <span id="vMeta" class="text-xs font-mono text-slate-400">0 votes</span>
                </div>
                <div id="vBars" class="space-y-2"></div>
                <div id="vWinner" class="hidden mt-3 bg-emerald-50 border border-emerald-300 rounded-lg px-4 py-3 text-sm text-emerald-800"></div>
            </div>
        </div>
        <p class="text-center text-[11px] text-slate-400 mt-4">© KOP-SSB · Secure ballot — one vote per member</p>
    </div>
</div>
@endif
@endsection

@push('scripts')
@if ($display)
<script src="https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs@master/qrcode.min.js"></script>
@endif
<script>
    const TOKEN = @json($token);
    function esc(s){ return String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

@if ($display)
    // ---- Projector display ----
    new QRCode(document.getElementById('qrcode'), { text: @json($voteUrl), width: 250, height: 250, colorDark: '#0a3a1c' });

    const greens = [['#facc15','#f59e0b'],['#34d399','#10b981'],['#22d3ee','#0891b2'],['#a3e635','#65a30d'],['#5eead4','#0d9488'],['#86efac','#16a34a'],['#fcd34d','#d97706'],['#67e8f9','#0e7490']];
    const medals = ['🥇','🥈','🥉','🏅'];
    const VOTE_ENDS_AT = @json(optional($meeting->vote_ends_at)->toIso8601String());
    let confettiFired = false;

    function fireConfetti() {
        const colors = ['#facc15','#34d399','#ffffff','#fcd34d','#10b981'];
        for (let i = 0; i < 110; i++) {
            const p = document.createElement('div');
            p.className = 'confetti-piece';
            p.style.left = Math.random() * 100 + 'vw';
            p.style.background = colors[i % colors.length];
            p.style.animationDuration = (2.5 + Math.random() * 2.5) + 's';
            p.style.animationDelay = (Math.random() * 0.8) + 's';
            p.style.transform = 'rotate(' + (Math.random() * 360) + 'deg)';
            document.body.appendChild(p);
            setTimeout(() => p.remove(), 6500);
        }
    }

    // Countdown timer from the voting window's end time.
    function tickTimer() {
        const txt = document.getElementById('dTimerText');
        if (!VOTE_ENDS_AT) return;
        const diff = new Date(VOTE_ENDS_AT).getTime() - Date.now();
        if (diff <= 0) { txt.textContent = '00:00'; return; }
        const s = Math.floor(diff / 1000);
        txt.textContent = String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
    }
    setInterval(tickTimer, 1000); tickTimer();

    // Close voting from the display (works when opened by the logged-in organiser).
    document.getElementById('dCloseBtn').addEventListener('click', async () => {
        if (!confirm('Close voting now? This finalises the results.')) return;
        try {
            const res = await fetch('/admin/election/voting', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.csrfToken },
                body: JSON.stringify({ action: 'close' }),
            });
            if (res.status === 401 || res.status === 419) { alert('Only the logged-in organiser can close voting. Open this page from the admin dashboard.'); return; }
            if (!res.ok) { alert('Could not close voting.'); return; }
            poll();
        } catch (e) { alert('Network error — could not close voting.'); }
    });

    // Top-N winner reveal (podium) — shown when voting is finished or all eligible voted.
    function renderWinners(winners, seats, finished) {
        const w = document.getElementById('dWinner');
        if (!winners.length) { w.classList.add('hidden'); return; }
        w.classList.remove('hidden');
        w.className = 'mt-7';
        const label = finished
            ? `Winner${winners.length > 1 ? 's' : ''} · ${seats} seat${seats > 1 ? 's' : ''}`
            : `Leading · all votes are in (top ${seats})`;
        const cards = winners.map((c, i) => `
            <div class="kop-pop bg-gradient-to-b from-amber-400/25 to-emerald-400/10 border border-amber-300/40 rounded-2xl p-5 text-center" style="animation-delay:${i * 130}ms">
                <div class="text-4xl mb-1">${medals[i] || '🏅'}</div>
                <div class="text-xl sm:text-2xl font-extrabold truncate">${esc(c.name)}</div>
                <div class="text-emerald-100/80 mt-1"><span class="text-2xl font-extrabold tabular-nums">${c.votes}</span> votes · ${c.percent}%</div>
            </div>`).join('');
        w.innerHTML = `
            <div class="text-center mb-4">
                <div class="text-5xl">🏆</div>
                <div class="text-xs uppercase tracking-[0.25em] text-amber-200 mt-1">${label}</div>
            </div>
            <div class="grid gap-4" style="grid-template-columns:repeat(${Math.min(winners.length, 4)},minmax(0,1fr))">${cards}</div>`;
    }

    // Results bars are built once and then UPDATED IN PLACE every poll (widths,
    // counts, ranking) — so they animate smoothly instead of flickering/rebuilding.
    let barEls = {}, builtKey = '', lastOrder = '';
    function renderBars(cands) {
        const box = document.getElementById('dBars');
        if (!cands.length) { box.innerHTML = '<div class="text-center text-emerald-200/60 py-10 text-lg">Waiting for the first vote…</div>'; builtKey = ''; lastOrder = ''; barEls = {}; return; }
        const key = cands.map(c => c.candidate_id).slice().sort((a, b) => a - b).join(',');
        if (key !== builtKey) {
            box.innerHTML = cands.map(c => `
                <div id="bar-${c.candidate_id}" class="kop-row bg-white/[0.07] rounded-2xl p-4 border border-white/10">
                    <div class="flex items-center justify-between mb-2 gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="b-rank text-2xl shrink-0 w-9 text-center"></span>
                            <span class="text-xl sm:text-2xl font-bold truncate">${esc(c.name)}</span>
                            <span class="b-crown text-2xl hidden">👑</span>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="b-vt text-2xl sm:text-3xl font-extrabold tabular-nums">0</span>
                            <span class="b-pct text-emerald-200/70 text-base sm:text-lg ml-1">0%</span>
                        </div>
                    </div>
                    <div class="h-5 rounded-full bg-white/10 overflow-hidden">
                        <div class="b-fill kop-bar-fill h-full rounded-full" style="width:0%"></div>
                    </div>
                </div>`).join('');
            barEls = {};
            cands.forEach(c => {
                const row = document.getElementById('bar-' + c.candidate_id);
                barEls[c.candidate_id] = { row, rank: row.querySelector('.b-rank'), crown: row.querySelector('.b-crown'), vt: row.querySelector('.b-vt'), pct: row.querySelector('.b-pct'), fill: row.querySelector('.b-fill') };
            });
            builtKey = key;
            lastOrder = cands.map(c => c.candidate_id).join(','); // DOM already in this order
        }
        // Update values in place — setting the same text/width again does not flicker.
        cands.forEach((c, i) => {
            const el = barEls[c.candidate_id]; if (!el) return;
            const g = c.is_winner ? greens[0] : greens[(i + 1) % greens.length];
            el.rank.textContent = i < 3 ? medals[i] : (i + 1);
            el.vt.textContent = c.votes;
            el.pct.textContent = c.percent + '%';
            el.fill.style.width = c.percent + '%';
            el.fill.style.background = `linear-gradient(90deg,${g[0]},${g[1]})`;
            el.crown.classList.toggle('hidden', !c.is_winner);
            el.row.classList.toggle('kop-leader', (i === 0 && c.votes > 0) || c.is_winner);
        });
        // Only move DOM nodes when the RANKING actually changes (prevents per-poll flicker).
        const order = cands.map(c => c.candidate_id).join(',');
        if (order !== lastOrder) {
            cands.forEach(c => { const el = barEls[c.candidate_id]; if (el) box.appendChild(el.row); });
            lastOrder = order;
        }
    }

    async function poll() {
        let res;
        try { res = await fetch('/vote/' + TOKEN + '/results', { headers: { 'Accept': 'application/json' } }); }
        catch (e) { return; }
        if (!res.ok) return;
        const { tally, eligible_voters } = await res.json();

        document.getElementById('dStatusText').textContent =
            tally.voting_active ? 'LIVE · Voting Open' : (tally.voting_finished ? 'Voting Closed' : 'Not Started');
        document.getElementById('dTotal').textContent = tally.total_votes;
        document.getElementById('dElig').textContent = eligible_voters;
        document.getElementById('dSeats').textContent = tally.seats;
        document.getElementById('dCloseBtn').classList.toggle('hidden', !tally.voting_active);
        const showTimer = tally.voting_active && !!VOTE_ENDS_AT;
        document.getElementById('dTimer').classList.toggle('hidden', !showTimer);
        document.getElementById('dTimer').classList.toggle('flex', showTimer);

        renderBars(tally.candidates);

        // Winner reveal: when finished, or when every eligible member has voted.
        const seats = tally.seats;
        const allIn = eligible_voters > 0 && tally.total_votes >= eligible_voters;
        let winners = [];
        if (tally.voting_finished) winners = tally.candidates.filter(c => c.is_winner);
        else if (allIn) winners = tally.candidates.filter(c => c.votes > 0).slice(0, seats);
        renderWinners(winners, seats, tally.voting_finished);
        if (tally.voting_finished && !confettiFired && winners.length) { confettiFired = true; fireConfetti(); }
    }
    poll(); setInterval(poll, 2000);
@else
    // ---- Voter ballot ----
    // Name autocomplete over this meeting's checked-in attendees (fills Nombor Ahli).
    const nameInput = document.getElementById('name');
    const koperasiInput = document.getElementById('koperasi_id');
    const suggestionsBox = document.getElementById('nameSuggestions');
    function hideSuggestions() { suggestionsBox.classList.add('hidden'); suggestionsBox.innerHTML = ''; }
    let searchTimer = null;
    if (nameInput) nameInput.addEventListener('input', () => {
        const q = nameInput.value.trim();
        clearTimeout(searchTimer);
        if (q.length < 2) { hideSuggestions(); return; }
        searchTimer = setTimeout(async () => {
            try {
                const res = await fetch('/vote/' + TOKEN + '/voters?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) { hideSuggestions(); return; }
                const { data } = await res.json();
                if (!data || !data.length) { hideSuggestions(); return; }
                suggestionsBox.innerHTML = data.map(m =>
                    `<li class="px-3 py-2 hover:bg-emerald-50 cursor-pointer text-sm flex justify-between gap-2" data-name="${esc(m.name)}" data-id="${esc(m.member_id)}">
                        <span class="font-medium truncate">${esc(m.name)}</span>
                        <span class="text-slate-400 shrink-0">${esc(m.member_id)}</span>
                    </li>`).join('');
                suggestionsBox.classList.remove('hidden');
                suggestionsBox.querySelectorAll('li').forEach(li => li.addEventListener('click', () => {
                    nameInput.value = li.dataset.name;
                    koperasiInput.value = li.dataset.id;
                    hideSuggestions();
                }));
            } catch (e) { hideSuggestions(); }
        }, 250);
    });
    document.addEventListener('click', (e) => {
        if (e.target !== nameInput && !suggestionsBox.contains(e.target)) hideSuggestions();
    });

    // Random per-device ID — shares the same key as check-in, so a phone keeps one
    // identity. NOT derived from device attributes, so identical phones never collide.
    function deviceFingerprint() {
        const key = 'device-fp';
        let fp = localStorage.getItem(key);
        if (fp) return fp;
        if (window.crypto && crypto.randomUUID) {
            fp = crypto.randomUUID();
        } else if (window.crypto && crypto.getRandomValues) {
            const b = new Uint8Array(16);
            crypto.getRandomValues(b);
            fp = Array.from(b, x => x.toString(16).padStart(2, '0')).join('');
        } else {
            fp = Date.now().toString(16) + Math.random().toString(16).slice(2);
        }
        localStorage.setItem(key, fp);
        return fp;
    }

    // Live results shown to the voter after they vote.
    const vPalette = [['#f59e0b','#d97706'],['#10b981','#0d9488'],['#0891b2','#0e7490'],['#65a30d','#4d7c0f'],['#16a34a','#15803d']];
    async function voterResults() {
        let res;
        try { res = await fetch('/vote/' + TOKEN + '/results', { headers: { 'Accept': 'application/json' } }); }
        catch (e) { return; }
        if (!res.ok) return;
        const { tally } = await res.json();
        document.getElementById('vMeta').textContent = `${tally.total_votes} vote${tally.total_votes === 1 ? '' : 's'}`;
        document.getElementById('vBars').innerHTML = tally.candidates.length
            ? tally.candidates.map((c, i) => {
                const g = vPalette[i % vPalette.length];
                return `<div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="${c.is_winner ? 'font-bold text-emerald-700' : 'text-slate-700'}">${c.is_winner ? '🏆 ' : ''}${esc(c.name)}</span>
                        <span class="font-mono text-slate-500">${c.votes} · ${c.percent}%</span>
                    </div>
                    <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full rounded-full" style="width:${c.percent}%;background:linear-gradient(90deg,${g[0]},${g[1]});transition:width .6s"></div></div>
                </div>`;
            }).join('')
            : '<div class="text-sm text-slate-400 text-center py-3">No votes yet.</div>';
        const w = document.getElementById('vWinner');
        if (tally.voting_finished) {
            const winners = tally.candidates.filter(c => c.is_winner).map(c => c.name);
            w.classList.remove('hidden');
            w.innerHTML = `🏆 <b>Winner${winners.length > 1 ? 's' : ''} (${winners.length} seat${tally.seats > 1 ? 's' : ''}):</b> ${winners.length ? winners.map(esc).join(', ') : 'No votes recorded.'}`;
        } else {
            w.classList.add('hidden');
        }
    }
    let voterTimer = null;
    function startVoterResults() {
        voterResults();
        if (!voterTimer) voterTimer = setInterval(voterResults, 2000);
    }

    const btn = document.getElementById('voteBtn');
    if (btn) btn.addEventListener('click', async () => {
        const status = document.getElementById('status');
        const koperasi_id = document.getElementById('koperasi_id').value.trim();
        const picked = document.querySelector('input[name="candidate"]:checked');
        if (!koperasi_id) { status.textContent = 'Please enter your Nombor Ahli.'; status.className = 'text-center text-sm mt-3 text-red-600'; return; }
        if (!picked) { status.textContent = 'Please choose a candidate.'; status.className = 'text-center text-sm mt-3 text-red-600'; return; }
        btn.disabled = true; status.textContent = 'Submitting…'; status.className = 'text-center text-sm mt-3 text-slate-500';
        const res = await window.apiFetch('/vote/' + TOKEN, {
            method: 'POST',
            body: JSON.stringify({ koperasi_id, candidate_id: parseInt(picked.value), device_fingerprint: deviceFingerprint() }),
        });
        const data = await res.json();
        if (res.ok) {
            document.getElementById('ballot').classList.add('hidden');
            document.getElementById('thanks').classList.remove('hidden');
            startVoterResults();
        } else {
            status.textContent = data.message || 'Could not record your vote.'; status.className = 'text-center text-sm mt-3 text-red-600';
            btn.disabled = false;
        }
    });
@endif
</script>
@endpush
