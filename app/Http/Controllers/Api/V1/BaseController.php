<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class BaseController extends Controller
{
    protected function successResponse($data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status);
    }

    protected function errorResponse(string $message, int $status = 400, $errors = null): JsonResponse
    {
        $response = ['message' => $message];
        if ($errors) {
            $response['errors'] = $errors;
        }
        return response()->json($response, $status);
    }

    protected function handleApiResponse($response): JsonResponse
    {
        return response()->json($response->json(), $response->status());
    }

    protected function handlePdfDownload($response, string $filename = 'document.pdf'): StreamedResponse|JsonResponse
    {
        if ($response->failed()) {
            return response()->json($response->json(), $response->status());
        }

        $contentType = $response->header('Content-Type');
        
        if (str_contains($contentType ?? '', 'application/pdf')) {
            return response()->streamDownload(function () use ($response) {
                echo $response->body();
            }, $filename, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        return response()->json($response->json(), $response->status());
    }
}
