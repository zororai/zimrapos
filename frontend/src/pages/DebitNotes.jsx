import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { debitNoteApi, receiptApi } from '../services/api';
import DataTable from '../components/DataTable';
import { toast } from '../utils/toast';

const columns = [
  { key: 'id', label: 'ID' },
  { key: 'invoice_no', label: 'Invoice No' },
  { key: 'receipt_total', label: 'Total', render: (val) => val ? `$${parseFloat(val).toFixed(2)}` : '—' },
  { key: 'receipt_notes', label: 'Reason' },
  { key: 'validation_code', label: 'Status', render: (val) => val || '—' },
  { key: 'fdms_receipt_id', label: 'FDMS ID' },
  { key: 'created_at', label: 'Date', render: (val) => val ? new Date(val).toLocaleDateString() : '—' },
];

export default function DebitNotes() {
  const queryClient = useQueryClient();
  const [searchQuery, setSearchQuery] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [selectedReceipt, setSelectedReceipt] = useState(null);
  const [formData, setFormData] = useState({
    invoice_id: '',
    reason: '',
    lineItems: [], // Line items from original receipt with quantities to debit
  });

  // Fetch debit notes (receipts with type = DebitNote)
  const { data, isLoading } = useQuery({
    queryKey: ['debit-notes', searchQuery],
    queryFn: () => receiptApi.getAll(),
    select: (res) => (res.data?.receipts || []).filter(r => r.receipt_type === 'DebitNote'),
  });

  // Fetch fiscalized invoices (receipts with type = FiscalInvoice)
  const { data: receipts } = useQuery({
    queryKey: ['receipts'],
    queryFn: () => receiptApi.getAll(),
    select: (res) => (res.data?.receipts || []).filter(r => 
      r.receipt_type === 'FiscalInvoice' && 
      r.fdms_receipt_id && 
      !r.is_voided
    ),
  });

  // When receipt is selected, populate line items from original receipt
  const handleReceiptSelect = (receiptId) => {
    const receipt = receipts?.find(r => r.id === parseInt(receiptId));
    setSelectedReceipt(receipt);
    
    if (receipt && receipt.receipt_lines) {
      // Initialize line items with 0 debit quantity
      const lineItems = receipt.receipt_lines.map((line, index) => ({
        line_index: index,
        receiptLineName: line.receiptLineName,
        receiptLinePrice: parseFloat(line.receiptLinePrice || 0),
        originalQuantity: parseFloat(line.receiptLineQuantity || 0),
        debitQuantity: 0, // User will set this
        taxPercent: line.taxPercent,
        taxID: line.taxID,
        taxCode: line.taxCode,
        receiptLineHSCode: line.receiptLineHSCode,
      }));
      
      setFormData(prev => ({
        ...prev,
        invoice_id: receipt.invoice_no,
        lineItems: lineItems,
      }));
    } else {
      setFormData(prev => ({
        ...prev,
        invoice_id: receiptId,
        lineItems: [],
      }));
    }
  };

  const createMutation = useMutation({
    mutationFn: (payload) => debitNoteApi.create(payload),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ['debit-notes'] });
      queryClient.invalidateQueries({ queryKey: ['receipts'] });
      if (response.data?.error) {
        toast.error(response.data.message || 'Debit note creation failed');
      } else {
        toast.success('Debit note created and submitted to ZIMRA');
      }
      setShowForm(false);
      setSelectedReceipt(null);
      setFormData({ invoice_id: '', reason: '', lineItems: [] });
    },
    onError: (err) => toast.error(err.response?.data?.message || 'Failed to create debit note'),
  });

  const handleLineItemChange = (index, quantity) => {
    setFormData(prev => ({
      ...prev,
      lineItems: prev.lineItems.map((item, i) => 
        i === index ? { ...item, debitQuantity: Math.max(0, parseInt(quantity) || 0) } : item
      )
    }));
  };

  const calculateDebitTotal = () => {
    return formData.lineItems.reduce((total, item) => {
      return total + (item.receiptLinePrice * item.debitQuantity);
    }, 0);
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!selectedReceipt) {
      toast.error('Please select an invoice');
      return;
    }
    
    // Filter only items with debit quantity > 0
    const itemsToDebit = formData.lineItems.filter(item => item.debitQuantity > 0);
    if (itemsToDebit.length === 0) {
      toast.error('Please set debit quantity for at least one item');
      return;
    }

    if (!formData.reason || formData.reason.length < 10) {
      toast.error('Please provide a reason (minimum 10 characters)');
      return;
    }

    // Build products array for backend
    const products = itemsToDebit.map(item => ({
      line_index: item.line_index,
      quantity: item.debitQuantity,
      name: item.receiptLineName,
      price: item.receiptLinePrice,
      taxID: item.taxID,
      taxPercent: item.taxPercent,
      taxCode: item.taxCode,
      receiptLineHSCode: item.receiptLineHSCode,
    }));

    createMutation.mutate({
      invoice_id: selectedReceipt.invoice_no,
      reason: formData.reason,
      products: products,
    });
  };

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h1 className="text-2xl font-bold">Debit Notes</h1>
        <button
          onClick={() => setShowForm(!showForm)}
          className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
        >
          {showForm ? 'Cancel' : '+ Create Debit Note'}
        </button>
      </div>

      {showForm && (
        <div className="bg-white p-6 rounded-lg shadow">
          <h2 className="text-xl font-semibold mb-4">Create Debit Note</h2>
          <p className="text-sm text-gray-600 mb-4">
            Create a debit note to add additional charges to a previous invoice. 
            The debit note will be automatically submitted to ZIMRA and linked to the original receipt.
          </p>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium mb-1">Select Original Invoice *</label>
              <select
                value={selectedReceipt?.id || ''}
                onChange={(e) => handleReceiptSelect(e.target.value)}
                className="w-full px-3 py-2 border rounded-lg"
                required
              >
                <option value="">Select the original invoice to debit</option>
                {receipts?.map(receipt => (
                  <option key={receipt.id} value={receipt.id}>
                    {receipt.invoice_no} - {receipt.receipt_currency} {parseFloat(receipt.receipt_total).toFixed(2)} ({new Date(receipt.created_at).toLocaleDateString()})
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium mb-1">Reason for Debit Note *</label>
              <textarea
                value={formData.reason}
                onChange={(e) => setFormData({ ...formData, reason: e.target.value })}
                className="w-full px-3 py-2 border rounded-lg"
                rows="2"
                required
                minLength={10}
                placeholder="e.g., Price adjustment for previously invoiced goods."
              />
              {formData.reason && (
                <p className={`text-xs mt-1 ${formData.reason.length >= 10 ? 'text-green-600' : 'text-red-600'}`}>
                  {formData.reason.length >= 10 ? '✓' : '✗'} {formData.reason.length} characters (minimum 10)
                </p>
              )}
            </div>

            {selectedReceipt && formData.lineItems.length > 0 && (
              <div>
                <label className="block text-sm font-medium mb-2">Line Items to Debit</label>
                <div className="border rounded-lg overflow-hidden">
                  <table className="w-full text-sm">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-3 py-2 text-left">Item</th>
                        <th className="px-3 py-2 text-right">Price</th>
                        <th className="px-3 py-2 text-center">Orig Qty</th>
                        <th className="px-3 py-2 text-center">Debit Qty</th>
                        <th className="px-3 py-2 text-right">Debit Amount</th>
                      </tr>
                    </thead>
                    <tbody>
                      {formData.lineItems.map((item, index) => (
                        <tr key={index} className="border-t">
                          <td className="px-3 py-2">{item.receiptLineName}</td>
                          <td className="px-3 py-2 text-right">{selectedReceipt.receipt_currency} {item.receiptLinePrice.toFixed(2)}</td>
                          <td className="px-3 py-2 text-center">{item.originalQuantity}</td>
                          <td className="px-3 py-2 text-center">
                            <input
                              type="number"
                              min="0"
                              step="0.01"
                              value={item.debitQuantity}
                              onChange={(e) => handleLineItemChange(index, e.target.value)}
                              className="w-20 px-2 py-1 border rounded text-center"
                              placeholder="0"
                            />
                          </td>
                          <td className="px-3 py-2 text-right font-medium">
                            {item.debitQuantity > 0 ? (
                              <span className="text-green-600">
                                +{selectedReceipt.receipt_currency} {(item.receiptLinePrice * item.debitQuantity).toFixed(2)}
                              </span>
                            ) : '—'}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                    <tfoot className="bg-gray-50 font-medium">
                      <tr className="border-t">
                        <td colSpan="4" className="px-3 py-2 text-right">Total Debit Amount:</td>
                        <td className="px-3 py-2 text-right text-green-600">
                          +{selectedReceipt.receipt_currency} {calculateDebitTotal().toFixed(2)}
                        </td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>
            )}

            {selectedReceipt && formData.lineItems.length === 0 && (
              <div className="p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-800">
                No line items found on this receipt. The receipt may not have been stored with line item details.
              </div>
            )}

            <div className="flex gap-2 pt-4">
              <button
                type="submit"
                disabled={createMutation.isPending || !selectedReceipt || calculateDebitTotal() === 0}
                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
              >
                {createMutation.isPending ? 'Creating...' : 'Create Debit Note'}
              </button>
              <button
                type="button"
                onClick={() => {
                  setShowForm(false);
                  setSelectedReceipt(null);
                  setFormData({ invoice_id: '', reason: '', lineItems: [] });
                }}
                className="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400"
              >
                Cancel
              </button>
            </div>
          </form>
        </div>
      )}

      <DataTable
        title=""
        data={data || []}
        columns={columns}
        loading={isLoading}
        searchQuery={searchQuery}
        onSearchChange={setSearchQuery}
      />
    </div>
  );
}
