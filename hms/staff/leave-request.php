<?php
require_once('session_auth.php'); 


// Get today's date for the date input's 'min' attribute
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Apply for Leave | Zion HMS</title>
    
    <!-- Meta Tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimum-scale=1.0, maximum-scale=1.0">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">

    <!-- Fonts and Stylesheets -->
    <link href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="../vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../vendor/fontawesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../vendor/themify-icons/themify-icons.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/plugins.css">
    <link rel="stylesheet" href="../assets/css/themes/theme-1.css" id="skin_color" />

    <!-- Custom Page Styles -->
    <style>
        .panel-white { border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .leave-details-card { margin-top: 20px; border-left: 4px solid #5bc0de; }
        .status-badge { color: white; padding: 4px 10px; border-radius: 10px; font-size: 0.9em; text-transform: capitalize; }
        .status-pending { background-color: #f0ad4e; } 
        .status-approved { background-color: #5cb85c; } 
        .status-rejected { background-color: #d9534f; }
        .main-content { background-color: #f9f9f9; }
        #loading-indicator { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: flex; align-items: center; justify-content: center;
            z-index: 9999; font-size: 1.5em; color: #333;
            flex-direction: column;
        }
        .form-control[readonly] { background-color: #eee; }
    </style>
</head>
<body>
<div id="app">
    <?php include('include/sidebar.php'); ?>
    <div class="app-content">
        <?php include('include/header.php'); ?>
        <div class="main-content">
            <div class="wrap-content container" id="container">
                
                <!-- Page Header -->
                <section id="page-title">
                    <div class="row">
                        <div class="col-sm-8">
                            <h1 class="mainTitle">Staff Leave Application</h1>
                            <span class="mainDescription">Submit and track your leave requests from this dashboard.</span>
                        </div>
                        <ol class="breadcrumb">
                            <li><span>Staff</span></li>
                            <li class="active"><span>Apply for Leave</span></li>
                        </ol>
                    </div>
                </section>

                <!-- System-wide Status Messages -->
                <div id="system-status-container"></div>

                <div class="row">
                    <!-- Leave Request Form -->
                    <div class="col-md-8 col-lg-7">
                        <div class="panel panel-white">
                            <div class="panel-heading"><h5 class="panel-title">New Leave Request</h5></div>
                            <div class="panel-body">
                                <div id="request-status-alert"></div>
                                <form id="leaveRequestForm" role="form" novalidate>
                                    <div class="form-group">
                                        <label for="leaveType">Leave Type</label>
                                        <select id="leaveType" name="leaveTypeId" class="form-control" required>
                                            <option value="">-- Select a Leave Type --</option>
                                        </select>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="startDate">Start Date</label>
                                                <input type="date" id="startDate" name="startDate" class="form-control" required min="<?php echo $today; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="endDate">End Date</label>
                                                <input type="date" id="endDate" name="endDate" class="form-control" required min="<?php echo $today; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="daysRequested" id="days-requested-label">Total Days</label>
                                        <input type="text" id="daysRequested" name="daysRequested" class="form-control" readonly title="This is calculated automatically based on the selected leave policy.">
                                    </div>
                                    <div class="form-group">
                                        <label for="reason">Reason</label>
                                        <textarea id="reason" name="reason" class="form-control" rows="4" required placeholder="Provide a brief reason for your leave request."></textarea>
                                    </div>
                                    <hr>
                                    <button type="submit" class="btn btn-primary btn-submit pull-right">
                                        <i class="fa fa-paper-plane"></i> Submit Request
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Leave Policy & Balances -->
                    <div class="col-md-4 col-lg-5">
                        <div class="panel panel-white">
                            <div class="panel-heading"><h5 class="panel-title">My Leave Balances</h5></div>
                            <div class="panel-body">
                                <div class="table-responsive">
                                    <table class="table table-condensed">
                                        <thead><tr><th>Leave Type</th><th>Available</th></tr></thead>
                                        <tbody id="leaveBalancesTableBody">
                                            <tr><td colspan="2" class="text-center">Loading...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="panel panel-white leave-details-card" id="leaveDetailsContainer" style="display: none;">
                            <div class="panel-heading"><h5 class="panel-title">Policy Details</h5></div>
                            <div class="panel-body" id="leavePolicyDetailsBody"></div>
                        </div>
                    </div>
                </div>

                <!-- Recent Leave History -->
                <div class="row">
                     <div class="col-md-12">
                        <div class="panel panel-white">
                            <div class="panel-heading"><h5 class="panel-title">My Recent Leave History</h5></div>
                            <div class="panel-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead><tr><th>Leave Type</th><th>Dates</th><th>Days</th><th>Status</th><th>Submitted On</th><th>Last Updated</th><th>Actions</th></tr></thead>
                                        <tbody id="my-requests-body">
                                            <tr><td colspan="7" class="text-center">Loading history...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <?php include('include/footer.php'); ?>
</div>
<!-- Loading Indicator -->
<div id="loading-indicator" style="display: none;">
    <i class="fa fa-spinner fa-spin"></i>
    <p style="margin-top: 10px;">Loading Data...</p>
</div>

<!-- JS Includes -->
<script src="../vendor/jquery/jquery.min.js"></script>
<script src="../vendor/bootstrap/js/bootstrap.min.js"></script>
<script src="../vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="../vendor/jquery-cookie/jquery.cookie.js"></script>
<script src="../assets/js/main.js"></script>

<!-- ======================================================================== -->
<!-- **REWRITTEN** Page-specific JavaScript with Policy-Aware Calculations  -->
<!-- ======================================================================== -->
<script>
$(document).ready(function() {
    Main.init();
    const API_URL = 'api/staff_leave_handler.php';
    let leaveTypesData = []; // Cache for leave type policies

    // --- UI HELPER FUNCTIONS ---
    const showLoader = () => $('#loading-indicator').fadeIn(200);
    const hideLoader = () => $('#loading-indicator').fadeOut(200);

    const showAlert = (containerId, message, type = 'danger') => {
        const alertHtml = `<div class="alert alert-${type} alert-dismissible" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>${message}</div>`;
        $(containerId).hide().html(alertHtml).slideDown(300);
    };

    function handleAjaxError(xhr) {
        console.error("AJAX Error:", xhr);
        let message = 'An unexpected error occurred. Please contact the administrator.';
        if (xhr.responseJSON && xhr.responseJSON.message) { message = `Error: ${xhr.responseJSON.message}`; }
        showAlert('#system-status-container', message, 'danger');
    }

    // --- API & DATA HANDLING ---
    function loadInitialData() {
        showLoader();
        Promise.all([
            $.getJSON(API_URL, { action: 'getLeaveTypes' }),
            $.getJSON(API_URL, { action: 'getLeaveBalances' }),
            $.getJSON(API_URL, { action: 'getMyRequests' })
        ]).then(([typesRes, balancesRes, requestsRes]) => {
            populateLeaveTypes(typesRes);
            populateLeaveBalances(balancesRes);
            populateMyRequests(requestsRes);
        }).catch(handleAjaxError).finally(hideLoader);
    }

    function populateLeaveTypes(response) {
        if (!response.success) return;
        leaveTypesData = response.leaveTypes; // Cache the policy data
        const $select = $('#leaveType').empty().append('<option value="">-- Select a Leave Type --</option>');
        leaveTypesData.forEach(lt => $select.append(`<option value="${lt.leave_type_id}">${lt.type_name}</option>`));
    }

    function populateLeaveBalances(response) {
        const tbody = $('#leaveBalancesTableBody').empty();
        if (response.success && response.balances.length > 0) {
            response.balances.forEach(b => tbody.append(`<tr><td>${b.type_name}</td><td><strong>${b.current_balance} days</strong></td></tr>`));
        } else {
            tbody.append('<tr><td colspan="2" class="text-center">No balance data available.</td></tr>');
        }
    }

    function populateMyRequests(response) {
        const tbody = $('#my-requests-body').empty();
        if (response.success && response.data.length > 0) {
            response.data.forEach(r => {
                const updatedAt = (r.updated_at && r.updated_at !== r.created_at) ? r.updated_at : 'N/A';
                const printButton = r.status === 'approved' 
                    ? `<a href="print_leave_slip.php?id=${r.leave_request_id}" target="_blank" class="btn btn-xs btn-info" title="Print Leave Slip"><i class="fa fa-print"></i></a>`
                    : '';
                tbody.append(`<tr><td>${r.type_name}</td><td>${r.start_date} to ${r.end_date}</td><td>${r.days_requested}</td><td><span class="status-badge status-${r.status}">${r.status}</span></td><td>${r.created_at}</td><td>${updatedAt}</td><td>${printButton}</td></tr>`);
            });
        } else {
            tbody.append('<tr><td colspan="7" class="text-center">You have no recent leave requests.</td></tr>');
        }
    }

    /**
     * **COMPLETELY REWRITTEN FOR TIMEZONE-SAFETY AND ACCURACY**
     */
    function calculateDaysRequested() {
        const startDateVal = $('#startDate').val();
        const endDateVal = $('#endDate').val();
        const leaveTypeId = $('#leaveType').val();
        const $daysInput = $('#daysRequested');
        const $daysLabel = $('#days-requested-label');

        if (!startDateVal || !endDateVal || !leaveTypeId) {
            $daysInput.val('');
            $daysLabel.text('Total Days');
            return;
        }
        
        // **FIX:** Create dates in a timezone-safe way by appending time.
        const startDate = new Date(startDateVal + 'T00:00:00');
        const endDate = new Date(endDateVal + 'T00:00:00');

        if (endDate < startDate) {
            $daysInput.val('Invalid date range');
            return;
        }

        const policy = leaveTypesData.find(lt => lt.leave_type_id == leaveTypeId);
        if (!policy) {
            $daysInput.val('Select a leave type');
            return;
        }

        const includeSaturdays = policy.includes_saturdays === 'YES';
        const includeSundays = policy.includes_sundays === 'YES';
        
        let labelText = 'Total Days';
        if (!includeSaturdays && !includeSundays) {
            labelText += ' (excluding weekends)';
        } else if (includeSaturdays && !includeSundays) {
            labelText += ' (excluding Sundays)';
        } else if (!includeSaturdays && includeSundays) {
            labelText += ' (excluding Saturdays)';
        } else {
            labelText += ' (including all days)';
        }
        $daysLabel.text(labelText);

        let count = 0;
        const curDate = new Date(startDate.getTime());

        while (curDate <= endDate) {
            const dayOfWeek = curDate.getDay(); // 0 = Sunday, 6 = Saturday
            
            if (dayOfWeek === 0 && !includeSundays) {
                // Skip Sunday if policy says so
            } else if (dayOfWeek === 6 && !includeSaturdays) {
                // Skip Saturday if policy says so
            } else {
                count++;
            }
            curDate.setDate(curDate.getDate() + 1);
        }
        $daysInput.val(count);
    }

    // --- EVENT HANDLERS ---
    $('#startDate, #endDate, #leaveType').on('change', function() {
        if ($(this).is('#startDate')) {
            const startDateValue = $(this).val();
            if (startDateValue) {
                $('#endDate').prop('min', startDateValue);
                if ($('#endDate').val() < startDateValue) { $('#endDate').val(''); }
            }
        }
        if ($(this).is('#leaveType')) {
            const selectedId = $(this).val();
            const detailsDiv = $('#leaveDetailsContainer');
            if (selectedId && leaveTypesData.length > 0) {
                const policy = leaveTypesData.find(lt => lt.leave_type_id == selectedId);
                if (policy) {
                    $('#leavePolicyDetailsBody').html(`<p><strong>Allotment:</strong> ${policy.annual_allotment} days</p><p><strong>Carry Over:</strong> ${policy.max_carry_over} days</p>`);
                    detailsDiv.slideDown();
                } else { detailsDiv.slideUp(); }
            } else { detailsDiv.slideUp(); }
        }
        calculateDaysRequested();
    });

    $('#leaveRequestForm').on('submit', function(e) {
        e.preventDefault();
        if (!$('#leaveType').val()) { showAlert('#request-status-alert', 'Please select a leave type.'); return; }
        if (!$('#startDate').val() || !$('#endDate').val()) { showAlert('#request-status-alert', 'Please select a start and end date.'); return; }
        if (new Date($('#endDate').val()) < new Date($('#startDate').val())) { showAlert('#request-status-alert', 'End date cannot be before start date.'); return; }
        const days = parseFloat($('#daysRequested').val());
        if (isNaN(days) || days <= 0) { showAlert('#request-status-alert', 'The date range must result in at least one valid leave day.', 'warning'); return; }
        if (!$('#reason').val().trim()) { showAlert('#request-status-alert', 'Please provide a reason.'); return; }
        
        const $submitBtn = $('.btn-submit'), originalBtnText = $submitBtn.html();
        $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Submitting...');
        
        $.ajax({ url: API_URL, method: 'POST', data: $(this).serialize() + '&action=submitLeaveRequest', dataType: 'json'
        }).done(response => {
            if (response && response.success) {
                showAlert('#request-status-alert', response.message, 'success');
                $('#leaveRequestForm')[0].reset();
                $('#daysRequested').val('');
                $('#days-requested-label').text('Total Days');
                $('#leaveDetailsContainer').slideUp();
                loadInitialData();
            } else { showAlert('#request-status-alert', response.message || 'An unknown error occurred.', 'danger'); }
        }).fail(handleAjaxError).always(() => { $submitBtn.prop('disabled', false).html(originalBtnText); });
    });

    // --- INITIALIZATION ---
    loadInitialData();
});
</script>
</body>
</html>