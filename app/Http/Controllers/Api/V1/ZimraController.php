<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\PanierApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZimraController extends BaseController
{
    public function __construct(
        protected PanierApiService $panierApi
    ) {}

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
        $response = $this->panierApi->zimraOpenDay();
        return $this->handleApiResponse($response);
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
        $response = $this->panierApi->zimraCloseDay();
        return $this->handleApiResponse($response);
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
     *             required={"data"},
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="transaction_id", type="string", description="The ID of the transaction to fiscalize"),
     *                 @OA\Property(property="transaction_type", type="string", enum={"sale", "invoice", "credit_note", "debit_note"})
     *             )
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
        ]);

        $response = $this->panierApi->zimraFiscalize($validated['data']);
        return $this->handleApiResponse($response);
    }
}
