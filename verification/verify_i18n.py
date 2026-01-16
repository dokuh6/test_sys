from playwright.sync_api import sync_playwright
import os

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load the local HTML file
        file_path = os.path.abspath('verification/test_index.html')
        page.goto(f'file://{file_path}')

        # Verify text presence
        # "ようこそ、ゲストハウスマル正へ"
        if "ゲストハウスマル正" not in page.title() and "ようこそ" not in page.content():
            print("Warning: Expected Japanese text not found?")

        # Take screenshot
        page.screenshot(path='verification/verification.png')
        browser.close()

if __name__ == "__main__":
    run()
