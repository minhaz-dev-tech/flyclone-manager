import React, { useState } from 'react';
import DashboardLayout from '../Layouts/DashboardLayout';
import axios from 'axios';

export default function CreateSite() {
  const [name, setName] = useState('');
  const [port, setPort] = useState('');
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setMessage('');

    try {
      const response = await axios.post('/api/sites', { name, port });
      setMessage(`Site "${response.data.name}" created successfully!`);
      setName('');
      setPort('');
    } catch (error) {
      console.error(error);
      setMessage(
        error.response?.data?.message || 'Something went wrong'
      );
    } finally {
      setLoading(false);
    }
  };

  return (
    <DashboardLayout>
      <h1 className="text-2xl font-bold mb-4">Create New WordPress Site</h1>

      {message && (
        <div className="mb-4 p-2 bg-blue-100 text-blue-800 rounded">{message}</div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4 max-w-md">
        <div>
          <label className="block mb-1">Site Name</label>
          <input
            type="text"
            value={name}
            onChange={e => setName(e.target.value)}
            className="w-full border rounded p-2"
          />
        </div>
        <div>
          <label className="block mb-1">Port</label>
          <input
            type="number"
            value={port}
            onChange={e => setPort(e.target.value)}
            className="w-full border rounded p-2"
          />
        </div>
        <button
          type="submit"
          disabled={loading}
          className="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600"
        >
          {loading ? 'Creating...' : 'Create'}
        </button>
      </form>
    </DashboardLayout>
  );
}