<?php
// import.php
include 'db.php';
include 'header.php';

if(isset($_POST['submit'])){
    $totalInserted = 0;
    $totalSkipped = 0;
    $fileCount = 0;
    $errorFiles = [];

    foreach($_FILES['datafile']['tmp_name'] as $key => $tmp_name) {
        if($_FILES['datafile']['error'][$key] == 0) {
            $fileCount++;
            $file = $tmp_name;
            $handle = fopen($file, 'r');
            $logs = [];
            $hasData = false;

            if($handle){
                while(($line = fgets($handle)) !== false){
                    $data = explode("\t", trim($line));
                    
                    if(count($data) >= 2){
                        $hasData = true;
                        $employee_id = trim($data[0]);
                        $datetime = trim($data[1]);

                        $date = date('Y-m-d', strtotime($datetime));
                        $time = date('H:i:s', strtotime($datetime));
                        
                        // Group by employee ID + date
                        $key = $employee_id . '_' . $date;
                        if(!isset($logs[$key])){
                            $logs[$key] = ['in' => null, 'out' => null];
                        }
                        
                        // Assign the earliest time as "Time In"
                        if($logs[$key]['in'] === null || $time < $logs[$key]['in']){
                            $logs[$key]['in'] = $time;
                        }
                        
                        // Assign the latest time as "Time Out"
                        if($logs[$key]['out'] === null || $time > $logs[$key]['out']){
                            $logs[$key]['out'] = $time;
                        }
                    }
                }
                fclose($handle);

                if(!$hasData) {
                    $errorFiles[] = $_FILES['datafile']['name'][$key] . " (No valid data found)";
                    continue;
                }

                // Insert records into the database
                foreach($logs as $logKey => $log){
                    list($employee_id, $date) = explode('_', $logKey);
                    
                    // Check if record already exists
                    $check_stmt = $mysqli->prepare("SELECT COUNT(*) FROM biometrics_logs WHERE employee_id = ? AND log_date = ?");
                    $check_stmt->bind_param("ss", $employee_id, $date);
                    $check_stmt->execute();
                    $check_stmt->bind_result($count);
                    $check_stmt->fetch();
                    $check_stmt->close();

                    if($count > 0) {
                        $totalSkipped++;
                        continue; // Skip this record
                    }

                    $time_in = $log['in'];
                    $time_out = $log['out'];

                    // Insert new record
                    $stmt = $mysqli->prepare("INSERT INTO biometrics_logs (employee_id, log_date, time_in, time_out) VALUES (?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("ssss", $employee_id, $date, $time_in, $time_out);
                        $stmt->execute();
                        $stmt->close();
                        $totalInserted++;
                    }
                }
                
            } else {
                $errorFiles[] = $_FILES['datafile']['name'][$key] . " (Unable to open file)";
            }
        } else {
            $errorFiles[] = $_FILES['datafile']['name'][$key] . " (Upload error)";
        }
    }

    $message = "Import Summary:\n";
    $message .= "$fileCount files processed\n";
    $message .= "$totalInserted new records inserted\n";
    $message .= "$totalSkipped existing records skipped";
    
    if(!empty($errorFiles)) {
        $message .= "\n\nErrors occurred in the following files:\n" . implode("\n", $errorFiles);
    }

    echo "<script>
        alert(`$message`);
        window.location.href = 'index.php';
    </script>";
    exit();
}
?>

<div class="container" style="max-width: 800px; margin: 80px auto; text-align: justify;">
    <div style="border: 2px solid #1864ab; padding: 20px; border-radius: 8px;">
        <h2 class="text-center">Import Biometrics Data (.dat file)</h2>

        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="datafile">Select .dat file(s):</label>
                <input type="file" name="datafile[]" id="datafile" class="form-control-file" accept=".dat,.txt" required multiple>
            </div>
            <div style="text-align: left;">
                <button type="submit" name="submit" class="btn btn-primary">Import Files</button>
            </div>
        </form>
    </div>
</div>

<div style="position: fixed; bottom: 0; left: 0; right: 0; text-align: center;">
    <?php include 'footer.php'; ?>
</div>
