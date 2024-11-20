<?php
session_start();
require 'includes/db_connect.php'; // This should initialize $conn

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user's transactions along with gain_loss and tax
$transactions_query = "
    SELECT t.transaction_id, t.purchase_date, t.sell_date, t.total_investment, t.gain_loss, tf.irs_tax AS tax
    FROM transactions t
    JOIN transaction_fees tf ON t.transaction_id = tf.transaction_id
    WHERE t.user_id = ?
    ORDER BY t.purchase_date ASC
";

$stmt = $conn->prepare($transactions_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$transactions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch transaction allocations for each transaction
$allocations = [];
if (!empty($transactions)) {
    $transaction_ids = array_column($transactions, 'transaction_id');
    $placeholders = implode(',', array_fill(0, count($transaction_ids), '?'));

    // Prepare the types string for bind_param
    $types = str_repeat('i', count($transaction_ids));

    $allocations_query = "
        SELECT ta.transaction_id, ta.stock_ticker, ta.allocation_percentage, ta.allocation_amount, ta.gain_loss
        FROM transaction_allocations ta
        WHERE ta.transaction_id IN ($placeholders) AND ta.allocation_percentage > 0
        ORDER BY ta.transaction_id ASC
    ";

    $stmt = $conn->prepare($allocations_query);
    $stmt->bind_param($types, ...$transaction_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    $allocation_rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Organize allocations by transaction_id
    foreach ($allocation_rows as $row) {
        $allocations[$row['transaction_id']][] = $row;
    }

    // Calculate totals if more than one transaction
    $total_transactions = count($transactions);

    if ($total_transactions > 1) {
        $total_investment = 0;
        $total_tax = 0;
        $total_gain_loss = 0;

        foreach ($transactions as $transaction) {
            $adjusted_investment = $transaction['total_investment'] * 0.99; // Subtract 1% brokerage fee
            $total_investment += $adjusted_investment;
            $total_tax += $transaction['tax'];
            $total_gain_loss += $transaction['gain_loss'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Head content remains the same -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Your Transactions</title>
    <link rel="stylesheet" href="styles.css">
    <!-- Include any necessary scripts for the dropdown functionality -->
    <style>
        /* Transactions Table */
        table {
            width: 90%;
            margin: 20px auto;
            border-collapse: collapse;
        }

        table thead {
            background-color: #007BFF;
            color: white;
        }

        table th, table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }

        table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .transaction-row {
            cursor: pointer;
        }

        .arrow-cell {
            width: 20px;
            text-align: center;
            transition: transform 0.2s;
        }

        .transaction-row.expanded .arrow-cell {
            transform: rotate(90deg);
        }

        .allocation-table {
            display: none;
        }

        .allocation-table th, .allocation-table td {
            padding: 5px;
        }

        .total-row {
            font-weight: bold;
            background-color: #e0e0e0;
        }
    </style>
    <script>
        function toggleAllocations(transactionId) {
            var allocationRow = document.getElementById('allocations-' + transactionId);
            var transactionRow = document.getElementById('transaction-row-' + transactionId);
            if (allocationRow.style.display === 'none' || allocationRow.style.display === '') {
                allocationRow.style.display = 'table-row';
                transactionRow.classList.add('expanded');
            } else {
                allocationRow.style.display = 'none';
                transactionRow.classList.remove('expanded');
            }
        }
    </script>
</head>
<body>
    <?php require 'includes/header.php'; ?>
    <main class="dashboard">
        <h2 class="center-text">Your Transactions</h2>
        <?php if (empty($transactions)): ?>
            <p class="center-text">You have no transactions.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th></th> <!-- For the arrow indicator -->
                        <th>Purchase Date</th>
                        <th>Sell Date</th>
                        <th>Total Investment ($)</th>
                        <th>Tax ($)</th>
                        <th>Gain/Loss ($)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                        <?php
                        $transaction_id = $transaction['transaction_id'];
                        $adjusted_investment = $transaction['total_investment'] * 0.99; // Subtract 1% brokerage fee
                        ?>
                        <tr id="transaction-row-<?php echo $transaction_id; ?>" class="transaction-row" onclick="toggleAllocations(<?php echo $transaction_id; ?>)">
                            <td class="arrow-cell">&#9658;</td>
                            <td><?php echo htmlspecialchars($transaction['purchase_date']); ?></td>
                            <td><?php echo htmlspecialchars($transaction['sell_date']); ?></td>
                            <td><?php echo number_format($adjusted_investment, 2); ?></td>
                            <td><?php echo number_format($transaction['tax'], 2); ?></td>
                            <td><?php echo number_format($transaction['gain_loss'], 2); ?></td>
                        </tr>
                        <?php if (isset($allocations[$transaction_id])): ?>
                            <tr id="allocations-<?php echo $transaction_id; ?>" class="allocation-table">
                                <td colspan="6">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Stock Ticker</th>
                                                <th>Allocation (%)</th>
                                                <th>Allocation Amount ($)</th>
                                                <th>Gain/Loss ($)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($allocations[$transaction_id] as $alloc): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($alloc['stock_ticker']); ?></td>
                                                    <td><?php echo number_format($alloc['allocation_percentage'], 2); ?></td>
                                                    <td><?php echo number_format($alloc['allocation_amount'], 2); ?></td>
                                                    <td><?php echo number_format($alloc['gain_loss'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($total_transactions > 1): ?>
                        <tr class="total-row">
                            <td colspan="3" style="text-align: right;"><strong>Total:</strong></td>
                            <td><?php echo number_format($total_investment, 2); ?></td>
                            <td><?php echo number_format($total_tax, 2); ?></td>
                            <td><?php echo number_format($total_gain_loss, 2); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>
</html>
