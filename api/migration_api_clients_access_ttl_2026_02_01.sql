ALTER TABLE api_clients
  ADD COLUMN access_ttl_seconds INT NULL AFTER rate_limit_per_minute;
