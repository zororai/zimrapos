<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\PanierApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends BaseController
{
    public function __construct(
        protected PanierApiService $panierApi
    ) {}

    /**
     * @OA\Post(
     *     path="/stock/add",
     *     summary="Add stock",
     *     description="Add stock quantities to products in your Panier company",
     *     operationId="addStock",
     *     tags={"Stocks"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"product_id", "quantity", "description"},
     *                     @OA\Property(property="product_id", type="string"),
     *                     @OA\Property(property="quantity", type="integer", minimum=1),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="suppliers", type="string", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successfully added stock"),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function add(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array',
            'data.*.product_id' => 'required|string',
            'data.*.quantity' => 'required|integer|min:1',
            'data.*.description' => 'required|string',
            'data.*.suppliers' => 'nullable|string',
        ]);

        $response = $this->panierApi->addStock($validated['data']);

        return $this->handleApiResponse($response);
    }

    /**
     * @OA\Post(
     *     path="/stock/subtract",
     *     summary="Subtract stock",
     *     description="Subtract stock quantities from products in your Panier company",
     *     operationId="subtractStock",
     *     tags={"Stocks"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"product_id", "quantity", "description"},
     *                     @OA\Property(property="product_id", type="string"),
     *                     @OA\Property(property="quantity", type="integer", minimum=1),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="suppliers", type="string", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successfully subtracted stock"),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function subtract(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array',
            'data.*.product_id' => 'required|string',
            'data.*.quantity' => 'required|integer|min:1',
            'data.*.description' => 'required|string',
            'data.*.suppliers' => 'nullable|string',
        ]);

        $response = $this->panierApi->subtractStock($validated['data']);

        return $this->handleApiResponse($response);
    }
}
