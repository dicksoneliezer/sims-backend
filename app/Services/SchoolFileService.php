<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolFileService
{
    public function store(UploadedFile $file, string $folder): string
    {
        $schoolId = app(SchoolContext::class)->id();
        abort_unless($schoolId || request()->user()?->role === 'system_admin', 403);
        $prefix = $schoolId ? 'schools/'.$schoolId : 'platform/users/'.request()->user()->id;
        $path = $file->store($prefix.'/'.$folder, 'local');
        abort_unless($path, 500, 'The file could not be saved.');

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            $disk = Storage::disk('local')->exists($path) ? 'local' : 'public';
            Storage::disk($disk)->delete($path);
        }
    }

    public function response(?string $path): StreamedResponse
    {
        abort_unless($path, 404);
        $disk = Storage::disk('local')->exists($path) ? 'local' : 'public';
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
