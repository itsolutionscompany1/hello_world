<?php
require_once('session_auth.php'); 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Leave Management | Zion HMS</title>
    <!-- Your standard CSS files -->
    <link href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="vendor/themify-icons/themify-icons.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/plugins.css">
    <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
    <style>
        .pending-requests-badge { background-color: #f0ad4e; color: white; padding: 5px 15px; border-radius: 20px; font-weight: bold; }
        .action-btn { padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; margin-right: 5px; font-size: 12px; color: white; }
        .approve-btn { background-color: #5cb85c; } .reject-btn { background-color: #d9534f; } .adjust-btn { background-color: #337ab7; }
        .search-filter { display: flex; gap: 15px; margin-bottom: 20px; align-items: center; max-width: 600px; }
        .policy-settings { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; }
        .policy-group h5 { border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; font-weight: bold; }
        .policy-item { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .policy-item label { flex-basis: 60%; }
        .policy-item input[type="number"] { width: 80px; text-align: center; }
        .policy-checkbox-group { display: flex; flex-direction: column; }
        .policy-checkbox-group .checkbox { margin-top: 0; margin-bottom: 10px; }
        .policy-checkbox-group label { font-weight: normal; }
        .status-pending { background-color: #f0ad4e; } .status-approved { background-color: #5cb85c; } .status-rejected { background-color: #d9534f; }
        .status-pending, .status-approved, .status-rejected { color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.9em; }
        #loading-indicator { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1051; background: rgba(0,0,0,0.6); color: white; padding: 20px; border-radius: 8px; }
        .modal-body .form-group { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .panel-title .badge { margin-left: 10px; }
        .tab-content { padding-top: 20px; }
    </style>
</head>
<body>
    <div id="app">
        <?php include('../staff/include/sidebar.php'); ?>
        <div class="app-content">
            <?php include('include/header.php'); ?>
            <div class="main-content">
                <div class="wrap-content container" id="container">
                    
                    <section id="page-title"><div class="row"><div class="col-sm-8"><h1 class="mainTitle">Leave Management Dashboard</h1></div><ol class="breadcrumb"><li><span>Admin</span></li><li class="active"><span>Leave</span></li></ol></div></section>

                    <div class="row">
                        <div class="col-md-12"><div class="panel panel-white"><div class="panel-heading"><h5 class="panel-title">Pending Leave Requests <span class="badge pending-requests-badge">0</span></h5></div><div class="panel-body"><div class="search-filter"><input type="text" id="pending-search-term" class="form-control" placeholder="Search by employee name..."><button id="search-pending-btn" class="btn btn-primary btn-sm">Search</button></div><div class="table-responsive"><table class="table table-hover"><thead><tr><th>Employee</th><th>Leave Type</th><th>Dates</th><th>Days</th><th>Actions</th></tr></thead><tbody id="pending-leaves-body"></tbody></table></div></div></div></div>
                    </div>

                    <div class="row">
                        <div class="col-md-7">
                            <div class="panel panel-white">
                                <div class="panel-body">
                                    <ul class="nav nav-tabs">
                                        <li class="active"><a data-toggle="tab" href="#balances">Employee Balances</a></li>
                                        <li><a data-toggle="tab" href="#all-requests">Request History</a></li>
                                    </ul>
                                    <div class="tab-content">
                                        <div id="balances" class="tab-pane fade in active">
                                            <div class="search-filter"><input type="text" id="employee-search" class="form-control" placeholder="Search by employee name..."></div>
                                            <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Employee</th><th>Balances</th><th>Actions</th></tr></thead><tbody id="leave-balances-body"></tbody></table></div>
                                        </div>
                                        <div id="all-requests" class="tab-pane fade">
                                            <div class="search-filter">
                                                <input type="text" id="history-search-term" class="form-control" placeholder="Search by employee name...">
                                                <select id="history-status-filter" class="form-control"><option value="all">All</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select>
                                            </div>
                                            <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Employee</th><th>Leave Type</th><th>Dates</th><th>Status</th></tr></thead><tbody id="history-leaves-body"></tbody></table></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                             <div class="panel panel-white">
                                <div class="panel-heading"><h5 class="panel-title">Leave Policy Settings</h5></div>
                                <div class="panel-body">
                                     <form id="leave-policy-form">
                                        <div class="policy-settings">
                                            <div class="policy-group" id="annual-allotments-section"><h5>Annual Allotments</h5></div>
                                            <div class="policy-group" id="carry-over-rules-section"><h5>Max Carry-Over Days</h5></div>
                                            <div class="policy-group" id="day-inclusion-rules-section"><h5>Day Calculation Rules</h5></div>
                                        </div>
                                        <hr/>
                                        <button type="submit" class="btn btn-success btn-block">Save Policy Settings</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        <?php include('include/footer.php'); ?>
    </div>
    <div id="loading-indicator"><i class="fa fa-spinner fa-spin"></i> Loading...</div>

    <div class="modal fade" id="adjustBalanceModal" tabindex="-1" role="dialog">
      <div class="modal-dialog" role="document"><div class="modal-content">
          <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Adjust Leave Balances</h4></div>
          <div class="modal-body">
            <p><strong>Employee:</strong> <span id="modal-employee-name"></span></p>
            <form id="adjust-balance-form" class="form-horizontal"><input type="hidden" id="modal-empid" name="empId"><div id="modal-balances-container"></div><div class="form-group" style="margin-top:20px;"><label for="modal-reason" class="col-sm-12">Reason for Adjustment</label><div class="col-sm-12"><textarea class="form-control" id="modal-reason" name="reason" required></textarea></div></div></form>
          </div>
          <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button><button type="button" class="btn btn-primary" id="save-adjustment-btn">Save</button></div>
      </div></div>
    </div>
    
    <!-- JS Includes -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
    <script src="vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="vendor/jquery-cookie/jquery.cookie.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
    $(document).ready(function() {
        Main.init();
        const API_URL = 'api/leave_handler.php';

        loadPendingRequests(); loadHistoryRequests(); fetchLeaveBalances(); fetchAndPopulateLeavePolicy();

        $('#search-pending-btn').on('click', () => loadPendingRequests($('#pending-search-term').val()));
        $('#history-status-filter, #history-search-term').on('change keyup', () => loadHistoryRequests($('#history-search-term').val(), $('#history-status-filter').val()));
        $('#employee-search').on('keyup', () => fetchLeaveBalances($('#employee-search').val()));
        $('#leave-policy-form').on('submit', function(e) { e.preventDefault(); if (confirm('Are you sure?')) saveLeavePolicy($(this).serialize()); });
        $(document).on('click', '.approve-btn, .reject-btn', handleLeaveApproval);
        $(document).on('click', '.adjust-btn', handleAdjustBalanceClick);
        $('#save-adjustment-btn').on('click', saveBalanceAdjustment);

        function loadPendingRequests(searchTerm = '') { showLoader(); $.getJSON(API_URL, { action: 'getLeaveRequests', status: 'pending', searchTerm: searchTerm }).done(populatePendingTable).fail(()=>alert('Error loading pending requests.')).always(hideLoader); }
        function loadHistoryRequests(searchTerm = '', status = 'all') { showLoader(); $.getJSON(API_URL, { action: 'getLeaveRequests', status: status, searchTerm: searchTerm }).done(populateHistoryTable).fail(()=>alert('Error loading history.')).always(hideLoader); }
        
        function populatePendingTable(response) {
            const tbody = $('#pending-leaves-body').empty();
            $('.pending-requests-badge').text('0');
            if (response.success && response.data.length > 0) {
                $('.pending-requests-badge').text(response.data.length);
                response.data.forEach(req => tbody.append(`<tr><td>${req.employee_name}</td><td>${req.type_name}</td><td>${req.start_date} to ${req.end_date}</td><td>${req.days_requested}</td><td><button class="action-btn approve-btn" data-id="${req.request_id}">Approve</button><button class="action-btn reject-btn" data-id="${req.request_id}">Reject</button></td></tr>`));
            } else { tbody.append('<tr><td colspan="5" class="text-center">No pending requests found.</td></tr>'); }
        }
        function populateHistoryTable(response) {
            const tbody = $('#history-leaves-body').empty();
            if (response.success && response.data.length > 0) {
                response.data.forEach(req => tbody.append(`<tr><td>${req.employee_name}</td><td>${req.type_name}</td><td>${req.start_date} to ${req.end_date}</td><td><span class="status-${req.status.toLowerCase()}">${req.status}</span></td></tr>`));
            } else { tbody.append('<tr><td colspan="4" class="text-center">No requests found.</td></tr>'); }
        }
        function fetchAndPopulateLeavePolicy() {
            $.getJSON(API_URL, { action: 'getLeavePolicy' }).done(function(response) {
                if (response.success) {
                    const allotmentsDiv = $('#annual-allotments-section').empty().append('<h5>Annual Allotments</h5>');
                    const carryOverDiv = $('#carry-over-rules-section').empty().append('<h5>Max Carry-Over Days</h5>');
                    const inclusionDiv = $('#day-inclusion-rules-section').empty().append('<h5>Day Calculation Rules</h5>');
                    response.leave_types.forEach(lt => {
                        allotmentsDiv.append(`<div class="policy-item"><label>${lt.type_name}:</label><input type="number" step="0.5" class="form-control" name="allotments[${lt.leave_type_id}]" value="${lt.annual_allotment}"></div>`);
                        carryOverDiv.append(`<div class="policy-item"><label>${lt.type_name}:</label><input type="number" step="0.5" class="form-control" name="carry_over[${lt.leave_type_id}]" value="${lt.max_carry_over}"></div>`);
                        const sat_checked = lt.includes_saturdays === 'YES' ? 'checked' : '';
                        const sun_checked = lt.includes_sundays === 'YES' ? 'checked' : '';
                        const hol_checked = lt.includes_holidays === 'YES' ? 'checked' : '';
                        const checkboxHtml = `<div class="policy-checkbox-group"><strong>${lt.type_name}</strong><div class="checkbox clip-check check-primary"><input type="checkbox" id="sat_${lt.leave_type_id}" name="saturdays[${lt.leave_type_id}]" ${sat_checked}><label for="sat_${lt.leave_type_id}">Include Saturdays</label></div><div class="checkbox clip-check check-primary"><input type="checkbox" id="sun_${lt.leave_type_id}" name="sundays[${lt.leave_type_id}]" ${sun_checked}><label for="sun_${lt.leave_type_id}">Include Sundays</label></div><div class="checkbox clip-check check-primary"><input type="checkbox" id="hol_${lt.leave_type_id}" name="holidays[${lt.leave_type_id}]" ${hol_checked}><label for="hol_${lt.leave_type_id}">Include Holidays</label></div></div>`;
                        inclusionDiv.append(checkboxHtml);
                    });
                }
            });
        }
        function saveLeavePolicy(formData) {
            showLoader();
            $.post(API_URL + '?action=saveLeavePolicy', formData)
             .done(response => { alert(response.message || 'An error occurred.'); if(response.success) fetchAndPopulateLeavePolicy(); })
             .fail(()=>alert('Server error while saving policy.'))
             .always(hideLoader);
        }
        function fetchLeaveBalances(searchTerm = '') {
            showLoader();
            $.getJSON(API_URL, { action: 'getEmployeeBalances', searchTerm: searchTerm }).done(function(response) {
                const tbody = $('#leave-balances-body').empty();
                if (response.success && response.employees.length > 0) {
                    response.employees.forEach(emp => {
                        let balancesHtml = emp.balances && Object.keys(emp.balances).length > 0 
                            ? Object.values(emp.balances).map(bal => `<div><small><strong>${bal.name}:</strong> ${bal.balance}</small></div>`).join('') 
                            : 'No balance records found.';
                        const row = $(`<tr><td>${emp.Surname}, ${emp.OtherNames}</td><td>${balancesHtml}</td><td><button class="action-btn adjust-btn">Adjust</button></td></tr>`);
                        row.data('employee', emp); 
                        tbody.append(row);
                    });
                } else { 
                    tbody.append('<tr><td colspan="3" class="text-center">No employees found.</td></tr>'); 
                }
            }).fail(()=>alert('Error fetching balances.')).always(hideLoader);
        }
        function handleAdjustBalanceClick() {
            const empData = $(this).closest('tr').data('employee');
            $('#modal-employee-name').text(`${empData.Surname}, ${empData.OtherNames}`);
            $('#modal-empid').val(empData.empid);
            const container = $('#modal-balances-container').empty();
            $.getJSON(API_URL, { action: 'getLeavePolicy' }).done(function(policy) {
                if (policy.success) {
                    policy.leave_types.forEach(lt => {
                        const balanceData = empData.balances[lt.leave_type_id];
                        const currentBalance = balanceData ? balanceData.balance : lt.annual_allotment;
                        container.append(`<div class="form-group"><label class="col-sm-7 control-label">${lt.type_name}</label><div class="col-sm-5"><input type="number" step="0.5" class="form-control" name="balances[${lt.leave_type_id}]" value="${currentBalance}"></div></div>`);
                    });
                    $('#adjustBalanceModal').modal('show');
                }
            });
        }

        /**
         * **MODIFIED:** This function now prompts for a reason on rejection.
         */
        function handleLeaveApproval() {
            const requestId = $(this).data('id');
            const isApproval = $(this).hasClass('approve-btn');
            const status = isApproval ? 'approved' : 'rejected';
            let reason = '';

            const performUpdate = () => {
                showLoader();
                $.post(API_URL, { 
                    action: 'updateLeaveStatus', 
                    requestId: requestId, 
                    status: status,
                    reason: reason // Send the reason to the backend
                }).done(function(res) { 
                    alert(res.message); 
                    if(res.success) { 
                        loadPendingRequests(); // Refresh both tables
                        loadHistoryRequests();
                    } 
                }).fail(() => alert('Server error occurred.'))
                .always(hideLoader);
            };

            if (isApproval) {
                if (confirm(`Are you sure you want to APPROVE this request?`)) {
                    performUpdate();
                }
            } else {
                // For rejections, prompt for a reason
                reason = prompt("Please provide a reason for rejecting this leave request:");
                if (reason) { // Only proceed if a reason was entered
                    performUpdate();
                } else if (reason === '') {
                    alert('Rejection reason cannot be empty.');
                }
                // If the user clicks "Cancel" (reason is null), do nothing.
            }
        }

        function saveBalanceAdjustment() {
            const formData = $('#adjust-balance-form').serialize();
            showLoader();
            $.post(API_URL + '?action=adjustEmployeeBalances', formData)
                .done(function(response) {
                    alert(response.message);
                    if (response.success) {
                        $('#adjustBalanceModal').modal('hide');
                        fetchLeaveBalances($('#employee-search').val());
                    }
                })
                .fail(() => alert('Server error while saving adjustment.'))
                .always(hideLoader);
        }
        function showLoader() { $('#loading-indicator').show(); }
        function hideLoader() { $('#loading-indicator').hide(); }
    });
    </script>
</body>
</html>