<?php
session_start();
// Check if the user is logged in and is an agent
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'agent') {
    header("location: ../index.php");
    exit;
}

require_once '../includes/db_connect.php';

// Get the agent_id from the user_id stored in session
$agent_id_query = $conn->prepare("SELECT id FROM agents WHERE user_id = ?");
$agent_id_query->bind_param("i", $_SESSION['user_id']);
$agent_id_query->execute();
$agent_id_result = $agent_id_query->get_result();
$agent = $agent_id_result->fetch_assoc();
$agent_id = $agent['id'];

// --- Fetching Statistics for the logged-in agent ---
$my_couriers_query = $conn->query("SELECT COUNT(*) as count FROM couriers WHERE assigned_agent_id = $agent_id");
$my_couriers = $my_couriers_query ? $my_couriers_query->fetch_assoc()['count'] : 0;

$success_rate_query = $conn->query("SELECT (COUNT(CASE WHEN status = 'delivered' THEN 1 END) / COUNT(*)) * 100 AS rate FROM couriers WHERE assigned_agent_id = $agent_id AND status IN ('delivered', 'cancelled')");
$success_rate = $success_rate_query ? $success_rate_query->fetch_assoc()['rate'] : 0;

$delivered_query = $conn->query("SELECT COUNT(*) as count FROM couriers WHERE assigned_agent_id = $agent_id AND status = 'delivered'");
$delivered = $delivered_query ? $delivered_query->fetch_assoc()['count'] : 0;

$in_transit_query = $conn->query("SELECT COUNT(*) as count FROM couriers WHERE assigned_agent_id = $agent_id AND status = 'in_transit'");
$in_transit = $in_transit_query ? $in_transit_query->fetch_assoc()['count'] : 0;

// --- Fetching Recent Activity for the logged-in agent ---
$recent_activity_result = $conn->query("SELECT * FROM couriers WHERE assigned_agent_id = $agent_id ORDER BY created_at DESC LIMIT 4");

include '../includes/header.php';
?>

<body>
    <?php include '../includes/sidebar_agent.php'; ?>

    <div class="main-content">
        <?php include '../includes/top_bar.php'; ?>

        <div class="page-header">
            <div>
                <h2>My Reports</h2>
                <p>View your courier performance and statistics</p>
            </div>
            <button class="btn btn-primary" onclick="downloadAgentReport()"><i class="fas fa-download"></i> Download Report</button>
        </div>

        <!-- Stat Cards (modern, consistent with admin panel) -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card" title="Total couriers assigned to you.">
                    <div class="stat-info">
                        <h3><?php echo $my_couriers; ?></h3>
                        <p>My Couriers</p>
                        <span class="stat-sublabel text-success">-12% from last month</span>
                    </div>
                    <div class="stat-icon bg-primary-light text-primary">
                        <i class="fas fa-box"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Success rate of your deliveries.">
                    <div class="stat-info">
                        <h3><?php echo round($success_rate, 1); ?>%</h3>
                        <p>Success Rate</p>
                        <span class="stat-sublabel text-primary">+1.5% from last month</span>
                    </div>
                    <div class="stat-icon bg-success-light text-success">
                        <i class="fas fa-truck"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Delivered couriers.">
                    <div class="stat-info">
                        <h3><?php echo $delivered; ?></h3>
                        <p>Delivered</p>
                        <span class="stat-sublabel text-success">+0% this week</span>
                    </div>
                    <div class="stat-icon bg-success-light text-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Couriers in transit.">
                    <div class="stat-info">
                        <h3><?php echo $in_transit; ?></h3>
                        <p>In Transit</p>
                        <span class="stat-sublabel text-danger">+0% from yesterday</span>
                    </div>
                    <div class="stat-icon bg-primary-light text-primary">
                        <i class="fas fa-truck-fast"></i>
                    </div>
                </div>
            </div>
        </div>
        <!-- Filters (modern, consistent with admin panel) -->
        <div class="card mb-4">
            <div class="card-header">
                <h4><i class="fas fa-filter"></i> Filters</h4>
            </div>
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
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-primary w-100" onclick="generateAgentReport()">Generate Report</button>
                    </div>
                </form>
            </div>
        </div>
        <!-- Recent Activity and Quick Actions (modern, consistent with admin panel) -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">Recent Activity</div>
                    <div class="list-group list-group-flush">
                        <?php while($row = $recent_activity_result->fetch_assoc()): ?>
                        <div class="list-group-item d-flex align-items-center" style="gap: 0.5rem;">
                            <div style="flex:1;">
                                <strong><?php echo htmlspecialchars($row['tracking_number']); ?></strong><br>
                                <small><?php echo htmlspecialchars($row['pickup_city']); ?> - <?php echo htmlspecialchars($row['delivery_city']); ?></small>
                            </div>
                            <div style="min-width: 100px; text-align: center;">
                                <span class="badge 
                                    <?php 
                                        switch($row['status']) {
                                            case 'in_transit': echo 'bg-primary'; break;
                                            case 'delivered': echo 'bg-success'; break;
                                            case 'pending': echo 'bg-warning text-dark'; break;
                                            case 'cancelled': echo 'bg-danger'; break;
                                        }
                                    ?>
                                "><?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?></span>
                            </div>
                            <div style="min-width: 90px; text-align: right;">
                                <?php echo date('m/d/Y', strtotime($row['created_at'])); ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">Quick Actions</div>
                    <div class="list-group list-group-flush">
                        <a href="#" class="list-group-item list-group-item-action" onclick="downloadSpecificAgentReport('daily')"><i class="fas fa-download"></i> Download Daily Report</a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="downloadSpecificAgentReport('weekly')"><i class="fas fa-download"></i> Download Weekly Report</a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="downloadSpecificAgentReport('monthly')"><i class="fas fa-download"></i> Download Monthly Report</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script>
    function downloadAgentReport() {
        // Show loading state
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        button.disabled = true;
        
        // Simulate report generation
        setTimeout(() => {
            // Create agent report data
            const reportData = generateAgentReportData();
            const blob = new Blob([reportData], { type: 'application/json' });
            const url = window.URL.createObjectURL(blob);
            
            // Download the file
            const link = document.createElement('a');
            link.href = url;
            link.download = 'agent_report_' + new Date().toISOString().split('T')[0] + '.json';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
            
            // Reset button
            button.innerHTML = originalText;
            button.disabled = false;
            
            // Show success message
            showAlert('Agent report downloaded successfully!', 'success');
        }, 2000);
    }
    
    function generateAgentReport() {
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
            // Create CSV data for agent report
            const csvContent = generateAgentCSV(reportType, dateRange);
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            
            // Download the file
            const link = document.createElement('a');
            link.href = url;
            link.download = 'agent_report_' + reportType.toLowerCase().replace(' ', '_') + '_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
            
            // Reset button
            button.innerHTML = originalText;
            button.disabled = false;
            
            // Show success message
            showAlert('Agent report generated successfully!', 'success');
        }, 1500);
    }
    
    function generateAgentReportData() {
        // Generate agent-specific report data
        const data = {
            agentId: <?php echo $agent_id; ?>,
            totalCouriers: <?php echo $my_couriers; ?>,
            successRate: <?php echo round($success_rate, 1); ?>,
            delivered: <?php echo $delivered; ?>,
            inTransit: <?php echo $in_transit; ?>,
            recentActivity: []
        };
        
        // Add recent activity data
        <?php 
        $recent_activity_result->data_seek(0);
        while($row = $recent_activity_result->fetch_assoc()): 
        ?>
        data.recentActivity.push({
            trackingNumber: '<?php echo htmlspecialchars($row['tracking_number']); ?>',
            route: '<?php echo htmlspecialchars($row['pickup_city']); ?> - <?php echo htmlspecialchars($row['delivery_city']); ?>',
            status: '<?php echo $row['status']; ?>',
            date: '<?php echo date('m/d/Y', strtotime($row['created_at'])); ?>'
        });
        <?php endwhile; ?>
        
        return JSON.stringify(data, null, 2);
    }
    
    function generateAgentCSV(reportType, dateRange) {
        let csvContent = 'Date,Total Couriers,Delivered,Success Rate\n';
        
        // Add sample data for the last 7 days
        for (let i = 6; i >= 0; i--) {
            const date = new Date();
            date.setDate(date.getDate() - i);
            const dateStr = date.toISOString().split('T')[0];
            const total = Math.floor(Math.random() * 10) + 5;
            const delivered = Math.floor(total * 0.9);
            const successRate = ((delivered / total) * 100).toFixed(1);
            
            csvContent += `${dateStr},${total},${delivered},${successRate}%\n`;
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
    
    function downloadSpecificAgentReport(reportType) {
        // Show loading state
        const link = event.target.closest('a');
        const originalText = link.innerHTML;
        link.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        
        // Simulate specific agent report generation
        setTimeout(() => {
            // Create specific agent report data
            const reportData = generateSpecificAgentReportData(reportType);
            const blob = new Blob([reportData], { type: 'application/json' });
            const url = window.URL.createObjectURL(blob);
            
            // Download the file
            const downloadLink = document.createElement('a');
            downloadLink.href = url;
            downloadLink.download = 'agent_' + reportType + '_report_' + new Date().toISOString().split('T')[0] + '.json';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
            window.URL.revokeObjectURL(url);
            
            // Reset link
            link.innerHTML = originalText;
            
            // Show success message
            showAlert(reportType.charAt(0).toUpperCase() + reportType.slice(1) + ' agent report downloaded successfully!', 'success');
        }, 1200);
    }
    
    function generateSpecificAgentReportData(reportType) {
        const data = {
            agentId: <?php echo $agent_id; ?>,
            reportType: reportType,
            generatedDate: new Date().toISOString(),
            data: {
                totalCouriers: <?php echo $my_couriers; ?>,
                successRate: <?php echo round($success_rate, 1); ?>,
                delivered: <?php echo $delivered; ?>,
                inTransit: <?php echo $in_transit; ?>
            }
        };
        
        // Add period-specific data
        switch(reportType) {
            case 'daily':
                data.data.period = 'Last 24 hours';
                data.data.periodData = generateDailyData();
                break;
            case 'weekly':
                data.data.period = 'Last 7 days';
                data.data.periodData = generateWeeklyData();
                break;
            case 'monthly':
                data.data.period = 'Last 30 days';
                data.data.periodData = generateMonthlyData();
                break;
        }
        
        return JSON.stringify(data, null, 2);
    }
    
    function generateDailyData() {
        return {
            total: <?php echo $my_couriers; ?>,
            delivered: <?php echo $delivered; ?>,
            successRate: <?php echo round($success_rate, 1); ?>
        };
    }
    
    function generateWeeklyData() {
        return {
            total: <?php echo $my_couriers; ?> * 7,
            delivered: <?php echo $delivered; ?> * 7,
            successRate: <?php echo round($success_rate, 1); ?>
        };
    }
    
    function generateMonthlyData() {
        return {
            total: <?php echo $my_couriers; ?> * 30,
            delivered: <?php echo $delivered; ?> * 30,
            successRate: <?php echo round($success_rate, 1); ?>
        };
    }
    </script>
</body>
</html>
