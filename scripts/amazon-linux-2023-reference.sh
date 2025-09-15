#!/bin/bash
# Amazon Linux 2023 Quick Reference for Learning Journeys Deployment

echo "=== Amazon Linux 2023 Deployment Quick Reference ==="
echo

echo "1. Package Management:"
echo "   - Use 'dnf' instead of 'yum'"
echo "   - dnf update -y"
echo "   - dnf install -y package-name"
echo

echo "2. MariaDB Package Names:"
echo "   - mariadb105-server (instead of mariadb-server)"
echo "   - mariadb105 (instead of mariadb)"
echo

echo "3. PHP Package Names:"
echo "   - php8.2-mysqlnd (instead of php8.2-mysql)"
echo "   - Standard naming for other extensions"
echo

echo "4. Service Management (same as AL2):"
echo "   - systemctl start/stop/enable/status service-name"
echo

echo "5. Key Differences from Amazon Linux 2:"
echo "   - No yum-config-manager (use dnf config-manager)"
echo "   - Different default package versions"
echo "   - Enhanced security features"
echo

echo "6. CodeDeploy Agent Installation:"
echo "   - Same process as AL2"
echo "   - Uses systemctl for service management"
echo

echo "7. Apache Modules (pre-installed):"
echo "   - mod_rewrite"
echo "   - mod_ssl"
echo "   - mod_headers"
echo

echo "=== End of Quick Reference ==="
