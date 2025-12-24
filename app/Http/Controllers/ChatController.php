<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            // 'session_id' is no longer required from input, we force it
            'content' => 'required|string',
            'role' => 'required|in:user,assistant',
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

        broadcast(new \App\Events\UserTyping($sessionId))->toOthers();

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
