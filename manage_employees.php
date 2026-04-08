<?php
include 'db.php';
include 'header.php';
?>
<!-- Make sure Bootstrap JS is included -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<?php
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'assign':
                $employee_id = $mysqli->real_escape_string($_POST['employee_id']);
                $employee_name = $mysqli->real_escape_string($_POST['employee_name']);
                
                $sql = "INSERT INTO employees (employee_id, employee_name) VALUES ('$employee_id', '$employee_name')";
                if ($mysqli->query($sql)) {
                    $_SESSION['message'] = "<div class='alert alert-success'>Employee assigned successfully.</div>";
                } else {
                    $_SESSION['message'] = "<div class='alert alert-danger'>Error assigning employee.</div>";
                }
                break;

            case 'update':
                $employee_id = $mysqli->real_escape_string($_POST['employee_id']);
                $employee_name = $mysqli->real_escape_string($_POST['new_employee_name']);
                
                $sql = "UPDATE employees SET employee_name = '$employee_name' WHERE employee_id = '$employee_id'";
                if ($mysqli->query($sql)) {
                    $_SESSION['message'] = "<div class='alert alert-success'>Employee updated successfully.</div>";
                } else {
                    $_SESSION['message'] = "<div class='alert alert-danger'>Error updating employee.</div>";
                }
                break;

            case 'unassign':
                $employee_id = $mysqli->real_escape_string($_POST['employee_id']);
                
                $sql = "DELETE FROM employees WHERE employee_id = '$employee_id'";
                if ($mysqli->query($sql)) {
                    $_SESSION['message'] = "<div class='alert alert-success'>Employee unassigned successfully.</div>";
                } else {
                    $_SESSION['message'] = "<div class='alert alert-danger'>Error unassigning employee.</div>";
                }
                break;
        }
        header("Location: manage_employees.php");
        exit();
    }
}

// Fetch all employees
$query = "SELECT * FROM employees ORDER BY employee_name";
$result = $mysqli->query($query);
?>

<div class="container mt-4">
    <?php
    if(isset($_SESSION['message'])) {
        echo $_SESSION['message'];
        unset($_SESSION['message']);
    }
    ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Manage Employees</h2>
        <div>
            <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#assignEmployeeForm">
                <i class="fas fa-plus"></i> Add New Employee
            </button>
            <a href="index.php" class="btn btn-secondary">Back to Logs</a>
        </div>
    </div>

    <!-- Assign New Employee Form -->
    <div class="collapse mb-4" id="assignEmployeeForm">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Assign New Employee</h4>
                <button type="button" class="btn-close btn-close-white" aria-label="Close" id="closeAssignForm"></button>
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="assign">
                    <div class="col-md-5">
                        <label for="employee_id" class="form-label">Employee ID</label>
                        <input type="text" class="form-control" id="employee_id" name="employee_id" placeholder="Enter Employee ID" required>
                    </div>
                    <div class="col-md-5">
                        <label for="employee_name" class="form-label">Employee Name</label>
                        <input type="text" class="form-control" id="employee_name" name="employee_name" placeholder="Enter Employee Name" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-success w-100">Assign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Employees Table -->
    <div class="card">
        <div class="card-header">
            <h4>Current Employees</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Employee ID</th>
                            <th>Employee Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['employee_id']); ?></td>
                                <td>
                                    <form method="post" class="update-form d-none" id="form_<?php echo $row['employee_id']; ?>">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($row['employee_id']); ?>">
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="new_employee_name" 
                                                   value="<?php echo htmlspecialchars($row['employee_name']); ?>">
                                            <button type="submit" class="btn btn-success btn-sm">Save</button>
                                            <button type="button" class="btn btn-secondary btn-sm cancel-edit">Cancel</button>
                                        </div>
                                    </form>
                                    <span class="employee-name"><?php echo htmlspecialchars($row['employee_name']); ?></span>
                                </td>
                                <td>
                                    <a href="index.php?search_employee=<?php echo urlencode($row['employee_id']); ?>" 
                                       class="btn btn-info btn-sm">View Records</a>
                                    <button class="btn btn-warning btn-sm edit-btn">Edit</button>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="unassign">
                                        <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($row['employee_id']); ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" 
                                                onclick="return confirm('Are you sure you want to unassign this employee?')">
                                            Unassign
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize collapse functionality for the assign form
    const addButton = document.querySelector('[data-bs-toggle="collapse"]');
    const assignForm = document.getElementById('assignEmployeeForm');
    const closeButton = document.getElementById('closeAssignForm');
    let bsCollapse;
    
    if (addButton && assignForm) {
        bsCollapse = new bootstrap.Collapse(assignForm, {
            toggle: false
        });
        
        addButton.addEventListener('click', function() {
            if (assignForm.classList.contains('show')) {
                bsCollapse.hide();
            } else {
                bsCollapse.show();
            }
        });

        // Add close button functionality
        closeButton.addEventListener('click', function() {
            bsCollapse.hide();
        });
    }

    // Handle edit button clicks
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            row.querySelector('.employee-name').style.display = 'none';
            row.querySelector('.update-form').classList.remove('d-none');
        });
    });

    // Handle cancel button clicks
    document.querySelectorAll('.cancel-edit').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            row.querySelector('.employee-name').style.display = '';
            row.querySelector('.update-form').classList.add('d-none');
        });
    });

    // Auto-show form if there was an error (message contains 'Error')
    if (document.querySelector('.alert-danger')) {
        if (assignForm && bsCollapse) {
            bsCollapse.show();
        }
    }
});
</script>

<style>
.update-form.d-none {
    display: none !important;
}
.employee-name {
    display: inline-block;
    min-height: 24px;
}
.card-header h4 {
    margin-bottom: 0;
}
#assignEmployeeForm {
    transition: all 0.3s ease;
}
.collapse {
    display: none;
}
.collapse.show {
    display: block;
}
.btn-close-white {
    cursor: pointer;
}
.btn-close:focus {
    box-shadow: none;
}
</style>

<div style="position: fixed; bottom: 0; left: 0; right: 0; text-align: center;">
    <?php include 'footer.php'; ?>
</div>