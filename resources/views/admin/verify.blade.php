@extends('layouts.app')
@section('title', 'Attendance Status')

@section('content')
<div class="max-w-5xl mx-auto p-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center mb-6">
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight">Attendance Status</h1>
        <div class="flex flex-wrap gap-2 items-center">
            <a href="/admin" class="bg-gray-200 px-3 sm:px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">← Dashboard</a>
            <a href="/admin/upload" class="bg-brand-600 text-white px-3 sm:px-4 py-2 rounded-lg hover:bg-brand-700 text-sm">Upload Roster</a>
            <form method="POST" action="/logout">@csrf
                <button class="bg-gray-200 px-3 sm:px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">Logout</button>
            </form>
        </div>
    </div>

    <div class="bg-white p-4 rounded-lg shadow mb-4">
        <div class="flex flex-wrap gap-3 items-center">
            <input id="search" type="text" placeholder="Search by name or Nombor Ahli / Anggota…"
                class="flex-grow min-w-[240px] p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
            <div class="flex gap-1">
                <button data-filter="all" class="filter-btn px-3 py-2 rounded-lg text-sm bg-indigo-600 text-white">All</button>
                <button data-filter="attended" class="filter-btn px-3 py-2 rounded-lg text-sm bg-gray-200 hover:bg-gray-300">Attended</button>
                <button data-filter="pending" class="filter-btn px-3 py-2 rounded-lg text-sm bg-gray-200 hover:bg-gray-300">Not yet</button>
            </div>
            <button id="refreshBtn" class="bg-gray-200 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">Refresh</button>
            <span id="count" class="text-sm text-gray-500"></span>
        </div>
    </div>

    <div class="bg-white p-4 rounded-lg shadow">
        <div class="overflow-x-auto max-h-[36rem] overflow-y-auto">
            <table class="min-w-full border border-gray-300 text-sm">
                <thead class="bg-gray-100 sticky top-0">
                    <tr>
                        <th class="px-3 py-2 border text-left">#</th>
                        <th class="px-3 py-2 border text-left">Full Name</th>
                        <th class="px-3 py-2 border text-left">Nombor Ahli / Anggota</th>
                        <th class="px-3 py-2 border text-left">Status</th>
                        <th class="px-3 py-2 border text-left">Time</th>
                        <th class="px-3 py-2 border text-left">Method</th>
                        <th class="px-3 py-2 border text-left">Action</th>
                    </tr>
                </thead>
                <tbody id="listBody"></tbody>
            </table>
        </div>
    </div>

    <!-- Manual check-in modal: email + phone required before marking attended -->
    <div id="checkinModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-1">Check in member</h3>
            <p id="ci_who" class="text-sm text-gray-500 mb-4"></p>
            <input type="hidden" id="ci_id">
            <div class="space-y-3">
                <div><label class="block text-xs text-gray-600 mb-1">Email <span class="text-red-500">*</span></label><input id="ci_email" type="email" class="w-full p-2 border border-gray-300 rounded" placeholder="name@example.com"></div>
                <div><label class="block text-xs text-gray-600 mb-1">Phone Number <span class="text-red-500">*</span></label><input id="ci_phone" type="tel" class="w-full p-2 border border-gray-300 rounded" placeholder="01x-xxxxxxx"></div>
            </div>
            <div id="ci_msg" class="text-sm mt-3"></div>
            <div class="flex justify-end gap-2 mt-4">
                <button id="ci_cancel" class="px-4 py-2 border rounded hover:bg-gray-50">Cancel</button>
                <button id="ci_save" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Check in</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let all = [];
    let statusFilter = 'all';
    function esc(s) { return String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }

    function render(rows) {
        const body = document.getElementById('listBody');
        const attended = all.filter(r => r.submitted).length;
        document.getElementById('count').textContent = `${attended} attended of ${all.length} · showing ${rows.length}`;
        if (!all.length) {
            body.innerHTML = '<tr><td colspan="7" class="px-3 py-4 border text-center text-gray-500">No roster yet — upload one to see attendance status.</td></tr>';
            return;
        }
        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="7" class="px-3 py-4 border text-center text-gray-500">No matching members.</td></tr>';
            return;
        }
        body.innerHTML = rows.map((r, i) => `<tr>
            <td class="px-3 py-2 border">${i + 1}</td>
            <td class="px-3 py-2 border">${esc(r.name)}</td>
            <td class="px-3 py-2 border font-medium">${esc(r.member_id)}</td>
            <td class="px-3 py-2 border">${r.submitted
                ? '<span class="text-green-600 font-medium">✓ Attended</span>'
                : '<span class="text-gray-400">Not yet</span>'}</td>
            <td class="px-3 py-2 border">${esc(r.time) || '—'}</td>
            <td class="px-3 py-2 border">${r.submitted ? (r.method === 'manual' ? '<span class="text-indigo-600">Manual</span>' : '<span class="text-green-600">Scanned</span>') : '—'}</td>
            <td class="px-3 py-2 border">${r.submitted
                ? `<button class="mark-btn text-red-600 hover:underline" data-id="${esc(r.member_id)}" data-attend="0" data-name="${esc(r.name)}">Mark not attended</button>`
                : `<button class="mark-btn text-green-700 hover:underline" data-id="${esc(r.member_id)}" data-attend="1" data-name="${esc(r.name)}">Mark attended</button>`}</td>
        </tr>`).join('');
        body.querySelectorAll('.mark-btn').forEach(b =>
            b.addEventListener('click', () => mark(b.dataset.id, b.dataset.attend === '1', b.dataset.name)));
    }

    function applyFilter() {
        const q = document.getElementById('search').value.trim().toLowerCase();
        let rows = all;
        if (statusFilter === 'attended') rows = rows.filter(r => r.submitted);
        else if (statusFilter === 'pending') rows = rows.filter(r => !r.submitted);
        if (q) rows = rows.filter(r =>
            String(r.name ?? '').toLowerCase().includes(q) ||
            String(r.member_id ?? '').toLowerCase().includes(q));
        render(rows);
    }

    async function mark(memberId, attend, name) {
        if (attend) {
            // Manual check-in needs email + phone — capture them in the modal first.
            const member = all.find(r => String(r.member_id) === String(memberId)) || { member_id: memberId, name };
            openCheckin(member);
            return;
        }
        if (!confirm(`Mark "${name}" as NOT attended? This removes their check-in.`)) return;
        const res = await window.apiFetch('/admin/roster/mark', {
            method: 'POST',
            body: JSON.stringify({ member_id: memberId, attend: false }),
        });
        const data = await res.json();
        if (!res.ok) { alert(data.message || 'Failed.'); return; }
        await load();
    }

    // ---- Manual check-in modal (email + phone required) ----
    function openCheckin(member) {
        document.getElementById('ci_id').value = member.member_id;
        document.getElementById('ci_who').textContent = `${member.name ?? ''} · ${member.member_id}`;
        document.getElementById('ci_email').value = member.email || '';
        document.getElementById('ci_phone').value = member.phone || '';
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
        if (!email || !phone) { msg.textContent = 'Email and phone number are required.'; msg.className = 'text-sm mt-3 text-red-600'; return; }
        const btn = document.getElementById('ci_save'); btn.disabled = true; btn.textContent = '…';
        const res = await window.apiFetch('/admin/roster/mark', { method: 'POST', body: JSON.stringify({ member_id: memberId, attend: true, email, phone_number: phone }) });
        const data = await res.json();
        btn.disabled = false; btn.textContent = 'Check in';
        if (!res.ok) { msg.textContent = data.message || 'Failed.'; msg.className = 'text-sm mt-3 text-red-600'; return; }
        closeCheckin();
        await load();
    });

    async function load() {
        const res = await window.apiFetch('/admin/roster');
        const { data } = await res.json();
        all = data;
        applyFilter();
    }

    document.getElementById('search').addEventListener('input', applyFilter);
    document.getElementById('refreshBtn').addEventListener('click', load);
    document.querySelectorAll('.filter-btn').forEach(b => b.addEventListener('click', () => {
        statusFilter = b.dataset.filter;
        document.querySelectorAll('.filter-btn').forEach(x => x.className = 'filter-btn px-3 py-2 rounded-lg text-sm bg-gray-200 hover:bg-gray-300');
        b.className = 'filter-btn px-3 py-2 rounded-lg text-sm bg-indigo-600 text-white';
        applyFilter();
    }));
    load();
</script>
@endpush
