from playwright.sync_api import sync_playwright

def create_mock_files():
    # Mock index.php only, focusing on Calendar Tap
    html_template = """
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
</head>
<body>
    <button id="toggle-calendar-btn">Toggle Calendar</button>
    <div id="search-calendar-container" class="hidden">
        <div id="search-calendar"></div>
    </div>

    <input type="date" id="check_in_date">
    <input type="date" id="check_out_date">

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('toggle-calendar-btn');
        const calendarContainer = document.getElementById('search-calendar-container');
        const calendarEl = document.getElementById('search-calendar');
        let calendar = null;

        toggleBtn.addEventListener('click', function() {
            calendarContainer.classList.remove('hidden');
            if (!calendar) {
                calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    selectable: true,
                    selectLongPressDelay: 0,
                    select: function(info) {
                        document.getElementById('check_in_date').value = info.startStr;
                        let endDate = new Date(info.start);
                        endDate.setDate(endDate.getDate() + 1);
                        document.getElementById('check_out_date').value = endDate.toISOString().split('T')[0];
                    },
                    dateClick: function(info) {
                        document.getElementById('check_in_date').value = info.dateStr;
                        let endDate = new Date(info.date);
                        endDate.setDate(endDate.getDate() + 1);
                        document.getElementById('check_out_date').value = endDate.toISOString().split('T')[0];
                    }
                });
                calendar.render();
            }
        });
    });
    </script>
</body>
</html>
    """

    with open('verification/mock_tap.html', 'w') as f:
        f.write(html_template)

def run_playwright():
    with sync_playwright() as p:
        iphone = p.devices['iPhone 12 Pro']
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(**iphone)
        page = context.new_page()

        import os
        cwd = os.getcwd()
        page.goto(f'file://{cwd}/verification/mock_tap.html')

        # Open calendar
        page.click('#toggle-calendar-btn')

        # Find a date cell (e.g., the 15th of the current month view)
        # Note: In FullCalendar, day cells have data-date attribute.
        # We need to wait for calendar to render.
        page.wait_for_selector('.fc-daygrid-day')

        # Tap a date. Let's pick a date element.
        # We'll use JS to find a valid date cell and tap it.
        # .fc-day-future ensures we click a future date if possible, or just any valid day.

        # Using page.tap() to simulate touch
        page.tap('.fc-daygrid-day[data-date]')

        # Verify input value
        check_in_val = page.input_value('#check_in_date')
        check_out_val = page.input_value('#check_out_date')

        print(f"Check-in set to: {check_in_val}")
        print(f"Check-out set to: {check_out_val}")

        if check_in_val and check_out_val:
            print("SUCCESS: Dates populated via Tap")
        else:
            print("FAILURE: Dates not populated")
            exit(1)

        browser.close()

if __name__ == '__main__':
    create_mock_files()
    run_playwright()
