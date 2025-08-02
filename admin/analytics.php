<?php
session_start();
// Check if the user is logged in and is an admin
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'admin') {
    header("location: ../index.php");
    exit;
}

require_once '../includes/db_connect.php';

// Fetching statistics for the dashboard
$total_revenue = $conn->query("SELECT SUM(delivery_fee) as total FROM couriers WHERE status = 'delivered'")->fetch_assoc()['total'];
$delivery_rate = $conn->query("SELECT (COUNT(CASE WHEN status = 'delivered' THEN 1 END) / COUNT(*)) * 100 AS rate FROM couriers WHERE status IN ('delivered', 'cancelled')")->fetch_assoc()['rate'];
$avg_delivery_time_query = $conn->query("SELECT AVG(DATEDIFF(actual_delivery_date, created_at)) as avg_time FROM couriers WHERE status = 'delivered' AND actual_delivery_date IS NOT NULL");
$avg_delivery_time = $avg_delivery_time_query ? $avg_delivery_time_query->fetch_assoc()['avg_time'] : 0;
$customer_satisfaction = 4.8; // Placeholder value

// Fetching data for charts and tables
$delivery_performance_result = $conn->query("SELECT status, COUNT(*) as count FROM couriers GROUP BY status");
$delivery_performance = [];
while($row = $delivery_performance_result->fetch_assoc()){
    $delivery_performance[$row['status']] = $row['count'];
}
$total_deliveries = array_sum($delivery_performance);

$top_routes_result = $conn->query("SELECT pickup_city, delivery_city, COUNT(*) as count FROM couriers GROUP BY pickup_city, delivery_city ORDER BY count DESC LIMIT 5");
$agent_performance_result = $conn->query("SELECT u.name, a.total_couriers, a.rating, u.status FROM agents a JOIN users u ON a.user_id = u.id ORDER BY a.rating DESC LIMIT 4");

include '../includes/header.php';
?>

<body>
    <!-- Sidebar -->
    <?php include '../includes/sidebar_admin.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <?php include '../includes/top_bar.php'; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>Analytics Dashboard</h2>
                <p>Courier insights and performance metrics</p>
            </div>

        </div>

        <!-- Stat Cards (modern, consistent with other admin pages) -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card" title="Total revenue from delivered couriers.">
                    <div class="stat-info">
                        <h3>$<?php echo number_format($total_revenue ?? 0, 2); ?></h3>
                        <p>Total Revenue</p>
                        <span class="stat-sublabel text-success">+12.5% from last period</span>
                    </div>
                    <div class="stat-icon bg-primary-light text-primary">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Delivery success rate.">
                    <div class="stat-info">
                        <h3><?php echo round($delivery_rate ?? 0, 1); ?>%</h3>
                        <p>Delivery Rate</p>
                        <span class="stat-sublabel text-primary">+2.1% from last period</span>
                    </div>
                    <div class="stat-icon bg-success-light text-success">
                        <i class="fas fa-truck"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Average delivery time for completed orders.">
                    <div class="stat-info">
                        <h3><?php echo round($avg_delivery_time ?? 0, 1); ?> days</h3>
                        <p>Avg Delivery Time</p>
                        <span class="stat-sublabel text-danger">-0.4 days from last period</span>
                    </div>
                    <div class="stat-icon bg-primary-light text-primary">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Customer satisfaction rating.">
                    <div class="stat-info">
                        <h3><?php echo $customer_satisfaction; ?>/5</h3>
                        <p>Customer Satisfaction</p>
                        <span class="stat-sublabel text-success">+0.2 from last period</span>
                    </div>
                    <div class="stat-icon bg-success-light text-success">
                        <i class="fas fa-smile"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Dashboard Content -->
        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">Delivery Performance</div>
                    <div class="card-body">
                        <div class="progress-group">
                            <div class="progress-text">
                                <span>On-Time Deliveries</span>
                                <span><?php echo $delivery_performance['delivered'] ?? 0; ?></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success" style="width: <?php echo $total_deliveries > 0 ? (($delivery_performance['delivered'] ?? 0) / $total_deliveries * 100) : 0; ?>%"></div>
                            </div>
                        </div>
                        <div class="progress-group">
                            <div class="progress-text">
                                <span>Delayed Deliveries</span>
                                <span><?php echo $delivery_performance['in_transit'] ?? 0; ?></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-warning" style="width: <?php echo $total_deliveries > 0 ? (($delivery_performance['in_transit'] ?? 0) / $total_deliveries * 100) : 0; ?>%"></div>
                            </div>
                        </div>
                        <div class="progress-group">
                            <div class="progress-text">
                                <span>Failed Deliveries</span>
                                <span><?php echo $delivery_performance['cancelled'] ?? 0; ?></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-danger" style="width: <?php echo $total_deliveries > 0 ? (($delivery_performance['cancelled'] ?? 0) / $total_deliveries * 100) : 0; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">Top Delivery Routes</div>
                    <div class="card-body">
                        <?php while($row = $top_routes_result->fetch_assoc()): ?>
                        <p><?php echo htmlspecialchars($row['pickup_city']); ?> - <?php echo htmlspecialchars($row['delivery_city']); ?></p>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                 <div class="card">
                    <div class="card-header">Agent Performance Overview</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Agent Name</th>
                                        <th>Deliveries</th>
                                        <th>Rating</th>
                                        <th>Efficiency</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $agent_performance_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="agent-info">
                                                <div class="agent-icon"><?php echo strtoupper(substr($row['name'], 0, 1)); ?></div>
                                                <span class="agent-name"><?php echo htmlspecialchars($row['name']); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo $row['total_couriers']; ?></td>
                                        <td class="rating"><i class="fas fa-star text-warning"></i> <?php echo $row['rating']; ?></td>
                                        <td>98%</td>
                                        <td><span class="badge bg-success-subtle text-success-emphasis"><?php echo ucfirst($row['status']); ?></span></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
         <!-- Monthly Delivery Trends (modern line chart) -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><i class="fas fa-chart-line me-2"></i>Monthly Delivery Trends</div>
                    <div class="card-body">
                        <canvas id="deliveryTrendsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('deliveryTrendsChart').getContext('2d');
            const deliveryTrendsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Deliveries',
                        data: [120, 150, 180, 160, 200, 220, 240, 230, 250, 270, 280, 300],
                        borderColor: 'rgba(13, 110, 253, 1)',
                        backgroundColor: 'rgba(13, 110, 253, 0.15)',
                        pointBackgroundColor: 'rgba(13, 110, 253, 1)',
                        pointRadius: 5,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Month',
                                color: '#888',
                                font: { weight: 'bold' }
                            },
                            ticks: { color: '#888' }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Deliveries',
                                color: '#888',
                                font: { weight: 'bold' }
                            },
                            ticks: { color: '#888' }
                        }
                    }
                }
            });
        });
    </script>
    
    <script>
    function downloadAnalytics() {
        // Show loading state
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Downloading...';
        button.disabled = true;
        
        // Simulate download process
        setTimeout(() => {
            // Create analytics data
            const analyticsData = generateAnalyticsData();
            const blob = new Blob([analyticsData], { type: 'application/json' });
            const url = window.URL.createObjectURL(blob);
            
            // Download the file
            const link = document.createElement('a');
            link.href = url;
            link.download = 'analytics_data_' + new Date().toISOString().split('T')[0] + '.json';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
            
            // Reset button
            button.innerHTML = originalText;
            button.disabled = false;
            
            // Show success message
            showAlert('Analytics data downloaded successfully!', 'success');
        }, 1500);
    }
    
    function exportAnalytics() {
        // Show loading state
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
        button.disabled = true;
        
        // Simulate export process
        setTimeout(() => {
            // Create CSV data for analytics
            const csvContent = generateAnalyticsCSV();
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            
            // Download the file
            const link = document.createElement('a');
            link.href = url;
            link.download = 'analytics_report_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
            
            // Reset button
            button.innerHTML = originalText;
            button.disabled = false;
            
            // Show success message
            showAlert('Analytics report exported successfully!', 'success');
        }, 2000);
    }
    
    function generateAnalyticsData() {
        // Generate sample analytics data
        const data = {
            totalRevenue: <?php echo $total_revenue ?? 0; ?>,
            deliveryRate: <?php echo round($delivery_rate ?? 0, 1); ?>,
            avgDeliveryTime: <?php echo round($avg_delivery_time ?? 0, 1); ?>,
            customerSatisfaction: <?php echo $customer_satisfaction; ?>,
            topRoutes: [],
            agentPerformance: []
        };
        
        // Add sample route data
        <?php 
        $top_routes_result->data_seek(0);
        while($row = $top_routes_result->fetch_assoc()): 
        ?>
        data.topRoutes.push({
            route: '<?php echo htmlspecialchars($row['pickup_city']); ?> - <?php echo htmlspecialchars($row['delivery_city']); ?>',
            count: <?php echo $row['count']; ?>
        });
        <?php endwhile; ?>
        
        return JSON.stringify(data, null, 2);
    }
    
    function generateAnalyticsCSV() {
        let csvContent = 'Metric,Value,Change\n';
        csvContent += `Total Revenue,$${<?php echo $total_revenue ?? 0; ?>},+12.5%\n`;
        csvContent += `Delivery Rate,${<?php echo round($delivery_rate ?? 0, 1); ?>}%,+2.1%\n`;
        csvContent += `Avg Delivery Time,${<?php echo round($avg_delivery_time ?? 0, 1); ?>} days,-0.4 days\n`;
        csvContent += `Customer Satisfaction,${<?php echo $customer_satisfaction; ?>/5,+0.2\n`;
        
        return csvContent;
    }
    
    function showAlert(message, type) {
        // Create alert element
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Insert at the top of main content
        const mainContent = document.querySelector('.main-content');
        mainContent.insertBefore(alertDiv, mainContent.firstChild);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
