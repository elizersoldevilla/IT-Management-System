<?php
require_once __DIR__ . '/../views/header.php';
require_login();
?>

<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>

<div class="mb-4">
    <h1 class="h3 mb-0">IT Calendar</h1>
</div>

<div class="glass-panel p-4">
    <div id='calendar'></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: 'api/calendar_events.php',
        eventClick: function(info) {
            if (info.event.url) {
                window.open(info.event.url, "_self");
                info.jsEvent.preventDefault();
            }
        }
    });
    calendar.render();
});
</script>

<style>
    /* Dark Mode Calendar Overrides */
    .fc-theme-standard td, .fc-theme-standard th {
        border-color: rgba(255, 255, 255, 0.1);
    }
    .fc-col-header-cell-cushion, .fc-daygrid-day-number {
        color: #e4e6eb;
        text-decoration: none;
    }
    .fc-button-primary {
        background-color: #3a3b3c !important;
        border-color: #3a3b3c !important;
    }
    .fc-button-primary:hover {
        background-color: #4e4f50 !important;
        border-color: #4e4f50 !important;
    }
    .fc-button-active {
        background-color: #2e89ff !important;
        border-color: #2e89ff !important;
    }
    .fc-day-today {
        background-color: rgba(46, 137, 255, 0.1) !important;
    }
</style>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
