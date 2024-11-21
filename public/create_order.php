<?php
require '../includes/header.php';

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

    // Allow a small margin for floating-point precision
    if (abs($total_allocation - $net_investment) > 0.01) {
        $errors[] = 'Total allocation must equal the net investment amount ($' . number_format($net_investment, 2) . ').';
    }

    if (empty($errors)) {
        try {
            // Begin transaction
            $conn->begin_transaction();
    
            // Insert into transactions table
            $stmt = $conn->prepare("INSERT INTO transactions (user_id, total_investment, fees, tax, gain_loss, purchase_date, sell_date) VALUES (?, ?, ?, ?, ?, ?, ?)");

            // Set default tax and gain/loss as 0 initially; they will be updated after processing allocations
            $initial_gain_loss = 0;
            $initial_tax = 0;
            $stmt->bind_param('idddsss', $user_id, $total_investment, $brokerage_fee, $initial_tax, $initial_gain_loss, $purchase_date, $sell_date);
            $stmt->execute();
    
            // Get the last inserted transaction ID
            $transaction_id = $conn->insert_id;
            $stmt->close();
    
            if (!$transaction_id) {
                throw new Exception("Failed to insert transaction into database.");
            }
    
            // Initialize total_gain_loss
            $total_gain_loss = 0;
    
            // Prepare statements for allocations
            $allocation_stmt = $conn->prepare("INSERT INTO transaction_allocations (transaction_id, stock_ticker, allocation_amount, gain_loss) VALUES (?, ?, ?, ?)");
            $price_stmt = $conn->prepare("SELECT closing_price FROM stocks WHERE stock_symbol = ? AND price_date = ?");
    
            foreach ($allocations as $stock => $allocation_amount) {
                $allocation_amount = floatval($allocation_amount);

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
    
                    // Insert allocation into the transaction_allocations table
                    $allocation_stmt->bind_param('isdd', $transaction_id, $stock, $allocation_amount, $gain_loss);
                    $allocation_stmt->execute();
    
                    $total_gain_loss += $gain_loss;
                } else {
                    $conn->rollback();
                    throw new Exception('Price data not available for ' . $stock);
                }
            }
    
            // Calculate IRS tax (20% of total gain)
            $irs_tax = $total_gain_loss > 0 ? 0.20 * $total_gain_loss : 0;
    
            // Calculate net gain/loss after taxes
            $net_gain_loss = $total_gain_loss - $irs_tax;
    
            // Update the transaction with the final gain/loss and tax
            $update_stmt = $conn->prepare("UPDATE transactions SET tax = ?, gain_loss = ? WHERE transaction_id = ?");
            $update_stmt->bind_param('ddi', $irs_tax, $net_gain_loss, $transaction_id);
            $update_stmt->execute();
            $update_stmt->close();
    
            // Close prepared statements
            $allocation_stmt->close();
            $price_stmt->close();
    
            // Commit transaction
            $conn->commit();
    
            // Redirect to dashboard
            header("Location: dashboard.php");
            exit;
        } catch (mysqli_sql_exception $e) {
            // Rollback transaction on error
            if ($conn->errno) {
                $conn->rollback();
            }
            $error = 'Error saving order: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Order</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>
    <main class="dashboard">
        <h2 class="center-text">Create Order</h2>
        <?php if (isset($error)): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST" action="create_order.php" class="order-form">
            <div class="input-group">
                <label for="total_investment">Total Investment Amount ($):</label>
                <input type="number" name="total_investment" id="total_investment" value="100.00" min="0.01" step="0.01" required>
            </div>

            <div class="input-group">
                <small id="fee-and-net-display">Brokerage Fee (1%): $1.00 | Net Investment Amount: $99.00</small>
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
                        <label for="slider-<?php echo $stock; ?>"><?php echo $stock; ?> ($):</label>
                        <input type="range" id="slider-<?php echo $stock; ?>" name="slider-<?php echo $stock; ?>" min="0" max="100" value="0" step="0.01">
                        <input type="number" id="dollar-<?php echo $stock; ?>" class="allocation-dollar" value="0.00" min="0" step="0.01">
                        <span id="allocated-<?php echo $stock; ?>" class="allocated-display">$0.00</span>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="allocations" id="allocations-input">
            <button type="submit" class="btn" id="submit-button" disabled>Submit</button>
        </form>
    </main>
    <script>
        const totalInvestmentInput = document.getElementById('total_investment');
        const feeAndNetDisplay = document.getElementById('fee-and-net-display');
        const stocks = ['AAPL', 'AMZN', 'GOOGL', 'META'];
        const sliders = stocks.map(stock => document.getElementById(`slider-${stock}`));
        const dollarInputs = stocks.map(stock => document.getElementById(`dollar-${stock}`));
        const allocatedDisplays = stocks.map(stock => document.getElementById(`allocated-${stock}`));
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
            allowInput: true,
            onChange: validateForm
        });

        flatpickr(sellDateInput, {
            dateFormat: 'Y-m-d',
            enable: availableDates,
            allowInput: true,
            onChange: validateForm
        });

        // Initialize Chart.js pie chart
        const chart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: stocks.concat(['Unallocated']),
                datasets: [{
                    data: stocks.map(() => 0).concat([0]),
                    backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#E0E0E0']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: true }
                }
            }
        });

        let netInvestment = 0;

        function updateInvestmentDetails() {
            const totalInvestment = parseFloat(totalInvestmentInput.value) || 0;
            const brokerageFee = totalInvestment * 0.01;
            netInvestment = totalInvestment - brokerageFee;

            feeAndNetDisplay.textContent = `Brokerage Fee (1%): $${brokerageFee.toFixed(2)} | Net Investment Amount: $${netInvestment.toFixed(2)}`;

            // Update sliders' max to netInvestment
            sliders.forEach(slider => {
                slider.max = netInvestment.toFixed(2);
            });

            // Update dollar inputs' max to netInvestment
            dollarInputs.forEach((input, index) => {
            input.addEventListener('input', () => {
                let dollarValue = input.value.trim() === '' ? 0 : parseFloat(input.value);

                // Handle invalid input
                if (isNaN(dollarValue) || dollarValue < 0) {
                    input.value = sliders[index].value;
                    return;
                }

                // Cap dollarValue to netInvestment
                if (dollarValue > netInvestment) {
                    dollarValue = netInvestment;
                    input.value = dollarValue.toFixed(2);
                }

                // Calculate sum of other allocations
                let otherAllocations = dollarInputs.reduce((sum, input, idx) => {
                    return idx !== index ? sum + (parseFloat(input.value) || 0) : sum;
                }, 0);

                // Adjust dollarValue if total allocations exceed netInvestment
                if (dollarValue + otherAllocations > netInvestment) {
                    dollarValue = netInvestment - otherAllocations;
                    dollarValue = dollarValue >= 0 ? dollarValue : 0;
                    input.value = dollarValue.toFixed(2);
                }

                // Update slider and display
                sliders[index].value = dollarValue.toFixed(2);
                allocatedDisplays[index].textContent = `$${dollarValue.toFixed(2)}`;

                updateSlidersAndChart(input);
            });

            // Add blur event to ensure formatting
            input.addEventListener('blur', () => {
                if (input.value.trim() === '') {
                    input.value = '0.00';
                } else {
                    input.value = parseFloat(input.value).toFixed(2);
                }
            });
        });

            return netInvestment;
        }

        function updateSlidersAndChart(sourceElement = null) {
            const currentNetInvestment = updateInvestmentDetails();

            let totalAllocation = sliders.reduce((sum, slider) => sum + parseFloat(slider.value), 0);

            // If total allocation exceeds net investment, adjust the source slider
            if (totalAllocation > currentNetInvestment && sourceElement) {
                const excess = totalAllocation - currentNetInvestment;
                const newValue = parseFloat(sourceElement.value) - excess;
                sourceElement.value = newValue >= 0 ? newValue : 0;
                totalAllocation = sliders.reduce((sum, slider) => sum + parseFloat(slider.value), 0);
            }

            // Update allocated displays and dollar inputs
            sliders.forEach((slider, index) => {
                const allocation = parseFloat(slider.value) || 0;
                dollarInputs[index].value = allocation.toFixed(2);
                allocatedDisplays[index].textContent = `$${allocation.toFixed(2)}`;
            });

            // Update Chart.js data
            const allocatedData = sliders.map(slider => parseFloat(slider.value) || 0);
            const unallocated = Math.max(0, currentNetInvestment - allocatedData.reduce((a, b) => a + b, 0));
            chart.data.datasets[0].data = [...allocatedData, unallocated];
            chart.update();

            // Update hidden input for backend
            allocationsInput.value = JSON.stringify(Object.fromEntries(stocks.map((stock, index) => [stock, parseFloat(sliders[index].value) || 0])));

            // Call validateForm at the end
            validateForm();

            // Toggle allocation inputs
            toggleAllocationInputs();
        }

        function toggleAllocationInputs() {
            const disabled = netInvestment <= 0;

            sliders.forEach(slider => {
                slider.disabled = disabled;
            });

            dollarInputs.forEach(input => {
                input.disabled = disabled;
            });
        }

        function dollarInputChanged(index) {
            let dollarValue = parseFloat(dollarInputs[index].value) || 0;

            // Handle invalid input
            if (isNaN(dollarValue) || dollarValue < 0) {
                alert('Please enter a valid number for the dollar amount.');
                dollarInputs[index].value = sliders[index].value;
                return;
            }

            // Cap dollarValue to netInvestment
            if (dollarValue > netInvestment) {
                dollarValue = netInvestment;
                dollarInputs[index].value = dollarValue.toFixed(2);
            }

            // Calculate sum of other allocations
            let otherAllocations = dollarInputs.reduce((sum, input, idx) => {
                return idx !== index ? sum + (parseFloat(input.value) || 0) : sum;
            }, 0);

            // Adjust dollarValue if total allocations exceed netInvestment
            if (dollarValue + otherAllocations > netInvestment) {
                dollarValue = netInvestment - otherAllocations;
                dollarValue = dollarValue >= 0 ? dollarValue : 0;
                dollarInputs[index].value = dollarValue.toFixed(2);
            }

            // Update slider value
            sliders[index].value = dollarValue.toFixed(2);
            allocatedDisplays[index].textContent = `$${dollarValue.toFixed(2)}`;

            updateSlidersAndChart(dollarInputs[index]);
        }

        function validateForm() {
            // Check net investment amount
            const investmentValid = netInvestment > 0.00;

            // Check total allocation
            const totalAllocation = sliders.reduce((sum, slider) => sum + parseFloat(slider.value), 0);
            const allocationValid = Math.abs(totalAllocation - netInvestment) < 0.01; // Allow small margin

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

        sliders.forEach((slider, index) =>
            slider.addEventListener('input', event => {
                dollarInputs[index].value = parseFloat(event.target.value).toFixed(2);
                allocatedDisplays[index].textContent = `$${parseFloat(event.target.value).toFixed(2)}`;
                updateSlidersAndChart(event.target);
            })
        );

        dollarInputs.forEach((input, index) => {
            input.addEventListener('input', () => dollarInputChanged(index));
        });

        // Initial update
        updateSlidersAndChart();

    </script>
</body>
</html>
