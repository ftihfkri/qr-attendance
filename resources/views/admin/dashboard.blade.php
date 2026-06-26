@extends('layouts.app')
@section('title', 'Attendance Dashboard')

@section('content')
<div class="max-w-5xl mx-auto p-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center mb-6">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight">Attendance Dashboard</h1>
            <span class="text-xs text-slate-500">{{ auth()->user()->username }} · {{ auth()->user()->role }}</span>
        </div>
        <div class="flex flex-wrap gap-2 items-center">
            <a href="/admin/election" class="bg-amber-500 text-white px-3 sm:px-4 py-2 rounded-lg hover:bg-amber-600 text-sm">🗳 Board Election</a>
            <a href="/admin/verify" class="bg-green-600 text-white px-3 sm:px-4 py-2 rounded-lg hover:bg-green-700 text-sm">Verify / Search</a>
            <a href="/admin/upload" class="bg-brand-600 text-white px-3 sm:px-4 py-2 rounded-lg hover:bg-brand-700 text-sm">Upload Roster</a>
            @if (auth()->user()->isAdmin())
                <a href="/admin/users" class="bg-gray-200 px-3 sm:px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">Users</a>
            @endif
            <form method="POST" action="/logout">@csrf
                <button class="bg-gray-200 px-3 sm:px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">Logout</button>
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

    <!-- Submission control -->
    <div class="bg-white p-4 rounded-lg shadow mb-6">
        <div class="flex flex-wrap gap-3 justify-between items-center">
            <div class="flex items-center gap-3">
                <span id="subBadge" class="text-sm font-semibold px-3 py-1 rounded-full bg-gray-100 text-gray-500">Loading…</span>
                <h2 class="text-lg font-semibold">Check-in form</h2>
            </div>
            <button id="subToggle" class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-gray-400">…</button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end mt-4 pt-4 border-t">
            <div>
                <label class="block text-xs text-gray-600 mb-1">Opens at (optional)</label>
                <input id="opensAt" type="datetime-local" class="w-full p-2 border border-gray-300 rounded">
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Closes at (optional)</label>
                <input id="closesAt" type="datetime-local" class="w-full p-2 border border-gray-300 rounded">
            </div>
            <div class="flex gap-2">
                <button id="schedSave" class="bg-brand-600 text-white px-4 py-2 rounded hover:bg-brand-700 text-sm">Save schedule</button>
                <button id="schedClear" class="bg-gray-200 px-4 py-2 rounded hover:bg-gray-300 text-sm">Clear</button>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-2">The form accepts submissions only when it's <b>Open</b> and (if a schedule is set) within the time window. Times use Kuala Lumpur time.</p>
        <div id="subMsg" class="text-sm mt-2 min-h-5"></div>
    </div>

    <!-- Check-in form fields settings (admin + staff) -->
    <div class="bg-white p-4 rounded-lg shadow mb-6">
        <h2 class="text-lg font-semibold mb-1">Check-in form fields</h2>
        <p class="text-xs text-gray-400 mb-3">Full Name and Nombor Ahli are always required. Choose what else to ask for, and add your own columns.</p>
        <div class="flex flex-wrap gap-5 mb-4">
            <label class="inline-flex items-center gap-2 text-sm"><input id="fPhone" type="checkbox" class="w-4 h-4 accent-brand-600"> Phone number required</label>
            <label class="inline-flex items-center gap-2 text-sm"><input id="fEmail" type="checkbox" class="w-4 h-4 accent-brand-600"> Email required</label>
        </div>
        <div class="border-t pt-3">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium">Custom columns</span>
                <button id="addColBtn" class="text-brand-600 text-sm hover:underline">+ Add column</button>
            </div>
            <div id="customCols" class="space-y-2"></div>
            <p class="text-xs text-gray-400 mt-2">Extra questions asked at check-in (e.g. Department, Table No.). Toggle “required” per column. Saved with each check-in and included in the Excel export.</p>
        </div>
        <div class="flex items-center gap-3 mt-4">
            <button id="formSave" class="bg-brand-600 text-white px-4 py-2 rounded hover:bg-brand-700 text-sm">Save form settings</button>
            <span id="formMsg" class="text-sm min-h-5"></span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- QR -->
        <div class="bg-white p-6 rounded-lg shadow text-center">
            <h2 class="text-lg font-semibold mb-4">Scan to check in</h2>
            <div id="qrcode" class="flex justify-center mb-3"></div>
            <p class="text-xs text-gray-500 break-all" id="checkinUrl"></p>
            <button id="printQrBtn" class="mt-4 inline-flex items-center gap-1.5 bg-slate-700 text-white px-4 py-2 rounded-lg hover:bg-slate-800 text-sm">🖨 Print QR</button>
        </div>

        <!-- List -->
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex flex-wrap gap-2 justify-between items-center mb-4">
                <h2 class="text-lg font-semibold">Submitted <span id="count" class="text-sm font-normal text-gray-500"></span></h2>
                <div class="flex gap-2">
                    <a href="/admin/export" class="bg-green-600 text-white px-3 py-1.5 rounded text-sm hover:bg-green-700">Download Excel</a>
                    <button id="refreshBtn" class="bg-brand-600 text-white px-3 py-1.5 rounded text-sm hover:bg-brand-700">Refresh</button>
                </div>
            </div>
            <div class="overflow-x-auto max-h-96 overflow-y-auto">
                <table class="min-w-full border border-gray-300 text-sm">
                    <thead class="bg-gray-100 sticky top-0">
                        <tr><th class="px-3 py-2 border text-left">#</th><th class="px-3 py-2 border text-left">Full Name</th><th class="px-3 py-2 border text-left">Nombor Ahli / Anggota</th><th class="px-3 py-2 border text-left">Method</th><th class="px-3 py-2 border text-left">Edit</th><th class="px-3 py-2 border text-left">Delete</th></tr>
                    </thead>
                    <tbody id="listBody"></tbody>
                </table>
            </div>
            <div class="mt-3 text-right">
                <button id="clearBtn" class="text-red-500 text-xs hover:underline">Clear list (start fresh)</button>
            </div>
        </div>
    </div>

    <!-- Quick check-in: click a not-yet-submitted member from the roster -->
    <div class="bg-white p-4 rounded-lg shadow mt-6">
        <div class="flex justify-between items-center mb-1">
            <h2 class="text-lg font-semibold">Quick Check-In <span id="pendingCount" class="text-sm font-normal text-gray-500"></span></h2>
        </div>
        <p class="text-xs text-gray-400 mb-3">Members from the roster who haven't checked in yet. Search and click <b>Check in</b> to mark them present.</p>
        <input id="memberSearch" type="text" placeholder="Search name or Nombor Ahli / Anggota…" class="w-full p-2 border border-gray-300 rounded mb-3">
        <div id="pendingList" class="max-h-80 overflow-y-auto divide-y border border-gray-200 rounded"></div>
        <div id="quickMsg" class="text-sm mt-2 min-h-5"></div>
    </div>

    <!-- Edit modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4">Edit Submission</h3>
            <input type="hidden" id="e_id">
            <div class="space-y-3">
                <div><label class="block text-xs text-gray-600 mb-1">Nombor Ahli / Nombor Anggota</label><input id="e_koperasi" class="w-full p-2 border border-gray-300 rounded"></div>
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

    <!-- Manual check-in modal: email + phone required before marking attended -->
    <div id="checkinModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-1">Check in member</h3>
            <p id="ci_who" class="text-sm text-gray-500 mb-4"></p>
            <input type="hidden" id="ci_id">
            <div class="space-y-3">
                <div><label class="block text-xs text-gray-600 mb-1">Email <span id="ci_emailReq" class="text-red-500"></span></label><input id="ci_email" type="email" class="w-full p-2 border border-gray-300 rounded" placeholder="name@example.com"></div>
                <div><label class="block text-xs text-gray-600 mb-1">Phone Number <span id="ci_phoneReq" class="text-red-500"></span></label><input id="ci_phone" type="tel" class="w-full p-2 border border-gray-300 rounded" placeholder="01x-xxxxxxx"></div>
                <div id="ci_custom" class="space-y-3"></div>
            </div>
            <div id="ci_msg" class="text-sm mt-3"></div>
            <div class="flex justify-end gap-2 mt-4">
                <button id="ci_cancel" class="px-4 py-2 border rounded hover:bg-gray-50">Cancel</button>
                <button id="ci_save" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Check in</button>
            </div>
        </div>
    </div>
</div>

<!-- Print-only layout: just the logo + QR + caption (clean printout, no dashboard chrome) -->
<div id="printArea" class="hidden">
    <img src="{{ asset('images/kop-ssb-logo.png') }}" alt="KOP-SSB" style="height:90px;width:auto;margin:0 auto 16px;">
    <div style="font-size:24px;font-weight:800;color:#111;">Scan to Check In</div>
    <div style="font-size:14px;color:#555;margin-top:4px;">Koperasi Kakitangan Sabah Softwoods Berhad</div>
    <div id="printQrcode" style="display:flex;justify-content:center;margin:28px 0;"></div>
    <div id="printUrl" style="font-size:13px;color:#777;"></div>
</div>
<style>
    @media print {
        body * { visibility: hidden; }
        #printArea, #printArea * { visibility: visible; }
        #printArea {
            display: block !important;
            position: absolute; top: 0; left: 0; right: 0;
            text-align: center; padding-top: 48px;
        }
    }
</style>
@endsection

@push('scripts')
<script src="{{ asset('js/qrcode.min.js') }}"></script>
<script>
    // QR of the public check-in URL (client-side render; works on any domain)
    const checkinUrl = window.location.origin + '/checkin';
    document.getElementById('checkinUrl').textContent = checkinUrl;
    new QRCode(document.getElementById('qrcode'), { text: checkinUrl, width: 240, height: 240 });

    // Larger QR for the print-only layout + print button.
    new QRCode(document.getElementById('printQrcode'), { text: checkinUrl, width: 360, height: 360 });
    document.getElementById('printUrl').textContent = checkinUrl;
    document.getElementById('printQrBtn').addEventListener('click', () => window.print());

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
        if (!data.length) { body.innerHTML = '<tr><td colspan="6" class="px-3 py-3 border text-center text-gray-500">No submissions yet.</td></tr>'; return; }
        body.innerHTML = data.map((r, i) => `<tr>
            <td class="px-3 py-2 border">${i + 1}</td>
            <td class="px-3 py-2 border">${esc(r.name)}</td>
            <td class="px-3 py-2 border">${esc(r.koperasi_id)}</td>
            <td class="px-3 py-2 border">${r.method === 'manual' ? '<span class="text-indigo-600">Manual</span>' : '<span class="text-green-600">Scanned</span>'}</td>
            <td class="px-3 py-2 border"><button class="edit-btn text-blue-600 hover:underline" data-idx="${i}">Edit</button></td>
            <td class="px-3 py-2 border"><button class="del-btn text-red-600 hover:underline" data-id="${r.id}" data-name="${esc(r.name)}">Delete</button></td>
        </tr>`).join('');
        document.querySelectorAll('.edit-btn').forEach(b => b.addEventListener('click', () => openEdit(parseInt(b.dataset.idx))));
        document.querySelectorAll('.del-btn').forEach(b => b.addEventListener('click', () => deleteSubmission(b.dataset.id, b.dataset.name)));
    }

    async function deleteSubmission(id, name) {
        if (!confirm(`Delete the check-in for "${name}"? This removes them from the submitted list.`)) return;
        const res = await window.apiFetch('/admin/attendance/' + id, { method: 'DELETE' });
        const data = await res.json();
        if (!res.ok) { alert(data.message || 'Delete failed.'); return; }
        loadList(); loadRoster();
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
        // Refresh BOTH the submitted list and the roster — the roster holds the
        // email/phone the Quick Check-In modal prefills, so it must reload too or
        // it keeps showing the old (e.g. dummy) contact details.
        await loadList();
        await loadRoster();
    });

    // ---- Quick Check-In: roster members who haven't submitted yet ----
    let roster = [];
    async function loadRoster() {
        const res = await window.apiFetch('/admin/roster');
        const { data } = await res.json();
        roster = data;
        renderPending();
    }
    function renderPending() {
        const box = document.getElementById('pendingList');
        const q = document.getElementById('memberSearch').value.trim().toLowerCase();
        let pending = roster.filter(r => !r.submitted);
        const total = pending.length;
        if (q) pending = pending.filter(r =>
            String(r.name ?? '').toLowerCase().includes(q) || String(r.member_id ?? '').toLowerCase().includes(q));
        document.getElementById('pendingCount').textContent = `(${total} pending)`;
        if (!roster.length) { box.innerHTML = '<div class="p-3 text-sm text-gray-500">No roster yet — add members from “Upload Roster”.</div>'; return; }
        if (!pending.length) { box.innerHTML = '<div class="p-3 text-sm text-gray-500">' + (total ? 'No matches.' : 'Everyone has checked in. 🎉') + '</div>'; return; }
        box.innerHTML = pending.slice(0, 200).map(r => `<div class="flex justify-between items-center p-2 hover:bg-indigo-50">
            <span class="truncate"><span class="font-medium">${esc(r.name)}</span> <span class="text-gray-400 text-sm">${esc(r.member_id)}</span></span>
            <button class="attend-btn shrink-0 bg-green-600 text-white text-xs px-3 py-1 rounded hover:bg-green-700" data-id="${esc(r.member_id)}">Check in</button>
        </div>`).join('');
        box.querySelectorAll('.attend-btn').forEach(b => b.addEventListener('click', () => {
            const member = roster.find(r => String(r.member_id) === b.dataset.id);
            openCheckin(member || { member_id: b.dataset.id, name: b.dataset.id });
        }));
    }
    document.getElementById('memberSearch').addEventListener('input', renderPending);

    // ---- Manual check-in: fields follow the "Check-in form fields" settings ----
    function openCheckin(member) {
        document.getElementById('ci_id').value = member.member_id;
        document.getElementById('ci_who').textContent = `${member.name ?? ''} · ${member.member_id}`;
        document.getElementById('ci_email').value = member.email || '';
        document.getElementById('ci_phone').value = member.phone || '';
        document.getElementById('ci_emailReq').textContent = formCfg.email_required ? '*' : '';
        document.getElementById('ci_phoneReq').textContent = formCfg.phone_required ? '*' : '';
        // Custom columns (optional on the staff path).
        document.getElementById('ci_custom').innerHTML = (formCfg.custom || []).map(f =>
            `<div><label class="block text-xs text-gray-600 mb-1">${esc(f.label)}</label><input data-cic="${esc(f.key)}" class="w-full p-2 border border-gray-300 rounded"></div>`).join('');
        document.getElementById('ci_msg').textContent = '';
        const m = document.getElementById('checkinModal'); m.classList.remove('hidden'); m.classList.add('flex');
        document.getElementById('ci_email').focus();
    }
    function closeCheckin() { const m = document.getElementById('checkinModal'); m.classList.add('hidden'); m.classList.remove('flex'); }
    document.getElementById('ci_cancel').addEventListener('click', closeCheckin);
    document.getElementById('ci_save').addEventListener('click', async () => {
        const memberId = document.getElementById('ci_id').value;
        const email = document.getElementById('ci_email').value.trim();
        const phone = document.getElementById('ci_phone').value.trim();
        const msg = document.getElementById('ci_msg');
        if (formCfg.email_required && !email) { msg.textContent = 'Email is required.'; msg.className = 'text-sm mt-3 text-red-600'; return; }
        if (formCfg.phone_required && !phone) { msg.textContent = 'Phone number is required.'; msg.className = 'text-sm mt-3 text-red-600'; return; }
        const custom = {};
        document.querySelectorAll('#ci_custom [data-cic]').forEach(i => { if (i.value.trim()) custom[i.dataset.cic] = i.value.trim(); });
        const btn = document.getElementById('ci_save'); btn.disabled = true; btn.textContent = '…';
        const res = await window.apiFetch('/admin/roster/mark', { method: 'POST', body: JSON.stringify({ member_id: memberId, attend: true, email, phone_number: phone, custom }) });
        const data = await res.json();
        btn.disabled = false; btn.textContent = 'Check in';
        if (!res.ok) { msg.textContent = data.message || 'Failed.'; msg.className = 'text-sm mt-3 text-red-600'; return; }
        closeCheckin();
        const qm = document.getElementById('quickMsg'); qm.textContent = data.message; qm.className = 'text-sm mt-2 text-green-600';
        await loadRoster(); await loadList();
    });

    // ---- Submission window control ----
    function renderSubmission(d, setInputs = true) {
        const badge = document.getElementById('subBadge');
        const toggle = document.getElementById('subToggle');
        if (d.accepting) {
            badge.textContent = '● Open'; badge.className = 'text-sm font-semibold px-3 py-1 rounded-full bg-green-100 text-green-700';
        } else {
            badge.textContent = d.submission_open ? '● Closed (schedule)' : '● Closed';
            badge.className = 'text-sm font-semibold px-3 py-1 rounded-full bg-red-100 text-red-700';
        }
        if (d.submission_open) {
            toggle.textContent = 'Close form now'; toggle.dataset.next = '0';
            toggle.className = 'px-4 py-2 rounded-lg text-sm font-medium text-white bg-red-600 hover:bg-red-700';
        } else {
            toggle.textContent = 'Open form'; toggle.dataset.next = '1';
            toggle.className = 'px-4 py-2 rounded-lg text-sm font-medium text-white bg-green-600 hover:bg-green-700';
        }
        if (setInputs) {
            document.getElementById('opensAt').value = d.opens_at || '';
            document.getElementById('closesAt').value = d.closes_at || '';
        }
    }
    async function loadSubmission(setInputs = true) {
        const res = await window.apiFetch('/admin/submission');
        const { data } = await res.json();
        renderSubmission(data, setInputs);
    }
    document.getElementById('subToggle').addEventListener('click', async (e) => {
        const next = e.currentTarget.dataset.next === '1';
        const res = await window.apiFetch('/admin/submission', { method: 'POST', body: JSON.stringify({ submission_open: next }) });
        const { data } = await res.json();
        renderSubmission(data);
        document.getElementById('subMsg').textContent = next ? 'Form opened.' : 'Form closed.';
        document.getElementById('subMsg').className = 'text-sm mt-2 ' + (next ? 'text-green-600' : 'text-red-600');
    });
    document.getElementById('schedSave').addEventListener('click', async () => {
        const msg = document.getElementById('subMsg');
        const res = await window.apiFetch('/admin/submission', { method: 'POST', body: JSON.stringify({
            opens_at: document.getElementById('opensAt').value || '',
            closes_at: document.getElementById('closesAt').value || '',
        }) });
        const json = await res.json();
        if (!res.ok) { msg.textContent = json.message || 'Failed.'; msg.className = 'text-sm mt-2 text-red-600'; return; }
        renderSubmission(json.data); msg.textContent = 'Schedule saved.'; msg.className = 'text-sm mt-2 text-green-600';
    });
    document.getElementById('schedClear').addEventListener('click', async () => {
        const res = await window.apiFetch('/admin/submission', { method: 'POST', body: JSON.stringify({ opens_at: '', closes_at: '' }) });
        const json = await res.json();
        renderSubmission(json.data);
        const msg = document.getElementById('subMsg'); msg.textContent = 'Schedule cleared.'; msg.className = 'text-sm mt-2 text-gray-500';
    });

    // ---- Check-in form fields settings (required toggles + custom columns) ----
    function colRow(label = '', required = false) {
        const wrap = document.createElement('div');
        wrap.className = 'flex items-center gap-2 col-row';
        wrap.innerHTML = `
            <input type="text" class="col-label flex-1 p-2 border border-gray-300 rounded text-sm" placeholder="Column label (e.g. Department)" value="${esc(label)}">
            <label class="inline-flex items-center gap-1 text-xs text-gray-600 shrink-0"><input type="checkbox" class="col-req w-4 h-4 accent-brand-600" ${required ? 'checked' : ''}> required</label>
            <button type="button" class="col-del text-red-500 text-sm shrink-0 px-2" title="Remove">✕</button>`;
        wrap.querySelector('.col-del').addEventListener('click', () => wrap.remove());
        return wrap;
    }
    let formCfg = { phone_required: true, email_required: true, custom: [] };
    async function loadFormConfig() {
        const res = await window.apiFetch('/admin/form-config');
        const { data } = await res.json();
        formCfg = data; // used by the Quick Check-In modal
        document.getElementById('fPhone').checked = !!data.phone_required;
        document.getElementById('fEmail').checked = !!data.email_required;
        const box = document.getElementById('customCols'); box.innerHTML = '';
        (data.custom || []).forEach(c => box.appendChild(colRow(c.label, c.required)));
    }
    document.getElementById('addColBtn').addEventListener('click', () => document.getElementById('customCols').appendChild(colRow()));
    document.getElementById('formSave').addEventListener('click', async () => {
        const custom = [...document.querySelectorAll('#customCols .col-row')].map(r => ({
            label: r.querySelector('.col-label').value.trim(),
            required: r.querySelector('.col-req').checked,
        })).filter(c => c.label);
        const msg = document.getElementById('formMsg');
        const res = await window.apiFetch('/admin/form-config', {
            method: 'POST',
            body: JSON.stringify({
                phone_required: document.getElementById('fPhone').checked,
                email_required: document.getElementById('fEmail').checked,
                custom,
            }),
        });
        const data = await res.json();
        msg.textContent = data.message || (res.ok ? 'Saved.' : 'Failed.');
        msg.className = 'text-sm min-h-5 ' + (res.ok ? 'text-green-600' : 'text-red-600');
        if (res.ok) loadFormConfig();
    });

    loadVenue();
    loadList();
    loadRoster();
    loadSubmission();
    loadFormConfig();
    setInterval(() => { loadList(); loadRoster(); loadSubmission(false); }, 10000);
</script>
@endpush
