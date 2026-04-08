<?php
ob_start();
// export.php
include 'db.php';
include 'header.php';

?>

<div class="container" style="max-width: 800px; margin: 80px auto; text-align: justify;">
  <div style="border: 2px solid #1864ab; padding: 20px; border-radius: 8px;">
    <h2 class="text-center">Export Biometrics Data</h2>
    <form method="post" action="export.php">
      <div class="form-group">
        <label for="filter">Select Export Filter:</label>
        <select name="filter" id="filter" class="form-control" required onchange="toggleFilters(this.value)">
          <option value="">--Select Filter--</option>
          <option value="all">All Records</option>
          <option value="employee">By Employee ID</option>
          <option value="monthly">By Monthly</option>
          <option value="range">By Range of Dates</option>
        </select>
      </div>

      <div id="employeeFilter" style="display:none;">
        <div class="form-group">
          <label for="employee_id">Employee ID:</label>
          <input type="text" name="employee_id" id="employee_id" class="form-control">
        </div>
      </div>

      <div id="monthFilter" style="display:none;">
        <div class="form-group">
          <label for="month">Select Month:</label>
          <input type="month" name="month" id="month" class="form-control">
        </div>
      </div>

      <div id="dateRange" style="display:none;">
        <div class="form-group">
          <label for="from_date">From Date:</label>
          <input type="date" name="from_date" id="from_date" class="form-control">
        </div>
        <div class="form-group">
          <label for="to_date">To Date:</label>
          <input type="date" name="to_date" id="to_date" class="form-control">
        </div>
      </div>

      <button type="submit" name="export" class="btn btn-primary">Export to Excel</button>
    </form>
  </div>
</div>

<script>
function toggleFilters(value){
    document.getElementById('employeeFilter').style.display = 'none';
    document.getElementById('monthFilter').style.display = 'none';
    document.getElementById('dateRange').style.display = 'none';
    
    if(value === 'employee'){
        document.getElementById('employeeFilter').style.display = 'block';
    } else if(value === 'monthly'){
        document.getElementById('monthFilter').style.display = 'block';
    } else if(value === 'range'){
        document.getElementById('dateRange').style.display = 'block';
    }
}
</script>

<?php
if(isset($_POST['export'])){
    $filterType = $_POST['filter'];
    $query = "";
    
    // Get current timestamp for the report
    $timestamp = date('Ymd_His');
    
    // Build query based on filter
    if($filterType == 'all') {
        $query = "SELECT b.*, e.employee_name 
                 FROM biometrics_logs b 
                 LEFT JOIN employees e ON b.employee_id = e.employee_id 
                 ORDER BY b.employee_id, b.log_date";
        $filename = "Complete_Attendance_Report_" . $timestamp;
    } elseif($filterType == 'employee'){
        if(empty($_POST['employee_id'])) {
            echo '<div class="alert alert-danger">Please enter an Employee ID.</div>';
            exit;
        }
        $employee_id = $mysqli->real_escape_string($_POST['employee_id']);
        $query = "SELECT b.*, e.employee_name 
                 FROM biometrics_logs b 
                 LEFT JOIN employees e ON b.employee_id = e.employee_id 
                 WHERE b.employee_id = '$employee_id' 
                 ORDER BY b.log_date";
        $filename = "Attendance_Report_EmpID" . sprintf("%04d", $employee_id) . "_" . $timestamp;
    } elseif($filterType == 'monthly'){
        if(empty($_POST['month'])) {
            echo '<div class="alert alert-danger">Please select a month.</div>';
            exit;
        }
        $month = $mysqli->real_escape_string($_POST['month']);
        $year_month = explode('-', $month);
        $year = $year_month[0];
        $month = $year_month[1];
        $query = "SELECT b.*, e.employee_name 
                 FROM biometrics_logs b 
                 LEFT JOIN employees e ON b.employee_id = e.employee_id 
                 WHERE YEAR(b.log_date) = '$year' AND MONTH(b.log_date) = '$month' 
                 ORDER BY b.employee_id, b.log_date";
        $filename = "Monthly_Attendance_Report_" . date("Y_F", strtotime($year . "-" . $month . "-01")) . "_" . $timestamp;
    } elseif($filterType == 'range'){
        if(empty($_POST['from_date']) || empty($_POST['to_date'])) {
            echo '<div class="alert alert-danger">Please select both From and To dates.</div>';
            exit;
        }
        $from = $mysqli->real_escape_string($_POST['from_date']);
        $to = $mysqli->real_escape_string($_POST['to_date']);
        $query = "SELECT b.*, e.employee_name
                 FROM biometrics_logs b 
                 LEFT JOIN employees e ON b.employee_id = e.employee_id 
                 WHERE b.log_date BETWEEN '$from' AND '$to' 
                 ORDER BY b.employee_id, b.log_date";
        $filename = "Attendance_Report_" . date("Ymd", strtotime($from)) . "_to_" . date("Ymd", strtotime($to)) . "_" . $timestamp;
    }
    
    // Execute query
    $result = $mysqli->query($query);
    
    if($result && $result->num_rows > 0) {
        // Disable output buffering
        if(ob_get_level()) ob_end_clean();
        
        // Set headers for Excel download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename . '.csv');
        header('Cache-Control: max-age=0');
        
        // Create a file pointer connected to the output stream
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for proper Excel encoding
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Output the column headings
        fputcsv($output, array('Employee ID', 'Employee Name', 'Date', 'Day', 'Time In', 'Time Out', 'Rendered Hours'));
        
        // Fetch and output each row
        while($row = $result->fetch_assoc()){
            $time_in = strtotime($row['time_in']);
            $time_out = strtotime($row['time_out']);
            
            $total_minutes = round((($time_out - $time_in) / 60) - 60);
            $hours = floor($total_minutes / 60);
            $minutes = $total_minutes % 60;
            $rendered = sprintf("%02d:%02d", $hours, $minutes);
            
            // Format employee name
            $employee_name = isset($row['employee_name']) 
                           ? ucwords(strtolower($row['employee_name']))
                           : 'N/A';
            
            fputcsv($output, array(
                sprintf("%04d", $row['employee_id']), // Pad employee ID with zeros
                $employee_name,
                date('Y-m-d', strtotime($row['log_date'])),
                date('l', strtotime($row['log_date'])),
                date('h:i A', $time_in),
                date('h:i A', $time_out),
                $rendered
            ));
        }
        
        fclose($output);
        exit();
    } else {
        echo '<div class="alert alert-warning">No records found for the selected filter.</div>';
    }
}


?>
<div style="position: fixed; bottom: 0; left: 0; right: 0; text-align: center;">
    <?php include 'footer.php'; ?>
</div>