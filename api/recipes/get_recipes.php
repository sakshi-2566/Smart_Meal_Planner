<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/database.php';

$conn = getDBConnection();

// Get query parameters
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // all, my, public, pending
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$dietary_tag = isset($_GET['dietary_tag']) ? $conn->real_escape_string($_GET['dietary_tag']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 12;
$offset = ($page - 1) * $limit;

// Build query based on filter
$where_clauses = [];
$params = [];
$types = '';

if ($filter === 'my' && $user_id) {
    $where_clauses[] = "r.user_id = ?";
    $params[] = $user_id;
    $types .= 'i';
} elseif ($filter === 'public') {
    $where_clauses[] = "r.is_public = 1 AND r.approval_status = 'approved'";
} elseif ($filter === 'pending') {
    // Only admins can see pending recipes
    $where_clauses[] = "r.approval_status = 'pending'";
} elseif ($filter === 'approved') {
    $where_clauses[] = "r.approval_status = 'approved'";
} elseif ($filter === 'ai_generated') {
    $where_clauses[] = "r.is_ai_generated = 1 AND r.approval_status = 'approved'";
    if ($user_id) {
        $where_clauses[] = "r.user_id = ?";
        $params[] = $user_id;
        $types .= 'i';
    }
}

if ($search) {
    $where_clauses[] = "(r.recipe_name LIKE ? OR r.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if ($dietary_tag) {
    $where_clauses[] = "r.dietary_tags LIKE ?";
    $tag_param = "%$dietary_tag%";
    $params[] = $tag_param;
    $types .= 's';
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM recipes r $where_sql";
$stmt = $conn->prepare($count_sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];

// Get recipes with user info and ratings
$sql = "SELECT r.*, 
        u.first_name, u.last_name, u.email,
        COALESCE(AVG(rt.rating), 0) as avg_rating,
        COUNT(DISTINCT rt.id) as rating_count,
        COUNT(DISTINCT rf.id) as favorite_count
        FROM recipes r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN recipe_ratings rt ON r.id = rt.recipe_id
        LEFT JOIN recipe_favorites rf ON r.id = rf.recipe_id
        $where_sql
        GROUP BY r.id
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$recipes = [];
while ($row = $result->fetch_assoc()) {
    $recipe_id = $row['id'];
    
    // Get ingredients
    $ing_sql = "SELECT ri.*, i.ingredient_name, i.category 
                FROM recipe_ingredients ri
                JOIN ingredients i ON ri.ingredient_id = i.id
                WHERE ri.recipe_id = ?";
    $ing_stmt = $conn->prepare($ing_sql);
    $ing_stmt->bind_param("i", $recipe_id);
    $ing_stmt->execute();
    $ingredients = $ing_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get steps
    $step_sql = "SELECT * FROM recipe_steps WHERE recipe_id = ? ORDER BY step_number";
    $step_stmt = $conn->prepare($step_sql);
    $step_stmt->bind_param("i", $recipe_id);
    $step_stmt->execute();
    $steps = $step_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get images
    $img_sql = "SELECT * FROM recipe_images WHERE recipe_id = ? ORDER BY display_order";
    $img_stmt = $conn->prepare($img_sql);
    $img_stmt->bind_param("i", $recipe_id);
    $img_stmt->execute();
    $images = $img_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Check if user has favorited
    $is_favorited = false;
    if ($user_id) {
        $fav_sql = "SELECT id FROM recipe_favorites WHERE recipe_id = ? AND user_id = ?";
        $fav_stmt = $conn->prepare($fav_sql);
        $fav_stmt->bind_param("ii", $recipe_id, $user_id);
        $fav_stmt->execute();
        $is_favorited = $fav_stmt->get_result()->num_rows > 0;
    }
    
    $row['ingredients'] = $ingredients;
    $row['steps'] = $steps;
    $row['images'] = $images;
    $row['is_favorited'] = $is_favorited;
    $row['avg_rating'] = round(floatval($row['avg_rating']), 1);
    
    $recipes[] = $row;
}

echo json_encode([
    'success' => true,
    'recipes' => $recipes,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => intval($total),
        'total_pages' => ceil($total / $limit)
    ]
]);

closeDBConnection($conn);
?>
