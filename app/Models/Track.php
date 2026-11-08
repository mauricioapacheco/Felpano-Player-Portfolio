<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Track extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'artist',
        'file_name',
        'thumbnail_url',
        'duration_sec'
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function favoritedBy()
    {
        return $this->belongsToMany(User::class, 'favorites');
    }

    public function playlists()
    {
        return $this->belongsToMany(Playlist::class, 'playlist_tracks')
            ->withPivot('position')
            ->withTimestamps();
    }
}