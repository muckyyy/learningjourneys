<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI;

class TestOpenAICommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'openai:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test OpenAI API connection and configuration';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Testing OpenAI API connection...');

        // Check if API key is configured
        $apiKey = config('openai.api_key');
        if (empty($apiKey) || $apiKey === 'your_openai_api_key_here') {
            $this->error('OpenAI API key is not configured. Please set OPENAI_API_KEY in your .env file.');
            return Command::FAILURE;
        }

        $this->info('API Key: ' . substr($apiKey, 0, 7) . '...' . substr($apiKey, -4));

        try {
            // Create HTTP client with SSL handling for Windows/XAMPP
            $httpClient = \Http\Discovery\Psr18ClientDiscovery::find();
            
            if (config('openai.http_options.verify') === false) {
                $httpClient = new \GuzzleHttp\Client([
                    'verify' => false,
                    'timeout' => config('openai.timeout', 30),
                ]);
            }

            $client = OpenAI::factory()
                ->withApiKey($apiKey)
                ->withHttpClient($httpClient)
                ->make();
            
            $this->info('Sending test request to OpenAI...');
            
            $response = $client->chat()->create([
                'model' => config('openai.default_model', 'gpt-3.5-turbo'),
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Say "OpenAI connection successful!" if you can read this.',
                    ],
                ],
                'max_tokens' => 50,
            ]);

            $this->info('✅ OpenAI API Response:');
            $this->line($response->choices[0]->message->content);
            $this->line('');
            $this->info('Usage:');
            $this->line('- Model: ' . $response->model);
            $this->line('- Prompt tokens: ' . $response->usage->promptTokens);
            $this->line('- Completion tokens: ' . $response->usage->completionTokens);
            $this->line('- Total tokens: ' . $response->usage->totalTokens);
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ OpenAI API Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
