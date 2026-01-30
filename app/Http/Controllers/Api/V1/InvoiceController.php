<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\PanierApiService;
use App\Services\PanierSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends BaseController
{
    public function __construct(
        protected PanierApiService $panierApi,
        protected PanierSyncService $syncService
    ) {}

    /**
     * @OA\Post(
     *     path="/invoice/create",
     *     summary="Create invoices",
     *     description="Create invoices in your Panier company with optional ZIMRA fiscalization",
     *     operationId="createInvoices",
     *     tags={"Invoices"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(property="data", type="array", minItems=1, maxItems=50,
     *                 @OA\Items(type="object",
     *                     required={"customer_id", "currency_id", "products"},
     *                     @OA\Property(property="customer_id", type="string"),
     *                     @OA\Property(property="currency_id", type="string"),
     *                     @OA\Property(property="products", type="array",
     *                         @OA\Items(type="object",
     *                             required={"id", "selling_price", "quantity"},
     *                             @OA\Property(property="id", type="string"),
     *                             @OA\Property(property="selling_price", type="number", format="float"),
     *                             @OA\Property(property="quantity", type="integer", minimum=1),
     *                             @OA\Property(property="discount", type="number", format="float", default=0)
     *                         )
     *                     ),
     *                     @OA\Property(property="date_format", type="string", example="dd/mm/yy"),
     *                     @OA\Property(property="payment_due", type="string", format="date-time", example="2025-04-18T15:40:00.546Z"),
     *                     @OA\Property(property="payment_information", type="string", example="Please make all payments to our CBZ Bank Account"),
     *                     @OA\Property(property="terms_n_conditions", type="string", example="Invoice invalid after due date"),
     *                     @OA\Property(property="recipients", type="array", @OA\Items(type="string", format="email")),
     *                     @OA\Property(property="is_proforma", type="boolean", default=false),
     *                     @OA\Property(property="template_preference", type="object",
     *                         @OA\Property(property="template", type="integer", default=0),
     *                         @OA\Property(property="color", type="string", default="no_color"),
     *                         @OA\Property(property="table_layout", type="string", default="Plain")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="zimra_fiscalize", type="boolean", default=false)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Successfully created the invoices"),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=406, description="Invoice Error"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array|min:1|max:50',
            'data.*.customer_id' => 'required|string',
            'data.*.currency_id' => 'required|string',
            'data.*.products' => 'required|array|min:1',
            'data.*.products.*.id' => 'required|string',
            'data.*.products.*.selling_price' => 'required|numeric',
            'data.*.products.*.quantity' => 'required|integer|min:1',
            'data.*.products.*.discount' => 'nullable|numeric',
            'data.*.date_format' => 'nullable|string',
            'data.*.payment_due' => 'nullable|string',
            'data.*.payment_information' => 'nullable|string',
            'data.*.terms_n_conditions' => 'nullable|string',
            'data.*.recipients' => 'nullable|array',
            'data.*.is_proforma' => 'nullable|boolean',
            'data.*.template_preference' => 'nullable|array',
            'zimra_fiscalize' => 'boolean',
        ]);

        $response = $this->panierApi->createInvoices(
            $validated['data'],
            $validated['zimra_fiscalize'] ?? false
        );
        return $this->handleApiResponse($response);
    }

    /**
     * @OA\Put(
     *     path="/invoice/update",
     *     summary="Update invoices",
     *     description="Update invoices in your Panier company",
     *     operationId="updateInvoices",
     *     tags={"Invoices"},
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
     *                     @OA\Property(property="due_date", type="string", format="date")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successfully updated the invoices"),
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

        $response = $this->panierApi->updateInvoices($validated['data']);
        return $this->handleApiResponse($response);
    }

    /**
     * @OA\Post(
     *     path="/invoice/search",
     *     summary="Search invoices",
     *     description="Search invoices in your Panier company",
     *     operationId="searchInvoices",
     *     tags={"Invoices"},
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
     *     @OA\Response(response=200, description="Successfully searched the invoices"),
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
        $response = $this->panierApi->searchInvoices(
            $data['query'] ?? '*',
            $data['limit'] ?? 10,
            $data['skip'] ?? 0
        );
        return $this->handleApiResponse($response);
    }

    /**
     * @OA\Post(
     *     path="/invoice/convert-to-sale",
     *     summary="Convert invoice to sale",
     *     description="Convert an invoice to a sale",
     *     operationId="convertInvoiceToSale",
     *     tags={"Invoices"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="invoice_id", type="string"),
     *                 @OA\Property(property="payment_method", type="string")
     *             ),
     *             @OA\Property(property="zimra_fiscalize", type="boolean", default=false)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successfully converted the invoice to sale"),
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
            'data.invoice_id' => 'required|string',
            'zimra_fiscalize' => 'boolean',
        ]);

        $response = $this->panierApi->convertInvoiceToSale(
            $validated['data'],
            $validated['zimra_fiscalize'] ?? false
        );
        return $this->handleApiResponse($response);
    }

    /**
     * @OA\Get(
     *     path="/invoice/download",
     *     summary="Download invoice",
     *     description="Download an invoice as PDF",
     *     operationId="downloadInvoice",
     *     tags={"Invoices"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\Parameter(name="id", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Successfully downloaded the invoice"),
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

        $response = $this->panierApi->downloadInvoice($validated['id']);
        return $this->handlePdfDownload($response, "invoice-{$validated['id']}.pdf");
    }

    /**
     * @OA\Post(
     *     path="/invoice/delete",
     *     summary="Delete invoices",
     *     description="Delete invoices in your Panier company",
     *     operationId="deleteInvoices",
     *     tags={"Invoices"},
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
     *     @OA\Response(response=200, description="Successfully deleted the invoices"),
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

        $response = $this->panierApi->deleteInvoices($validated['data']);
        return $this->handleApiResponse($response);
    }
}
