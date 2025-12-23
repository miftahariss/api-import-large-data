<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'status',
        'total',
        'success',
        'failed',
        'error_message',
    ];

    protected $casts = [
        'total' => 'integer',
        'success' => 'integer',
        'failed' => 'integer',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /**
     * Set status to pending
     */
    public function setPending(): void
    {
        $this->update(['status' => self::STATUS_PENDING]);
    }

    /**
     * Set status to in progress
     */
    public function setInProgress(): void
    {
        $this->update(['status' => self::STATUS_IN_PROGRESS]);
    }

    /**
     * Set status to completed
     */
    public function setCompleted(): void
    {
        $this->update(['status' => self::STATUS_COMPLETED]);
    }

    /**
     * Set status to failed
     */
    public function setFailed(string $message = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $message,
        ]);
    }

    /**
     * Increment success count
     */
    public function incrementSuccess(): void
    {
        $this->increment('success');
    }

    /**
     * Increment failed count
     */
    public function incrementFailed(): void
    {
        $this->increment('failed');
    }

    /**
     * Check if job is completed
     */
    public function isCompleted(): bool
    {
        return ($this->success + $this->failed) >= $this->total;
    }
}