<?php

declare(strict_types=1);

namespace App\Core;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

/**
 * Tiny migration runner on top of Eloquent's Schema Builder.
 * Each file in database/migrations returns an object with up() and down().
 * Applied files are recorded in the `migrations` table.
 */
final class Migrator
{
    public function __construct(private readonly string $path)
    {
    }

    /** @return string[] names of migrations applied in this run */
    public function migrate(): array
    {
        $this->ensureTable();
        $applied = DB::table('migrations')->pluck('migration')->all();
        $batch = (int) DB::table('migrations')->max('batch') + 1;
        $ran = [];

        foreach ($this->files() as $name => $file) {
            if (in_array($name, $applied, true)) {
                continue;
            }
            $migration = require $file;
            DB::connection()->transaction(function () use ($migration, $name, $batch) {
                $migration->up();
                DB::table('migrations')->insert(['migration' => $name, 'batch' => $batch]);
            });
            $ran[] = $name;
        }

        return $ran;
    }

    /** Drop every table in the public schema, then migrate from scratch. */
    public function fresh(): array
    {
        DB::statement('DROP SCHEMA public CASCADE');
        DB::statement('CREATE SCHEMA public');
        return $this->migrate();
    }

    private function ensureTable(): void
    {
        $schema = DB::schema();
        if (!$schema->hasTable('migrations')) {
            $schema->create('migrations', function (Blueprint $table) {
                $table->id();
                $table->string('migration')->unique();
                $table->integer('batch');
            });
        }
    }

    /** @return array<string, string> migration name => absolute path, in filename order */
    private function files(): array
    {
        $files = glob($this->path . '/*.php') ?: [];
        sort($files);
        $out = [];
        foreach ($files as $file) {
            $out[basename($file, '.php')] = $file;
        }
        return $out;
    }
}
