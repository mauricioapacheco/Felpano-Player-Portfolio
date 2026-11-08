<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Track;

class AudioLibraryController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $path = public_path('audio' . DIRECTORY_SEPARATOR . $userId);

        if (!File::exists($path)) {
            return response()->json([
                'tracks' => [],
                'error' => 'Pasta public/audio/' . $userId . ' não encontrada'
            ], 404);
        }

        $files = collect(File::files($path))
            ->filter(fn($file) => strtolower($file->getExtension()) === 'mp3')
            ->sortByDesc(fn($file) => $file->getMTime())
            ->values();

        $tracks = [];
        $i = 1;

        foreach ($files as $file) {
            $filename = $file->getFilename();
            $name = pathinfo($filename, PATHINFO_FILENAME);

            $trackDb = Track::where('user_id', $userId)
                ->where('file_name', $filename)
                ->first();

            $tracks[] = [
                'id' => $i++,
                'title' => $name,
                'artist' => $trackDb?->artist ?? 'Local',
                'file' => $filename,
                'duration' => '—',
                'src' => '/audio/' . $userId . '/' . $filename,
                'collected_at' => date('Y-m-d H:i:s', $file->getMTime()),
            ];
        }

        return response()->json([
            'tracks' => $tracks,
            'ordered_by' => 'last_collected'
        ]);
    }

    public function delete(Request $request)
    {
        $userId = $request->user()->id;

        $request->validate([
            'file_name' => ['required', 'string', 'max:255'],
        ]);

        $fileName = $request->input('file_name');

        // segurança: evita "../../"
        if (basename($fileName) !== $fileName) {
            return response()->json(['message' => 'Nome de arquivo inválido.'], 422);
        }

        // 1) apaga do banco (somente desse user)
        $track = DB::table('tracks')
            ->where('user_id', $userId)
            ->where('file_name', $fileName)
            ->first();

        if (!$track) {
            return response()->json(['message' => 'Track não encontrada para este usuário.'], 404);
        }

        DB::table('tracks')
            ->where('user_id', $userId)
            ->where('id', $track->id)
            ->delete();

        // 2) apaga arquivo físico
        $filePath = public_path('audio' . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . $fileName);

        $fileDeleted = false;
        if (File::exists($filePath)) {
            $fileDeleted = File::delete($filePath);
        }

        return response()->json([
            'ok' => true,
            'deleted' => [
                'id' => $track->id,
                'file_name' => $fileName,
                'file_deleted' => $fileDeleted,
            ]
        ]);
    }
}
