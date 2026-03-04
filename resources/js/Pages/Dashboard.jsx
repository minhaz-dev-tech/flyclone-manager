import React, { useEffect, useState } from 'react';
import DashboardLayout from '../Layouts/DashboardLayout';
import axios from 'axios';

export default function Dashboard() {
  const [sites, setSites] = useState([]);

  useEffect(() => {
    fetchSites();
  }, []);

  const fetchSites = async () => {
    const response = await axios.get('/api/sites'); // Laravel API endpoint
    setSites(response.data);
  };

  return (
    <DashboardLayout>
      <h2 className="text-2xl font-bold mb-4">Dashboard</h2>
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {sites.map(site => (
          <div key={site.id} className="bg-white p-4 rounded shadow">
            <h3 className="font-semibold">{site.name}</h3>
            <p>Status: {site.status}</p>
            <p>Port: {site.port}</p>
            <div className="mt-2">
              <button className="bg-green-500 text-white px-3 py-1 rounded mr-2">Start</button>
              <button className="bg-red-500 text-white px-3 py-1 rounded">Stop</button>
            </div>
          </div>
        ))}
      </div>
    </DashboardLayout>
  );
}