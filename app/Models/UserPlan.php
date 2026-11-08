<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class UserPlan extends Model
{
  protected $fillable = [
    'user_id','plan_name','amount','status',
    'starts_at','ends_at','confirmed_at','confirm_method','notes'
  ];

  protected $casts = [
    'starts_at' => 'datetime',
    'ends_at' => 'datetime',
    'confirmed_at' => 'datetime',
  ];

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  public function isActive(): bool
  {
    return $this->status === 'active'
      && $this->ends_at
      && $this->ends_at->isFuture();
  }
}