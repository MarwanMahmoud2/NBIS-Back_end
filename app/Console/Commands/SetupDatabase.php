<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:setup-database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations and seeders after deployment';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing database connection...');
        
        try {
            DB::connection()->getPdo();
            $this->info('✓ Database connection successful!');
        } catch (\Exception $e) {
            $this->error('✗ Database connection failed: ' . $e->getMessage());
            return 1;
        }

        $this->info('Running migrations...');
        $this->call('migrate', [
            '--force' => true,
            '--isolated' => true,
        ]);

        $this->info('Running seeders...');
        $this->call('db:seed', [
            '--force' => true,
        ]);

        $this->info('✓ Database setup complete!');
        return 0;
    }
}
