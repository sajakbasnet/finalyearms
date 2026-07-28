<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProposalReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'proposal_comment_id',
        'user_id',
        'reply',
        'attachment_path',
    ];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(ProposalComment::class, 'proposal_comment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
