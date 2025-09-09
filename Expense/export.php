<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { die("Not authorized"); }

$user_id = $_SESSION['user_id'];
$q = trim($_GET['q'] ?? '');
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$catf = $_GET['cat'] ?? '';

$where = "WHERE e.user_id=?";
$params = [$user_id];
$types = "i";
if ($q !== '') { $where .= " AND (e.title LIKE CONCAT('%',?,'%'))"; $types.="s"; $params[]=$q; }
if ($from !== '') { $where .= " AND e.expense_date>=?"; $types.="s"; $params[]=$from; }
if ($to !== '') { $where .= " AND e.expense_date<=?"; $types.="s"; $params[]=$to; }
if ($catf !== '') { $where .= " AND e.category_id=?"; $types.="i"; $params[]=(int)$catf; }

$sql = "SELECT e.id, e.title, COALESCE(c.name,'Uncategorized') category, e.amount, e.currency, e.expense_date, e.recurring
        FROM expenses e LEFT JOIN categories c ON c.id=e.category_id $where ORDER BY e.expense_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=expenses_export.csv');
$out = fopen('php://output', 'w');
fputcsv($out, ['ID','Title','Category','Amount','Currency','Date','Recurring']);
while ($row = $res->fetch_assoc()) { fputcsv($out, $row); }
fclose($out);
