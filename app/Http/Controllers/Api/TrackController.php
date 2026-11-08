<?php
namespace App\Http\Controllers\Api;

use App\Models\Track;
use Illuminate\Http\Request;

class TrackController {
    public function index(Request $request) {
        return Track::where('user_id', $request->user()->id)
            ->latest()->limit(50)->get();
    }

    public function store(Request $request) {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'artist' => ['nullable', 'string', 'max:255'],
            'file_name' => ['required', 'string', 'max:255'],
        ]);

        $file = $data['file_name'];

        if (basename($file) !== $file ||
            strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'mp3') {
            return response()->json(['message' => 'MP3 inválido'], 422);
        }

        $path = public_path(
            'audio' . DIRECTORY_SEPARATOR .
            $request->user()->id . DIRECTORY_SEPARATOR . $file
        );

        if (!is_file($path)) {
            return response()->json(['message' => 'MP3 inexistente'], 404);
        }

        $track = Track::firstOrCreate(
            ['user_id' => $request->user()->id, 'file_name' => $file],
            ['title' => $data['title'], 'artist' => $data['artist'] ?? 'Local']
        );

        return response()->json(
            $track, $track->wasRecentlyCreated ? 201 : 200
        );
    }
}
