from playwright.sync_api import sync_playwright
import os

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()

    # Mocking the rooms.php view with and without filter
    mock_html_filtered = """
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <title>Rooms - Filtered</title>
</head>
<body class="bg-gray-100 dark:bg-gray-900 font-sans antialiased text-gray-900 dark:text-gray-100">
<div class="max-w-6xl mx-auto my-8 px-4">
    <!-- Search Section Omitted -->

    <div class="flex items-center justify-between mb-8">
        <h2 class="text-3xl font-bold text-gray-800 dark:text-white border-l-4 border-blue-600 pl-4">お部屋一覧</h2>
        <a href="#" class="text-blue-600 hover:underline flex items-center gap-1">
            <span class="material-icons text-sm">undo</span>
            すべての部屋を表示
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
        <!-- Room 1 (Filtered) -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md hover:shadow-xl transition-shadow duration-300 overflow-hidden border border-gray-100 dark:border-gray-700 flex flex-col group">
             <div class="p-6">
                <h3 class="text-xl font-bold">Filtered Room Type A</h3>
                <p>Only rooms of Type A should be seen here.</p>
             </div>
        </div>
    </div>
</div>
</body>
</html>
    """

    file_path = os.path.abspath("verification/mock_rooms_filtered.html")
    with open(file_path, "w") as f:
        f.write(mock_html_filtered)

    page.goto(f"file://{file_path}")
    page.screenshot(path="verification/verification_rooms_filtered.png", full_page=True)
    browser.close()

with sync_playwright() as playwright:
    run(playwright)
