<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\DatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyController extends BaseController
{
    public function __construct(
        protected DatabaseService $dbService
    ) {}

    /**
     * @OA\Post(
     *     path="/currency/create",
     *     summary="Create currencies",
     *     description="Create currencies in your Panier company. Supports 119 currencies.",
     *     operationId="createCurrencies",
     *     tags={"Currencies"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(property="data", type="array", minItems=1, maxItems=1000,
     *                 @OA\Items(type="object", required={"code", "exchange_rate"},
     *                     @OA\Property(property="code", type="string", example="USD"),
     *                     @OA\Property(property="exchange_rate", type="number", format="float", example=1.0)
     *                 )
     *             ),
     *             @OA\Property(property="overwrite_duplicates", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Successfully created the currencies"),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array|min:1|max:1000',
            'data.*.code' => 'required|string',
            'data.*.exchange_rate' => 'required|numeric',
            'overwrite_duplicates' => 'nullable|boolean',
        ]);

        $result = $this->dbService->createCurrencies(
            $validated['data'],
            $validated['overwrite_duplicates'] ?? false
        );
        return $this->successResponse($result, 201);
    }

    /**
     * @OA\Put(
     *     path="/currency/update",
     *     summary="Update currencies",
     *     description="Update currencies in your Panier company",
     *     operationId="updateCurrencies",
     *     tags={"Currencies"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(property="data", type="array", minItems=1, maxItems=1000,
     *                 @OA\Items(type="object", required={"id"},
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="exchange_rate", type="number", format="float")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successfully updated the currencies"),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array|min:1|max:1000',
            'data.*.id' => 'required|string',
        ]);

        $result = $this->dbService->updateCurrencies($validated['data']);
        return $this->successResponse($result);
    }

    /**
     * @OA\Post(
     *     path="/currency/search",
     *     summary="Search currencies",
     *     description="Search currencies in your Panier company",
     *     operationId="searchCurrencies",
     *     tags={"Currencies"},
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
     *     @OA\Response(response=200, description="Successfully searched the currencies"),
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
        $result = $this->dbService->searchCurrencies(
            $data['query'] ?? '*',
            $data['limit'] ?? 10,
            $data['skip'] ?? 0
        );
        return $this->successResponse($result);
    }

    /**
     * @OA\Post(
     *     path="/currency/delete",
     *     summary="Delete currencies",
     *     description="Delete currencies in your Panier company",
     *     operationId="deleteCurrencies",
     *     tags={"Currencies"},
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
     *     @OA\Response(response=200, description="Successfully deleted the currencies"),
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

        $result = $this->dbService->deleteCurrencies($validated['data']);
        return $this->successResponse($result);
    }
}
