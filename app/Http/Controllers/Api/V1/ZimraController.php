<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\FiscalizationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZimraController extends BaseController
{

    /**
     * @OA\Get(
     *     path="/zimra/open-day",
     *     summary="Open ZIMRA fiscal day",
     *     description="Open a fiscal day with ZIMRA. Must be called before creating fiscalized transactions.",
     *     operationId="zimraOpenDay",
     *     tags={"ZIMRA Fiscalisation"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\Response(response=200, description="Successfully opened the fiscal day"),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=406, description="ZIMRA Error"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function openDay(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Fiscal day opened (local only)',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/zimra/close-day",
     *     summary="Close ZIMRA fiscal day",
     *     description="Close a fiscal day with ZIMRA. Should be called at the end of the business day.",
     *     operationId="zimraCloseDay",
     *     tags={"ZIMRA Fiscalisation"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\Response(response=200, description="Successfully closed the fiscal day"),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=406, description="ZIMRA Error"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function closeDay(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Fiscal day closed (local only)',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/zimra/fiscalize",
     *     summary="Fiscalize a transaction",
     *     description="Fiscalize a transaction with ZIMRA. Based on ZIMRA Fiscal Device Gateway API Specs v7.2.",
     *     operationId="zimraFiscalize",
     *     tags={"ZIMRA Fiscalisation"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data", "type"},
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", example="", description="The ID of the transaction to fiscalize")
     *             ),
     *             @OA\Property(property="type", type="string", enum={"Invoice", "Debit Note", "Credit Note"}, example="Invoice")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successfully fiscalized the transaction"),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=406, description="ZIMRA Fiscalization Error"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function fiscalize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array',
            'data.id' => 'required|string',
            'type' => 'required|string|in:Invoice,Debit Note,Credit Note',
        ]);

        $fiscalizationRequest = FiscalizationRequest::create([
            'document_id' => $validated['data']['id'],
            'document_type' => $validated['type'],
            'request_data' => $validated['data'],
            'status' => FiscalizationRequest::STATUS_PENDING,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Fiscalization request saved successfully',
            'data' => [
                'request_id' => $fiscalizationRequest->id,
                'document_id' => $fiscalizationRequest->document_id,
                'document_type' => $fiscalizationRequest->document_type,
                'status' => $fiscalizationRequest->status,
            ],
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/zimra/fiscalize-status",
     *     summary="Check fiscalization status",
     *     description="Check the status of a fiscalization request",
     *     operationId="zimraFiscalizeStatus",
     *     tags={"ZIMRA Fiscalisation"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\Parameter(name="request_id", in="query", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Fiscalization request status"),
     *     @OA\Response(response=404, description="Fiscalization request not found"),
     *     @OA\Response(response=422, description="Missing Headers")
     * )
     */
    public function fiscalizeStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'request_id' => 'required|integer',
        ]);

        $fiscalizationRequest = FiscalizationRequest::find($validated['request_id']);

        if (!$fiscalizationRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Fiscalization request not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'request_id' => $fiscalizationRequest->id,
                'document_id' => $fiscalizationRequest->document_id,
                'document_type' => $fiscalizationRequest->document_type,
                'status' => $fiscalizationRequest->status,
                'response_data' => $fiscalizationRequest->response_data,
                'error_message' => $fiscalizationRequest->error_message,
                'attempts' => $fiscalizationRequest->attempts,
                'processed_at' => $fiscalizationRequest->processed_at,
            ],
        ]);
    }
}
