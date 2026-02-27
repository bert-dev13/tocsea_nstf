# TOCSEA - Start ngrok tunnel (run this after adding your authtoken once)
# Prerequisites: Laravel must be running: php artisan serve --host=0.0.0.0 --port=8000
# First time only: ngrok config add-authtoken YOUR_TOKEN from https://dashboard.ngrok.com/get-started/your-authtoken

$env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")
ngrok http 8000
