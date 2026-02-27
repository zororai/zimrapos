import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { creditNoteApi, saleApi, productApi } from '../services/api';
import DataTable from '../components/DataTable';
import { toast } from '../utils/toast';

const columns = [
  { key: 'id', label: 'ID' },
  { key: 'sale_id', label: 'Sale ID' },
  { key: 'total', label: 'Total', render: (val) => val ? `$${parseFloat(val).toFixed(2)}` : '—' },
  { key: 'reason', label: 'Reason' },
  { key: 'zimra_fiscalized', label: 'ZIMRA', render: (val) => val ? '✓' : '—' },
  { key: 'zimra_fiscal_code', label: 'Fiscal Code' },
  { key: 'created_at', label: 'Date', render: (val) => val ? new Date(val).toLocaleDateString() : '—' },
];

export default function CreditNotes() {
  const queryClient = useQueryClient();
  const [searchQuery, setSearchQuery] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [formData, setFormData] = useState({
    sale_id: '',
    reason: '',
    products: [],
    zimra_fiscalize: false,
  });

  const { data, isLoading } = useQuery({
    queryKey: ['credit-notes', searchQuery],
    queryFn: () => creditNoteApi.search(searchQuery || '*', 100, 0),
    select: (res) => res.data?.searched || [],
  });

  const { data: sales } = useQuery({
    queryKey: ['sales'],
    queryFn: () => saleApi.search('*', 100, 0),
    select: (res) => res.data?.searched || [],
  });

  const { data: products } = useQuery({
    queryKey: ['products'],
    queryFn: () => productApi.search('*', 100, 0),
    select: (res) => res.data?.searched || [],
  });

  const createMutation = useMutation({
    mutationFn: (data) => creditNoteApi.create({ data: [data], zimra_fiscalize: data.zimra_fiscalize }),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ['credit-notes'] });
      const hasErrors = response.data?.zimra_errors && response.data.zimra_errors.length > 0;
      if (hasErrors) {
        toast.error(`Credit note created but ZIMRA submission failed: ${JSON.stringify(response.data.zimra_errors)}`);
      } else {
        toast.success(formData.zimra_fiscalize ? 'Credit note created and submitted to ZIMRA' : 'Credit note created');
      }
      setShowForm(false);
      setFormData({ sale_id: '', reason: '', products: [], zimra_fiscalize: false });
    },
    onError: (err) => toast.error(err.response?.data?.message || 'Failed to create credit note'),
  });

  const deleteMutation = useMutation({
    mutationFn: (item) => creditNoteApi.delete([{ id: item.id }]),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['credit-notes'] }); toast.success('Credit note deleted'); },
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
    if (!formData.sale_id) {
      toast.error('Please select a sale');
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
        <h1 className="text-2xl font-bold">Credit Notes</h1>
        <button
          onClick={() => setShowForm(!showForm)}
          className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
        >
          {showForm ? 'Cancel' : '+ Create Credit Note'}
        </button>
      </div>

      {showForm && (
        <div className="bg-white p-6 rounded-lg shadow">
          <h2 className="text-xl font-semibold mb-4">Create Credit Note</h2>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium mb-1">Sale</label>
              <select
                value={formData.sale_id}
                onChange={(e) => setFormData({ ...formData, sale_id: e.target.value })}
                className="w-full px-3 py-2 border rounded-lg"
                required
              >
                <option value="">Select a sale</option>
                {sales?.map(sale => (
                  <option key={sale.id} value={sale.id}>
                    {sale.id} - ${parseFloat(sale.total).toFixed(2)} ({new Date(sale.created_at).toLocaleDateString()})
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
                placeholder="e.g., Customer return - damaged goods"
              />
            </div>

            <div>
              <div className="flex justify-between items-center mb-2">
                <label className="block text-sm font-medium">Products to Credit</label>
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
                {createMutation.isPending ? 'Creating...' : 'Create Credit Note'}
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
        onDelete={(item) => window.confirm('Delete this credit note?') && deleteMutation.mutate(item)}
      />
    </div>
  );
}
