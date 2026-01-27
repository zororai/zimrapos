import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { saleApi } from '../services/api';
import DataTable from '../components/DataTable';
import { toast } from '../utils/toast';
import { Download, XCircle } from 'lucide-react';

const columns = [
  { key: 'id', label: 'ID' },
  { 
    key: 'total', 
    label: 'Total',
    render: (val) => val ? `$${parseFloat(val).toFixed(2)}` : '—'
  },
  { key: 'status', label: 'Status' },
  { key: 'payment_method', label: 'Payment' },
  { 
    key: 'created_at', 
    label: 'Date',
    render: (val) => val ? new Date(val).toLocaleDateString() : '—'
  },
];

export default function Sales() {
  const queryClient = useQueryClient();
  const [searchQuery, setSearchQuery] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['sales', searchQuery],
    queryFn: () => saleApi.search(searchQuery || '*', 100, 0),
    select: (res) => res.data?.searched || [],
  });

  const voidMutation = useMutation({
    mutationFn: (sale) => saleApi.void([{ id: sale.id }]),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales'] });
      toast.success('Sale voided successfully');
    },
    onError: (err) => toast.error(err.response?.data?.message || 'Failed to void sale'),
  });

  const handleVoid = (sale) => {
    if (window.confirm('Are you sure you want to void this sale?')) {
      voidMutation.mutate(sale);
    }
  };

  const handleDownload = async (sale) => {
    try {
      const response = await saleApi.download(sale.id);
      toast.success('Download started');
    } catch (err) {
      toast.error('Failed to download receipt');
    }
  };

  return (
    <div>
      <div className="bg-white rounded-xl shadow-sm border border-gray-200">
        <div className="p-4 border-b border-gray-200">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 className="text-lg font-semibold text-gray-900">Sales</h2>
            <div className="relative">
              <input
                type="text"
                placeholder="Search..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-4 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
              />
            </div>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="bg-gray-50">
                {columns.map((col) => (
                  <th
                    key={col.key}
                    className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                  >
                    {col.label}
                  </th>
                ))}
                <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200">
              {isLoading ? (
                <tr>
                  <td colSpan={columns.length + 1} className="px-4 py-12 text-center text-gray-500">
                    Loading...
                  </td>
                </tr>
              ) : (data || []).length === 0 ? (
                <tr>
                  <td colSpan={columns.length + 1} className="px-4 py-12 text-center text-gray-500">
                    No sales found
                  </td>
                </tr>
              ) : (
                (data || []).map((row, idx) => (
                  <tr key={row.id || idx} className="hover:bg-gray-50">
                    {columns.map((col) => (
                      <td key={col.key} className="px-4 py-3 text-sm text-gray-900">
                        {col.render ? col.render(row[col.key], row) : row[col.key]}
                      </td>
                    ))}
                    <td className="px-4 py-3 text-right">
                      <div className="flex items-center justify-end gap-2">
                        <button
                          onClick={() => handleDownload(row)}
                          className="p-1.5 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
                          title="Download Receipt"
                        >
                          <Download className="h-4 w-4" />
                        </button>
                        {row.status !== 'voided' && (
                          <button
                            onClick={() => handleVoid(row)}
                            className="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                            title="Void Sale"
                          >
                            <XCircle className="h-4 w-4" />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
