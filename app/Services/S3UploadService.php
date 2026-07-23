<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Aws\S3\Exception\S3Exception;

class S3UploadService
{
    /**
     * @var ImageOptimizationService
     */
    protected $imageOptimizationService;

    /**
     * @param ImageOptimizationService $imageOptimizationService
     */
    public function __construct(ImageOptimizationService $imageOptimizationService)
    {
        $this->imageOptimizationService = $imageOptimizationService;
    }

    /**
     * Upload file with optimization if it's an image
     *
     * @param UploadedFile $file
     * @param string $path
     * @param string|null $customFilename Optional custom filename (without extension)
     * @return array
     * @throws \Exception
     */
    public function uploadFile(UploadedFile $file, string $path, ?string $customFilename = null): array
    {
        try {
            $result = [
                'url' => '',
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getMimeType(),
                'original_size' => $file->getSize(),
                'final_size' => $file->getSize(),
                'was_optimized' => false,
                'dimensions' => null,
                'quality_used' => null,
                'optimization_time' => null
            ];

            // Only optimize if it's an image file
            if (str_starts_with($file->getMimeType(), 'image/')) {
                $optimizationResult = $this->imageOptimizationService->optimizeIfNeeded($file);
                $file = $optimizationResult['file'];

                $result['was_optimized'] = $optimizationResult['was_optimized'];
                $result['final_size'] = $optimizationResult['optimized_size'];
                $result['dimensions'] = $optimizationResult['dimensions'];
                $result['quality_used'] = $optimizationResult['quality_used'];
                $result['optimization_time'] = $optimizationResult['optimization_time'];
            }

            // Generate filename - use custom if provided, otherwise generate unique one
            if ($customFilename) {
                // If custom filename already includes extension, use as-is
                if (pathinfo($customFilename, PATHINFO_EXTENSION)) {
                    $filename = $customFilename;
                } else {
                    // Add the original file extension to custom filename
                    $filename = $customFilename . '.' . $file->getClientOriginalExtension();
                }
            } else {
                // Fallback to original unique filename generation
                $filename = uniqid() . '.' . $file->getClientOriginalExtension();
            }

            $fullPath = $path . '/' . $filename;

            // Use stream to handle large files more efficiently
            $stream = fopen($file->getRealPath(), 'r');
            Storage::disk('s3')->put($fullPath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $result['url'] = Storage::disk('s3')->url($fullPath);
            $result['s3_path'] = $fullPath;

            // Log upload details
            Log::info('File uploaded successfully', [
                'file_name' => $result['file_name'],
                'custom_filename' => $customFilename ?: 'None',
                'final_filename' => $filename,
                'path' => $fullPath,
                'original_size' => $this->formatBytes($result['original_size']),
                'final_size' => $this->formatBytes($result['final_size']),
                'optimization' => $result['was_optimized'] ? 'Yes' : 'No',
                'reduction' => $result['was_optimized']
                    ? round((($result['original_size'] - $result['final_size']) / $result['original_size']) * 100, 2) . '%'
                    : 'N/A',
                'time_taken' => $result['optimization_time'] ? $result['optimization_time'] . 'ms' : 'N/A'
            ]);

            return $result;

        } catch (S3Exception $e) {
            Log::error('S3 upload error', [
                'file_name' => $file->getClientOriginalName(),
                'custom_filename' => $customFilename ?? 'None',
                'message' => $e->getMessage(),
                'aws_error_code' => $e->getAwsErrorCode(),
                'path' => $fullPath ?? 'unknown',
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Upload error', [
                'file_name' => $file->getClientOriginalName(),
                'custom_filename' => $customFilename ?? 'None',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete file from S3
     *
     * @param string $url
     * @return bool
     */
    public function deleteFile(string $url): bool
    {
        try {
            $path = parse_url($url, PHP_URL_PATH);
            $path = ltrim($path, '/');

            Log::info("Attempting to delete S3 file", [
                'path' => $path
            ]);

            // Get bucket name from config
            $bucket = config('filesystems.disks.s3.bucket');

            // Remove bucket name from path if it exists
            $path = preg_replace('/^' . preg_quote($bucket, '/') . '\//', '', $path);

            $deleted = Storage::disk('s3')->delete($path);

            if ($deleted) {
                Log::info("S3 file deleted successfully", [
                    'path' => $path
                ]);
            } else {
                Log::warning("S3 file deletion returned false", [
                    'path' => $path
                ]);
            }

            return $deleted;

        } catch (S3Exception $e) {
            Log::error("S3 deletion error", [
                'message' => $e->getMessage(),
                'aws_error_code' => $e->getAwsErrorCode(),
                'path' => $path ?? 'unknown'
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error("Unexpected error during S3 file deletion", [
                'message' => $e->getMessage(),
                'path' => $path ?? 'unknown'
            ]);
            return false;
        }
    }

    /**
     * Get signed URL for private files
     *
     * @param string $path
     * @param int $expiration Time in seconds
     * @return string
     */
    public function getSignedUrl(string $path, int $expiration = 300): string
    {
        try {
            return Storage::disk('s3')->temporaryUrl(
                $path,
                now()->addSeconds($expiration)
            );
        } catch (\Exception $e) {
            Log::error("Failed to generate signed URL", [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Check if file exists in S3
     *
     * @param string $path
     * @return bool
     */
    public function fileExists(string $path): bool
    {
        try {
            return Storage::disk('s3')->exists($path);
        } catch (\Exception $e) {
            Log::error("Failed to check file existence", [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get file metadata from S3
     *
     * @param string $path
     * @return array|null
     */
    public function getMetadata(string $path): ?array
    {
        try {
            if (!$this->fileExists($path)) {
                return null;
            }

            return [
                'size' => Storage::disk('s3')->size($path),
                'last_modified' => Storage::disk('s3')->lastModified($path),
                'mime_type' => Storage::disk('s3')->mimeType($path),
                'visibility' => Storage::disk('s3')->getVisibility($path),
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get file metadata", [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Set file visibility in S3
     *
     * @param string $path
     * @param string $visibility 'public' or 'private'
     * @return bool
     */
    public function setVisibility(string $path, string $visibility): bool
    {
        try {
            Storage::disk('s3')->setVisibility($path, $visibility);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to set file visibility", [
                'path' => $path,
                'visibility' => $visibility,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Copy file within S3
     *
     * @param string $fromPath
     * @param string $toPath
     * @return bool
     */
    public function copyFile(string $fromPath, string $toPath): bool
    {
        try {
            Storage::disk('s3')->copy($fromPath, $toPath);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to copy file", [
                'from' => $fromPath,
                'to' => $toPath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Move file within S3
     *
     * @param string $fromPath
     * @param string $toPath
     * @return bool
     */
    public function moveFile(string $fromPath, string $toPath): bool
    {
        try {
            Storage::disk('s3')->move($fromPath, $toPath);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to move file", [
                'from' => $fromPath,
                'to' => $toPath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Format bytes to human readable format
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
    }
}