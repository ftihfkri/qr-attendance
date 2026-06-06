// Create or update an admin user (bcrypt-hashed password).
// Usage: node backend/create-admin.js <username> <password>
//   or:  npm run create-admin -- <username> <password>
const prisma = require('./db');
const bcrypt = require('bcryptjs');

const [, , username, password] = process.argv;
if (!username || !password) {
  console.error('Usage: node backend/create-admin.js <username> <password>');
  process.exit(1);
}

(async () => {
  const passwordHash = bcrypt.hashSync(password, 10);
  const user = await prisma.user.upsert({
    where: { username },
    update: { passwordHash },
    create: { username, passwordHash }
  });
  console.log(`Admin user '${user.username}' saved (id ${user.id}).`);
  await prisma.$disconnect();
})().catch(async (e) => {
  console.error(e);
  await prisma.$disconnect();
  process.exit(1);
});
