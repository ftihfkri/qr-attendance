@extends('layouts.app')
@section('title', 'Board Election')

@section('content')
<div class="max-w-5xl mx-auto p-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center mb-6">
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/kop-ssb-logo.png') }}" alt="KOP-SSB" class="h-10 w-auto">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight">🗳 Board Election</h1>
                <span id="votingStatus" class="text-xs text-slate-500">Loading…</span>
            </div>
        </div>
        <a href="/admin" class="bg-gray-200 px-3 sm:px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">← Dashboard</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- LEFT: candidates + controls -->
        <div class="space-y-6">
            <!-- Candidates -->
            <div class="bg-white p-5 rounded-lg shadow">
                <h2 class="text-lg font-semibold mb-1">1 · Candidates <span id="candCount" class="text-sm font-normal text-gray-500"></span></h2>
                <p class="text-xs text-gray-400 mb-3">Only members who have <b>checked in</b> can be nominated.</p>
                <div id="candChips" class="flex flex-wrap gap-2 mb-4"></div>

                <input id="candSearch" type="text" placeholder="Search checked-in members by name or Nombor Ahli…" class="w-full p-2 border border-gray-300 rounded mb-3">
                <div id="candPool" class="max-h-64 overflow-y-auto divide-y border border-gray-200 rounded"></div>
                <div id="candMsg" class="text-sm mt-2 min-h-5"></div>
            </div>

            <!-- Voting window -->
            <div class="bg-white p-5 rounded-lg shadow">
                <h2 class="text-lg font-semibold mb-3">2 · Voting Window</h2>
                <div class="grid grid-cols-2 gap-3 items-end mb-4">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Seats — top-N win &amp; each voter picks N</label>
                        <input id="seats" type="number" min="1" value="1" class="w-full p-2 border border-gray-300 rounded">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Timer (minutes, optional)</label>
                        <input id="duration" type="number" min="1" placeholder="e.g. 15" class="w-full p-2 border border-gray-300 rounded">
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button id="openBtn" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 text-sm">▶ Open Voting &amp; Display</button>
                    <button id="closeBtn" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 text-sm hidden">■ Close Voting</button>
                    <button id="saveBtn" class="bg-gray-200 px-4 py-2 rounded hover:bg-gray-300 text-sm">Save seats/timer</button>
                    <a id="liveLink" href="#" target="_blank" class="bg-amber-500 text-white px-4 py-2 rounded hover:bg-amber-600 text-sm hidden">Open live page ↗</a>
                    <a id="stationLink" href="#" target="_blank" class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700 text-sm hidden">Open voting station ↗</a>
                </div>
                <p id="autoClose" class="text-xs text-gray-500 mt-2"></p>
                <div id="voteMsg" class="text-sm mt-2 min-h-5"></div>
            </div>
        </div>

        <!-- RIGHT: QR + live results -->
        <div class="space-y-6">
            <div class="bg-white p-6 rounded-lg shadow text-center">
                <h2 class="text-lg font-semibold mb-3">Voting QR</h2>
                <div id="qrcode" class="flex justify-center mb-3"></div>
                <p id="voteUrl" class="text-xs text-gray-500 break-all"></p>
                <p id="qrHint" class="text-xs text-gray-400 mt-2">Open voting to generate the voting QR code.</p>
            </div>

            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex justify-between items-baseline mb-4">
                    <h2 class="text-lg font-semibold">Live Results</h2>
                    <span id="resultMeta" class="text-xs font-mono text-gray-500">0 votes · 0 eligible</span>
                </div>
                <div id="resultBars" class="space-y-3"></div>
                <div id="winnerBanner" class="hidden mt-4 bg-green-50 border border-green-300 rounded-lg px-4 py-3 text-sm text-green-800"></div>
                <div class="mt-4 pt-3 border-t flex flex-wrap items-center justify-between gap-2">
                    <button id="restartBtn" class="bg-amber-100 text-amber-800 text-xs px-3 py-1.5 rounded hover:bg-amber-200 font-medium">♻ Restart voting (clear votes, keep QR)</button>
                    <button id="clearBtn" class="text-red-500 text-xs hover:underline">Clear election (start fresh)</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs@master/qrcode.min.js"></script>
<script>
    const palette = ['#4f46e5','#16a34a','#0ea5e9','#d97706','#db2777','#7c3aed','#0d9488','#dc2626'];
    let meeting = {}, qr = null, lastToken = null, seatsDirty = false;
    function esc(s){ return String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

    // ---- Candidate pool search ----
    let pool = [];
    async function loadPool() {
        const q = document.getElementById('candSearch').value.trim();
        const res = await window.apiFetch('/admin/election/candidates/search?q=' + encodeURIComponent(q));
        const { data } = await res.json();
        pool = data || [];
        renderPool();
    }
    function renderPool() {
        const box = document.getElementById('candPool');
        if (!pool.length) { box.innerHTML = '<div class="p-3 text-sm text-gray-500">No checked-in members match.</div>'; return; }
        box.innerHTML = pool.map(u => `<div class="flex justify-between items-center p-2 hover:bg-indigo-50">
            <span class="truncate"><span class="font-medium">${esc(u.name)}</span> <span class="text-gray-400 text-sm">${esc(u.koperasi_id)}</span></span>
            ${u.is_candidate
                ? '<span class="text-xs text-green-600 font-semibold shrink-0">✓ candidate</span>'
                : `<button class="add-btn shrink-0 bg-green-600 text-white text-xs px-3 py-1 rounded hover:bg-green-700" data-id="${esc(u.koperasi_id)}">+ Add</button>`}
        </div>`).join('');
        box.querySelectorAll('.add-btn').forEach(b => b.addEventListener('click', () => addCandidate(b.dataset.id, b)));
    }
    async function addCandidate(koperasiId, btn) {
        btn.disabled = true; btn.textContent = '…';
        const res = await window.apiFetch('/admin/election/candidates', { method: 'POST', body: JSON.stringify({ koperasi_id: koperasiId }) });
        const data = await res.json();
        const msg = document.getElementById('candMsg');
        msg.textContent = data.message || ''; msg.className = 'text-sm mt-2 ' + (res.ok ? 'text-green-600' : 'text-red-600');
        await loadResults(); await loadPool();
    }
    async function removeCandidate(id) {
        await window.apiFetch('/admin/election/candidates/' + id, { method: 'DELETE' });
        await loadResults(); await loadPool();
    }
    document.getElementById('candSearch').addEventListener('input', loadPool);

    // ---- Voting controls ----
    async function setVoting(action) {
        const body = { action, vote_seats: parseInt(document.getElementById('seats').value) || 1 };
        const dur = parseInt(document.getElementById('duration').value);
        if (dur > 0) body.duration_min = dur;
        const res = await window.apiFetch('/admin/election/voting', { method: 'POST', body: JSON.stringify(body) });
        const data = await res.json();
        const msg = document.getElementById('voteMsg');
        msg.textContent = data.message || ''; msg.className = 'text-sm mt-2 ' + (res.ok ? 'text-green-600' : 'text-red-600');
        if (res.ok && data.meeting) {
            meeting = data.meeting;
            seatsDirty = false; // saved — let the poll sync the box again
            if (action === 'open' && meeting.vote_token) {
                window.open(window.location.origin + '/vote/' + meeting.vote_token + '?display=1', '_blank');
            }
        }
        await loadResults();
    }
    document.getElementById('openBtn').addEventListener('click', () => setVoting('open'));
    document.getElementById('closeBtn').addEventListener('click', () => setVoting('close'));
    document.getElementById('saveBtn').addEventListener('click', () => setVoting('update'));

    // Keep the seats box from being reset by the 2s poll: mark it dirty while editing,
    // and persist the value as soon as the user leaves the field so it sticks.
    const seatsInput = document.getElementById('seats');
    seatsInput.addEventListener('input', () => { seatsDirty = true; });
    seatsInput.addEventListener('change', () => {
        if ((parseInt(seatsInput.value) || 0) < 1) seatsInput.value = 1;
        setVoting('update');
    });

    // ---- Render QR ----
    function renderQR(token) {
        const url = window.location.origin + '/vote/' + token;
        document.getElementById('voteUrl').textContent = url;
        document.getElementById('liveLink').href = url + '?display=1';
        document.getElementById('stationLink').href = url + '?station=1';
        document.getElementById('qrHint').classList.add('hidden');
        if (token !== lastToken) {
            document.getElementById('qrcode').innerHTML = '';
            qr = new QRCode(document.getElementById('qrcode'), { text: url, width: 220, height: 220 });
            lastToken = token;
        }
    }

    // ---- Poll candidates + state + results ----
    async function loadResults() {
        const res = await window.apiFetch('/admin/election/results');
        const { meeting: m, candidates, tally, eligible_voters } = await res.json();
        meeting = m;

        // status line
        const st = document.getElementById('votingStatus');
        if (m.voting_active) { st.textContent = '● Voting OPEN'; st.className = 'text-xs text-green-600 font-semibold'; }
        else if (m.voting_finished) { st.textContent = 'Voting closed'; st.className = 'text-xs text-red-600 font-semibold'; }
        else { st.textContent = 'Voting not started'; st.className = 'text-xs text-slate-500'; }

        // candidate chips
        document.getElementById('candCount').textContent = '(' + candidates.length + ')';
        const chips = document.getElementById('candChips');
        chips.innerHTML = candidates.length
            ? candidates.map(c => `<span class="inline-flex items-center gap-2 bg-slate-100 border border-slate-200 rounded-full pl-3 pr-1.5 py-1">
                <span class="text-sm font-medium">${esc(c.name)}</span>
                <button class="rm-btn w-5 h-5 rounded-full bg-red-100 text-red-600 text-xs leading-none hover:bg-red-200" data-id="${c.candidate_id}" title="Remove">✕</button>
              </span>`).join('')
            : '<span class="text-sm text-gray-400">No candidates yet — add checked-in members below.</span>';
        chips.querySelectorAll('.rm-btn').forEach(b => b.addEventListener('click', () => removeCandidate(b.dataset.id)));

        // controls reflect state
        document.getElementById('openBtn').classList.toggle('hidden', m.voting_active);
        document.getElementById('closeBtn').classList.toggle('hidden', !m.voting_active);
        document.getElementById('liveLink').classList.toggle('hidden', !m.vote_token);
        document.getElementById('stationLink').classList.toggle('hidden', !m.vote_token);
        if (!seatsDirty && document.activeElement?.id !== 'seats') document.getElementById('seats').value = m.vote_seats || 1;
        document.getElementById('autoClose').textContent =
            (m.vote_ends_at && m.voting_active) ? 'Auto-closes at ' + m.vote_ends_at.replace('T', ' ') : '';

        if (m.vote_token) {
            renderQR(m.vote_token);
        } else {
            lastToken = null;
            document.getElementById('qrcode').innerHTML = '';
            document.getElementById('voteUrl').textContent = '';
            document.getElementById('qrHint').classList.remove('hidden');
        }

        // results bars
        document.getElementById('resultMeta').textContent = `${tally.total_votes} votes · ${eligible_voters} eligible`;
        const bars = document.getElementById('resultBars');
        bars.innerHTML = (tally.candidates.length)
            ? tally.candidates.map((c, i) => `<div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="${c.is_winner ? 'font-bold' : ''}">${c.is_winner ? '🏆 ' : ''}${esc(c.name)}</span>
                    <span class="font-mono text-gray-500">${c.votes} · ${c.percent}%</span>
                </div>
                <div class="h-2.5 bg-gray-100 rounded overflow-hidden"><div class="h-full rounded" style="width:${c.percent}%;background:${palette[i % palette.length]};transition:width .4s"></div></div>
              </div>`).join('')
            : '<div class="text-sm text-gray-400 text-center py-3">No candidates / votes yet.</div>';

        // winner banner
        const banner = document.getElementById('winnerBanner');
        if (tally.voting_finished) {
            const winners = tally.candidates.filter(c => c.is_winner).map(c => c.name);
            banner.classList.remove('hidden');
            const tie = tally.tie_at_cutoff ? '<div class="mt-1 text-amber-700 font-semibold">⚖ Tie for the last seat — runoff or manual decision needed.</div>' : '';
            banner.innerHTML = `🏆 <b>Winners (${winners.length} seat${tally.seats > 1 ? 's' : ''}):</b> ${winners.length ? winners.map(esc).join(', ') : 'No votes recorded.'}${tie}`;
        } else {
            banner.classList.add('hidden');
        }
    }

    // ---- Restart voting: clear votes only, keep the same QR + candidates ----
    document.getElementById('restartBtn').addEventListener('click', async () => {
        if (!confirm('Restart voting? This clears ALL current votes so everyone can vote again — but keeps the candidates and the SAME QR code. Use this if voting was started by mistake.')) return;
        const res = await window.apiFetch('/admin/election/reset-votes', { method: 'POST' });
        const data = await res.json();
        const msg = document.getElementById('voteMsg');
        msg.textContent = data.message || ''; msg.className = 'text-sm mt-2 ' + (res.ok ? 'text-green-600' : 'text-red-600');
        await loadResults();
    });

    // ---- Clear election (start fresh) — like the attendance Clear list ----
    document.getElementById('clearBtn').addEventListener('click', async () => {
        if (!confirm('Clear the entire election? This removes ALL candidates and votes and resets the voting window. This cannot be undone.')) return;
        const res = await window.apiFetch('/admin/election/clear', { method: 'POST' });
        const data = await res.json();
        const msg = document.getElementById('voteMsg');
        msg.textContent = data.message || ''; msg.className = 'text-sm mt-2 ' + (res.ok ? 'text-green-600' : 'text-red-600');
        if (res.ok) { document.getElementById('duration').value = ''; document.getElementById('seats').value = 1; seatsDirty = false; }
        await loadResults(); await loadPool();
    });

    loadResults();
    loadPool();
    setInterval(loadResults, 2000);
</script>
@endpush
