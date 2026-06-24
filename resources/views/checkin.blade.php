@extends('layouts.app')
@section('title', 'Attendance Check-In')

@section('content')
<div class="min-h-screen flex items-center justify-center p-4"
     style="background:linear-gradient(135deg,#eef2ff 0%,#f5f3ff 100%)">
    <div class="bg-white rounded-2xl shadow-card p-8 w-full max-w-md border border-slate-100">
        <div class="text-center mb-7">
            <div class="w-14 h-14 bg-brand-600 rounded-2xl flex items-center justify-center mx-auto mb-4 text-white shadow-sm">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Attendance Check-In</h1>
            <p class="text-slate-500 text-sm mt-1">Start typing your name and pick yourself from the list.</p>
        </div>

        @unless ($open)
        <div class="text-center py-6">
            <div class="w-14 h-14 bg-red-100 rounded-2xl flex items-center justify-center mx-auto mb-4 text-red-600">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"/></svg>
            </div>
            <h2 class="text-lg font-semibold text-slate-900">Check-in is closed</h2>
            <p class="text-slate-500 text-sm mt-1">{{ $closedReason }}</p>
        </div>
        @else
        <form id="form" class="space-y-4">
            <div class="relative">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Full Name</label>
                <input id="name" autocomplete="off" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 outline-none transition" placeholder="Type your name…">
                <ul id="nameSuggestions" class="absolute z-20 left-0 right-0 bg-white border border-slate-200 rounded-lg shadow-lg mt-1 max-h-56 overflow-y-auto hidden"></ul>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Nombor Ahli / Nombor Anggota</label>
                <input id="koperasi_id" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 outline-none transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Phone Number</label>
                <input id="phone_number" type="tel" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 outline-none transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                <input id="email" type="email" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 outline-none transition">
            </div>
            <button id="submitBtn" class="w-full bg-brand-600 text-white py-2.5 rounded-lg hover:bg-brand-700 active:bg-brand-800 font-semibold shadow-sm transition focus:ring-2 focus:ring-brand-500/40 outline-none">Submit</button>
            <p id="status" class="text-center text-sm"></p>
        </form>
        @endunless
    </div>
</div>

@if ($open)
<!-- Pre-permission priming popup (Malay): shown before the browser's native location prompt -->
<div id="locModal" class="fixed inset-0 z-[90] hidden items-center justify-center p-4 bg-black/50">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 text-center">
        <div class="w-14 h-14 bg-brand-100 text-brand-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
        </div>
        <h3 class="text-lg font-bold text-slate-900">Allow Location Access</h3>
        <p class="text-xs text-slate-400 mb-3">Benarkan Akses Lokasi</p>
        <p class="text-sm text-slate-600 leading-relaxed">
            To confirm you're at the meeting venue, we need access to your device's location.
            After you tap <b>“Allow Location”</b>, your browser will ask for permission — please choose <b>“Allow”</b>.
        </p>
        <p class="text-sm text-slate-500 leading-relaxed mt-2">
            Untuk mengesahkan kehadiran anda di lokasi mesyuarat, kami memerlukan akses lokasi peranti anda.
            Selepas menekan <b>“Allow Location”</b>, pelayar akan meminta kebenaran — sila pilih <b>“Allow”</b>.
        </p>
        <p class="text-xs text-slate-400 mt-3">Used only to verify attendance, not shared · Hanya untuk pengesahan kehadiran, tidak dikongsi.</p>
        <button id="locAllowBtn" class="w-full mt-5 bg-brand-600 text-white py-2.5 rounded-lg hover:bg-brand-700 font-semibold shadow-sm transition">Allow Location · Benarkan</button>
        <button id="locSkipBtn" class="w-full mt-2 text-slate-400 text-sm hover:text-slate-600 transition">Continue without location · Teruskan tanpa lokasi</button>
    </div>
</div>
@endif
@endsection

@push('scripts')
@if ($open)
<script>
    // ---- Name autocomplete from the membership roster (fills the Koperasi ID) ----
    const nameInput = document.getElementById('name');
    const koperasiInput = document.getElementById('koperasi_id');
    const suggestionsBox = document.getElementById('nameSuggestions');

    function escHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    function hideSuggestions() { suggestionsBox.classList.add('hidden'); suggestionsBox.innerHTML = ''; }

    let searchTimer = null;
    nameInput.addEventListener('input', () => {
        const q = nameInput.value.trim();
        clearTimeout(searchTimer);
        if (q.length < 2) { hideSuggestions(); return; }
        searchTimer = setTimeout(async () => {
            try {
                const res = await fetch('/checkin/members?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) { hideSuggestions(); return; }
                const { data } = await res.json();
                if (!data || !data.length) { hideSuggestions(); return; }
                suggestionsBox.innerHTML = data.map(m =>
                    `<li class="px-3 py-2 hover:bg-indigo-50 cursor-pointer text-sm flex justify-between gap-2" data-name="${escHtml(m.name)}" data-id="${escHtml(m.member_id)}">
                        <span class="font-medium truncate">${escHtml(m.name)}</span>
                        <span class="text-gray-400 shrink-0">${escHtml(m.member_id)}</span>
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
    // Hide the dropdown when clicking elsewhere.
    document.addEventListener('click', (e) => {
        if (e.target !== nameInput && !suggestionsBox.contains(e.target)) hideSuggestions();
    });

    function deviceFingerprint() {
        const key = 'device-fp';
        let fp = localStorage.getItem(key);
        if (fp) return fp;
        // A random per-device ID — NOT derived from device attributes — so two
        // identical phones (same model/OS/region) never collide. Persisted so the
        // same browser keeps the same ID across visits.
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

    // Pre-permission priming: explain in Malay first, then trigger the native prompt.
    let cachedLoc = null;
    const locModal = document.getElementById('locModal');
    function showLocModal() { if (locModal) { locModal.classList.remove('hidden'); locModal.classList.add('flex'); } }
    function hideLocModal() { if (locModal) { locModal.classList.add('hidden'); locModal.classList.remove('flex'); } }

    function requestLocation() {
        return new Promise((resolve) => {
            if (!navigator.geolocation) return resolve({ lat: null, lng: null });
            navigator.geolocation.getCurrentPosition(
                p => { cachedLoc = { lat: p.coords.latitude, lng: p.coords.longitude }; resolve(cachedLoc); },
                () => resolve({ lat: null, lng: null }),
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });
    }

    // The browser's permission prompt is only fired AFTER the user taps "Benarkan".
    document.getElementById('locAllowBtn')?.addEventListener('click', async () => {
        hideLocModal();
        await requestLocation();
    });
    document.getElementById('locSkipBtn')?.addEventListener('click', hideLocModal);
    showLocModal();

    // Reuse the location captured by the popup; only ask again if we don't have it yet.
    function getLocation() {
        return cachedLoc ? Promise.resolve(cachedLoc) : requestLocation();
    }

    document.getElementById('form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const status = document.getElementById('status');
        const btn = document.getElementById('submitBtn');
        const name = document.getElementById('name').value.trim();
        const koperasi_id = document.getElementById('koperasi_id').value.trim();
        const phone_number = document.getElementById('phone_number').value.trim();
        const email = document.getElementById('email').value.trim();
        if (!name || !koperasi_id || !phone_number || !email) {
            status.textContent = 'Please fill in all fields (name, Nombor Ahli / Anggota, phone, email).';
            status.className = 'text-center text-sm text-red-600';
            return;
        }
        btn.disabled = true; btn.textContent = 'Submitting…';
        status.textContent = 'Getting your location…';
        status.className = 'text-center text-sm text-gray-500';
        const loc = await getLocation();
        try {
            const res = await window.apiFetch('/checkin', {
                method: 'POST',
                body: JSON.stringify({
                    name, koperasi_id, phone_number, email,
                    device_fingerprint: deviceFingerprint(),
                    location_lat: loc.lat, location_lng: loc.lng,
                }),
            });
            const data = await res.json();
            if (!res.ok) {
                status.textContent = data.message || 'Submission failed.';
                status.className = 'text-center text-sm text-red-600';
                btn.disabled = false; btn.textContent = 'Submit';
                return;
            }
            status.textContent = data.message || 'Done!';
            status.className = 'text-center text-sm text-green-600';
            document.getElementById('form').reset();
            btn.textContent = 'Submitted ✓';
        } catch (err) {
            status.textContent = 'Network error. Please try again.';
            status.className = 'text-center text-sm text-red-600';
            btn.disabled = false; btn.textContent = 'Submit';
        }
    });
</script>
@endif
@endpush
