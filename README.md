# 📋 Koperasi AGM Attendance System 🧑‍🤝‍🧑

A secure, geolocation-verified **shareholder attendance system for koperasi (cooperative) Annual General Meetings (AGM)**, using **QR codes**, **device fingerprinting**, and **real-time GPS validation**. Built with **Node.js**, **MySQL (Prisma ORM)**, and **Tailwind CSS**.

---

## 🚀 Features

- ✅ Secure, time-limited QR code generation for the meeting
- 📍 Geofencing: a shareholder can only check in within the meeting venue radius
- 🧠 Device fingerprinting to prevent duplicate / proxy check-ins
- 🗳 One active AGM meeting at a time; each shareholder checks in once per meeting
- 🛡 Rate-limited QR generation
- 🎨 Clean, responsive UI with Tailwind CSS
- 🗄 MySQL persistent storage via Prisma ORM

---

## 🧱 Core Entities

| Entity        | Purpose                                                            |
|---------------|-------------------------------------------------------------------|
| `Shareholder` | A koperasi member: `name`, `koperasiId` (unique), `phoneNumber`, optional `email`/`address` |
| `Meeting`     | An AGM event with venue coordinates + radius; exactly one is `isActive` |
| `Attendance`  | A check-in linking a shareholder to a meeting (date, time, location, device, status) |

Several dormant models (IoT, friendship, campus graph, etc.) are carried over from the original codebase but are not wired into any route.

---

## 🛠 Tech Stack

| Layer       | Tech/Library                          |
|-------------|---------------------------------------|
| Frontend    | HTML, TailwindCSS, JavaScript         |
| Backend     | Node.js, Express.js                   |
| Database    | MySQL (via Prisma ORM)                |
| Security    | Helmet.js, SHA-256, CORS, rate limit  |
| Features    | QR Code (`qrcode`), Geo validation    |
| Extras      | Device fingerprinting, Haversine      |

---

## 📂 Project Structure

```
QR-Based-Attendance-System/
├── prisma/
│   ├── schema.prisma      # MySQL schema (Shareholder, Meeting, Attendance, …)
│   └── seed.js            # Seeds one active meeting + sample shareholders
├── backend/
│   ├── server.js          # Express app + all endpoints (Prisma)
│   ├── db.js              # Shared PrismaClient instance
│   ├── qr-generator.js
│   ├── routes/
│   └── algorithms/
├── frontend/              # index.html (check-in), dashboard.html, admin-dashboard.html, …
├── .env
└── package.json
```

---

## 📦 Setup

### Prerequisites
- Node.js & npm
- A running **MySQL** server, with a database created (e.g. `agm_attendance`)

### 1. Configure environment (`.env` at the project root)
```env
PORT=5000
DATABASE_URL="mysql://USER:PASSWORD@localhost:3306/agm_attendance"
QR_SECRET_KEY=change-me
```

### 2. Install dependencies
```bash
npm install
```

### 3. Create the database schema & seed
```bash
npx prisma migrate dev --name init   # creates tables + generates the client
npx prisma db seed                    # inserts one ACTIVE meeting + sample shareholders
```

### 4. Start the server (run from the project root)
```bash
npm start          # → node backend/server.js
```

Useful Prisma commands:
```bash
npm run studio     # open Prisma Studio to view/edit data (manage the active meeting here)
npm run generate   # regenerate the Prisma client after schema changes
```

---

## 🌐 Usage

- `http://localhost:5000/qr-scanner.html` → generate the meeting QR code
- Scan the QR → redirected to `http://localhost:5000/index.html?sessionId=...`
- The shareholder enters **Name, Koperasi ID, Phone Number** and checks in
- `http://localhost:5000/dashboard.html?koperasiId=...` → shareholder dashboard
- `http://localhost:5000/admin-dashboard.html` → admin views (attendance %, by-date)

> **Note:** check-in requires an `isActive` meeting (the seed creates one). To run a
> new AGM, add a `Meeting` and set it active (e.g. via `npm run studio`), making sure
> only one meeting is active at a time.

---

## 🔐 Core Algorithms

- **Haversine Formula** – validates the shareholder is within the venue radius
- **SHA-256 Hashing** – signs the QR session payload
- **Device Fingerprinting** – consistent hash to prevent duplicate check-ins
- **Rate Limiting** – protects the QR endpoint (max 5/minute/IP)

---

## 📃 License

Open source under the [MIT License](LICENSE).
