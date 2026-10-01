<?php

namespace App\Services\ACLi\Providers;

use App\Services\ACLi\DTO\AIResponse;

/**
 * Supplies streamChat() for providers that have no streaming implementation.
 *
 * Adding streamChat to the AIProvider contract would otherwise force every
 * provider to be rewritten at once. This keeps a provider working: it performs
 * the normal buffered call and emits the whole answer as a single chunk, which
 * is exactly the behaviour that existed before streaming was introduced.
 */
trait StreamsViaBufferedCall
{
    public function streamChat(\App\Services\ACLi\DTO\AIRequest $request, callable $onChunk): AIResponse
    {
        $response = $this->chat($request);
        $onChunk($response->content);

        return $response;
    }
}
