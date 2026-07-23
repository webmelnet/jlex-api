const escpos = require('escpos');
escpos.USB = require('escpos-usb');

// Configure your printer
// For Gprinter GP-1324D, you'll need to find the vendor ID and product ID
// You can find these by running `lsusb` on Linux or using Device Manager on Windows
const PRINTER_CONFIG = {
    // Default Gprinter values - adjust based on your actual printer
    vendorId: process.env.PRINTER_VENDOR_ID || 0x28e9,  // Gprinter vendor ID
    productId: process.env.PRINTER_PRODUCT_ID || 0x0289, // GP-1324D product ID
    encoding: 'UTF8'
};

/**
 * Get USB device for the printer
 */
function getDevice() {
    try {
        const device = new escpos.USB(PRINTER_CONFIG.vendorId, PRINTER_CONFIG.productId);
        return device;
    } catch (error) {
        console.error('Failed to connect to printer:', error.message);
        throw new Error('Printer not found. Please check USB connection.');
    }
}

/**
 * Format currency
 */
function formatCurrency(amount) {
    return `₱${parseFloat(amount).toFixed(2)}`;
}

/**
 * Format date/time
 */
function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('en-PH', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
}

/**
 * Center text on 48-character width receipt
 */
function centerText(text, width = 48) {
    const padding = Math.floor((width - text.length) / 2);
    return ' '.repeat(Math.max(0, padding)) + text;
}

/**
 * Create line of text with left and right alignment
 */
function leftRight(left, right, width = 48) {
    const spaces = width - left.length - right.length;
    return left + ' '.repeat(Math.max(1, spaces)) + right;
}

/**
 * Print receipt
 */
async function printReceipt(receiptData) {
    return new Promise((resolve, reject) => {
        let device;
        let printer;

        try {
            device = getDevice();
            
            device.open(function(error) {
                if (error) {
                    console.error('Failed to open device:', error);
                    return reject(new Error('Failed to open printer device'));
                }

                try {
                    printer = new escpos.Printer(device, { encoding: PRINTER_CONFIG.encoding });

                    // Start printing
                    printer
                        .font('a')
                        .align('ct')
                        .style('bu')
                        .size(1, 1)
                        .text(receiptData.store_name || 'YOUR STORE NAME')
                        .style('normal')
                        .size(0, 0)
                        .text(receiptData.store_address || '')
                        .text(receiptData.store_phone || '')
                        .text(receiptData.store_email || '')
                        .text('')
                        .drawLine()
                        .text('')

                        // Invoice details
                        .align('lt')
                        .text(leftRight('Invoice:', receiptData.invoice_number || 'N/A'))
                        .text(leftRight('Date:', formatDateTime(receiptData.sale_date || new Date())))
                        .text(leftRight('Cashier:', receiptData.cashier_name || 'N/A'))
                        
                    // Customer info (if available)
                    if (receiptData.customer_name) {
                        printer.text(leftRight('Customer:', receiptData.customer_name));
                    }

                    printer
                        .text('')
                        .drawLine()
                        .text('')

                        // Column headers
                        .style('b')
                        .text('Item                      Qty   Price  Total')
                        .style('normal')
                        .text('')

                    // Print items
                    receiptData.items.forEach(item => {
                        const itemName = item.product_name.substring(0, 22);
                        const qty = String(item.quantity).padStart(3);
                        const price = formatCurrency(item.price).padStart(8);
                        const total = formatCurrency(item.quantity * item.price).padStart(8);
                        
                        printer.text(`${itemName.padEnd(22)} ${qty} ${price} ${total}`);
                        
                        // Print discount if applicable
                        if (item.discount && parseFloat(item.discount) > 0) {
                            printer.text(`  Discount: -${formatCurrency(item.discount)}`);
                        }
                    });

                    printer
                        .text('')
                        .drawLine()
                        .text('')

                        // Totals section
                        .align('rt')
                        .text(leftRight('Subtotal:', formatCurrency(receiptData.subtotal)))

                    // Discount
                    if (receiptData.discount && parseFloat(receiptData.discount) > 0) {
                        printer.text(leftRight('Discount:', `-${formatCurrency(receiptData.discount)}`));
                    }

                    // Tax
                    if (receiptData.tax && parseFloat(receiptData.tax) > 0) {
                        printer.text(leftRight('Tax:', formatCurrency(receiptData.tax)));
                    }

                    printer
                        .text('')
                        .style('b')
                        .size(1, 1)
                        .text(leftRight('TOTAL:', formatCurrency(receiptData.total)))
                        .style('normal')
                        .size(0, 0)
                        .text('')
                        .text(leftRight('Amount Paid:', formatCurrency(receiptData.amount_paid)))
                        .text(leftRight('Change:', formatCurrency(receiptData.change)))
                        .text('')
                        .text(leftRight('Payment:', receiptData.payment_method || 'Cash'))
                        .text('')
                        .drawLine()
                        .text('')

                        // Footer
                        .align('ct')
                        .text('Thank you for your purchase!')
                        .text('Please come again')
                        .text('')
                        
                    // Notes (if any)
                    if (receiptData.notes) {
                        printer
                            .text('')
                            .text(receiptData.notes)
                            .text('');
                    }

                    printer
                        .text('')
                        .text('Powered by Your POS System')
                        .text('')
                        .text('')
                        .text('')
                        
                        // Cut paper
                        .cut()
                        
                        // Close connection
                        .close(() => {
                            console.log('✓ Receipt printed successfully');
                            resolve({ success: true });
                        });

                } catch (printError) {
                    console.error('Print error:', printError);
                    if (device) {
                        try {
                            device.close();
                        } catch (closeError) {
                            console.error('Error closing device:', closeError);
                        }
                    }
                    reject(new Error('Failed to format and print receipt'));
                }
            });

        } catch (error) {
            console.error('Printer connection error:', error);
            reject(error);
        }
    });
}

/**
 * Test printer with a simple test page
 */
async function testPrinter() {
    return new Promise((resolve, reject) => {
        let device;
        
        try {
            device = getDevice();
            
            device.open(function(error) {
                if (error) {
                    return reject(new Error('Failed to open printer'));
                }

                const printer = new escpos.Printer(device, { encoding: PRINTER_CONFIG.encoding });

                printer
                    .font('a')
                    .align('ct')
                    .style('bu')
                    .size(1, 1)
                    .text('PRINTER TEST')
                    .style('normal')
                    .size(0, 0)
                    .text('')
                    .text('Gprinter GP-1324D')
                    .text('POS Print Service')
                    .text('')
                    .text(new Date().toLocaleString())
                    .text('')
                    .text('If you can read this,')
                    .text('your printer is working correctly!')
                    .text('')
                    .text('')
                    .drawLine()
                    .text('')
                    .barcode('1234567890', 'CODE39')
                    .text('')
                    .text('')
                    .cut()
                    .close(() => {
                        console.log('✓ Test page printed');
                        resolve({ success: true });
                    });
            });

        } catch (error) {
            reject(error);
        }
    });
}

/**
 * Get printer status
 */
async function getPrinterStatus() {
    try {
        const device = getDevice();
        return {
            connected: true,
            vendorId: PRINTER_CONFIG.vendorId,
            productId: PRINTER_CONFIG.productId,
            model: 'Gprinter GP-1324D',
            status: 'Ready'
        };
    } catch (error) {
        return {
            connected: false,
            error: error.message,
            status: 'Disconnected'
        };
    }
}

module.exports = {
    printReceipt,
    testPrinter,
    getPrinterStatus
};
