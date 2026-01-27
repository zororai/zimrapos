import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { debitNoteApi } from '../services/api';
import DataTable from '../components/DataTable';
import { toast } from '../utils/toast';

const columns = [
  { key: 'id', label: 'ID' },
  { key: 'invoice_id', label: 'Invoice' },
  { key: 'total', label: 'Total', render: (val) => val ? `$${parseFloat(val).toFixed(2)}` : '—' },
  { key: 'reason', label: 'Reason' },
  { key: 'created_at', label: 'Date', render: (val) => val ? new Date(val).toLocaleDateString() : '—' },
];

export default function DebitNotes() {
  const queryClient = useQueryClient();
  const [searchQuery, setSearchQuery] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['debit-notes', searchQuery],
    queryFn: () => debitNoteApi.search(searchQuery || '*', 100, 0),
    select: (res) => res.data?.searched || [],
  });

  const deleteMutation = useMutation({
    mutationFn: (item) => debitNoteApi.delete([{ id: item.id }]),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['debit-notes'] }); toast.success('Debit note deleted'); },
    onError: (err) => toast.error(err.response?.data?.message || 'Failed'),
  });

  return (
    <DataTable
      title="Debit Notes"
      data={data || []}
      columns={columns}
      loading={isLoading}
      searchQuery={searchQuery}
      onSearchChange={setSearchQuery}
      onDelete={(item) => window.confirm('Delete this debit note?') && deleteMutation.mutate(item)}
    />
  );
}
