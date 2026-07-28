<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProgressComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'progress_report_id',
        'user_id',
        'comment',
        'action',
    ];

    public function progressReport(): BelongsTo
    {
        return $this->belongsTo(ProgressReport::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
