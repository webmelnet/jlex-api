# POS Print Service

Local thermal printer service for automatic receipt printing with Gprinter GP-1324D.

## Overview

This Node.js service runs locally on your POS terminal and handles automatic receipt printing. It receives print requests from your web-based POS application and communicates directly with your thermal printer.

## Features

- ✅ Automatic receipt printing via HTTP API
- ✅ ESC/POS thermal printer support
- ✅ USB printer connection
- ✅ Customizable receipt formatting
- ✅ Test print functionality
- ✅ Printer status monitoring
- ✅ Error handling and logging

## Prerequisites

### Windows (WSL Ubuntu)

1. **Install Node.js** (v16 or higher)
   ```bash
   curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
   sudo apt-get install -y nodejs
   ```

2. **Install USB Libraries**
   ```bash
   sudo apt-get update
   sudo apt-get install -y build-essential libudev-dev libusb-1.0-0-dev
   ```

3. **Configure USB Access for WSL**
   
   Since WSL doesn't have direct USB access by default, you have two options:

   **Option A: USB/IP (Recommended for WSL)**
   - Install usbipd-win on Windows: https://github.com/dorssel/usbipd-win
   - Share your printer USB device with WSL
   
   **Option B: Install on Windows directly**
   - Install Node.js for Windows
   - Run the print service natively on Windows (not in WSL)

### Production (Amazon EC2 with Ubuntu)

**Important:** The print service runs on the **POS terminal** (client machine), NOT on your EC2 server.

1. Install Node.js on the POS terminal:
   ```bash
   curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
   sudo apt-get install -y nodejs
   ```

2. Install USB libraries:
   ```bash
   sudo apt-get install -y build-essential libudev-dev libusb-1.0-0-dev
   ```

## Installation

1. **Copy this folder to your POS terminal**
   ```bash
   # Navigate to the print-service folder
   cd print-service
   ```

2. **Install dependencies**
   ```bash
   npm install
   ```

3. **Create environment file**
   ```bash
   cp .env.example .env
   ```

4. **Find your printer USB IDs**
   
   **On Linux:**
   ```bash
   lsusb
   ```
   Look for "Gprinter" in the output:
   ```
   Bus 001 Device 003: ID 28e9:0289 Gprinter Co., Ltd GP-1324D
   ```
   - Vendor ID: `0x28e9`
   - Product ID: `0x0289`

   **On Windows:**
   - Open Device Manager
   - Find your printer under "Printers" or "USB devices"
   - Right-click → Properties → Details → Hardware IDs
   - Look for VID (Vendor ID) and PID (Product ID)

5. **Update .env file with your printer IDs**
   ```env
   PRINTER_VENDOR_ID=0x28e9
   PRINTER_PRODUCT_ID=0x0289
   ```

6. **Test the printer connection**
   ```bash
   npm start
   ```
   
   In another terminal or browser:
   ```bash
   # Test printer status
   curl http://localhost:3001/printer/status
   
   # Print test page
   curl -X POST http://localhost:3001/printer/test
   ```

## Usage

### Start the Service

**Development mode (with auto-restart):**
```bash
npm run dev
```

**Production mode:**
```bash
npm start
```

### API Endpoints

#### 1. Health Check
```bash
GET http://localhost:3001/health
```

Response:
```json
{
  "status": "online",
  "service": "POS Print Service",
  "version": "1.0.0"
}
```

#### 2. Printer Status
```bash
GET http://localhost:3001/printer/status
```

Response:
```json
{
  "connected": true,
  "vendorId": 10473,
  "productId": 649,
  "model": "Gprinter GP-1324D",
  "status": "Ready"
}
```

#### 3. Test Print
```bash
POST http://localhost:3001/printer/test
```

#### 4. Print Receipt
```bash
POST http://localhost:3001/print
Content-Type: application/json

{
  "invoice_number": "INV-2025-001",
  "sale_date": "2025-01-27T10:30:00",
  "cashier_name": "John Doe",
  "customer_name": "Jane Smith",
  "items": [
    {
      "product_name": "Product A",
      "quantity": 2,
      "price": 150.00,
      "discount": 0
    }
  ],
  "subtotal": 300.00,
  "discount": 0,
  "tax": 36.00,
  "total": 336.00,
  "amount_paid": 500.00,
  "change": 164.00,
  "payment_method": "Cash"
}
```

## Running as a Service (Production)

### Windows

1. **Install PM2** (Process Manager)
   ```bash
   npm install -g pm2
   ```

2. **Start service with PM2**
   ```bash
   pm2 start server.js --name "pos-print-service"
   pm2 save
   pm2 startup
   ```

3. **PM2 will now:**
   - Auto-restart on crash
   - Start automatically on system boot
   - Log all output

### Linux

Create a systemd service:

1. Create service file:
   ```bash
   sudo nano /etc/systemd/system/pos-print.service
   ```

2. Add configuration:
   ```ini
   [Unit]
   Description=POS Print Service
   After=network.target

   [Service]
   Type=simple
   User=youruser
   WorkingDirectory=/path/to/print-service
   ExecStart=/usr/bin/node server.js
   Restart=always
   RestartSec=10

   [Install]
   WantedBy=multi-user.target
   ```

3. Enable and start:
   ```bash
   sudo systemctl enable pos-print.service
   sudo systemctl start pos-print.service
   sudo systemctl status pos-print.service
   ```

## Troubleshooting

### Printer Not Found

1. **Check USB connection**
   ```bash
   lsusb | grep Gprinter
   ```

2. **Check permissions**
   ```bash
   # Add your user to the lp (printer) group
   sudo usermod -a -G lp $USER
   
   # Create udev rule for printer
   sudo nano /etc/udev/rules.d/99-gprinter.rules
   ```
   
   Add:
   ```
   SUBSYSTEM=="usb", ATTR{idVendor}=="28e9", ATTR{idProduct}=="0289", MODE="0666"
   ```
   
   Reload rules:
   ```bash
   sudo udevadm control --reload-rules
   sudo udevadm trigger
   ```

3. **Unplug and replug the printer**

### WSL USB Issues

If using WSL, the printer must be shared via usbipd-win:

```powershell
# On Windows PowerShell (as Administrator)
usbipd list
usbipd bind --busid <BUSID>
usbipd attach --wsl --busid <BUSID>
```

### Port Already in Use

If port 3001 is already in use:
1. Change PORT in `.env` file
2. Update frontend API calls to use new port

### Module Build Errors

If you get errors during `npm install`:

```bash
# Clear npm cache
npm cache clean --force

# Remove node_modules
rm -rf node_modules package-lock.json

# Reinstall
npm install
```

## Security Notes

- The service runs on `127.0.0.1` (localhost only)
- No external access - can only be reached from the same machine
- No authentication required since it's local-only
- CORS enabled for browser requests

## Development

### Project Structure
```
print-service/
├── server.js         # Express server
├── printer.js        # Printer communication & formatting
├── package.json      # Dependencies
├── .env             # Configuration (create from .env.example)
└── README.md        # This file
```

### Adding Custom Features

To customize receipt formatting, edit `printer.js`:
- Modify `printReceipt()` function
- Change receipt layout, fonts, sizes
- Add/remove sections
- Adjust column widths

## Support

For issues:
1. Check printer USB connection
2. Verify printer IDs in `.env`
3. Check service logs: `pm2 logs pos-print-service`
4. Test with `/printer/test` endpoint
5. Verify USB permissions

## License

MIT
