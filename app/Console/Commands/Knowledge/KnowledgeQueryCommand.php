<?php

declare(strict_types=1);

namespace App\Console\Commands\Knowledge;

use App\Models\User;
use App\Services\Knowledge\KnowledgeQueryService;
use Illuminate\Console\Command;

class KnowledgeQueryCommand extends Command
{
    protected $signature = 'knowledge:query {question : The question to ask}';
    protected $description = 'Ask the knowledge assistant a question (CLI test)';

    public function __construct(private readonly KnowledgeQueryService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!config('knowledge.enabled')) {
            $this->warn('Knowledge module is disabled.');
            return self::FAILURE;
        }

        $user = User::where('role', 'admin')->first();
        if (!$user) {
            $this->error('No admin user found.');
            return self::FAILURE;
        }

        $question = $this->argument('question');
        $this->info("Question: {$question}");
        $this->newLine();

        $result = $this->service->ask($user, $question);

        $this->line("Answer:\n" . $result['answer']);
        $this->newLine();

        if (!empty($result['sources'])) {
            $this->info('Sources:');
            foreach ($result['sources'] as $i => $src) {
                $this->line(sprintf(
                    '  [%d] %s — %s (score: %.3f)',
                    $i + 1,
                    $src['source_type'],
                    $src['source_title'] ?? 'n/a',
                    $src['score'] ?? 0,
                ));
            }
        }

        return self::SUCCESS;
    }
}
