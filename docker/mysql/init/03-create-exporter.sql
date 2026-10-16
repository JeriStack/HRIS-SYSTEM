-- =============================================================================
-- MySQL Monitoring Exporter Least-Privilege Account
-- Prometheus MySQL Exporter (mysqld-exporter)
-- =============================================================================

CREATE USER IF NOT EXISTS 'exporter'@'%' IDENTIFIED BY 'exporter_password' WITH MAX_USER_CONNECTIONS 3;
GRANT PROCESS, REPLICATION CLIENT, SELECT ON *.* TO 'exporter'@'%';
FLUSH PRIVILEGES;
