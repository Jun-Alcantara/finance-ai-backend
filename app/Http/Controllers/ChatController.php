<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Data\Content;
use Gemini\Enums\Role;

class ChatController extends Controller
{
    public function index(Request $request, $sessionId)
    {
        // Enforce user ownership
        $currentSessionId = 'user-' . $request->user()->id;
        
        // Return messages for current user
        return JsonResource::collection(
            Message::where('session_id', $currentSessionId)
                ->orderBy('created_at', 'asc')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'role' => 'required|in:user,model',
            'metadata' => 'nullable|array',
        ]);

        $sessionId = 'user-' . $request->user()->id;

        $message = Message::create([
            'session_id' => $sessionId,
            'content' => $validated['content'],
            'role' => $validated['role'],
            'metadata' => $validated['metadata'] ?? null,
        ]);

        broadcast(new MessageSent($message))->toOthers();

        if ($validated['role'] === 'user') {
            // Broadcast AI typing indicator before generating response
            broadcast(new \App\Events\AiTyping($sessionId))->toOthers();
            
            $sessionId = 'user-1';
            $model = 'gemini-2.0-flash';

            $messages = Message::whereSessionId($sessionId)
                ->get();

            $history = $messages->map(fn ($message) => Content::parse($message->content, $message->role))
                ->toArray();
            
            $chat = Gemini::generativeModel(model: $model)
                ->startChat(history: $history) ;

            $response = $chat->sendMessage($message->content)->text();

            $aiResopnse = Message::create([
                'session_id' => $sessionId,
                'content' => $response,
                'role' => Role::MODEL,
                'metadata' => [],
            ]);
        }

        event(new \App\Events\MessageSent($aiResopnse));

        return new JsonResource($message);
    }

    public function typing(Request $request) 
    {
        // No input validation needed for session_id anymore
        $sessionId = 'user-' . $request->user()->id;
        
        broadcast(new \App\Events\UserTyping($sessionId))->toOthers();
        
        return response()->json(['status' => 'ok']);
    }
}
