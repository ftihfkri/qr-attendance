@extends('layouts.app')
@section('title', 'Board Election — Vote')

@section('content')
@php($voteUrl = url('/vote/' . $token))
<div class="min-h-screen p-4" style="background:linear-gradient(135deg,#eef2ff 0%,#f5f3ff 100%)">

@if ($display)
    {{-- ───────── Projector display: big QR + live results ───────── --}}
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-6 pt-4">
            <h1 class="text-3xl font-bold text-slate-900">🗳 {{ $meeting->title ?? 'Board Election' }}</h1>
            <p id="dStatus" class="text-slate-500 mt-1">Loading…</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-2xl shadow-card p-6 text-center">
                <h2 class="text-lg font-semibold mb-3">Scan to vote</h2>
                <div id="qrcode" class="flex justify-center mb-3"></div>
                <p class="text-xs text-slate-500 break-all">{{ $voteUrl }}</p>
                <p class="text-xs text-slate-400 mt-2">You must be checked in. Candidates cannot vote.</p>
            </div>
            <div class="bg-white rounded-2xl shadow-card p-6">
                <div class="flex justify-between items-baseline mb-4">
                    <h2 class="text-lg font-semibold">Live Results</h2>
                    <span id="dMeta" class="text-xs font-mono text-slate-500"></span>
                </div>
                <div id="dBars" class="space-y-3"></div>
                <div id="dWinner" class="hidden mt-4 bg-green-50 border border-green-300 rounded-lg px-4 py-3 text-sm text-green-800"></div>
            </div>
        </div>
    </div>
@else
    {{-- ───────── Voter ballot ───────── --}}
    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-2xl shadow-card p-8 border border-slate-100 mt-6">
            <div class="text-center mb-6">
                <div class="w-14 h-14 bg-amber-500 rounded-2xl flex items-center justify-center mx-auto mb-4 text-white text-2xl shadow-sm">🗳</div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $meeting->title ?? 'Board Election' }}</h1>
                <p class="text-slate-500 text-sm mt-1">Enter your Nombor Ahli and choose one candidate.</p>
            </div>

            <div id="ballot">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Nombor Ahli / Nombor Anggota</label>
                    <input id="koperasi_id" autocomplete="off" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 outline-none transition" placeholder="Your member number">
                </div>

                <p class="text-sm font-medium text-slate-700 mb-2">Candidates</p>
                @if ($candidates->isEmpty())
                    <p class="text-sm text-slate-400 mb-4">No candidates have been nominated yet.</p>
                @else
                <div class="space-y-2 mb-5">
                    @foreach ($candidates as $c)
                    <label class="flex items-center gap-3 p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-amber-50 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50 transition">
                        <input type="radio" name="candidate" value="{{ $c['candidate_id'] }}" class="accent-amber-500 w-4 h-4">
                        <span class="text-slate-800 font-medium">{{ $c['name'] }}</span>
                    </label>
                    @endforeach
                </div>
                <button id="voteBtn" class="w-full bg-amber-500 text-white py-2.5 rounded-lg hover:bg-amber-600 active:bg-amber-700 font-semibold shadow-sm transition disabled:opacity-50">Submit Vote</button>
                @endif
                <p id="status" class="text-center text-sm mt-3 min-h-5"></p>
            </div>

            <div id="thanks" class="hidden text-center py-6">
                <div class="w-14 h-14 bg-green-100 rounded-2xl flex items-center justify-center mx-auto mb-4 text-green-600 text-2xl">✓</div>
                <h2 class="text-lg font-semibold text-slate-900">Vote recorded</h2>
                <p class="text-slate-500 text-sm mt-1">Thank you for voting.</p>
            </div>
        </div>
    </div>
@endif
</div>
@endsection

@push('scripts')
@if ($display)
<script src="https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs@master/qrcode.min.js"></script>
@endif
<script>
    const TOKEN = @json($token);
    const palette = ['#4f46e5','#16a34a','#0ea5e9','#d97706','#db2777','#7c3aed','#0d9488','#dc2626'];
    function esc(s){ return String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

@if ($display)
    new QRCode(document.getElementById('qrcode'), { text: @json($voteUrl), width: 240, height: 240 });
    async function poll() {
        const res = await fetch('/vote/' + TOKEN + '/results', { headers: { 'Accept': 'application/json' } });
        if (!res.ok) return;
        const { tally, eligible_voters } = await res.json();
        const st = document.getElementById('dStatus');
        st.textContent = tally.voting_active ? '● Voting is OPEN' : (tally.voting_finished ? 'Voting closed' : 'Voting not started');
        document.getElementById('dMeta').textContent = `${tally.total_votes} votes · ${eligible_voters} eligible`;
        document.getElementById('dBars').innerHTML = tally.candidates.length
            ? tally.candidates.map((c, i) => `<div>
                <div class="flex justify-between text-sm mb-1"><span class="${c.is_winner?'font-bold':''}">${c.is_winner?'🏆 ':''}${esc(c.name)}</span><span class="font-mono text-slate-500">${c.votes} · ${c.percent}%</span></div>
                <div class="h-3 bg-slate-100 rounded overflow-hidden"><div class="h-full rounded" style="width:${c.percent}%;background:${palette[i%palette.length]};transition:width .4s"></div></div>
              </div>`).join('')
            : '<div class="text-sm text-slate-400 text-center py-3">No candidates / votes yet.</div>';
        const w = document.getElementById('dWinner');
        if (tally.voting_finished) {
            const winners = tally.candidates.filter(c => c.is_winner).map(c => c.name);
            w.classList.remove('hidden');
            w.innerHTML = `🏆 <b>Winners (${winners.length} seat${tally.seats>1?'s':''}):</b> ${winners.length ? winners.map(esc).join(', ') : 'No votes recorded.'}`;
        } else { w.classList.add('hidden'); }
    }
    poll(); setInterval(poll, 2000);
@else
    // Lightweight device fingerprint (stored once per browser).
    function fingerprint() {
        let fp = localStorage.getItem('vote_fp');
        if (!fp) { fp = (navigator.userAgent + '|' + screen.width + 'x' + screen.height + '|' + Math.random().toString(36).slice(2)); localStorage.setItem('vote_fp', fp); }
        return fp.slice(0, 255);
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
            body: JSON.stringify({ koperasi_id, candidate_id: parseInt(picked.value), device_fingerprint: fingerprint() }),
        });
        const data = await res.json();
        if (res.ok) {
            document.getElementById('ballot').classList.add('hidden');
            document.getElementById('thanks').classList.remove('hidden');
        } else {
            status.textContent = data.message || 'Could not record your vote.'; status.className = 'text-center text-sm mt-3 text-red-600';
            btn.disabled = false;
        }
    });
@endif
</script>
@endpush
