
<?php
require_once('session_auth.php'); 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Attendance | HMS</title>
    <meta charset="utf-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="../vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../vendor/fontawesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../vendor/themify-icons/themify-icons.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/plugins.css">
    <link rel="stylesheet" href="../assets/css/themes/theme-1.css" id="skin_color" />
    <style>
        .summary-card { text-align: center; padding: 20px; border-radius: 8px; color: white; margin-bottom: 20px; }
        .summary-card .summary-value { font-size: 2.5em; font-weight: bold; }
        .summary-card .summary-title { font-size: 1.1em; text-transform: uppercase; }
        .bg-present { background-color: #5cb85c; } .bg-absent { background-color: #d9534f; }
        .bg-hours { background-color: #337ab7; } .bg-late { background-color: #f0ad4e; }
        .bg-leave { background-color: #5bc0de; } .bg-working { background-color: #777; }
        #loading-indicator { display: none; text-align: center; padding: 20px; font-size: 1.2em; }
        #error-container { display: none; }
        /* Label colors for statuses */
        .label-leave { background-color: #5bc0de; }
        .label-weekend, .label-day-off { background-color: #777; }
        .label-holiday { background-color: #6f42c1; color: white; }
    </style>
</head>
<body>
    <div id="app">
    <?php include('include/sidebar.php'); ?>
        <div class="app-content">
            <?php include('include/header.php'); ?>
            <div class="main-content">
                <div class="wrap-content container" id="container">
                    <section id="page-title">
                        <div class="row"><div class="col-sm-8"><h1 class="mainTitle">My Attendance Records</h1></div>
                            <ol class="breadcrumb"><li><span>Staff</span></li><li class="active"><span>My Attendance</span></li></ol>
                        </div>
                    </section>

                    <div class="panel panel-white">
                        <div class="panel-body">
                            <form id="filterForm" class="form-inline">
                                <div class="form-group"><label for="month">Month:</label>
                                    <select id="month" name="month" class="form-control">
                                        <?php $currentMonth=date('n'); for($m=1;$m<=12;$m++){$selected=($m==$currentMonth)?'selected':'';echo '<option value="'.$m.'" '.$selected.'>'.date('F',mktime(0,0,0,$m,10)).'</option>';}?>
                                    </select>
                                </div>
                                <div class="form-group"><label for="year">Year:</label>
                                    <select id="year" name="year" class="form-control">
                                        <?php $currentYear=date('Y'); for($y=$currentYear;$y>=$currentYear-5;$y--){$selected=($y==$currentYear)?'selected':'';echo '<option value="'.$y.'" '.$selected.'>'.$y.'</option>';}?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Apply</button>
                                <button type="button" id="resetFilters" class="btn btn-default">Current Month</button>
                            </form>
                        </div>
                    </div>

                    <div class="row" id="summary-container"></div>

                    <div class="panel panel-white">
                        <div class="panel-heading"><h5 class="panel-title">Detailed Records</h5></div>
                        <div class="panel-body">
                            <div id="error-container" class="alert alert-danger"></div>
                            <div id="loading-indicator"><i class="fa fa-spinner fa-spin"></i> Loading records...</div>
                            <div class="table-responsive" id="table-container" style="display:none;">
                                <table class="table table-striped table-hover">
                                    <thead><tr><th>Date</th><th>Status</th><th>Check In</th><th>Check Out</th><th>Working Hours</th><th>Notes</th></tr></thead>
                                    <tbody id="attendanceTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../vendor/jquery/jquery.min.js"></script>
    <script src="../vendor/bootstrap/js/bootstrap.min.js"></script>
    <script>
    $(document).ready(function() {
        const currentMonth = new Date().getMonth() + 1;
        const currentYear = new Date().getFullYear();

        function loadAttendanceData() {
            const month = $('#month').val(); const year = $('#year').val();
            $('#loading-indicator').show(); $('#table-container').hide(); $('#error-container').hide();
            $('#summary-container').empty(); $('#attendanceTableBody').empty();

            $.ajax({
                url: 'api/attendance_handler.php', method: 'GET',
                data: { action: 'getAttendance', month: month, year: year },
                dataType: 'json',
                success: function(response) {
                    $('#loading-indicator').hide();
                    if (response.success && response.data) {
                        populateSummaryCards(response.data.summary);
                        populateAttendanceTable(response.data.records);
                        $('#table-container').show();
                    } else { $('#error-container').text('Error: ' + response.message).show(); }
                },
                error: function(xhr) {
                    $('#loading-indicator').hide();
                    const msg = xhr.responseJSON ? xhr.responseJSON.message : 'A server error occurred.';
                    $('#error-container').text('Error: ' + msg).show();
                }
            });
        }

        function populateSummaryCards(summary) {
            // **MODIFIED:** Added the "Missed Punches" card.
            const summaryHtml = `
                <div class="col-lg-2 col-md-4 col-sm-6"><div class="summary-card bg-working"><div class="summary-value">${summary.working_days}</div><div class="summary-title">Scheduled Days</div></div></div>
                <div class="col-lg-2 col-md-4 col-sm-6"><div class="summary-card bg-present"><div class="summary-value">${summary.present_days}</div><div class="summary-title">Present Days</div></div></div>
                <div class="col-lg-2 col-md-4 col-sm-6"><div class="summary-card bg-absent"><div class="summary-value">${summary.absent_days}</div><div class="summary-title">Absent Days</div></div></div>
                <div class="col-lg-2 col-md-4 col-sm-6"><div class="summary-card bg-leave"><div class="summary-value">${summary.leave_days}</div><div class="summary-title">Leave Days</div></div></div>
                <div class="col-lg-2 col-md-4 col-sm-6"><div class="summary-card bg-late"><div class="summary-value">${summary.late_arrivals}</div><div class="summary-title">Late Arrivals</div></div></div>
                <div class="col-lg-2 col-md-4 col-sm-6"><div class="summary-card bg-absent"><div class="summary-value">${summary.missed_punches || 0}</div><div class="summary-title">Missed Punches</div></div></div>
            `;
            $('#summary-container').html(summaryHtml);
        }

        function populateAttendanceTable(records) {
            const tbody = $('#attendanceTableBody');
            if (records.length === 0) {
                tbody.html('<tr><td colspan="6" class="text-center">No records found for this period.</td></tr>');
                return;
            }
            let tableRows = '';
            records.forEach(record => {
                let labelClass = 'default';
                // **MODIFIED:** Added a case for 'Missed Punch' to display an orange warning label.
                switch(record.status) {
                    case 'Present': labelClass = 'success'; break;
                    case 'Absent': labelClass = 'danger'; break;
                    case 'Late':
                    case 'Early Out':
                    case 'Late & Early Out':
                    case 'Missed Punch': // Added new status here
                        labelClass = 'warning'; 
                        break;
                    case 'On Leave': labelClass = 'leave'; break;
                    case 'Weekend': labelClass = 'weekend'; break;
                    case 'Holiday': labelClass = 'holiday'; break;
                    case 'Day Off': labelClass = 'day-off'; break;
                }
                tableRows += `
                    <tr>
                        <td>${record.date}</td>
                        <td><span class="label label-${labelClass}">${record.status}</span></td>
                        <td>${record.check_in || 'N/A'}</td>
                        <td>${record.check_out || 'N/A'}</td>
                        <td>${record.working_hours || 'N/A'}</td>
                        <td>${record.notes || ''}</td>
                    </tr>
                `;
            });
            tbody.html(tableRows);
        }

        $('#filterForm').on('submit', function(e) { e.preventDefault(); loadAttendanceData(); });
        $('#resetFilters').on('click', function() { $('#month').val(currentMonth); $('#year').val(currentYear); loadAttendanceData(); });
        loadAttendanceData();
    });
    </script>
</body>
</html>