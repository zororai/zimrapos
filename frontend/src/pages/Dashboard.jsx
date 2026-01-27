import { useQuery } from '@tanstack/react-query';
import { Package, Users, ShoppingCart, FileText, DollarSign, TrendingUp } from 'lucide-react';
import { productApi, customerApi, saleApi, invoiceApi } from '../services/api';

const StatCard = ({ title, value, icon: Icon, color }) => (
  <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <div className="flex items-center justify-between">
      <div>
        <p className="text-sm font-medium text-gray-500">{title}</p>
        <p className="mt-1 text-3xl font-bold text-gray-900">{value}</p>
      </div>
      <div className={`p-3 rounded-xl ${color}`}>
        <Icon className="h-6 w-6 text-white" />
      </div>
    </div>
  </div>
);

export default function Dashboard() {
  const { data: products } = useQuery({
    queryKey: ['products-count'],
    queryFn: () => productApi.search('*', 1, 0),
    select: (res) => res.data?.total || 0,
  });

  const { data: customers } = useQuery({
    queryKey: ['customers-count'],
    queryFn: () => customerApi.search('*', 1, 0),
    select: (res) => res.data?.total || 0,
  });

  const { data: sales } = useQuery({
    queryKey: ['sales-count'],
    queryFn: () => saleApi.search('*', 1, 0),
    select: (res) => res.data?.total || 0,
  });

  const { data: invoices } = useQuery({
    queryKey: ['invoices-count'],
    queryFn: () => invoiceApi.search('*', 1, 0),
    select: (res) => res.data?.total || 0,
  });

  return (
    <div className="space-y-8">
      <div>
        <h2 className="text-2xl font-bold text-gray-900">Welcome to Panier POS</h2>
        <p className="mt-1 text-gray-500">Here&apos;s an overview of your business</p>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <StatCard
          title="Total Products"
          value={products ?? '—'}
          icon={Package}
          color="bg-indigo-500"
        />
        <StatCard
          title="Total Customers"
          value={customers ?? '—'}
          icon={Users}
          color="bg-emerald-500"
        />
        <StatCard
          title="Total Sales"
          value={sales ?? '—'}
          icon={ShoppingCart}
          color="bg-amber-500"
        />
        <StatCard
          title="Total Invoices"
          value={invoices ?? '—'}
          icon={FileText}
          color="bg-rose-500"
        />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
          <div className="grid grid-cols-2 gap-4">
            <a
              href="/sales"
              className="flex items-center gap-3 p-4 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 transition-colors"
            >
              <ShoppingCart className="h-5 w-5 text-indigo-600" />
              <span className="font-medium text-gray-900">New Sale</span>
            </a>
            <a
              href="/invoices"
              className="flex items-center gap-3 p-4 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 transition-colors"
            >
              <FileText className="h-5 w-5 text-indigo-600" />
              <span className="font-medium text-gray-900">New Invoice</span>
            </a>
            <a
              href="/products"
              className="flex items-center gap-3 p-4 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 transition-colors"
            >
              <Package className="h-5 w-5 text-indigo-600" />
              <span className="font-medium text-gray-900">Add Product</span>
            </a>
            <a
              href="/customers"
              className="flex items-center gap-3 p-4 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 transition-colors"
            >
              <Users className="h-5 w-5 text-indigo-600" />
              <span className="font-medium text-gray-900">Add Customer</span>
            </a>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">System Status</h3>
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <span className="text-gray-600">API Connection</span>
              <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
                <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                Connected
              </span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-gray-600">Database Sync</span>
              <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
                <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                Active
              </span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-gray-600">ZIMRA Fiscalisation</span>
              <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                <span className="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                Available
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
