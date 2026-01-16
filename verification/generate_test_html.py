import json
import os

# Load language file
with open('lang/ja.json', 'r') as f:
    lang = json.load(f)

html_content = f"""<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        /* Minimal css from style.css equivalent */
        body {{ font-family: sans-serif; }}
        .bg-primary {{ background-color: #1a73e8; }}
        .text-primary {{ color: #1a73e8; }}
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto bg-white rounded-xl shadow-lg p-8">
        <h1 class="text-3xl font-bold mb-4">{lang['index_welcome_title']}</h1>
        <p class="mb-6">{lang['index_description']}</p>

        <div class="border p-4 rounded mb-4">
            <h2 class="text-xl font-bold mb-2">{lang['index_search_title']}</h2>
            <form class="flex gap-4 items-end">
                <div>
                    <label class="block text-sm font-bold">{lang['form_check_in']}</label>
                    <input type="date" class="border p-2 rounded">
                </div>
                <div>
                    <label class="block text-sm font-bold">{lang['form_check_out']}</label>
                    <input type="date" class="border p-2 rounded">
                </div>
                <button class="bg-blue-600 text-white font-bold py-2 px-4 rounded">
                    {lang['btn_search']}
                </button>
            </form>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="border p-4 rounded">
                <h3 class="font-bold">{lang['index_pricing_title']}</h3>
                <p>{lang['pricing_adult']}: ¥4,500</p>
            </div>
            <div class="border p-4 rounded">
                <h3 class="font-bold">{lang['admin_hint_recalc']}</h3>
            </div>
        </div>
    </div>
</body>
</html>
"""

with open('verification/test_index.html', 'w') as f:
    f.write(html_content)
