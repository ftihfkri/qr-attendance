const express = require('express');
const router = express.Router();
const prisma = require('../db');

// GET /profile?koperasiId=KOP-0001
router.get('/profile', async (req, res) => {
  try {
    const { koperasiId } = req.query;

    if (!koperasiId) {
      return res.status(400).json({
        success: false,
        error: "koperasiId is required"
      });
    }

    const shareholder = await prisma.shareholder.findUnique({
      where: { koperasiId: koperasiId.trim() }
    });

    if (!shareholder) {
      return res.status(404).json({
        success: false,
        error: "Shareholder not found"
      });
    }

    res.json({
      success: true,
      data: shareholder
    });

  } catch (error) {
    res.status(500).json({
      success: false,
      error: "Server error"
    });
  }
});

module.exports = router;
