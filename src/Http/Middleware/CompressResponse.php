<?php

declare(strict_types=1);

namespace Statisty\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class CompressResponse
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $response = $next($request);

        if (! $this->shouldCompress($request, $response)) {
            return $response;
        }

        return $this->compress($response);
    }

    private function shouldCompress(Request $request, SymfonyResponse $response): bool
    {
        if (! config('statisty.response_compression.enabled', true)) {
            return false;
        }

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return false;
        }

        if ($response->isInformational() || $response->isRedirection() || $response->isClientError() || $response->isServerError()) {
            return false;
        }

        if ($response->headers->has('Content-Encoding')) {
            return false;
        }

        $content = $response->getContent();
        if ($content === '' || $content === null) {
            return false;
        }

        $acceptEncoding = $request->headers->get('Accept-Encoding', '');
        if (strpos($acceptEncoding, 'gzip') === false) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        return str_starts_with($contentType, 'text/')
            || str_contains($contentType, 'json')
            || str_contains($contentType, 'xml')
            || str_contains($contentType, 'javascript')
            || str_contains($contentType, 'svg');
    }

    private function compress(SymfonyResponse $response): SymfonyResponse
    {
        $content = $response->getContent();

        if ($content === null || $content === '') {
            return $response;
        }

        $compressed = gzencode($content, (int) config('statisty.response_compression.level', 6));
        if ($compressed === false) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Vary', 'Accept-Encoding');
        $response->headers->set('Content-Length', (string) strlen($compressed));

        return $response;
    }
}
