require('dotenv').config(); // ✅ this loads .env vars

const crypto = require('crypto');
const fetch = require('node-fetch');

const userid = '2';
const secret = process.env.MOODLE_SECRET; // ✅ will now work

if (!secret) {
    console.error('❌ MOODLE_SECRET is missing from .env');
    process.exit(1);
}

const token = crypto.createHmac('sha256', secret).update(userid).digest('hex');
console.log('🔐 Generated token:', token);

fetch(process.env.MOODLE_VALIDATE_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ userid, token })
})
    .then(res => res.json())
    .then(json => console.log('✅ Moodle response:', json))
    .catch(err => console.error('❌ Fetch error:', err));
