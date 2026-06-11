@extends('layouts.app')
@section('title', 'Attendance Check-In')

@section('content')
<div class="min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-lg p-8 w-full max-w-md">
        <div class="text-center mb-6">
            <div class="w-14 h-14 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <span class="text-2xl">📋</span>
            </div>
            <h1 class="text-2xl font-bold">Attendance Check-In</h1>
            <p class="text-gray-500 text-sm mt-1">Please fill in your details</p>
        </div>

        <form id="form" class="space-y-4">
            <div class="relative">
                <label class="block text-sm font-medium mb-1">Full Name</label>
                <input id="name" autocomplete="off" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                <ul id="nameSuggestions" class="absolute z-20 left-0 right-0 bg-white border border-gray-200 rounded-lg shadow-lg mt-1 max-h-56 overflow-y-auto hidden"></ul>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Nombor Ahli / Nombor Anggota</label>
                <input id="koperasi_id" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Phone Number</label>
                <input id="phone_number" type="tel" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Email</label>
                <input id="email" type="email" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>
            <button id="submitBtn" class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 font-medium">Submit</button>
            <p id="status" class="text-center text-sm"></p>
        </form>
    </div>
</div>
@endsection

@push('scripts')
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
        const parts = [navigator.userAgent, navigator.language, screen.width + 'x' + screen.height,
            Intl.DateTimeFormat().resolvedOptions().timeZone, navigator.hardwareConcurrency || ''].join('|');
        let h = 0;
        for (let i = 0; i < parts.length; i++) { h = (h * 31 + parts.charCodeAt(i)) >>> 0; }
        fp = h.toString(16).padStart(8, '0');
        localStorage.setItem(key, fp);
        return fp;
    }

    function getLocation() {
        return new Promise((resolve) => {
            if (!navigator.geolocation) return resolve({ lat: null, lng: null });
            navigator.geolocation.getCurrentPosition(
                p => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }),
                () => resolve({ lat: null, lng: null }),
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });
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
@endpush
