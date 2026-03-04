#!/bin/bash

echo "🧪 Testing WordPress Site Creation API"
echo "======================================"

# Test 1: Create site with subdomain (local)
echo -e "\n📝 Test 1: Create site with subdomain"
curl -X POST http://localhost:8000/api/sites \
  -H "Content-Type: application/json" \
  -d '{
    "name": "test-site-1",
    "domain_type": "subdomain",
    "port": 8081,
    "enable_redis": true,
    "enable_ssl": false
  }'

echo -e "\n\n📝 Test 2: Check domain availability"
curl -X POST http://localhost:8000/api/sites/check-domain \
  -H "Content-Type: application/json" \
  -d '{"domain": "test.localhost"}'

echo -e "\n\n📝 Test 3: List all sites"
curl http://localhost:8000/api/sites