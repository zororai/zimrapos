import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { currencyApi } from '../services/api';
import DataTable from '../components/DataTable';
import Modal from '../components/Modal';
import { toast } from '../utils/toast';

const columns = [
  { key: 'name', label: 'Name' },
  { key: 'code', label: 'Code' },
  { key: 'symbol', label: 'Symbol' },
  { key: 'exchange_rate', label: 'Exchange Rate' },
];

const emptyCurrency = { name: '', code: '', symbol: '', exchange_rate: '' };

export default function Currencies() {
  const queryClient = useQueryClient();
  const [searchQuery, setSearchQuery] = useState('');
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [formData, setFormData] = useState(emptyCurrency);

  const { data, isLoading } = useQuery({
    queryKey: ['currencies', searchQuery],
    queryFn: () => currencyApi.search(searchQuery || '*', 100, 0),
    select: (res) => res.data?.searched || [],
  });

  const createMutation = useMutation({
    mutationFn: (data) => currencyApi.create([data]),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['currencies'] }); setModalOpen(false); setFormData(emptyCurrency); toast.success('Currency created'); },
    onError: (err) => toast.error(err.response?.data?.message || 'Failed'),
  });

  const updateMutation = useMutation({
    mutationFn: (data) => currencyApi.update([data]),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['currencies'] }); setModalOpen(false); setEditing(null); setFormData(emptyCurrency); toast.success('Currency updated'); },
    onError: (err) => toast.error(err.response?.data?.message || 'Failed'),
  });

  const deleteMutation = useMutation({
    mutationFn: (item) => currencyApi.delete([{ id: item.id }]),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['currencies'] }); toast.success('Currency deleted'); },
    onError: (err) => toast.error(err.response?.data?.message || 'Failed'),
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    const payload = {
      name: formData.name || '',
      code: formData.code || '',
      symbol: formData.symbol || '',
      exchange_rate: parseFloat(formData.exchange_rate) || 1,
    };
    editing ? updateMutation.mutate({ ...payload, id: editing.id }) : createMutation.mutate(payload);
  };

  return (
    <div>
      <DataTable
        title="Currencies"
        data={data || []}
        columns={columns}
        loading={isLoading}
        searchQuery={searchQuery}
        onSearchChange={setSearchQuery}
        onAdd={() => { setEditing(null); setFormData(emptyCurrency); setModalOpen(true); }}
        onEdit={(item) => { setEditing(item); setFormData({ name: item.name || '', code: item.code || '', symbol: item.symbol || '', exchange_rate: item.exchange_rate || '' }); setModalOpen(true); }}
        onDelete={(item) => window.confirm(`Delete "${item.name}"?`) && deleteMutation.mutate(item)}
        addLabel="Add Currency"
      />
      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title={editing ? 'Edit Currency' : 'Add Currency'}>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Name *</label>
            <input type="text" required value={formData.name} onChange={(e) => setFormData({ ...formData, name: e.target.value })} className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Code</label>
              <input type="text" value={formData.code} onChange={(e) => setFormData({ ...formData, code: e.target.value })} className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="USD" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Symbol</label>
              <input type="text" value={formData.symbol} onChange={(e) => setFormData({ ...formData, symbol: e.target.value })} className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="$" />
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Exchange Rate</label>
            <input type="number" step="0.0001" value={formData.exchange_rate} onChange={(e) => setFormData({ ...formData, exchange_rate: e.target.value })} className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div className="flex justify-end gap-3 pt-4">
            <button type="button" onClick={() => setModalOpen(false)} className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
            <button type="submit" disabled={createMutation.isPending || updateMutation.isPending} className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">Save</button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
