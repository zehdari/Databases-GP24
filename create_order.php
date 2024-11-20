<?php
session_start();
require 'includes/db_connect.php'; // Ensure this file initializes $conn

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch available dates from the stocks table
$available_dates_query = "
    SELECT DISTINCT price_date
    FROM stocks
    ORDER BY price_date ASC
";
$result = $conn->query($available_dates_query);
$available_dates = [];
while ($row = $result->fetch_assoc()) {
    $available_dates[] = $row['price_date'];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $total_investment = floatval($_POST['total_investment']);
    $purchase_date = $_POST['purchase_date'];
    $sell_date = $_POST['sell_date'];
    $allocations = json_decode($_POST['allocations'], true);

    // Calculate brokerage fee (1% of total investment)
    $brokerage_fee = 0.01 * $total_investment;
    $net_investment = $total_investment - $brokerage_fee;

    // Date validation
    $errors = array();

    // Check if purchase date and sell date are in available dates
    if (!in_array($purchase_date, $available_dates)) {
        $errors[] = 'Purchase date is not available.';
    }
    if (!in_array($sell_date, $available_dates)) {
        $errors[] = 'Sell date is not available.';
    }

    // Validate total investment amount
    if ($total_investment <= 0) {
        $errors[] = 'Total investment amount must be greater than $0.00.';
    }

    // Check if purchase date is before sell date
    if ($purchase_date >= $sell_date) {
        $errors[] = 'Purchase date must be before the sell date.';
    }

    // Validate allocations
    $total_allocation = 0;
    foreach ($allocations as $stock => $allocation) {
        $total_allocation += floatval($allocation);
    }
    if (round($total_allocation, 2) != 100.00) {
        $errors[] = 'Total allocation must equal 100%.';
    }

    if (empty($errors)) {
        // Proceed to save the transaction
        try {
            // Begin transaction
            $conn->begin_transaction();

            // Insert into transactions table
            $stmt = $conn->prepare("INSERT INTO transactions (user_id, total_investment, purchase_date, sell_date) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('idss', $user_id, $total_investment, $purchase_date, $sell_date);
            $stmt->execute();
            $stmt->close();

            // Get the last inserted transaction ID
            $transaction_id = $conn->insert_id;

            // Prepare statements
            $allocation_stmt = $conn->prepare("INSERT INTO transaction_allocations (transaction_id, stock_ticker, allocation_percentage, allocation_amount) VALUES (?, ?, ?, ?)");
            $price_stmt = $conn->prepare("SELECT closing_price FROM stocks WHERE stock_symbol = ? AND price_date = ?");
            $update_alloc_stmt = $conn->prepare("UPDATE transaction_allocations SET gain_loss = ? WHERE transaction_id = ? AND stock_ticker = ?");

            $total_gain_loss = 0;

            foreach ($allocations as $stock => $allocation_percentage) {
                $allocation_amount = ($allocation_percentage / 100) * $net_investment;

                // Insert allocation
                $allocation_stmt->bind_param('isid', $transaction_id, $stock, $allocation_percentage, $allocation_amount);
                $allocation_stmt->execute();

                // Get purchase price
                $price_stmt->bind_param('ss', $stock, $purchase_date);
                $price_stmt->execute();
                $price_result = $price_stmt->get_result();
                $purchase_price_row = $price_result->fetch_assoc();

                // Get sell price
                $price_stmt->bind_param('ss', $stock, $sell_date);
                $price_stmt->execute();
                $price_result = $price_stmt->get_result();
                $sell_price_row = $price_result->fetch_assoc();

                if ($purchase_price_row && $sell_price_row) {
                    $purchase_price = $purchase_price_row['closing_price'];
                    $sell_price = $sell_price_row['closing_price'];

                    // Calculate gain/loss per allocation
                    $quantity = $allocation_amount / $purchase_price;
                    $proceeds = $quantity * $sell_price;
                    $gain_loss = $proceeds - $allocation_amount;

                    // Update gain_loss in transaction_allocations table
                    $update_alloc_stmt->bind_param('dis', $gain_loss, $transaction_id, $stock);
                    $update_alloc_stmt->execute();

                    $total_gain_loss += $gain_loss;
                } else {
                    // Handle missing price data
                    $conn->rollback();
                    $error = 'Price data not available for ' . $stock . ' on selected dates.';
                    break;
                }
            }

            // Close prepared statements
            $allocation_stmt->close();
            $price_stmt->close();
            $update_alloc_stmt->close();

            if (!isset($error)) {
                // Calculate IRS tax (20% of gain)
                $irs_tax = $total_gain_loss > 0 ? 0.20 * $total_gain_loss : 0;

                // Insert into transaction_fees table
                $fee_stmt = $conn->prepare("INSERT INTO transaction_fees (transaction_id, brokerage_fee, irs_tax) VALUES (?, ?, ?)");
                $fee_stmt->bind_param('idd', $transaction_id, $brokerage_fee, $irs_tax);
                $fee_stmt->execute();
                $fee_stmt->close();

                // Calculate net gain/loss after taxes
                $net_gain_loss = $total_gain_loss - $irs_tax;

                // Update gain_loss in transactions table
                $update_trans_stmt = $conn->prepare("UPDATE transactions SET gain_loss = ? WHERE transaction_id = ?");
                $update_trans_stmt->bind_param('di', $net_gain_loss, $transaction_id);
                $update_trans_stmt->execute();
                $update_trans_stmt->close();

                // Commit transaction
                $conn->commit();

                // Redirect to dashboard
                header("Location: dashboard.php");
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            // Rollback transaction on error
            if ($conn->errno) {
                $conn->rollback();
            }
            $error = 'Error saving order: ' . $e->getMessage();
        }
    } else {
        // Handle errors
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Head content remains the same -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Order</title>
    <link rel="stylesheet" href="styles.css">
    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Include flatpickr CSS and JS for date pickers -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>
    <?php require 'includes/header.php'; ?>
    <main class="dashboard">
        <h2 class="center-text">Create Order</h2>
        <?php if (isset($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <!-- No need to check for $success since we redirect on success -->
        <form method="POST" action="create_order.php" class="order-form">
            <div class="input-group">
                <label for="total_investment">Total Investment Amount ($):</label>
                <input type="number" name="total_investment" id="total_investment" value="0.00" min="0.01" step="0.01" required>
            </div>

            <div class="input-group">
                <!-- Display fee and net investment amount -->
                <small id="fee-and-net-display"></small>
            </div>

            <div class="input-group">
                <label for="purchase_date">Purchase Date:</label>
                <input type="text" name="purchase_date" id="purchase_date" required>
            </div>

            <div class="input-group">
                <label for="sell_date">Sell Date:</label>
                <input type="text" name="sell_date" id="sell_date" required>
            </div>

            <h3>Stock Allocations</h3>
            <div class="chart-container">
                <canvas id="allocation-chart"></canvas>
            </div>
            <div id="sliders-container">
                <?php foreach (['AAPL', 'AMZN', 'GOOGL', 'META'] as $stock): ?>
                    <div class="slider-group">
                        <label for="slider-<?php echo $stock; ?>"><?php echo $stock; ?>:</label>
                        <input type="range" id="slider-<?php echo $stock; ?>" name="slider-<?php echo $stock; ?>" min="0" max="100" value="0" step="0.01">
                        <input type="text" id="dollar-<?php echo $stock; ?>" class="allocation-dollar" value="">
                        <span id="percent-<?php echo $stock; ?>">0.00%</span>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="allocations" id="allocations-input">
            <button type="submit" class="btn" id="submit-button" disabled>Submit</button>
        </form>
    </main>
    <script>
        // JavaScript code
        const totalInvestmentInput = document.getElementById('total_investment');
        const feeAndNetDisplay = document.getElementById('fee-and-net-display');
        const stocks = ['AAPL', 'AMZN', 'GOOGL', 'META'];
        const sliders = stocks.map(stock => document.getElementById(`slider-${stock}`));
        const dollarInputs = stocks.map(stock => document.getElementById(`dollar-${stock}`));
        const percentDisplays = stocks.map(stock => document.getElementById(`percent-${stock}`));
        const allocationsInput = document.getElementById('allocations-input');
        const submitButton = document.getElementById('submit-button');
        const ctx = document.getElementById('allocation-chart').getContext('2d');

        // New references to date inputs
        const purchaseDateInput = document.getElementById('purchase_date');
        const sellDateInput = document.getElementById('sell_date');

        // Available dates from PHP
        const availableDates = <?php echo json_encode($available_dates); ?>;

        // Initialize flatpickr for date inputs
        flatpickr(purchaseDateInput, {
            dateFormat: 'Y-m-d',
            enable: availableDates,
            onChange: validateForm
        });

        flatpickr(sellDateInput, {
            dateFormat: 'Y-m-d',
            enable: availableDates,
            onChange: validateForm
        });

        // Initialize Chart.js pie chart
        const chart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: [...stocks, 'Unallocated'],
                datasets: [{
                    data: [...sliders.map(slider => parseFloat(slider.value)), 100],
                    backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#E0E0E0'] // Add gray for "Unallocated"
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: true }
                }
            }
        });

        function updateInvestmentDetails() {
            const totalInvestment = parseFloat(totalInvestmentInput.value) || 0;
            const brokerageFee = totalInvestment * 0.01;
            const netInvestment = totalInvestment - brokerageFee;

            feeAndNetDisplay.textContent = `Brokerage Fee (1%): $${brokerageFee.toFixed(2)} | Net Investment Amount: $${netInvestment.toFixed(2)}`;

            return netInvestment;
        }

        function updateSlidersAndChart(sourceElement = null) {
            const netInvestment = updateInvestmentDetails();

            let totalAllocation = sliders.reduce((sum, slider) => sum + parseFloat(slider.value), 0);

            sliders.forEach((slider, index) => {
                let percentage = parseFloat(slider.value);

                // If moving the slider causes total to exceed 100%, adjust it
                if ((sourceElement === slider || sourceElement === totalInvestmentInput) && totalAllocation > 100) {
                    const excess = totalAllocation - 100;
                    percentage = percentage - excess;
                    if (percentage < 0) percentage = 0;
                    slider.value = percentage;
                    totalAllocation = 100; // Cap allocation
                }

                // Update percent display
                percentDisplays[index].textContent = `${percentage.toFixed(2)}%`;

                // Update dollar input if the change is not coming from the dollar input
                if (sourceElement !== dollarInputs[index]) {
                    const dollarAmount = ((percentage / 100) * netInvestment).toFixed(2); // Use net investment
                    dollarInputs[index].value = netInvestment > 0 ? dollarAmount : '';
                }
            });

            // Update Chart.js data
            const allocatedData = sliders.map(slider => parseFloat(slider.value));
            const unallocated = Math.max(0, 100 - totalAllocation);
            chart.data.datasets[0].data = [...allocatedData, unallocated];
            chart.update();

            // Update hidden input for backend
            allocationsInput.value = JSON.stringify(Object.fromEntries(stocks.map((stock, index) => [stock, parseFloat(sliders[index].value)])));

            // Call validateForm at the end
            validateForm();

            // Toggle allocation inputs
            toggleAllocationInputs();
        }

        function toggleAllocationInputs() {
            const netInvestment = updateInvestmentDetails();

            const disabled = netInvestment <= 0;

            sliders.forEach(slider => {
                slider.disabled = disabled;
            });

            dollarInputs.forEach(input => {
                input.disabled = disabled;
            });
        }

        function dollarInputChanged(index) {
            const inputValue = dollarInputs[index].value.trim();
            const netInvestment = updateInvestmentDetails();

            // Interpret empty input as zero
            let dollarValue = inputValue === '' ? 0 : parseFloat(inputValue);

            // Handle invalid input
            if (isNaN(dollarValue) || dollarValue < 0) {
                alert('Please enter a valid number for the dollar amount.');
                dollarInputs[index].value = '';
                sliders[index].value = 0;
                updateSlidersAndChart(dollarInputs[index]);
                return;
            }

            // Cap dollarValue to netInvestment
            if (dollarValue > netInvestment) {
                dollarValue = netInvestment;
                dollarInputs[index].value = dollarValue.toFixed(2);
            }

            // Calculate sum of other allocations
            let otherAllocations = 0;
            dollarInputs.forEach((input, idx) => {
                if (idx !== index) {
                    const val = parseFloat(input.value) || 0;
                    otherAllocations += val;
                }
            });

            // Adjust dollarValue if total allocations exceed netInvestment
            if (dollarValue + otherAllocations > netInvestment) {
                dollarValue = netInvestment - otherAllocations;
                if (dollarValue < 0) {
                    dollarValue = 0;
                }
                dollarInputs[index].value = dollarValue.toFixed(2);
            }

            // Calculate percentage
            const percentage = netInvestment > 0 ? (dollarValue / netInvestment) * 100 : 0;
            const roundedPercentage = Math.round(percentage * 100) / 100; // Round percentage to two decimals
            sliders[index].value = roundedPercentage;
            updateSlidersAndChart(dollarInputs[index]);
        }

        function validateForm() {
            // Check net investment amount
            const netInvestment = updateInvestmentDetails();
            const investmentValid = netInvestment > 0.00;

            // Check total allocation
            const totalAllocation = sliders.reduce((sum, slider) => sum + parseFloat(slider.value), 0);
            const allocationValid = Math.round(totalAllocation * 100) / 100 === 100;

            // Check dates
            const purchaseDate = purchaseDateInput.value;
            const sellDate = sellDateInput.value;

            let datesValid = true;

            if (purchaseDate && sellDate) {
                const purchaseTimestamp = new Date(purchaseDate).getTime();
                const sellTimestamp = new Date(sellDate).getTime();

                // Check if purchase date is before sell date
                if (purchaseTimestamp >= sellTimestamp) {
                    datesValid = false;
                }
            } else {
                datesValid = false;
            }

            // Enable or disable submit button
            if (investmentValid && allocationValid && datesValid) {
                submitButton.disabled = false;
            } else {
                submitButton.disabled = true;
            }
        }

        totalInvestmentInput.addEventListener('input', () => {
            updateSlidersAndChart(totalInvestmentInput);
            validateForm();
        });

        sliders.forEach(slider =>
            slider.addEventListener('input', event => updateSlidersAndChart(event.target))
        );

        dollarInputs.forEach((input, index) => {
            input.addEventListener('input', () => dollarInputChanged(index));
        });

        // Initial update
        updateSlidersAndChart();
    </script>
</body>
</html>
