<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SendChatReply extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:reply {user_id : The ID of the user to reply to} {--content=Hello there! : The message content}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a mock AI reply to a specific user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        $content = $this->option('content');
        $sessionId = 'user-' . $userId;

        $this->info("Sending reply to User ID: {$userId}...");

        $message = \App\Models\Message::create([
            'session_id' => $sessionId,
            'content' => $content,
            'role' => 'assistant', // AI response
        ]);

        event(new \App\Events\MessageSent($message));

        $this->info("Message sent successfully! Content: {$content}");
    }
}
