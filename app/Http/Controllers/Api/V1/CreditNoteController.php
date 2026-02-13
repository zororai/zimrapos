<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\DatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditNoteController extends BaseController
{
    public function __construct(
        protected DatabaseService $dbService
    ) {}

    /**
     * @OA\Post(
     *     path="/credit-note/create",
     *     summary="Create credit notes",
     *     description="Create credit notes in your Panier company with optional ZIMRA fiscalization",
     *     operationId="createCreditNotes",
     *     tags={"Credit Notes"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(property="data", type="array", minItems=1, maxItems=50,
     *                 @OA\Items(type="object", required={"sale_id", "products", "reason"},
     *                     @OA\Property(property="sale_id", type="string"),
     *                     @OA\Property(property="products", type="array",
     *                         @OA\Items(type="object",
     *                             @OA\Property(property="id", type="string"),
     *                             @OA\Property(property="quantity", type="integer", minimum=1)
     *                         )
     *                     ),
     *                     @OA\Property(property="reason", type="string")
     *                 )
     *             ),
     *             @OA\Property(property="zimra_fiscalize", type="boolean", default=false)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Successfully created the credit notes"),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=406, description="Credit Note Error"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array|min:1|max:50',
            'data.*.sale_id' => 'required|string',
            'data.*.products' => 'required|array|min:1',
            'data.*.reason' => 'required|string',
            'zimra_fiscalize' => 'boolean',
        ]);

        $result = $this->dbService->createCreditNotes(
            $validated['data'],
            $validated['zimra_fiscalize'] ?? false
        );
        return $this->successResponse($result, 201);
    }

    /**
     * @OA\Post(
     *     path="/credit-note/search",
     *     summary="Search credit notes",
     *     description="Search credit notes in your Panier company",
     *     operationId="searchCreditNotes",
     *     tags={"Credit Notes"},
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
     *     @OA\Response(response=200, description="Successfully searched the credit notes"),
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
        $result = $this->dbService->searchCreditNotes(
            $data['query'] ?? '*',
            $data['limit'] ?? 10,
            $data['skip'] ?? 0
        );
        return $this->successResponse($result);
    }

    /**
     * @OA\Get(
     *     path="/credit-note/download",
     *     summary="Download credit note",
     *     description="Download a credit note as PDF",
     *     operationId="downloadCreditNote",
     *     tags={"Credit Notes"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\Parameter(name="id", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Successfully downloaded the credit note"),
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
     *     path="/credit-note/delete",
     *     summary="Delete credit notes",
     *     description="Delete credit notes in your Panier company",
     *     operationId="deleteCreditNotes",
     *     tags={"Credit Notes"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(property="data", type="array", minItems=1, maxItems=1000,
     *                 @OA\Items(type="object", required={"id"},
     *                     @OA\Property(property="id", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successfully deleted the credit notes"),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function delete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array|min:1|max:1000',
            'data.*.id' => 'required|string',
        ]);

        $result = $this->dbService->deleteCreditNotes($validated['data']);
        return $this->successResponse($result);
    }
}
