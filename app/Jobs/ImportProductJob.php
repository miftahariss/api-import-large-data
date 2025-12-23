<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ImportProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;

    protected $importJobId;
    protected $productData;
    protected $rowNumber;

    /**
     * Create a new job instance.
     */
    public function __construct(int $importJobId, array $productData, int $rowNumber)
    {
        $this->importJobId = $importJobId;
        $this->productData = $productData;
        $this->rowNumber = $rowNumber;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $importJob = ImportJob::find($this->importJobId);

        if (!$importJob) {
            Log::error("Import job not found", ['job_id' => $this->importJobId]);
            return;
        }

        try {
            // Update status to in_progress if still pending
            if ($importJob->status === ImportJob::STATUS_PENDING) {
                $importJob->setInProgress();
            }

            // Validate product data
            $validator = Validator::make($this->productData, [
                'name' => 'required|string|max:255',
                'sku' => 'required|string|max:100',
                'price' => 'required|numeric|min:0',
                'stock' => 'required|integer|min:0',
            ]);

            if ($validator->fails()) {
                throw new \Exception("Validation failed: " . $validator->errors()->first());
            }

            // Save product to database
            Product::updateOrCreateBySku([
                'name' => $this->productData['name'],
                'sku' => $this->productData['sku'],
                'price' => $this->productData['price'],
                'stock' => $this->productData['stock'],
            ]);

            // Increment success count
            $importJob->incrementSuccess();

            Log::info("Product imported successfully", [
                'job_id' => $this->importJobId,
                'row' => $this->rowNumber,
                'sku' => $this->productData['sku']
            ]);

        } catch (\Exception $e) {
            // Increment failed count
            $importJob->incrementFailed();

            Log::error("Failed to import product", [
                'job_id' => $this->importJobId,
                'row' => $this->rowNumber,
                'error' => $e->getMessage(),
                'data' => $this->productData
            ]);
        }

        // Check if all jobs completed
        $importJob->refresh();
        if ($importJob->isCompleted()) {
            $importJob->setCompleted();
            
            Log::info("Import job completed", [
                'job_id' => $this->importJobId,
                'total' => $importJob->total,
                'success' => $importJob->success,
                'failed' => $importJob->failed
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $importJob = ImportJob::find($this->importJobId);
        
        if ($importJob) {
            $importJob->incrementFailed();
            
            Log::error("Job failed permanently", [
                'job_id' => $this->importJobId,
                'row' => $this->rowNumber,
                'error' => $exception->getMessage()
            ]);

            // Check if all jobs completed
            $importJob->refresh();
            if ($importJob->isCompleted()) {
                $importJob->setCompleted();
            }
        }
    }
}