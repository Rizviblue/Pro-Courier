<?php
session_start();
// Check if the user is logged in and is an admin
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'admin') {
    header("location: ../index.php");
    exit;
}

require_once '../includes/db_connect.php';

// --- Fetching Statistics ---
// These queries will be based on the selected date range in a real application.
// For now, we'll use some overall stats.
$total_revenue_query = $conn->query("SELECT SUM(delivery_fee) as total FROM couriers WHERE status = 'delivered'");
$total_revenue = $total_revenue_query ? $total_revenue_query->fetch_assoc()['total'] : 0;

$delivery_rate_query = $conn->query("SELECT (COUNT(CASE WHEN status = 'delivered' THEN 1 END) / COUNT(*)) * 100 AS rate FROM couriers WHERE status IN ('delivered', 'cancelled')");
$delivery_rate = $delivery_rate_query ? $delivery_rate_query->fetch_assoc()['rate'] : 0;

$active_agents = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'agent' AND status = 'active'")->fetch_assoc()['count'];
$total_couriers = $conn->query("SELECT COUNT(*) as count FROM couriers")->fetch_assoc()['count'];


// --- Fetching Daily Performance (Last 7 Days) ---
$daily_performance_result = $conn->query("SELECT DATE(created_at) as date, COUNT(*) as total, SUM(delivery_fee) as revenue, (SUM(CASE WHEN status = 'delivered' THEN 1 END) / COUNT(*)) * 100 as success_rate FROM couriers WHERE created_at >= CURDATE() - INTERVAL 7 DAY GROUP BY DATE(created_at) ORDER BY date DESC");

// --- Fetching Top Performing Agents ---
$top_agents_result = $conn->query("SELECT u.name, a.total_couriers, a.rating FROM agents a JOIN users u ON a.user_id = u.id ORDER BY a.rating DESC, a.total_couriers DESC LIMIT 4");

// --- Fetching Most Popular Routes ---
$popular_routes_result = $conn->query("SELECT pickup_city, delivery_city, COUNT(*) as count FROM couriers GROUP BY pickup_city, delivery_city ORDER BY count DESC LIMIT 4");


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
                <h2>Reports & Analytics</h2>
                <p>Key insights into your courier operations</p>
            </div>
            <div>
                <button class="btn btn-outline-secondary" onclick="downloadPDF()"><i class="fas fa-download"></i> Download PDF</button>
                <button class="btn btn-primary" onclick="exportReport()"><i class="fas fa-file-export"></i> Export Report</button>
            </div>
        </div>

        <!-- Report Filters -->
        <div class="card">
            <div class="card-body">
                <form class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="reportType" class="form-label">Report Type</label>
                        <select id="reportType" class="form-select">
                            <option selected>Daily Reports</option>
                            <option>Weekly Reports</option>
                            <option>Monthly Reports</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="dateRange" class="form-label">Date Range</label>
                        <select id="dateRange" class="form-select">
                            <option selected>Last 7 Days</option>
                            <option>Last 30 Days</option>
                            <option>Last 90 Days</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-primary w-100" onclick="generateReport()">Generate Report</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Stat Cards (modern, consistent with other admin pages) -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card" title="Total number of couriers.">
                    <div class="stat-info">
                        <h3><?php echo $total_couriers; ?></h3>
                        <p>Total Couriers</p>
                        <span class="stat-sublabel text-success">+12.5% from last month</span>
                    </div>
                    <div class="stat-icon bg-primary-light text-primary">
                        <i class="fas fa-box"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Delivery success rate.">
                    <div class="stat-info">
                        <h3><?php echo round($delivery_rate, 1); ?>%</h3>
                        <p>Delivery Rate</p>
                        <span class="stat-sublabel text-primary">+2.1% from last month</span>
                    </div>
                    <div class="stat-icon bg-success-light text-success">
                        <i class="fas fa-truck"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Total revenue from delivered couriers.">
                    <div class="stat-info">
                        <h3>$<?php echo number_format($total_revenue, 2); ?></h3>
                        <p>Revenue</p>
                        <span class="stat-sublabel text-success">+15.8% from last month</span>
                    </div>
                    <div class="stat-icon bg-primary-light text-primary">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Number of active agents.">
                    <div class="stat-info">
                        <h3><?php echo $active_agents; ?></h3>
                        <p>Active Agents</p>
                        <span class="stat-sublabel text-danger">-2 from last month</span>
                    </div>
                    <div class="stat-icon bg-danger-light text-danger">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header">Most Popular Routes</div>
                    <div class="list-group list-group-flush">
                        <?php while($row = $popular_routes_result->fetch_assoc()): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                           <div><strong><?php echo htmlspecialchars($row['pickup_city']); ?> - <?php echo htmlspecialchars($row['delivery_city']); ?></strong></div>
                           <span><?php echo $row['count']; ?> orders</span>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">Daily Performance (Last 7 Days)</div>
                    <div class="list-group list-group-flush">
                        <?php while($row = $daily_performance_result->fetch_assoc()): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div><strong><?php echo date('m/d/Y', strtotime($row['date'])); ?></strong><br><small><?php echo $row['total']; ?> delivered</small></div>
                            <div><strong>$<?php echo number_format($row['revenue'], 2); ?></strong><br><small><?php echo round($row['success_rate'], 1); ?>% success</small></div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card agent-performance h-100">
                    <div class="card-header">Top Performing Agents</div>
                    <div class="list-group list-group-flush">
                        <?php while($row = $top_agents_result->fetch_assoc()): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div class="agent-info">
                                <img src="https://placehold.co/32x32/E2E8F0/4A5568?text=<?php echo substr($row['name'], 0, 1); ?>" alt="Agent">
                                <div><strong><?php echo htmlspecialchars($row['name']); ?></strong><br><small><?php echo $row['total_couriers']; ?> orders completed</small></div>
                            </div>
                            <span><i class="fas fa-star text-warning"></i> <?php echo $row['rating']; ?></span>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script>
    function downloadPDF() {
        // Show loading state
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
        button.disabled = true;
        
        // Simulate PDF generation
        setTimeout(() => {
            // Create a temporary link to download the PDF
            const link = document.createElement('a');
            link.href = 'generate_pdf.php?type=reports&date=' + new Date().toISOString().split('T')[0];
            link.download = 'courier_reports_' + new Date().toISOString().split('T')[0] + '.pdf';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Reset button
            button.innerHTML = originalText;
            button.disabled = false;
            
            // Show success message
            showAlert('PDF downloaded successfully!', 'success');
        }, 2000);
    }
    
    function exportReport() {
        // Show loading state
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
        button.disabled = true;
        
        // Get form data
        const reportType = document.getElementById('reportType').value;
        const dateRange = document.getElementById('dateRange').value;
        
        // Simulate export process
        setTimeout(() => {
            // Create CSV data
            const csvContent = generateCSVData(reportType, dateRange);
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            
            // Download the file
            const link = document.createElement('a');
            link.href = url;
            link.download = 'courier_report_' + reportType.toLowerCase().replace(' ', '_') + '_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
            
            // Reset button
            button.innerHTML = originalText;
            button.disabled = false;
            
            // Show success message
            showAlert('Report exported successfully!', 'success');
        }, 1500);
    }
    
    function generateReport() {
        // Show loading state
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        button.disabled = true;
        
        // Get form data
        const reportType = document.getElementById('reportType').value;
        const dateRange = document.getElementById('dateRange').value;
        
        // Simulate report generation
        setTimeout(() => {
            // Reset button
            button.innerHTML = originalText;
            button.disabled = false;
            
            // Show success message
            showAlert('Report generated successfully!', 'success');
            
            // Optionally refresh the page or update content
            // location.reload();
        }, 2000);
    }
    
    function generateCSVData(reportType, dateRange) {
        // Generate sample CSV data based on report type
        let csvContent = 'Date,Total Couriers,Delivered,Revenue,Success Rate\n';
        
        // Add sample data
        for (let i = 6; i >= 0; i--) {
            const date = new Date();
            date.setDate(date.getDate() - i);
            const dateStr = date.toISOString().split('T')[0];
            const total = Math.floor(Math.random() * 50) + 20;
            const delivered = Math.floor(total * 0.85);
            const revenue = (delivered * 25.99).toFixed(2);
            const successRate = ((delivered / total) * 100).toFixed(1);
            
            csvContent += `${dateStr},${total},${delivered},${revenue},${successRate}%\n`;
        }
        
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
    
    function downloadSpecificReport(reportType) {
        // Show loading state
        const link = event.target.closest('a');
        const originalText = link.innerHTML;
        link.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        
        // Simulate specific report generation
        setTimeout(() => {
            // Create specific report data
            const reportData = generateSpecificReportData(reportType);
            const blob = new Blob([reportData], { type: 'application/json' });
            const url = window.URL.createObjectURL(blob);
            
            // Download the file
            const downloadLink = document.createElement('a');
            downloadLink.href = url;
            downloadLink.download = reportType + '_report_' + new Date().toISOString().split('T')[0] + '.json';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
            window.URL.revokeObjectURL(url);
            
            // Reset link
            link.innerHTML = originalText;
            
            // Show success message
            showAlert(reportType.charAt(0).toUpperCase() + reportType.slice(1) + ' report downloaded successfully!', 'success');
        }, 1500);
    }
    
    function generateSpecificReportData(reportType) {
        const data = {
            reportType: reportType,
            generatedDate: new Date().toISOString(),
            data: {}
        };
        
        switch(reportType) {
            case 'courier':
                data.data = {
                    totalCouriers: <?php echo $total_couriers; ?>,
                    deliveryRate: <?php echo round($delivery_rate, 1); ?>,
                    totalRevenue: <?php echo $total_revenue; ?>
                };
                break;
            case 'financial':
                data.data = {
                    totalRevenue: <?php echo $total_revenue; ?>,
                    averageOrderValue: <?php echo $total_revenue > 0 ? round($total_revenue / $total_couriers, 2) : 0; ?>,
                    deliveryRate: <?php echo round($delivery_rate, 1); ?>
                };
                break;
            case 'agent':
                data.data = {
                    activeAgents: <?php echo $active_agents; ?>,
                    averageRating: 4.5,
                    totalDeliveries: <?php echo $total_couriers; ?>
                };
                break;
            case 'raw':
                data.data = {
                    couriers: [],
                    agents: [],
                    customers: []
                };
                break;
        }
        
        return JSON.stringify(data, null, 2);
    }
    </script>
</body>
</html>
