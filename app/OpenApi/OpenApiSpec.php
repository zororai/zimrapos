<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "API Integration",
    description: "API endpoints for ZIMRA Fiscalisation Support.

## Rate Limit
500 API calls every 5 minutes for each unique IP Address.

## Authentication
Requires APP-ID and API-KEY headers obtained from your company account's Developer API section.

## ZIMRA Fiscalisation
This API supports ZIMRA Fiscal Invoices, Credit Notes and Debit Notes based on ZIMRA Fiscal Device Gateway API Specs v7.2.",
    contact: new OA\Contact(email: "support@example.com")
)]
#[OA\Server(url: "/api/v1", description: "Local API Server")]
#[OA\SecurityScheme(
    securityScheme: "AppId",
    type: "apiKey",
    in: "header",
    name: "APP-ID",
    description: "Panier APP-ID from Developer API section"
)]
#[OA\SecurityScheme(
    securityScheme: "ApiKey",
    type: "apiKey",
    in: "header",
    name: "API-KEY",
    description: "Panier API-KEY from Developer API section"
)]
#[OA\Tag(name: "Products", description: "Manage products in your Panier company")]
#[OA\Tag(name: "Stocks", description: "Manage stock quantities")]
#[OA\Tag(name: "Customers", description: "Manage customers")]
#[OA\Tag(name: "Suppliers", description: "Manage suppliers")]
#[OA\Tag(name: "Taxes", description: "Manage taxes")]
#[OA\Tag(name: "Currencies", description: "Manage currencies (119 supported)")]
#[OA\Tag(name: "Sales", description: "Manage sales and payments")]
#[OA\Tag(name: "Invoices", description: "Manage invoices")]
#[OA\Tag(name: "Debit Notes", description: "Manage debit notes")]
#[OA\Tag(name: "Credit Notes", description: "Manage credit notes")]
#[OA\Tag(name: "Delivery Notes", description: "Manage delivery notes")]
#[OA\Tag(name: "Quotations", description: "Manage quotations")]
#[OA\Tag(name: "ZIMRA Fiscalisation", description: "ZIMRA fiscal day operations and fiscalization")]

// Products
#[OA\Post(
    path: "/product/create",
    summary: "Create products",
    description: "Create products in your Panier company",
    operationId: "createProducts",
    tags: ["Products"],
    security: [["AppId" => [], "ApiKey" => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["data"],
            properties: [
                new OA\Property(
                    property: "data",
                    type: "array",
                    minItems: 1,
                    maxItems: 1000,
                    items: new OA\Items(
                        type: "object",
                        required: ["name", "selling_price"],
                        properties: [
                            new OA\Property(property: "name", type: "string", example: "Eggs"),
                            new OA\Property(property: "description", type: "string", example: "One dozen large eggs"),
                            new OA\Property(property: "buying_price", type: "number", format: "float", example: 2.51),
                            new OA\Property(property: "selling_price", type: "number", format: "float", example: 5.81),
                            new OA\Property(property: "initial_quantity", type: "integer", example: 100),
                            new OA\Property(property: "hs_code", type: "string", example: "0808.01.01"),
                            new OA\Property(property: "sku", type: "string", example: ""),
                            new OA\Property(property: "is_inventory_item", type: "boolean", example: true),
                            new OA\Property(property: "applicable_tax_id", type: "string", nullable: true),
                            new OA\Property(property: "suppliers", type: "array", nullable: true, items: new OA\Items(type: "string"))
                        ]
                    )
                ),
                new OA\Property(property: "overwrite_duplicates", type: "boolean", default: true, example: true)
            ]
        )
    ),
    responses: [
        new OA\Response(response: 201, description: "Successfully created the products"),
        new OA\Response(response: 400, description: "Request Body Validation Error"),
        new OA\Response(response: 402, description: "Expired Panier company subscription"),
        new OA\Response(response: 403, description: "Incorrect API Credentials"),
        new OA\Response(response: 406, description: "Product Error"),
        new OA\Response(response: 422, description: "Missing Headers"),
        new OA\Response(response: 429, description: "Rate Limit exceeded")
    ]
)]
#[OA\Put(
    path: "/product/update",
    summary: "Update products",
    description: "Update products in your Panier company. Cannot update quantities directly - use Stock endpoints.",
    operationId: "updateProducts",
    tags: ["Products"],
    security: [["AppId" => [], "ApiKey" => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["data"],
            properties: [
                new OA\Property(
                    property: "data",
                    type: "array",
                    minItems: 1,
                    maxItems: 1000,
                    items: new OA\Items(
                        type: "object",
                        required: ["id"],
                        properties: [
                            new OA\Property(property: "id", type: "string"),
                            new OA\Property(property: "name", type: "string", example: "Eggs"),
                            new OA\Property(property: "description", type: "string"),
                            new OA\Property(property: "buying_price", type: "number", format: "float"),
                            new OA\Property(property: "selling_price", type: "number", format: "float"),
                            new OA\Property(property: "hs_code", type: "string"),
                            new OA\Property(property: "sku", type: "string"),
                            new OA\Property(property: "is_inventory_item", type: "boolean"),
                            new OA\Property(property: "applicable_tax_id", type: "string", nullable: true),
                            new OA\Property(property: "suppliers", type: "array", nullable: true, items: new OA\Items(type: "string"))
                        ]
                    )
                )
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: "Successfully updated the products"),
        new OA\Response(response: 400, description: "Request Body Validation Error"),
        new OA\Response(response: 402, description: "Expired Panier company subscription"),
        new OA\Response(response: 403, description: "Incorrect API Credentials"),
        new OA\Response(response: 406, description: "Product Error"),
        new OA\Response(response: 422, description: "Missing Headers"),
        new OA\Response(response: 429, description: "Rate Limit exceeded")
    ]
)]
#[OA\Post(
    path: "/product/search",
    summary: "Search products",
    description: "Search products in your Panier company. Results returned in descending order of created_at.",
    operationId: "searchProducts",
    tags: ["Products"],
    security: [["AppId" => [], "ApiKey" => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["data"],
            properties: [
                new OA\Property(
                    property: "data",
                    type: "object",
                    properties: [
                        new OA\Property(property: "query", type: "string", default: "*", example: "*"),
                        new OA\Property(property: "limit", type: "integer", default: 10, example: 10),
                        new OA\Property(property: "skip", type: "integer", default: 0, example: 0)
                    ]
                )
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: "Successfully searched the products"),
        new OA\Response(response: 400, description: "Request Body Validation Error"),
        new OA\Response(response: 402, description: "Expired Panier company subscription"),
        new OA\Response(response: 403, description: "Incorrect API Credentials"),
        new OA\Response(response: 422, description: "Missing Headers"),
        new OA\Response(response: 429, description: "Rate Limit exceeded")
    ]
)]
#[OA\Post(
    path: "/product/delete",
    summary: "Delete products",
    description: "Delete products in your Panier company",
    operationId: "deleteProducts",
    tags: ["Products"],
    security: [["AppId" => [], "ApiKey" => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["data"],
            properties: [
                new OA\Property(
                    property: "data",
                    type: "array",
                    minItems: 1,
                    maxItems: 1000,
                    items: new OA\Items(
                        type: "object",
                        required: ["id"],
                        properties: [
                            new OA\Property(property: "id", type: "string")
                        ]
                    )
                )
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: "Successfully deleted the products"),
        new OA\Response(response: 400, description: "Request Body Validation Error"),
        new OA\Response(response: 402, description: "Expired Panier company subscription"),
        new OA\Response(response: 403, description: "Incorrect API Credentials"),
        new OA\Response(response: 422, description: "Missing Headers"),
        new OA\Response(response: 429, description: "Rate Limit exceeded")
    ]
)]

// Stocks
#[OA\Post(
    path: "/stock/add",
    summary: "Add stock",
    description: "Add stock quantities to products",
    operationId: "addStock",
    tags: ["Stocks"],
    security: [["AppId" => [], "ApiKey" => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["data"],
            properties: [
                new OA\Property(
                    property: "data",
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        required: ["product_id", "quantity", "description"],
                        properties: [
                            new OA\Property(property: "product_id", type: "string"),
                            new OA\Property(property: "quantity", type: "integer", minimum: 1),
                            new OA\Property(property: "description", type: "string"),
                            new OA\Property(property: "suppliers", type: "string", nullable: true)
                        ]
                    )
                )
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: "Successfully added stock"),
        new OA\Response(response: 400, description: "Request Body Validation Error"),
        new OA\Response(response: 429, description: "Rate Limit exceeded")
    ]
)]
#[OA\Post(
    path: "/stock/subtract",
    summary: "Subtract stock",
    description: "Subtract stock quantities from products",
    operationId: "subtractStock",
    tags: ["Stocks"],
    security: [["AppId" => [], "ApiKey" => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["data"],
            properties: [
                new OA\Property(
                    property: "data",
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        required: ["product_id", "quantity", "description"],
                        properties: [
                            new OA\Property(property: "product_id", type: "string"),
                            new OA\Property(property: "quantity", type: "integer", minimum: 1),
                            new OA\Property(property: "description", type: "string"),
                            new OA\Property(property: "suppliers", type: "string", nullable: true)
                        ]
                    )
                )
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: "Successfully subtracted stock"),
        new OA\Response(response: 400, description: "Request Body Validation Error"),
        new OA\Response(response: 429, description: "Rate Limit exceeded")
    ]
)]

// Customers
#[OA\Post(path: "/customer/create", summary: "Create customers", tags: ["Customers"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["name"], properties: [new OA\Property(property: "name", type: "string", example: "Corner Bakery"), new OA\Property(property: "contact_person", type: "string", example: "John Smith"), new OA\Property(property: "phone", type: "string", example: "0772000001"), new OA\Property(property: "email", type: "string", format: "email", example: "john@cornerbakery.com"), new OA\Property(property: "address", type: "string", example: "123 Road Drive, City, Country"), new OA\Property(property: "fax", type: "string"), new OA\Property(property: "website", type: "string"), new OA\Property(property: "tax_reg_number", type: "string"), new OA\Property(property: "company_reg_number", type: "string"), new OA\Property(property: "tin_number", type: "string"), new OA\Property(property: "vat_number", type: "string")])), new OA\Property(property: "overwrite_duplicates", type: "boolean", example: true)])), responses: [new OA\Response(response: 201, description: "Success"), new OA\Response(response: 400, description: "Error")])]
#[OA\Put(path: "/customer/update", summary: "Update customers", tags: ["Customers"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["id"], properties: [new OA\Property(property: "id", type: "string"), new OA\Property(property: "name", type: "string")]))])), responses: [new OA\Response(response: 200, description: "Success")])]
#[OA\Post(path: "/customer/search", summary: "Search customers", tags: ["Customers"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "object", properties: [new OA\Property(property: "query", type: "string", default: "*"), new OA\Property(property: "limit", type: "integer", default: 10), new OA\Property(property: "skip", type: "integer", default: 0)])])), responses: [new OA\Response(response: 200, description: "Success")])]
#[OA\Post(path: "/customer/delete", summary: "Delete customers", tags: ["Customers"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["id"], properties: [new OA\Property(property: "id", type: "string")]))])), responses: [new OA\Response(response: 200, description: "Success")])]

// Suppliers
#[OA\Post(path: "/supplier/create", summary: "Create suppliers", tags: ["Suppliers"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["name"], properties: [new OA\Property(property: "name", type: "string")]))])), responses: [new OA\Response(response: 201, description: "Success")])]
#[OA\Put(path: "/supplier/update", summary: "Update suppliers", tags: ["Suppliers"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["id"], properties: [new OA\Property(property: "id", type: "string")]))])), responses: [new OA\Response(response: 200, description: "Success")])]
#[OA\Post(path: "/supplier/search", summary: "Search suppliers", tags: ["Suppliers"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "object", properties: [new OA\Property(property: "query", type: "string", default: "*"), new OA\Property(property: "limit", type: "integer", default: 10), new OA\Property(property: "skip", type: "integer", default: 0)])])), responses: [new OA\Response(response: 200, description: "Success")])]
#[OA\Post(path: "/supplier/delete", summary: "Delete suppliers", tags: ["Suppliers"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["id"], properties: [new OA\Property(property: "id", type: "string")]))])), responses: [new OA\Response(response: 200, description: "Success")])]

// Taxes
#[OA\Post(path: "/tax/create", summary: "Create taxes", tags: ["Taxes"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["name", "percentage"], properties: [new OA\Property(property: "name", type: "string"), new OA\Property(property: "percentage", type: "number"), new OA\Property(property: "code", type: "string")]))])), responses: [new OA\Response(response: 201, description: "Success")])]
#[OA\Put(path: "/tax/update", summary: "Update taxes", tags: ["Taxes"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["id"], properties: [new OA\Property(property: "id", type: "string")]))])), responses: [new OA\Response(response: 200, description: "Success")])]
#[OA\Post(path: "/tax/search", summary: "Search taxes", tags: ["Taxes"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "object", properties: [new OA\Property(property: "query", type: "string", default: "*")])])), responses: [new OA\Response(response: 200, description: "Success")])]
#[OA\Post(path: "/tax/delete", summary: "Delete taxes", tags: ["Taxes"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["id"], properties: [new OA\Property(property: "id", type: "string")]))])), responses: [new OA\Response(response: 200, description: "Success")])]

// Currencies
#[OA\Post(path: "/currency/create", summary: "Create currencies", description: "Supports 119 currencies", tags: ["Currencies"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["code"], properties: [new OA\Property(property: "code", type: "string", example: "USD"), new OA\Property(property: "exchange_rate", type: "number", example: 1.0)]))])), responses: [new OA\Response(response: 201, description: "Success")])]
#[OA\Put(path: "/currency/update", summary: "Update currencies", tags: ["Currencies"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["id"], properties: [new OA\Property(property: "id", type: "string"), new OA\Property(property: "exchange_rate", type: "number")]))])), responses: [new OA\Response(response: 200, description: "Success")])]
#[OA\Post(path: "/currency/search", summary: "Search currencies", tags: ["Currencies"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "object", properties: [new OA\Property(property: "query", type: "string", default: "*")])])), responses: [new OA\Response(response: 200, description: "Success")])]
#[OA\Post(path: "/currency/delete", summary: "Delete currencies", tags: ["Currencies"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["id"], properties: [new OA\Property(property: "id", type: "string")]))])), responses: [new OA\Response(response: 200, description: "Success")])]

// Sales
#[OA\Post(path: "/sale/create", summary: "Create a sale", description: "Create a sale with optional ZIMRA fiscalization", tags: ["Sales"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "object", properties: [new OA\Property(property: "customer_id", type: "string", nullable: true), new OA\Property(property: "currency_id", type: "string"), new OA\Property(property: "products", type: "array", items: new OA\Items(type: "object")), new OA\Property(property: "payment_method", type: "string")]), new OA\Property(property: "zimra_fiscalize", type: "boolean", default: false)])), responses: [new OA\Response(response: 201, description: "Success")])]
#[OA\Post(path: "/sale/search", summary: "Search sales", tags: ["Sales"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "object", properties: [new OA\Property(property: "query", type: "string", default: "*")])])), responses: [new OA\Response(response: 200, description: "Success")])]
#[OA\Post(path: "/sale/void", summary: "Void a sale", tags: ["Sales"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "object", properties: [new OA\Property(property: "id", type: "string"), new OA\Property(property: "reason", type: "string")])])), responses: [new OA\Response(response: 200, description: "Success")])]
#[OA\Get(path: "/sale/download", summary: "Download sale receipt as PDF", tags: ["Sales"], security: [["AppId" => [], "ApiKey" => []]], parameters: [new OA\Parameter(name: "id", in: "query", required: true, schema: new OA\Schema(type: "string"))], responses: [new OA\Response(response: 200, description: "PDF file")])]
#[OA\Get(path: "/payment-provider/check-payment-status", summary: "Check payment status", tags: ["Sales"], security: [["AppId" => [], "ApiKey" => []]], parameters: [new OA\Parameter(name: "id", in: "query", required: true, schema: new OA\Schema(type: "string"))], responses: [new OA\Response(response: 200, description: "Payment status")])]
#[OA\Post(path: "/payment-provider/confirm-payment", summary: "Confirm payment", tags: ["Sales"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "object")])), responses: [new OA\Response(response: 200, description: "Success")])]

// Invoices
#[OA\Post(path: "/invoice/create", summary: "Create invoices", description: "Create invoices with optional ZIMRA fiscalization", tags: ["Invoices"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["customer_id", "currency_id", "products"], properties: [new OA\Property(property: "customer_id", type: "string", example: ""), new OA\Property(property: "currency_id", type: "string", example: ""), new OA\Property(property: "products", type: "array", items: new OA\Items(type: "object", required: ["id", "selling_price", "quantity"], properties: [new OA\Property(property: "id", type: "string", example: ""), new OA\Property(property: "selling_price", type: "number", example: 10.2), new OA\Property(property: "quantity", type: "integer", example: 1), new OA\Property(property: "discount", type: "number", example: 0)])), new OA\Property(property: "date_format", type: "string", example: "dd/mm/yy"), new OA\Property(property: "payment_due", type: "string", example: "2025-04-18T15:40:00.546Z"), new OA\Property(property: "payment_information", type: "string", example: "Please make all payments to our CBZ Bank Account 0100000000"), new OA\Property(property: "terms_n_conditions", type: "string", example: "This invoice will be considered invalid when the payment due date has lapsed"), new OA\Property(property: "recipients", type: "array", items: new OA\Items(type: "string"), example: []), new OA\Property(property: "is_proforma", type: "boolean", example: false), new OA\Property(property: "template_preference", type: "object", properties: [new OA\Property(property: "template", type: "integer", example: 0), new OA\Property(property: "color", type: "string", example: "no_color"), new OA\Property(property: "table_layout", type: "string", example: "Plain")])])), new OA\Property(property: "zimra_fiscalize", type: "boolean", default: false)])), responses: [new OA\Response(response: 201, description: "Success")])]
#[OA\Put(path: "/invoice/update", summary: "Update invoices", tags: ["Invoices"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["id"], properties: [new OA\Property(property: "id", type: "string", example: ""), new OA\Property(property: "customer_id", type: "string", example: ""), new OA\Property(property: "use_base_currency", type: "boolean", example: false), new OA\Property(property: "currency_id", type: "string", example: ""), new OA\Property(property: "products", type: "array", items: new OA\Items(type: "object", properties: [new OA\Property(property: "id", type: "string", example: ""), new OA\Property(property: "selling_price", type: "number", example: 10.2), new OA\Property(property: "quantity", type: "integer", example: 1), new OA\Property(property: "discount", type: "number", example: 0)])), new OA\Property(property: "date_format", type: "string", example: "dd/mm/yy"), new OA\Property(property: "payment_due", type: "string", example: "2025-04-18T15:40:00.546Z"), new OA\Property(property: "payment_information", type: "string", example: "Please make all payments to our CBZ Bank Account 0100000000"), new OA\Property(property: "terms_n_conditions", type: "string", example: "This invoice will be considered invalid when the payment due date has lapsed"), new OA\Property(property: "recipients", type: "array", items: new OA\Items(type: "string"), example: []), new OA\Property(property: "is_proforma", type: "boolean", example: false), new OA\Property(property: "template_preference", type: "object", properties: [new OA\Property(property: "template", type: "integer", example: 0), new OA\Property(property: "color", type: "string", example: "no_color"), new OA\Property(property: "table_layout", type: "string", example: "Plain")])]))])), responses: [new OA\Response(response: 200, description: "Success")])]
#[OA\Post(path: "/invoice/search", summary: "Search invoices", tags: ["Invoices"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "object", properties: [new OA\Property(property: "query", type: "string", example: "*"), new OA\Property(property: "limit", type: "integer", example: 10), new OA\Property(property: "skip", type: "integer", example: 0)])])), responses: [new OA\Response(response: 200, description: "Success")])]
#[OA\Post(path: "/invoice/convert-to-sale", summary: "Convert invoice to sale", tags: ["Invoices"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "object", properties: [new OA\Property(property: "invoice_id", type: "string", example: "")]), new OA\Property(property: "payment_method", type: "object", properties: [new OA\Property(property: "type", type: "string", example: "PAYNOW_ECOCASH"), new OA\Property(property: "phone", type: "string", example: "0772000001"), new OA\Property(property: "email", type: "string", example: "john@cornerbakery.com")])])), responses: [new OA\Response(response: 200, description: "Success")])]
#[OA\Get(path: "/invoice/download", summary: "Download invoice as PDF", tags: ["Invoices"], security: [["AppId" => [], "ApiKey" => []]], parameters: [new OA\Parameter(name: "id", in: "query", required: true, schema: new OA\Schema(type: "string"))], responses: [new OA\Response(response: 200, description: "PDF file")])]
#[OA\Post(path: "/invoice/delete", summary: "Delete invoices", tags: ["Invoices"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["id"], properties: [new OA\Property(property: "id", type: "string", example: "")]))])), responses: [new OA\Response(response: 200, description: "Success")])]

// Debit Notes
#[OA\Post(path: "/debit-note/create", summary: "Create debit notes", description: "Create debit notes with optional ZIMRA fiscalization. Only USD and ZWG supported for ZIMRA.", tags: ["Debit Notes"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["invoice_id", "products", "reason"])), new OA\Property(property: "zimra_fiscalize", type: "boolean", default: false)])), responses: [new OA\Response(response: 201, description: "Success")])]
#[OA\Post(path: "/debit-note/search", summary: "Search debit notes", tags: ["Debit Notes"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "object")])), responses: [new OA\Response(response: 200, description: "Success")])]
#[OA\Get(path: "/debit-note/download", summary: "Download debit note as PDF", tags: ["Debit Notes"], security: [["AppId" => [], "ApiKey" => []]], parameters: [new OA\Parameter(name: "id", in: "query", required: true, schema: new OA\Schema(type: "string"))], responses: [new OA\Response(response: 200, description: "PDF file")])]
#[OA\Post(path: "/debit-note/delete", summary: "Delete debit notes", tags: ["Debit Notes"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["id"], properties: [new OA\Property(property: "id", type: "string", example: "")]))])), responses: [new OA\Response(response: 200, description: "Success")])]

// Credit Notes
#[OA\Post(path: "/credit-note/create", summary: "Create credit notes", description: "Create credit notes with optional ZIMRA fiscalization", tags: ["Credit Notes"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["sale_id", "products", "reason"])), new OA\Property(property: "zimra_fiscalize", type: "boolean", default: false)])), responses: [new OA\Response(response: 201, description: "Success")])]
#[OA\Post(path: "/credit-note/search", summary: "Search credit notes", tags: ["Credit Notes"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "object")])), responses: [new OA\Response(response: 200, description: "Success")])]
#[OA\Get(path: "/credit-note/download", summary: "Download credit note as PDF", tags: ["Credit Notes"], security: [["AppId" => [], "ApiKey" => []]], parameters: [new OA\Parameter(name: "id", in: "query", required: true, schema: new OA\Schema(type: "string"))], responses: [new OA\Response(response: 200, description: "PDF file")])]
#[OA\Post(path: "/credit-note/delete", summary: "Delete credit notes", tags: ["Credit Notes"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["id"], properties: [new OA\Property(property: "id", type: "string", example: "")]))])), responses: [new OA\Response(response: 200, description: "Success")])]

// Delivery Notes
#[OA\Post(path: "/delivery-note/create", summary: "Create delivery notes", tags: ["Delivery Notes"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object"))])), responses: [new OA\Response(response: 201, description: "Success")])]
#[OA\Post(path: "/delivery-note/search", summary: "Search delivery notes", tags: ["Delivery Notes"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "object")])), responses: [new OA\Response(response: 200, description: "Success")])]
#[OA\Get(path: "/delivery-note/download", summary: "Download delivery note as PDF", tags: ["Delivery Notes"], security: [["AppId" => [], "ApiKey" => []]], parameters: [new OA\Parameter(name: "id", in: "query", required: true, schema: new OA\Schema(type: "string"))], responses: [new OA\Response(response: 200, description: "PDF file")])]
#[OA\Post(path: "/delivery-note/delete", summary: "Delete delivery notes", tags: ["Delivery Notes"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["id"], properties: [new OA\Property(property: "id", type: "string", example: "")]))])), responses: [new OA\Response(response: 200, description: "Success")])]

// Quotations
#[OA\Post(path: "/quotation/create", summary: "Create quotations", tags: ["Quotations"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object"))])), responses: [new OA\Response(response: 201, description: "Success")])]
#[OA\Put(path: "/quotation/update", summary: "Update quotations", tags: ["Quotations"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["id"]))])), responses: [new OA\Response(response: 200, description: "Success")])]
#[OA\Post(path: "/quotation/search", summary: "Search quotations", tags: ["Quotations"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "object")])), responses: [new OA\Response(response: 200, description: "Success")])]
#[OA\Post(path: "/quotation/convert-to-invoice", summary: "Convert quotation to invoice", tags: ["Quotations"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "object", properties: [new OA\Property(property: "quotation_id", type: "string")])])), responses: [new OA\Response(response: 200, description: "Success")])]
#[OA\Post(path: "/quotation/convert-to-sale", summary: "Convert quotation to sale", tags: ["Quotations"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "object", properties: [new OA\Property(property: "quotation_id", type: "string")]), new OA\Property(property: "zimra_fiscalize", type: "boolean", default: false)])), responses: [new OA\Response(response: 200, description: "Success")])]
#[OA\Get(path: "/quotation/download", summary: "Download quotation as PDF", tags: ["Quotations"], security: [["AppId" => [], "ApiKey" => []]], parameters: [new OA\Parameter(name: "id", in: "query", required: true, schema: new OA\Schema(type: "string"))], responses: [new OA\Response(response: 200, description: "PDF file")])]
#[OA\Post(path: "/quotation/delete", summary: "Delete quotations", tags: ["Quotations"], security: [["AppId" => [], "ApiKey" => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["data"], properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object", required: ["id"], properties: [new OA\Property(property: "id", type: "string", example: "")]))])), responses: [new OA\Response(response: 200, description: "Success")])]

// ZIMRA Fiscalisation - Configuration
#[OA\Post(
    path: "/zimra/config",
    summary: "Store ZIMRA configuration",
    description: "Store ZIMRA API configuration (base URL, device model, version). Only one active config allowed.",
    tags: ["ZIMRA Fiscalisation"],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["base_url", "device_model", "device_version"],
            properties: [
                new OA\Property(property: "base_url", type: "string", example: "https://fdmsapitest.zimra.co.zw"),
                new OA\Property(property: "device_model", type: "string", example: "Server"),
                new OA\Property(property: "device_version", type: "string", example: "v1")
            ]
        )
    ),
    responses: [
        new OA\Response(response: 201, description: "Configuration stored successfully"),
        new OA\Response(response: 400, description: "Validation error")
    ]
)]
#[OA\Get(
    path: "/zimra/config",
    summary: "Get active ZIMRA configuration",
    description: "Retrieve the currently active ZIMRA configuration",
    tags: ["ZIMRA Fiscalisation"],
    responses: [
        new OA\Response(response: 200, description: "Active configuration returned"),
        new OA\Response(response: 404, description: "No active configuration found")
    ]
)]
#[OA\Put(
    path: "/zimra/config/{id}",
    summary: "Update ZIMRA configuration",
    description: "Update an existing ZIMRA configuration",
    tags: ["ZIMRA Fiscalisation"],
    parameters: [
        new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "base_url", type: "string"),
                new OA\Property(property: "device_model", type: "string"),
                new OA\Property(property: "device_version", type: "string"),
                new OA\Property(property: "is_active", type: "boolean")
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: "Configuration updated"),
        new OA\Response(response: 404, description: "Configuration not found")
    ]
)]

// ZIMRA Fiscalisation - Device Registration
#[OA\Post(
    path: "/zimra/register",
    summary: "Register ZIMRA fiscal device",
    description: "Register device with ZIMRA. Generates ECC P-256 private key, CSR, and obtains device certificate.",
    tags: ["ZIMRA Fiscalisation"],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["device_id", "serial_number", "activation_key"],
            properties: [
                new OA\Property(property: "device_id", type: "integer", example: 32558),
                new OA\Property(property: "serial_number", type: "string", example: "lotusdream-1"),
                new OA\Property(property: "activation_key", type: "string", example: "00362772")
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: "Device registered successfully", content: new OA\JsonContent(properties: [new OA\Property(property: "message", type: "string"), new OA\Property(property: "operationID", type: "string")])),
        new OA\Response(response: 400, description: "Registration failed")
    ]
)]

// ZIMRA Fiscalisation - Device Operations (mTLS)
#[OA\Get(
    path: "/zimra/device-config",
    summary: "Get ZIMRA device configuration",
    description: "Retrieve device configuration from ZIMRA using mTLS authentication",
    tags: ["ZIMRA Fiscalisation"],
    responses: [
        new OA\Response(response: 200, description: "Device configuration returned"),
        new OA\Response(response: 400, description: "Error retrieving configuration")
    ]
)]
#[OA\Get(
    path: "/zimra/status",
    summary: "Get ZIMRA device status",
    description: "Get current fiscal day status from ZIMRA using mTLS authentication",
    tags: ["ZIMRA Fiscalisation"],
    responses: [
        new OA\Response(response: 200, description: "Device status returned", content: new OA\JsonContent(properties: [new OA\Property(property: "operationID", type: "string"), new OA\Property(property: "fiscalDayStatus", type: "string", enum: ["FiscalDayClosed", "FiscalDayOpened"])])),
        new OA\Response(response: 400, description: "Error retrieving status")
    ]
)]
#[OA\Post(
    path: "/zimra/open-day",
    summary: "Open ZIMRA fiscal day",
    description: "Open a new fiscal day with ZIMRA. Datetime is automatically set to current local time. Must be called before creating fiscalized transactions.",
    tags: ["ZIMRA Fiscalisation"],
    requestBody: new OA\RequestBody(
        required: false,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "fiscal_day_no", type: "integer", example: 1, description: "Fiscal day number (auto-increments if not provided)")
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: "Fiscal day opened", content: new OA\JsonContent(properties: [new OA\Property(property: "success", type: "boolean"), new OA\Property(property: "data", type: "object"), new OA\Property(property: "fiscal_day", type: "object")])),
        new OA\Response(response: 400, description: "Error opening fiscal day or day already open")
    ]
)]
#[OA\Post(
    path: "/zimra/close-day",
    summary: "Close ZIMRA fiscal day",
    description: "Close the current open fiscal day. Sends accumulated fiscal counters to ZIMRA. Auto-closes at 11 PM if not manually closed.",
    tags: ["ZIMRA Fiscalisation"],
    requestBody: new OA\RequestBody(
        required: false,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "fiscalDayNo", type: "integer", example: 1),
                new OA\Property(property: "fiscalDayCounters", type: "array", items: new OA\Items(type: "object")),
                new OA\Property(property: "receiptCounter", type: "integer", example: 5),
                new OA\Property(property: "fiscalDayDeviceSignature", type: "object")
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: "Fiscal day closed", content: new OA\JsonContent(properties: [new OA\Property(property: "success", type: "boolean"), new OA\Property(property: "data", type: "object"), new OA\Property(property: "fiscal_day", type: "object")])),
        new OA\Response(response: 400, description: "Error closing fiscal day or no open day")
    ]
)]
#[OA\Get(
    path: "/zimra/fiscal-day",
    summary: "Get current fiscal day status",
    description: "Check if a fiscal day is currently open and get its details",
    tags: ["ZIMRA Fiscalisation"],
    responses: [
        new OA\Response(response: 200, description: "Fiscal day status", content: new OA\JsonContent(properties: [new OA\Property(property: "is_open", type: "boolean"), new OA\Property(property: "fiscal_day", type: "object", nullable: true), new OA\Property(property: "message", type: "string", nullable: true)]))
    ]
)]
#[OA\Post(
    path: "/zimra/submit-receipt",
    summary: "Submit fiscal receipt to ZIMRA",
    description: "Submit a fiscal receipt with automatic SHA256 hashing and ECDSA signing. Requires an open fiscal day.",
    tags: ["ZIMRA Fiscalisation"],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["receiptType", "receiptCurrency", "receiptCounter", "receiptGlobalNo", "receiptDate", "receiptLines", "receiptTaxes", "receiptPayments", "receiptTotal"],
            properties: [
                new OA\Property(property: "receiptType", type: "string", enum: ["FiscalInvoice", "CreditNote", "DebitNote"], example: "FiscalInvoice"),
                new OA\Property(property: "receiptCurrency", type: "string", example: "USD"),
                new OA\Property(property: "receiptCounter", type: "integer", example: 1),
                new OA\Property(property: "receiptGlobalNo", type: "integer", example: 1),
                new OA\Property(property: "invoiceNo", type: "string", example: "INV-001"),
                new OA\Property(property: "receiptDate", type: "string", format: "date-time", example: "2026-02-19T12:00:00"),
                new OA\Property(property: "receiptLinesTaxInclusive", type: "boolean", example: true),
                new OA\Property(property: "receiptLines", type: "array", items: new OA\Items(type: "object", properties: [new OA\Property(property: "receiptLineType", type: "string", example: "Sale"), new OA\Property(property: "receiptLineNo", type: "integer", example: 1), new OA\Property(property: "receiptLineHSCode", type: "string", example: "85456852"), new OA\Property(property: "receiptLineName", type: "string", example: "Product"), new OA\Property(property: "receiptLinePrice", type: "number", example: 25.00), new OA\Property(property: "receiptLineQuantity", type: "integer", example: 1), new OA\Property(property: "receiptLineTotal", type: "number", example: 25.00), new OA\Property(property: "taxCode", type: "string", example: "A"), new OA\Property(property: "taxPercent", type: "number", example: 15), new OA\Property(property: "taxID", type: "integer", example: 1)])),
                new OA\Property(property: "receiptTaxes", type: "array", items: new OA\Items(type: "object", properties: [new OA\Property(property: "taxCode", type: "string", example: "A"), new OA\Property(property: "taxPercent", type: "number", example: 15), new OA\Property(property: "taxID", type: "integer", example: 1), new OA\Property(property: "taxAmount", type: "number", example: 3.75), new OA\Property(property: "salesAmountWithTax", type: "number", example: 28.75)])),
                new OA\Property(property: "receiptPayments", type: "array", items: new OA\Items(type: "object", properties: [new OA\Property(property: "moneyTypeCode", type: "string", example: "Cash"), new OA\Property(property: "paymentAmount", type: "number", example: 28.75)])),
                new OA\Property(property: "receiptTotal", type: "number", example: 28.75),
                new OA\Property(property: "receiptPrintForm", type: "string", example: "Receipt48")
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: "Receipt submitted", content: new OA\JsonContent(properties: [new OA\Property(property: "success", type: "boolean"), new OA\Property(property: "data", type: "object", properties: [new OA\Property(property: "receiptID", type: "integer"), new OA\Property(property: "serverDate", type: "string"), new OA\Property(property: "receiptServerSignature", type: "object"), new OA\Property(property: "operationID", type: "string")]), new OA\Property(property: "fiscal_day_no", type: "integer")])),
        new OA\Response(response: 400, description: "Error submitting receipt or no open fiscal day")
    ]
)]
#[OA\Post(
    path: "/zimra/submit-file",
    summary: "Submit fiscal file to ZIMRA",
    description: "Submit a fiscal day file to ZIMRA. Used after closing a fiscal day. Content-Type is text/plain.",
    tags: ["ZIMRA Fiscalisation"],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["header", "content", "footer"],
            properties: [
                new OA\Property(property: "header", type: "object", properties: [new OA\Property(property: "fiscalDayNo", type: "integer", example: 1), new OA\Property(property: "fiscalDayOpened", type: "string", format: "date-time", example: "2026-02-19T08:00:00"), new OA\Property(property: "fileSequence", type: "integer", example: 1)]),
                new OA\Property(property: "content", type: "object", properties: [new OA\Property(property: "receipts", type: "array", items: new OA\Items(type: "object"))]),
                new OA\Property(property: "footer", type: "object", properties: [new OA\Property(property: "fiscalDayCounters", type: "array", items: new OA\Items(type: "object")), new OA\Property(property: "receiptCounter", type: "integer", example: 0), new OA\Property(property: "fiscalDayClosed", type: "string", format: "date-time", example: "2026-02-19T22:00:00")])
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: "File submitted", content: new OA\JsonContent(properties: [new OA\Property(property: "operationID", type: "string")])),
        new OA\Response(response: 400, description: "Error submitting file")
    ]
)]
class OpenApiSpec
{
    // This class exists only to hold OpenAPI annotations
}
