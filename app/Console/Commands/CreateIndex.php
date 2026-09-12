<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Opensolr\ScoutOpensolr\OpensolrClient;
use RuntimeException;

/**
 * Creates a vector-enabled Opensolr index for the Scout models and records it in .env.
 *
 * The public demo account's news index is read-only, so Scout needs an index of its own.
 * On your own account you can skip this and point OPENSOLR_INDEX at any vector index.
 */
class CreateIndex extends Command
{
    protected $signature = 'opensolr:create-index
        {name? : Index name; letters, digits and underscores. Generated when omitted.}
        {--location=us : Data centre: us, de or fi}
        {--no-env : Print the name instead of writing OPENSOLR_SCOUT_INDEX to .env}';

    protected $description = 'Create a vector-enabled Opensolr index for the searchable models';

    /**
     * Create the index, wait until it answers, then save its name for Scout.
     */
    public function handle(OpensolrClient $client): int
    {
        $name = $this->indexName();
        if ($name === null) {
            return self::INVALID;
        }

        try {
            $client->createIndex($name, (string) $this->option('location'));
        } catch (RuntimeException $exception) {
            $this->error('Opensolr refused to create the index: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Created {$name}.");

        if (! $this->waitUntilReady($client, $name)) {
            $this->warn('The index was created but is not answering yet. Retry the import in a minute.');
        }

        if ($this->option('no-env')) {
            $this->line("Set OPENSOLR_SCOUT_INDEX={$name} in .env, then run: php artisan scout:import \"App\\Models\\Article\"");

            return self::SUCCESS;
        }

        $this->writeEnv($name);
        $this->line('Next: php artisan scout:import "App\\Models\\Article"');

        return self::SUCCESS;
    }

    /**
     * A vector index name: the given one when valid, otherwise a generated one.
     *
     * The __dense suffix is what gives the index its vector schema on Opensolr.
     */
    protected function indexName(): ?string
    {
        $name = (string) $this->argument('name');
        if ($name === '') {
            return 'laravel_'.Str::lower(Str::random(6)).'__dense';
        }
        if (! preg_match('/^[a-z0-9_]{3,60}$/i', $name)) {
            $this->error('Index names may contain letters, digits and underscores only.');

            return null;
        }

        return Str::endsWith($name, '__dense') ? $name : $name.'__dense';
    }

    /**
     * Poll the new index until Solr answers a query on it.
     */
    protected function waitUntilReady(OpensolrClient $client, string $name): bool
    {
        for ($attempt = 0; $attempt < 12; $attempt++) {
            try {
                $client->solrSelect($name, ['q' => '*:*', 'rows' => 0]);

                return true;
            } catch (RuntimeException) {
                sleep(5);
            }
        }

        return false;
    }

    /**
     * Set OPENSOLR_SCOUT_INDEX in .env, replacing the line when it exists.
     */
    protected function writeEnv(string $name): void
    {
        $path = $this->laravel->environmentFilePath();
        $env = is_file($path) ? (string) file_get_contents($path) : '';
        $line = "OPENSOLR_SCOUT_INDEX={$name}";

        if (preg_match('/^OPENSOLR_SCOUT_INDEX=.*$/m', $env)) {
            $env = preg_replace('/^OPENSOLR_SCOUT_INDEX=.*$/m', $line, $env);
        } else {
            $env = rtrim($env, "\n")."\n{$line}\n";
        }

        file_put_contents($path, $env);
        $this->info("Wrote {$line} to .env.");
    }
}
