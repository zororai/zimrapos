import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { taxApi } from '../services/api';
import DataTable from '../components/DataTable';
import Modal from '../components/Modal';
import { toast } from '../utils/toast';

const columns = [
  { key: 'name', label: 'Name' },
  { key: 'rate', label: 'Rate', render: (val) => val ? `${val}%` : '—' },
  { key: 'type', label: 'Type' },
];

const emptyTax = { name: '', rate: '', type: '' };

export default function Taxes() {
  const queryClient = useQueryClient();
  const [searchQuery, setSearchQuery] = useState('');
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [formData, setFormData] = useState(emptyTax);

  const { data, isLoading } = useQuery({
    queryKey: ['taxes', searchQuery],
    queryFn: () => taxApi.search(searchQuery || '*', 100, 0),
    select: (res) => res.data?.searched || [],
  });

  const createMutation = useMutation({
    mutationFn: (data) => taxApi.create([data]),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['taxes'] }); setModalOpen(false); setFormData(emptyTax); toast.success('Tax created'); },
    onError: (err) => toast.error(err.response?.data?.message || 'Failed'),
  });

  const updateMutation = useMutation({
    mutationFn: (data) => taxApi.update([data]),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['taxes'] }); setModalOpen(false); setEditing(null); setFormData(emptyTax); toast.success('Tax updated'); },
    onError: (err) => toast.error(err.response?.data?.message || 'Failed'),
  });

  const deleteMutation = useMutation({
    mutationFn: (item) => taxApi.delete([{ id: item.id }]),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['taxes'] }); toast.success('Tax deleted'); },
    onError: (err) => toast.error(err.response?.data?.message || 'Failed'),
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    const payload = {
      name: formData.name || '',
      rate: parseFloat(formData.rate) || 0,
      type: formData.type || '',
    };
    editing ? updateMutation.mutate({ ...payload, id: editing.id }) : createMutation.mutate(payload);
  };

  return (
    <div>
      <DataTable
        title="Taxes"
        data={data || []}
        columns={columns}
        loading={isLoading}
        searchQuery={searchQuery}
        onSearchChange={setSearchQuery}
        onAdd={() => { setEditing(null); setFormData(emptyTax); setModalOpen(true); }}
        onEdit={(item) => { setEditing(item); setFormData({ name: item.name || '', rate: item.rate || '', type: item.type || '' }); setModalOpen(true); }}
        onDelete={(item) => window.confirm(`Delete "${item.name}"?`) && deleteMutation.mutate(item)}
        addLabel="Add Tax"
      />
      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title={editing ? 'Edit Tax' : 'Add Tax'}>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Name *</label>
            <input type="text" required value={formData.name} onChange={(e) => setFormData({ ...formData, name: e.target.value })} className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Rate (%)</label>
            <input type="number" step="0.01" value={formData.rate} onChange={(e) => setFormData({ ...formData, rate: e.target.value })} className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Type</label>
            <select value={formData.type} onChange={(e) => setFormData({ ...formData, type: e.target.value })} className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">Select type</option>
              <option value="inclusive">Inclusive</option>
              <option value="exclusive">Exclusive</option>
            </select>
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
