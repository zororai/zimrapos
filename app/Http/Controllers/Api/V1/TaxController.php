<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\PanierTax;
use App\Services\DatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxController extends BaseController
{
    public function __construct(
        protected DatabaseService $dbService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tax/zimra-types",
     *     summary="Get available ZIMRA tax types",
     *     description="Returns the list of available ZIMRA tax types that can be used when creating taxes",
     *     operationId="getZimraTaxTypes",
     *     tags={"Taxes"},
     *     @OA\Response(response=200, description="Successfully retrieved ZIMRA tax types")
     * )
     */
    public function zimraTypes(): JsonResponse
    {
        return $this->successResponse([
            'data' => array_values(PanierTax::ZIMRA_TAX_TYPES),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/tax/create",
     *     summary="Create taxes",
     *     description="Create taxes in your Panier company",
     *     operationId="createTaxes",
     *     tags={"Taxes"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(property="data", type="array", minItems=1, maxItems=1000,
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="zimra_tax_id", type="integer", description="ZIMRA Tax ID (3=Exempt, 515=Standard, 514=Withholding, 2=Zero)"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="percentage", type="number", format="float"),
     *                     @OA\Property(property="code", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Successfully created the taxes"),
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
            'data.*.zimra_tax_id' => 'nullable|integer|in:2,3,514,515',
            'data.*.name' => 'required_without:data.*.zimra_tax_id|string',
            'data.*.percentage' => 'required_without:data.*.zimra_tax_id|numeric',
            'data.*.code' => 'nullable|string',
        ]);

        // Map zimra_tax_id to full tax data if provided
        $taxData = array_map(function ($tax) {
            if (isset($tax['zimra_tax_id']) && isset(PanierTax::ZIMRA_TAX_TYPES[$tax['zimra_tax_id']])) {
                return array_merge(PanierTax::ZIMRA_TAX_TYPES[$tax['zimra_tax_id']], $tax);
            }
            return $tax;
        }, $validated['data']);

        $result = $this->dbService->createTaxes($taxData);
        return $this->successResponse($result, 201);
    }

    /**
     * @OA\Put(
     *     path="/tax/update",
     *     summary="Update taxes",
     *     description="Update taxes in your Panier company",
     *     operationId="updateTaxes",
     *     tags={"Taxes"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(property="data", type="array", minItems=1, maxItems=1000,
     *                 @OA\Items(type="object", required={"id"},
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="percentage", type="number", format="float"),
     *                     @OA\Property(property="code", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successfully updated the taxes"),
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

        $result = $this->dbService->updateTaxes($validated['data']);
        return $this->successResponse($result);
    }

    /**
     * @OA\Post(
     *     path="/tax/search",
     *     summary="Search taxes",
     *     description="Search taxes in your Panier company",
     *     operationId="searchTaxes",
     *     tags={"Taxes"},
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
     *     @OA\Response(response=200, description="Successfully searched the taxes"),
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
        $result = $this->dbService->searchTaxes(
            $data['query'] ?? '*',
            $data['limit'] ?? 10,
            $data['skip'] ?? 0
        );
        return $this->successResponse($result);
    }

    /**
     * @OA\Post(
     *     path="/tax/delete",
     *     summary="Delete taxes",
     *     description="Delete taxes in your Panier company",
     *     operationId="deleteTaxes",
     *     tags={"Taxes"},
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
     *     @OA\Response(response=200, description="Successfully deleted the taxes"),
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

        $result = $this->dbService->deleteTaxes($validated['data']);
        return $this->successResponse($result);
    }
}
