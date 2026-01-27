import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { invoiceApi } from '../services/api';
import DataTable from '../components/DataTable';
import { toast } from '../utils/toast';
import { Download, ShoppingCart } from 'lucide-react';

const columns = [
  { key: 'id', label: 'ID' },
  { key: 'customer_name', label: 'Customer' },
  { 
    key: 'total', 
    label: 'Total',
    render: (val) => val ? `$${parseFloat(val).toFixed(2)}` : '—'
  },
  { key: 'status', label: 'Status' },
  { 
    key: 'due_date', 
    label: 'Due Date',
    render: (val) => val ? new Date(val).toLocaleDateString() : '—'
  },
];

export default function Invoices() {
  const queryClient = useQueryClient();
  const [searchQuery, setSearchQuery] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['invoices', searchQuery],
    queryFn: () => invoiceApi.search(searchQuery || '*', 100, 0),
    select: (res) => res.data?.searched || [],
  });

  const convertMutation = useMutation({
    mutationFn: (invoice) => invoiceApi.convertToSale([{ id: invoice.id }]),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      queryClient.invalidateQueries({ queryKey: ['sales'] });
      toast.success('Invoice converted to sale');
    },
    onError: (err) => toast.error(err.response?.data?.message || 'Failed to convert'),
  });

  const deleteMutation = useMutation({
    mutationFn: (invoice) => invoiceApi.delete([{ id: invoice.id }]),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      toast.success('Invoice deleted');
    },
    onError: (err) => toast.error(err.response?.data?.message || 'Failed to delete'),
  });

  const handleConvert = (invoice) => {
    if (window.confirm('Convert this invoice to a sale?')) {
      convertMutation.mutate(invoice);
    }
  };

  const handleDelete = (invoice) => {
    if (window.confirm('Delete this invoice?')) {
      deleteMutation.mutate(invoice);
    }
  };

  return (
    <div>
      <DataTable
        title="Invoices"
        data={data || []}
        columns={columns}
        loading={isLoading}
        searchQuery={searchQuery}
        onSearchChange={setSearchQuery}
        onDelete={handleDelete}
      />
    </div>
  );
}
