// Single shared PrismaClient instance for the whole backend.
// Importing this module everywhere avoids opening multiple connection pools.
const { PrismaClient } = require('@prisma/client');

const prisma = new PrismaClient();

module.exports = prisma;
