#!/bin/bash
# Migration Script for Chain of Command
# Run this script directly on EC2 after SSH

echo "Running migration on MySQL..."
docker exec mysql_db mysql -u appuser -papppass appdb -e "
ALTER TABLE users ADD COLUMN IF NOT EXISTS parent_id INT(11) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS coc_sort_order INT(11) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS coc_x INT(11) DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS coc_y INT(11) DEFAULT 0;
"

echo ""
echo "Verifying columns..."
docker exec mysql_db mysql -u appuser -papppass appdb -e "SHOW COLUMNS FROM users WHERE Field IN ('parent_id', 'coc_sort_order', 'coc_x', 'coc_y');"

echo ""
echo "Migration complete!"
