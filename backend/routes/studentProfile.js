const express = require('express');
const router = express.Router();
const prisma = require('../db');

// Get shareholder profile by koperasiId
router.get('/profile', async (req, res) => {
  try {
    const { koperasiId } = req.query;
    if (!koperasiId) {
      return res.status(400).json({ error: 'koperasiId is required' });
    }

    const shareholder = await prisma.shareholder.findUnique({
      where: { koperasiId }
    });

    if (!shareholder) {
      return res.status(404).json({ error: 'Shareholder not found' });
    }

    res.json({ data: shareholder });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

module.exports = router;
