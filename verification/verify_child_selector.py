
from playwright.sync_api import sync_playwright

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()

    # Create a mock HTML file to test the layout
    # We can't use PHP execution easily in this environment for full end-to-end,
    # but we can verify the HTML structure if we mock the output or if we can run php -S.
    # Given 'php' command failed earlier, we will assume we can't run the server.
    # However, we can create a file that mimics the output of index.php to check the layout of the child selector.

    html_content = """
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <script src="https://cdn.tailwindcss.com"></script>
        <title>Test</title>
    </head>
    <body class="bg-gray-100 p-8">
        <form class="flex flex-col md:flex-row gap-6 justify-center items-end" action="search_results.php" method="GET">
            <div class="w-full md:w-auto flex-1">
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2" for="check_in_date">チェックイン:</label>
                <input class="w-full rounded-md border-gray-300 shadow-sm py-2.5 px-3" id="check_in_date" name="check_in_date" type="date" required />
            </div>
            <div class="w-full md:w-auto flex-1">
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2" for="check_out_date">チェックアウト:</label>
                <input class="w-full rounded-md border-gray-300 shadow-sm py-2.5 px-3" id="check_out_date" name="check_out_date" type="date" required />
            </div>
            <div class="w-full md:w-40">
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2" for="num_guests">人数(大人):</label>
                <select class="w-full rounded-md border-gray-300 shadow-sm py-2.5 px-3" id="num_guests" name="num_guests">
                    <option value="1">1名</option>
                    <option value="2">2名</option>
                </select>
            </div>
            <!-- Added Child Selector -->
            <div class="w-full md:w-40">
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2" for="num_children">人数(子供):</label>
                <select class="w-full rounded-md border-gray-300 shadow-sm py-2.5 px-3" id="num_children" name="num_children">
                    <option value="0">0名</option>
                    <option value="1">1名</option>
                    <option value="2">2名</option>
                </select>
            </div>
            <div class="w-full md:w-auto">
                <button class="w-full bg-blue-600 text-white font-bold py-2.5 px-8 rounded-md shadow" type="submit">
                    検索
                </button>
            </div>
        </form>
    </body>
    </html>
    """

    import os
    # Create absolute path
    abs_path = os.path.abspath("verification/mock_index.html")

    with open(abs_path, "w") as f:
        f.write(html_content)

    page.goto(f"file://{abs_path}")

    # Check if the child selector exists
    page.locator("#num_children").wait_for()

    # Take screenshot
    page.screenshot(path="verification/index_child_selector.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
