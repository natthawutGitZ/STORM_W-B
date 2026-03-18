<?php
header('Content-Type: application/json');
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Helper: Check if ID is for custom card (negative in frontend)
 * Custom cards use negative IDs in frontend, but positive in DB
 */
function isCustomCard($id) {
    return intval($id) < 0;
}

/**
 * Helper: Convert frontend ID to DB ID for custom_cards
 * Frontend: -1, -2, -3 => DB: 1, 2, 3
 */
function toDbId($id) {
    return abs(intval($id));
}

/**
 * Helper: Convert DB ID to frontend ID for custom_cards
 * DB: 1, 2, 3 => Frontend: -1, -2, -3
 */
function toFrontendId($id) {
    return -1 * abs(intval($id));
}

try {
    if ($method === 'GET') {
        if ($action === 'tree') {
            // Fetch Real Users (Assigned in Chain)
            $stmt = $pdo->query("SELECT id, personaname as name, `rank`, `position`, avatar as image_url, parent_id, coc_sort_order as sort_order, coc_x, coc_y, 'user' as card_type
                                FROM users 
                                WHERE status != 'Inactive' AND coc_sort_order IS NOT NULL
                                ORDER BY coc_sort_order ASC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch Custom Cards (Assigned in Chain)
            $customCards = [];
            try {
                $stmt2 = $pdo->query("SELECT id, name, `rank`, `position`, image_url, parent_id, coc_sort_order as sort_order, coc_x, coc_y, 'custom' as card_type
                                     FROM custom_cards 
                                     WHERE coc_sort_order IS NOT NULL
                                     ORDER BY coc_sort_order ASC");
                $customCards = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                
                // Convert custom card IDs to negative for frontend
                foreach ($customCards as &$card) {
                    $card['id'] = toFrontendId($card['id']);
                    // Also convert parent_id if it references another custom card (stored as negative in DB)
                    if ($card['parent_id'] !== null && intval($card['parent_id']) < 0) {
                        // Parent is a custom card (stored as negative)
                        $card['parent_id'] = intval($card['parent_id']); // Keep as negative
                    }
                }
                unset($card);
            } catch (PDOException $e) {
                // Table might not exist yet, ignore
            }

            // Merge and return
            $nodes = array_merge($users, $customCards);
            echo json_encode(['success' => true, 'data' => $nodes]);

        } else if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            
            if (isCustomCard($id)) {
                // Custom Card
                $dbId = toDbId($id);
                $stmt = $pdo->prepare("SELECT id, name, `rank`, `position`, image_url, parent_id, coc_x, coc_y FROM custom_cards WHERE id = ?");
                $stmt->execute([$dbId]);
                $node = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($node) {
                    $node['id'] = toFrontendId($node['id']);
                    $node['card_type'] = 'custom';
                }
            } else {
                // Real User
                $stmt = $pdo->prepare("SELECT id, personaname as name, `rank`, `position`, avatar as image_url, parent_id, coc_x, coc_y FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $node = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($node) $node['card_type'] = 'user';
            }
            echo json_encode(['success' => true, 'data' => $node ?? null]);

        } else {
            // Toolbox (Unassigned Users only - custom cards don't go to toolbox)
            $stmt = $pdo->query("SELECT id, personaname as name, avatar as image_url FROM users WHERE status != 'Inactive' AND coc_sort_order IS NULL ORDER BY personaname ASC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if ($action === 'move') {
            if (!isset($input['id']))
                throw new Exception("Missing ID");

            $id = intval($input['id']);
            $updates = [];
            $params = [];

            if (isset($input['x'])) {
                $updates[] = "coc_x = ?";
                $params[] = intval($input['x']);
            }
            if (isset($input['y'])) {
                $updates[] = "coc_y = ?";
                $params[] = intval($input['y']);
            }
            if (array_key_exists('parent_id', $input)) {
                $updates[] = "parent_id = ?";
                $val = $input['parent_id'];
                // Parent can be positive (user) or negative (custom card) - store as-is
                $params[] = ($val === "" || $val === null) ? null : intval($val);
            }

            // Ensure it's in the chain
            $updates[] = "coc_sort_order = IFNULL(coc_sort_order, 0)";

            if (count($updates) <= 1) { // Only has coc_sort_order
                echo json_encode(['success' => true]);
                exit;
            }

            if (isCustomCard($id)) {
                // Custom Card
                $dbId = toDbId($id);
                $sql = "UPDATE custom_cards SET " . implode(", ", $updates) . " WHERE id = ?";
                $params[] = $dbId;
            } else {
                // Real User
                $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
                $params[] = $id;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['success' => true]);

        } elseif ($action === 'removeFromChain') {
            $id = intval($_GET['id']);
            
            if (isCustomCard($id)) {
                // Custom Card - remove from chain (reset position)
                $dbId = toDbId($id);
                $stmt = $pdo->prepare("UPDATE custom_cards SET parent_id = NULL, coc_sort_order = NULL, coc_x = 0, coc_y = 0 WHERE id = ?");
                $stmt->execute([$dbId]);
            } else {
                // Real User
                $stmt = $pdo->prepare("UPDATE users SET parent_id = NULL, coc_sort_order = NULL, coc_x = 0, coc_y = 0 WHERE id = ?");
                $stmt->execute([$id]);
            }
            
            // Also update any children that had this as parent
            $pdo->prepare("UPDATE users SET parent_id = NULL WHERE parent_id = ?")->execute([$id]);
            $pdo->prepare("UPDATE custom_cards SET parent_id = NULL WHERE parent_id = ?")->execute([$id]);
            
            echo json_encode(['success' => true]);

        } elseif ($action === 'create_custom') {
            // Create new custom card
            $name = isset($input['name']) ? trim($input['name']) : 'Custom Card';
            $rank = isset($input['rank']) ? trim($input['rank']) : '';
            $position = isset($input['position']) ? trim($input['position']) : '';
            $imageUrl = isset($input['image_url']) ? trim($input['image_url']) : '';
            $x = isset($input['x']) ? intval($input['x']) : 100;
            $y = isset($input['y']) ? intval($input['y']) : 100;

            $sql = "INSERT INTO custom_cards (name, `rank`, `position`, image_url, coc_x, coc_y, coc_sort_order) VALUES (?, ?, ?, ?, ?, ?, 0)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $rank, $position, $imageUrl, $x, $y]);
            
            $newId = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'id' => toFrontendId($newId)]);

        } elseif ($action === 'delete_custom') {
            // Delete custom card permanently
            $id = isset($input['id']) ? intval($input['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
            
            if (!isCustomCard($id)) {
                throw new Exception("Cannot delete real users from this endpoint");
            }
            
            $dbId = toDbId($id);
            
            // Update any children that had this as parent
            $pdo->prepare("UPDATE users SET parent_id = NULL WHERE parent_id = ?")->execute([$id]);
            $pdo->prepare("UPDATE custom_cards SET parent_id = NULL WHERE parent_id = ?")->execute([$id]);
            
            // Delete the card
            $stmt = $pdo->prepare("DELETE FROM custom_cards WHERE id = ?");
            $stmt->execute([$dbId]);
            
            echo json_encode(['success' => true]);

        } elseif ($action === 'update_custom') {
            // Update custom card details
            $id = isset($input['id']) ? intval($input['id']) : 0;
            
            if (!isCustomCard($id)) {
                throw new Exception("Use default update for real users");
            }
            
            $dbId = toDbId($id);
            $name = isset($input['name']) ? trim($input['name']) : '';
            $rank = isset($input['rank']) ? trim($input['rank']) : '';
            $position = isset($input['position']) ? trim($input['position']) : '';
            $imageUrl = isset($input['image_url']) ? trim($input['image_url']) : '';

            $sql = "UPDATE custom_cards SET name = ?, `rank` = ?, `position` = ?, image_url = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $rank, $position, $imageUrl, $dbId]);
            
            echo json_encode(['success' => true, 'id' => $id]);

        } else {
            // Update Details (Real Users)
            $id = isset($input['id']) ? intval($input['id']) : null;
            
            if ($id && isCustomCard($id)) {
                // Redirect to update_custom logic
                $dbId = toDbId($id);
                $name = isset($input['name']) ? trim($input['name']) : '';
                $rank = isset($input['rank']) ? trim($input['rank']) : '';
                $position = isset($input['position']) ? trim($input['position']) : '';
                $imageUrl = isset($input['image_url']) ? trim($input['image_url']) : '';

                $sql = "UPDATE custom_cards SET name = ?, `rank` = ?, `position` = ?, image_url = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$name, $rank, $position, $imageUrl, $dbId]);
                echo json_encode(['success' => true, 'id' => $id]);
            } elseif ($id) {
                $name = isset($input['name']) ? trim($input['name']) : '';
                $rank = isset($input['rank']) ? trim($input['rank']) : '';
                $position = isset($input['position']) ? trim($input['position']) : '';
                $imageUrl = isset($input['image_url']) ? trim($input['image_url']) : '';

                $sql = "UPDATE users SET personaname = ?, `rank` = ?, `position` = ?, avatar = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$name, $rank, $position, $imageUrl, $id]);
                echo json_encode(['success' => true, 'id' => $id]);
            }
        }

    } elseif ($method === 'DELETE') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($id && isCustomCard($id)) {
            // Delete custom card
            $dbId = toDbId($id);
            $pdo->prepare("UPDATE users SET parent_id = NULL WHERE parent_id = ?")->execute([$id]);
            $pdo->prepare("UPDATE custom_cards SET parent_id = NULL WHERE parent_id = ?")->execute([$id]);
            $stmt = $pdo->prepare("DELETE FROM custom_cards WHERE id = ?");
            $stmt->execute([$dbId]);
        } elseif ($id) {
            // Remove user from chain (don't delete user)
            $stmt = $pdo->prepare("UPDATE users SET parent_id = NULL, coc_sort_order = NULL, coc_x = 0, coc_y = 0 WHERE id = ?");
            $stmt->execute([$id]);
        }
        echo json_encode(['success' => true]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
