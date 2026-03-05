<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\DatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DebitNoteController extends BaseController
{
    public function __construct(
        protected DatabaseService $dbService
    ) {}

    /**
     * @OA\Post(
     *     path="/debit-notes",
     *     summary="Create a debit note",
     *     description="Create a debit note as a fiscal document. Must reference an original fiscalized receipt. Debit notes are automatically fiscalized with ZIMRA. Only USD and ZWG supported. All products must have ZIMRA Tax and valid HS Code.",
     *     operationId="createDebitNote",
     *     tags={"Debit Notes"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"invoice_id", "products", "reason"},
     *             @OA\Property(property="invoice_id", type="string", description="Original receipt invoice_no or ID"),
     *             @OA\Property(property="external_reference", type="string", description="Optional external reference for idempotency"),
     *             @OA\Property(property="invoice_no", type="string", description="Optional custom invoice number (default: DN-{timestamp})"),
     *             @OA\Property(property="products", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id", type="string", description="Product panier_id"),
     *                     @OA\Property(property="quantity", type="integer", minimum=1)
     *                 )
     *             ),
     *             @OA\Property(property="reason", type="string", description="Reason for debit note (mandatory)"),
     *             @OA\Property(property="customer_id", type="string", description="Optional customer panier_id")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Successfully created the debit note"),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=406, description="Debit Note Error"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => 'required|string',
            'external_reference' => 'nullable|string|max:255',
            'invoice_no' => 'nullable|string|max:255',
            'products' => 'required|array|min:1',
            // Support both legacy format (id) and new format (name, price, taxID)
            'products.*.id' => 'nullable|string',
            'products.*.name' => 'nullable|string',
            'products.*.price' => 'nullable|numeric',
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.taxID' => 'nullable|integer',
            'products.*.taxPercent' => 'nullable|numeric',
            'products.*.taxCode' => 'nullable|string',
            'products.*.receiptLineHSCode' => 'nullable|string',
            'reason' => 'required|string|min:10',
            'customer_id' => 'nullable|string',
        ]);

        $result = $this->dbService->createDebitNote($validated);
        return $this->successResponse($result, 201);
    }

    /**
     * @OA\Post(
     *     path="/debit-note/search",
     *     summary="Search debit notes",
     *     description="Search debit notes in your Panier company. Results returned in descending order of created_at.",
     *     operationId="searchDebitNotes",
     *     tags={"Debit Notes"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="query", type="string", default="*"),
     *                 @OA\Property(property="limit", type="integer", default=10),
     *                 @OA\Property(property="skip", type="integer", default=0)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successfully searched the debit notes"),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array',
            'data.query' => 'string',
            'data.limit' => 'integer|min:1',
            'data.skip' => 'integer|min:0',
        ]);

        $data = $validated['data'];
        $result = $this->dbService->searchDebitNotes(
            $data['query'] ?? '*',
            $data['limit'] ?? 10,
            $data['skip'] ?? 0
        );
        return $this->successResponse($result);
    }

    /**
     * @OA\Get(
     *     path="/debit-note/download",
     *     summary="Download debit note",
     *     description="Download a debit note as PDF",
     *     operationId="downloadDebitNote",
     *     tags={"Debit Notes"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\Parameter(name="id", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Successfully downloaded the debit note"),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=406, description="Download Error"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function download(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|string',
        ]);

        // PDF download not available for local database
        return $this->errorResponse('PDF download not available', 501);
    }

    /**
     * @OA\Post(
     *     path="/debit-notes/void",
     *     summary="Void a debit note",
     *     description="Void a debit note by creating a reversing credit note. Fiscal documents cannot be deleted.",
     *     operationId="voidDebitNote",
     *     tags={"Debit Notes"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"receipt_id", "reason"},
     *             @OA\Property(property="receipt_id", type="integer", description="Receipt ID to void"),
     *             @OA\Property(property="reason", type="string", description="Reason for voiding")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successfully voided the debit note"),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function void(Request $request): JsonResponse
    {
        return $this->errorResponse('Void functionality not yet implemented. Use credit notes to reverse debit notes.', 501);
    }
}
