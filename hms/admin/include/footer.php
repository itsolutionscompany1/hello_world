<footer>
    <div class="footer-inner">
        <div class="row">
            <!-- Left Side: Copyright and Branding -->
            <div class="col-sm-4 text-left">
                &copy; <?php echo date("Y"); ?> 
                <span class="text-bold">Medico Systems Ltd.</span>
            </div>

            <!-- Center: User and System Information -->
            <div class="col-sm-4 text-center">
                <?php if (isset($_SESSION['user_name'])): ?>
                    <span class="text-muted">
                        <i class="fa fa-user"></i> User: <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong> |
                        <i class="fa fa-laptop"></i> IP: <?php echo htmlspecialchars($_SERVER['REMOTE_ADDR']); ?>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Right Side: Live Clock and Go Top Button -->
            <div class="col-sm-4 text-right">
                <span id="live_clock" class="text-muted"></span>
                <span class="go-top"><i class="ti-angle-up"></i></span>
            </div>
        </div>
    </div>

    <!-- JavaScript for the Live Clock -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Function to format numbers with a leading zero
            function addLeadingZero(num) {
                return (num < 10) ? '0' + num : num;
            }

            // Function to update the clock
            function updateClock() {
                var now = new Date();
                var day = addLeadingZero(now.getDate());
                var month = addLeadingZero(now.getMonth() + 1); // JS months are 0-11
                var year = now.getFullYear();
                var hours = addLeadingZero(now.getHours());
                var minutes = addLeadingZero(now.getMinutes());
                var seconds = addLeadingZero(now.getSeconds());

                var dateTimeString = day + '-' + month + '-' + year + ' ' + hours + ':' + minutes + ':' + seconds;

                var clockElement = document.getElementById('live_clock');
                if (clockElement) {
                    clockElement.textContent = dateTimeString;
                }
            }

            // Update the clock every second
            setInterval(updateClock, 1000);

            // Initial call to display clock immediately
            updateClock();
        });
    </script>
</footer>

