<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];
$pref_currency = $_SESSION['preferred_currency'] ?? 'INR';
$theme = $_SESSION['theme'] ?? 'light';
$message = "";

/* ----- Theme toggle ----- */
if (isset($_POST['toggle_theme'])) {
    $theme = ($theme === 'light') ? 'dark' : 'light';
    $_SESSION['theme'] = $theme;
    $stmt = $conn->prepare("UPDATE users SET theme=? WHERE id=?");
    $stmt->bind_param("si", $theme, $user_id);
    $stmt->execute(); $stmt->close();
}

/* ----- Create category ----- */
if (isset($_POST['add_category'])) {
    $cat = trim($_POST['category_name'] ?? '');
    if ($cat !== '') {
        $stmt = $conn->prepare("INSERT IGNORE INTO categories (user_id, name) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $cat);
        $stmt->execute(); $stmt->close();
        $message = "Category saved.";
    }
}

/* ----- Add income ----- */
if (isset($_POST['add_income'])) {
    $title = trim($_POST['income_title'] ?? '');
    $amount = (float)($_POST['income_amount'] ?? 0);
    $date = $_POST['income_date'] ?? '';
    $currency = $_POST['income_currency'] ?? $pref_currency;
    if ($title !== '' && $amount > 0 && $date !== '') {
        $stmt = $conn->prepare("INSERT INTO incomes (user_id, title, amount, currency, income_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isdss", $user_id, $title, $amount, $currency, $date);
        $stmt->execute(); $stmt->close();
        $message = "Income added.";
    } else { $message = "Income: all fields are required."; }
}

/* ----- Add expense ----- */
if (isset($_POST['add_expense'])) {
    $title = trim($_POST['title'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $date = $_POST['date'] ?? '';
    $currency = $_POST['currency'] ?? $pref_currency;
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $recurring = $_POST['recurring'] ?? 'none';

    if ($title !== '' && $amount > 0 && $date !== '') {
        $next = null;
        if ($recurring !== 'none') {
            $d = new DateTime($date);
            if ($recurring === 'weekly') { $d->modify('+1 week'); }
            elseif ($recurring === 'monthly') { $d->modify('+1 month'); }
            elseif ($recurring === 'yearly') { $d->modify('+1 year'); }
            $next = $d->format('Y-m-d');
        }
        $stmt = $conn->prepare("INSERT INTO expenses (user_id, category_id, title, amount, currency, expense_date, recurring, next_occurrence)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisdssss", $user_id, $category_id, $title, $amount, $currency, $date, $recurring, $next);
        $stmt->execute(); $stmt->close();
        $message = "Expense added.";
    } else { $message = "Expense: all fields are required."; }
}

/* ----- Edit expense load ----- */
$edit = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM expenses WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute(); $edit = $stmt->get_result()->fetch_assoc(); $stmt->close();
}

/* ----- Update expense ----- */
if (isset($_POST['update_expense'])) {
    $id = (int)$_POST['id'];
    $title = trim($_POST['title'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $date = $_POST['date'] ?? '';
    $currency = $_POST['currency'] ?? $pref_currency;
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $recurring = $_POST['recurring'] ?? 'none';
    $next = null;
    if ($recurring !== 'none' && $date !== '') {
        $d = new DateTime($date);
        if ($recurring === 'weekly') $d->modify('+1 week');
        elseif ($recurring === 'monthly') $d->modify('+1 month');
        elseif ($recurring === 'yearly') $d->modify('+1 year');
        $next = $d->format('Y-m-d');
    }
    if ($title !== '' && $amount > 0 && $date !== '') {
        $stmt = $conn->prepare("UPDATE expenses SET category_id=?, title=?, amount=?, currency=?, expense_date=?, recurring=?, next_occurrence=? WHERE id=? AND user_id=?");
        $stmt->bind_param("isdssssii", $category_id, $title, $amount, $currency, $date, $recurring, $next, $id, $user_id);
        $stmt->execute(); $stmt->close();
        $message = "Expense updated.";
        $edit = null;
    } else { $message = "Expense: all fields are required."; }
}

/* ----- Delete expense ----- */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM expenses WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute(); $stmt->close();
    $message = "Expense deleted.";
}

/* ----- Save/Update budget ----- */
if (isset($_POST['save_budget'])) {
    $month = (int)$_POST['budget_month'];
    $year = (int)$_POST['budget_year'];
    $amount = (float)$_POST['budget_amount'];
    if ($month>=1 && $month<=12 && $year>=2000 && $amount>0) {
        $stmt = $conn->prepare("INSERT INTO budgets (user_id, month, year, amount)
                                VALUES (?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE amount=VALUES(amount)");
        $stmt->bind_param("iiid", $user_id, $month, $year, $amount);
        $stmt->execute(); $stmt->close();
        $message = "Budget saved.";
    }
}

/* ----- Filters ----- */
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

/* ----- Fetch categories ----- */
$cats = [];
$rs = $conn->prepare("SELECT id, name FROM categories WHERE user_id=? ORDER BY name");
$rs->bind_param("i", $user_id); $rs->execute();
$rc = $rs->get_result(); while($row=$rc->fetch_assoc()) $cats[]=$row; $rs->close();

/* ----- Fetch expenses (filtered) ----- */
$sql = "SELECT e.*, c.name AS category
        FROM expenses e
        LEFT JOIN categories c ON c.id=e.category_id
        $where
        ORDER BY e.expense_date DESC, e.id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ----- Summaries (month/year/top item) ----- */
$currMonth = (int)date('m');
$currYear  = (int)date('Y');

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND MONTH(expense_date)=? AND YEAR(expense_date)=?");
$stmt->bind_param("iii", $user_id, $currMonth, $currYear); $stmt->execute();
$stmt->bind_result($month_total); $stmt->fetch(); $stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND YEAR(expense_date)=?");
$stmt->bind_param("ii", $user_id, $currYear); $stmt->execute();
$stmt->bind_result($year_total); $stmt->fetch(); $stmt->close();

$stmt = $conn->prepare("SELECT title, SUM(amount) total FROM expenses WHERE user_id=? GROUP BY title ORDER BY total DESC LIMIT 1");
$stmt->bind_param("i", $user_id); $stmt->execute();
$top = $stmt->get_result()->fetch_assoc(); $stmt->close();

/* ----- Income vs Expense (this month) ----- */
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM incomes WHERE user_id=? AND MONTH(income_date)=? AND YEAR(income_date)=?");
$stmt->bind_param("iii", $user_id, $currMonth, $currYear); $stmt->execute();
$stmt->bind_result($month_income); $stmt->fetch(); $stmt->close();

$month_saving = $month_income - $month_total;

/* ----- Budget for this month ----- */
$stmt = $conn->prepare("SELECT amount FROM budgets WHERE user_id=? AND month=? AND year=?");
$stmt->bind_param("iii", $user_id, $currMonth, $currYear); $stmt->execute();
$stmt->bind_result($budget_amt); $stmt->fetch(); $stmt->close();
$budget_amt = $budget_amt ?? 0.00;
$budget_used_pct = $budget_amt>0 ? min(100, round(($month_total/$budget_amt)*100, 1)) : 0;

/* ----- Chart data: monthly totals for current year ----- */
$monthlySeries = array_fill(1, 12, 0.0);
$stmt = $conn->prepare("SELECT MONTH(expense_date) m, SUM(amount) t FROM expenses WHERE user_id=? AND YEAR(expense_date)=? GROUP BY m");
$stmt->bind_param("ii", $user_id, $currYear); $stmt->execute();
$r = $stmt->get_result(); while($row=$r->fetch_assoc()) { $monthlySeries[(int)$row['m']] = (float)$row['t']; }
$stmt->close();

/* ----- Chart data: category distribution (current month) ----- */
$catLabels = []; $catTotals = [];
$stmt = $conn->prepare("SELECT COALESCE(c.name,'Uncategorized') label, SUM(e.amount) total
                        FROM expenses e
                        LEFT JOIN categories c ON c.id=e.category_id
                        WHERE e.user_id=? AND MONTH(e.expense_date)=? AND YEAR(e.expense_date)=?
                        GROUP BY label ORDER BY total DESC");
$stmt->bind_param("iii", $user_id, $currMonth, $currYear); $stmt->execute();
$r = $stmt->get_result(); while($row=$r->fetch_assoc()){ $catLabels[]=$row['label']; $catTotals[]=(float)$row['total']; }
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard - Expense Tracker</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    :root {
        --purple:#6c5ce7; --teal:#00cec9; --bgDark:#111; --cardDark:#1e1e1e; --textLight:#fff;
    }
    body {
        margin:0; font-family: Arial, sans-serif;
        background: linear-gradient(135deg, #6c5ce7, #00cec9);
        color: #222;
    }
    .light header, .light footer { background: rgba(0,0,0,0.6); color:#fff; }
    .dark header, .dark footer { background:#000; color:#fff; }
    .wrap { width:92%; max-width:1200px; margin:20px auto; }
    header, footer { padding: 15px; text-align:center; }
    .topbar { display:flex; justify-content:space-between; align-items:center; }
    .card { background:#fff; border-radius:10px; padding:16px; margin:10px 0; box-shadow:0 6px 18px rgba(0,0,0,0.15); }
    .dark .card { background:rgba(255,255,255,0.08); color:#fff; }
    .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    .grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
    @media(max-width:900px){ .grid-2, .grid-3 { grid-template-columns:1fr; } .topbar { flex-direction:column; gap:8px; } }

    input, select, button { padding:10px; border-radius:6px; border:1px solid #ccc; }
    button.btn { background:#fff; color:#6c5ce7; border:none; font-weight:bold; cursor:pointer; }
    a.btn { text-decoration:none; padding:10px 14px; background:#fff; color:#6c5ce7; border-radius:6px; }
    .danger { background:#e94560; color:#fff; border:none; }
    .warn { background:#ffb84d; border:none; }
    table { width:100%; border-collapse:collapse; }
    th, td { border:1px solid #ddd; padding:8px; text-align:center; }
    th { background:#93329e; color:#fff; }
    .message { font-weight:bold; margin:10px 0; color:#fff; }
    .progress { background:#eee; border-radius:6px; overflow:hidden; height:12px; }
    .progress > div { height:100%; background:#e94560; width:0; }
</style>
</head>
<body class="<?php echo htmlspecialchars($theme); ?>">
<header>
  <div class="wrap topbar">
    <h2>Expense Tracker Dashboard</h2>
    <div>
        <form method="post" style="display:inline;">
            <button class="btn" name="toggle_theme" type="submit">Toggle Theme (<?php echo $theme; ?>)</button>
        </form>
        <a class="btn" href="logout.php">Logout</a>
    </div>
  </div>
</header>

<div class="wrap">
    <?php if ($message) echo "<div class='message'>{$message}</div>"; ?>

    <!-- Summary Cards -->
    <div class="grid-3">
        <div class="card">
            <h3>This Month Expense</h3>
            <p><strong><?php echo $pref_currency; ?> <?php echo number_format($month_total, 2); ?></strong></p>
            <h4>Budget: <?php echo $pref_currency; ?> <?php echo number_format($budget_amt,2); ?></h4>
            <div class="progress"><div style="width:<?php echo $budget_used_pct; ?>%"></div></div>
            <small><?php echo $budget_used_pct; ?>% used</small>
        </div>
        <div class="card">
            <h3>This Year Expense</h3>
            <p><strong><?php echo $pref_currency; ?> <?php echo number_format($year_total, 2); ?></strong></p>
            <h4>Income (This Month)</h4>
            <p><strong><?php echo $pref_currency; ?> <?php echo number_format($month_income,2); ?></strong></p>
            <h4>Savings (This Month)</h4>
            <p><strong><?php echo $pref_currency; ?> <?php echo number_format($month_saving,2); ?></strong></p>
        </div>
        <div class="card">
            <h3>Most Spent Item</h3>
            <p><strong><?php echo $top ? htmlspecialchars($top['title'])." (".$pref_currency." ".number_format($top['total'],2).")" : 'N/A'; ?></strong></p>
        </div>
    </div>

    <!-- Budget form -->
    <div class="card">
        <h3>Set / Update Budget</h3>
        <form method="post" class="grid-3">
            <div>
                <label>Month</label>
                <select name="budget_month">
                    <?php for($m=1;$m<=12;$m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo ($m==$currMonth?'selected':''); ?>><?php echo $m; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label>Year</label>
                <input type="number" name="budget_year" value="<?php echo $currYear; ?>">
            </div>
            <div>
                <label>Amount (<?php echo $pref_currency; ?>)</label>
                <input type="number" step="0.01" name="budget_amount" value="<?php echo number_format($budget_amt,2,'.',''); ?>">
            </div>
            <div><button class="btn" type="submit" name="save_budget">Save Budget</button></div>
        </form>
    </div>

    <!-- Forms: Category / Income / Expense -->
    <div class="grid-3">
        <div class="card">
            <h3>New Category</h3>
            <form method="post">
                <input type="text" name="category_name" placeholder="Category name" required>
                <button class="btn" type="submit" name="add_category">Add Category</button>
            </form>
        </div>
        <div class="card">
            <h3>Add Income</h3>
            <form method="post">
                <input type="text" name="income_title" placeholder="Title" required>
                <input type="number" step="0.01" name="income_amount" placeholder="Amount" required>
                <input type="date" name="income_date" required>
                <select name="income_currency">
                    <option value="INR" <?php echo $pref_currency==='INR'?'selected':''; ?>>INR</option>
                    <option value="USD" <?php echo $pref_currency==='USD'?'selected':''; ?>>USD</option>
                    <option value="EUR" <?php echo $pref_currency==='EUR'?'selected':''; ?>>EUR</option>
                    <option value="GBP" <?php echo $pref_currency==='GBP'?'selected':''; ?>>GBP</option>
                </select>
                <button class="btn" type="submit" name="add_income">Add Income</button>
            </form>
        </div>
        <div class="card">
            <h3><?php echo $edit ? 'Edit Expense' : 'Add Expense'; ?></h3>
            <form method="post">
                <?php if ($edit): ?><input type="hidden" name="id" value="<?php echo (int)$edit['id']; ?>"><?php endif; ?>
                <input type="text" name="title" placeholder="Title" value="<?php echo htmlspecialchars($edit['title'] ?? ''); ?>" required>
                <input type="number" step="0.01" name="amount" placeholder="Amount" value="<?php echo htmlspecialchars($edit['amount'] ?? ''); ?>" required>
                <input type="date" name="date" value="<?php echo htmlspecialchars($edit['expense_date'] ?? ''); ?>" required>
                <select name="currency">
                    <option value="INR" <?php echo (($edit['currency'] ?? $pref_currency)==='INR')?'selected':''; ?>>INR</option>
                    <option value="USD" <?php echo (($edit['currency'] ?? $pref_currency)==='USD')?'selected':''; ?>>USD</option>
                    <option value="EUR" <?php echo (($edit['currency'] ?? $pref_currency)==='EUR')?'selected':''; ?>>EUR</option>
                    <option value="GBP" <?php echo (($edit['currency'] ?? $pref_currency)==='GBP')?'selected':''; ?>>GBP</option>
                </select>
                <select name="category_id">
                    <option value="">Uncategorized</option>
                    <?php foreach($cats as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo (isset($edit['category_id']) && (int)$edit['category_id']===(int)$c['id'])?'selected':''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="recurring">
                    <?php $r=$edit['recurring']??'none'; ?>
                    <option value="none" <?php echo $r==='none'?'selected':''; ?>>One-time</option>
                    <option value="weekly" <?php echo $r==='weekly'?'selected':''; ?>>Weekly</option>
                    <option value="monthly" <?php echo $r==='monthly'?'selected':''; ?>>Monthly</option>
                    <option value="yearly" <?php echo $r==='yearly'?'selected':''; ?>>Yearly</option>
                </select>
                <button class="btn" type="submit" name="<?php echo $edit ? 'update_expense':'add_expense'; ?>">
                    <?php echo $edit ? 'Update Expense':'Add Expense'; ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="card">
        <h3>Search & Filters</h3>
        <form method="get" class="grid-3">
            <div><input type="text" name="q" placeholder="Search title..." value="<?php echo htmlspecialchars($q); ?>"></div>
            <div><label>From</label><input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>"></div>
            <div><label>To</label><input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>"></div>
            <div>
                <label>Category</label>
                <select name="cat">
                    <option value="">All</option>
                    <?php foreach($cats as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($catf!=='' && (int)$catf===(int)$c['id'])?'selected':''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><button class="btn" type="submit">Apply</button></div>
            <div><a class="btn" href="export.php?<?php echo http_build_query(['q'=>$q,'from'=>$from,'to'=>$to,'cat'=>$catf]); ?>">Export CSV</a></div>
        </form>
    </div>

    <!-- Expenses Table -->
    <div class="card">
        <h3>Expenses</h3>
        <table>
            <tr>
                <th>ID</th><th>Title</th><th>Category</th><th>Amount</th><th>Currency</th><th>Date</th><th>Recurring</th><th>Actions</th>
            </tr>
            <?php if (empty($expenses)): ?>
                <tr><td colspan="8">No records found.</td></tr>
            <?php else: foreach($expenses as $e): ?>
                <tr>
                    <td><?php echo $e['id']; ?></td>
                    <td><?php echo htmlspecialchars($e['title']); ?></td>
                    <td><?php echo htmlspecialchars($e['category'] ?? 'Uncategorized'); ?></td>
                    <td><?php echo number_format($e['amount'],2); ?></td>
                    <td><?php echo htmlspecialchars($e['currency']); ?></td>
                    <td><?php echo htmlspecialchars($e['expense_date']); ?></td>
                    <td><?php echo htmlspecialchars($e['recurring']); ?></td>
                    <td>
                        <a class="warn btn" href="dashboard.php?edit=<?php echo $e['id']; ?>">Edit</a>
                        <a class="danger btn" href="dashboard.php?delete=<?php echo $e['id']; ?>" onclick="return confirm('Delete this expense?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </table>
    </div>

    <!-- Charts -->
    <div class="grid-2">
        <div class="card">
            <h3>Monthly Expense Trend (<?php echo $currYear; ?>)</h3>
            <canvas id="lineChart"></canvas>
        </div>
        <div class="card">
            <h3>Category-wise (This Month)</h3>
            <canvas id="pieChart"></canvas>
        </div>
    </div>
</div>

<footer>
  <div class="wrap"><p>&copy; <?php echo date('Y'); ?> Expense Tracker</p></div>
</footer>

<script>
const months = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
const lineData = <?php echo json_encode(array_values($monthlySeries)); ?>;
const catLabels = <?php echo json_encode($catLabels); ?>;
const catTotals = <?php echo json_encode($catTotals); ?>;

// Line chart
new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
        labels: months,
        datasets: [{ label: 'Expenses', data: lineData }]
    },
    options: { responsive:true, plugins:{ legend:{ display:true } } }
});

// Pie chart
new Chart(document.getElementById('pieChart'), {
    type: 'pie',
    data: { labels: catLabels, datasets: [{ data: catTotals }] },
    options: { responsive:true, plugins:{ legend:{ position:'bottom' } } }
});
</script>
</body>
</html>
