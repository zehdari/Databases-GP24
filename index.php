<?php
require 'includes/header.php';

// Check if the user is logged in
$is_logged_in = isset($_SESSION['user_id']);

// Fetch stock data
$stocks = ['AAPL', 'AMZN', 'GOOGL', 'META'];
$stock_data = [];

foreach ($stocks as $stock) {
    $result = $conn->query("SELECT price_date, closing_price FROM stocks WHERE stock_symbol = '$stock' ORDER BY price_date ASC");
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'date' => $row['price_date'],
            'price' => $row['closing_price']
        ];
    }
    $stock_data[$stock] = $data;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Investment Simulator</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <main>
        <div class="charts-container">
            <?php foreach ($stocks as $stock): ?>
                <div class="chart">
                    <canvas id="chart-<?php echo $stock; ?>"></canvas>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
    <script>
    const stockData = <?php echo json_encode($stock_data); ?>;
    const colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0'];

    Object.keys(stockData).forEach((stock, index) => {
        const ctx = document.getElementById(`chart-${stock}`).getContext('2d');
        const data = stockData[stock];
        
        const dates = data.map(d => d.date);
        const prices = data.map(d => d.price);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: `${stock} Closing Prices`,
                    data: prices,
                    borderColor: colors[index],
                    backgroundColor: colors[index],
                    fill: false,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Allows the chart to adjust its height
                plugins: {
                    legend: { display: true }
                },
                scales: {
                    x: { 
                        title: { 
                            display: true, 
                            text: 'Date' 
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    },
                    y: { 
                        title: { 
                            display: true, 
                            text: 'Price ($)' 
                        },
                        beginAtZero: false
                    }
                },
                layout: {
                    padding: {
                        left: 10,
                        right: 10,
                        top: 10,
                        bottom: 10
                    }
                }
            }
        });
    });
</script>

</body>
</html>
