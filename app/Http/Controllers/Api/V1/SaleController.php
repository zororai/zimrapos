<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\PanierApiService;
use App\Services\PanierSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends BaseController
{
    public function __construct(
        protected PanierApiService $panierApi,
        protected PanierSyncService $syncService
    ) {}

    /**
     * @OA\Post(
     *     path="/sale/create",
     *     summary="Create a sale",
     *     description="Create a sale in your Panier company with optional ZIMRA fiscalization",
     *     operationId="createSale",
     *     tags={"Sales"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data", "payment_method"},
     *             @OA\Property(property="data", type="object",
     *                 required={"currency_id", "products"},
     *                 @OA\Property(property="customer_id", type="string", nullable=true),
     *                 @OA\Property(property="currency_id", type="string"),
     *                 @OA\Property(property="products", type="array",
     *                     @OA\Items(type="object",
     *                         required={"id", "selling_price", "quantity"},
     *                         @OA\Property(property="id", type="string"),
     *                         @OA\Property(property="selling_price", type="number", format="float"),
     *                         @OA\Property(property="quantity", type="integer", minimum=1),
     *                         @OA\Property(property="discount", type="number", format="float", default=0)
     *                     )
     *                 ),
     *                 @OA\Property(property="recipients", type="array", @OA\Items(type="string", format="email")),
     *                 @OA\Property(property="template_preference", type="object",
     *                     @OA\Property(property="template", type="integer", default=0),
     *                     @OA\Property(property="color", type="string", default="no_color"),
     *                     @OA\Property(property="table_layout", type="string", default="Plain")
     *                 )
     *             ),
     *             @OA\Property(property="zimra_fiscalize", type="boolean", default=false, description="Whether to fiscalize with ZIMRA. Only USD and ZWG supported."),
     *             @OA\Property(property="payment_method", type="object",
     *                 required={"type"},
     *                 @OA\Property(property="type", type="string", example="PAYNOW_ECOCASH"),
     *                 @OA\Property(property="phone", type="string", example="0772000001"),
     *                 @OA\Property(property="email", type="string", format="email")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Successfully created the sale"),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=406, description="Sale Error"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array',
            'data.customer_id' => 'nullable|string',
            'data.currency_id' => 'required|string',
            'data.products' => 'required|array|min:1',
            'data.products.*.id' => 'required|string',
            'data.products.*.selling_price' => 'required|numeric',
            'data.products.*.quantity' => 'required|integer|min:1',
            'data.products.*.discount' => 'nullable|numeric',
            'data.recipients' => 'nullable|array',
            'data.template_preference' => 'nullable|array',
            'zimra_fiscalize' => 'boolean',
            'payment_method' => 'required|array',
            'payment_method.type' => 'required|string',
            'payment_method.phone' => 'nullable|string',
            'payment_method.email' => 'nullable|email',
        ]);

        $response = $this->panierApi->createSale(
            $validated['data'],
            $validated['zimra_fiscalize'] ?? false,
            $validated['payment_method']
        );
        return $this->handleApiResponse($response);
    }

    /**
     * @OA\Get(
     *     path="/payment-provider/check-payment-status",
     *     summary="Check payment status",
     *     description="Check the payment status of a sale",
     *     operationId="checkPaymentStatus",
     *     tags={"Sales"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\Parameter(name="id", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Payment status retrieved"),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function checkPaymentStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|string',
        ]);

        $response = $this->panierApi->checkPaymentStatus($validated['id']);
        return $this->handleApiResponse($response);
    }

    /**
     * @OA\Post(
     *     path="/payment-provider/confirm-payment",
     *     summary="Confirm payment",
     *     description="Confirm a payment for a sale",
     *     operationId="confirmPayment",
     *     tags={"Sales"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="sale_id", type="string"),
     *                 @OA\Property(property="payment_reference", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Payment confirmed"),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function confirmPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array',
        ]);

        $response = $this->panierApi->confirmPayment($validated['data']);
        return $this->handleApiResponse($response);
    }

    /**
     * @OA\Post(
     *     path="/sale/search",
     *     summary="Search sales",
     *     description="Search sales in your Panier company",
     *     operationId="searchSales",
     *     tags={"Sales"},
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
     *     @OA\Response(response=200, description="Successfully searched the sales"),
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
        $response = $this->panierApi->searchSales(
            $data['query'] ?? '*',
            $data['limit'] ?? 10,
            $data['skip'] ?? 0
        );
        return $this->handleApiResponse($response);
    }

    /**
     * @OA\Post(
     *     path="/sale/void",
     *     summary="Void a sale",
     *     description="Void a sale in your Panier company",
     *     operationId="voidSale",
     *     tags={"Sales"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="reason", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successfully voided the sale"),
     *     @OA\Response(response=400, description="Request Body Validation Error"),
     *     @OA\Response(response=402, description="Expired Panier company subscription"),
     *     @OA\Response(response=403, description="Incorrect API Credentials"),
     *     @OA\Response(response=422, description="Missing Headers"),
     *     @OA\Response(response=429, description="Rate Limit exceeded")
     * )
     */
    public function void(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array',
            'data.id' => 'required|string',
        ]);

        $response = $this->panierApi->voidSale($validated['data']);
        return $this->handleApiResponse($response);
    }

    /**
     * @OA\Get(
     *     path="/sale/download",
     *     summary="Download sale receipt",
     *     description="Download a sale receipt as PDF",
     *     operationId="downloadSale",
     *     tags={"Sales"},
     *     security={{"AppId": {}, "ApiKey": {}}},
     *     @OA\Parameter(name="id", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Successfully downloaded the sale receipt"),
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

        $response = $this->panierApi->downloadSale($validated['id']);
        return $this->handlePdfDownload($response, "sale-{$validated['id']}.pdf");
    }
}
