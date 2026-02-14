<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\DatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends BaseController
{
    public function __construct(
        protected DatabaseService $dbService
    ) {}

    /**
     * @OA\Post(
     *     path="/product/create",
     *     summary="Create products",
     *     description="Create products in your Panier company",
     *     operationId="createProducts",
     *     tags={"Products"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 minItems=1,
     *                 maxItems=1000,
     *                 @OA\Items(
     *                     type="object",
     *                     required={"name", "selling_price"},
     *                     @OA\Property(property="name", type="string", example="Eggs"),
     *                     @OA\Property(property="description", type="string", example="One dozen large eggs"),
     *                     @OA\Property(property="buying_price", type="number", format="float", example=2.51),
     *                     @OA\Property(property="selling_price", type="number", format="float", example=5.81),
     *                     @OA\Property(property="initial_quantity", type="integer", example=100),
     *                     @OA\Property(property="hs_code", type="string", example="0808.01.01"),
     *                     @OA\Property(property="sku", type="string", example=""),
     *                     @OA\Property(property="is_inventory_item", type="boolean", example=true),
     *                     @OA\Property(property="applicable_tax_id", type="string", nullable=true),
     *                     @OA\Property(property="suppliers", type="array", nullable=true, @OA\Items(type="string"))
     *                 )
     *             ),
     *             @OA\Property(property="overwrite_duplicates", type="boolean", default=true, example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Successfully created the products",
     *         @OA\JsonContent(
     *             @OA\Property(property="created", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="excluded_duplicates", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=406, description="Product Error"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array|min:1|max:1000',
            'data.*.name' => 'required|string',
            'data.*.description' => 'nullable|string',
            'data.*.buying_price' => 'nullable|numeric',
            'data.*.selling_price' => 'required|numeric',
            'data.*.initial_quantity' => 'nullable|integer',
            'data.*.hs_code' => 'nullable|string',
            'data.*.sku' => 'nullable|string',
            'data.*.is_inventory_item' => 'nullable|boolean',
            'data.*.applicable_tax_id' => 'nullable|string',
            'data.*.suppliers' => 'nullable|array',
            'overwrite_duplicates' => 'boolean',
        ]);

        // Ensure all required fields are present with defaults
        $productData = array_map(function ($product) {
            return [
                'name' => $product['name'],
                'description' => $product['description'] ?? '',
                'buying_price' => $product['buying_price'] ?? 0,
                'selling_price' => $product['selling_price'],
                'initial_quantity' => $product['initial_quantity'] ?? 0,
                'hs_code' => $product['hs_code'] ?? '',
                'sku' => $product['sku'] ?? '',
                'is_inventory_item' => $product['is_inventory_item'] ?? true,
                'applicable_tax_id' => $product['applicable_tax_id'] ?? null,
                'suppliers' => $product['suppliers'] ?? null,
            ];
        }, $validated['data']);

        $result = $this->dbService->createProducts(
            $productData,
            $validated['overwrite_duplicates'] ?? true
        );

        return $this->successResponse($result, 201);
    }

    /**
     * @OA\Put(
     *     path="/product/update",
     *     summary="Update products",
     *     description="Update products in your Panier company. Note: You cannot update product quantities directly. Use Stock endpoints instead.",
     *     operationId="updateProducts",
     *     tags={"Products"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 minItems=1,
     *                 maxItems=1000,
     *                 @OA\Items(
     *                     type="object",
     *                     required={"id"},
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="name", type="string", example="Eggs"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="buying_price", type="number", format="float"),
     *                     @OA\Property(property="selling_price", type="number", format="float"),
     *                     @OA\Property(property="hs_code", type="string"),
     *                     @OA\Property(property="sku", type="string"),
     *                     @OA\Property(property="is_inventory_item", type="boolean"),
     *                     @OA\Property(property="applicable_tax_id", type="string", nullable=true),
     *                     @OA\Property(property="suppliers", type="array", nullable=true, @OA\Items(type="string"))
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully updated the products",
     *         @OA\JsonContent(
     *             @OA\Property(property="updated", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=406, description="Product Error"),
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

        $result = $this->dbService->updateProducts($validated['data']);

        return $this->successResponse($result);
    }

    /**
     * @OA\Post(
     *     path="/product/search",
     *     summary="Search products",
     *     description="Search products in your Panier company. Results are returned in descending order of created_at.",
     *     operationId="searchProducts",
     *     tags={"Products"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="query", type="string", default="*", example="*"),
     *                 @OA\Property(property="limit", type="integer", default=10, example=10),
     *                 @OA\Property(property="skip", type="integer", default=0, example=0)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully searched the products",
     *         @OA\JsonContent(
     *             @OA\Property(property="searched_record_count", type="integer"),
     *             @OA\Property(property="total_record_count", type="integer"),
     *             @OA\Property(property="searched", type="array", @OA\Items(type="object"))
     *         )
     *     ),
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
        $result = $this->dbService->searchProducts(
            $data['query'] ?? '*',
            $data['limit'] ?? 10,
            $data['skip'] ?? 0
        );

        return $this->successResponse($result);
    }

    /**
     * @OA\Post(
     *     path="/product/delete",
     *     summary="Delete products",
     *     description="Delete products in your Panier company",
     *     operationId="deleteProducts",
     *     tags={"Products"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 minItems=1,
     *                 maxItems=1000,
     *                 @OA\Items(
     *                     type="object",
     *                     required={"id"},
     *                     @OA\Property(property="id", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully deleted the products",
     *         @OA\JsonContent(
     *             @OA\Property(property="deleted_count", type="integer")
     *         )
     *     ),
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

        $result = $this->dbService->deleteProducts($validated['data']);

        return $this->successResponse($result);
    }
}
