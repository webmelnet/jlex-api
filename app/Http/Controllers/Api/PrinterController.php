<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Printer Controller for WSL + Windows Setup
 * 
 * This controller sends print jobs to a Windows print server
 * running on the Windows host (not directly to the printer)
 */
class PrinterController extends Controller
{
    /**
     * Windows print server configuration
     */
    private $windowsHost = 'localhost'; // Windows is accessible via localhost from WSL
    private $windowsPort = 9100;        // Port where Windows print server is running
    
    /**
     * Print receipt to thermal printer via Windows bridge
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function printReceipt(Request $request)
    {
        try {
            $receiptContent = $request->input('receiptContent');
            $receiptData = $request->input('receiptData');
            
            // Log receipt data for debugging
            Log::info('Print receipt request received', [
                'receipt_number' => $receiptData['receiptNumber'] ?? 'N/A',
                'total' => $receiptData['total'] ?? 0,
                'items_count' => count($receiptData['items'] ?? [])
            ]);
            
            // Send to Windows print server
            $printResult = $this->sendToWindowsPrintServer($receiptContent);
            
            if ($printResult['success']) {
                Log::info('Receipt printed successfully via Windows bridge');
                
                return response()->json([
                    'success' => true,
                    'message' => 'Receipt printed successfully'
                ], 200);
            } else {
                Log::error('Failed to print receipt', [
                    'error' => $printResult['error'] ?? 'Unknown error'
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => $printResult['error'] ?? 'Failed to print receipt'
                ], 500);
            }
            
        } catch (\Exception $e) {
            Log::error('Print error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Print error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Send receipt to Windows print server via HTTP
     * 
     * @param string $content Receipt content with ESC/POS commands
     * @return array ['success' => bool, 'error' => string|null]
     */
    private function sendToWindowsPrintServer($content)
    {
        try {
            $url = "http://{$this->windowsHost}:{$this->windowsPort}/print";
            
            // Prepare the request data
            $data = json_encode([
                'receiptContent' => $content
            ]);
            
            // Initialize cURL
            $ch = curl_init($url);
            
            if (!$ch) {
                return [
                    'success' => false,
                    'error' => 'Failed to initialize cURL'
                ];
            }
            
            // Set cURL options
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data)
                ],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5
            ]);
            
            // Execute request
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // Check for cURL errors
            if ($curlError) {
                Log::error('cURL error connecting to Windows print server', [
                    'error' => $curlError,
                    'url' => $url
                ]);
                
                return [
                    'success' => false,
                    'error' => "Connection error: {$curlError}. Make sure Windows print server is running."
                ];
            }
            
            // Check HTTP response code
            if ($httpCode === 200) {
                Log::info('Receipt sent to Windows print server successfully', [
                    'response' => $response
                ]);
                
                return [
                    'success' => true,
                    'error' => null
                ];
            } else {
                $errorMsg = "Windows print server returned error (HTTP {$httpCode})";
                
                if ($response) {
                    $responseData = json_decode($response, true);
                    if ($responseData && isset($responseData['message'])) {
                        $errorMsg .= ": " . $responseData['message'];
                    }
                }
                
                Log::error('Windows print server error', [
                    'http_code' => $httpCode,
                    'response' => $response
                ]);
                
                return [
                    'success' => false,
                    'error' => $errorMsg
                ];
            }
            
        } catch (\Exception $e) {
            Log::error('Exception in sendToWindowsPrintServer: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Test printer connection
     * Tests connection to Windows print server
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function testPrinter()
    {
        try {
            $url = "http://{$this->windowsHost}:{$this->windowsPort}/test";
            
            Log::info('Testing connection to Windows print server', ['url' => $url]);
            
            // Initialize cURL
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3
            ]);
            
            // Execute request
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // Check for errors
            if ($curlError) {
                Log::error('Test connection failed', ['error' => $curlError]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot connect to Windows print server',
                    'details' => $curlError,
                    'help' => [
                        'Make sure the Windows print server is running',
                        'Run: node C:\printer-bridge\print-server.js',
                        'Check if port ' . $this->windowsPort . ' is accessible'
                    ]
                ], 500);
            }
            
            if ($httpCode === 200) {
                $responseData = json_decode($response, true);
                
                Log::info('Test connection successful', ['response' => $responseData]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Print server connection successful',
                    'server' => [
                        'host' => $this->windowsHost,
                        'port' => $this->windowsPort,
                        'status' => 'online'
                    ],
                    'details' => $responseData
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Print server returned error',
                    'http_code' => $httpCode,
                    'response' => $response
                ], 500);
            }
            
        } catch (\Exception $e) {
            Log::error('Test printer error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Test error: ' . $e->getMessage()
            ], 500);
        }
    }
}