import React from 'react';
import { Link } from '@inertiajs/react'; // ✅ updated import

export default function DashboardLayout({ children }) {
  return (
    <div className="flex h-screen">
      {/* Sidebar */}
      <aside className="w-64 bg-gray-900 text-white p-4">
        <h1 className="text-xl font-bold mb-6">FlyClone Manager</h1>
        <ul>
          <li>
            <Link
              href="/dashboard"
              className="block py-2 px-3 hover:bg-gray-700 rounded"
            >
              Dashboard
            </Link>
          </li>
          <li>
            <Link
              href="/sites"
              className="block py-2 px-3 hover:bg-gray-700 rounded"
            >
              Sites
            </Link>
                <Link
        href="/create"
        className="block py-2 px-3 hover:bg-gray-700 rounded"
      >
        Create Site
      </Link>
          </li>
          
        </ul>
      </aside>

      {/* Main Content */}
      <main className="flex-1 bg-gray-100 p-6 overflow-auto">
        {children}
      </main>
    </div>
  );
}