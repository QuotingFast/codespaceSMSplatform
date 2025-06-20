require('dotenv').config();
const express = require('express');
const bodyParser = require('body-parser');
const mysql = require('mysql2/promise');
const twilio = require('twilio');

const app = express();
app.use(bodyParser.json());

const db = mysql.createPool({
  host: process.env.DB_HOST,
  database: process.env.DB_NAME,
  user: process.env.DB_USER,
  password: process.env.DB_PASS,
});

const twilioClient = twilio(
  process.env.TWILIO_ACCOUNT_SID,
  process.env.TWILIO_AUTH_TOKEN
);

// Example: POST /webhook with { name, phone, email, ... }
app.post('/webhook', async (req, res) => {
  const lead = req.body;
  if (!lead || !lead.phone || !lead.name) {
    return res.status(400).json({ error: 'Missing lead data' });
  }

  try {
    // Store lead in DB
    await db.query(
      'INSERT INTO leads (name, phone, email, data, created_at) VALUES (?, ?, ?, ?, NOW())',
      [lead.name, lead.phone, lead.email || '', JSON.stringify(lead)]
    );

    // Generate quote link (could use lead ID for tracking)
    // For demo, just pass phone as param
    const quoteLink = `${process.env.QUOTE_BASE_URL}?phone=${encodeURIComponent(lead.phone)}&name=${encodeURIComponent(lead.name)}`;

    // Compose SMS
    const smsBody = `Hi ${lead.name}! Your personalized quote is ready: ${quoteLink}. You qualify for additional discounts that require a licensed agent to apply. CALL NOW!`;

    // Send SMS
    await twilioClient.messages.create({
      body: smsBody,
      from: process.env.TWILIO_PHONE,
      to: lead.phone,
    });

    res.json({ success: true, sent: true });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to process lead' });
  }
});

// Simple health check
app.get('/', (req, res) => res.send('SMS Lead Quote Platform is running.'));

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log(`Server running on ${PORT}`));