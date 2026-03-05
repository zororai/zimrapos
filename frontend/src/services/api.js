import axios from 'axios';

const api = axios.create({
  baseURL: '/api/v1',
  headers: {
    'Content-Type': 'application/json',
  },
});

// Products
export const productApi = {
  search: (query = '*', limit = 50, skip = 0) =>
    api.post('/product/search', { data: { query, limit, skip } }),
  create: (data, overwrite_duplicates = true) =>
    api.post('/product/create', { data, overwrite_duplicates }),
  update: (data) =>
    api.put('/product/update', { data }),
  delete: (data) =>
    api.post('/product/delete', { data }),
};

// Stock
export const stockApi = {
  add: (data) => api.post('/stock/add', { data }),
  subtract: (data) => api.post('/stock/subtract', { data }),
};

// Customers
export const customerApi = {
  search: (query = '*', limit = 50, skip = 0) =>
    api.post('/customer/search', { data: { query, limit, skip } }),
  create: (data, overwrite_duplicates = true) =>
    api.post('/customer/create', { data, overwrite_duplicates }),
  update: (data) =>
    api.put('/customer/update', { data }),
  delete: (data) =>
    api.post('/customer/delete', { data }),
};

// Suppliers
export const supplierApi = {
  search: (query = '*', limit = 50, skip = 0) =>
    api.post('/supplier/search', { data: { query, limit, skip } }),
  create: (data, overwrite_duplicates = true) =>
    api.post('/supplier/create', { data, overwrite_duplicates }),
  update: (data) =>
    api.put('/supplier/update', { data }),
  delete: (data) =>
    api.post('/supplier/delete', { data }),
};

// Taxes
export const taxApi = {
  search: (query = '*', limit = 50, skip = 0) =>
    api.post('/tax/search', { data: { query, limit, skip } }),
  create: (data, overwrite_duplicates = true) =>
    api.post('/tax/create', { data, overwrite_duplicates }),
  update: (data) =>
    api.put('/tax/update', { data }),
  delete: (data) =>
    api.post('/tax/delete', { data }),
};

// Currencies
export const currencyApi = {
  search: (query = '*', limit = 50, skip = 0) =>
    api.post('/currency/search', { data: { query, limit, skip } }),
  create: (data, overwrite_duplicates = true) =>
    api.post('/currency/create', { data, overwrite_duplicates }),
  update: (data) =>
    api.put('/currency/update', { data }),
  delete: (data) =>
    api.post('/currency/delete', { data }),
};

// Sales
export const saleApi = {
  search: (query = '*', limit = 50, skip = 0) =>
    api.post('/sale/search', { data: { query, limit, skip } }),
  create: (data) =>
    api.post('/sale/create', { data }),
  void: (data) =>
    api.post('/sale/void', { data }),
  download: (id) =>
    api.get(`/sale/download?id=${id}`),
};

// Invoices
export const invoiceApi = {
  search: (query = '*', limit = 50, skip = 0) =>
    api.post('/invoice/search', { data: { query, limit, skip } }),
  create: (data) =>
    api.post('/invoice/create', { data }),
  update: (data) =>
    api.put('/invoice/update', { data }),
  delete: (data) =>
    api.post('/invoice/delete', { data }),
  convertToSale: (data) =>
    api.post('/invoice/convert-to-sale', { data }),
  download: (id) =>
    api.get(`/invoice/download?id=${id}`),
};

// Quotations
export const quotationApi = {
  search: (query = '*', limit = 50, skip = 0) =>
    api.post('/quotation/search', { data: { query, limit, skip } }),
  create: (data) =>
    api.post('/quotation/create', { data }),
  update: (data) =>
    api.put('/quotation/update', { data }),
  delete: (data) =>
    api.post('/quotation/delete', { data }),
  convertToInvoice: (data) =>
    api.post('/quotation/convert-to-invoice', { data }),
  convertToSale: (data) =>
    api.post('/quotation/convert-to-sale', { data }),
  download: (id) =>
    api.get(`/quotation/download?id=${id}`),
};

// Debit Notes
export const debitNoteApi = {
  search: (query = '*', limit = 50, skip = 0) =>
    api.post('/debit-note/search', { data: { query, limit, skip } }),
  create: (data) =>
    api.post('/debit-note/create', { data }),
  delete: (data) =>
    api.post('/debit-note/delete', { data }),
  download: (id) =>
    api.get(`/debit-note/download?id=${id}`),
};

// Credit Notes
export const creditNoteApi = {
  search: (query = '*', limit = 50, skip = 0) =>
    api.post('/credit-note/search', { data: { query, limit, skip } }),
  create: (payload) =>
    api.post('/credit-note/create', payload),
  delete: (data) =>
    api.post('/credit-note/delete', { data }),
  download: (id) =>
    api.get(`/credit-note/download?id=${id}`),
};

// Delivery Notes
export const deliveryNoteApi = {
  search: (query = '*', limit = 50, skip = 0) =>
    api.post('/delivery-note/search', { data: { query, limit, skip } }),
  create: (data) =>
    api.post('/delivery-note/create', { data }),
  delete: (data) =>
    api.post('/delivery-note/delete', { data }),
  download: (id) =>
    api.get(`/delivery-note/download?id=${id}`),
};

// Receipts (fiscalized documents)
export const receiptApi = {
  getAll: () => api.get('/zimra/receipts'),
  getById: (id) => api.get(`/zimra/receipts/${id}`),
};

// ZIMRA
export const zimraApi = {
  openDay: () => api.get('/zimra/open-day'),
  closeDay: () => api.get('/zimra/close-day'),
  fiscalize: (data) => api.post('/zimra/fiscalize', data),
};

export default api;
