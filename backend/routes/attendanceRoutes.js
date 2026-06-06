const express = require('express');
const router = express.Router();
const prisma = require('../db');

// Get attendance by koperasiId
router.get('/', async (req, res) => {
  try {
    const { koperasiId } = req.query;

    if (!koperasiId) {
      return res.status(400).json({ error: "koperasiId is required" });
    }

    const attendance = await prisma.attendance.findMany({
      where: { koperasiId },
      orderBy: [{ date: 'desc' }, { time: 'desc' }]
    });

    res.json(attendance);
  } catch (error) {
    console.error("Error fetching attendance:", error);
    res.status(500).json({ error: "Server error" });
  }
});

module.exports = router;
