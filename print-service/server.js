require('dotenv').config();
const express = require('express');
const cors = require('cors');
const { printReceipt, testPrinter, getPrinterStatus } = require('./printer');

const app = express();
const PORT = process.env.PORT || 3001;

// Middleware
app.use(cors());
app.use(express.json());

// Health check endpoint
app.get('/health', (req, res) => {
    res.json({ 
        status: 'online',
        service: 'POS Print Service',
        version: '1.0.0'
    });
});

// Get printer status
app.get('/printer/status', async (req, res) => {
    try {
        const status = await getPrinterStatus();
        res.json(status);
    } catch (error) {
        res.status(500).json({ 
            error: 'Failed to get printer status',
            message: error.message 
        });
    }
});

// Test print endpoint
app.post('/printer/test', async (req, res) => {
    try {
        await testPrinter();
        res.json({ 
            success: true,
            message: 'Test print sent successfully' 
        });
    } catch (error) {
        console.error('Test print error:', error);
        res.status(500).json({ 
            error: 'Failed to print test page',
            message: error.message 
        });
    }
});

// Main print endpoint
app.post('/print', async (req, res) => {
    try {
        const receiptData = req.body;
        
        // Validate receipt data
        if (!receiptData || !receiptData.items || receiptData.items.length === 0) {
            return res.status(400).json({ 
                error: 'Invalid receipt data',
                message: 'Receipt must contain items' 
            });
        }

        console.log('Printing receipt for invoice:', receiptData.invoice_number);
        
        // Send to printer
        await printReceipt(receiptData);
        
        res.json({ 
            success: true,
            message: 'Receipt printed successfully',
            invoice_number: receiptData.invoice_number
        });
    } catch (error) {
        console.error('Print error:', error);
        res.status(500).json({ 
            error: 'Failed to print receipt',
            message: error.message,
            details: process.env.NODE_ENV === 'development' ? error.stack : undefined
        });
    }
});

// Error handling middleware
app.use((err, req, res, next) => {
    console.error('Server error:', err);
    res.status(500).json({ 
        error: 'Internal server error',
        message: err.message 
    });
});

// Start server
app.listen(PORT, '127.0.0.1', () => {
    console.log('╔════════════════════════════════════════╗');
    console.log('║   POS Print Service Started            ║');
    console.log('╚════════════════════════════════════════╝');
    console.log(`🖨️  Listening on: http://localhost:${PORT}`);
    console.log(`📅  Started at: ${new Date().toLocaleString()}`);
    console.log('');
    console.log('Endpoints:');
    console.log(`  GET  /health          - Service health check`);
    console.log(`  GET  /printer/status  - Get printer status`);
    console.log(`  POST /printer/test    - Print test page`);
    console.log(`  POST /print           - Print receipt`);
    console.log('');
    console.log('Press Ctrl+C to stop');
    console.log('════════════════════════════════════════');
});

// Graceful shutdown
process.on('SIGINT', () => {
    console.log('\n\n🛑 Shutting down print service...');
    process.exit(0);
});

process.on('SIGTERM', () => {
    console.log('\n\n🛑 Shutting down print service...');
    process.exit(0);
});
