<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Signature('school:migrate-files {--remove-public : Archive public copies after a verified private copy exists}')]
#[Description('Move existing profile photos, student photos and receipts into private school storage')]
class MigrateSchoolFiles extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $private = Storage::disk('local');
        $public = Storage::disk('public');
        $moved = 0;
        $missing = 0;
        foreach (['users' => 'profile_pic', 'students' => 'photo', 'expenses' => 'receipt'] as $table => $column) {
            DB::table($table)->whereNotNull($column)->orderBy('id')->chunkById(100, function ($rows) use ($private, $public, $table, $column, &$moved, &$missing): void {
                foreach ($rows as $row) {
                    $old = $row->{$column};
                    if ($private->exists($old)) {
                        continue;
                    }
                    if (! $public->exists($old)) {
                        $missing++;

                        continue;
                    }
                    $prefix = $row->school_id ? 'schools/'.$row->school_id : 'platform/users/'.$row->id;
                    $path = $prefix.'/legacy/'.$table.'/'.$row->id.'/'.basename($old);
                    $contents = $public->get($old);
                    if (! $private->put($path, $contents) || hash('sha256', $private->get($path)) !== hash('sha256', $contents)) {
                        throw new \RuntimeException('The private file copy could not be verified. No public copies have been removed.');
                    }
                    DB::table($table)->where('id', $row->id)->where($column, $old)->update([$column => $path]);
                    $moved++;
                }
            });
        }
        if ($this->option('remove-public')) {
            foreach (['avatars', 'students/photos', 'finance/receipts'] as $directory) {
                foreach ($public->allFiles($directory) as $path) {
                    $archive = 'school-file-archive/'.$path;
                    $contents = $public->get($path);
                    if (! $private->put($archive, $contents) || hash('sha256', $private->get($archive)) !== hash('sha256', $contents)) {
                        throw new \RuntimeException('Public file archiving could not be verified.');
                    }
                    $public->delete($path);
                }
            }
        }
        $this->info("Private files migrated: {$moved}; previously missing files: {$missing}.");

        return self::SUCCESS;
    }
}
