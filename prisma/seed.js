// Seeds a single ACTIVE AGM meeting (required for check-in to work) plus a
// couple of sample shareholders. Run with: npx prisma db seed
const { PrismaClient } = require('@prisma/client');

const prisma = new PrismaClient();

async function main() {
  // Deactivate any existing active meetings so only one stays active.
  await prisma.meeting.updateMany({
    where: { isActive: true },
    data: { isActive: false },
  });

  const meeting = await prisma.meeting.create({
    data: {
      title: 'Annual General Meeting 2026',
      meetingDate: new Date('2026-06-05T09:00:00'),
      venueName: 'Koperasi Main Hall',
      // Venue coordinates — shareholders must be within radiusMeters to check in.
      // Replace with your real venue location.
      venueLat: 30.2679634,
      venueLng: 77.991887,
      radiusMeters: 100,
      isActive: true,
    },
  });
  console.log(`Active meeting created: #${meeting.id} "${meeting.title}"`);

  const shareholders = [
    { name: 'Aisyah Binti Rahman', koperasiId: 'KOP-0001', phoneNumber: '+60123456789', email: 'aisyah@example.com', address: 'Lot 12, Jalan Mawar' },
    { name: 'Tan Wei Ming', koperasiId: 'KOP-0002', phoneNumber: '+60198765432', email: 'weiming@example.com', address: 'No 8, Taman Indah' },
  ];

  for (const s of shareholders) {
    await prisma.shareholder.upsert({
      where: { koperasiId: s.koperasiId },
      update: {},
      create: s,
    });
  }
  console.log(`Seeded ${shareholders.length} sample shareholders.`);
}

main()
  .then(async () => {
    await prisma.$disconnect();
  })
  .catch(async (e) => {
    console.error(e);
    await prisma.$disconnect();
    process.exit(1);
  });
