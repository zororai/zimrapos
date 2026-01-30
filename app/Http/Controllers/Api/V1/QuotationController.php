<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\PanierApiService;
use App\Services\PanierSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotationController extends BaseController
{
    public function __construct(
        protected PanierApiService $panierApi,
        protected PanierSyncService $syncService
    ) {}

    /**
     * @OA\Post(
     *     path="/quotation/create",
     *     summary="Create quotations",
     *     description="Create quotations in your Panier company",
     *     operationId="createQuotations",
     *     tags={"Quotations"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(property="data", type="array", minItems=1, maxItems=50,
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="customer_id", type="string"),
     *                     @OA\Property(property="currency_id", type="string"),
     *                     @OA\Property(property="products", type="array",
     *                         @OA\Items(type="object",
     *                             @OA\Property(property="id", type="string"),
     *                             @OA\Property(property="quantity", type="integer")
     *                         )
     *                     ),
     *                     @OA\Property(property="valid_until", type="string", format="date"),
     *                     @OA\Property(property="recipients", type="array", @OA\Items(type="string", format="email"))
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Successfully created the quotations"),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=406, description="Quotation Error"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array|min:1|max:50',
        ]);

        $response = $this->panierApi->createQuotations($validated['data']);
        return $this->handleApiResponse($response);
    }

    /**
     * @OA\Put(
     *     path="/quotation/update",
     *     summary="Update quotations",
     *     description="Update quotations in your Panier company",
     *     operationId="updateQuotations",
     *     tags={"Quotations"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(property="data", type="array", minItems=1, maxItems=50,
     *                 @OA\Items(type="object", required={"id"},
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="customer_id", type="string"),
     *                     @OA\Property(property="products", type="array", @OA\Items(type="object")),
     *                     @OA\Property(property="valid_until", type="string", format="date")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successfully updated the quotations"),
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
            'data' => 'required|array|min:1|max:50',
            'data.*.id' => 'required|string',
        ]);

        $response = $this->panierApi->updateQuotations($validated['data']);
        return $this->handleApiResponse($response);
    }

    /**
     * @OA\Post(
     *     path="/quotation/search",
     *     summary="Search quotations",
     *     description="Search quotations in your Panier company",
     *     operationId="searchQuotations",
     *     tags={"Quotations"},
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
     *     @OA\Response(response=200, description="Successfully searched the quotations"),
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
        $response = $this->panierApi->searchQuotations(
            $data['query'] ?? '*',
            $data['limit'] ?? 10,
            $data['skip'] ?? 0
        );
        return $this->handleApiResponse($response);
    }

    /**
     * @OA\Post(
     *     path="/quotation/convert-to-invoice",
     *     summary="Convert quotation to invoice",
     *     description="Convert a quotation to an invoice",
     *     operationId="convertQuotationToInvoice",
     *     tags={"Quotations"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="quotation_id", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successfully converted the quotation to invoice"),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function convertToInvoice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array',
            'data.quotation_id' => 'required|string',
        ]);

        $response = $this->panierApi->convertQuotationToInvoice($validated['data']);
        return $this->handleApiResponse($response);
    }

    /**
     * @OA\Post(
     *     path="/quotation/convert-to-sale",
     *     summary="Convert quotation to sale",
     *     description="Convert a quotation directly to a sale",
     *     operationId="convertQuotationToSale",
     *     tags={"Quotations"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="quotation_id", type="string"),
     *                 @OA\Property(property="payment_method", type="string")
     *             ),
     *             @OA\Property(property="zimra_fiscalize", type="boolean", default=false)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successfully converted the quotation to sale"),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function convertToSale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array',
            'data.quotation_id' => 'required|string',
            'zimra_fiscalize' => 'boolean',
        ]);

        $response = $this->panierApi->convertQuotationToSale(
            $validated['data'],
            $validated['zimra_fiscalize'] ?? false
        );
        return $this->handleApiResponse($response);
    }

    /**
     * @OA\Get(
     *     path="/quotation/download",
     *     summary="Download quotation",
     *     description="Download a quotation as PDF",
     *     operationId="downloadQuotation",
     *     tags={"Quotations"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\Parameter(name="id", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Successfully downloaded the quotation"),
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

        $response = $this->panierApi->downloadQuotation($validated['id']);
        return $this->handlePdfDownload($response, "quotation-{$validated['id']}.pdf");
    }

    /**
     * @OA\Post(
     *     path="/quotation/delete",
     *     summary="Delete quotations",
     *     description="Delete quotations in your Panier company",
     *     operationId="deleteQuotations",
     *     tags={"Quotations"},
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
     *     @OA\Response(response=200, description="Successfully deleted the quotations"),
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

        $response = $this->panierApi->deleteQuotations($validated['data']);
        return $this->handleApiResponse($response);
    }
}
