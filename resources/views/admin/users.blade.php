@extends('layouts.app')
@section('title', 'Manage Users')

@section('content')
<div class="max-w-3xl mx-auto p-4">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Manage Users</h1>
        <a href="/admin" class="bg-gray-200 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">← Dashboard</a>
    </div>

    <div class="bg-white p-6 rounded-lg shadow mb-6">
        <h2 class="text-lg font-semibold mb-1">Add user or change password</h2>
        <p class="text-xs text-gray-500 mb-4">Existing username → changes its password. New username → creates the account. Min 6 chars.</p>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            <div><label class="block text-xs text-gray-600 mb-1">Username</label><input id="username" class="w-full p-2 border border-gray-300 rounded" autocomplete="off"></div>
            <div><label class="block text-xs text-gray-600 mb-1">Password</label><input id="password" type="password" class="w-full p-2 border border-gray-300 rounded" autocomplete="new-password"></div>
            <div><label class="block text-xs text-gray-600 mb-1">Role</label>
                <select id="role" class="w-full p-2 border border-gray-300 rounded"><option value="staff">staff</option><option value="admin">admin</option></select>
            </div>
            <button id="saveBtn" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Save</button>
        </div>
        <div id="msg" class="text-sm mt-3"></div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow">
        <h2 class="text-lg font-semibold mb-4">Users</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full border border-gray-300 text-sm">
                <thead class="bg-gray-100"><tr>
                    <th class="px-3 py-2 border text-left">ID</th><th class="px-3 py-2 border text-left">Username</th>
                    <th class="px-3 py-2 border text-left">Role</th><th class="px-3 py-2 border text-left">Action</th>
                </tr></thead>
                <tbody id="usersBody"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function showMsg(t, ok) { const e = document.getElementById('msg'); e.textContent = t; e.className = 'text-sm mt-3 ' + (ok ? 'text-green-600' : 'text-red-600'); }

    async function loadUsers() {
        const res = await window.apiFetch('/admin/users/list');
        const { data } = await res.json();
        document.getElementById('usersBody').innerHTML = data.map(u => `<tr>
            <td class="px-3 py-2 border">${u.id}</td>
            <td class="px-3 py-2 border">${u.username}</td>
            <td class="px-3 py-2 border">${u.role}</td>
            <td class="px-3 py-2 border"><button data-id="${u.id}" data-name="${u.username}" class="del text-red-500 hover:underline">Delete</button></td>
        </tr>`).join('');
        document.querySelectorAll('.del').forEach(b => b.addEventListener('click', () => del(b.dataset.id, b.dataset.name)));
    }

    document.getElementById('saveBtn').addEventListener('click', async () => {
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;
        const role = document.getElementById('role').value;
        if (!username || !password) { showMsg('Enter username and password.', false); return; }
        const res = await window.apiFetch('/admin/users', { method: 'POST', body: JSON.stringify({ username, password, role }) });
        const data = await res.json();
        if (!res.ok) { showMsg(data.message || 'Failed', false); return; }
        showMsg(`Saved '${data.data.username}' (${data.data.role}).`, true);
        document.getElementById('username').value = ''; document.getElementById('password').value = '';
        loadUsers();
    });

    async function del(id, name) {
        if (!confirm(`Delete '${name}'?`)) return;
        const res = await window.apiFetch('/admin/users/' + id, { method: 'DELETE' });
        const data = await res.json();
        if (!res.ok) { showMsg(data.message || 'Failed to delete', false); return; }
        showMsg(`Deleted '${name}'.`, true);
        loadUsers();
    }

    loadUsers();
</script>
@endpush
