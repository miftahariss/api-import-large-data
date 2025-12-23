<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ImportProductJob;
use App\Models\ImportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ImportController extends Controller
{
    /**
     * Upload and queue CSV file for import
     */
    public function uploadProducts(Request $request): JsonResponse
    {
        try {
            // Validate file upload
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:csv,txt|max:10240', // max 10MB
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $filename = $file->getClientOriginalName();

            // Read and parse CSV
            $handle = fopen($file->getRealPath(), 'r');
            
            if ($handle === false) {
                return response()->json([
                    'message' => 'Failed to read file'
                ], 500);
            }

            // Read header
            $header = fgetcsv($handle);
            // dd($header);
            
            if (!$header || !$this->validateCsvHeader($header)) {
                fclose($handle);
                return response()->json([
                    'message' => 'Invalid CSV format. Expected headers: name, sku, price, stock'
                ], 422);
            }

            // Count total rows
            $totalRows = 0;
            $products = [];
            
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) === count($header)) {
                    $products[] = array_combine($header, $row);
                    $totalRows++;
                }
            }
            
            fclose($handle);

            if ($totalRows === 0) {
                return response()->json([
                    'message' => 'CSV file is empty'
                ], 422);
            }

            // Create import job record
            $importJob = ImportJob::create([
                'filename' => $filename,
                'status' => ImportJob::STATUS_PENDING,
                'total' => $totalRows,
                'success' => 0,
                'failed' => 0,
            ]);

            // Dispatch jobs to queue
            foreach ($products as $index => $productData) {
                ImportProductJob::dispatch(
                    $importJob->id,
                    $productData,
                    $index + 1
                )->onQueue('product-import');
            }

            Log::info("CSV file uploaded and queued", [
                'job_id' => $importJob->id,
                'filename' => $filename,
                'total_rows' => $totalRows
            ]);

            return response()->json([
                'job_id' => $importJob->id,
                'status' => $importJob->status,
                'message' => 'File uploaded successfully and queued for processing',
                'total' => $totalRows
            ], 201);

        } catch (\Exception $e) {
            Log::error("Failed to upload CSV", [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to process file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get import job status
     */
    public function getStatus(int $jobId): JsonResponse
    {
        $importJob = ImportJob::find($jobId);

        if (!$importJob) {
            return response()->json([
                'message' => 'Job not found'
            ], 404);
        }

        return response()->json([
            'job_id' => $importJob->id,
            'filename' => $importJob->filename,
            'status' => $importJob->status,
            'total' => $importJob->total,
            'success' => $importJob->success,
            'failed' => $importJob->failed,
            'created_at' => $importJob->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $importJob->updated_at->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get all import jobs
     */
    public function listJobs(): JsonResponse
    {
        $jobs = ImportJob::orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $jobs->items(),
            'pagination' => [
                'current_page' => $jobs->currentPage(),
                'per_page' => $jobs->perPage(),
                'total' => $jobs->total(),
                'last_page' => $jobs->lastPage(),
            ]
        ]);
    }

    /**
     * Validate CSV header
     */
    private function validateCsvHeader(array $header): bool
    {
        $expectedHeaders = ['name', 'sku', 'price', 'stock'];
        $header = array_map('trim', $header);
        
        return empty(array_diff($expectedHeaders, $header));
    }
}