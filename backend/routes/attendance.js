const express = require('express');
const router = express.Router();
const prisma = require('../db');

// GET shareholder AGM attendance records by koperasiId
router.get('/', async (req, res) => {
  try {
    const { koperasiId } = req.query;

    if (!koperasiId) {
      return res.status(400).json({ message: "koperasiId is required" });
    }

    const shareholder = await prisma.shareholder.findUnique({ where: { koperasiId } });
    if (!shareholder) {
      return res.status(404).json({ message: "Shareholder not found" });
    }

    const attendance = await prisma.attendance.findMany({
      where: { koperasiId },
      orderBy: [{ date: 'desc' }, { time: 'desc' }]
    });

    res.json({
      status: "success",
      name: shareholder.name,
      koperasiId: shareholder.koperasiId,
      attendance
    });
  } catch (error) {
    console.error("Attendance fetch error:", error);
    res.status(500).json({ status: "error", message: error.message });
  }
});

module.exports = router;
