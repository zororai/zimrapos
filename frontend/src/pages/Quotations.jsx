import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { quotationApi } from '../services/api';
import DataTable from '../components/DataTable';
import { toast } from '../utils/toast';

const columns = [
  { key: 'id', label: 'ID' },
  { key: 'customer_name', label: 'Customer' },
  { key: 'total', label: 'Total', render: (val) => val ? `$${parseFloat(val).toFixed(2)}` : '—' },
  { key: 'status', label: 'Status' },
  { key: 'valid_until', label: 'Valid Until', render: (val) => val ? new Date(val).toLocaleDateString() : '—' },
];

export default function Quotations() {
  const queryClient = useQueryClient();
  const [searchQuery, setSearchQuery] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['quotations', searchQuery],
    queryFn: () => quotationApi.search(searchQuery || '*', 100, 0),
    select: (res) => res.data?.searched || [],
  });

  const deleteMutation = useMutation({
    mutationFn: (item) => quotationApi.delete([{ id: item.id }]),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['quotations'] }); toast.success('Quotation deleted'); },
    onError: (err) => toast.error(err.response?.data?.message || 'Failed'),
  });

  return (
    <DataTable
      title="Quotations"
      data={data || []}
      columns={columns}
      loading={isLoading}
      searchQuery={searchQuery}
      onSearchChange={setSearchQuery}
      onDelete={(item) => window.confirm('Delete this quotation?') && deleteMutation.mutate(item)}
    />
  );
}
