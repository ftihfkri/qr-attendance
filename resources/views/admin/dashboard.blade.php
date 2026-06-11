@extends('layouts.app')
@section('title', 'Attendance Dashboard')

@section('content')
<div class="max-w-5xl mx-auto p-4">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Attendance Dashboard</h1>
        <div class="flex gap-2 items-center">
            <span class="text-sm text-gray-500">{{ auth()->user()->username }} ({{ auth()->user()->role }})</span>
            <a href="/admin/verify" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm">Verify / Search</a>
            @if (auth()->user()->isAdmin())
                <a href="/admin/users" class="bg-gray-200 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">Users</a>
            @endif
            <form method="POST" action="/logout">@csrf
                <button class="bg-gray-200 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">Logout</button>
            </form>
        </div>
    </div>

    <!-- Venue -->
    <div class="bg-white p-4 rounded-lg shadow mb-6">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs text-gray-600 mb-1">Geofence radius (m)</label>
                <input id="radius" type="number" min="10" value="{{ $meeting->radius_meters }}" class="w-28 p-2 border border-gray-300 rounded">
            </div>
            <button id="venueBtn" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">📍 Use my current location as venue</button>
            <span id="venueStatus" class="text-sm text-gray-500">Venue: loading…</span>
        </div>
        <p class="text-xs text-gray-400 mt-2">Set this at the venue. Submissions are only accepted within the radius of this point.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- QR -->
        <div class="bg-white p-6 rounded-lg shadow text-center">
            <h2 class="text-lg font-semibold mb-4">Scan to check in</h2>
            <div id="qrcode" class="flex justify-center mb-3"></div>
            <p class="text-xs text-gray-500 break-all" id="checkinUrl"></p>
        </div>

        <!-- List -->
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold">Submitted <span id="count" class="text-sm font-normal text-gray-500"></span></h2>
                <div class="flex gap-2">
                    <a href="/admin/export" class="bg-green-600 text-white px-3 py-1.5 rounded text-sm hover:bg-green-700">Download Excel</a>
                    <button id="refreshBtn" class="bg-indigo-600 text-white px-3 py-1.5 rounded text-sm hover:bg-indigo-700">Refresh</button>
                </div>
            </div>
            <div class="overflow-x-auto max-h-96 overflow-y-auto">
                <table class="min-w-full border border-gray-300 text-sm">
                    <thead class="bg-gray-100 sticky top-0">
                        <tr><th class="px-3 py-2 border text-left">#</th><th class="px-3 py-2 border text-left">Full Name</th><th class="px-3 py-2 border text-left">Koperasi ID</th><th class="px-3 py-2 border text-left">Method</th><th class="px-3 py-2 border text-left">Edit</th></tr>
                    </thead>
                    <tbody id="listBody"></tbody>
                </table>
            </div>
            <div class="mt-3 text-right">
                <button id="clearBtn" class="text-red-500 text-xs hover:underline">Clear list (start fresh)</button>
            </div>
        </div>
    </div>

    <!-- Manual add -->
    <div class="bg-white p-4 rounded-lg shadow mt-6">
        <h2 class="text-lg font-semibold mb-3">Manual Check-In</h2>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2 items-end">
            <div><label class="block text-xs text-gray-600 mb-1">Koperasi ID</label><input id="m_koperasi" class="w-full p-2 border border-gray-300 rounded"></div>
            <div><label class="block text-xs text-gray-600 mb-1">Name</label><input id="m_name" class="w-full p-2 border border-gray-300 rounded"></div>
            <div><label class="block text-xs text-gray-600 mb-1">Phone</label><input id="m_phone" class="w-full p-2 border border-gray-300 rounded"></div>
            <div><label class="block text-xs text-gray-600 mb-1">Email</label><input id="m_email" type="email" class="w-full p-2 border border-gray-300 rounded"></div>
            <button id="manualBtn" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Check In</button>
        </div>
        <div id="manualMsg" class="text-sm mt-2"></div>
    </div>

    <!-- Membership roster upload -->
    <div class="bg-white p-4 rounded-lg shadow mt-6">
        <h2 class="text-lg font-semibold mb-1">Upload Membership Roster</h2>
        <p class="text-xs text-gray-400 mb-3">Excel (.xlsx) or CSV with two columns: <b>name</b> and <b>membership_id</b>. A header row is optional. Existing IDs are skipped.</p>
        <div class="flex flex-wrap items-center gap-3">
            <input id="memberFile" type="file" accept=".xlsx,.csv" class="text-sm">
            <button id="uploadMembersBtn" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 disabled:opacity-50">Upload</button>
        </div>
        <div id="uploadMsg" class="text-sm mt-2 min-h-5"></div>
    </div>

    <!-- Edit modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4">Edit Submission</h3>
            <input type="hidden" id="e_id">
            <div class="space-y-3">
                <div><label class="block text-xs text-gray-600 mb-1">Koperasi ID</label><input id="e_koperasi" class="w-full p-2 border border-gray-300 rounded"></div>
                <div><label class="block text-xs text-gray-600 mb-1">Full Name</label><input id="e_name" class="w-full p-2 border border-gray-300 rounded"></div>
                <div><label class="block text-xs text-gray-600 mb-1">Phone</label><input id="e_phone" class="w-full p-2 border border-gray-300 rounded"></div>
                <div><label class="block text-xs text-gray-600 mb-1">Email</label><input id="e_email" type="email" class="w-full p-2 border border-gray-300 rounded"></div>
            </div>
            <div id="editMsg" class="text-sm mt-3"></div>
            <div class="flex justify-end gap-2 mt-4">
                <button id="editCancel" class="px-4 py-2 border rounded hover:bg-gray-50">Cancel</button>
                <button id="editSave" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Save</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs@master/qrcode.min.js"></script>
<script>
    // QR of the public check-in URL (client-side render; works on any domain)
    const checkinUrl = window.location.origin + '/checkin';
    document.getElementById('checkinUrl').textContent = checkinUrl;
    new QRCode(document.getElementById('qrcode'), { text: checkinUrl, width: 240, height: 240 });

    function setVenueStatus(d) {
        const el = document.getElementById('venueStatus');
        if (d && d.venue_lat != null && d.venue_lng != null) {
            el.textContent = `Venue set: ${Number(d.venue_lat).toFixed(5)}, ${Number(d.venue_lng).toFixed(5)} (±${d.radius_meters}m)`;
            el.className = 'text-sm text-green-600';
        } else {
            el.textContent = 'No venue set — geofence OFF (anyone can submit)';
            el.className = 'text-sm text-orange-600';
        }
    }

    async function loadVenue() {
        const res = await window.apiFetch('/admin/venue');
        const { data } = await res.json();
        if (data.radius_meters) document.getElementById('radius').value = data.radius_meters;
        setVenueStatus(data);
    }

    document.getElementById('venueBtn').addEventListener('click', () => {
        const el = document.getElementById('venueStatus');
        if (!navigator.geolocation) { el.textContent = 'Geolocation not supported'; return; }
        el.textContent = 'Getting location…';
        navigator.geolocation.getCurrentPosition(async (p) => {
            const res = await window.apiFetch('/admin/venue', {
                method: 'POST',
                body: JSON.stringify({ venue_lat: p.coords.latitude, venue_lng: p.coords.longitude, radius_meters: parseInt(document.getElementById('radius').value) || 100 }),
            });
            const { data } = await res.json();
            setVenueStatus(data);
        }, (err) => { el.textContent = 'Location error: ' + err.message; el.className = 'text-sm text-red-600'; },
        { enableHighAccuracy: true, timeout: 10000 });
    });

    let currentList = [];
    function esc(s) { return String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }

    async function loadList() {
        const res = await window.apiFetch('/admin/attendance');
        const { data } = await res.json();
        currentList = data;
        document.getElementById('count').textContent = `(${data.length})`;
        const body = document.getElementById('listBody');
        if (!data.length) { body.innerHTML = '<tr><td colspan="5" class="px-3 py-3 border text-center text-gray-500">No submissions yet.</td></tr>'; return; }
        body.innerHTML = data.map((r, i) => `<tr>
            <td class="px-3 py-2 border">${i + 1}</td>
            <td class="px-3 py-2 border">${esc(r.name)}</td>
            <td class="px-3 py-2 border">${esc(r.koperasi_id)}</td>
            <td class="px-3 py-2 border">${r.method === 'manual' ? '<span class="text-indigo-600">Manual</span>' : '<span class="text-green-600">Scanned</span>'}</td>
            <td class="px-3 py-2 border"><button class="edit-btn text-blue-600 hover:underline" data-idx="${i}">Edit</button></td>
        </tr>`).join('');
        document.querySelectorAll('.edit-btn').forEach(b => b.addEventListener('click', () => openEdit(parseInt(b.dataset.idx))));
    }

    // ---- Edit modal ----
    function openEdit(idx) {
        const r = currentList[idx];
        if (!r) return;
        document.getElementById('e_id').value = r.id;
        document.getElementById('e_koperasi').value = r.koperasi_id ?? '';
        document.getElementById('e_name').value = r.name ?? '';
        document.getElementById('e_phone').value = r.phone_number ?? '';
        document.getElementById('e_email').value = r.email ?? '';
        document.getElementById('editMsg').textContent = '';
        const m = document.getElementById('editModal');
        m.classList.remove('hidden'); m.classList.add('flex');
    }
    function closeEdit() {
        const m = document.getElementById('editModal');
        m.classList.add('hidden'); m.classList.remove('flex');
    }
    document.getElementById('editCancel').addEventListener('click', closeEdit);
    document.getElementById('editSave').addEventListener('click', async () => {
        const id = document.getElementById('e_id').value;
        const payload = {
            koperasi_id: document.getElementById('e_koperasi').value.trim(),
            name: document.getElementById('e_name').value.trim(),
            phone_number: document.getElementById('e_phone').value.trim(),
            email: document.getElementById('e_email').value.trim(),
        };
        const em = document.getElementById('editMsg');
        if (!payload.koperasi_id || !payload.name || !payload.phone_number || !payload.email) {
            em.textContent = 'All fields are required.'; em.className = 'text-sm mt-3 text-red-600'; return;
        }
        const res = await window.apiFetch('/admin/attendance/' + id, { method: 'POST', body: JSON.stringify(payload) });
        const data = await res.json();
        if (!res.ok) { em.textContent = data.message || 'Update failed.'; em.className = 'text-sm mt-3 text-red-600'; return; }
        closeEdit();
        loadList();
    });

    document.getElementById('refreshBtn').addEventListener('click', loadList);

    document.getElementById('clearBtn').addEventListener('click', async () => {
        if (!confirm('Clear the entire list?')) return;
        await window.apiFetch('/admin/clear', { method: 'POST' });
        loadList();
    });

    document.getElementById('manualBtn').addEventListener('click', async () => {
        const msg = document.getElementById('manualMsg');
        const koperasi_id = document.getElementById('m_koperasi').value.trim();
        const name = document.getElementById('m_name').value.trim();
        const phone_number = document.getElementById('m_phone').value.trim();
        const email = document.getElementById('m_email').value.trim();
        if (!koperasi_id || !name || !phone_number || !email) { msg.textContent = 'Fill all fields (including email).'; msg.className = 'text-sm mt-2 text-red-600'; return; }
        const res = await window.apiFetch('/admin/manual', { method: 'POST', body: JSON.stringify({ koperasi_id, name, phone_number, email }) });
        const data = await res.json();
        if (!res.ok) { msg.textContent = data.message || 'Failed'; msg.className = 'text-sm mt-2 text-red-600'; return; }
        msg.textContent = name + ' added.'; msg.className = 'text-sm mt-2 text-green-600';
        document.getElementById('m_koperasi').value = ''; document.getElementById('m_name').value = ''; document.getElementById('m_phone').value = ''; document.getElementById('m_email').value = '';
        loadList();
    });

    // ---- Membership roster upload (multipart; can't use the JSON apiFetch helper) ----
    document.getElementById('uploadMembersBtn').addEventListener('click', async () => {
        const input = document.getElementById('memberFile');
        const msg = document.getElementById('uploadMsg');
        const btn = document.getElementById('uploadMembersBtn');
        const file = input.files[0];
        if (!file) { msg.textContent = 'Choose a .xlsx or .csv file first.'; msg.className = 'text-sm mt-2 text-red-600'; return; }

        const form = new FormData();
        form.append('file', file);
        btn.disabled = true; btn.textContent = 'Uploading…';
        msg.textContent = 'Uploading…'; msg.className = 'text-sm mt-2 text-gray-500';
        try {
            const res = await fetch('/admin/memberships/upload', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: form,
            });
            if (res.status === 401 || res.status === 419) { showSessionExpired(); return; }
            const data = await res.json();
            if (!res.ok || data.status !== 'success') throw new Error(data.message || 'Upload failed.');
            const { added, skipped, errors } = data.data;
            let text = `${added} added, ${skipped} skipped`;
            if (errors.length) text += `, ${errors.length} error${errors.length > 1 ? 's' : ''} — ${errors.slice(0, 5).join('; ')}${errors.length > 5 ? '…' : ''}`;
            msg.textContent = text;
            msg.className = 'text-sm mt-2 ' + (errors.length ? 'text-amber-600' : 'text-green-600');
            input.value = '';
        } catch (e) {
            msg.textContent = e.message || 'Upload failed.'; msg.className = 'text-sm mt-2 text-red-600';
        } finally {
            btn.disabled = false; btn.textContent = 'Upload';
        }
    });

    loadVenue();
    loadList();
    setInterval(loadList, 10000);
</script>
@endpush
