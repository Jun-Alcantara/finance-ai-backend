<?php

namespace App\Jobs;

use App\Events\AiTyping;
use App\Events\MessageSent;
use App\Models\Message;
use Gemini\Enums\Role;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Data\Content;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAIChatResponse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $sessionId;
    public $userMessageContent;
    public $messageId;

    /**
     * Create a new job instance.
     */
    public function __construct($sessionId, $userMessageContent, $messageId)
    {
        $this->sessionId = $sessionId;
        $this->userMessageContent = $userMessageContent;
        $this->messageId = $messageId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Processing AI response for session: {$this->sessionId}");
        
        broadcast(new AiTyping($this->sessionId));

        $messages = Message::where('session_id', $this->sessionId)
            ->where('id', '!=', $this->messageId) // Exclude current message
            ->orderBy('created_at', 'asc')
            ->get();

        $history = $messages->map(function ($message) {
            return Content::parse($message->content, $message->role);
        })->toArray();

        // 3. Call Gemini
        try {
            $chat = Gemini::generativeModel(model: 'gemini-2.0-flash')
                ->startChat(history: $history);

            $response = $chat->sendMessage($this->userMessageContent)->text();

            // 4. Save Response
            $aiMessage = Message::create([
                'session_id' => $this->sessionId,
                'content' => $response,
                'role' => 'assistant', // DB stores 'assistant'
                'metadata' => [],
            ]);

            // 5. Broadcast
            broadcast(new MessageSent($aiMessage))->toOthers();
            
        } catch (\Exception $e) {
            Log::error("Gemini API Error: " . $e->getMessage());
            // Optionally send an error message to the user?
        }
    }
}
