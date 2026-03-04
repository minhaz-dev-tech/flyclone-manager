# 1. Run migrations


# 2. Start Laravel server


# 3. In another terminal, add test domains to hosts file (Windows)
# Run as Administrator:
echo 127.0.0.1 test1.localhost >> C:\Windows\System32\drivers\etc\hosts
echo 127.0.0.1 test2.localhost >> C:\Windows\System32\drivers\etc\hosts

# 4. Test API endpoints
curl http://localhost:8000/api/sites
curl -X POST http://localhost:8000/api/sites/check-domain -H "Content-Type: application/json" -d '{"domain":"test1.localhost"}'

# 5. Create a test site
curl -X POST http://localhost:8000/api/sites \
  -H "Content-Type: application/json" \
  -d '{
    "name": "test1",
    "domain_type": "subdomain",
    "port": 8081,
    "enable_redis": true
  }'

# 6. Access your WordPress site
# Open browser: http://test1.localhost:8081