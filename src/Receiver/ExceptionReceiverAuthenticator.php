<?php

namespace Korioinc\ExceptionViewer\Receiver;

class ExceptionReceiverAuthenticator
{
    public function accepts(?string $authorizationHeader): bool
    {
        $token = $this->bearerToken($authorizationHeader);

        if ($token === null) {
            return false;
        }

        foreach ($this->apiKeys() as $apiKey) {
            if (hash_equals($apiKey, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function apiKeys(): array
    {
        $configured = config('exception-viewer.receiver.api_keys', []);

        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }

        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $key): string => trim((string) $key), $configured),
            static fn (string $key): bool => $key !== '',
        ));
    }

    private function bearerToken(?string $authorizationHeader): ?string
    {
        $header = trim((string) $authorizationHeader);

        if (! str_starts_with(strtolower($header), 'bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token === '' ? null : $token;
    }
}
