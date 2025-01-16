<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashBoardController extends Controller
{
    public function updates(Request $request)
    {
        // Configura la respuesta como un stream de eventos
        return response()->stream(function () {
            // Escucha el canal 'invoices'
            \Illuminate\Support\Facades\Redis::subscribe(['channel-name'], function ($message) {
                $data = json_decode($message, true);

                // Envía los datos al cliente en formato SSE
                echo "data: " . json_encode($data) . "\n\n";

                // Limpia el buffer de salida y envía los datos al cliente
                ob_flush();
                flush();
            });
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }
}
