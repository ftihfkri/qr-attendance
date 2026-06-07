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
            <div>
                <label class="block text-sm font-medium mb-1">Full Name</label>
                <input id="name" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Koperasi ID</label>
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
            status.textContent = 'Please fill in all fields (name, Koperasi ID, phone, email).';
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
