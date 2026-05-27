<?php

namespace Korioinc\ExceptionViewer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Korioinc\ExceptionViewer\Receiver\CentralExceptionReceiver;
use Korioinc\ExceptionViewer\Receiver\ExceptionReceiverAuthenticator;

class ExceptionReceiverController
{
    public function __construct(
        private readonly ExceptionReceiverAuthenticator $authenticator,
        private readonly CentralExceptionReceiver $receiver,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        abort_if(! config('exception-viewer.receiver.enabled', false), 404);

        if (! $this->authenticator->accepts($request->header('Authorization'))) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $payload = $request->validate([
            'version' => ['required', 'integer', 'in:1'],
            'source' => ['required', 'array'],
            'source.key' => ['required', 'string', 'max:255'],
            'exception' => ['required', 'array'],
            'exception.key' => ['required', 'string', 'regex:/\A[A-Fa-f0-9]{64}\z/'],
            'exception.name' => ['required', 'string', 'max:255'],
            'exception.message' => ['required', 'string'],
            'exception.file' => ['required', 'string'],
            'exception.line' => ['required', 'integer', 'min:0'],
            'exception.raw_exception' => ['required', 'string'],
            'exception.count' => ['required', 'integer', 'min:1'],
            'exception.latest_at' => ['required', 'date'],
            'request' => ['nullable', 'array'],
            'request.method' => ['nullable', 'string', 'max:255'],
            'request.endpoint' => ['nullable', 'string'],
            'request.headers' => ['nullable', 'string', 'json'],
            'request.payload' => ['nullable', 'string', 'json'],
            'sent_at' => ['nullable', 'date'],
        ]);

        $exception = $this->receiver->receive($payload);

        return response()->json([
            'accepted' => true,
            'source_key' => $exception->source_key,
            'key' => $exception->key,
            'count' => (int) $exception->count,
        ], 202);
    }
}
