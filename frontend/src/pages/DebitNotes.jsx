import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { debitNoteApi, invoiceApi, productApi } from '../services/api';
import DataTable from '../components/DataTable';
import { toast } from '../utils/toast';

const columns = [
  { key: 'id', label: 'ID' },
  { key: 'invoice_id', label: 'Invoice ID' },
  { key: 'total', label: 'Total', render: (val) => val ? `$${parseFloat(val).toFixed(2)}` : '—' },
  { key: 'reason', label: 'Reason' },
  { key: 'zimra_fiscalized', label: 'ZIMRA', render: (val) => val ? '✓' : '—' },
  { key: 'zimra_fiscal_code', label: 'Fiscal Code' },
  { key: 'created_at', label: 'Date', render: (val) => val ? new Date(val).toLocaleDateString() : '—' },
];

export default function DebitNotes() {
  const queryClient = useQueryClient();
  const [searchQuery, setSearchQuery] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [formData, setFormData] = useState({
    invoice_id: '',
    reason: '',
    products: [],
    zimra_fiscalize: false,
  });

  const { data, isLoading } = useQuery({
    queryKey: ['debit-notes', searchQuery],
    queryFn: () => debitNoteApi.search(searchQuery || '*', 100, 0),
    select: (res) => res.data?.searched || [],
  });

  const { data: invoices } = useQuery({
    queryKey: ['invoices'],
    queryFn: () => invoiceApi.search('*', 100, 0),
    select: (res) => res.data?.searched || [],
  });

  const { data: products } = useQuery({
    queryKey: ['products'],
    queryFn: () => productApi.search('*', 100, 0),
    select: (res) => res.data?.searched || [],
  });

  const createMutation = useMutation({
    mutationFn: (data) => debitNoteApi.create({ data: [data], zimra_fiscalize: data.zimra_fiscalize }),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ['debit-notes'] });
      const hasErrors = response.data?.zimra_errors && response.data.zimra_errors.length > 0;
      if (hasErrors) {
        toast.error(`Debit note created but ZIMRA submission failed: ${JSON.stringify(response.data.zimra_errors)}`);
      } else {
        toast.success(formData.zimra_fiscalize ? 'Debit note created and submitted to ZIMRA' : 'Debit note created');
      }
      setShowForm(false);
      setFormData({ invoice_id: '', reason: '', products: [], zimra_fiscalize: false });
    },
    onError: (err) => toast.error(err.response?.data?.message || 'Failed to create debit note'),
  });

  const deleteMutation = useMutation({
    mutationFn: (item) => debitNoteApi.delete([{ id: item.id }]),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['debit-notes'] }); toast.success('Debit note deleted'); },
    onError: (err) => toast.error(err.response?.data?.message || 'Failed'),
  });

  const handleAddProduct = () => {
    setFormData(prev => ({
      ...prev,
      products: [...prev.products, { id: '', quantity: 1 }]
    }));
  };

  const handleRemoveProduct = (index) => {
    setFormData(prev => ({
      ...prev,
      products: prev.products.filter((_, i) => i !== index)
    }));
  };

  const handleProductChange = (index, field, value) => {
    setFormData(prev => ({
      ...prev,
      products: prev.products.map((p, i) => i === index ? { ...p, [field]: value } : p)
    }));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!formData.invoice_id) {
      toast.error('Please select an invoice');
      return;
    }
    if (formData.products.length === 0) {
      toast.error('Please add at least one product');
      return;
    }
    createMutation.mutate(formData);
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
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium mb-1">Invoice</label>
              <select
                value={formData.invoice_id}
                onChange={(e) => setFormData({ ...formData, invoice_id: e.target.value })}
                className="w-full px-3 py-2 border rounded-lg"
                required
              >
                <option value="">Select an invoice</option>
                {invoices?.map(invoice => (
                  <option key={invoice.id} value={invoice.id}>
                    {invoice.invoice_number} - ${parseFloat(invoice.total).toFixed(2)} ({new Date(invoice.created_at).toLocaleDateString()})
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium mb-1">Reason</label>
              <textarea
                value={formData.reason}
                onChange={(e) => setFormData({ ...formData, reason: e.target.value })}
                className="w-full px-3 py-2 border rounded-lg"
                rows="3"
                required
                placeholder="e.g., Additional charges - late payment fee"
              />
            </div>

            <div>
              <div className="flex justify-between items-center mb-2">
                <label className="block text-sm font-medium">Products to Debit</label>
                <button
                  type="button"
                  onClick={handleAddProduct}
                  className="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700"
                >
                  + Add Product
                </button>
              </div>
              {formData.products.map((product, index) => (
                <div key={index} className="flex gap-2 mb-2">
                  <select
                    value={product.id}
                    onChange={(e) => handleProductChange(index, 'id', e.target.value)}
                    className="flex-1 px-3 py-2 border rounded-lg"
                    required
                  >
                    <option value="">Select product</option>
                    {products?.map(p => (
                      <option key={p.id} value={p.id}>
                        {p.name} - ${parseFloat(p.selling_price).toFixed(2)}
                      </option>
                    ))}
                  </select>
                  <input
                    type="number"
                    min="1"
                    value={product.quantity}
                    onChange={(e) => handleProductChange(index, 'quantity', parseInt(e.target.value))}
                    className="w-24 px-3 py-2 border rounded-lg"
                    placeholder="Qty"
                    required
                  />
                  <button
                    type="button"
                    onClick={() => handleRemoveProduct(index)}
                    className="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                  >
                    Remove
                  </button>
                </div>
              ))}
            </div>

            <div className="flex items-center gap-2">
              <input
                type="checkbox"
                id="zimra_fiscalize"
                checked={formData.zimra_fiscalize}
                onChange={(e) => setFormData({ ...formData, zimra_fiscalize: e.target.checked })}
                className="w-4 h-4"
              />
              <label htmlFor="zimra_fiscalize" className="text-sm font-medium">
                Submit to ZIMRA (Fiscalize)
              </label>
            </div>

            <div className="flex gap-2">
              <button
                type="submit"
                disabled={createMutation.isPending}
                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
              >
                {createMutation.isPending ? 'Creating...' : 'Create Debit Note'}
              </button>
              <button
                type="button"
                onClick={() => setShowForm(false)}
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
        onDelete={(item) => window.confirm('Delete this debit note?') && deleteMutation.mutate(item)}
      />
    </div>
  );
}
