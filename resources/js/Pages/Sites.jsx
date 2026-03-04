import React, { useEffect, useState } from 'react';
import DashboardLayout from '../Layouts/DashboardLayout';
import axios from 'axios';

export default function Sites() {
  const [sites, setSites] = useState([]);

  useEffect(() => {
    fetchSites();
  }, []);

  const fetchSites = async () => {
    const response = await axios.get('/api/sites');
    setSites(response.data);
  };

  const handleStart = async (siteId) => {
    await axios.post(`/api/sites/${siteId}/start`);
    fetchSites();
  };

  const handleStop = async (siteId) => {
    await axios.post(`/api/sites/${siteId}/stop`);
    fetchSites();
  };

  return (
    <DashboardLayout>
      <h2 className="text-2xl font-bold mb-4">Sites</h2>
      <table className="w-full bg-white rounded shadow">
        <thead>
          <tr className="bg-gray-200">
            <th className="p-2 text-left">Name</th>
            <th>Status</th>
            <th>Port</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          {sites.map(site => (
            <tr key={site.id} className="border-b">
              <td className="p-2">{site.name}</td>
              <td className="p-2">{site.status}</td>
              <td className="p-2">{site.port}</td>
              <td className="p-2">
                <button onClick={() => handleStart(site.id)} className="bg-green-500 text-white px-2 py-1 rounded mr-2">
                  Start
                </button>
                <button onClick={() => handleStop(site.id)} className="bg-red-500 text-white px-2 py-1 rounded">
                  Stop
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </DashboardLayout>
  );
}