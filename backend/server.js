
const express = require("express");
const cors = require("cors");
require("dotenv").config();
const path = require('path');
const rateLimit = require('express-rate-limit');
const fs = require('fs');
const crypto = require('crypto');
const helmet = require("helmet");
const sha256 = require('./sha256');
const { execFileSync } = require('child_process');

const bcrypt = require('bcryptjs');

// Prisma client (single shared instance) + QR helpers
const prisma = require('./db');
const { generateQRCode, validateSession } = require('./qr-generator');

// --- Import algorithm modules (optional; endpoints degrade gracefully) ---
let dijkstra, profileOptimizer, graphTraversal;
try {
  dijkstra = require('./algorithms/dijkstra');
  profileOptimizer = require('./algorithms/profileOptimizer');
  graphTraversal = require('./algorithms/graphTraversal');
  console.log("Successfully loaded algorithm modules.");
} catch (err) {
  console.warn("Warning: Could not load one or more algorithm modules. Related endpoints might not work.", err.message);
  dijkstra = { findShortestPath: () => { throw new Error("Dijkstra module not loaded"); } };
  profileOptimizer = { getProfileRecommendations: () => { throw new Error("ProfileOptimizer module not loaded"); } };
  graphTraversal = { exploreCommunity: () => { throw new Error("GraphTraversal module not loaded"); } };
}
// --- END ---


const requiredEnvVars = ['DATABASE_URL', 'QR_SECRET_KEY'];
requiredEnvVars.forEach(envVar => {
  if (!process.env[envVar]) {
    console.error(`${envVar} environment variable is required`);
    process.exit(1);
  }
});

const app = express();

// Behind Railway's (single) reverse proxy: trust it so req.protocol reflects
// HTTPS and req.ip is the real client IP for rate limiting.
app.set('trust proxy', 1);

const QR_CODE_DIR = path.join(__dirname, '../frontend/public/qrcodes');

// Fixed venue for the geofence (set in .env). If lat/lng are unset, the
// geofence is skipped and submissions are accepted from anywhere.
const VENUE_LAT = process.env.VENUE_LAT ? parseFloat(process.env.VENUE_LAT) : null;
const VENUE_LNG = process.env.VENUE_LNG ? parseFloat(process.env.VENUE_LNG) : null;
const VENUE_RADIUS = process.env.VENUE_RADIUS_METERS ? parseInt(process.env.VENUE_RADIUS_METERS) : 100;

// Middleware Setup
app.use(
  helmet({
    contentSecurityPolicy: {
      useDefaults: true,
      directives: {
        "script-src": [
          "'self'",
          "https://cdn.tailwindcss.com",
          "https://cdn.jsdelivr.net",
          "'unsafe-inline'"
        ],
        "style-src": [
          "'self'",
          "https://fonts.googleapis.com",
          "https://cdn.jsdelivr.net",
          "https://cdnjs.cloudflare.com",
          "'unsafe-inline'"
        ],
        "font-src": [
          "'self'",
          "https://fonts.gstatic.com",
          "https://cdnjs.cloudflare.com"
        ],
        "img-src": [
          "'self'",
          "data:",
          "https://ui-avatars.com"
        ]
      }
    }
  })
);


app.use(cors());
app.use(express.json());
app.use(express.static(path.join(__dirname, '../frontend')));

// Request logging
app.use((req, res, next) => {
  console.log(`[${new Date().toISOString()}] ${req.method} ${req.url}`);
  next();
});


// QR Code Directory Setup
try {
  if (!fs.existsSync(QR_CODE_DIR)) {
    fs.mkdirSync(QR_CODE_DIR, { recursive: true });
    console.log(` Created QR code directory at: ${QR_CODE_DIR}`);
  }

  // Clean up old QR codes on startup
  fs.readdir(QR_CODE_DIR, (err, files) => {
    if (err) {
      console.error('Startup cleanup error:', err);
      return;
    }

    const now = Date.now();
    files.forEach(file => {
      if (file.startsWith('qr_') && file.endsWith('.png')) {
        const fileTimestamp = parseInt(file.split('_')[1].split('.')[0]);
        if (isNaN(fileTimestamp) || (now - fileTimestamp > 1.5 * 60 * 1000)) {
          fs.unlink(path.join(QR_CODE_DIR, file), err => {
            if (err) console.error('Error deleting file:', file, err);
          });
        }
      }
    });
  });

  app.use('/qrcodes', express.static(QR_CODE_DIR, {
    maxAge: '1h', // Cache for 1 hour
    setHeaders: (res, path) => {
        res.set('Cross-Origin-Resource-Policy', 'cross-origin');
    }
}));
  console.log(` Serving QR codes from: ${QR_CODE_DIR}`);
}
catch (err) {
  console.error(' Failed to setup QR code directory:', err);
  process.exit(1);
}

// Rate limiting for QR generation
const qrLimiter = rateLimit({
  windowMs: 60 * 1000,
  max: 5,
  handler: (req, res) => {
    console.log(`Rate limit exceeded for IP: ${req.ip}`);
    res.status(429).json({
      status: "error",
      message: "Too many QR requests. Please wait a minute."
    });
  },
  standardHeaders: true,
  legacyHeaders: false
});


// Routes
const shareholderRoutes = require("./routes/studentProfile");
const attendanceRoutes = require("./routes/attendance");
app.use("/api/shareholders", shareholderRoutes);
app.use("/api/attendance", attendanceRoutes);

// ---------------------------------------------------------------------------
// Admin authentication (token-based, backed by the AdminSession table)
// ---------------------------------------------------------------------------
const ADMIN_TOKEN_TTL_MS = 8 * 60 * 60 * 1000; // 8 hours

app.post('/api/admin/login', async (req, res) => {
  try {
    const { username, password } = req.body;
    if (!username || !password) {
      return res.status(400).json({ status: 'error', message: 'Username and password are required' });
    }
    const user = await prisma.user.findUnique({ where: { username } });
    if (!user || !bcrypt.compareSync(password, user.passwordHash)) {
      return res.status(401).json({ status: 'error', message: 'Invalid username or password' });
    }
    const token = crypto.randomBytes(32).toString('hex');
    await prisma.adminSession.create({
      data: {
        adminId: user.id,
        token,
        ipAddress: req.ip,
        userAgent: req.get('user-agent') || null,
        expiresAt: new Date(Date.now() + ADMIN_TOKEN_TTL_MS)
      }
    });
    res.json({ status: 'success', token, username: user.username, role: user.role });
  } catch (error) {
    console.error('Admin login error:', error);
    res.status(500).json({ status: 'error', message: error.message });
  }
});

// Middleware: require a valid, unexpired session token (any logged-in user).
// Attaches req.currentUser = { id, username, role }.
async function requireAdmin(req, res, next) {
  try {
    const auth = req.get('authorization') || '';
    const token = auth.startsWith('Bearer ') ? auth.slice(7) : null;
    if (!token) {
      return res.status(401).json({ status: 'error', message: 'Authentication required' });
    }
    const session = await prisma.adminSession.findFirst({ where: { token } });
    if (!session || session.expiresAt < new Date()) {
      return res.status(401).json({ status: 'error', message: 'Session expired. Please log in again.' });
    }
    req.adminSession = session;
    req.currentUser = session.adminId
      ? await prisma.user.findUnique({ where: { id: session.adminId }, select: { id: true, username: true, role: true } })
      : null;
    next();
  } catch (error) {
    res.status(500).json({ status: 'error', message: error.message });
  }
}

// Middleware: require the 'admin' role (for managing users).
function requireSuperAdmin(req, res, next) {
  if (!req.currentUser || req.currentUser.role !== 'admin') {
    return res.status(403).json({ status: 'error', message: 'Admin role required' });
  }
  next();
}

// Who am I? — lets the frontend gate admin-only UI by role.
app.get('/api/admin/me', requireAdmin, (req, res) => {
  res.json({ status: 'success', data: req.currentUser || null });
});

// Public self-registration — always creates a 'staff' account.
app.post('/api/register', async (req, res) => {
  try {
    const { username, password, confirmPassword } = req.body;
    if (!username || !password || !confirmPassword) {
      return res.status(400).json({ status: 'error', message: 'All fields are required' });
    }
    if (password !== confirmPassword) {
      return res.status(400).json({ status: 'error', message: 'Passwords do not match' });
    }
    if (password.length < 6) {
      return res.status(400).json({ status: 'error', message: 'Password must be at least 6 characters' });
    }
    const exists = await prisma.user.findUnique({ where: { username: username.trim() } });
    if (exists) {
      return res.status(409).json({ status: 'error', message: 'Username already taken' });
    }
    const passwordHash = bcrypt.hashSync(password, 10);
    const user = await prisma.user.create({
      data: { username: username.trim(), passwordHash, role: 'staff' }
    });
    res.json({ status: 'success', data: { id: user.id, username: user.username, role: user.role } });
  } catch (e) {
    res.status(500).json({ status: 'error', message: e.message });
  }
});

app.post('/api/admin/logout', requireAdmin, async (req, res) => {
  await prisma.adminSession.deleteMany({ where: { token: req.adminSession.token } });
  res.json({ status: 'success' });
});

// ---- User management (admin role only; no password hashes are ever returned) ----
app.get('/api/admin/users', requireAdmin, requireSuperAdmin, async (req, res) => {
  try {
    const users = await prisma.user.findMany({
      select: { id: true, username: true, role: true, createdAt: true },
      orderBy: { id: 'asc' }
    });
    res.json({ status: 'success', data: users });
  } catch (e) {
    res.status(500).json({ status: 'error', message: e.message });
  }
});

// Create a user (admin or staff) OR change an existing user's password (upsert by username).
app.post('/api/admin/users', requireAdmin, requireSuperAdmin, async (req, res) => {
  try {
    const { username, password, role } = req.body;
    if (!username || !password) {
      return res.status(400).json({ status: 'error', message: 'Username and password are required' });
    }
    if (password.length < 6) {
      return res.status(400).json({ status: 'error', message: 'Password must be at least 6 characters' });
    }
    const safeRole = role === 'admin' ? 'admin' : 'staff';
    const passwordHash = bcrypt.hashSync(password, 10);
    const user = await prisma.user.upsert({
      where: { username: username.trim() },
      update: { passwordHash, role: safeRole },
      create: { username: username.trim(), passwordHash, role: safeRole }
    });
    res.json({ status: 'success', data: { id: user.id, username: user.username, role: user.role } });
  } catch (e) {
    res.status(500).json({ status: 'error', message: e.message });
  }
});

app.delete('/api/admin/users/:id', requireAdmin, requireSuperAdmin, async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const total = await prisma.user.count();
    if (total <= 1) {
      return res.status(400).json({ status: 'error', message: 'Cannot delete the last admin user' });
    }
    await prisma.user.delete({ where: { id } });
    res.json({ status: 'success' });
  } catch (e) {
    if (e.code === 'P2025') return res.status(404).json({ status: 'error', message: 'User not found' });
    res.status(500).json({ status: 'error', message: e.message });
  }
});

// ---------------------------------------------------------------------------
// Attendance session — a single rolling list (no per-event management).
// All submissions attach to one persistent "session" meeting, created once.
// The geofence venue comes from .env (VENUE_LAT/VENUE_LNG/VENUE_RADIUS_METERS).
// ---------------------------------------------------------------------------

async function getSessionMeeting(client) {
  let meeting = await client.meeting.findFirst({ orderBy: { id: 'asc' } });
  if (!meeting) {
    // Seed with the optional .env default venue; the admin can override it
    // any time from the QR page via POST /api/session/venue.
    meeting = await client.meeting.create({
      data: {
        title: 'Attendance',
        meetingDate: new Date(),
        isActive: true,
        venueLat: VENUE_LAT,
        venueLng: VENUE_LNG,
        radiusMeters: VENUE_RADIUS
      }
    });
  }
  return meeting;
}

// Admin: read / set the venue used for the geofence (current session).
app.get('/api/session/venue', requireAdmin, async (req, res) => {
  try {
    const meeting = await getSessionMeeting(prisma);
    res.json({
      status: 'success',
      data: {
        venueName: meeting.venueName,
        venueLat: meeting.venueLat,
        venueLng: meeting.venueLng,
        radiusMeters: meeting.radiusMeters
      }
    });
  } catch (e) {
    res.status(500).json({ status: 'error', message: e.message });
  }
});

app.post('/api/session/venue', requireAdmin, async (req, res) => {
  try {
    const { venueName, venueLat, venueLng, radiusMeters } = req.body;
    if (typeof venueLat !== 'number' || typeof venueLng !== 'number') {
      return res.status(400).json({ status: 'error', message: 'venueLat and venueLng (numbers) are required' });
    }
    const meeting = await getSessionMeeting(prisma);
    const updated = await prisma.meeting.update({
      where: { id: meeting.id },
      data: {
        venueName: venueName || meeting.venueName,
        venueLat,
        venueLng,
        radiusMeters: typeof radiusMeters === 'number' ? radiusMeters : meeting.radiusMeters
      }
    });
    res.json({
      status: 'success',
      data: { venueLat: updated.venueLat, venueLng: updated.venueLng, radiusMeters: updated.radiusMeters }
    });
  } catch (e) {
    res.status(500).json({ status: 'error', message: e.message });
  }
});

function attendanceToRow(r) {
  return {
    name: r.name,
    koperasiId: r.koperasiId,
    phoneNumber: r.phoneNumber,
    email: r.shareholder?.email || null,
    date: r.date,
    time: r.time,
    method: (r.deviceFingerprint || '').startsWith('manual:') ? 'manual' : 'scanned'
  };
}

// Admin: live list of everyone who submitted the form.
app.get('/api/attendance/list', requireAdmin, async (req, res) => {
  try {
    const meeting = await getSessionMeeting(prisma);
    const rows = await prisma.attendance.findMany({
      where: { meetingId: meeting.id },
      include: { shareholder: true },
      orderBy: { createdAt: 'desc' }
    });
    res.json({ status: 'success', data: rows.map(attendanceToRow) });
  } catch (error) {
    res.status(500).json({ status: 'error', message: error.message });
  }
});

// Backwards-compatible endpoint still used by admin-dashboard.html.
app.get('/api/meetings/active/attendance', requireAdmin, async (req, res) => {
  try {
    const meeting = await getSessionMeeting(prisma);
    const attendance = await prisma.attendance.findMany({
      where: { meetingId: meeting.id },
      orderBy: { createdAt: 'desc' }
    });
    res.json({ status: 'success', data: { meeting, attendance } });
  } catch (error) {
    res.status(500).json({ status: 'error', message: error.message });
  }
});

// Admin: download the list as an Excel (.xlsx) file.
app.get('/api/attendance/export', requireAdmin, async (req, res) => {
  try {
    const ExcelJS = require('exceljs');
    const meeting = await getSessionMeeting(prisma);
    const rows = await prisma.attendance.findMany({
      where: { meetingId: meeting.id },
      include: { shareholder: true },
      orderBy: { createdAt: 'asc' }
    });

    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet('Attendance');
    sheet.columns = [
      { header: 'No', key: 'no', width: 6 },
      { header: 'Full Name', key: 'name', width: 28 },
      { header: 'Koperasi ID', key: 'koperasiId', width: 18 },
      { header: 'Phone', key: 'phone', width: 18 },
      { header: 'Email', key: 'email', width: 28 },
      { header: 'Date', key: 'date', width: 14 },
      { header: 'Time', key: 'time', width: 12 },
      { header: 'Method', key: 'method', width: 10 }
    ];
    sheet.getRow(1).font = { bold: true };
    rows.forEach((r, i) => {
      sheet.addRow({
        no: i + 1,
        name: r.name,
        koperasiId: r.koperasiId,
        phone: r.phoneNumber,
        email: r.shareholder?.email || '',
        date: r.date,
        time: r.time,
        method: (r.deviceFingerprint || '').startsWith('manual:') ? 'manual' : 'scanned'
      });
    });

    const buffer = await workbook.xlsx.writeBuffer();
    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    res.setHeader('Content-Disposition', `attachment; filename="attendance_${new Date().toISOString().split('T')[0]}.xlsx"`);
    res.send(Buffer.from(buffer));
  } catch (error) {
    console.error('Excel export error:', error);
    res.status(500).json({ status: 'error', message: error.message });
  }
});

// Admin: clear the whole list (start a fresh session).
app.post('/api/attendance/clear', requireAdmin, async (req, res) => {
  try {
    const meeting = await getSessionMeeting(prisma);
    const result = await prisma.attendance.deleteMany({ where: { meetingId: meeting.id } });
    res.json({ status: 'success', deleted: result.count });
  } catch (error) {
    res.status(500).json({ status: 'error', message: error.message });
  }
});

// Admin manually adds a person (bypasses geofence/device/QR; still one per Koperasi ID).
app.post('/api/attendance/manual', requireAdmin, async (req, res) => {
  try {
    const { koperasiId, name, phoneNumber, email } = req.body;
    if (!koperasiId || !name || !phoneNumber) {
      return res.status(400).json({ status: 'error', message: 'koperasiId, name and phoneNumber are required' });
    }

    const attendance = await prisma.$transaction(async (tx) => {
      const meeting = await getSessionMeeting(tx);

      const existing = await tx.attendance.findUnique({
        where: { meetingId_koperasiId: { meetingId: meeting.id, koperasiId } }
      });
      if (existing) {
        const e = new Error('This Koperasi ID has already been recorded');
        e.statusCode = 400;
        throw e;
      }

      const shUpdate = { name, phoneNumber };
      if (email) shUpdate.email = email;

      const shareholder = await tx.shareholder.upsert({
        where: { koperasiId },
        update: shUpdate,
        create: { name, koperasiId, phoneNumber, email: email || null }
      });

      return tx.attendance.create({
        data: {
          meetingId: meeting.id,
          shareholderId: shareholder.id,
          koperasiId,
          name,
          phoneNumber,
          date: new Date().toISOString().split('T')[0],
          time: new Date().toLocaleTimeString('en-IN', { hour12: false }),
          locationLat: 0,
          locationLng: 0,
          deviceFingerprint: `manual:${koperasiId}`,
          status: 'present',
          distanceFromVenue: null
        }
      });
    });

    res.json({ status: 'success', message: 'Added to the list', data: attendance });
  } catch (error) {
    if (error.statusCode) return res.status(error.statusCode).json({ status: 'error', message: error.message });
    console.error('Manual add error:', error);
    res.status(500).json({ status: 'error', message: error.message });
  }
});

// List shareholders whose AGM attendance percentage falls within [min, max].
// "Percentage" = meetings attended / total meetings.
app.get('/api/shareholders/by-attendance-range', async (req, res) => {
    try {
        const { min, max } = req.query;

        if (!min || !max) {
            return res.status(400).json({ error: 'Both min and max percentage parameters are required' });
        }

        const minPercentage = parseFloat(min);
        const maxPercentage = parseFloat(max);

        if (isNaN(minPercentage)) {
            return res.status(400).json({ error: 'Minimum percentage must be a number' });
        }
        if (isNaN(maxPercentage)) {
            return res.status(400).json({ error: 'Maximum percentage must be a number' });
        }
        if (minPercentage < 0 || maxPercentage > 100) {
            return res.status(400).json({ error: 'Percentages must be between 0 and 100' });
        }
        if (minPercentage > maxPercentage) {
            return res.status(400).json({ error: 'Minimum percentage cannot be greater than maximum' });
        }

        const totalMeetings = await prisma.meeting.count();
        if (totalMeetings === 0) {
            return res.json({ status: "success", data: [] });
        }

        // Count present check-ins per shareholder.
        const grouped = await prisma.attendance.groupBy({
            by: ['koperasiId'],
            where: { status: 'present' },
            _count: { _all: true }
        });
        const presentMap = new Map(grouped.map(g => [g.koperasiId, g._count._all]));

        const shareholders = await prisma.shareholder.findMany();

        const results = shareholders
            .map(s => {
                const presentDays = presentMap.get(s.koperasiId) || 0;
                const attendancePercentage = Math.round((presentDays / totalMeetings) * 100);
                return {
                    koperasiId: s.koperasiId,
                    name: s.name,
                    phoneNumber: s.phoneNumber,
                    attendancePercentage,
                    presentDays,
                    totalMeetings
                };
            })
            .filter(r => r.attendancePercentage >= minPercentage && r.attendancePercentage <= maxPercentage)
            .sort((a, b) => b.attendancePercentage - a.attendancePercentage);

        res.json({ status: "success", data: results });

    } catch (error) {
        console.error("Error fetching shareholders by attendance range:", error);
        res.status(500).json({ status: "error", message: error.message });
    }
});

app.get('/api/attendance/dates', async (req, res) => {
  try {
    const rows = await prisma.attendance.findMany({
      distinct: ['date'],
      select: { date: true },
      orderBy: { date: 'asc' }
    });
    res.json({ status: "success", data: rows.map(r => r.date) });
  } catch (error) {
    console.error("Error fetching attendance dates:", error);
    res.status(500).json({ status: "error", message: error.message });
  }
});

app.get('/api/attendance/by-date', async (req, res) => {
    try {
        const { date } = req.query;
        if (!date) {
            return res.status(400).json({ error: 'Date parameter is required' });
        }

        const attendance = await prisma.attendance.findMany({
            where: { date, status: 'present' },
            orderBy: { koperasiId: 'asc' }
        });

        res.json({ status: "success", data: attendance });
    } catch (error) {
        console.error("Error fetching attendance by date:", error);
        res.status(500).json({ status: "error", message: error.message });
    }
});

// QR Code Generation Endpoint
app.get("/api/generate-qr", qrLimiter, async (req, res) => {
  try {
    console.log(`Generating QR code for IP: ${req.ip}`);
    const baseUrl = process.env.PUBLIC_BASE_URL || `${req.protocol}://${req.get('host')}`;
    const qrData = await generateQRCode(req.ip, baseUrl);

    console.log(` Generated QR code at: ${qrData.qrImage}`);
    res.json({
      status: "success",
      qrImage: qrData.qrImage,
      sessionId: qrData.sessionId,
      expiresIn: qrData.expiresIn
    });
  } catch (error) {
    console.error("QR generation error:", error);
    res.status(500).json({
      status: "error",
      message: "Failed to generate QR code",
      details: process.env.NODE_ENV === 'development' ? error.message : undefined
    });
  }
});

// Device fingerprint hashing (delegates to Java, falls back to JS).
app.post('/api/consistent-hash', async (req, res) => {
    try {
        const { input } = req.body;

        if (typeof input !== 'string' || !input.trim()) {
            return res.status(400).json({ error: 'Input must be a non-empty string' });
        }

        // Pass input as a separate argv entry via execFileSync — no shell is
        // spawned, so the input cannot be interpreted as a shell command.
        const result = execFileSync('java', ['ConsistentHash', input], {
            cwd: __dirname, // Java .class files live alongside this file
            encoding: 'utf-8',
            stdio: ['pipe', 'pipe', 'ignore'],
            timeout: 5000
        });

        if (!/^[0-9a-f]{8}$/.test(result.trim())) {
            throw new Error('Invalid hash format from Java');
        }

        res.json({ fingerprint: result.trim() });
    } catch (error) {
        console.error("Consistent hash error:", error);
        const jsHash = consistentHashJS(req.body.input);
        res.status(500).json({
            error: 'Java hashing failed. Used JS fallback.',
            fingerprint: jsHash
        });
    }
});

function consistentHashJS(input) {
    let hash = 0;
    for (let i = 0; i < input.length; i++) {
        hash = (hash * 31 + input.charCodeAt(i)) >>> 0;
    }
    return hash.toString(16).padStart(8, '0');
}

app.post("/api/validate-session", async (req, res) => {
  try {
    const { sessionId } = req.body;
    if (!sessionId) {
      return res.status(400).json({ valid: false, message: "Session ID required" });
    }

    const isValid = validateSession(sessionId);
    res.json({
      valid: isValid,
      message: isValid ? "Valid session" : "Invalid or expired session ID"
    });
  } catch (error) {
    console.error("Session validation error:", error);
    res.status(500).json({ valid: false, message: "Validation error" });
  }
});

app.get('/verify-attendance', (req, res) => {
    try {
        console.log('Raw query data:', req.query.data);
        const dataStr = decodeURIComponent(req.query.data);
        const data = JSON.parse(dataStr);
        console.log('Parsed data:', data);

        if (!data?.sessionId || !data?.timestamp || !data?.hash) {
            console.log('Missing fields in data:', data);
            return res.status(400).send('Invalid QR code data: Missing fields');
        }

        const secretKey = process.env.QR_SECRET_KEY || 'default-secret-key';
        const hashInput = data.sessionId + data.timestamp + secretKey;
        const expectedHash = sha256(hashInput);

        if (data.hash !== expectedHash) {
            console.log('Hash mismatch details:', {
                input: hashInput,
                expected: expectedHash,
                received: data.hash
            });
            return res.status(400).send('Invalid QR code: Hash mismatch');
        }

        const currentTime = Date.now();
        const qrExpiryTime = 15 * 60 * 1000;
        if (currentTime - data.timestamp > qrExpiryTime) {
            return res.status(400).send('QR code expired');
        }

        res.redirect(`/index.html?sessionId=${data.sessionId}`);
    } catch (error) {
        console.error('QR validation error:', error);
        res.status(400).send('Invalid QR code data');
    }
});

// Distance between two coordinates (delegates to Java Haversine, JS fallback).
function getDistanceFromLatLngInMeters(lat1, lng1, lat2, lng2) {
    try {
        const result = execFileSync(
            'java',
            ['Haversine', String(lat1), String(lng1), String(lat2), String(lng2)],
            {
                cwd: __dirname, // Java .class files live alongside this file
                encoding: 'utf-8',
                stdio: ['pipe', 'pipe', 'ignore']
            }
        );
        return parseFloat(result.trim());
    } catch (error) {
        console.error("Java Haversine Error:", error.message);
        const toRad = angle => (angle * Math.PI) / 180;
        const R = 6371000;
        const dLat = toRad(lat2 - lat1);
        const dLng = toRad(lng2 - lng1);
        const a = Math.sin(dLat / 2) ** 2 +
                  Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
                  Math.sin(dLng / 2) ** 2;
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }
}

app.get("/health", async (req, res) => {
  let dbState = "down";
  try {
    await prisma.$queryRaw`SELECT 1`;
    dbState = "ok";
  } catch (err) {
    console.error("Health DB check failed:", err.message);
  }
  res.json({ status: "ok", dbState, uptime: process.uptime() });
});

function validateAttendance(req, res, next) {
  const required = ['name', 'koperasiId', 'phoneNumber', 'deviceFingerprint'];
  const missing = required.filter(field => !req.body[field]);

  if (missing.length) {
    return res.status(400).json({
      status: "error",
      message: `Missing required fields: ${missing.join(', ')}`
    });
  }

  if (!req.body.location || typeof req.body.location.lat !== "number" || typeof req.body.location.lng !== "number") {
    return res.status(400).json({
      status: "error",
      message: "Location (lat, lng) is required and must be numeric"
    });
  }
  next();
}

// AGM check-in.
app.post("/mark-attendance", validateAttendance, async (req, res) => {
  try {
    const { name, koperasiId, phoneNumber, email, address, deviceFingerprint, location } = req.body;
    const today = new Date().toISOString().split('T')[0];

    const attendance = await prisma.$transaction(async (tx) => {
      // Single rolling attendance list (no per-event management).
      const meeting = await getSessionMeeting(tx);

      // 1. One submission per Koperasi ID.
      const existing = await tx.attendance.findUnique({
        where: { meetingId_koperasiId: { meetingId: meeting.id, koperasiId } }
      });
      if (existing) {
        const e = new Error("This Koperasi ID has already submitted the form");
        e.statusCode = 400;
        throw e;
      }

      // 2. One submission per device.
      const existingDevice = await tx.attendance.findUnique({
        where: { meetingId_deviceFingerprint: { meetingId: meeting.id, deviceFingerprint } }
      });
      if (existingDevice) {
        const e = new Error("This device has already been used to submit the form");
        e.statusCode = 400;
        throw e;
      }

      // 3. Geofence against the venue the admin set for this session
      //    (skipped if no venue has been set yet).
      let distance = null;
      if (meeting.venueLat != null && meeting.venueLng != null) {
        distance = getDistanceFromLatLngInMeters(location.lat, location.lng, meeting.venueLat, meeting.venueLng);
        if (distance > meeting.radiusMeters) {
          const e = new Error(`You must be within ${meeting.radiusMeters} meters of the venue. Current distance: ${distance.toFixed(0)}m`);
          e.statusCode = 400;
          throw e;
        }
      }

      // 4. Create/refresh the person's record.
      const shareholderUpdate = { name, phoneNumber };
      if (email) shareholderUpdate.email = email;
      if (address) shareholderUpdate.address = address;

      const shareholder = await tx.shareholder.upsert({
        where: { koperasiId },
        update: shareholderUpdate,
        create: { name, koperasiId, phoneNumber, email: email || null, address: address || null }
      });

      // 6. Record the check-in.
      return tx.attendance.create({
        data: {
          meetingId: meeting.id,
          shareholderId: shareholder.id,
          koperasiId,
          name,
          phoneNumber,
          date: today,
          time: new Date().toLocaleTimeString('en-IN', { hour12: false }),
          locationLat: location.lat,
          locationLng: location.lng,
          deviceFingerprint,
          status: "present",
          distanceFromVenue: distance
        }
      });
    });

    res.json({
      status: "success",
      message: "Attendance marked successfully",
      data: attendance
    });
  } catch (error) {
    if (error.statusCode) {
      return res.status(error.statusCode).json({ status: "error", message: error.message });
    }
    console.error("Attendance error:", error);
    res.status(500).json({ status: "error", message: error.message });
  }
});

app.get('/api/shareholders/profile', async (req, res) => {
    try {
        const { koperasiId } = req.query;
        if (!koperasiId) {
            return res.status(400).json({ error: 'koperasiId is required' });
        }
        const shareholder = await prisma.shareholder.findUnique({ where: { koperasiId } });
        if (!shareholder) {
            return res.status(404).json({ error: 'Shareholder not found' });
        }
        res.json({ data: shareholder });
    } catch (error) {
        console.error(`[PROFILE] Error fetching profile:`, error);
        res.status(500).json({ error: error.message });
    }
});

app.get('/api/shareholders/notifications', async (req, res) => {
  // TODO: Replace with real data lookup
  const dummyNotifications = [
    { text: "AGM agenda published.", timestamp: new Date(), read: false },
    { text: "Dividend statement available.", timestamp: new Date(), read: true }
  ];
  res.json({ data: dummyNotifications });
});

app.post('/api/shareholders/notifications/read', (req, res) => {
  const { koperasiId } = req.body;
  // TODO: Implement actual DB update here
  console.log(`Marking all notifications as read for koperasiId: ${koperasiId}`);
  res.json({ status: 'success' });
});

app.get("/api/shareholders/:koperasiId/attendance", async (req, res) => {
    try {
        const { koperasiId } = req.params;

        const shareholder = await prisma.shareholder.findUnique({ where: { koperasiId } });
        if (!shareholder) {
            return res.status(404).json({ error: 'Shareholder not found' });
        }

        const totalMeetings = await prisma.meeting.count();

        const attendance = await prisma.attendance.findMany({
            where: { koperasiId },
            orderBy: { date: 'asc' }
        });

        const presentDays = attendance.filter(a => a.status === 'present').length;
        const percentage = totalMeetings > 0 ? Math.round((presentDays / totalMeetings) * 100) : 0;

        const monthlyData = attendance.reduce((acc, record) => {
            const monthYear = new Date(record.date).toLocaleString('default', { month: 'short', year: 'numeric' });
            if (!acc[monthYear]) acc[monthYear] = { present: 0, total: 0 };
            acc[monthYear].total++;
            if (record.status === 'present') acc[monthYear].present++;
            return acc;
        }, {});

        const labels = Object.keys(monthlyData);
        const studentAttendance = labels.map(label =>
            monthlyData[label].total > 0 ? Math.round((monthlyData[label].present / monthlyData[label].total) * 100) : 0
        );

        res.json({
            status: "success",
            data: {
                attendanceRecords: attendance,
                attendancePercentage: percentage,
                totalClasses: totalMeetings,
                presentDays: presentDays,
                chartData: {
                    labels,
                    studentAttendance,
                    departmentAverage: studentAttendance.map(p => Math.max(70, Math.min(95, p + (Math.random() * 10 - 5))))
                }
            }
        });

    } catch (error) {
        console.error("Error fetching attendance:", error);
        res.status(500).json({ status: "error", message: error.message });
    }
});

app.get('/api/shareholders/:koperasiId/documents', async (req, res) => {
  // Dummy data, replace with DB lookup
  res.json({
    data: {
      idCardUrl: null,
      resumeUrl: null,
      feeReceipts: [],
      gradeSheets: []
    }
  });
});


// --- ALGORITHM ENDPOINTS (graph/DP demos; mock data) ---

// Dijkstra: shortest path across a sample venue map
app.get('/api/navigation/shortest-path', async (req, res) => {
    try {
        const { start, end } = req.query;
        if (!start || !end) {
            return res.status(400).json({ status: "error", message: "Start and end locations are required." });
        }

        const graphData = {
            nodes: ["HostelA", "HostelB", "Library", "Mess", "AdminBuilding", "CSEDept", "ECEdept", "MainGate"],
            edges: [
                { from: "HostelA", to: "Mess", weight: 5 },
                { from: "HostelA", to: "Library", weight: 7 },
                { from: "Mess", to: "CSEDept", weight: 10 },
                { from: "Library", to: "CSEDept", weight: 6 },
                { from: "Library", to: "AdminBuilding", weight: 3 },
                { from: "AdminBuilding", to: "ECEdept", weight: 4 },
                { from: "CSEDept", to: "ECEdept", weight: 2 },
                { from: "MainGate", to: "HostelA", weight: 15 },
                { from: "MainGate", to: "AdminBuilding", weight: 8 },
            ]
        };

        if (!graphData.nodes.includes(start) || !graphData.nodes.includes(end)) {
             return res.status(404).json({ status: "error", message: "One or both locations not found in map data." });
        }

        const result = dijkstra.findShortestPath(start, end, graphData);

        if (!result || result.path.length === 0) {
            return res.status(404).json({ status: "success", message: `No path found from ${start} to ${end}.`, data: result });
        }

        res.json({ status: "success", data: result });

    } catch (error) {
        console.error("Dijkstra shortest path error:", error);
        if (error.message.includes("module not loaded")) {
             return res.status(501).json({ status: "error", message: "Navigation module is not available." });
        }
        res.status(500).json({ status: "error", message: error.message });
    }
});

// Knapsack/DP: profile recommendations
app.get('/api/shareholders/:koperasiId/recommendations', async (req, res) => {
    const { koperasiId } = req.params;
    const { type } = req.query;

    if (!type) {
        return res.status(400).json({ status: "error", message: "Recommendation type is required (e.g., 'course', 'job')." });
    }

    try {
        const shareholder = await prisma.shareholder.findUnique({ where: { koperasiId } });
        if (!shareholder) {
            return res.status(404).json({ status: "error", message: "Shareholder not found." });
        }

        let availableItems = [];
        if (type === "course") {
            availableItems = [
                { id: "CS101", name: "Intro to Programming", difficulty: 2, relevance_tags: ["programming", "beginner"] },
                { id: "CS305", name: "Machine Learning", difficulty: 4, relevance_tags: ["ai", "ml", "advanced", "math"] },
                { id: "DS202", name: "Data Structures", difficulty: 3, relevance_tags: ["programming", "core"] },
                { id: "EE201", name: "Basic Electronics", difficulty: 3, relevance_tags: ["electronics", "hardware"] },
            ];
        } else if (type === "job") {
            availableItems = [
                { id: "JOB01", title: "Software Dev Intern", required_skills: ["javascript", "nodejs"], company: "TechCorp" },
                { id: "JOB02", title: "Data Analyst", required_skills: ["python", "sql", "statistics"], company: "DataDrivenLLC" },
                { id: "JOB03", title: "Hardware Engineer", required_skills: ["verilog", "circuit design"], company: "ChipMakers" },
            ];
        } else {
            return res.status(400).json({ status: "error", message: "Unsupported recommendation type." });
        }

        const recommendations = profileOptimizer.getProfileRecommendations(shareholder, type, availableItems);

        res.json({ status: "success", data: recommendations });

    } catch (error) {
        console.error("Profile recommendation error:", error);
         if (error.message.includes("module not loaded")) {
             return res.status(501).json({ status: "error", message: "Recommendation module is not available." });
        }
        res.status(500).json({ status: "error", message: error.message });
    }
});

// DFS/BFS: community / network explorer
app.get('/api/shareholders/:koperasiId/community', async (req, res) => {
    const { koperasiId } = req.params;
    const depth = parseInt(req.query.depth) || 2;
    const algorithm = req.query.algorithm || 'bfs';

    if (algorithm !== 'bfs' && algorithm !== 'dfs') {
        return res.status(400).json({ status: "error", message: "Invalid algorithm type. Use 'bfs' or 'dfs'." });
    }
    if (depth <= 0 || depth > 5) {
        return res.status(400).json({ status: "error", message: "Depth must be between 1 and 5." });
    }

    try {
        const startExists = await prisma.shareholder.findUnique({ where: { koperasiId }, select: { id: true } });
        if (!startExists) {
            return res.status(404).json({ status: "error", message: "Starting shareholder not found." });
        }

        const allShareholders = await prisma.shareholder.findMany({
            select: { koperasiId: true, name: true }
        });
        const mockConnections = [
            { from: allShareholders[0]?.koperasiId, to: allShareholders[1]?.koperasiId, type: "associate" },
            { from: allShareholders[0]?.koperasiId, to: allShareholders[2]?.koperasiId, type: "associate" },
            { from: allShareholders[1]?.koperasiId, to: allShareholders[3]?.koperasiId, type: "associate" },
        ].filter(c => c.from && c.to);

        const graphData = {
            students: allShareholders.map(s => ({ id: s.koperasiId, name: s.name })),
            connections: mockConnections
        };

        const communityData = graphTraversal.exploreCommunity(koperasiId, graphData, depth, algorithm);

        res.json({ status: "success", data: communityData });

    } catch (error) {
        console.error("Community exploration error:", error);
        if (error.message.includes("module not loaded")) {
             return res.status(501).json({ status: "error", message: "Graph traversal module is not available." });
        }
        res.status(500).json({ status: "error", message: error.message });
    }
});

// --- END ALGORITHM ENDPOINTS ---


// Error Handlers
app.use((req, res) => {
  res.status(404).json({ status: "error", message: "Route not found" });
});

app.use((err, req, res, next) => {
  console.error(" Server error:", {
    message: err.message,
    stack: process.env.NODE_ENV === 'development' ? err.stack : undefined,
    url: req.url,
    method: req.method
  });
  res.status(500).json({ status: "error", message: "Internal server error" });
});

// Database connection + start server
const PORT = process.env.PORT || 5000;

prisma.$connect()
  .then(() => {
    console.log("Connected to MySQL via Prisma");
    app.listen(PORT, () => console.log(`Server running on port ${PORT}`));
  })
  .catch(err => {
    console.error("Database connection error:", err);
    process.exit(1);
  });

// Graceful shutdown
process.on('SIGINT', async () => {
  await prisma.$disconnect();
  process.exit(0);
});
