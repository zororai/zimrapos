import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import Layout from './components/Layout';
import Dashboard from './pages/Dashboard';
import Products from './pages/Products';
import Customers from './pages/Customers';
import Suppliers from './pages/Suppliers';
import Sales from './pages/Sales';
import Invoices from './pages/Invoices';
import Quotations from './pages/Quotations';
import CreditNotes from './pages/CreditNotes';
import DebitNotes from './pages/DebitNotes';
import DeliveryNotes from './pages/DeliveryNotes';
import Taxes from './pages/Taxes';
import Currencies from './pages/Currencies';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 60,
      retry: 1,
    },
  },
});

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Layout>
          <Routes>
            <Route path="/" element={<Dashboard />} />
            <Route path="/products" element={<Products />} />
            <Route path="/customers" element={<Customers />} />
            <Route path="/suppliers" element={<Suppliers />} />
            <Route path="/sales" element={<Sales />} />
            <Route path="/invoices" element={<Invoices />} />
            <Route path="/quotations" element={<Quotations />} />
            <Route path="/credit-notes" element={<CreditNotes />} />
            <Route path="/debit-notes" element={<DebitNotes />} />
            <Route path="/delivery-notes" element={<DeliveryNotes />} />
            <Route path="/taxes" element={<Taxes />} />
            <Route path="/currencies" element={<Currencies />} />
          </Routes>
        </Layout>
      </BrowserRouter>
    </QueryClientProvider>
  );
}

export default App
