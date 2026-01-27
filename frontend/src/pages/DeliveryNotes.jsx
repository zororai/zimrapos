import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { deliveryNoteApi } from '../services/api';
import DataTable from '../components/DataTable';
import { toast } from '../utils/toast';

const columns = [
  { key: 'id', label: 'ID' },
  { key: 'customer_name', label: 'Customer' },
  { key: 'status', label: 'Status' },
  { key: 'delivery_date', label: 'Delivery Date', render: (val) => val ? new Date(val).toLocaleDateString() : '—' },
  { key: 'created_at', label: 'Created', render: (val) => val ? new Date(val).toLocaleDateString() : '—' },
];

export default function DeliveryNotes() {
  const queryClient = useQueryClient();
  const [searchQuery, setSearchQuery] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['delivery-notes', searchQuery],
    queryFn: () => deliveryNoteApi.search(searchQuery || '*', 100, 0),
    select: (res) => res.data?.searched || [],
  });

  const deleteMutation = useMutation({
    mutationFn: (item) => deliveryNoteApi.delete([{ id: item.id }]),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['delivery-notes'] }); toast.success('Delivery note deleted'); },
    onError: (err) => toast.error(err.response?.data?.message || 'Failed'),
  });

  return (
    <DataTable
      title="Delivery Notes"
      data={data || []}
      columns={columns}
      loading={isLoading}
      searchQuery={searchQuery}
      onSearchChange={setSearchQuery}
      onDelete={(item) => window.confirm('Delete this delivery note?') && deleteMutation.mutate(item)}
    />
  );
}
