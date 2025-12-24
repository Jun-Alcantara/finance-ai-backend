<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Data\Content;
use Gemini\Enums\Role;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function index(Request $request)
    {
        $sessionId = 'user-1';
        $model = 'gemini-2.0-flash';

        $messages = Message::whereSessionId($sessionId)
            ->get();

        $history = $messages->map(fn ($message) => Content::parse($message->content, $message->role))
            ->toArray();

        $chat = Gemini::generativeModel(model: $model)
            ->startChat(history: $history) ;

        $response = $chat->sendMessage($request->message)->text();

        Message::create([
            'session_id' => $sessionId,
            'content' => $request->message,
            'role' => Role::USER,
            'metadata' => [],
        ]);

        Message::create([
            'session_id' => $sessionId,
            'content' => $response,
            'role' => Role::MODEL,
            'metadata' => [],
        ]);

        $updatedMessages = Message::whereSessionId($sessionId)
            ->get();

        foreach ($updatedMessages as $m) {
            echo "{$m->role->value}: {$m->content} <br/>";
        }
    }
}
