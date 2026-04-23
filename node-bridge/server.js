/**
 * GenifyAI WhatsApp Bridge - Node.js Server
 * ===========================================
 * 
 * මෙය whatsapp-web.js library එක භාවිතා කරලා 
 * WhatsApp Automation (Option B) සඳහා QR Scan based connection 
 * manage කරන Node.js Bridge Server එකකි.
 * 
 * Features:
 * - QR Code Generation for multiple users
 * - Receive incoming messages & forward to Laravel
 * - Receive send commands from Laravel & send to WhatsApp
 * - Session management (save/restore)
 * - Auto-reconnect on disconnect
 */

const express = require('express');
const { Client, LocalAuth, MessageMedia } = require('whatsapp-web.js');
const qrcode = require('qrcode');
const qrcodeTerminal = require('qrcode-terminal');
const axios = require('axios');
const cors = require('cors');
const bodyParser = require('body-parser');
const fs = require('fs');
const path = require('path');
const { v4: uuidv4 } = require('uuid');

// ============================================
// CONFIGURATION
// ============================================

// Load config from environment or use defaults
const PORT = process.env.PORT || 3000;
const LARAVEL_URL = process.env.LARAVEL_URL || 'http://172.235.19.62';
const LARAVEL_API_KEY = process.env.LARAVEL_API_KEY || 'genify-node-bridge-secret-2026';
const QR_CODE_DIR = process.env.QR_CODE_DIR || './public/qr-codes';
const SESSION_DIR = process.env.SESSION_DIR || './sessions';

// ============================================
// SETUP
// ============================================

// Ensure directories exist
[QR_CODE_DIR, SESSION_DIR, './public'].forEach(dir => {
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
});

// App Initialization
const app = express();
app.use(cors());
app.use(bodyParser.json({ limit: '10mb' }));
app.use(express.static('public'));

// Store active WhatsApp clients per user
const clients = new Map(); // Map<user_id, { client, status, qrReady }>

// ============================================
// WHATSAPP CLIENT MANAGEMENT
// ============================================

/**
 * Create or get WhatsApp client for a user
 * @param {number} userId - Laravel user ID
 * @returns {Promise<object>} - { client, status }
 */
async function getOrCreateClient(userId) {
    const key = String(userId);
    
    // If client already exists, return it
    if (clients.has(key)) {
        const existing = clients.get(key);
        return existing;
    }

    console.log(`[${userId}] Creating new WhatsApp client...`);

    // Session storage path
    const sessionPath = path.join(SESSION_DIR, `session-${userId}`);
    
    const client = new Client({
        authStrategy: new LocalAuth({
            dataPath: sessionPath,
        }),
        puppeteer: {
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--no-first-run',
                '--no-zygote',
                '--single-process',
                '--disable-gpu'
            ],
        },
        webVersionCache: {
            type: 'remote',
            remotePath: 'https://raw.githubusercontent.com/wppconnect-team/wa-version/main/html/2.2412.54.html',
        }
    });

    const clientData = {
        client,
        status: 'initializing',
        qrReady: false,
        userId,
    };

    clients.set(key, clientData);

    // --- Event Handlers ---

    // QR Code Generated
    client.on('qr', async (qr) => {
        console.log(`[${userId}] QR Code generated`);
        
        try {
            // Generate QR as data URL (base64)
            const qrDataUrl = await qrcode.toDataURL(qr);
            
            // Also save as PNG file
            const qrFileName = `qr-${userId}.png`;
            const qrFilePath = path.join(QR_CODE_DIR, qrFileName);
            await qrcode.toFile(qrFilePath, qr);
            
            // Display in terminal
            qrcodeTerminal.generate(qr, { small: true });
            
            clientData.qrReady = true;
            clientData.qrDataUrl = qrDataUrl;
            clientData.qrFilePath = `/qr-codes/${qrFileName}`;
            
            // Notify Laravel about QR code
            await notifyLaravel(userId, 'qr_generated', {
                qr_code_path: `/qr-codes/${qrFileName}`,
                qr_data_url: qrDataUrl,
            });
            
        } catch (err) {
            console.error(`[${userId}] QR generation error:`, err);
        }
    });

    // Client Ready (Authenticated)
    client.on('ready', async () => {
        console.log(`[${userId}] ✅ Client is ready!`);
        
        const info = client.info;
        clientData.status = 'connected';
        clientData.qrReady = false;
        
        // Notify Laravel about successful connection
        await notifyLaravel(userId, 'connected', {
            phone: info.wid.user,
            pushname: info.pushname,
            platform: info.platform,
        });
    });

    // Authentication Failure
    client.on('auth_failure', (msg) => {
        console.error(`[${userId}] ❌ Auth failure:`, msg);
        clientData.status = 'auth_failure';
        
        notifyLaravel(userId, 'auth_failure', { error: msg });
    });

    // Disconnected
    client.on('disconnected', async (reason) => {
        console.log(`[${userId}] 🔌 Disconnected:`, reason);
        clientData.status = 'disconnected';
        clientData.qrReady = false;
        
        await notifyLaravel(userId, 'disconnected', { reason });
        
        // Auto-reconnect after 5 seconds
        console.log(`[${userId}] Attempting reconnect in 5 seconds...`);
        setTimeout(async () => {
            try {
                // Destroy old client and create new one
                clients.delete(key);
                await getOrCreateClient(userId);
            } catch (err) {
                console.error(`[${userId}] Reconnect failed:`, err);
            }
        }, 5000);
    });

    // Message Received
    client.on('message', async (message) => {
        console.log(`[${userId}] 📩 Message from ${message.from}: ${message.body.substring(0, 50)}`);
        
        // Ignore own messages and status broadcasts
        if (message.from === 'status@broadcast') return;
        if (message.fromMe) return;
        
        // Extract phone number (remove @c.us suffix)
        const phone = message.from.replace('@c.us', '').replace('@s.whatsapp.net', '');
        
        // Get message content
        let body = message.body;
        let msgType = 'text';
        
        if (message.hasMedia) {
            const media = await message.downloadMedia();
            if (media.mimetype.startsWith('audio/')) {
                msgType = 'audio';
                body = media.data; // base64 encoded audio
            } else if (media.mimetype.startsWith('image/')) {
                msgType = 'image';
                body = media.data;
            }
        }
        
        // Forward to Laravel
        try {
            await axios.post(`${LARAVEL_URL}/api/whatsapp/webhook/automation`, {
                user_id: userId,
                from: phone,
                body: body,
                type: msgType,
                timestamp: Math.floor(Date.now() / 1000),
            }, {
                headers: {
                    'x-api-key': LARAVEL_API_KEY,
                    'Content-Type': 'application/json',
                },
                timeout: 30000,
            });
            
            console.log(`[${userId}] ✅ Message forwarded to Laravel`);
        } catch (err) {
            console.error(`[${userId}] ❌ Failed to forward message:`, err.message);
        }
    });

    // Initialize client
    try {
        await client.initialize();
    } catch (err) {
        console.error(`[${userId}] Initialization error:`, err);
        clientData.status = 'error';
    }

    return clientData;
}

/**
 * Disconnect and remove a WhatsApp client
 */
async function disconnectClient(userId) {
    const key = String(userId);
    const clientData = clients.get(key);
    
    if (clientData) {
        try {
            await clientData.client.destroy();
        } catch (err) {
            console.error(`[${userId}] Destroy error:`, err);
        }
        clients.delete(key);
        console.log(`[${userId}] 🔌 Client disconnected and removed`);
    }
}

// ============================================
// LARAVEL NOTIFICATIONS
// ============================================

/**
 * Send status update to Laravel
 */
async function notifyLaravel(userId, event, data = {}) {
    try {
        await axios.post(`${LARAVEL_URL}/api/node-bridge/update-connection-status`, {
            user_id: userId,
            event: event,
            ...data,
        }, {
            headers: {
                'x-api-key': LARAVEL_API_KEY,
                'Content-Type': 'application/json',
            },
            timeout: 10000,
        });
    } catch (err) {
        // Don't log QR errors too loudly - they happen frequently
        if (event !== 'qr_generated') {
            console.error(`[${userId}] Notification failed:`, err.message);
        }
    }
}

// ============================================
// API ENDPOINTS
// ============================================

/**
 * GET /health
 * Health check endpoint
 */
app.get('/health', (req, res) => {
    const activeClients = [];
    clients.forEach((data, userId) => {
        activeClients.push({
            userId,
            status: data.status,
            qrReady: data.qrReady,
        });
    });
    
    res.json({
        status: 'running',
        uptime: process.uptime(),
        activeConnections: clients.size,
        clients: activeClients,
    });
});

/**
 * POST /connect
 * Start WhatsApp connection for a user (generate QR)
 * Body: { user_id: number }
 */
app.post('/connect', async (req, res) => {
    const { user_id } = req.body;
    
    if (!user_id) {
        return res.status(400).json({ error: 'user_id is required' });
    }
    
    try {
        const clientData = await getOrCreateClient(user_id);
        
        res.json({
            status: clientData.status,
            qr_ready: clientData.qrReady,
            qr_data_url: clientData.qrDataUrl || null,
            qr_file_path: clientData.qrFilePath || null,
        });
    } catch (err) {
        console.error(`[${user_id}] Connect error:`, err);
        res.status(500).json({ error: err.message });
    }
});

/**
 * POST /disconnect
 * Disconnect WhatsApp for a user
 * Body: { user_id: number }
 */
app.post('/disconnect', async (req, res) => {
    const { user_id } = req.body;
    
    if (!user_id) {
        return res.status(400).json({ error: 'user_id is required' });
    }
    
    try {
        await disconnectClient(user_id);
        res.json({ status: 'disconnected' });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

/**
 * POST /send-message
 * Send a WhatsApp message (called by Laravel)
 * Body: { user_id: number, phone: string, message: string }
 */
app.post('/send-message', async (req, res) => {
    const { user_id, phone, message } = req.body;
    
    if (!user_id || !phone || !message) {
        return res.status(400).json({ error: 'user_id, phone, and message are required' });
    }
    
    const key = String(user_id);
    const clientData = clients.get(key);
    
    if (!clientData || clientData.status !== 'connected') {
        return res.status(503).json({ 
            error: 'WhatsApp not connected',
            status: clientData?.status || 'not_found'
        });
    }
    
    try {
        // Format phone number for WhatsApp Web.js (add @c.us)
        const chatId = phone.includes('@c.us') ? phone : `${phone}@c.us`;
        
        await clientData.client.sendMessage(chatId, message);
        console.log(`[${userId}] ✅ Message sent to ${phone}`);
        
        res.json({ status: 'sent', to: phone });
    } catch (err) {
        console.error(`[${user_id}] Send message error:`, err);
        res.status(500).json({ error: err.message });
    }
});

/**
 * POST /get-qr
 * Get current QR code for a user
 * Body: { user_id: number }
 */
app.post('/get-qr', async (req, res) => {
    const { user_id } = req.body;
    
    if (!user_id) {
        return res.status(400).json({ error: 'user_id is required' });
    }
    
    const key = String(user_id);
    const clientData = clients.get(key);
    
    if (!clientData) {
        return res.status(404).json({ error: 'No client found. Call /connect first.' });
    }
    
    res.json({
        status: clientData.status,
        qr_ready: clientData.qrReady,
        qr_data_url: clientData.qrDataUrl || null,
        qr_file_path: clientData.qrFilePath || null,
    });
});

/**
 * POST /status
 * Get connection status for a user
 * Body: { user_id: number }
 */
app.post('/status', async (req, res) => {
    const { user_id } = req.body;
    
    if (!user_id) {
        return res.status(400).json({ error: 'user_id is required' });
    }
    
    const key = String(user_id);
    const clientData = clients.get(key);
    
    res.json({
        exists: !!clientData,
        status: clientData?.status || 'not_initialized',
        qr_ready: clientData?.qrReady || false,
        connected: clientData?.status === 'connected',
    });
});

// ============================================
// START SERVER
// ============================================

app.listen(PORT, () => {
    console.log(`
╔═══════════════════════════════════════════╗
║     GenifyAI WhatsApp Bridge Server       ║
║     =================================     ║
║                                           ║
║     Port: ${PORT}                           ║
║     Laravel: ${LARAVEL_URL}               ║
║     Status: ✅ RUNNING                     ║
║                                           ║
║     Endpoints:                            ║
║     GET  /health                          ║
║     POST /connect                         ║
║     POST /disconnect                      ║
║     POST /send-message                    ║
║     POST /get-qr                          ║
║     POST /status                          ║
╚═══════════════════════════════════════════╝
    `);
});

// Graceful shutdown
process.on('SIGINT', async () => {
    console.log('\nShutting down...');
    for (const [userId, clientData] of clients) {
        try {
            await clientData.client.destroy();
        } catch (err) {
            // ignore
        }
    }
    process.exit(0);
});

process.on('SIGTERM', async () => {
    for (const [userId, clientData] of clients) {
        try {
            await clientData.client.destroy();
        } catch (err) {
            // ignore
        }
    }
    process.exit(0);
});
